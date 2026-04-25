"""
Agent 4 — The Succinct Secretary
=================================
Receives Librarian verification + Critic questions for an applicant.
Compiles the final llm_result JSON matching the target schema
that the frontend (applicants.php) expects.
"""

import json
import logging
from utils.glm_client import call_glm_json

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are The Succinct Secretary, a report compilation agent for a candidate screening system.

You receive:
1. The employer's job requirement JSON.
2. Lexical Librarian verification JSON for one applicant.
3. Curious Critic question JSON for one applicant.

Your job — produce a single JSON object matching this EXACT structure:
{
  "evaluation_score": 0-100,
  "rank": 1,
  "summary": "A concise paragraph evaluating the applicant for this specific role",
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

Scoring rules:
- evaluation_score is a weighted average: technical_skills 35%, work_experience 25%, certifications 15%, community_engagement 10%, project_quality 15%
- Use the Librarian's verification data: verified skills score higher, unverified lower, disputed score lowest
- Certifications that are verified boost the certifications score significantly
- Education relevance and extracurricular evidence factor into community_engagement and project_quality
- Use the Critic's recommended_questions directly (as an array of strings)
- Strengths and gaps should be specific and actionable
- Set rank to 1 (the orchestrator will re-rank all applicants after evaluation)

Return ONLY the JSON object."""


async def run_secretary(employer_json: dict, librarian_results: list[dict], critic_results: list[dict]) -> list[dict]:
    """Compile llm_result JSONs for all applicants."""

    results = []
    for i, lib in enumerate(librarian_results):
        crit = critic_results[i] if i < len(critic_results) else {}

        user_prompt = f"""## EMPLOYER JOB REQUIREMENTS
```json
{json.dumps(employer_json, indent=2)}
```

## LIBRARIAN VERIFICATION DATA
```json
{json.dumps(lib, indent=2)}
```

## CRITIC INTERVIEW QUESTIONS
```json
{json.dumps(crit, indent=2)}
```

Compile the final evaluation for this applicant. Score fairly based on verified evidence — unverified claims should score lower."""

        try:
            result = await call_glm_json(SYSTEM_PROMPT, user_prompt, temperature=0.2, max_tokens=4096)
        except Exception as e:
            logger.error("Secretary failed for applicant %s: %s", lib.get("applicant_id"), e)
            result = {
                "evaluation_score": 0, "rank": 0,
                "summary": lib.get("profile_summary", "Evaluation failed — manual review required."),
                "aspect_scores": {"technical_skills": 0, "work_experience": 0,
                    "certifications": 0, "community_engagement": 0, "project_quality": 0},
                "strengths": lib.get("strengths", []),
                "gaps": lib.get("red_flags", []),
                "recommended_questions": crit.get("recommended_questions", []),
            }

        result.setdefault("evaluation_score", 0)
        result.setdefault("rank", 0)
        result.setdefault("summary", "")
        result.setdefault("aspect_scores", {})
        result.setdefault("strengths", [])
        result.setdefault("gaps", [])
        result.setdefault("recommended_questions", [])
        result["applicant_id"] = lib.get("applicant_id", "")

        results.append(result)

    return results
