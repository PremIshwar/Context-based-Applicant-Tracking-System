# CATS — Context-based Application Tracking System

An AI-powered candidate screening system where **ilmu-glm-5.1** acts as the central reasoning engine to transform raw resumes into evaluated, ranked candidate profiles with recommended interview questions.

## Architecture

```
┌──────────────┐  ┌──────────────┐
│  Employer UI │  │ Candidate UI │
│  (PHP/MySQL) │  │  (PHP/MySQL) │
└──────┬───────┘  └──────┬───────┘
       │ posts job        │ uploads resume
       │ job_data → DB    │ applicant_text → DB
       ▼                  ▼
┌─────────────────────────────────────────┐
│         THE ORCHESTRATOR (Python)       │
│         FastAPI + ilmu-glm-5.1         │
│                                         │
│  Pipeline triggered on application:     │
│                                         │
│  1. Jsoneer:  raw text → JSON          │
│  2. Scraper:  enrich with GitHub/LinkedIn│
│  3. Librarian: verify skills vs evidence │
│  4. Critic:   generate questions        │
│  5. Secretary: compile llm_result       │
│                                         │
│  All results written to MySQL           │
└─────────────────────────────────────────┘
       │
       ▼
  cats_db (MySQL/phpMyAdmin)
  - jobs.job_data (employer requirements)
  - applications.applicant_text (raw resume)
  - applications.applicant_json (structured + scraped)
  - applications.llm_result (evaluation + questions)
```

## Data Flow

1. **Employer posts a job** via `cats/employer/create_job.php` → stored in `jobs.job_data` JSON
2. **Candidate signs up** via `cats/applicant/login.php` → links stored in `applicants.portfolio` JSON
3. **Candidate applies and uploads resume (PDF)** via `cats/applicant/apply.php` → saves PDF, triggers the pipeline
4. **Orchestrator reads `jobs.job_data`** as employer requirements JSON
5. **Jsoneer** parses raw `applicant_text` → structured JSON (skills, work_experience, education, cert, extracurricular) → written to `applications.applicant_json`
6. **Scraper** (`cats/python/scraper.py`) scrapes GitHub/LinkedIn/portfolio links from `applicants.portfolio` → appends `github[]`, `linkedin_titles[]`, `others[]` to `applicant_json` → updated in DB
7. **Librarian** cross-references claimed skills/certs/education/extracurricular against scraped evidence → verification JSON
8. **Critic** generates targeted interview questions with reasoning per question
9. **Secretary** compiles everything into `llm_result` JSON (score, rank, summary, aspect_scores, strengths, gaps, recommended_questions) → written to DB, status set to `evaluated`
10. **Orchestrator re-ranks** all evaluated candidates by score across the job
11. **Frontend** (`cats/employer/applicants.php`) reads `llm_result` and displays ranked candidates

## Agent Details

### Agent 0 — The Jumbled Jsoneer
- **Input**: Raw unstructured resume text
- **Output**: Structured JSON ignoring all personal info
- **Fields**: `skills`, `work_experience`, `education`, `cert`, `extracurricular` (with sub-categories: `competitions`, `community`, `roles`, `project`)
- **Rules**: Languages count as skills; jobs/internships → work_experience; degrees → education; Cisco/Microsoft/CompTIA etc → cert; everything else → extracurricular with categorization

### Agent 1 — The Lexical Librarian
- **Input**: Employer job JSON + applicant JSON (with scraped github/linkedin data)
- **Output**: Verification JSON per skill, cert, work experience, education, extracurricular
- **Key**: Cross-references claimed skills against actual GitHub repos, LinkedIn posts, and portfolio evidence. Considers education relevance, certification validity, and extracurricular engagement.

### Agent 3 — The Curious Critic
- **Input**: Employer JSON + applicant JSON + Librarian verification
- **Output**: 5 targeted interview questions with reasoning per question
- **Key**: Questions are scenario-based, calibrated to experience level, and strategically probe verified/unverified/disputed skills

### Agent 4 — The Succinct Secretary
- **Input**: Employer JSON + Librarian verification + Critic questions
- **Output**: Final `llm_result` JSON matching the target format:
  ```json
  {
    "evaluation_score": 0-100,
    "rank": 1,
    "summary": "...",
    "aspect_scores": {
      "technical_skills": 0-100,
      "work_experience": 0-100,
      "certifications": 0-100,
      "community_engagement": 0-100,
      "project_quality": 0-100
    },
    "strengths": ["..."],
    "gaps": ["..."],
    "recommended_questions": ["..."]
  }
  ```

## Database Schema (cats_db)

| Table | Key Columns |
|-------|-------------|
| `employers` | id, company_name, email, password_hash |
| `applicants` | id, name, email, password_hash, phone, **portfolio** (JSON), location, birth_date |
| `jobs` | id, employer_id, title, company_name, **job_data** (JSON) |
| `applications` | id, applicant_id, job_id, resume_path, **applicant_text** (TEXT), **applicant_json** (JSON), **llm_result** (JSON), status |

