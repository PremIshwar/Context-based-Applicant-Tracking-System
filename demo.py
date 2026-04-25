"""
Demo script — runs the full pipeline with raw resume text.
Tests the Jsoneer → Librarian → Critic → Secretary flow.
Usage: python demo.py
"""

import asyncio
import json
import sys
import os

sys.stdout.reconfigure(encoding="utf-8")
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from orchestrator import orchestrator


SAMPLE_EMPLOYER = {
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
                {"position": "SOC Analyst", "time": "3 years"},
                {"position": "Network Administrator", "time": "2 years"}
            ]
        }
    ],
    "preferred_skills": ["CEH Certification", "OSCP", "Splunk", "AWS Security", "Threat Intelligence"],
    "project_contributions": ["Open source security tools", "CTF competitions", "Bug bounty programs"]
}

SAMPLE_RAW_TEXTS = [
    {
        "applicant_id": "1",
        "selected_job_id": "JOB-2025-001",
        "text": """AHMAD RAZIF
ahmad.razif@email.com | +60-12-345-6789
NRIC: 920101-14-5678
Kuala Lumpur, Malaysia

SKILLS
Python, SIEM Tools (Splunk, IBM QRadar), Incident Response, Linux (Ubuntu, CentOS), Network Security, Penetration Testing, Firewall Management (Palo Alto, Fortinet), CEH, OSCP, Bash Scripting, English, Bahasa Malaysia

WORK EXPERIENCE
SOC Analyst — CyberDefend Sdn Bhd (Jan 2021 - Present, 4 years)
- Monitored and analyzed security events using Splunk SIEM across 500+ endpoints
- Led incident response for 3 critical ransomware containment operations
- Developed automated threat hunting playbooks in Python reducing MTTR by 40%
- Mentored 2 junior analysts on incident triage procedures

Network Administrator — DataNet Solutions (Mar 2019 - Dec 2020, 2 years)
- Managed Palo Alto and Fortinet firewalls for enterprise network of 1000+ users
- Implemented network segmentation and VPN tunnels for remote workforce
- Conducted vulnerability assessments using Nessus and OpenVAS
- Maintained 99.9% network uptime across 3 office locations

EDUCATION
BSc (Hons) Computer Science — University of Malaya (2015-2019)

CERTIFICATIONS
- Certified Ethical Hacker (CEH) — EC-Council (2022)
- Offensive Security Certified Professional (OSCP) — OffSec (2023)
- CompTIA Security+ (2020)

EXTRACURRICULAR
- Speaker at OWASP KL Meetup on Zero-Day Mitigation Strategies (Jan 2025)
- Red Team Engagement for Financial Institution — Pro Bono (Mar 2025)
- HackTheBox Active Player — Top 5% Global Ranking
- CTF Writeups Author — Personal Blog and GitHub
- President of Cybersecurity Society — University of Malaya (2017-2018)

PROJECTS
- py-threat-hunter: Python tool for automated threat hunting using MITRE ATT&CK framework (GitHub: 200+ stars)
- siem-dashboard: Custom Splunk dashboard for real-time SOC monitoring
- network-scanner: Multi-threaded network vulnerability scanner in Python
- ctf-writeups: Collection of CTF challenge solutions and methodologies

LINKS
LinkedIn: https://linkedin.com/in/ahmadrazif
GitHub: https://github.com/ahmadrazif""",
    },
    {
        "applicant_id": "2",
        "selected_job_id": "JOB-2025-001",
        "text": """PRIYA NAIR
priya.nair@email.com | +60-16-789-0123
NRIC: 930515-10-1234
Petaling Jaya, Malaysia

SKILLS
Python, Network Security, Incident Response, Linux, SIEM Tools, AWS Security, Threat Intelligence, Terraform, English, Bahasa Malaysia, Tamil

WORK EXPERIENCE
SOC Analyst — SecureOps Technologies (Jun 2022 - Present, 3 years)
- Managed incident response workflow for 200+ security incidents per quarter
- Developed and maintained 50+ SIEM correlation rules for threat detection
- Created automated incident response playbooks reducing response time by 35%
- Conducted threat hunting using MITRE ATT&CK framework

IT Support Engineer — TechStart Solutions (Jan 2021 - May 2022, 1 year)
- Provided L2 technical support for enterprise clients
- Assisted in basic security incident triage and escalation
- Managed endpoint protection deployment across 500+ devices

EDUCATION
BSc Information Technology — Universiti Teknologi Malaysia (2017-2021)

CERTIFICATIONS
- AWS Certified Security - Specialty (2025)
- CompTIA Security+ (2021)

EXTRACURRICULAR
- Volunteer Mentor — Women in Cybersecurity Malaysia (2023-Present)
- Article Contributor — Supply Chain Attack Analysis (LinkedIn, Feb 2025)

PROJECTS
- aws-security-scripts: Collection of AWS security automation scripts in Python
- incident-playbooks: Standardized IR playbooks for common attack scenarios
- log-analyzer: Python-based log analysis tool for security event correlation

LINKS
LinkedIn: https://linkedin.com/in/priyanair
GitHub: https://github.com/priyanair""",
    },
    {
        "applicant_id": "3",
        "selected_job_id": "JOB-2025-001",
        "text": """WEI LIANG TAN
weiliang.tan@email.com | +60-11-222-3333
NRIC: 950820-14-9876
Shah Alam, Malaysia

SKILLS
Python, Linux (Ubuntu, RHEL), Firewall Management (iptables, pfSense), Network Security, Wireshark, TCP/IP, English, Bahasa Malaysia, Mandarin

WORK EXPERIENCE
Network Administrator — NetConnect Sdn Bhd (Feb 2022 - Present, 3 years)
- Configured and managed pfSense and iptables firewalls for 3 data centers
- Implemented network monitoring using Nagios and custom Python scripts
- Performed regular network security audits and penetration tests
- Managed VLAN configurations and access control lists

Junior SOC Analyst — CyberWatch Malaysia (Jan 2021 - Jan 2022, 1 year)
- Monitored security alerts and performed initial triage
- Escalated critical incidents to senior analysts
- Maintained security event logs and documentation
- Assisted in incident response procedures

EDUCATION
Diploma in Network Engineering — Politeknik Shah Alam (2018-2021)

CERTIFICATIONS
None listed

EXTRACURRICULAR
None listed

PROJECTS
- firewall-rules-generator: Python tool to auto-generate firewall rules from network policies
- network-monitoring-scripts: Collection of Python scripts for network health monitoring

LINKS
GitHub: https://github.com/wltan""",
    },
    {
        "applicant_id": "4",
        "selected_job_id": "JOB-2025-001",
        "text": """NURUL AIN
nurul.ain@email.com | +60-17-456-7890
NRIC: 980305-10-4567
Kuala Lumpur, Malaysia

SKILLS
Python (Basic), Linux (Basic), Basic Networking, English, Bahasa Malaysia

WORK EXPERIENCE
IT Intern — StartupHub Malaysia (Jun 2024 - Dec 2024, 6 months)
- Provided desktop support for 50+ employees
- Assisted in basic network troubleshooting
- Helped maintain IT inventory and documentation
- Shadowed security team during incident response drills

EDUCATION
BSc Computer Science — Universiti Kebangsaan Malaysia (2021-2024)

CERTIFICATIONS
None

EXTRACURRICULAR
- Member of Computer Science Club — UKM (2022-2024)

PROJECTS
None

LINKS
LinkedIn: https://linkedin.com/in/nurulain""",
    },
    {
        "applicant_id": "5",
        "selected_job_id": "JOB-2025-001",
        "text": """DARREN CHONG
darren.chong@email.com | +60-19-876-5432
NRIC: 910712-14-3322
Klang, Malaysia

SKILLS
Windows Administration, Microsoft Office Suite, Basic Python, English, Bahasa Malaysia

WORK EXPERIENCE
Desktop Support Technician — Klang IT Services (Jan 2021 - Present, 4 years)
- Provided L1 and L2 desktop support for Windows environments
- Managed Active Directory user accounts and group policies
- Performed hardware troubleshooting and repairs
- Maintained IT asset inventory and license management

EDUCATION
Diploma in Information Technology — Kolej Tunku Abdul Rahman (2016-2019)

CERTIFICATIONS
None

EXTRACURRICULAR
None

PROJECTS
None""",
    },
]


