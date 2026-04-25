"""
Employer Web Interface — bare-bones FastAPI gateway.
Frontend provided separately; this exposes the API contract.
Employer data comes from the `jobs.job_data` JSON column in MySQL.
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from typing import List, Optional

router = APIRouter(prefix="/employer", tags=["employer"])


class SalaryRange(BaseModel):
    min: str = ""
    max: str = ""
    currency: str = ""


class CompanyContact(BaseModel):
    phone: str = ""
    email: str = ""


class WorkExperience(BaseModel):
    position: str = ""
    time: str = ""


class RequirementBlock(BaseModel):
    skills: List[str] = []
    work_experience: List[WorkExperience] = []


class EmployerInput(BaseModel):
    job_id: str = ""
    title: str = ""
    company_name: str = ""
    company_contact: CompanyContact = CompanyContact()
    office_location: str = ""
    job_type: str = ""
    work_hours: str = ""
    probation: str = ""
    salary_range: SalaryRange = SalaryRange()
    job_benefits: List[str] = []
    requirements: List[RequirementBlock] = []
    preferred_skills: List[str] = []
    project_contributions: List[str] = []


_job_store: dict[str, EmployerInput] = {}


@router.post("/submit-job")
async def submit_job(data: EmployerInput):
    if not data.job_id:
        raise HTTPException(status_code=400, detail="job_id is required")
    _job_store[data.job_id] = data
    return {"status": "received", "job_id": data.job_id}


@router.get("/job/{job_id}")
async def get_job(job_id: str):
    if job_id not in _job_store:
        raise HTTPException(status_code=404, detail="Job not found")
    return _job_store[job_id]


@router.get("/jobs")
async def list_jobs():
    return {"jobs": list(_job_store.keys())}


def get_stored_jobs() -> dict:
    return _job_store
