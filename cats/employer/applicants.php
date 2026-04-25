<?php
require_once '../config.php';
require_login('employer');

$conn = db_connect();
$employer_id = $_SESSION['user_id'];

// Get jobs for this employer
$jobs_result = $conn->query("SELECT id, title FROM jobs WHERE employer_id = $employer_id AND is_active = 1");
$jobs = [];
while ($j = $jobs_result->fetch_assoc()) $jobs[] = $j;

$selected_job = isset($_GET['job_id']) ? (int)$_GET['job_id'] : ($jobs[0]['id'] ?? 0);

// Get applicants — evaluated first (sorted by score DESC), pending at the bottom
$applications = [];
if ($selected_job) {
    $stmt = $conn->prepare("
        SELECT a.id, a.applicant_id, ap.name, ap.email, ap.phone, ap.location, ap.birth_date,
               a.resume_path, a.applicant_json, a.llm_result, a.status, a.applied_at
        FROM applications a
        JOIN applicants ap ON a.applicant_id = ap.id
        WHERE a.job_id = ?
        ORDER BY
            ISNULL(JSON_EXTRACT(a.llm_result, '$.evaluation_score')) ASC,
            JSON_EXTRACT(a.llm_result, '$.evaluation_score') DESC
    ");
    $stmt->bind_param("i", $selected_job);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['llm']       = $row['llm_result']    ? json_decode($row['llm_result'],    true) : null;
        $row['applicant'] = $row['applicant_json'] ? json_decode($row['applicant_json'], true) : null;
        $applications[] = $row;
    }
}

$conn->close();

// ── Helper: shared profile block rendered in both evaluated + pending modals ──
function render_profile($app, $applicant_data) {
    ob_start(); ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">
      <div>
        <div class="detail-label">Location</div>
        <div class="detail-value"><?= htmlspecialchars($app['location'] ?? '—') ?></div>
      </div>
      <div>
        <div class="detail-label">Phone</div>
        <div class="detail-value"><?= htmlspecialchars($app['phone'] ?? '—') ?></div>
      </div>
      <?php if (!empty($app['birth_date'])): ?>
      <div>
        <div class="detail-label">Date of Birth</div>
        <div class="detail-value"><?= date('d M Y', strtotime($app['birth_date'])) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($applicant_data['work_experience'])): ?>
      <div class="modal-section-title">Work Experience</div>
      <div style="margin-bottom:16px;">
        <?php foreach ($applicant_data['work_experience'] as $we): ?>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-subtle);font-size:12px;">
            <span style="color:var(--text-primary);"><?= htmlspecialchars($we['position']) ?></span>
            <span style="color:var(--text-muted);"><?= htmlspecialchars($we['time']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($applicant_data['education'])): ?>
      <div class="modal-section-title">Education</div>
      <div style="margin-bottom:16px;">
        <?php foreach ($applicant_data['education'] as $edu): ?>
          <div style="font-size:12px;color:var(--text-secondary);padding:4px 0;">
            <?= htmlspecialchars($edu) ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($applicant_data['cert'])): ?>
      <div class="modal-section-title">Certifications</div>
      <div class="tags" style="margin-bottom:16px;">
        <?php foreach ($applicant_data['cert'] as $c): ?>
          <span class="tag tag-amber"><?= htmlspecialchars($c) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($applicant_data['skills'])): ?>
      <div class="modal-section-title">Skills</div>
      <div class="tags" style="margin-bottom:16px;">
        <?php foreach ($applicant_data['skills'] as $s): ?>
          <span class="tag tag-cyan"><?= htmlspecialchars($s) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php
    $extra    = $applicant_data['extracurricular'] ?? [];
    $has_extra = !empty($extra['competitions']) || !empty($extra['community'])
              || !empty($extra['roles'])        || !empty($extra['project']);
    if ($has_extra): ?>
      <div class="modal-section-title">Extracurricular</div>
      <div style="margin-bottom:16px;">
        <?php foreach (['competitions' => 'Competitions', 'community' => 'Community', 'roles' => 'Roles', 'project' => 'Projects'] as $key => $label): ?>
          <?php if (!empty($extra[$key])): ?>
            <div style="font-size:10px;color:var(--text-muted);letter-spacing:1px;text-transform:uppercase;margin:8px 0 4px;">
              <?= $label ?>
            </div>
            <?php foreach ($extra[$key] as $item): ?>
              <div style="font-size:12px;color:var(--text-secondary);padding:2px 0;">
                · <?= htmlspecialchars($item) ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($applicant_data['linkedin_titles'])): ?>
      <div class="modal-section-title">LinkedIn Activity</div>
      <?php foreach (array_slice($applicant_data['linkedin_titles'], 0, 3) as $title): ?>
        <div style="background:var(--bg-secondary);border:1px solid var(--border-subtle);border-radius:var(--radius-sm);padding:12px;margin-bottom:8px;">
          <div style="font-size:12px;color:var(--text-secondary);line-height:1.6;">
            <?= htmlspecialchars($title) ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($applicant_data['github'])): ?>
      <div class="modal-section-title">GitHub Projects</div>
      <div class="tags">
        <?php foreach ($applicant_data['github'] as $repo): ?>
          <span class="tag"><?= htmlspecialchars($repo['title']) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CATS — Applicants</title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>