async def main():
    print("=" * 60)
    print("HireAgent.AI — Demo Pipeline")
    print("Jsoneer → Librarian → Critic → Secretary")
    print("=" * 60)

    print(f"\n[1/4] Running full screening pipeline...")
    print(f"  Job: {SAMPLE_EMPLOYER['title']}")
    print(f"  Applicants: {len(SAMPLE_RAW_TEXTS)} (raw resume text)")

    result = await orchestrator.run_screening_from_json(
        SAMPLE_EMPLOYER, SAMPLE_RAW_TEXTS
    )

    print(f"\n[2/4] Pipeline complete!")
    print(f"  Total applicants: {result.get('total_applicants', 0)}")

    # Show Jsoneer output for first applicant
    structured = result.get("structured_profiles", [])
    if structured:
        print(f"\n  --- Jsoneer output for Applicant 1 ---")
        print(json.dumps(structured[0], indent=2, ensure_ascii=False)[:500])

    # Display final results
    for r in result.get("results", []):
        print(f"\n  #{r.get('rank', '?')} Applicant {r.get('applicant_id', '?')} — Score: {r.get('evaluation_score', 0)}")
        print(f"  Summary: {r.get('summary', '')[:150]}...")
        print(f"  Strengths: {r.get('strengths', [])[:3]}")
        print(f"  Gaps: {r.get('gaps', [])[:3]}")
        for q in r.get("recommended_questions", [])[:2]:
            if isinstance(q, dict):
                print(f"    Q: {q.get('question', '')[:80]}...")
            else:
                print(f"    Q: {str(q)[:80]}...")

    # Save JSON output
    os.makedirs("output", exist_ok=True)
    json_path = os.path.join("output", "demo_results.json")
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2, ensure_ascii=False)

    print(f"\n[3/4] Results saved to: {json_path}")
    print("\n" + "=" * 60)
    print("Demo complete!")


if __name__ == "__main__":
    asyncio.run(main())
