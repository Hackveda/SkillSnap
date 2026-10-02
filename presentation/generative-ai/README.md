# Generative AI Intern — Evidence & Resume Intelligence

## Presentation question
Where is Generative AI used in SkillSnap, and how is it constrained?

## Own these concepts
- OpenAI Responses API with structured JSON Schema output.
- Evidence-grounded requirement classification: Existing / Partial / Missing.
- Confidence, evidence and reason fields.
- Visual resume review from rendered resume pages.
- Reviewer personas: recruiter, screener, hiring manager, technical interviewer and senior leader.
- ATS-oriented resume rewriting for a selected job.
- Human review overrides AI output.

## Important quality rule
AI must not invent employers, dates, degrees, certifications, years of experience or delivered results. Unsupported target-job requirements should remain gaps/development priorities.

## Demo flow
1. Show the schema expected from the model.
2. Show one requirement and its resume evidence.
3. Explain why structured output is easier to validate/store than free-form text.
4. Explain visual resume analysis separately from factual resume evidence.
5. Explain how a human review becomes the final authority.

Use `presentation.php` for the code walkthrough. The full deployable export contains the richer AI/vision/ATS implementation from Candidate Compass, renamed and isolated for SkillSnap.
