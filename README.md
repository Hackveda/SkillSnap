# SkillSnap

SkillSnap is an isolated intern-testing application derived from Candidate Compass. Its purpose is to let interns test candidate CRUD, resume extraction, market-driven role requirements, gap analysis, human review, public candidate sharing and job matching without writing to Candidate Compass tables.

## Isolation from Candidate Compass

SkillSnap uses:

- its own `skillsnap_*` MySQL tables;
- its own `skillsnap_admin` PHP session namespace;
- its own `/skillsnap/uploads` directory;
- its own `config.local.php`;
- no committed production DB password, OpenAI API key or admin password.

The original uploaded Candidate Compass source used live fallback credentials, so the SkillSnap replica intentionally removes those fallbacks. Use `config.local.example.php` to configure the test environment.

## Repository layout

```text
SkillSnap/
├── index.php                         # deployment entry point
├── working/
│   └── skillsnap.php                 # readable working intern-test application
├── config.local.example.php          # copy to config.local.php on server
├── .htaccess                         # Apache hardening
├── uploads/
│   └── .htaccess                     # blocks direct resume access
├── database/
│   ├── skillsnap_schema.sql          # isolated SkillSnap schema
│   └── copy_job_details.sql          # optional exact copy of job market data
├── presentation/
│   ├── data-analyst/
│   │   ├── intern-1-market-intelligence/
│   │   └── intern-2-analytics-matching/
│   ├── generative-ai/
│   └── web-development/
├── docs/architecture/                # architecture diagram goes here later
└── DEPLOYMENT.md
```

## Deployment to `public_html/skillsnap`

1. Place/clone this repository in `public_html/skillsnap`.
2. Copy `config.local.example.php` → `config.local.php`.
3. Set the SkillSnap DB credentials and a new SkillSnap admin password.
4. Run `database/skillsnap_schema.sql`.
5. If you need the same job-market dataset as Candidate Compass, run `database/copy_job_details.sql`. It creates an isolated `skillsnap_job_details` copy instead of sharing the original table.
6. Make `uploads/` writable by PHP.
7. Open `https://YOUR-DOMAIN/skillsnap/`.

All application calls use relative `?action=...` URLs, so `/skillsnap/` does not need hard-coded route changes.

## Intern ownership

| Intern | Track | Presentation ownership |
|---|---|---|
| Data Analyst 1 | Market intelligence | Job data → target-role requirement aggregation, weights, mentions and descriptions |
| Data Analyst 2 | Candidate analytics | Evidence scoring, gap prioritization and company/job matching |
| Generative AI | AI layer | Structured AI evidence analysis, visual resume-review design and ATS-generation design |
| Web Developer | Application layer | Authentication, CRUD, uploads, AJAX actions, public sharing and security boundaries |

Each presentation folder has a focused README plus a code extract. Interns should present those extracts, while `/working/skillsnap.php` remains the common working application.

## Core data flow

```text
skillsnap_job_details
        ↓
Target-role requirement aggregation
        ↓
Resume extraction and evidence matching
        ↓
Existing / Partial / Missing / Not Required
        ↓
Candidate gap/readiness view
        ↓
Human review overrides
        ↓
Company/job matching + shared candidate profile
```

## Full replica versus presentation code

The working training application in this repository is intentionally readable and isolated. The full SkillSnap export prepared from the attached Candidate Compass source retains the richer Candidate Compass flows, including the AI/visual/plan/ATS functions, with SkillSnap branding and isolated table names. The presentation folders are smaller teaching extracts and must not replace the working application.

## Architecture map

The final architecture diagram will be added under `docs/architecture/` after the server deployment is validated end-to-end.
