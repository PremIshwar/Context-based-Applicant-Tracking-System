<?php
require_once '../config.php';

// Handle form submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $conn = db_connect();
    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        echo password_hash("employer123", PASSWORD_DEFAULT);
        

        $stmt = $conn->prepare("SELECT id, company_name, password_hash FROM employers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_type'] = 'employer';
                $_SESSION['user_name'] = $row['company_name'];
                header('Location: dashboard.php');
                exit;
            }
        }
        $error = 'Invalid credentials.';

    } elseif ($action === 'register') {
        $company = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($company) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO employers (company_name, email, password_hash, phone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $company, $email, $hash, $phone);
            if ($stmt->execute()) {
                $success = 'Account created. You can now log in.';
            } else {
                $error = 'Email already registered.';
            }
        }
    }
    $conn->close();
}

if (is_logged_in('employer')) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CATS — Employer Portal</title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>
<div class="page-wrapper">
  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-header">
        <a href="../index.php" class="auth-logo">
          <div class="brand-icon">CATS</div>
          <div>
            <div class="brand-text">CATS System</div>
            <div class="brand-sub">Employer Portal</div>
          </div>
        </a>
      </div>

      <div class="auth-card">
        <div class="auth-tabs">
          <div class="auth-tab <?= !isset($_GET['tab']) || $_GET['tab']==='login' ? 'active' : '' ?>" onclick="switchTab('login')">Login</div>
          <div class="auth-tab <?= isset($_GET['tab']) && $_GET['tab']==='register' ? 'active' : '' ?>" onclick="switchTab('register')">Register</div>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Login Tab -->
        <div class="tab-content <?= !isset($_GET['tab']) || $_GET['tab']==='login' ? 'active' : '' ?>" id="tab-login">
          <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="hr@company.com" required>
            </div>
            <div class="form-group">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:8px;">Sign In</button>
          </form>
          <div style="margin-top:16px; font-size:11px; color:var(--text-muted); text-align:center;">
            Demo: hr@techcorp.com / employer123
          </div>
        </div>

        <!-- Register Tab -->
        <div class="tab-content <?= isset($_GET['tab']) && $_GET['tab']==='register' ? 'active' : '' ?>" id="tab-register">
          <form method="POST">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
              <label class="form-label">Company Name</label>
              <input type="text" name="company_name" class="form-control" placeholder="Acme Corp" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="hr@company.com" required>
            </div>
            <div class="form-group">
              <label class="form-label">Phone (Optional)</label>
              <input type="text" name="phone" class="form-control" placeholder="+60-3-xxxx-xxxx">
            </div>
            <div class="form-group">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:8px;">Create Account</button>
          </form>
        </div>
      </div>

      <div style="text-align:center; margin-top:20px; font-size:11px; color:var(--text-muted);">
        Looking to apply? <a href="../applicant/login.php" style="color:var(--accent-cyan);">Applicant Portal →</a>
      </div>
    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  event.target.classList.add('active');
  document.getElementById('tab-' + tab).classList.add('active');
}
</script>
</body>
</html>
