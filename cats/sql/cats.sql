-- ============================================================
-- CATS — Context-based Application Tracking System
-- Database Schema v2
-- ============================================================

CREATE DATABASE IF NOT EXISTS cats_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cats_db;

-- ============================================================
-- TABLES
-- ============================================================

CREATE TABLE employers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    company_name  VARCHAR(255) NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(50),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE applicants (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(50),
    portfolio     JSON,
    location      VARCHAR(255),
    birth_date    DATE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE jobs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    employer_id   INT NOT NULL,
    title         VARCHAR(255) NOT NULL,
    company_name  VARCHAR(255) NOT NULL,
    job_data      JSON NOT NULL,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES employers(id)
);

CREATE TABLE applications (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id   INT NOT NULL,
    job_id         INT NOT NULL,
    resume_path    VARCHAR(500),
    applicant_text TEXT,
    applicant_json JSON,
    llm_result     JSON,
    status         ENUM('pending','processing','evaluated') DEFAULT 'pending',
    applied_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id),
    FOREIGN KEY (job_id)       REFERENCES jobs(id),
    UNIQUE KEY unique_application (applicant_id, job_id)
);

-- ============================================================
-- EMPLOYERS
-- Password: employer123
-- ============================================================

INSERT INTO employers (company_name, email, password_hash, phone) VALUES
('TechCorp Solutions', 'hr@techcorp.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '+60-3-2100-0000');

-- ============================================================
-- JOBS
-- ============================================================

INSERT INTO jobs (employer_id, title, company_name, job_data) VALUES
(1, 'Senior Cybersecurity Analyst', 'TechCorp Solutions', '{
  "job_id": "JOB-2025-001",
  "title": "Senior Cybersecurity Analyst",
  "company_name": "TechCorp Solutions",
  "company_contact": {
    "phone": "+60-3-2100-0000",
    "email": "hr@techcorp.com"
  },
  "office_location": "Kuala Lumpur, Malaysia",
  "job_type": "Full-Time",
  "work_hours": "9AM - 6PM, Mon-Fri",
  "probation": "3 months",
  "salary_range": {
    "min": "8000",
    "max": "14000",
    "currency": "MYR"
  },
  "job_benefits": [
    "Medical & Dental Coverage",
    "Annual Leave 18 days",
    "Remote Work 2 days/week",
    "Professional Certification Support",
    "Performance Bonus"
  ],
  "requirements": [
    {
      "skills": ["Python", "SIEM Tools", "Network Security", "Incident Response", "Penetration Testing", "Linux", "Firewall Management"],
      "work_experience": [
        {"position": "SOC Analyst",           "time": "3 years"},
        {"position": "Network Administrator", "time": "2 years"}
      ]
    }
  ],
  "preferred_skills": ["CEH Certification", "OSCP", "Splunk", "AWS Security", "Threat Intelligence"],
  "project_contributions": ["Open source security tools", "CTF competitions", "Bug bounty programs"]
}');

-- ============================================================
-- APPLICANTS
-- Password for all: password123 (You may need to update password hashes)
-- ============================================================

INSERT INTO applicants (name, email, password_hash, phone, location, birth_date, portfolio) VALUES

('Ahmad Razif', 'ahmad.razif@email.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '+60-12-345-6789', 'Kuala Lumpur', '1992-01-01',
 '[{"platform":"GitHub","url":"https://github.com/ahmadrazif"},{"platform":"LinkedIn","url":"https://linkedin.com/in/ahmadrazif"}]'),

('Priya Nair', 'priya.nair@email.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '+60-16-789-0123', 'Petaling Jaya, Selangor', '1993-05-15',
 '[{"platform":"GitHub","url":"https://github.com/priyanair"},{"platform":"LinkedIn","url":"https://linkedin.com/in/priyanair"}]'),

('Wei Liang Tan', 'weiliang.tan@email.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '+60-11-222-3333', 'Shah Alam, Selangor', '1995-08-20',
 '[{"platform":"GitHub","url":"https://github.com/wltan"}]'),

('Nurul Ain', 'nurul.ain@email.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '+60-17-456-7890', 'Kuala Lumpur', '1998-03-05',
 '[{"platform":"LinkedIn","url":"https://linkedin.com/in/nurulain"}]'),

('Darren Chong', 'darren.chong@email.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '+60-19-876-5432', 'Klang, Selangor', '1991-07-12',
 '[]');

-- ============================================================
-- APPLICATIONS
--
-- applicant_text : raw text extracted from PDF by extractor.py
-- applicant_json : structured JSON built by AI agent + scraper
--   fields: skills, work_experience, education, cert,
--           extracurricular, github, linkedin_titles
-- llm_result     : fake evaluation output for UI testing
-- ============================================================

INSERT INTO applications
    (applicant_id, job_id, resume_path, applicant_text, applicant_json, llm_result, status)
VALUES

-- ----------------------------------------------------------------
-- Ahmad Razif — Rank 1 / Score 92
-- ----------------------------------------------------------------
(1, 1, 'uploads/1.pdf',

'Ahmad Razif
Senior SOC Analyst | Cybersecurity Professional
Email: ahmad.razif@email.com | Phone: +60-12-345-6789
Kuala Lumpur, Malaysia | github.com/ahmadrazif | linkedin.com/in/ahmadrazif

CERTIFICATIONS
Offensive Security Certified Professional (OSCP) 2023
Certified Ethical Hacker (CEH) 2021
CompTIA Security+ 2020

WORK EXPERIENCE
SOC Analyst CyberShield Sdn Bhd 2021 Present 4 years
Led threat hunting operations across enterprise SIEM Splunk
Reduced mean time to detect MTTD by 40 percent through custom detection rules
Conducted red team engagements for 3 financial sector clients

Network Administrator DataLink Sdn Bhd 2019 2021 2 years
Managed Cisco firewall policies and VPN infrastructure
Deployed network monitoring using Zabbix and Nagios

EDUCATION
B.Sc. Computer Science Network Security Universiti Malaya 2019

SKILLS
Python SIEM Tools Splunk Incident Response Linux Network Security
Penetration Testing Firewall Management CEH OSCP Nmap Metasploit Wireshark

EXTRACURRICULAR
CTF Competitions HackTheBox TryHackMe Top 1 percent global ranking
Speaker OWASP KL Chapter Meetup 2024
Bug Bounty HackerOne active participant',

'{
  "applicant_id": "1",
  "selected_job_id": "1",
  "skills": ["Python","SIEM Tools","Splunk","Incident Response","Linux","Network Security","Penetration Testing","Firewall Management","CEH","OSCP","Nmap","Metasploit","Wireshark"],
  "work_experience": [
    {"position": "SOC Analyst",           "time": "4 years"},
    {"position": "Network Administrator", "time": "2 years"}
  ],
  "education": [
    "B.Sc. Computer Science (Network Security) — Universiti Malaya, 2019"
  ],
  "cert": ["OSCP (2023)", "CEH (2021)", "CompTIA Security+ (2020)"],
  "extracurricular": {
    "competitions": ["HackTheBox — Top 1% global ranking", "TryHackMe — Top 1% global ranking"],
    "community":    ["Speaker at OWASP KL Chapter Meetup 2024", "Bug Bounty via HackerOne"],
    "roles":        [],
    "project":      []
  },
  "github": [
    {"title": "py-threat-hunter",  "readme": "Automated threat hunting tool using Python and Splunk API. Detects lateral movement and exfiltration patterns in enterprise networks."},
    {"title": "siem-dashboard",    "readme": "Custom Splunk dashboard for SOC operations. Includes correlation rules, alert tuning guides, and incident response playbooks."},
    {"title": "ctf-writeups",      "readme": "Collection of CTF solutions and methodology notes from HackTheBox, TryHackMe and local competitions."},
    {"title": "network-scanner",   "readme": "Python wrapper around Nmap for automated network discovery and vulnerability fingerprinting."}
  ],
  "linkedin_titles": [
    "Just completed a red team engagement — key finding: 73% of breaches came from phishing vectors",
    "Sharing my HackTheBox writeup on machine Phantom — OSCP prep never stops",
    "Presented at OWASP KL meetup on zero-day mitigation strategies"
  ]
}',

'{
  "evaluation_score": 92,
  "rank": 1,
  "summary": "Ahmad is an exceptional candidate with 4+ years of SOC experience, holding both CEH and OSCP certifications. His active GitHub contributions to security tools and regular CTF participation demonstrate a strong passion for cybersecurity beyond the workplace. His LinkedIn activity shows thought leadership and community engagement. Highly recommended for the role.",
  "aspect_scores": {
    "technical_skills":     95,
    "work_experience":      90,
    "certifications":       95,
    "community_engagement": 90,
    "project_quality":      88
  },
  "strengths": [
    "OSCP & CEH certified",
    "4 years hands-on SOC experience",
    "Active CTF competitor — Top 1% globally",
    "Community speaker at OWASP KL",
    "Strong GitHub project portfolio"
  ],
  "gaps": [
    "No explicit AWS Security experience"
  ],
  "recommended_questions": [
    "Walk me through a recent red team engagement — what was your methodology from recon to reporting?",
    "You mentioned zero-day mitigation at OWASP KL. What specific techniques do you advocate for?",
    "Describe a time your Splunk SIEM alert caught a true positive that others missed.",
    "How do you stay current with threat intelligence feeds and incorporate them into your workflow?",
    "Tell me about the py-threat-hunter project — what detection logic does it use?"
  ]
}',
'evaluated'),

