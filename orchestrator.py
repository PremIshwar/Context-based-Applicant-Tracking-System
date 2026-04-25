"""
The Orchestrator — Central Reasoning Engine
============================================
Triggered when a candidate applies. Reads job_data + applicant_text
from MySQL, runs the full pipeline, writes results back.

On startup, automatically resumes all pending/processing applications.
On API errors, waits 120s then retries from the last checkpoint.

Pipeline per application:
  1. Jsoneer:  raw text → structured JSON          (checkpoint: applicant_json written)
  2. Scraper:  enrich with GitHub/LinkedIn data     (checkpoint: applicant_json updated)
  3. Librarian: verify skills against evidence      (checkpoint: librarian done)
  4. Critic:   generate interview questions         (checkpoint: critic done)
  5. Secretary: compile final llm_result            (checkpoint: evaluated)
"""

import json
import asyncio
import logging
import subprocess
import sys
import os
from typing import Optional

import aiomysql

from agents.jsoneer import run_jsoneer
from agents.librarian import run_librarian
from agents.critic import run_critic
from agents.secretary import run_secretary

from config import (
    MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE,
)

logger = logging.getLogger(__name__)

CATS_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "cats")
PYTHON_EXE = sys.executable

API_RETRY_DELAY = 120  # seconds to wait before retrying on API errors
MAX_RETRIES = 5         # max retries per phase before giving up


