-- ================================================================
-- EngineersHub — Job Portal Database
-- ================================================================

CREATE DATABASE IF NOT EXISTS engineershub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE engineershub;

-- ================================================================
-- Companies
-- ================================================================
CREATE TABLE IF NOT EXISTS companies (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(200)  NOT NULL,
    slug         VARCHAR(200)  UNIQUE NOT NULL,
    logo_initial VARCHAR(5)    DEFAULT '',
    logo_color   VARCHAR(7)    DEFAULT '#2563EB',
    website      VARCHAR(300),
    industry     VARCHAR(100),
    size_range   ENUM('1-10','11-50','51-200','201-500','501-1000','1000+') DEFAULT '51-200',
    description  TEXT,
    hq_location  VARCHAR(200),
    founded_year INT,
    is_verified  TINYINT(1)    DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ================================================================
-- Job Listings
-- ================================================================
CREATE TABLE IF NOT EXISTS jobs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    company_id      INT NOT NULL,
    title           VARCHAR(300) NOT NULL,
    slug            VARCHAR(300) UNIQUE NOT NULL,
    department      VARCHAR(100),
    location        VARCHAR(200),
    location_type   ENUM('on-site','remote','hybrid') DEFAULT 'hybrid',
    job_type        ENUM('full-time','part-time','contract','internship','freelance') DEFAULT 'full-time',
    experience_min  INT DEFAULT 0,
    experience_max  INT DEFAULT 5,
    salary_min      INT,
    salary_max      INT,
    salary_currency CHAR(3) DEFAULT 'USD',
    description     TEXT,
    responsibilities TEXT,
    requirements    TEXT,
    nice_to_have    TEXT,
    benefits        TEXT,
    skills_required JSON,
    status          ENUM('active','closed','draft','paused') DEFAULT 'active',
    is_featured     TINYINT(1) DEFAULT 0,
    is_urgent       TINYINT(1) DEFAULT 0,
    applications    INT DEFAULT 0,
    views           INT DEFAULT 0,
    posted_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at      TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- ================================================================
-- Job Seekers
-- ================================================================
CREATE TABLE IF NOT EXISTS seekers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(200) NOT NULL,
    email        VARCHAR(255) UNIQUE NOT NULL,
    phone        VARCHAR(30),
    location     VARCHAR(200),
    headline     VARCHAR(300),
    summary      TEXT,
    resume_url   VARCHAR(500),
    linkedin_url VARCHAR(500),
    github_url   VARCHAR(500),
    portfolio_url VARCHAR(500),
    years_exp    INT DEFAULT 0,
    skills       JSON,
    education    JSON,
    is_open      TINYINT(1) DEFAULT 1,
    password_hash VARCHAR(255) NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ================================================================
-- Employers (company HR/recruiter accounts)
-- ================================================================
CREATE TABLE IF NOT EXISTS employers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    company_id    INT NOT NULL,
    full_name     VARCHAR(200) NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    role          VARCHAR(100) DEFAULT 'Recruiter',
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- ================================================================
-- Applications
-- ================================================================
CREATE TABLE IF NOT EXISTS applications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    job_id       INT NOT NULL,
    seeker_id    INT NOT NULL,
    cover_letter TEXT,
    resume_url   VARCHAR(500),
    status       ENUM('submitted','reviewing','shortlisted','interview','offered','rejected','withdrawn') DEFAULT 'submitted',
    notes        TEXT,
    applied_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id)    REFERENCES jobs(id)    ON DELETE CASCADE,
    FOREIGN KEY (seeker_id) REFERENCES seekers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (job_id, seeker_id)
);

-- ================================================================
-- Saved Jobs
-- ================================================================
CREATE TABLE IF NOT EXISTS saved_jobs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    seeker_id  INT NOT NULL,
    job_id     INT NOT NULL,
    saved_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seeker_id) REFERENCES seekers(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)    REFERENCES jobs(id)    ON DELETE CASCADE,
    UNIQUE KEY unique_save (seeker_id, job_id)
);

-- ================================================================
-- Skills Master List
-- ================================================================
CREATE TABLE IF NOT EXISTS skills (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) UNIQUE NOT NULL,
    category   VARCHAR(100),
    job_count  INT DEFAULT 0
);

