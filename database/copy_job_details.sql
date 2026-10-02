-- Optional: make SkillSnap use an exact isolated copy of Candidate Compass job_details.
-- IMPORTANT: this copies job-market data only. It does NOT copy candidate/resume records.
-- Run after database/skillsnap_schema.sql in the same database that contains job_details.

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS skillsnap_job_details;
CREATE TABLE skillsnap_job_details LIKE job_details;
INSERT INTO skillsnap_job_details SELECT * FROM job_details;
SET FOREIGN_KEY_CHECKS=1;

-- Validation
SELECT COUNT(*) AS candidate_compass_jobs FROM job_details;
SELECT COUNT(*) AS skillsnap_jobs FROM skillsnap_job_details;
