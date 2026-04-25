<?php
require_once '../config.php';
require_login('applicant');

$conn = db_connect();
$applicant_id = $_SESSION['user_id'];

// Handle cancel action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_app_id'])) {
    $cancel_app_id = (int)$_POST['cancel_app_id'];
    // Verify this application belongs to the logged-in applicant
    $check = $conn->prepare("SELECT id FROM applications WHERE id = ? AND applicant_id = ?");
    $check->bind_param("ii", $cancel_app_id, $applicant_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $del = $conn->prepare("DELETE FROM applications WHERE id = ? AND applicant_id = ?");
        $del->bind_param("ii", $cancel_app_id, $applicant_id);
        $del->execute();
    }
    header('Location: jobs.php?cancelled=1');
    exit;
}

// Get all active jobs with application status
$jobs = $conn->query("
  SELECT j.id, j.title, j.company_name, j.job_data, j.created_at,
         a.id as app_id, a.status as app_status
  FROM jobs j
  LEFT JOIN applications a ON a.job_id = j.id AND a.applicant_id = $applicant_id
  WHERE j.is_active = 1
  ORDER BY j.created_at DESC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CATS — Browse Jobs</title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>
<div class="page-wrapper">
  <nav class="navbar">
    <div class="navbar-inner">
      <a href="jobs.php" class="navbar-brand">
        <div class="brand-icon">CATS</div>
        <div>
          <div class="brand-text">CATS System</div>
          <div class="brand-sub">Applicant Portal</div>
        </div>
      </a>
      <div class="navbar-nav">
        <a href="jobs.php" class="nav-link active">Jobs</a>
        <span class="nav-badge">&#9679; <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        <a href="logout.php" class="nav-link">Logout</a>
      </div>
    </div>
  </nav>

  <div style="position:relative;z-index:1;padding:40px 24px;max-width:900px;margin:0 auto;">
    <div class="page-header">
      <div class="page-title">Open Positions</div>
      <div class="page-subtitle">Browse and apply for available roles</div>
    </div>

    <?php if (isset($_GET['cancelled'])): ?>
      <div class="alert alert-success" style="margin-bottom:16px;">✓ Application cancelled successfully.</div>
    <?php endif; ?>

    <?php if ($jobs->num_rows === 0): ?>
      <div class="card">
        <div class="card-body" style="text-align:center;padding:60px;">
          <div style="font-size:40px;margin-bottom:12px;">◈</div>
          <div style="color:var(--text-secondary);">No job listings available right now.</div>
        </div>
      </div>
    <?php else: ?>
      <?php while ($job = $jobs->fetch_assoc()):
        $data = json_decode($job['job_data'], true);
        $already_applied = !empty($job['app_id']);
      ?>
      <div class="job-card mb-16" onclick="toggleJob(<?= $job['id'] ?>)">
        <div class="job-card-header">
          <div>
            <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
            <div class="job-company"><?= htmlspecialchars($job['company_name']) ?></div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
            <?php if ($already_applied): ?>
              <span class="badge badge-green">✓ Applied</span>
            <?php else: ?>
              <span class="badge badge-cyan">Open</span>
            <?php endif; ?>
            <span style="font-size:11px;color:var(--text-muted);"><?= date('d M Y', strtotime($job['created_at'])) ?></span>
          </div>
        </div>

        <div class="job-meta">
          <span class="badge badge-muted"><?= htmlspecialchars($data['job_type'] ?? '') ?></span>
          <span class="badge badge-muted">📍 <?= htmlspecialchars($data['office_location'] ?? '') ?></span>
          <span class="badge badge-amber">
            <?= $data['salary_range']['currency'] ?> <?= number_format($data['salary_range']['min']) ?> – <?= number_format($data['salary_range']['max']) ?>/mo
          </span>
        </div>

        <!-- Skills preview -->
        <div class="tags">
          <?php foreach (array_slice($data['requirements'][0]['skills'] ?? [], 0, 5) as $s): ?>
            <span class="tag tag-cyan"><?= htmlspecialchars($s) ?></span>
          <?php endforeach; ?>
          <?php $total = count($data['requirements'][0]['skills'] ?? []); if ($total > 5): ?>
            <span class="tag">+<?= $total - 5 ?> more</span>
          <?php endif; ?>
        </div>

        <!-- Expanded Details (hidden by default) -->
        <div id="job-detail-<?= $job['id'] ?>" style="display:none;" onclick="event.stopPropagation()">
          <hr class="divider">
          <div class="job-detail-grid" style="margin-bottom:16px;">
            <div class="detail-item">
              <div class="detail-label">Work Hours</div>
              <div class="detail-value"><?= htmlspecialchars($data['work_hours'] ?? '—') ?></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Probation</div>
              <div class="detail-value"><?= htmlspecialchars($data['probation'] ?? '—') ?></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Contact</div>
              <div class="detail-value"><?= htmlspecialchars($data['company_contact']['email'] ?? '—') ?></div>
            </div>
          </div>

          <?php if (!empty($data['requirements'][0]['work_experience'])): ?>
          <div class="mb-16">
            <div class="detail-label" style="margin-bottom:6px;">Required Experience</div>
            <?php foreach ($data['requirements'][0]['work_experience'] as $we): ?>
              <span class="tag" style="margin-right:4px;margin-bottom:4px;"><?= htmlspecialchars($we['position']) ?> · <?= htmlspecialchars($we['time']) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($data['job_benefits'])): ?>
          <div class="mb-16">
            <div class="detail-label" style="margin-bottom:6px;">Benefits</div>
            <div class="tags">
              <?php foreach ($data['job_benefits'] as $b): ?>
                <span class="tag tag-green"><?= htmlspecialchars($b) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($data['preferred_skills'])): ?>
          <div class="mb-16">
            <div class="detail-label" style="margin-bottom:6px;">Preferred Skills</div>
            <div class="tags">
              <?php foreach ($data['preferred_skills'] as $ps): ?>
                <span class="tag"><?= htmlspecialchars($ps) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <div style="margin-top:20px;display:flex;gap:12px;align-items:center;">
            <?php if ($already_applied): ?>
              <div class="alert alert-success" style="margin-bottom:0;flex:1;">
                ✓ Applied — Status: <strong><?= htmlspecialchars($job['app_status']) ?></strong>
              </div>
              <form method="POST" onsubmit="return confirm('Are you sure you want to cancel your application? This cannot be undone.');">
                <input type="hidden" name="cancel_app_id" value="<?= $job['app_id'] ?>">
                <button type="submit" class="btn btn-secondary" style="color:var(--accent-red);border-color:var(--accent-red);">Cancel Application</button>
              </form>
            <?php else: ?>
              <button class="btn btn-primary" style="width:auto;" onclick="openApply(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes($job['title'])) ?>')">
                Apply Now →
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Apply Modal -->
<div class="modal-overlay" id="apply-modal">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <div>
        <div class="modal-title">Apply for <span id="apply-job-title"></span></div>
        <div style="font-size:11px;color:var(--text-muted);">Upload your resume (PDF, max 5MB)</div>
      </div>
      <button class="modal-close" onclick="closeApply()">✕</button>
    </div>
    <div class="modal-body">
      <form id="applyForm" method="POST" action="apply.php" enctype="multipart/form-data">
        <input type="hidden" name="job_id" id="apply-job-id">

        <div class="file-upload" id="dropzone" onclick="document.getElementById('resume-file').click()">
          <div class="file-upload-icon">📄</div>
          <div class="file-upload-text">Click to upload your resume</div>
          <div class="file-upload-hint">PDF format only · Max 5MB · 1 page recommended</div>
          <div id="file-selected" style="margin-top:10px;display:none;">
            <span class="badge badge-green" id="file-name-badge"></span>
          </div>
        </div>
        <input type="file" id="resume-file" name="resume" accept=".pdf" style="display:none;" onchange="fileSelected(this)">

        <div id="apply-error" class="alert alert-error" style="display:none;margin-top:12px;"></div>

        <div style="margin-top:20px;display:flex;gap:12px;">
          <button type="submit" class="btn btn-primary" style="width:auto;" id="apply-submit-btn">Submit Application</button>
          <button type="button" class="btn btn-secondary" onclick="closeApply()">Cancel</button>
        </div>
      </form>

      <div id="processing-state" style="display:none;text-align:center;padding:20px 0;">
        <div class="processing-indicator" style="justify-content:center;margin-bottom:12px;">
          <div class="spinner"></div>
          <span id="processing-msg">Uploading resume...</span>
        </div>
        <div style="font-size:11px;color:var(--text-muted);">Extracting and analysing your profile data</div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleJob(id) {
  const el = document.getElementById('job-detail-' + id);
  const isOpen = el.style.display !== 'none';
  document.querySelectorAll('[id^="job-detail-"]').forEach(d => d.style.display = 'none');
  if (!isOpen) el.style.display = 'block';
}