-- ================================================================
-- Sample Data
-- ================================================================
INSERT INTO companies (name, slug, logo_initial, logo_color, industry, size_range, description, hq_location, founded_year, is_verified) VALUES
('Google','google','G','#4285F4','Technology','1000+','Building products that organize the world''s information.','Mountain View, CA',1998,1),
('Tesla','tesla','T','#CC0000','Automotive & Energy','1000+','Accelerating the world''s transition to sustainable energy.','Austin, TX',2003,1),
('SpaceX','spacex','S','#005288','Aerospace','1000+','Manufacturing advanced rockets and spacecraft.','Hawthorne, CA',2002,1),
('Stripe','stripe','St','#635BFF','Fintech','1000+','Building economic infrastructure for the internet.','San Francisco, CA',2010,1),
('Airbnb','airbnb','A','#FF5A5F','Travel Technology','1000+','Creating a world where anyone can belong anywhere.','San Francisco, CA',2008,1),
('Palantir','palantir','P','#1B1B1B','Data Analytics','501-1000','Building the operating system for the modern enterprise.','Denver, CO',2003,1),
('Databricks','databricks','D','#FF3621','Data & AI','501-1000','Pioneering the Lakehouse paradigm in data, analytics and AI.','San Francisco, CA',2013,1),
('Figma','figma','F','#F24E1E','Design Technology','201-500','Building tools for product teams to build better products together.','San Francisco, CA',2012,1),
('Notion','notion','N','#000000','Productivity Software','201-500','The connected workspace where better, faster work happens.','San Francisco, CA',2016,1),
('Vercel','vercel','V','#000000','Cloud Infrastructure','201-500','Enabling frontend teams to do their best work.','San Francisco, CA',2015,1);

