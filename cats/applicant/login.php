<?php
require_once '../config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $conn = db_connect();

  if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password_hash FROM applicants WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      if (password_verify($password, $row['password_hash'])) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_type'] = 'applicant';
        $_SESSION['user_name'] = $row['name'];
        header('Location: jobs.php');
        exit;
      }
    }

    $error = 'Invalid credentials.';
  } elseif ($action === 'register') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');

    // Parse portfolio links
    $raw_portfolio = trim($_POST['portfolio'] ?? '');
    $portfolio_links = [];

    if ($raw_portfolio) {
      $lines = preg_split('/[\n,]+/', $raw_portfolio);
      foreach ($lines as $line) {
        $url = trim($line);
        if (!empty($url)) {
          $platform = 'Portfolio';
          if (stripos($url, 'github.com') !== false) $platform = 'GitHub';
          elseif (stripos($url, 'linkedin.com') !== false) $platform = 'LinkedIn';

          $portfolio_links[] = [
            'platform' => $platform,
            'url' => $url
          ];
        }
      }
    }

    $portfolio_json = json_encode($portfolio_links);

    if (empty($name) || empty($email) || empty($password)) {
      $error = 'All required fields must be filled.';
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      $stmt = $conn->prepare("
        INSERT INTO applicants 
        (name, email, password_hash, phone, portfolio, location, birth_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");

      $stmt->bind_param("sssssss", $name, $email, $hash, $phone, $portfolio_json, $location, $birth_date);

      if ($stmt->execute()) {
        $success = 'Account created. You can now sign in.';
      } else {
        $error = 'Email already registered.';
      }
    }
  }

  $conn->close();
}

if (is_logged_in('applicant')) {
  header('Location: jobs.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CATS — Applicant Portal</title>
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
              <div class="brand-sub">Applicant Portal</div>
            </div>
          </a>
        </div>

        <div class="auth-card">
          <div class="auth-tabs">
            <div class="auth-tab <?= !isset($_GET['tab']) || $_GET['tab'] === 'login' ? 'active' : '' ?>" onclick="switchTab('login')">Login</div>
            <div class="auth-tab <?= isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">Register</div>
          </div>

          <?php if ($error): ?>
            <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
          <?php endif; ?>

          <!-- Login Tab -->
          <div class="tab-content <?= !isset($_GET['tab']) || $_GET['tab'] === 'login' ? 'active' : '' ?>" id="tab-login">
            <form method="POST">
              <input type="hidden" name="action" value="login">
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@email.com" required>
              </div>
              <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
              </div>
              <button type="submit" class="btn btn-primary" style="margin-top:8px;">Sign In</button>
            </form>
            <div style="margin-top:16px; font-size:11px; color:var(--text-muted); text-align:center;">
              Demo accounts use password: <span style="color:var(--accent-cyan)">password123</span>
            </div>
          </div>

          <!-- Register Tab -->
          <div class="tab-content <?= isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'active' : '' ?>" id="tab-register">
            <form method="POST">
              <input type="hidden" name="action" value="register">
              <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" placeholder="Ahmad bin Abdullah" required>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" placeholder="you@email.com" required>
              </div>
              <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" placeholder="+60-12-xxx-xxxx" required>
              </div>
              <div class="form-group">
                <label class="form-label">Date of Birth *</label>
                <input type="date" name="birth_date" class="form-control" required>
              </div>

              <div class="form-group">
                <label class="form-label">Location *</label>
                <select name="location" class="form-control" required>
                  <option value="" disabled selected>Select your city/state</option>
                  <optgroup label="Kuala Lumpur & Selangor">
                    <option value="Kuala Lumpur">Kuala Lumpur</option>
                    <option value="Petaling Jaya, Selangor">Petaling Jaya</option>
                    <option value="Shah Alam, Selangor">Shah Alam</option>
                    <option value="Subang Jaya, Selangor">Subang Jaya</option>
                    <option value="Klang, Selangor">Klang</option>
                    <option value="Cyberjaya, Selangor">Cyberjaya</option>
                    <option value="Putrajaya">Putrajaya</option>
                  </optgroup>
                  <optgroup label="Johor">
                    <option value="Johor Bahru, Johor">Johor Bahru</option>
                    <option value="Skudai, Johor">Skudai</option>
                  </optgroup>
                  <optgroup label="Penang">
                    <option value="George Town, Penang">George Town</option>
                    <option value="Butterworth, Penang">Butterworth</option>
                  </optgroup>
                  <optgroup label="Perak">
                    <option value="Ipoh, Perak">Ipoh</option>
                  </optgroup>
                  <optgroup label="Negeri Sembilan">
                    <option value="Seremban, Negeri Sembilan">Seremban</option>
                  </optgroup>
                  <optgroup label="Melaka">
                    <option value="Melaka City, Melaka">Melaka City</option>
                  </optgroup>
                  <optgroup label="Pahang">
                    <option value="Kuantan, Pahang">Kuantan</option>
                  </optgroup>
                  <optgroup label="Kelantan">
                    <option value="Kota Bharu, Kelantan">Kota Bharu</option>
                  </optgroup>
                  <optgroup label="Terengganu">
                    <option value="Kuala Terengganu, Terengganu">Kuala Terengganu</option>
                  </optgroup>
                  <optgroup label="Kedah">
                    <option value="Alor Setar, Kedah">Alor Setar</option>
                  </optgroup>
                  <optgroup label="Perlis">
                    <option value="Kangar, Perlis">Kangar</option>
                  </optgroup>
                  <optgroup label="Sabah">
                    <option value="Kota Kinabalu, Sabah">Kota Kinabalu</option>
                    <option value="Sandakan, Sabah">Sandakan</option>
                  </optgroup>
                  <optgroup label="Sarawak">
                    <option value="Kuching, Sarawak">Kuching</option>
                    <option value="Miri, Sarawak">Miri</option>
                  </optgroup>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Portfolio / Social Links</label>
                <textarea name="portfolio" class="form-control" rows="3"
                  placeholder="Paste your links here, one per line&#10;e.g. https://github.com/yourname&#10;https://linkedin.com/in/yourname"
                  style="resize:vertical;"></textarea>
                <div style="font-size:10px;color:var(--text-muted);margin-top:4px;">
                  GitHub and LinkedIn are auto-detected from the URL.
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
              </div>
              <button type="submit" class="btn btn-primary" style="margin-top:8px;">Create Account</button>
            </form>
          </div>
        </div>

        <div style="text-align:center; margin-top:20px; font-size:11px; color:var(--text-muted);">
          Hiring manager? <a href="../employer/login.php" style="color:var(--accent-cyan);">Employer Portal →</a>
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