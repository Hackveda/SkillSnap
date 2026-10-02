# SkillSnap

SkillSnap is an isolated intern-testing replica of the Candidate Compass application. It turns a candidate resume and target role into evidence-based skill/experience/project/certification gaps, visual resume review, readiness analytics, preparation-plan recommendations, job matching, and job-specific ATS resume output.

## Why this repository exists

The intern environment must not interfere with Candidate Compass. SkillSnap therefore uses its own tables (`skillsnap_*`), its own upload folder, its own PHP session namespace, and its own local configuration file. The committed source contains **no production DB password, OpenAI API key, or admin password**.

## Repository layout

```text
SkillSnap/
├── index.php                         # Complete working SkillSnap application
├── config.local.example.php          # Copy to config.local.php on the server
├── skillsnap-share.svg               # Default social/share image
├── uploads/                           # Resume files (blocked from direct web access)
├── database/
│   ├── skillsnap_schema.sql           # Isolated SkillSnap tables
│   └── copy_job_details.sql           # Optional exact copy of Candidate Compass job_details
├── presentation/
│   ├── data-analyst/
│   │   ├── intern-1-market-intelligence/
│   │   └── intern-2-analytics-matching/
│   ├── generative-ai/
│   └── web-development/
└── docs/architecture/                 # Architecture map will be added after server validation
```

## Server deployment: `public_html/skillsnap`

1. Copy the repository contents to `public_html/skillsnap` (or deploy this repo there).
2. Copy `config.local.example.php` to `config.local.php` and set the SkillSnap DB credentials/passwords.
3. Run `database/skillsnap_schema.sql`.
4. If you want the same job dataset as Candidate Compass without sharing the table, run `database/copy_job_details.sql` once.
5. Ensure `public_html/skillsnap/uploads` is writable by PHP (typically 0750/0755 depending on hosting).
6. Open `/skillsnap/` and sign in with the SkillSnap admin password.
7. For AI analysis, add a **separate/restricted** OpenAI key to `config.local.php` or `SKILLSNAP_OPENAI_API_KEY`.

All browser/API URLs in the application are relative (`?action=...`), so deployment under `/skillsnap/` does not require hard-coded path changes.

## Intern ownership

| Intern | Track | Presentation ownership |
|---|---|---|
| Data Analyst 1 | Market intelligence | Job data → role requirement aggregation, weights, mentions and descriptions |
| Data Analyst 2 | Candidate analytics | Evidence scoring, descriptive/diagnostic/predictive/prescriptive analytics and job matching |
| Generative AI | AI layer | Structured OpenAI responses, evidence classification, visual resume review and ATS resume generation |
| Web Developer | Application layer | Authentication, CRUD, uploads, AJAX actions, public sharing, UI and security boundaries |

The `presentation/` folders contain curated code extracts from the working app so each intern can explain a focused slice without editing the production copy.

## Core data flow

```text
skillsnap_job_details
        ↓
Target-role requirement aggregation
        ↓
Resume extraction + keyword/AI evidence classification
        ↓
Existing / Partial / Missing / Not Required
        ↓
Readiness analytics + visual resume analysis
        ↓
Preparation prescription + company/job matching
        ↓
Shared candidate review + job-specific ATS resume
```

## Important isolation rule

Do not change SkillSnap SQL back to `candidates`, `candidate_requirements`, `candidate_activity`, or `job_details`. Those names belong to the original environment. SkillSnap intentionally writes only to `skillsnap_*` tables.

## Architecture map

A final visual architecture map is intentionally deferred until the server build is validated. Add it under `docs/architecture/` once the live SkillSnap flow has been tested end-to-end.
