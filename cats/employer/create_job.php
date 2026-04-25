<?php
require_once '../config.php';
require_login('employer');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = db_connect();
    $employer_id = $_SESSION['user_id'];

    // Build job JSON from form
    $job_id = 'JOB-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

    $skills = json_decode($_POST['skills_json'] ?? '[]', true) ?: [];
    $benefits = json_decode($_POST['benefits_json'] ?? '[]', true) ?: [];
    $preferred = json_decode($_POST['preferred_json'] ?? '[]', true) ?: [];
    $projects = json_decode($_POST['projects_json'] ?? '[]', true) ?: [];

    // Work experience entries
    $exp_positions = $_POST['exp_position'] ?? [];
    $exp_times = $_POST['exp_time'] ?? [];
    $work_exp = [];
    foreach ($exp_positions as $k => $pos) {
        if (!empty($pos) && !empty($exp_times[$k])) {
            $work_exp[] = ['position' => $pos, 'time' => $exp_times[$k]];
        }
    }

    $job_data = [
        'job_id' => $job_id,
        'title' => trim($_POST['title'] ?? ''),
        'company_name' => $_SESSION['user_name'],
        'company_contact' => [
            'phone' => trim($_POST['contact_phone'] ?? ''),
            'email' => trim($_POST['contact_email'] ?? '')
        ],
        'office_location' => trim($_POST['office_location'] ?? ''),
        'job_type' => trim($_POST['job_type'] ?? ''),
        'work_hours' => trim($_POST['work_hours'] ?? ''),
        'probation' => trim($_POST['probation'] ?? ''),
        'salary_range' => [
            'min' => trim($_POST['salary_min'] ?? ''),
            'max' => trim($_POST['salary_max'] ?? ''),
            'currency' => trim($_POST['currency'] ?? 'MYR')
        ],
        'job_benefits' => $benefits,
        'requirements' => [[
            'skills' => $skills,
            'work_experience' => $work_exp
        ]],
        'preferred_skills' => $preferred,
        'project_contributions' => $projects
    ];

    $title = $job_data['title'];
    if (empty($title)) {
        $error = 'Job title is required.';
    } else {
        $json = json_encode($job_data);
        $stmt = $conn->prepare("INSERT INTO jobs (employer_id, title, company_name, job_data) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $employer_id, $title, $_SESSION['user_name'], $json);
        if ($stmt->execute()) {
            $success = "Job '{$title}' posted successfully!";
        } else {
            $error = 'Failed to create job.';
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CATS — Create Job</title>
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
          <li><a href="create_job.php" class="active"><span class="sidebar-icon">+</span> Create Job</a></li>
          <li><a href="applicants.php"><span class="sidebar-icon">◈</span> View Applicants</a></li>
        </ul>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <div class="page-title">Post a Job</div>
        <div class="page-subtitle">Fill in the details to create a new job listing</div>
      </div>

      <?php if ($error): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

      <div class="card">
        <div class="card-body">
          <form method="POST" id="jobForm">
            <!-- Hidden JSON fields -->
            <input type="hidden" name="skills_json" id="skills_json">
            <input type="hidden" name="benefits_json" id="benefits_json">
            <input type="hidden" name="preferred_json" id="preferred_json">
            <input type="hidden" name="projects_json" id="projects_json">

            <div class="form-section-title">Basic Information</div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Job Title *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Senior Backend Engineer" required>
              </div>
              <div class="form-group">
                <label class="form-label">Job Type</label>
                <select name="job_type" class="form-control">
                  <option>Full-Time</option>
                  <option>Part-Time</option>
                  <option>Contract</option>
                  <option>Internship</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Office Location</label>
                <input type="text" name="office_location" class="form-control" placeholder="e.g. Kuala Lumpur, Malaysia">
              </div>
              <div class="form-group">
                <label class="form-label">Work Hours</label>
                <input type="text" name="work_hours" class="form-control" placeholder="e.g. 9AM – 6PM, Mon–Fri">
              </div>
              <div class="form-group">
                <label class="form-label">Probation Period</label>
                <input type="text" name="probation" class="form-control" placeholder="e.g. 3 months">
              </div>
            </div>

            <div class="form-section-title">Contact</div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Contact Email</label>
                <input type="email" name="contact_email" class="form-control" placeholder="hr@company.com">
              </div>
              <div class="form-group">
                <label class="form-label">Contact Phone</label>
                <input type="text" name="contact_phone" class="form-control" placeholder="+60-3-xxxx-xxxx">
              </div>
            </div>

            <div class="form-section-title">Salary</div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Min Salary</label>
                <input type="number" name="salary_min" class="form-control" placeholder="5000">
              </div>
              <div class="form-group">
                <label class="form-label">Max Salary</label>
                <input type="number" name="salary_max" class="form-control" placeholder="10000">
              </div>
              <div class="form-group">
                <label class="form-label">Currency</label>
                <select name="currency" class="form-control">
                  <option value="MYR">MYR</option>
                  <option value="USD">USD</option>
                  <option value="SGD">SGD</option>
                </select>
              </div>
            </div>

            <div class="form-section-title">Requirements — Skills</div>
            <div class="form-group">
              <label class="form-label">Required Skills <span style="color:var(--text-muted)">(Press Enter or comma to add)</span></label>
              <div class="tag-input-wrapper" id="skills-wrapper" onclick="document.getElementById('skills-input').focus()">
                <input type="text" id="skills-input" class="tag-input-field" placeholder="e.g. Python, SIEM Tools...">
              </div>
            </div>

            <div class="form-section-title">Requirements — Work Experience</div>
            <div id="exp-list">
              <div class="work-exp-entry">
                <input type="text" name="exp_position[]" class="form-control" placeholder="Position (e.g. SOC Analyst)">
                <input type="text" name="exp_time[]" class="form-control" placeholder="Duration (e.g. 3 years)" style="max-width:160px;">
                <button type="button" onclick="removeExp(this)" style="background:none;border:none;color:var(--accent-red);cursor:pointer;font-size:16px;">✕</button>
              </div>
            </div>
            <button type="button" class="btn-add-exp" onclick="addExp()">+ Add Experience</button>

            <div class="form-section-title">Benefits</div>
            <div class="form-group">
              <label class="form-label">Job Benefits <span style="color:var(--text-muted)">(Press Enter or comma to add)</span></label>
              <div class="tag-input-wrapper" id="benefits-wrapper" onclick="document.getElementById('benefits-input').focus()">
                <input type="text" id="benefits-input" class="tag-input-field" placeholder="e.g. Medical Coverage, Annual Leave...">
              </div>
            </div>

            <div class="form-section-title">Preferred Skills & Extras</div>
            <div class="form-group">
              <label class="form-label">Preferred Skills</label>
              <div class="tag-input-wrapper" id="preferred-wrapper" onclick="document.getElementById('preferred-input').focus()">
                <input type="text" id="preferred-input" class="tag-input-field" placeholder="e.g. CEH Certification, Splunk...">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Project/Portfolio Contributions</label>
              <div class="tag-input-wrapper" id="projects-wrapper" onclick="document.getElementById('projects-input').focus()">
                <input type="text" id="projects-input" class="tag-input-field" placeholder="e.g. Open source contributions...">
              </div>
            </div>

            <div style="margin-top:24px; display:flex; gap:12px;">
              <button type="submit" class="btn btn-primary" style="width:auto;padding:10px 32px;">Post Job</button>
              <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
// Tag input system
function createTagInput(wrapperId, inputId, hiddenFieldId) {
  const wrapper = document.getElementById(wrapperId);
  const input = document.getElementById(inputId);
  let tags = [];

  function renderTags() {
    wrapper.querySelectorAll('.removable-tag').forEach(t => t.remove());
    tags.forEach((tag, i) => {
      const el = document.createElement('div');
      el.className = 'removable-tag';
      el.innerHTML = `${tag} <span class="remove-tag-btn" data-i="${i}">✕</span>`;
      wrapper.insertBefore(el, input);
    });
    document.getElementById(hiddenFieldId).value = JSON.stringify(tags);
  }

  function addTag(val) {
    val = val.trim().replace(/,$/, '');
    if (val && !tags.includes(val)) {
      tags.push(val);
      renderTags();
    }
    input.value = '';
  }

  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      addTag(input.value);
    } else if (e.key === 'Backspace' && !input.value && tags.length) {
      tags.pop();
      renderTags();
    }
  });

  input.addEventListener('blur', () => {
    if (input.value) addTag(input.value);
  });

  wrapper.addEventListener('click', e => {
    if (e.target.classList.contains('remove-tag-btn')) {
      tags.splice(parseInt(e.target.dataset.i), 1);
      renderTags();
    }
  });
}

createTagInput('skills-wrapper', 'skills-input', 'skills_json');
createTagInput('benefits-wrapper', 'benefits-input', 'benefits_json');
createTagInput('preferred-wrapper', 'preferred-input', 'preferred_json');
createTagInput('projects-wrapper', 'projects-input', 'projects_json');

function addExp() {
  const list = document.getElementById('exp-list');
  const div = document.createElement('div');
  div.className = 'work-exp-entry';
  div.innerHTML = `
    <input type="text" name="exp_position[]" class="form-control" placeholder="Position (e.g. SOC Analyst)">
    <input type="text" name="exp_time[]" class="form-control" placeholder="Duration (e.g. 3 years)" style="max-width:160px;">
    <button type="button" onclick="removeExp(this)" style="background:none;border:none;color:var(--accent-red);cursor:pointer;font-size:16px;">✕</button>
  `;
  list.appendChild(div);
}

function removeExp(btn) {
  const list = document.getElementById('exp-list');
  if (list.children.length > 1) btn.parentElement.remove();
}
</script>
</body>
</html>
