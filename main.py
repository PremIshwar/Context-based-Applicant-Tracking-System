"""
HireAgent.AI — AI-Powered Candidate Screening System
=====================================================
Main FastAPI application. Orchestrator reads from cats_db MySQL.
Triggered when candidates apply; runs full screening pipeline.
Run: python main.py
"""

import asyncio
import sys
import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException, BackgroundTasks

from orchestrator import orchestrator

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(name)s] %(levelname)s: %(message)s",
)
logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    sys.stdout.reconfigure(encoding="utf-8")
    await orchestrator.init_db()
    logger.info("HireAgent.AI + MySQL ready")
    # Auto-resume any pending/processing applications from last session
    asyncio.create_task(_startup_resume())
    yield
    await orchestrator.close_db()


async def _startup_resume():
    """Run after startup to resume any pending work."""
    await asyncio.sleep(2)  # let uvicorn finish startup
    await orchestrator.resume_pending()


app = FastAPI(
    title="CATS — AI Screening Engine",
    description="Context-based Application Tracking System — AI Backend",
    version="2.0.0",
    lifespan=lifespan,
)


# ─── Trigger Screening ────────────────────────────────────────────

@app.post("/screening/application/{application_id}")
async def screen_application(application_id: int):
    """Process a single application through the full pipeline."""
    try:
        result = await orchestrator.process_application(application_id)
        if result:
            return {"status": "evaluated", "application_id": application_id, "result": result}
        raise HTTPException(status_code=404, detail="Application not found")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Screening failed: {e}")


@app.post("/screening/job/{job_id}")
async def screen_job(job_id: int, background_tasks: BackgroundTasks):
    """Process all pending applications for a job (async)."""
    background_tasks.add_task(orchestrator.process_job, job_id)
    return {"status": "screening_started", "job_id": job_id}


@app.post("/screening/job-sync/{job_id}")
async def screen_job_sync(job_id: int):
    """Process all pending applications for a job (synchronous)."""
    try:
        result = await orchestrator.process_job(job_id)
        if result:
            return result
        raise HTTPException(status_code=404, detail="Job not found or no pending applications")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Screening failed: {e}")


# ─── Read Endpoints ───────────────────────────────────────────────

@app.get("/jobs")
async def list_jobs():
    jobs = await orchestrator.get_jobs_list()
    return {"jobs": jobs}


@app.get("/")
async def root():
    return {
        "service": "CATS — AI Screening Engine",
        "model": "ilmu-glm-5.1",
        "status": "running",
        "endpoints": {
            "POST /screening/application/{id}": "Process single application",
            "POST /screening/job/{id}": "Process all pending for a job (async)",
            "POST /screening/job-sync/{id}": "Process all pending (sync)",
            "GET /jobs": "List active jobs",
            "GET /health": "Health check",
            "POST /pipeline/run": "Test without DB",
            "GET /docs": "API documentation",
        },
    }


@app.delete("/screening/application/{application_id}")
async def cancel_application(application_id: int):
    """Cancel and delete an application."""
    app = await orchestrator.get_application(application_id)
    if not app:
        raise HTTPException(status_code=404, detail="Application not found")
    await orchestrator.delete_application(application_id)
    return {"status": "deleted", "application_id": application_id}


@app.get("/health")
async def health():
    return {"status": "ok", "service": "CATS AI Engine", "model": "ilmu-glm-5.1"}


# ─── Direct JSON Pipeline (testing without DB) ────────────────────

@app.post("/pipeline/run")
async def run_full_pipeline(employer_json: dict, raw_texts: list[dict]):
    """One-shot: supply employer JSON + raw resume texts, get results."""
    result = await orchestrator.run_screening_from_json(employer_json, raw_texts)
    return result


if __name__ == "__main__":
    import uvicorn
    sys.stdout.reconfigure(encoding="utf-8")
    uvicorn.run("main:app", host="127.0.0.1", port=8000, reload=True)