<div class="page-wrapper">
  <nav class="navbar">
    <div class="navbar-inner">
      <a href="dashboard.php" class="navbar-brand">
        <div class="brand-icon">CATS</div>
        <div>
          <div class="brand-text">CATS System</div>
          <div class="brand-sub">Employer Portal</div>
        </div>
      </a>
      <div class="navbar-nav">
        <span class="nav-badge">&#9679; <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        <a href="logout.php" class="nav-link">Logout</a>
      </div>
    </div>
  </nav>

  <div class="dashboard">
    <aside class="sidebar">
      <div class="sidebar-section">
        <div class="sidebar-label">Navigation</div>
        <ul class="sidebar-nav">
          <li><a href="dashboard.php"><span class="sidebar-icon">⌂</span> Overview</a></li>
          <li><a href="create_job.php"><span class="sidebar-icon">+</span> Create Job</a></li>
          <li><a href="applicants.php" class="active"><span class="sidebar-icon">◈</span> View Applicants</a></li>
        </ul>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-label">Account</div>
        <ul class="sidebar-nav">
          <li><a href="logout.php"><span class="sidebar-icon">→</span> Logout</a></li>
        </ul>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header flex items-center justify-between">
        <div>
          <div class="page-title">Applicant Rankings</div>
          <div class="page-subtitle">AI-evaluated and ranked candidates</div>
        </div>
        <div>
          <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <label style="font-size:11px;color:var(--text-muted);">Job:</label>
            <select name="job_id" class="form-control" style="width:240px;" onchange="this.form.submit()">
              <?php foreach ($jobs as $j): ?>
                <option value="<?= $j['id'] ?>" <?= $j['id'] == $selected_job ? 'selected' : '' ?>>
                  <?= htmlspecialchars($j['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      </div>

      <?php if (empty($applications)): ?>
        <div class="card">
          <div class="card-body" style="text-align:center;padding:60px;">
            <div style="font-size:40px;margin-bottom:12px;">◈</div>
            <div style="color:var(--text-secondary);">No applications yet for this position.</div>
          </div>
        </div>

      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php
          $rank_counter = 0;
          foreach ($applications as $i => $app):
            $llm            = $app['llm'];
            $applicant_data = $app['applicant'];
            $is_evaluated   = !empty($llm) && isset($llm['evaluation_score']);
            $initials       = strtoupper(
                                substr($app['name'], 0, 1) .
                                (strpos($app['name'], ' ')
                                  ? substr($app['name'], strpos($app['name'], ' ') + 1, 1)
                                  : '')
                              );
          ?>

            <?php if (!$is_evaluated): ?>
              <!-- PENDING CARD -->
              <div class="card" style="padding:20px;opacity:0.7;">
                <div style="display:flex;gap:16px;align-items:center;">
                  <div class="rank-badge rank-other">—</div>
                  <div class="applicant-avatar"><?= $initials ?></div>
                  <div style="flex:1;">
                    <div style="font-family:var(--font-display);font-weight:700;font-size:15px;margin-bottom:2px;">
                      <?= htmlspecialchars($app['name']) ?>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($app['email']) ?></div>
                    <div style="margin-top:6px;font-size:11px;color:var(--text-muted);">
                      Applied <?= date('d M Y', strtotime($app['applied_at'])) ?>
                    </div>
                  </div>
                  <span class="badge badge-amber">⏳ Pending Evaluation</span>
                  <button class="btn btn-secondary btn-sm" onclick="openModal(<?= $i ?>)">View →</button>
                </div>
              </div>

            <?php else:
              $rank_counter++;
              $score         = $llm['evaluation_score'];
              $scoreColor    = $score >= 75 ? 'var(--accent-green)' : ($score >= 50 ? 'var(--accent-amber)' : 'var(--accent-red)');
              $rankClass     = $rank_counter == 1 ? 'rank-1' : ($rank_counter == 2 ? 'rank-2' : ($rank_counter == 3 ? 'rank-3' : 'rank-other'));
              $circumference = 2 * pi() * 30;
              $strokeDash    = ($score / 100) * $circumference;
            ?>
              <!-- EVALUATED CARD -->
              <div class="card" style="padding:20px;">
                <div style="display:flex;gap:16px;align-items:center;">
                  <div class="rank-badge <?= $rankClass ?>"><?= $rank_counter ?></div>
                  <div class="applicant-avatar"><?= $initials ?></div>

                  <div style="flex:1;">
                    <div style="font-family:var(--font-display);font-weight:700;font-size:15px;margin-bottom:2px;">
                      <?= htmlspecialchars($app['name']) ?>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($app['email']) ?></div>
                    <div style="margin-top:8px;">
                      <?php
                        $skills = array_slice($applicant_data['skills'] ?? [], 0, 4);
                        foreach ($skills as $s): ?>
                          <span class="tag tag-cyan" style="margin-right:4px;"><?= htmlspecialchars($s) ?></span>
                      <?php endforeach;
                        if (count($applicant_data['skills'] ?? []) > 4): ?>
                          <span class="tag">+<?= count($applicant_data['skills']) - 4 ?> more</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Score Ring -->
                  <div class="score-ring">
                    <svg viewBox="0 0 70 70" width="70" height="70">
                      <circle cx="35" cy="35" r="30" fill="none" stroke="var(--border-subtle)" stroke-width="4"/>
                      <circle cx="35" cy="35" r="30" fill="none"
                        stroke="<?= $scoreColor ?>"
                        stroke-width="4"
                        stroke-dasharray="<?= $strokeDash ?> <?= $circumference ?>"
                        stroke-linecap="round"/>
                    </svg>
                    <div class="score-ring-value">
                      <div class="score-ring-number" style="color:<?= $scoreColor ?>"><?= $score ?></div>
                      <div class="score-ring-label">Score</div>
                    </div>
                  </div>

                  <button class="btn btn-secondary btn-sm" onclick="openModal(<?= $i ?>)">View →</button>
                </div>

                <?php if (!empty($llm['summary'])): ?>
                  <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-subtle);">
                    <div style="font-size:12px;color:var(--text-secondary);line-height:1.6;">
                      <?= htmlspecialchars(substr($llm['summary'], 0, 180)) ?>...
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<!-- ============================================================
     MODALS
     ============================================================ -->
<?php foreach ($applications as $i => $app):
  $llm            = $app['llm'];
  $applicant_data = $app['applicant'];
  $is_evaluated   = !empty($llm) && isset($llm['evaluation_score']);
  $score          = $is_evaluated ? $llm['evaluation_score'] : null;
  $scoreColor     = $score !== null
                      ? ($score >= 75 ? 'var(--accent-green)' : ($score >= 50 ? 'var(--accent-amber)' : 'var(--accent-red)'))
                      : 'var(--text-muted)';
  $initials       = strtoupper(
                      substr($app['name'], 0, 1) .
                      (strpos($app['name'], ' ')
                        ? substr($app['name'], strpos($app['name'], ' ') + 1, 1)
                        : '')
                    );
?>
<div class="modal-overlay" id="modal-<?= $i ?>">
  <div class="modal" style="max-width:720px;">
    <div class="modal-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="applicant-avatar"><?= $initials ?></div>
        <div>
          <div class="modal-title"><?= htmlspecialchars($app['name']) ?></div>
          <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($app['email']) ?></div>
        </div>
      </div>
      <button class="modal-close" onclick="closeModal(<?= $i ?>)">✕</button>
    </div>

    <div class="modal-body" style="max-height:75vh;overflow-y:auto;">

      <?php if (!$is_evaluated): ?>
        <!-- PENDING STATE -->
        <div class="modal-section" style="text-align:center;padding:32px 0;">
          <div style="font-size:36px;margin-bottom:12px;">⏳</div>
          <div style="font-family:var(--font-display);font-weight:700;font-size:16px;margin-bottom:6px;">
            Evaluation Pending
          </div>
          <div style="font-size:12px;color:var(--text-secondary);">
            The AI pipeline has not finished processing this applicant yet.<br>
            Check back shortly.
          </div>
          <div style="margin-top:16px;">
            <span class="badge badge-muted">Status: <?= htmlspecialchars($app['status']) ?></span>
            <span class="badge badge-muted" style="margin-left:8px;">
              Applied <?= date('d M Y', strtotime($app['applied_at'])) ?>
            </span>
          </div>
        </div>
        <?php if ($applicant_data): ?>
          <div class="modal-section">
            <div class="modal-section-title">Applicant Profile</div>
            <?= render_profile($app, $applicant_data) ?>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <!-- EVALUATED STATE -->

        <!-- Score Overview -->
        <div class="modal-section">
          <div class="modal-section-title">Evaluation Summary</div>
          <div style="display:flex;gap:16px;align-items:center;margin-bottom:16px;">
            <div style="font-family:var(--font-display);font-size:48px;font-weight:800;color:<?= $scoreColor ?>;line-height:1;">
              <?= $score ?>
            </div>
            <div>
              <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">OVERALL SCORE</div>
              <div style="font-size:12px;color:var(--text-secondary);line-height:1.6;">
                <?= htmlspecialchars($llm['summary'] ?? '') ?>
              </div>
            </div>
          </div>

          <?php if (!empty($llm['aspect_scores'])): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <?php foreach ($llm['aspect_scores'] as $aspect => $val):
                $label     = ucwords(str_replace('_', ' ', $aspect));
                $fillClass = $val >= 75 ? 'fill-high' : ($val >= 50 ? 'fill-mid' : 'fill-low');
              ?>
                <div class="score-bar-wrapper">
                  <div class="score-bar-label">
                    <span><?= $label ?></span>
                    <span style="color:<?= $val >= 75 ? 'var(--accent-green)' : ($val >= 50 ? 'var(--accent-amber)' : 'var(--accent-red)') ?>">
                      <?= $val ?>
                    </span>
                  </div>
                  <div class="score-bar">
                    <div class="score-bar-fill <?= $fillClass ?>" style="width:<?= $val ?>%"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Strengths & Gaps -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;" class="modal-section">
          <div>
            <div class="modal-section-title">Strengths</div>
            <div class="tags">
              <?php foreach ($llm['strengths'] ?? [] as $s): ?>
                <span class="tag tag-green"><?= htmlspecialchars($s) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <div class="modal-section-title">Gaps</div>
            <div class="tags">
              <?php foreach ($llm['gaps'] ?? [] as $g): ?>
                <span class="tag tag-red"><?= htmlspecialchars($g) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Recommended Questions -->
        <?php if (!empty($llm['recommended_questions'])): ?>
          <div class="modal-section">
            <div class="modal-section-title">Recommended Interview Questions</div>
            <?php foreach ($llm['recommended_questions'] as $qi => $q): ?>
              <div class="question-item">
                <div class="question-num"><?= $qi + 1 ?>.</div>
                <div class="question-text"><?= htmlspecialchars($q) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Applicant Profile -->
        <div class="modal-section">
          <div class="modal-section-title">Applicant Profile</div>
          <?= render_profile($app, $applicant_data) ?>
        </div>

      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
function openModal(i) {
  document.getElementById('modal-' + i).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(i) {
  document.getElementById('modal-' + i).classList.remove('open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', function(e) {
    if (e.target === this) {
      this.classList.remove('open');
      document.body.style.overflow = '';
    }
  });
});
</script>
</body>
</html>