### Key JSON Fields

**job_data** (employer requirements):
```json
{
  "job_id": "", "title": "", "company_name": "",
  "company_contact": {"phone": "", "email": ""},
  "office_location": "", "job_type": "", "work_hours": "", "probation": "",
  "salary_range": {"min": "", "max": "", "currency": ""},
  "job_benefits": [],
  "requirements": [{"skills": [], "work_experience": [{"position": "", "time": ""}]}],
  "preferred_skills": [], "project_contributions": []
}
```

**applicant_json** (after Jsoneer + Scraper):
```json
{
  "applicant_id": "", "selected_job_id": "",
  "skills": [], "work_experience": [{"position": "", "time": ""}],
  "education": [], "cert": [],
  "extracurricular": {"competitions": [], "community": [], "roles": [], "project": []},
  "github": [{"title": "", "readme": ""}],
  "linkedin_titles": [], "others": []
}
```

**portfolio** (applicants table, links submitted at signup):
```json
[{"platform": "GitHub", "url": "..."}, {"platform": "LinkedIn", "url": "..."}]
```

## Setup

### Prerequisites
- Python 3.11+ (Anaconda or standalone)
- MySQL/MariaDB with phpMyAdmin
- PHP 7.4+ with mysqli extension

### 1. Database
Import the SQL schema:
```
cats/sql/cats.sql → phpMyAdmin
```
This creates the database, tables, and seed data (1 employer, 1 job, 5 applicants with pre-evaluated applications).

### 2. Python Dependencies
```bash
pip install fastapi uvicorn httpx pydantic aiomysql beautifulsoup4 pdfminer.six
```

### 3. Configure
Edit `config.py` if your MySQL credentials differ from defaults (root, no password).

### 4. Start the AI Backend
```bash
python main.py
```
Server starts at `http://localhost:8000`.

### 5. Start the PHP Frontend
Place the `cats/` folder in your web server root (e.g., XAMPP htdocs) and access:
- Employer: `http://localhost/cats/employer/login.php`
- Applicant: `http://localhost/cats/applicant/login.php`

### 6. Trigger Screening
When a candidate applies, call:
```
POST http://localhost:8000/screening/application/{application_id}
```
Or batch-process all pending applications for a job:
```
POST http://localhost:8000/screening/job/{job_id}
```

## Demo (Without MySQL)
```bash
python demo.py
```
Runs the full pipeline with sample data in memory.

## Testing (Without DB)
```
POST http://localhost:8000/pipeline/run
```
Supply `employer_json` and `raw_texts` array directly.

## File Structure

```
UMHackathon/
├── config.py              # API key, model, MySQL config
├── main.py                # FastAPI app with endpoints
├── orchestrator.py         # Central pipeline controller
├── demo.py                # Standalone demo script
├── requirements.txt       # Python dependencies
├── agents/
│   ├── jsoneer.py         # Agent 0: raw text → structured JSON
│   ├── librarian.py       # Agent 1: skill verification
│   ├── critic.py          # Agent 3: interview questions
│   └── secretary.py       # Agent 4: compile llm_result
├── utils/
│   ├── glm_client.py      # ilmu-glm-5.1 API client
│ 
├── interfaces/
│   ├── employer.py        # Employer API models
│   └── candidate.py       # Applicant API models
├── output/                # Generated reports
└── cats/                  # Frontend (PHP + SQL + Scraper)
    ├── sql/cats.sql       # Database schema + seed data
    ├── config.php         # DB connection config
    ├── python/
    │   ├── extractor.py   # PDF → raw text extraction
    │   └── scraper.py     # GitHub/LinkedIn/portfolio scraper
    ├── employer/
    │   ├── login.php      # Employer auth
    │   ├── create_job.php # Post job listings
    │   ├── dashboard.php  # Overview stats
    │   └── applicants.php # Ranked candidates view
    ├── applicant/
    │   ├── login.php      # Applicant auth + registration
    │   ├── jobs.php       # Browse & apply for jobs
    │   └── apply.php      # Upload resume + trigger pipeline
    └── css/main.css       # Stylesheet
```

## API Reference

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/screening/application/{id}` | POST | Process single application |
| `/screening/job/{id}` | POST | Process all pending for a job (async) |
| `/screening/job-sync/{id}` | POST | Process all pending (sync, blocks) |
| `/jobs` | GET | List active jobs |
| `/health` | GET | Health check |
| `/pipeline/run` | POST | Test without DB |

## Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Employer | hr@techcorp.com | employer123 |
| Applicant | ahmad.razif@email.com | password123 |
| Applicant | priya.nair@email.com | password123 |
| (all applicants use) | | password123 |

## GLM Model

Powered by **ilmu-glm-5.1** via the ilmu.ai API. If GLM is removed, the system loses its ability to:
- Parse unstructured resume text into structured data
- Cross-reference skills against scraped evidence
- Generate contextually relevant interview questions
- Score and rank candidates based on verification strength
