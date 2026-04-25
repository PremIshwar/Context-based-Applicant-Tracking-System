<?php
require_once '../config.php';
require_login('applicant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: jobs.php');
    exit;
}

$applicant_id = $_SESSION['user_id'];
$job_id = (int)($_POST['job_id'] ?? 0);

if (!$job_id) {
    header('Location: jobs.php?error=invalid_job');
    exit;
}

$conn = db_connect();

// Check already applied
$check = $conn->prepare("SELECT id FROM applications WHERE applicant_id = ? AND job_id = ?");
$check->bind_param("ii", $applicant_id, $job_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header('Location: jobs.php?error=already_applied');
    exit;
}

// Validate file
if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
    header('Location: jobs.php?error=upload_failed');
    exit;
}

$file = $_FILES['resume'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    header('Location: jobs.php?error=not_pdf');
    exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    header('Location: jobs.php?error=too_large');
    exit;
}

// Save file as applicant_id.pdf
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
$dest = UPLOAD_DIR . $applicant_id . '.pdf';
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    header('Location: jobs.php?error=save_failed');
    exit;
}

$resume_path = 'uploads/' . $applicant_id . '.pdf';

// ============================================================
// STEP 1: Extract raw text from PDF using extractor.py
// ============================================================
$python_path = 'python3';
$extractor_script = __DIR__ . '/../python/extractor.py';

$cmd_extract = escapeshellcmd("$python_path $extractor_script")
             . ' ' . escapeshellarg($dest)
             . ' 2>&1';

$applicant_text = shell_exec($cmd_extract) ?? '';
$applicant_text = trim($applicant_text);
$applicant_text = iconv('UTF-8', 'UTF-8//IGNORE', $applicant_text);


// ============================================================
// STEP 2: Insert application as 'pending' with raw text only
//   The AI orchestrator will handle:
//   Jsoneer → Scraper → Librarian → Critic → Secretary
// ============================================================
$stmt = $conn->prepare("
    INSERT INTO applications
        (applicant_id, job_id, resume_path, applicant_text, status)
    VALUES
        (?, ?, ?, ?, 'pending')
");
$stmt->bind_param("iiss",
    $applicant_id,
    $job_id,
    $resume_path,
    $applicant_text
);
$stmt->execute();

$application_id = $conn->insert_id;
$conn->close();

// ============================================================
// STEP 3: Trigger the AI screening pipeline
//   Calls the Python FastAPI orchestrator which runs:
//   1. Jsoneer  — parse text → structured applicant_json
//   2. Scraper  — enrich with GitHub/LinkedIn/portfolio data
//   3. Librarian — verify skills against evidence
//   4. Critic   — generate interview questions
//   5. Secretary — compile llm_result (score, rank, questions)
// ============================================================
$orchestrator_url = 'http://127.0.0.1:8000/screening/application/' . $application_id;

// Fire async — don't block the user while AI processes
$ch = curl_init($orchestrator_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT_MS     => 3000,  // 3s timeout — don't make user wait
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => '{}',
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Redirect regardless — processing happens in background
header('Location: jobs.php?applied=1');
exit;
