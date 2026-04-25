<?php
require_once '../config.php';
require_login('employer');

$conn = db_connect();
$employer_id = $_SESSION['user_id'];

// Stats
$job_count = $conn->query("SELECT COUNT(*) as c FROM jobs WHERE employer_id = $employer_id")->fetch_assoc()['c'];
$app_count = $conn->query("SELECT COUNT(*) as c FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.employer_id = $employer_id")->fetch_assoc()['c'];
$eval_count = $conn->query("SELECT COUNT(*) as c FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.employer_id = $employer_id AND a.status = 'evaluated'")->fetch_assoc()['c'];

// Recent applications
$recent = $conn->query("
  SELECT ap.name, ap.email, a.applied_at, j.title, a.llm_result, a.status
  FROM applications a
  JOIN applicants ap ON a.applicant_id = ap.id
  JOIN jobs j ON a.job_id = j.id
  WHERE j.employer_id = $employer_id
  ORDER BY a.applied_at DESC
  LIMIT 5
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CATS — Employer Dashboard</title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>
<div class="page-wrapper">
  <!-- Navbar -->
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
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-section">
        <div class="sidebar-label">Navigation</div>
        <ul class="sidebar-nav">
          <li><a href="dashboard.php" class="active"><span class="sidebar-icon">⌂</span> Overview</a></li>
          <li><a href="create_job.php"><span class="sidebar-icon">+</span> Create Job</a></li>
          <li><a href="applicants.php"><span class="sidebar-icon">◈</span> View Applicants</a></li>
        </ul>
      </div>
      <div class="sidebar-section">
        <div class="sidebar-label">Account</div>
        <ul class="sidebar-nav">
          <li><a href="logout.php"><span class="sidebar-icon">→</span> Logout</a></li>
        </ul>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <div class="page-header">
        <div class="page-title">Overview</div>
        <div class="page-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?></div>
      </div>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card cyan">
          <div class="stat-value"><?= $job_count ?></div>
          <div class="stat-label">Active Jobs</div>
        </div>
        <div class="stat-card green">
          <div class="stat-value"><?= $app_count ?></div>
          <div class="stat-label">Total Applications</div>
        </div>
        <div class="stat-card amber">
          <div class="stat-value"><?= $eval_count ?></div>
          <div class="stat-label">Evaluated</div>
        </div>
        <div class="stat-card purple">
          <div class="stat-value"><?= $app_count > 0 ? round(($eval_count/$app_count)*100) : 0 ?>%</div>
          <div class="stat-label">Eval Rate</div>
        </div>
      </div>

      <!-- Recent Applications -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Recent Applications</div>
          <a href="applicants.php" class="btn btn-secondary btn-sm">View All →</a>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Applicant</th>
                <th>Position</th>
                <th>Score</th>
                <th>Status</th>
                <th>Applied</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $recent->fetch_assoc()):
                $llm = $row['llm_result'] ? json_decode($row['llm_result'], true) : null;
                $score = $llm['evaluation_score'] ?? '—';
                $initials = strtoupper(substr($row['name'], 0, 1) . (strpos($row['name'], ' ') ? substr($row['name'], strpos($row['name'],' ')+1, 1) : ''));
              ?>
              <tr>
                <td>
                  <div class="applicant-card">
                    <div class="applicant-avatar"><?= $initials ?></div>
                    <div>
                      <div class="applicant-name"><?= htmlspecialchars($row['name']) ?></div>
                      <div class="applicant-email"><?= htmlspecialchars($row['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="color:var(--text-secondary)"><?= htmlspecialchars($row['title']) ?></td>
                <td>
                  <?php if (is_numeric($score)): ?>
                    <span style="font-family:var(--font-display);font-weight:700;color:<?= $score>=75?'var(--accent-green)':($score>=50?'var(--accent-amber)':'var(--accent-red)') ?>"><?= $score ?></span>
                  <?php else: echo '<span class="text-muted">—</span>'; endif; ?>
                </td>
                <td>
                  <span class="badge <?= $row['status']==='evaluated'?'badge-green':($row['status']==='processing'?'badge-amber':'badge-muted') ?>">
                    <?= $row['status'] ?>
                  </span>
                </td>
                <td style="color:var(--text-muted)"><?= date('d M Y', strtotime($row['applied_at'])) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Quick Actions -->
      <div style="margin-top:24px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <a href="create_job.php" class="card" style="padding:20px; text-decoration:none; display:flex; align-items:center; gap:16px; transition:var(--transition);" onmouseover="this.style.borderColor='var(--accent-cyan)'" onmouseout="this.style.borderColor='var(--border-subtle)'">
          <div style="font-size:28px;">+</div>
          <div>
            <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px;">Post New Job</div>
            <div style="font-size:11px;color:var(--text-muted);">Create a new job listing</div>
          </div>
        </a>
        <a href="applicants.php" class="card" style="padding:20px; text-decoration:none; display:flex; align-items:center; gap:16px; transition:var(--transition);" onmouseover="this.style.borderColor='var(--accent-green)'" onmouseout="this.style.borderColor='var(--border-subtle)'">
          <div style="font-size:28px;">◈</div>
          <div>
            <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px;">View Rankings</div>
            <div style="font-size:11px;color:var(--text-muted);">See ranked applicants</div>
          </div>
        </a>
      </div>
    </main>
  </div>
</div>
</body>
</html>