-- ----------------------------------------------------------------
-- Priya Nair — Rank 2 / Score 81
-- ----------------------------------------------------------------
(2, 1, 'uploads/2.pdf',

'Priya Nair
SOC Analyst Cloud Security Specialist
Email: priya.nair@email.com | Phone: +60-16-789-0123
Petaling Jaya Selangor | github.com/priyanair | linkedin.com/in/priyanair

CERTIFICATIONS
AWS Security Specialty 2025
CompTIA CySA+ 2022

WORK EXPERIENCE
SOC Analyst SecureNet Malaysia 2022 Present 3 years
Monitored and triaged 200 plus alerts daily using QRadar SIEM
Developed incident response playbooks adopted company-wide
Responded to 3 major cloud-based security incidents AWS

IT Support Engineer Maxis Berhad 2021 2022 1 year
First-line support for network and endpoint issues

EDUCATION
B.Sc. Information Security Universiti Teknologi Malaysia UTM 2021

SKILLS
Python Network Security Incident Response Linux SIEM Tools AWS Security Threat Intelligence QRadar

EXTRACURRICULAR
ISACA KL Chapter member
Volunteer mentor Girls in Tech Malaysia 2024',

'{
  "applicant_id": "2",
  "selected_job_id": "1",
  "skills": ["Python","Network Security","Incident Response","Linux","SIEM Tools","AWS Security","Threat Intelligence","QRadar"],
  "work_experience": [
    {"position": "SOC Analyst",         "time": "3 years"},
    {"position": "IT Support Engineer", "time": "1 year"}
  ],
  "education": [
    "B.Sc. Information Security — Universiti Teknologi Malaysia (UTM), 2021"
  ],
  "cert": ["AWS Security Specialty (2025)", "CompTIA CySA+ (2022)"],
  "extracurricular": {
    "competitions": [],
    "community":    ["ISACA KL Chapter member", "Mentor at Girls in Tech Malaysia 2024"],
    "roles":        [],
    "project":      []
  },
  "github": [
    {"title": "aws-security-scripts", "readme": "Collection of AWS Lambda functions and boto3 scripts for automated security remediation — S3 bucket policy enforcement, IAM auditing, and GuardDuty alert processing."},
    {"title": "incident-playbooks",   "readme": "Markdown-based incident response playbooks for common attack scenarios: ransomware, credential stuffing, cloud misconfiguration."},
    {"title": "log-analyzer",         "readme": "Python tool to parse and correlate logs from multiple sources (CloudTrail, VPC Flow Logs, QRadar exports) into a unified timeline."}
  ],
  "linkedin_titles": [
    "Excited to share — passed AWS Security Specialty exam! Hard work pays off",
    "Interesting read on supply chain attacks — the SolarWinds aftermath still teaches new lessons"
  ]
}',

