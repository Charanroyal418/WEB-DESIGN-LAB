-- =============================================
-- WORKSHOP DATABASE SETUP
-- =============================================

-- Create database
CREATE DATABASE IF NOT EXISTS workshop_db;
USE workshop_db;

-- =============================================
-- WORKSHOPS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS workshops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    time TIME,
    location VARCHAR(255),
    capacity INT NOT NULL DEFAULT 20,
    current_participants INT DEFAULT 0,
    price DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date),
    INDEX idx_status (status),
    CHECK (current_participants <= capacity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PARTICIPANTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workshop_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    company VARCHAR(255),
    job_title VARCHAR(255),
    dietary_requirements TEXT,
    special_requests TEXT,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'confirmed', 'attended', 'cancelled') DEFAULT 'registered',
    registration_code VARCHAR(50) UNIQUE,
    FOREIGN KEY (workshop_id) REFERENCES workshops(id) ON DELETE CASCADE,
    UNIQUE KEY unique_registration (workshop_id, email),
    INDEX idx_email (email),
    INDEX idx_registration_date (registration_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TRIGGER: Auto-generate registration code
-- =============================================
DELIMITER //
CREATE TRIGGER tr_generate_registration_code
BEFORE INSERT ON participants
FOR EACH ROW
BEGIN
    IF NEW.registration_code IS NULL THEN
        SET NEW.registration_code = CONCAT(
            'REG-',
            DATE_FORMAT(NOW(), '%Y%m'),
            '-',
            LPAD(FLOOR(RAND() * 1000000), 6, '0')
        );
    END IF;
END//
DELIMITER ;

-- =============================================
-- SAMPLE DATA
-- =============================================

INSERT INTO workshops (title, description, date, time, location, capacity, price, status) VALUES
('Advanced PHP Development', 'Master advanced PHP concepts including OOP, design patterns, and modern frameworks', 
 '2026-07-15', '09:00:00', 'Online - Zoom', 20, 99.99, 'upcoming'),

('MySQL Performance Optimization', 'Learn advanced MySQL optimization techniques, query tuning, and indexing strategies', 
 '2026-07-20', '10:00:00', 'Conference Room A', 15, 149.99, 'upcoming'),

('JavaScript & React Masterclass', 'Comprehensive workshop on modern JavaScript and React development', 
 '2026-07-25', '09:30:00', 'Online - Google Meet', 25, 129.99, 'upcoming'),

('DevOps with Docker & Kubernetes', 'Hands-on workshop on containerization and orchestration', 
 '2026-08-01', '10:00:00', 'Tech Hub, Room 201', 12, 199.99, 'upcoming'),

('Machine Learning Fundamentals', 'Introduction to machine learning with Python and scikit-learn', 
 '2026-08-05', '09:00:00', 'Online - Zoom', 30, 89.99, 'upcoming');