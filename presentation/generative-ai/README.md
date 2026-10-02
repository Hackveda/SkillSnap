# Generative AI Intern — AI Evidence & Resume Intelligence

## Presentation question
Where is Generative AI used, and how does SkillSnap constrain it?

## Explain
1. OpenAI Responses API is called with strict JSON Schemas.
2. Requirement analysis classifies each requirement as Existing / Partial / Missing with confidence, evidence and reason.
3. The visual-review flow renders resume pages and asks the model to evaluate scanability, hierarchy, acceptance/rejection signals and reviewer personas.
4. ATS generation rewrites/reorders supported evidence for a selected job.
5. Human `review_status` overrides AI and keyword output.

## Safety/quality point
The committed code never contains the API key. Keep AI outputs evidence-grounded and do not treat heuristic percentages as guarantees.