'{
  "evaluation_score": 81,
  "rank": 2,
  "summary": "Priya brings solid SOC experience with a strong AWS Security background that complements the role well. Her recent AWS Security Specialty certification is a notable asset. GitHub shows practical automation scripts. Lacks offensive security experience — no OSCP or CEH — and penetration testing skills are not evident.",
  "aspect_scores": {
    "technical_skills":     82,
    "work_experience":      80,
    "certifications":       80,
    "community_engagement": 70,
    "project_quality":      75
  },
  "strengths": [
    "AWS Security Specialty certified",
    "Well-structured incident response playbooks on GitHub",
    "Cloud security expertise is a differentiator",
    "Active community involvement via ISACA and mentoring"
  ],
  "gaps": [
    "No penetration testing experience",
    "No CEH or OSCP certification",
    "Limited CTF or offensive security background"
  ],
  "recommended_questions": [
    "How has your AWS Security experience translated to on-premise threat detection work?",
    "Walk us through one of your incident response playbooks — what triggered it and what was the outcome?",
    "What SIEM tools have you worked with, and how did you tune alert thresholds to reduce false positives?",
    "Have you ever had to respond to a cloud-native attack? Walk us through your process.",
    "Where do you see yourself growing in the offensive security space?"
  ]
}',
'evaluated'),

