#!/usr/bin/env python3
"""
CATS - Portfolio Scraper
Enriches applicant JSON with GitHub and LinkedIn data.

Usage: python3 scraper.py '<applicant_json>' '<portfolio_json>'
  applicant_json : JSON object from the AI agent
  portfolio_json : JSON array from applicants.portfolio column
                   e.g. [{"platform":"GitHub","url":"..."},...]

Output: Enriched applicant_json with github and linkedin_titles appended.
"""

import sys
import json
import re
import urllib.request
import urllib.error
import time

# ============================================================
# GITHUB SCRAPER
# ============================================================

def get_github_repos(username):
    if not username:
        return []
    
    url = f"https://api.github.com/users/{username}/repos?per_page=10&sort=updated"
    headers = {
        'User-Agent': 'CATS-ATS/1.0',
        'Accept': 'application/vnd.github.v3+json'
    }
    
    try:
        req = urllib.request.Request(url, headers=headers)
        with urllib.request.urlopen(req, timeout=8) as response:
            if response.status == 200:
                return json.loads(response.read().decode())
    except Exception as e:
        sys.stderr.write(f"GitHub fetch error: {e}\n")
    
    return []


def get_repo_readme(username, repo_name):
    url = f"https://api.github.com/repos/{username}/{repo_name}/readme"
    headers = {
        'User-Agent': 'CATS-ATS/1.0',
        'Accept': 'application/vnd.github.v3+json'
    }
    
    try:
        req = urllib.request.Request(url, headers=headers)
        with urllib.request.urlopen(req, timeout=6) as response:
            if response.status == 200:
                data = json.loads(response.read().decode())
                import base64
                content = base64.b64decode(data.get('content', '')).decode('utf-8', errors='ignore')
                return content[:200]
    except:
        pass
    
    return ""


def scrape_github(github_handle):
    if not github_handle:
        return []
    
    repos = get_github_repos(github_handle)
    github_data = []
    
    for repo in repos:
        if repo.get('fork'):
            continue
        
        name = repo.get('name', '')
        if not name:
            continue
        
        readme = get_repo_readme(github_handle, name)
        
        github_data.append({
            "title": name,
            "readme": readme
        })
        
        time.sleep(0.3)
    
    return github_data


# ============================================================
# LINKEDIN (MOCK)
# ============================================================

def scrape_linkedin_mock(linkedin_url):
    if not linkedin_url:
        return []
    
    return [
        "Shared insights on cybersecurity trends",
        "Completed new certification milestone",
        "Presented at internal tech talk"
    ]


# ============================================================
# OTHER PORTFOLIOS
# ============================================================

def scrape_other_titles(portfolio):
    titles = []
    
    for item in portfolio:
        if item.get('platform') in ('LinkedIn', 'GitHub'):
            continue
        
        url = item.get('url', '')
        if not url:
            continue
        
        try:
            req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
            with urllib.request.urlopen(req, timeout=5) as response:
                html = response.read().decode('utf-8', errors='ignore')
                match = re.search(r'<title[^>]*>(.*?)</title>', html, re.IGNORECASE | re.DOTALL)
                if match:
                    title = re.sub(r'\s+', ' ', match.group(1)).strip()
                    if title:
                        titles.append(title[:80])
        except:
            pass
    
    return titles


# ============================================================
# MAIN
# ============================================================

def main():
    if len(sys.argv) < 3:
        sys.stderr.write("Usage: scraper.py '<applicant_json>' '<portfolio_json>'\n")
        sys.exit(1)
    
    # Parse applicant_json from agent
    try:
        applicant_data = json.loads(sys.argv[1])
    except json.JSONDecodeError as e:
        sys.stderr.write(f"Invalid applicant_json: {e}\n")
        sys.exit(1)

    # Parse portfolio links from DB
    try:
        portfolio = json.loads(sys.argv[2])
        if not isinstance(portfolio, list):
            portfolio = []
    except json.JSONDecodeError:
        sys.stderr.write("Invalid portfolio_json, defaulting to empty.\n")
        portfolio = []

    # Extract GitHub handle from portfolio
    github_handle = ''
    for item in portfolio:
        if item.get('platform') == 'GitHub':
            match = re.search(r'github\.com/([A-Za-z0-9\-_]+)', item.get('url', ''))
            if match:
                github_handle = match.group(1)
                break

    # Extract LinkedIn URL from portfolio
    linkedin_url = ''
    for item in portfolio:
        if item.get('platform') == 'LinkedIn':
            linkedin_url = item.get('url', '')
            break

    # Scrape and append directly onto applicant_data
    applicant_data['github']          = scrape_github(github_handle)
    applicant_data['linkedin_titles'] = scrape_linkedin_mock(linkedin_url)
    applicant_data['others']          = scrape_other_titles(portfolio)

    print(json.dumps(applicant_data))


if __name__ == '__main__':
    main()