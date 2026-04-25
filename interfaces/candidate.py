"""
Candidate / Applicant Web Interface — bare-bones FastAPI gateway.
Frontend provided separately; this exposes the API contract.
Applicant data comes from the `applications.applicant_json` JSON column in MySQL.
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from typing import List, Optional

router = APIRouter(prefix="/candidate", tags=["candidate"])


class Identification(BaseModel):
    type: str = ""
    number: str = ""


class ContactInfo(BaseModel):
    email: str = ""
    phone: str = ""


class SocialLink(BaseModel):
    platform: str = ""
    url: str = ""


class LinkedInPost(BaseModel):
    content: str = ""
    date: str = ""


class WorkExperience(BaseModel):
    position: str = ""
    time: str = ""


class ApplicantInput(BaseModel):
    applicant_id: str = ""
    identification: Identification = Identification()
    name: str = ""
    selected_job_id: str = ""
    contact_info: ContactInfo = ContactInfo()
    location: str = ""
    skills: List[str] = []
    socials: List[SocialLink] = []
    linkedin_posts: List[LinkedInPost] = []
    work_experience: List[WorkExperience] = []
    github_handle: str = ""
    portfolio_titles: List[str] = []


_candidate_store: dict[str, ApplicantInput] = {}


@router.post("/submit-profile")
async def submit_profile(data: ApplicantInput):
    if not data.applicant_id:
        raise HTTPException(status_code=400, detail="applicant_id is required")
    if not data.selected_job_id:
        raise HTTPException(status_code=400, detail="selected_job_id is required")
    _candidate_store[data.applicant_id] = data
    return {"status": "received", "applicant_id": data.applicant_id}


@router.get("/profile/{applicant_id}")
async def get_profile(applicant_id: str):
    if applicant_id not in _candidate_store:
        raise HTTPException(status_code=404, detail="Applicant not found")
    return _candidate_store[applicant_id]


@router.get("/applicants-for-job/{job_id}")
async def applicants_for_job(job_id: str):
    ids = [aid for aid, a in _candidate_store.items() if a.selected_job_id == job_id]
    return {"job_id": job_id, "applicants": ids}


def get_stored_candidates() -> dict:
    return _candidate_store