-- ----------------------------------------------------------------
-- Wei Liang Tan — Rank 3 / Score 67
-- ----------------------------------------------------------------
(3, 1, 'uploads/3.pdf',

'Wei Liang Tan
Network Administrator
Email: weiliang.tan@email.com | Phone: +60-11-222-3333
Shah Alam Selangor | github.com/wltan

WORK EXPERIENCE
Network Administrator Telekom Malaysia 2022 Present 3 years
Managed Cisco ASA and Palo Alto firewall rulesets
Deployed and maintained site-to-site VPN infrastructure
Monitored network traffic using Wireshark and SolarWinds

Junior SOC Analyst CyberSafe Sdn Bhd 2021 2022 1 year
Alert triage and first-level incident escalation

EDUCATION
Diploma in Computer Networking Politeknik Sultan Salahuddin Abdul Aziz Shah 2021

SKILLS
Python Linux Firewall Management Network Security Wireshark Cisco VPN SolarWinds

EXTRACURRICULAR
Completed SANS SEC401 online course self-study',

'{
  "applicant_id": "3",
  "selected_job_id": "1",
  "skills": ["Python","Linux","Firewall Management","Network Security","Wireshark","Cisco","VPN","SolarWinds"],
  "work_experience": [
    {"position": "Network Administrator", "time": "3 years"},
    {"position": "Junior SOC Analyst",    "time": "1 year"}
  ],
  "education": [
    "Diploma in Computer Networking — Politeknik Sultan Salahuddin Abdul Aziz Shah, 2021"
  ],
  "cert": [],
  "extracurricular": {
    "competitions": [],
    "community":    [],
    "roles":        [],
    "project":      ["Self-study: SANS SEC401 online course"]
  },
  "github": [
    {"title": "firewall-rules-generator",   "readme": "Python script to generate and validate Cisco ASA and Palo Alto firewall rules from a CSV template. Reduces manual config errors."},
    {"title": "network-monitoring-scripts", "readme": "Bash and Python scripts for automated network health checks, bandwidth monitoring, and alerting via email."}
  ],
  "linkedin_titles": []
}',

'{
  "evaluation_score": 67,
  "rank": 3,
  "summary": "Wei Liang has a solid networking background and meets the network administration requirement well. However, his SOC experience is limited to 1 year at junior level and he lacks key skills like SIEM tooling and incident response. No formal certifications listed. Minimal online presence makes community engagement difficult to assess.",
  "aspect_scores": {
    "technical_skills":     65,
    "work_experience":      70,
    "certifications":       40,
    "community_engagement": 30,
    "project_quality":      60
  },
  "strengths": [
    "Strong networking and firewall fundamentals",
    "3 years hands-on infrastructure experience",
    "Practical GitHub automation projects"
  ],
  "gaps": [
    "No SIEM platform experience",
    "Incident response skills not demonstrated",
    "No formal security certifications",
    "SOC experience is junior-level only",
    "No LinkedIn presence"
  ],
  "recommended_questions": [
    "You have strong networking experience — how have you applied that in a security context?",
    "Have you worked with any SIEM platforms? If so, in what capacity?",
    "Describe a network security incident you personally handled from detection to resolution.",
    "What certifications are you currently pursuing or planning to pursue?",
    "How do you approach learning new security tools on the job?"
  ]
}',
'evaluated'),

-- ----------------------------------------------------------------
-- Nurul Ain — Rank 4 / Score 44
-- ----------------------------------------------------------------
(4, 1, 'uploads/4.pdf',

'Nurul Ain binti Roslan
Fresh Graduate Cybersecurity Enthusiast
Email: nurul.ain@email.com | Phone: +60-17-456-7890
Kuala Lumpur | linkedin.com/in/nurulain

WORK EXPERIENCE
IT Intern MDEC 2024 6 months
Assisted helpdesk team with endpoint troubleshooting
Documented network topology for internal wiki

EDUCATION
B.Sc. Computer Science Universiti Putra Malaysia UPM 2024
CGPA 3.41

SKILLS
Python Linux Basic Networking Microsoft Office

EXTRACURRICULAR
UPM Cybersecurity Club member 2022 2024
Participated in NTLM CTF 2023 team event',

'{
  "applicant_id": "4",
  "selected_job_id": "1",
  "skills": ["Python","Linux","Basic Networking","Microsoft Office"],
  "work_experience": [
    {"position": "IT Intern", "time": "6 months"}
  ],
  "education": [
    "B.Sc. Computer Science — Universiti Putra Malaysia (UPM), 2024 (CGPA 3.41)"
  ],
  "cert": [],
  "extracurricular": {
    "competitions": ["NTLM CTF 2023 — team participant"],
    "community":    ["UPM Cybersecurity Club member 2022–2024"],
    "roles":        [],
    "project":      []
  },
  "github": [],
  "linkedin_titles": [
    "Just graduated with a degree in Computer Science — looking forward to starting my cybersecurity career!"
  ]
}',