INSERT INTO jobs (company_id, title, slug, department, location, location_type, job_type, experience_min, experience_max, salary_min, salary_max, description, requirements, skills_required, status, is_featured, is_urgent, posted_at) VALUES
(1,'Senior Software Engineer — Infrastructure','google-senior-swe-infra','Engineering','Mountain View, CA','hybrid','full-time',5,10,180000,260000,'Join Google''s Infrastructure team to build the backbone of products serving billions of users. You''ll design and implement large-scale distributed systems that power Google Search, Gmail, and YouTube.','5+ years of software engineering experience. Strong CS fundamentals. Experience with distributed systems at scale.','["Go","C++","Kubernetes","gRPC","Distributed Systems","Linux"]','active',1,0,NOW() - INTERVAL 2 DAY),
(2,'Embedded Systems Engineer — Autopilot','tesla-embedded-autopilot','Autopilot','Palo Alto, CA','on-site','full-time',3,7,150000,210000,'Work on Tesla''s Autopilot system — real-time embedded software running on custom silicon in every car we ship. You''ll write firmware that operates at the boundary of software and physics.','3+ years embedded C/C++. Real-time OS experience. Strong debugging skills with oscilloscopes and logic analyzers.','["C","C++","RTOS","CAN Bus","Python","ARM","AUTOSAR"]','active',1,1,NOW() - INTERVAL 1 DAY),
(3,'Avionics Software Engineer','spacex-avionics-swe','Flight Software','Hawthorne, CA','on-site','full-time',4,8,160000,220000,'Develop flight software for Falcon 9, Starship, and Dragon spacecraft. Your code will run on hardware flying to orbit and beyond. You''ll own subsystems end-to-end from design to launch.','4+ years in safety-critical or embedded software. Experience with fault-tolerant systems. BS/MS in CS or Engineering.','["C++","Python","Linux","Real-Time Systems","GNC","Fault Tolerance"]','active',1,1,NOW() - INTERVAL 3 DAY),
(4,'Backend Engineer — Payments Infrastructure','stripe-backend-payments','Payments','San Francisco, CA','hybrid','full-time',3,7,170000,240000,'Build the infrastructure that processes hundreds of billions of dollars in payments annually. You''ll work on the core of Stripe''s API — optimizing for correctness, reliability, and latency at global scale.','Strong background in backend systems. Experience with high-availability services. Proficiency in Ruby or Go.','["Ruby","Go","PostgreSQL","Redis","Kafka","AWS","API Design"]','active',1,0,NOW() - INTERVAL 4 DAY),
(5,'Staff Frontend Engineer','airbnb-staff-frontend','Product Engineering','Remote','remote','full-time',7,12,190000,270000,'Lead frontend architecture for Airbnb''s host tools — used by 4M+ hosts globally. You''ll define component systems, mentor engineers, and raise the bar for web performance and accessibility.','7+ years frontend experience. Deep React expertise. Experience leading technical direction on cross-functional teams.','["React","TypeScript","GraphQL","Node.js","CSS","Web Performance","A11y"]','active',1,0,NOW() - INTERVAL 5 DAY),
(6,'Data Engineer — Platform','palantir-data-engineer','Data Platform','Denver, CO','hybrid','full-time',2,6,130000,190000,'Build the data pipelines and tooling that power Palantir''s Foundry platform. You''ll work with petabyte-scale datasets and design systems that make data accessible to non-technical users.','2+ years in data engineering. Experience with Spark or similar big data frameworks. SQL mastery.','["Python","Spark","SQL","Airflow","dbt","AWS","Snowflake"]','active',0,0,NOW() - INTERVAL 6 DAY),
(7,'Machine Learning Engineer — LLM Infra','databricks-ml-llm','AI Platform','San Francisco, CA','hybrid','full-time',4,8,180000,260000,'Join Databricks'' LLM Infrastructure team to build the training, fine-tuning, and serving infrastructure for large language models. You''ll work at the frontier of ML systems and distributed computing.','4+ years ML engineering. Experience training large models. Deep understanding of GPU compute and model parallelism.','["Python","PyTorch","CUDA","Spark","MLflow","Kubernetes","Distributed Training"]','active',1,0,NOW() - INTERVAL 1 DAY),
(8,'Product Designer — Design Systems','figma-product-designer','Design','San Francisco, CA','hybrid','full-time',4,8,160000,220000,'Own Figma''s design system that''s used by millions of designers worldwide. You''ll define patterns, create components, and collaborate with engineering to ship polished, accessible interfaces.','4+ years product design. Experience building design systems. Proficiency in Figma (naturally!).','["Figma","Design Systems","Prototyping","User Research","Accessibility","Motion Design"]','active',0,0,NOW() - INTERVAL 7 DAY),
(1,'Site Reliability Engineer','google-sre','SRE','Remote','remote','full-time',3,7,160000,230000,'Ensure reliability and scalability of Google''s production systems. You''ll be on-call for critical infrastructure, drive automation to eliminate toil, and build tooling to improve system observability.','3+ years SRE or DevOps. Strong programming skills. Experience with Linux, networking, and distributed systems.','["Go","Python","Kubernetes","Prometheus","Terraform","Linux","Distributed Systems"]','active',0,0,NOW() - INTERVAL 8 DAY),
(9,'Full Stack Engineer — Growth','notion-fullstack-growth','Growth Engineering','San Francisco, CA','hybrid','full-time',2,5,140000,200000,'Work on Notion''s growth team to experiment, build, and ship features that bring Notion to new users worldwide. You''ll own the full stack of growth experiments from backend to frontend.','2+ years full-stack experience. Experience with A/B testing frameworks. React and Node.js proficiency.','["React","TypeScript","Node.js","PostgreSQL","Redis","Next.js","Growth Engineering"]','active',0,0,NOW() - INTERVAL 3 DAY),
(10,'Infrastructure Engineer — Edge','vercel-infra-edge','Infrastructure','Remote','remote','full-time',3,6,150000,210000,'Build the global edge network that powers millions of deployments daily. You''ll work on low-latency routing, edge caching, and the serverless runtime that runs at 100+ locations worldwide.','3+ years systems/infrastructure engineering. Experience with CDN architecture. Networking fundamentals.','["Rust","Go","TypeScript","Linux","Networking","Kubernetes","Edge Computing"]','active',0,0,NOW() - INTERVAL 2 DAY),
(4,'Security Engineer — Application Security','stripe-appsec','Security','Remote','remote','full-time',4,8,170000,240000,'Protect Stripe''s payments infrastructure and customer data. You''ll perform security reviews, build tooling to catch vulnerabilities at scale, and work with product teams to ship secure features.','4+ years application security. Experience with web application vulnerabilities. Coding proficiency for security tooling.','["Python","Go","SAST","Penetration Testing","Cryptography","AWS Security","Threat Modeling"]','active',0,1,NOW() - INTERVAL 9 DAY),
(2,'Manufacturing Engineer — Battery','tesla-mfg-battery','Manufacturing','Fremont, CA','on-site','full-time',2,6,110000,160000,'Improve Tesla''s battery cell manufacturing process at Gigafactory. You''ll design and optimize production lines, reduce defect rates, and implement automation to scale cell output.','2+ years manufacturing engineering. Experience with statistical process control. Lean/Six Sigma preferred.','["SolidWorks","SPC","DFMEA","Lean Manufacturing","Python","AutoCAD","Six Sigma"]','active',0,0,NOW() - INTERVAL 10 DAY),
(7,'Data Scientist — Analytics','databricks-data-scientist','Analytics','Remote','remote','full-time',2,5,130000,185000,'Use data to understand how engineers use Databricks and identify growth opportunities. You''ll build dashboards, run experiments, and surface insights that drive product decisions.','2+ years data science. Strong statistics background. Experience with ML and causal inference.','["Python","SQL","R","Statistics","Machine Learning","Spark","Tableau"]','active',0,0,NOW() - INTERVAL 4 DAY),
(3,'Propulsion Engineer — Raptor Engine','spacex-propulsion-raptor','Propulsion','Hawthorne, CA','on-site','full-time',3,8,140000,210000,'Design and develop components for the Raptor engine — the most powerful rocket engine ever flown. You''ll work from concept to test, tackling combustion stability, turbopump dynamics, and materials at extreme conditions.','3+ years propulsion or mechanical engineering. CFD experience. Background in thermodynamics.','["CFD","MATLAB","SolidWorks","Thermodynamics","FEA","Python","System Testing"]','active',0,1,NOW() - INTERVAL 6 DAY);

