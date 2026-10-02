-- SkillSnap isolated schema
-- Designed for an intern test environment that must not write to Candidate Compass tables.
-- Run in the same MySQL database or in a separate test database.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS skillsnap_candidates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(80) NULL,
  current_role VARCHAR(255) NULL,
  target_role VARCHAR(255) NULL,
  target_location VARCHAR(255) NULL,
  current_ctc VARCHAR(100) NULL,
  expected_ctc VARCHAR(100) NULL,
  notice_period VARCHAR(100) NULL,
  stage VARCHAR(80) NOT NULL DEFAULT 'Lead',
  status VARCHAR(40) NOT NULL DEFAULT 'Active',
  notes MEDIUMTEXT NULL,
  resume_original_name VARCHAR(512) NULL,
  resume_stored_name VARCHAR(512) NULL,
  resume_mime VARCHAR(120) NULL,
  resume_text LONGTEXT NULL,
  resume_analysis_json LONGTEXT NULL,
  resume_analysis_provider VARCHAR(80) NULL,
  resume_analysis_model VARCHAR(120) NULL,
  resume_analysis_at DATETIME NULL,
  resume_analysis_error MEDIUMTEXT NULL,
  visual_analysis_json LONGTEXT NULL,
  visual_analysis_model VARCHAR(120) NULL,
  visual_analysis_at DATETIME NULL,
  visual_analysis_error MEDIUMTEXT NULL,
  requirements_json LONGTEXT NULL,
  analysis_version INT NOT NULL DEFAULT 0,
  share_token VARCHAR(96) NULL,
  share_enabled TINYINT(1) NOT NULL DEFAULT 0,
  share_created_at DATETIME NULL,
  candidate_review_note MEDIUMTEXT NULL,
  candidate_reviewed_at DATETIME NULL,
  plan_recommendations_json LONGTEXT NULL,
  plan_recommendations_at DATETIME NULL,
  plan_display_enabled TINYINT(1) NOT NULL DEFAULT 0,
  company_match_display_enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_skillsnap_share_token(share_token),
  INDEX idx_ss_name(full_name), INDEX idx_ss_email(email), INDEX idx_ss_target(target_role), INDEX idx_ss_stage(stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skillsnap_candidate_requirements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  candidate_id BIGINT UNSIGNED NOT NULL,
  group_name ENUM('skills','experience','projects','certificates') NOT NULL,
  term VARCHAR(700) NOT NULL,
  term_norm VARCHAR(700) NOT NULL,
  description MEDIUMTEXT NULL,
  weight DECIMAL(12,4) NOT NULL DEFAULT 1,
  mentions INT NOT NULL DEFAULT 1,
  auto_status ENUM('existing','missing') NOT NULL DEFAULT 'missing',
  ai_status ENUM('existing','partial','missing') NULL,
  ai_confidence DECIMAL(5,4) NULL,
  ai_reason MEDIUMTEXT NULL,
  ai_evidence MEDIUMTEXT NULL,
  ai_model VARCHAR(120) NULL,
  ai_analyzed_at DATETIME NULL,
  review_status ENUM('existing','partial','missing','not_required') NULL,
  evidence MEDIUMTEXT NULL,
  reviewer_note MEDIUMTEXT NULL,
  reviewed_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_ss_candidate_group_term(candidate_id,group_name,term_norm),
  INDEX idx_ss_req_candidate(candidate_id), INDEX idx_ss_req_group(group_name),
  CONSTRAINT fk_ss_candidate_req FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skillsnap_company_match_cache (
  candidate_id BIGINT UNSIGNED NOT NULL,
  filter_hash CHAR(64) NOT NULL,
  filters_json TEXT NOT NULL,
  source_version VARCHAR(120) NOT NULL DEFAULT '',
  status ENUM('pending','running','ready','failed') NOT NULL DEFAULT 'pending',
  result_json LONGTEXT NULL,
  error_text MEDIUMTEXT NULL,
  generated_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY(candidate_id,filter_hash),
  INDEX idx_ss_match_cache_status(status,updated_at),
  CONSTRAINT fk_ss_match_cache_candidate FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skillsnap_company_match_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  candidate_id BIGINT UNSIGNED NOT NULL,
  filter_hash CHAR(64) NOT NULL,
  filters_json TEXT NOT NULL,
  source_version VARCHAR(120) NOT NULL DEFAULT '',
  status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
  priority INT NOT NULL DEFAULT 5,
  attempts INT NOT NULL DEFAULT 0,
  lock_token VARCHAR(80) NULL,
  locked_at DATETIME NULL,
  error_text MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_ss_match_job(candidate_id,filter_hash),
  INDEX idx_ss_match_job_claim(status,priority,id),
  CONSTRAINT fk_ss_match_job_candidate FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skillsnap_company_match_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  candidate_id BIGINT UNSIGNED NOT NULL,
  filter_hash CHAR(64) NOT NULL,
  filters_json TEXT NOT NULL,
  source_version VARCHAR(120) NOT NULL DEFAULT '',
  status ENUM('queued','running','ready','failed','superseded') NOT NULL DEFAULT 'queued',
  requested_by ENUM('automatic','admin','shared','cron') NOT NULL DEFAULT 'automatic',
  result_json LONGTEXT NULL,
  result_count INT NOT NULL DEFAULT 0,
  error_text MEDIUMTEXT NULL,
  requested_at DATETIME NOT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  INDEX idx_ss_match_run_candidate(candidate_id,id),
  INDEX idx_ss_match_run_status(status,requested_at),
  CONSTRAINT fk_ss_match_run_candidate FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skillsnap_company_match_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  candidate_id BIGINT UNSIGNED NOT NULL,
  priority INT NOT NULL DEFAULT 5,
  status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  lock_token VARCHAR(80) NULL,
  locked_at DATETIME NULL,
  available_at DATETIME NOT NULL,
  error_text MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_ss_match_queue_run(run_id),
  INDEX idx_ss_match_queue_claim(status,available_at,priority,id),
  CONSTRAINT fk_ss_match_queue_run FOREIGN KEY(run_id) REFERENCES skillsnap_company_match_runs(id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_match_queue_candidate FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skillsnap_activity (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  candidate_id BIGINT UNSIGNED NOT NULL,
  action_name VARCHAR(120) NOT NULL,
  detail MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_ss_candidate_time(candidate_id,created_at),
  CONSTRAINT fk_ss_activity_candidate FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skillsnap_job_details (
  ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  Title VARCHAR(512) NULL,
  Company VARCHAR(255) NULL,
  Location VARCHAR(255) NULL,
  parsed_json LONGTEXT NULL,
  skill_desc LONGTEXT NULL,
  analysed TINYINT(1) NOT NULL DEFAULT 0,
  apply_url TEXT NULL,
  created_at DATETIME NULL,
  INDEX idx_ss_job_title(Title), INDEX idx_ss_job_company(Company), INDEX idx_ss_job_analysed(analysed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