'{
  "evaluation_score": 44,
  "rank": 4,
  "summary": "Nurul Ain is a fresh graduate with limited practical experience. While she shows enthusiasm for cybersecurity and has some extracurricular involvement, the role requires 3+ years of SOC experience and she only has a 6-month IT internship. Her skill set is foundational and does not yet meet the technical requirements for a Senior Analyst position.",
  "aspect_scores": {
    "technical_skills":     40,
    "work_experience":      25,
    "certifications":       20,
    "community_engagement": 45,
    "project_quality":      10
  },
  "strengths": [
    "Enthusiasm and growth mindset",
    "Degree from reputable university with strong CGPA",
    "Some extracurricular cybersecurity involvement"
  ],
  "gaps": [
    "Only 6 months of internship experience",
    "No SIEM tools experience",
    "No security certifications",
    "No GitHub portfolio or personal projects"
  ],
  "recommended_questions": [
    "What specific cybersecurity projects did you work on during your internship at MDEC?",
    "Which certifications are you actively pursuing to build your security career?",
    "Tell me about your experience at the NTLM CTF — what was your role and what did you learn?",
    "What does your self-study routine look like for cybersecurity topics?",
    "Why are you applying for a senior role with your current experience level?"
  ]
}',
'evaluated'),

-- ----------------------------------------------------------------
-- Darren Chong — Rank 5 / Score 28
-- ----------------------------------------------------------------
(5, 1, 'uploads/5.pdf',

'Darren Chong
Desktop Support Technician
Email: darren.chong@email.com | Phone: +60-19-876-5432
Klang Selangor

WORK EXPERIENCE
Desktop Support Technician Eon Capital 2021 Present 4 years
Hardware and software troubleshooting for 200 plus users
Windows 10 11 deployment and AD account management
Printer and peripheral maintenance

EDUCATION
Diploma in Information Technology Kolej Komuniti Klang 2020

SKILLS
Windows Administration Microsoft Office Active Directory Basic Python Hardware Troubleshooting',

'{
  "applicant_id": "5",
  "selected_job_id": "1",
  "skills": ["Windows Administration","Microsoft Office","Active Directory","Basic Python","Hardware Troubleshooting"],
  "work_experience": [
    {"position": "Desktop Support Technician", "time": "4 years"}
  ],
  "education": [
    "Diploma in Information Technology — Kolej Komuniti Klang, 2020"
  ],
  "cert": [],
  "extracurricular": {
    "competitions": [],
    "community":    [],
    "roles":        [],
    "project":      []
  },
  "github": [],
  "linkedin_titles": []
}',

'{
  "evaluation_score": 28,
  "rank": 5,
  "summary": "Darren has IT support experience but it is not aligned with cybersecurity or the requirements of this role. No security-specific skills, SIEM experience, certifications, or relevant projects were found. His background in desktop support does not translate to the Senior Analyst responsibilities required for this position.",
  "aspect_scores": {
    "technical_skills":     20,
    "work_experience":      30,
    "certifications":       10,
    "community_engagement": 10,
    "project_quality":      10
  },
  "strengths": [
    "IT troubleshooting and support experience",
    "Windows and Active Directory familiarity"
  ],
  "gaps": [
    "No cybersecurity experience",
    "No SIEM or network security skills",
    "No security certifications",
    "No relevant projects or portfolio",
    "No LinkedIn or GitHub presence"
  ],
  "recommended_questions": [
    "What motivated you to apply for a cybersecurity analyst role from a desktop support background?",
    "Have you done any self-study or coursework in cybersecurity? Which platforms?",
    "Are you aware of the technical gap between your current role and this position?",
    "What is your plan to upskill for this role if selected?",
    "Have you ever dealt with a security incident in your support role?"
  ]
}',
'evaluated');