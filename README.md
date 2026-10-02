# SkillSnap

SkillSnap is an intern presentation project focused on understanding how candidate data, resume evidence, market requirements, analytics, AI-assisted evaluation, job matching, and web application components can be combined into a career-readiness platform.

The project is derived conceptually from Candidate Compass, but SkillSnap is maintained as an isolated learning environment with its own application namespace, data tables, upload area, configuration boundary, and presentation modules.

## Project Objective

The central problem addressed by SkillSnap is:

> How can a candidate's resume be compared with real job-market requirements to identify evidence, gaps, readiness, and suitable opportunities?

The system connects four technical perspectives:

1. **Data Analysis** — understanding job-market demand and candidate gaps.
2. **Generative AI** — evaluating evidence and producing structured insights.
3. **Web Development** — building the application workflow and user experience.
4. **Database Design** — maintaining candidate, requirement, review, activity, and matching data.

## Project Concept

SkillSnap follows an evidence-oriented career analysis flow:

```text
Job Market Data
      ↓
Target Role Requirements
      ↓
Candidate Resume & Profile
      ↓
Evidence Classification
      ↓
Existing / Partial / Missing / Not Required
      ↓
Candidate Gap & Readiness Analytics
      ↓
Job / Company Matching
      ↓
Candidate Review & Career Insights
```

This structure helps separate three different questions:

- **What does the market require?**
- **What evidence does the candidate currently demonstrate?**
- **Where is the gap between the two?**

## Major Functional Areas

### Candidate Profile Management

The application maintains candidate information such as current role, target role, target location, compensation details, notice period, stage, notes, and resume metadata.

### Resume Processing

SkillSnap supports resume text extraction from PDF, DOCX, and TXT files. Extracted resume content becomes the evidence source for subsequent analysis.

### Market Requirement Analysis

Job data is analysed to identify recurring requirements for a selected target role. Requirements are grouped into:

- Skills
- Experience
- Projects
- Certifications

Frequency and weight information help represent relative market demand.

### Evidence Classification

Candidate evidence is evaluated against role requirements and represented using four states:

- **Existing** — credible evidence is present.
- **Partial** — related or incomplete evidence is present.
- **Missing** — sufficient evidence is not present.
- **Not Required** — the requirement is excluded through review.

The application also supports human review overrides so automated analysis is not treated as the final authority.

### Candidate Analytics

The analytics layer converts requirement-level evidence into higher-level information such as:

- Category-level match
- Weighted match
- Evidence completeness
- Important missing requirements
- Gap prioritisation
- Readiness indicators

The purpose of these metrics is decision support rather than guaranteed hiring prediction.

### Company and Job Matching

Candidate evidence is compared with individual job requirements across skills, experience, projects, and certifications. Matching results provide a more specific view than target-role analysis because they compare the candidate with individual opportunities.

### Generative AI Layer

The Generative AI component demonstrates structured AI-assisted analysis for areas such as:

- Resume evidence classification
- Requirement-level reasoning
- Resume presentation review
- Reviewer-perspective analysis
- Job-specific resume adaptation

Structured outputs are used so AI responses can be stored and interpreted consistently by the application.

### Candidate Review Experience

A shareable candidate profile presents evidence, gaps, analytics, and matching information in a review-friendly format. Human corrections and additional evidence can become part of the final candidate assessment.

## Intern Presentation Ownership

The project is divided according to the four internship focus areas so that each intern can explain a meaningful subsystem while still understanding the complete application.

| Intern | Track | Presentation Focus |
|---|---|---|
| Data Analyst 1 | Market Intelligence | Job data, target-role aggregation, requirement frequency, weights, mentions, and market-demand interpretation |
| Data Analyst 2 | Candidate Analytics | Evidence scoring, category match, gap prioritisation, readiness analytics, and job-matching logic |
| Generative AI Intern | Generative AI | Structured AI outputs, requirement classification, evidence reasoning, visual resume review, and ATS-oriented resume generation |
| Web Development Intern | Web Development | PHP application flow, authentication, CRUD, resume upload, AJAX interaction, candidate sharing, and UI integration |

The presentation folders contain focused code extracts corresponding to these responsibilities, while the working application represents the integrated system.

## Repository Structure

```text
SkillSnap/
├── index.php
├── working/
│   └── skillsnap.php
├── database/
│   ├── skillsnap_schema.sql
│   └── copy_job_details.sql
├── presentation/
│   ├── data-analyst/
│   │   ├── intern-1-market-intelligence/
│   │   └── intern-2-analytics-matching/
│   ├── generative-ai/
│   └── web-development/
├── docs/
│   └── architecture/
├── uploads/
├── config.local.example.php
└── DEPLOYMENT.md
```

## Database Design

SkillSnap uses a separate `skillsnap_*` table namespace so the project remains independent from Candidate Compass.

The main logical data areas are:

- Candidate profiles
- Candidate requirements
- Candidate review activity
- Company-match cache
- Company-match jobs
- Match execution history
- Match processing queue
- Job-market source data

This separation also makes the database design easier to explain during the internship presentation because each table belongs to a clearly defined application responsibility.

## Separation from Candidate Compass

SkillSnap is intentionally isolated from the original Candidate Compass environment. Its candidate data, requirement analysis, activity history, matching history, job data, PHP session namespace, uploads, and local configuration are independent.

This design allows SkillSnap to function as a learning and presentation application without changing Candidate Compass records.

## Learning Outcomes Demonstrated

Through SkillSnap, the interns are expected to demonstrate understanding of:

- Translating a business problem into a data model
- Converting raw job data into structured role requirements
- Comparing candidate evidence with market expectations
- Designing interpretable scoring and gap metrics
- Applying Generative AI with structured outputs and evidence constraints
- Integrating backend logic with a browser-based interface
- Maintaining separation between automated analysis and human review
- Connecting analytics, AI, database, and application development into one end-to-end system

## Mentor Evaluation Perspective

SkillSnap can be evaluated as an integrated project rather than four unrelated assignments. Each intern owns a different layer, but the value of the project comes from the interaction between those layers.

A useful presentation discussion can therefore move from:

**market data → candidate evidence → analytics → AI reasoning → application experience**

while allowing each intern to explain the technical decisions within their assigned area.

## Architecture Documentation

The repository includes a dedicated architecture section under `docs/architecture/`. The final architecture map will represent the validated end-to-end SkillSnap flow after application testing is complete.
