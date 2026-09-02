-- ================================================================
--  ResumeRank — Resume Shortlist Sorting System
--  MySQL 8.0+ / MariaDB 10.6+  |  schema.sql
-- ================================================================

CREATE DATABASE IF NOT EXISTS resumerank
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE resumerank;

-- ── JOBS ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jobs (
  id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(200)    NOT NULL,
  department       VARCHAR(100)    NOT NULL,
  location         VARCHAR(150),
  description      TEXT,
  required_skills  JSON            COMMENT 'Array of required skill strings',
  min_experience   DECIMAL(4,1)    DEFAULT 0,
  status           ENUM('open','closed') DEFAULT 'open',
  created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
);

-- ── CANDIDATES ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS candidates (
  id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  job_id           INT UNSIGNED    NOT NULL,
  -- Personal
  full_name        VARCHAR(200)    NOT NULL,
  email            VARCHAR(255)    NOT NULL,
  phone            VARCHAR(40),
  location         VARCHAR(150),
  linkedin_url     VARCHAR(500),
  -- Professional
  current_role     VARCHAR(200),
  experience_yrs   DECIMAL(4,1)    DEFAULT 0,
  education        VARCHAR(255),
  skills           JSON            COMMENT 'Array of candidate skill strings',
  -- Resume file
  resume_file      VARCHAR(400),
  resume_size_kb   SMALLINT UNSIGNED,
  -- Auto-computed scores (0–100)
  score_skills     TINYINT UNSIGNED DEFAULT 0,
  score_experience TINYINT UNSIGNED DEFAULT 0,
  score_education  TINYINT UNSIGNED DEFAULT 0,
  score_overall    TINYINT UNSIGNED DEFAULT 0,
  -- Pipeline
  status           ENUM('new','reviewing','shortlisted','interviewing','offered','hired','rejected')
                   DEFAULT 'new',
  notes            TEXT,
  applied_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  INDEX idx_job       (job_id),
  INDEX idx_status    (status),
  INDEX idx_score     (score_overall DESC),
  INDEX idx_applied   (applied_at DESC)
);

-- ── ACTIVITY LOG ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id           INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT UNSIGNED    NOT NULL,
  action       VARCHAR(80)     NOT NULL,
  old_value    VARCHAR(200),
  new_value    VARCHAR(200),
  created_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  INDEX idx_cand (candidate_id)
);

-- ── SEED: Sample job positions ───────────────────────────────
INSERT INTO jobs (title, department, location, required_skills, min_experience, status) VALUES
(
  'Senior Full Stack Developer', 'Engineering', 'Chennai / Remote',
  JSON_ARRAY('PHP','MySQL','React','JavaScript','Docker','REST API','Git'),
  4, 'open'
),
(
  'Data Scientist', 'Analytics', 'Bangalore / Hybrid',
  JSON_ARRAY('Python','Machine Learning','SQL','TensorFlow','Pandas','Statistics'),
  3, 'open'
),
(
  'UX / UI Designer', 'Product', 'Remote',
  JSON_ARRAY('Figma','Adobe XD','User Research','Prototyping','CSS','Design Systems'),
  2, 'open'
),
(
  'DevOps Engineer', 'Infrastructure', 'Hyderabad',
  JSON_ARRAY('Docker','Kubernetes','AWS','CI/CD','Linux','Terraform','Ansible'),
  4, 'open'
);