# Data Analyst Intern 1 — Market Intelligence

## Presentation question
How does SkillSnap convert job-market records into a ranked target-role requirement model?

## Own these concepts
- `skillsnap_job_details` as the market-data source.
- `parsed_json` for skills, experience, projects and certifications.
- `skill_desc` for requirement descriptions.
- Role/location filtering.
- Normalization and de-duplication.
- `weight` = importance accumulated from job data.
- `mentions` = how frequently a requirement appears across matching jobs.
- Top requirements become the benchmark against which a candidate resume is tested.

## Demo flow
1. Pick a target role.
2. Show number of jobs scanned.
3. Show top skills/experience/projects/certifications.
4. Explain why high-weight/high-mention gaps should be prioritized.

Use `presentation.php` during the code walkthrough. The working source remains under `/working`.
