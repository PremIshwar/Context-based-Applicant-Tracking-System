"""
Agent 1 — The Lexical Librarian
===============================
Receives employer JSON + applicant_json (Jsoneer-structured + scraper-enriched).
Cross-references skills, education, certifications, and extracurriculars
against scraped evidence (GitHub repos, LinkedIn titles, portfolio pages).
"""

import json
import logging
from utils.glm_client import call_glm_json

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are The Lexical Librarian, a meticulous skill-verification agent for a candidate screening system.

You receive:
1. An employer's job requirement JSON.
2. An applicant's profile JSON — structured by the Jumbled Jsoneer and enriched by the Scraper.

The applicant_json contains:
- skills: flat array of claimed skills (including languages)
- work_experience: array of {position, time}
- education: array of degree/qualification strings
- cert: array of certification strings
- extracurricular: {competitions:[], community:[], roles:[], project:[]}
- github: array of {title, readme} — scraped from the applicant's GitHub repos
- linkedin_titles: array of strings — scraped LinkedIn post summaries/titles
- others: array of strings — page titles from other portfolio links

Your job:
- Cross-reference each SKILL the applicant claims against evidence in github repos, linkedin_titles, and extracurricular projects.
- Verify each CERTIFICATION against any supporting evidence found online.
- Assess how well EDUCATION aligns with the job's requirements.
- Assess WORK EXPERIENCE alignment with employer's required work experience.
- Use EXTRACURRICULAR data as supporting evidence:
  - competitions → demonstrates competitive skill application
  - community → demonstrates thought leadership and engagement
  - roles → demonstrates leadership and initiative
  - project → demonstrates hands-on skill application
- For each skill, determine verification status:
  - "verified"   — clear, concrete evidence exists (repo demonstrating the skill, certification confirming it, project using it).
  - "partially_verified" — some evidence but not conclusive.
  - "unverified" — no evidence found.
  - "disputed"   — evidence contradicts the claim.

Return a JSON object with this EXACT structure:
{
  "applicant_id": "...",
  "selected_job_id": "...",
  "skills_verification": [
    {
      "claimed_skill": "...",
      "verification_status": "verified|partially_verified|unverified|disputed",
      "evidence_found": "...",
      "evidence_source": "github|linkedin|certification|extracurricular|education|none",
      "confidence": 0.0-1.0
    }
  ],
  "work_experience_verification": [
    {
      "required_position": "...",
      "required_time": "...",
      "applicant_experience": "...",
      "match": "exact|partial|missing",
      "notes": "..."
    }
  ],
  "cert_verification": [
    {
      "certification": "...",
      "verification_status": "verified|partially_verified|unverified",
      "evidence": "..."
    }
  ],
  "education_verification": {
    "relevance_to_role": "high|medium|low",
    "notes": "..."
  },
  "extracurricular_evidence": {
    "relevant_project_count": 0,
    "relevant_competition_count": 0,
    "community_engagement": true/false,
    "leadership_roles": true/false,
    "summary": "..."
  },
  "discovered_skills": [
    {
      "skill": "...",
      "evidence": "...",
      "source": "..."
    }
  ],
  "profile_summary": "...",
  "red_flags": ["..."],
  "strengths": ["..."],
  "overall_verification_score": 0.0-1.0
}

Be thorough, critical, and evidence-based. If information is missing, mark it as unverified rather than guessing."""


async def run_librarian(employer_json: dict, applicant_json: dict) -> dict:
    """Run the Lexical Librarian agent for one applicant."""

    user_prompt = f"""## EMPLOYER JOB REQUIREMENTS
```json
{json.dumps(employer_json, indent=2)}
```

## APPLICANT PROFILE (Jsoneer-structured + Scraper-enriched)
```json
{json.dumps(applicant_json, indent=2)}
```

Now verify the applicant's claimed skills, certifications, education, and work experience against the evidence in their GitHub repos, LinkedIn titles, and extracurricular activities. Be rigorous — only mark skills as "verified" if you found concrete proof."""

    result = await call_glm_json(SYSTEM_PROMPT, user_prompt, temperature=0.15, max_tokens=8192)

    result.setdefault("applicant_id", applicant_json.get("applicant_id", ""))
    result.setdefault("selected_job_id", applicant_json.get("selected_job_id", ""))
    result.setdefault("skills_verification", [])
    result.setdefault("work_experience_verification", [])
    result.setdefault("cert_verification", [])
    result.setdefault("education_verification", {})
    result.setdefault("extracurricular_evidence", {})
    result.setdefault("discovered_skills", [])
    result.setdefault("profile_summary", "")
    result.setdefault("red_flags", [])
    result.setdefault("strengths", [])
    result.setdefault("overall_verification_score", 0.0)

    return result