class Orchestrator:
    """Stateful orchestrator with checkpoint resume and retry logic."""

    def __init__(self):
        self.pool: Optional[aiomysql.Pool] = None

    # ─── Database ───────────────────────────────────────────────────

    async def init_db(self):
        self.pool = await aiomysql.create_pool(
            host=MYSQL_HOST, port=MYSQL_PORT,
            user=MYSQL_USER, password=MYSQL_PASSWORD,
            db=MYSQL_DATABASE, charset="utf8mb4",
            autocommit=True, minsize=1, maxsize=5,
        )
        logger.info("MySQL connection pool initialized")

    async def close_db(self):
        if self.pool:
            self.pool.close()
            await self.pool.wait_closed()

    async def get_job(self, job_id: int) -> Optional[dict]:
        async with self.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(
                    "SELECT id, job_data FROM jobs WHERE id = %s AND is_active = 1", (job_id,),
                )
                row = await cur.fetchone()
                if not row:
                    return None
                jd = row["job_data"]
                if isinstance(jd, str):
                    jd = json.loads(jd)
                jd.setdefault("job_id", f"JOB-{row['id']}")
                return jd

    async def get_application(self, application_id: int) -> Optional[dict]:
        async with self.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(
                    """SELECT a.id, a.applicant_id, a.job_id, a.applicant_text,
                              a.applicant_json, a.llm_result, a.status,
                              ap.name as applicant_name, ap.portfolio
                       FROM applications a
                       JOIN applicants ap ON a.applicant_id = ap.id
                       WHERE a.id = %s""",
                    (application_id,),
                )
                row = await cur.fetchone()
                if not row:
                    return None
                aj = row.get("applicant_json")
                if aj and isinstance(aj, str):
                    aj = json.loads(aj)
                portfolio = row.get("portfolio")
                if portfolio and isinstance(portfolio, str):
                    portfolio = json.loads(portfolio)
                return {
                    "application_id": row["id"],
                    "applicant_id": row["applicant_id"],
                    "job_id": row["job_id"],
                    "applicant_name": row.get("applicant_name", ""),
                    "applicant_text": row.get("applicant_text", ""),
                    "applicant_json": aj,
                    "portfolio": portfolio or [],
                    "status": row["status"],
                }

    async def get_pending_applications(self) -> list[dict]:
        """Get all applications with status 'pending' or 'processing'."""
        async with self.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(
                    """SELECT a.id, a.applicant_id, a.job_id, a.applicant_text,
                              a.applicant_json, a.status,
                              ap.name as applicant_name, ap.portfolio
                       FROM applications a
                       JOIN applicants ap ON a.applicant_id = ap.id
                       WHERE a.status IN ('pending', 'processing')
                       ORDER BY a.applied_at ASC"""
                )
                rows = await cur.fetchall()
        results = []
        for row in rows:
            aj = row.get("applicant_json")
            if aj and isinstance(aj, str):
                aj = json.loads(aj)
            portfolio = row.get("portfolio")
            if portfolio and isinstance(portfolio, str):
                portfolio = json.loads(portfolio)
            results.append({
                "application_id": row["id"],
                "applicant_id": row["applicant_id"],
                "job_id": row["job_id"],
                "applicant_name": row.get("applicant_name", ""),
                "applicant_text": row.get("applicant_text", ""),
                "applicant_json": aj,
                "portfolio": portfolio or [],
                "status": row["status"],
            })
        return results

    async def get_applications_for_job(self, job_id: int) -> list[dict]:
        async with self.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(
                    """SELECT a.id, a.applicant_id, a.job_id, a.applicant_text,
                              a.applicant_json, a.llm_result, a.status,
                              ap.name as applicant_name, ap.portfolio
                       FROM applications a
                       JOIN applicants ap ON a.applicant_id = ap.id
                       WHERE a.job_id = %s""",
                    (job_id,),
                )
                rows = await cur.fetchall()
        results = []
        for row in rows:
            aj = row.get("applicant_json")
            if aj and isinstance(aj, str):
                aj = json.loads(aj)
            portfolio = row.get("portfolio")
            if portfolio and isinstance(portfolio, str):
                portfolio = json.loads(portfolio)
            results.append({
                "application_id": row["id"],
                "applicant_id": row["applicant_id"],
                "job_id": row["job_id"],
                "applicant_name": row.get("applicant_name", ""),
                "applicant_text": row.get("applicant_text", ""),
                "applicant_json": aj,
                "portfolio": portfolio or [],
                "status": row["status"],
            })
        return results

    async def write_applicant_json(self, application_id: int, applicant_json: dict):
        async with self.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute(
                    "UPDATE applications SET applicant_json = %s WHERE id = %s",
                    (json.dumps(applicant_json, ensure_ascii=False), application_id),
                )
        logger.info("Wrote applicant_json for application %d", application_id)

    async def write_llm_result(self, application_id: int, llm_result: dict):
        async with self.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute(
                    "UPDATE applications SET llm_result = %s, status = 'evaluated' WHERE id = %s",
                    (json.dumps(llm_result, ensure_ascii=False), application_id),
                )
        logger.info("Wrote llm_result for application %d", application_id)

    async def update_status(self, application_id: int, status: str):
        async with self.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute(
                    "UPDATE applications SET status = %s WHERE id = %s",
                    (status, application_id),
                )

    async def get_jobs_list(self) -> list[dict]:
        async with self.pool.acquire() as conn:
            async with conn.cursor(aiomysql.DictCursor) as cur:
                await cur.execute(
                    "SELECT id, title, company_name, is_active, created_at FROM jobs WHERE is_active = 1"
                )
                return await cur.fetchall()

    async def delete_application(self, application_id: int):
        async with self.pool.acquire() as conn:
            async with conn.cursor() as cur:
                await cur.execute(
                    "DELETE FROM applications WHERE id = %s", (application_id,),
                )
        logger.info("Deleted application %d", application_id)

    # ─── Scraper Integration ────────────────────────────────────────

    def _run_scraper(self, applicant_json: dict, portfolio: list) -> dict:
        scraper_path = os.path.join(CATS_DIR, "python", "scraper.py")
        try:
            result = subprocess.run(
                [PYTHON_EXE, scraper_path,
                 json.dumps(applicant_json, ensure_ascii=False),
                 json.dumps(portfolio, ensure_ascii=False)],
                capture_output=True, text=True, timeout=60,
            )
            if result.returncode == 0 and result.stdout.strip():
                lines = [l for l in result.stdout.strip().split("\n") if l.strip()]
                last = lines[-1]
                enriched = json.loads(last)
                logger.info("Scraper enriched applicant_json with %d github repos",
                            len(enriched.get("github", [])))
                return enriched
            else:
                logger.warning("Scraper returned no output: %s", result.stderr[:200])
        except Exception as e:
            logger.error("Scraper failed: %s", e)
        return applicant_json

    # ─── Retry Helper ───────────────────────────────────────────────

    async def _retry_call(self, coro_factory, application_id: int, phase_name: str):
        """Call a coroutine with retry logic. Waits 120s on API errors."""
        for attempt in range(1, MAX_RETRIES + 1):
            try:
                return await coro_factory()
            except Exception as e:
                err_str = str(e).lower()
                is_api_error = any(kw in err_str for kw in [
                    "504", "502", "500", "429", "timeout", "connection",
                    "gateway", "rate limit", "overloaded",
                ])
                if is_api_error and attempt < MAX_RETRIES:
                    logger.warning(
                        "[App %d] %s failed (attempt %d/%d): %s — retrying in %ds",
                        application_id, phase_name, attempt, MAX_RETRIES,
                        str(e)[:100], API_RETRY_DELAY,
                    )
                    await asyncio.sleep(API_RETRY_DELAY)
                else:
                    raise

    # ─── Main Pipeline with Checkpoint Resume ───────────────────────

    async def process_application(self, application_id: int) -> Optional[dict]:
        """
        Full pipeline for a single application with checkpoint resume.
        Each phase writes its result to DB, so if the pipeline crashes
        and restarts, it picks up from the last completed phase.
        """
        app = await self.get_application(application_id)
        if not app:
            logger.error("Application %d not found", application_id)
            return None

        job_id = app["job_id"]
        employer_json = await self.get_job(job_id)
        if not employer_json:
            logger.error("Job %d not found", job_id)
            return None

        # If already evaluated, skip
        if app["status"] == "evaluated":
            logger.info("[App %d] Already evaluated, skipping", application_id)
            return None

        await self.update_status(application_id, "processing")

        # ── Determine checkpoint: what's already done? ──
        applicant_json = app.get("applicant_json")
        has_jsoneer = applicant_json is not None and "skills" in applicant_json
        has_scraper = applicant_json is not None and ("github" in applicant_json or "linkedin_titles" in applicant_json)

        try:
            # ── Phase 1: Jsoneer (skip if applicant_json already structured) ──
            if not has_jsoneer:
                logger.info("[App %d] Phase 1: Jsoneer", application_id)
                raw_text = app.get("applicant_text", "")
                if raw_text:
                    applicant_json = await self._retry_call(
                        lambda: run_jsoneer(
                            str(app["applicant_id"]), str(job_id), raw_text
                        ),
                        application_id, "Jsoneer",
                    )
                else:
                    applicant_json = _fallback_jsoneer(str(app["applicant_id"]), str(job_id))

                await self.write_applicant_json(application_id, applicant_json)
            else:
                logger.info("[App %d] Phase 1: Jsoneer — SKIPPED (already done)", application_id)

            # ── Phase 2: Scraper (skip if github/linkedin already in applicant_json) ──
            if not has_scraper:
                logger.info("[App %d] Phase 2: Scraper", application_id)
                portfolio = app.get("portfolio", [])
                loop = asyncio.get_event_loop()
                applicant_json = await loop.run_in_executor(
                    None, self._run_scraper, applicant_json, portfolio
                )
                await self.write_applicant_json(application_id, applicant_json)
            else:
                logger.info("[App %d] Phase 2: Scraper — SKIPPED (already done)", application_id)

            # ── Phase 3: Librarian ──
            logger.info("[App %d] Phase 3: Librarian", application_id)
            librarian_result = await self._retry_call(
                lambda: run_librarian(employer_json, applicant_json),
                application_id, "Librarian",
            )
            librarian_result["applicant_id"] = str(app["applicant_id"])
            librarian_result["selected_job_id"] = str(job_id)

            # ── Phase 4: Critic ──
            logger.info("[App %d] Phase 4: Critic", application_id)
            critic_result = await self._retry_call(
                lambda: run_critic(employer_json, applicant_json, librarian_result),
                application_id, "Critic",
            )
            critic_result["applicant_id"] = str(app["applicant_id"])
            critic_result["selected_job_id"] = str(job_id)

            # ── Phase 5: Secretary ──
            logger.info("[App %d] Phase 5: Secretary", application_id)
            try:
                llm_result = await self._retry_call(
                    lambda: run_secretary(employer_json, [librarian_result], [critic_result]),
                    application_id, "Secretary",
                )
                if isinstance(llm_result, list) and llm_result:
                    llm_result = llm_result[0]
            except Exception as e:
                logger.error("Secretary failed after retries: %s", e)
                llm_result = _build_fallback_llm_result(app, librarian_result, critic_result)

            llm_result["applicant_id"] = str(app["applicant_id"])
            await self.write_llm_result(application_id, llm_result)

            logger.info("[App %d] Complete — score: %s", application_id,
                        llm_result.get("evaluation_score", "?"))
            return llm_result

        except Exception as e:
            # Revert to pending so it will be picked up on next startup/retry
            await self.update_status(application_id, "pending")
            logger.error("[App %d] Pipeline failed after all retries: %s", application_id, e)
            raise

    # ─── Startup: Resume Pending Applications ───────────────────────

    async def resume_pending(self):
        """On startup, find and process all pending/processing applications."""
        pending = await self.get_pending_applications()
        if not pending:
            logger.info("No pending applications to resume")
            return

        logger.info("Resuming %d pending/processing applications", len(pending))
        for app in pending:
            try:
                await self.process_application(app["application_id"])
            except Exception as e:
                logger.error("Failed to resume application %d: %s", app["application_id"], e)

        # Re-rank all affected jobs
        job_ids = set(app["job_id"] for app in pending)
        for jid in job_ids:
            try:
                await self._rerank_job(jid)
            except Exception as e:
                logger.error("Failed to re-rank job %d: %s", jid, e)

    # ─── Batch: Process All for a Job ───────────────────────────────

    async def process_job(self, job_id: int) -> Optional[dict]:
        employer_json = await self.get_job(job_id)
        if not employer_json:
            return None

        applications = await self.get_applications_for_job(job_id)
        if not applications:
            return None

        pending = [a for a in applications if a["status"] != "evaluated"]
        if not pending:
            logger.info("All applications for job %d already evaluated", job_id)
            return None

        logger.info("Processing %d applications for job %d", len(pending), job_id)

        results = []
        for app in pending:
            try:
                result = await self.process_application(app["application_id"])
                if result:
                    results.append(result)
            except Exception as e:
                logger.error("Failed for application %d: %s", app["application_id"], e)

        await self._rerank_job(job_id)

        return {"job_id": job_id, "processed": len(results), "results": results}

    async def _rerank_job(self, job_id: int):
        applications = await self.get_applications_for_job(job_id)
        evaluated = []
        for app in applications:
            if app["status"] == "evaluated":
                async with self.pool.acquire() as conn:
                    async with conn.cursor(aiomysql.DictCursor) as cur:
                        await cur.execute(
                            "SELECT llm_result FROM applications WHERE id = %s",
                            (app["application_id"],),
                        )
                        row = await cur.fetchone()
                        if row and row["llm_result"]:
                            llm = row["llm_result"]
                            if isinstance(llm, str):
                                llm = json.loads(llm)
                            evaluated.append({
                                "application_id": app["application_id"],
                                "score": llm.get("evaluation_score", 0),
                            })

        evaluated.sort(key=lambda x: x["score"], reverse=True)
        for i, ev in enumerate(evaluated):
            async with self.pool.acquire() as conn:
                async with conn.cursor() as cur:
                    await cur.execute(
                        "SELECT llm_result FROM applications WHERE id = %s",
                        (ev["application_id"],),
                    )
                    row = await cur.fetchone()
                    llm = row["llm_result"]
                    if isinstance(llm, str):
                        llm = json.loads(llm)
                    llm["rank"] = i + 1
                    await cur.execute(
                        "UPDATE applications SET llm_result = %s WHERE id = %s",
                        (json.dumps(llm, ensure_ascii=False), ev["application_id"]),
                    )

    # ─── Direct JSON Pipeline (testing without DB) ─────────────────

    async def run_screening_from_json(
        self, employer_json: dict, raw_texts: list[dict],
    ) -> dict:
        structured = []
        for rt in raw_texts:
            try:
                s = await run_jsoneer(rt["applicant_id"], rt.get("selected_job_id", ""), rt["text"])
                structured.append(s)
            except Exception as e:
                logger.error("Jsoneer failed for %s: %s", rt["applicant_id"], e)
                structured.append(_fallback_jsoneer(rt["applicant_id"], rt.get("selected_job_id", "")))

        librarian_results = []
        critic_results = []
        for i, s in enumerate(structured):
            try:
                lr = await run_librarian(employer_json, s)
                lr["applicant_id"] = s["applicant_id"]
                librarian_results.append(lr)
            except Exception as e:
                logger.error("Librarian failed for %s: %s", s["applicant_id"], e)
                librarian_results.append({"applicant_id": s["applicant_id"],
                    "skills_verification": [], "profile_summary": f"Failed: {e}",
                    "red_flags": ["Verification failed"], "strengths": [],
                    "overall_verification_score": 0.0})

            try:
                cr = await run_critic(employer_json, s, librarian_results[-1])
                cr["applicant_id"] = s["applicant_id"]
                critic_results.append(cr)
            except Exception as e:
                logger.error("Critic failed for %s: %s", s["applicant_id"], e)
                critic_results.append({"applicant_id": s["applicant_id"],
                    "recommended_questions": []})

        try:
            final_results = await run_secretary(employer_json, librarian_results, critic_results)
        except Exception as e:
            logger.error("Secretary failed: %s", e)
            final_results = []
            for i, lr in enumerate(librarian_results):
                final_results.append(_build_fallback_llm_result(
                    {"applicant_id": structured[i].get("applicant_id", ""),
                     "applicant_name": ""}, lr,
                    critic_results[i] if i < len(critic_results) else {},
                ))

        return {
            "job_id": employer_json.get("job_id", ""),
            "title": employer_json.get("title", ""),
            "total_applicants": len(structured),
            "structured_profiles": structured,
            "results": final_results if isinstance(final_results, list) else [final_results],
        }


# ─── Fallback Builders ─────────────────────────────────────────────

def _fallback_jsoneer(applicant_id: str, job_id: str) -> dict:
    return {
        "applicant_id": applicant_id, "selected_job_id": job_id,
        "skills": [], "work_experience": [], "education": [],
        "cert": [],
        "extracurricular": {"competitions": [], "community": [], "roles": [], "project": []},
    }


def _build_fallback_llm_result(app: dict, librarian: dict, critic: dict) -> dict:
    return {
        "evaluation_score": 0, "rank": 0,
        "summary": librarian.get("profile_summary", "Fallback — manual review required."),
        "aspect_scores": {
            "technical_skills": 0, "work_experience": 0,
            "certifications": 0, "community_engagement": 0, "project_quality": 0,
        },
        "strengths": librarian.get("strengths", []),
        "gaps": librarian.get("red_flags", []),
        "recommended_questions": critic.get("recommended_questions", []),
    }


orchestrator = Orchestrator()
