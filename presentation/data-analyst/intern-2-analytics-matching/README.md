# Data Analyst Intern 2 — Candidate Analytics & Job Matching

## Presentation question
How does SkillSnap convert requirement evidence into candidate readiness and job-match scores?

## Own these concepts
- Final status precedence: manual review → AI status → automatic status.
- Existing = full credit, Partial = half credit, Missing = zero credit.
- Not Required is excluded from the denominator.
- Weighted category match for skills, experience, projects and certifications.
- Gap prioritization using requirement weight and frequency.
- Job-match weighting: Skills 40%, Experience 30%, Projects 20%, Certifications 10%, renormalized when a category is absent.
- These scores are decision-support estimates, not hiring guarantees.

## Demo flow
1. Open one candidate.
2. Change a requirement from Missing to Existing.
3. Show the change in weighted match.
4. Compare the candidate against multiple job rows.
5. Explain why the same candidate can score differently for two companies/jobs.

Use `presentation.php` for the code walkthrough.
