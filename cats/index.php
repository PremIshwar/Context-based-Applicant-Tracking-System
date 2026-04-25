<?php
require_once 'config.php';


// Redirect logged-in users
if (is_logged_in('employer')) {
    header('Location: employer/dashboard.php');
    exit;
}
if (is_logged_in('applicant')) {
    header('Location: applicant/jobs.php');
    exit;
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CATS — Context-based Application Tracking System</title>
  <link rel="stylesheet" href="css/main.css">
  <style>
    .hero {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 40px 24px;
      position: relative;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 14px;
      background: rgba(0, 229, 255, 0.1);
      border: 1px solid rgba(0, 229, 255, 0.3);
      border-radius: 20px;
      font-size: 11px;
      color: var(--accent-cyan);
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-bottom: 24px;
    }

    .hero-title {
      font-family: var(--font-display);
      font-size: clamp(36px, 6vw, 72px);
      font-weight: 800;
      line-height: 1.05;
      letter-spacing: -1px;
      margin-bottom: 16px;
      color: var(--text-primary);
    }

    .hero-title span {
      color: var(--accent-cyan);
    }

    .hero-desc {
      font-size: 15px;
      color: var(--text-secondary);
      max-width: 500px;
      margin: 0 auto 40px;
      line-height: 1.7;
    }

    .portal-cards {
      display: flex;
      gap: 20px;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 8px;
    }

    .portal-card {
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-lg);
      padding: 32px 40px;
      width: 240px;
      text-decoration: none;
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .portal-card::before {
      content: '';
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 2px;
      background: var(--accent-cyan);
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }

    .portal-card.employer::before { background: var(--accent-amber); }

    .portal-card:hover {
      border-color: var(--accent-cyan);
      box-shadow: var(--shadow-glow);
      transform: translateY(-4px);
    }

    .portal-card.employer:hover {
      border-color: var(--accent-amber);
      box-shadow: 0 0 20px rgba(255, 184, 48, 0.15);
    }

    .portal-card:hover::before { transform: scaleX(1); }

    .portal-icon {
      font-size: 32px;
      margin-bottom: 16px;
    }

    .portal-title {
      font-family: var(--font-display);
      font-size: 18px;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 6px;
    }

    .portal-desc {
      font-size: 12px;
      color: var(--text-secondary);
      line-height: 1.5;
    }

    .features {
      display: flex;
      gap: 32px;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 60px;
    }

    .feature {
      text-align: center;
      max-width: 160px;
    }

    .feature-icon {
      font-size: 20px;
      color: var(--accent-cyan);
      margin-bottom: 8px;
    }

    .feature-text {
      font-size: 11px;
      color: var(--text-muted);
      letter-spacing: 0.5px;
    }

    /* Animated scan line effect */
    .scan-line {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--accent-cyan), transparent);
      animation: scan 4s ease-in-out infinite;
      opacity: 0.3;
      pointer-events: none;
      z-index: 0;
    }

    @keyframes scan {
      0% { top: 0; }
      100% { top: 100%; }
    }
  </style>
</head>
<body>
<div class="scan-line"></div>

<div class="page-wrapper">
  <div class="hero">
    <div class="hero-badge">
      <span>●</span> System Online
    </div>

    <div class="hero-title">
      Context-Based<br>
      <span>Application Tracking</span>
    </div>

    <div class="hero-desc">
      AI-powered candidate evaluation that goes beyond the résumé.
      Analyse GitHub portfolios, LinkedIn activity, and skills to surface the right talent.
    </div>

    <div class="portal-cards">
      <a href="applicant/login.php" class="portal-card">
        <div class="portal-icon">◈</div>
        <div class="portal-title">Applicant Portal</div>
        <div class="portal-desc">Browse jobs, upload your resume, and let your full profile speak for you.</div>
      </a>
      <a href="employer/login.php" class="portal-card employer">
        <div class="portal-icon">⌂</div>
        <div class="portal-title">Employer Portal</div>
        <div class="portal-desc">Post roles, review AI-ranked candidates, and get recommended interview questions.</div>
      </a>
    </div>

    <div class="features">
      <div class="feature">
        <div class="feature-icon">⚡</div>
        <div class="feature-text">Auto PDF Extraction</div>
      </div>
      <div class="feature">
        <div class="feature-icon">◈</div>
        <div class="feature-text">GitHub Scraping</div>
      </div>
      <div class="feature">
        <div class="feature-icon">▲</div>
        <div class="feature-text">AI Evaluation</div>
      </div>
      <div class="feature">
        <div class="feature-icon">◉</div>
        <div class="feature-text">Smart Rankings</div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