function openApply(jobId, jobTitle) {
  document.getElementById('apply-job-id').value = jobId;
  document.getElementById('apply-job-title').textContent = jobTitle;
  document.getElementById('apply-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeApply() {
  document.getElementById('apply-modal').classList.remove('open');
  document.body.style.overflow = '';
}

function fileSelected(input) {
  const file = input.files[0];
  if (file) {
    document.getElementById('file-selected').style.display = 'block';
    document.getElementById('file-name-badge').textContent = file.name;
  }
}

const dropzone = document.getElementById('dropzone');
dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
dropzone.addEventListener('drop', e => {
  e.preventDefault();
  dropzone.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file && file.type === 'application/pdf') {
    const input = document.getElementById('resume-file');
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    fileSelected(input);
  }
});

document.getElementById('applyForm').addEventListener('submit', function(e) {
  const fileInput = document.getElementById('resume-file');
  if (!fileInput.files[0]) {
    e.preventDefault();
    const err = document.getElementById('apply-error');
    err.style.display = 'flex';
    err.textContent = '⚠ Please upload your resume before submitting.';
    return;
  }
  document.getElementById('apply-submit-btn').style.display = 'none';
  document.getElementById('processing-state').style.display = 'block';

  const msgs = ['Uploading resume...', 'Extracting text...', 'Analysing profile...', 'Scraping portfolio...'];
  let i = 0;
  setInterval(() => {
    if (i < msgs.length) document.getElementById('processing-msg').textContent = msgs[i++];
  }, 1200);
});
</script>
</body>
</html>
