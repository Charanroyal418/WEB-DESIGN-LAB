CREATE DATABASE IF NOT EXISTS simple_jobs
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE simple_jobs;

CREATE TABLE IF NOT EXISTS applications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(100)  NOT NULL,
    email        VARCHAR(150)  NOT NULL,
    phone        VARCHAR(20)   NOT NULL,
    position     VARCHAR(100)  NOT NULL,
    job_type     VARCHAR(30)   NOT NULL,
    experience   VARCHAR(20)   NOT NULL,
    skills       TEXT          NOT NULL,
    message      TEXT,
    status       VARCHAR(20)   DEFAULT 'Pending',
    applied_at   DATETIME      DEFAULT CURRENT_TIMESTAMP
);