"""
Agent 3 — The Curious Critic
=============================
Receives employer JSON, applicant profile, and Librarian verification.
Generates targeted interview questions per applicant with reasoning.
"""

import json
import logging
from utils.glm_client import call_glm_json

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are The Curious Critic, an expert interview question designer for a candidate screening system.

You receive:
1. An employer's job requirement JSON.
2. An applicant's structured profile JSON (skills, work_experience, education, cert, extracurricular, github, linkedin_titles).
3. The Lexical Librarian's verification JSON for that applicant.

Your job:
- Generate 5 targeted interview questions that:
  - Directly test the skills the applicant claims to have.
  - Prioritize skills that were "verified" or "partially_verified" — confirm them.
  - Probe skills that were "unverified" or "disputed" — determine truth.
  - Test knowledge related to certifications claimed.
  - Are scenario-based or practical rather than trivial recall.
  - Are calibrated to the claimed experience level (harder for senior claims).
- For EACH question, provide:
  - The question itself
  - The skill being tested
  - The reasoning WHY this question should be asked

Return a JSON with this exact structure:
{
  "applicant_id": "...",
  "selected_job_id": "...",
  "recommended_questions": [
    {
      "question": "...",
      "skill_tested": "...",
      "reasoning": "Why this question matters and what to look for in the response"
    }
  ]
}

Questions must be genuinely insightful and scenario-based."""


async def run_critic(employer_json: dict, applicant_json: dict, librarian_json: dict) -> dict:
    user_prompt = f"""## EMPLOYER JOB REQUIREMENTS
```json
{json.dumps(employer_json, indent=2)}
```

## APPLICANT PROFILE
```json
{json.dumps(applicant_json, indent=2)}
```

## SKILL VERIFICATION (from Lexical Librarian)
```json
{json.dumps(librarian_json, indent=2)}
```

Generate targeted interview questions. Test verified skills to confirm them, probe unverified claims, and challenge disputed ones. Every question must have clear reasoning."""

    result = await call_glm_json(SYSTEM_PROMPT, user_prompt, temperature=0.3, max_tokens=8192)

    result.setdefault("applicant_id", applicant_json.get("applicant_id", ""))
    result.setdefault("selected_job_id", applicant_json.get("selected_job_id", ""))
    result.setdefault("recommended_questions", [])

    return result
