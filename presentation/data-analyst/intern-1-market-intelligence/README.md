# Data Analyst Intern 1 — Market Intelligence

## Presentation question
How does SkillSnap convert thousands of job rows into a ranked target-role requirement model?

## Explain
1. `skillsnap_job_details` is the market-data source.
2. `parsed_json` supplies skills, experience, projects and certifications; `skill_desc` supplies descriptions.
3. `role_requirements()` filters jobs by target role/location, normalizes duplicate concepts, sums weights, counts mentions, then ranks requirements.
4. Output becomes the market benchmark against which the resume is tested.

## Demo
Show a target role, count jobs scanned, then show the top requirements and explain `weight` versus `mentions`.

## Files
- `presentation.php`: exact curated functions from the working app.
- `../../../../database/skillsnap_schema.sql`: table model.
