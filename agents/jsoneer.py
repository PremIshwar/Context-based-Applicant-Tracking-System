"""
Agent 0 — The Jumbled Jsoneer
==============================
Parses raw, unstructured resume text into a structured JSON.
Ignores all personal info. Extracts skills, work experience,
education, certifications, and categorised extracurriculars.
"""

import json
import logging
from utils.glm_client import call_glm_json

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are The Jumbled Jsoneer, a precise information-extraction agent.

You receive raw, unstructured resume text from an applicant.

YOUR RULES — follow them strictly:

1. IGNORE all personal information: name, age, date of birth, identification numbers, phone numbers, emails, physical addresses, location, links/URLs, social media handles, references, photos, nationality, gender, marital status. Do NOT include any of these in your output.

2. CONSIDER languages (e.g. English, Mandarin, Bahasa Malaysia) as skills.

3. Extract information into EXACTLY this JSON structure:
{
  "applicant_id": "...",
  "selected_job_id": "...",
  "skills": ["..."],
  "work_experience": [{"position": "...", "time": "..."}],
  "education": ["..."],
  "cert": ["..."],
  "extracurricular": {
    "competitions": ["..."],
    "community": ["..."],
    "roles": ["..."],
    "project": ["..."]
  }
}

4. CLASSIFICATION RULES:
   - work_experience: ANY job, career, internship, fellowship, or professional engagement. Include position title and duration.
   - education: Diplomas, degrees, academic qualifications, university/college programs.
   - cert: Professional or skill certifications (Cisco, Microsoft, CompTIA, AWS, CEH, OSCP, PMP, etc.).
   - extracurricular.competitions: Hackathons, coding competitions, sports tournaments, CTFs, case competitions, any competitive participation.
   - extracurricular.community: Volunteering, community service, outreach programs, charity work, mentoring programs.
   - extracurricular.roles: Positions held in clubs, organisations, societies, student councils, committees (e.g. "President of CS Society", "Treasurer of Robotics Club").
   - extracurricular.project: Research papers, publications, personal projects, open-source contributions, capstone projects.

5. REDUCE HALLUCINATIONS:
   - Only extract information that is explicitly stated in the text.
   - Do NOT infer skills that are not mentioned.
   - Do NOT guess time durations if not stated — use "" for unknown.
   - If a category has no items, leave it as an empty array.

6. SKILL EXTRACTION:
   - Extract technical skills (Python, Java, Docker, etc.), soft skills (Leadership, Communication), tools (Splunk, Wireshark), and languages.
   - Be specific: "Machine Learning" not just "AI", "React.js" not just "Frontend".
   - Include skills mentioned in work experience descriptions, project descriptions, and education.

Return ONLY the JSON object, nothing else."""


async def run_jsoneer(applicant_id: str, selected_job_id: str, raw_text: str) -> dict:
    """Parse raw resume text into structured JSON."""

    user_prompt = f"""## APPLICANT ID: {applicant_id}
## SELECTED JOB ID: {selected_job_id}

## RAW RESUME TEXT:
{raw_text}

Extract the structured information from this resume text following the rules strictly. Ignore all personal information. Return only the JSON."""

    result = await call_glm_json(SYSTEM_PROMPT, user_prompt, temperature=0.1, max_tokens=4096)

    # Ensure all required fields exist
    result.setdefault("applicant_id", applicant_id)
    result.setdefault("selected_job_id", selected_job_id)
    result.setdefault("skills", [])
    result.setdefault("work_experience", [])
    result.setdefault("education", [])
    result.setdefault("cert", [])
    result.setdefault("extracurricular", {
        "competitions": [],
        "community": [],
        "roles": [],
        "project": [],
    })

    # Ensure extracurricular sub-fields exist
    ec = result["extracurricular"]
    if not isinstance(ec, dict):
        ec = {"competitions": [], "community": [], "roles": [], "project": []}
        result["extracurricular"] = ec
    ec.setdefault("competitions", [])
    ec.setdefault("community", [])
    ec.setdefault("roles", [])
    ec.setdefault("project", [])

    return result