INSERT INTO skills (name, category, job_count) VALUES
('Python','Programming',8),('Go','Programming',5),('C++','Programming',4),('C','Programming',2),
('TypeScript','Programming',5),('Rust','Programming',1),('Ruby','Programming',1),('SQL','Data',6),
('React','Frontend',4),('Node.js','Backend',3),('Kubernetes','DevOps',4),('AWS','Cloud',5),
('PostgreSQL','Database',3),('Redis','Database',3),('Docker','DevOps',4),('Linux','Systems',4),
('Machine Learning','AI/ML',3),('PyTorch','AI/ML',2),('Spark','Data',3),('Kafka','Backend',2),
('gRPC','Backend',2),('GraphQL','API',2),('Terraform','DevOps',3),('Figma','Design',1);

INSERT INTO seekers (full_name, email, phone, location, headline, summary, years_exp, skills, is_open, password_hash) VALUES
('Arjun Mehta','arjun@example.com','+1 415 555 0101','San Francisco, CA','Senior Software Engineer | Distributed Systems & Go','5+ years building large-scale backend systems. Passionate about distributed computing and reliability engineering.',5,'["Go","Python","Kubernetes","PostgreSQL","gRPC","Linux"]',1,password('demo1234')),
('Priya Sharma','priya@example.com','+1 512 555 0202','Austin, TX','Embedded Systems Engineer | C/C++ & RTOS','Hardware-software boundary engineer with deep experience in automotive and aerospace embedded systems.',4,'["C","C++","RTOS","CAN Bus","ARM","Python","AUTOSAR"]',1,password('demo1234')),
('James Chen','james@example.com','+1 650 555 0303','Mountain View, CA','ML Engineer | LLMs & PyTorch','Machine learning engineer specializing in large model training and inference optimization.',6,'["Python","PyTorch","CUDA","Spark","Kubernetes","MLflow"]',1,password('demo1234'));

INSERT INTO employers (company_id, full_name, email, role, password_hash) VALUES
(1,'Sarah Mitchell','sarah@google.com','Recruiter',password('demo1234')),
(2,'David Park','david@tesla.com','Engineering Manager',password('demo1234')),
(4,'Lisa Wong','lisa@stripe.com','Senior Recruiter',password('demo1234'));