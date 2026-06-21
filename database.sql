-- =====================================================
-- RUCU Graduate Employment Tracking & Verification System
-- Database Schema
-- =====================================================

CREATE DATABASE IF NOT EXISTS rucu_gets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rucu_gets;

-- =====================================================
-- GRADUATES TABLE (synced from SIMS)
-- =====================================================
CREATE TABLE IF NOT EXISTS graduates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reg_number VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(20),
    course VARCHAR(150) NOT NULL,
    graduation_year INT NOT NULL,
    form4_index_number VARCHAR(50) NOT NULL,
    password VARCHAR(255) DEFAULT NULL,
    password_expiry_date DATETIME DEFAULT NULL,
    first_login TINYINT(1) DEFAULT 1,
    profile_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reg_number (reg_number),
    INDEX idx_graduation_year (graduation_year),
    INDEX idx_course (course)
) ENGINE=InnoDB;

-- =====================================================
-- EMPLOYMENT DETAILS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS employment_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    graduate_id INT NOT NULL,
    employment_status ENUM('employed', 'self_employed', 'unemployed', 'further_studies', 'seeking') DEFAULT 'unemployed',
    company_name VARCHAR(200),
    organization_type ENUM('government', 'private', 'ngo', 'self_employed', 'international', 'other'),
    job_title VARCHAR(150),
    salary_range VARCHAR(50),
    start_date DATE,
    location VARCHAR(150),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (graduate_id) REFERENCES graduates(id) ON DELETE CASCADE,
    INDEX idx_graduate_id (graduate_id),
    INDEX idx_employment_status (employment_status)
) ENGINE=InnoDB;

-- =====================================================
-- VERIFICATION LOGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS verification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    graduate_id INT NOT NULL,
    verification_status ENUM('verified', 'not_verified', 'pending') DEFAULT 'pending',
    verification_source ENUM('necta', 'employer_simulation', 'manual_review') NOT NULL,
    necta_status VARCHAR(50) DEFAULT NULL,
    employer_match TINYINT(1) DEFAULT NULL,
    notes TEXT,
    date_checked DATETIME DEFAULT CURRENT_TIMESTAMP,
    checked_by VARCHAR(100) DEFAULT 'system',
    FOREIGN KEY (graduate_id) REFERENCES graduates(id) ON DELETE CASCADE,
    INDEX idx_graduate_id (graduate_id),
    INDEX idx_verification_status (verification_status),
    INDEX idx_date_checked (date_checked)
) ENGINE=InnoDB;

-- =====================================================
-- ADMIN USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'staff', 'dvcaa') DEFAULT 'staff',
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- JOB FEED CACHE TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS job_feed (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    organization VARCHAR(200) NOT NULL,
    location VARCHAR(150),
    description TEXT,
    deadline DATETIME NOT NULL,
    source_url VARCHAR(500),
    source_name VARCHAR(100),
    status ENUM('active', 'expired') DEFAULT 'active',
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_deadline (deadline),
    INDEX idx_organization (organization),
    INDEX idx_status (status),
    UNIQUE KEY unique_job (title(100), organization(100))
) ENGINE=InnoDB;

-- =====================================================
-- ACTIVITY LOGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('graduate', 'admin') NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type, user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- SIMS SYNC LOG TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS sims_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_type ENUM('full', 'incremental') NOT NULL,
    records_processed INT DEFAULT 0,
    records_added INT DEFAULT 0,
    records_updated INT DEFAULT 0,
    status ENUM('success', 'partial', 'failed') NOT NULL,
    notes TEXT,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL
) ENGINE=InnoDB;

-- =====================================================
-- INSERT DEFAULT ADMIN USER
-- Password: admin123
-- =====================================================
INSERT INTO admin_users (name, email, password, role) VALUES
('RUCU Administrator', 'admin@rucu.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin'),
('DVCAA Officer', 'dvcaa@rucu.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dvcaa');

-- =====================================================
-- INSERT SAMPLE GRADUATES (SIMS data simulation)
-- Password: graduate123 (for testing)
-- =====================================================
INSERT INTO graduates (reg_number, full_name, email, phone, course, graduation_year, form4_index_number, password, password_expiry_date, first_login) VALUES
('RUCU/2020/001', 'John Mwangema', 'john.mwangema@email.com', '+255712345001', 'Bachelor of Education', 2020, 'S0101/0001/2016', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', DATE_ADD(NOW(), INTERVAL 30 DAY), 0),
('RUCU/2020/002', 'Mary Josephat', 'mary.joseph@email.com', '+255712345002', 'Bachelor of Business Administration', 2020, 'S0102/0002/2016', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', DATE_ADD(NOW(), INTERVAL 30 DAY), 0),
('RUCU/2021/003', 'Emmanuel Mushi', 'emmanuel.mushi@email.com', '+255712345003', 'Bachelor of Science in ICT', 2021, 'S0103/0003/2017', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', DATE_ADD(NOW(), INTERVAL 30 DAY), 0),
('RUCU/2021/004', 'Grace Mlay', 'grace.mlay@email.com', '+255712345004', 'Bachelor of Nursing', 2021, 'S0104/0004/2017', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', DATE_ADD(NOW(), INTERVAL 30 DAY), 0),
('RUCU/2022/005', 'David Mrema', 'david.mrema@email.com', '+255712345005', 'Bachelor of Education', 2022, 'S0105/0005/2018', NULL, NULL, 1),
('RUCU/2022/006', 'Sarah Kimaro', 'sarah.kimaro@email.com', '+255712345006', 'Bachelor of Business Administration', 2022, 'S0106/0006/2018', NULL, NULL, 1),
('RUCU/2023/007', 'Michael Massawe', 'michael.massawe@email.com', '+255712345007', 'Bachelor of Science in ICT', 2023, 'S0107/0007/2019', NULL, NULL, 1),
('RUCU/2023/008', 'Rebecca Mtegha', 'rebecca.mtegha@email.com', '+255712345008', 'Bachelor of Theology', 2023, 'S0108/0008/2019', NULL, NULL, 1),
('RUCU/2023/009', 'Joseph Mmbaga', 'joseph.mmbaga@email.com', '+255712345009', 'Bachelor of Education', 2023, 'S0109/0009/2019', NULL, NULL, 1),
('RUCU/2024/010', 'Elizabeth Swai', 'elizabeth.swai@email.com', '+255712345010', 'Bachelor of Nursing', 2024, 'S0110/0010/2020', NULL, NULL, 1),
('RUCU/2024/011', 'Peter Mgaya', 'peter.mgaya@email.com', '+255712345011', 'Bachelor of Business Administration', 2024, 'S0111/0011/2020', NULL, NULL, 1),
('RUCU/2024/012', 'Anna Lema', 'anna.lema@email.com', '+255712345012', 'Bachelor of Science in ICT', 2024, 'S0112/0012/2020', NULL, NULL, 1);

-- =====================================================
-- INSERT SAMPLE EMPLOYMENT DATA
-- =====================================================
INSERT INTO employment_details (graduate_id, employment_status, company_name, organization_type, job_title, salary_range, start_date, location) VALUES
(1, 'employed', 'Ministry of Education', 'government', 'Secondary School Teacher', '500000-800000', '2021-01-15', 'Dodoma'),
(2, 'employed', 'CRDB Bank', 'private', 'Customer Relations Officer', '800000-1200000', '2021-03-01', 'Dar es Salaam'),
(3, 'employed', 'Tanzania Communications Authority', 'government', 'ICT Officer', '1000000-1500000', '2021-06-01', 'Dar es Salaam'),
(4, 'employed', 'KCMC Hospital', 'government', 'Registered Nurse', '700000-1000000', '2022-01-10', 'Moshi'),
(7, 'self_employed', 'Self - Tech Solutions', 'self_employed', 'Freelance Developer', '500000-1000000', '2023-09-01', 'Iringa'),
(9, 'employed', 'St. Mary Secondary School', 'private', 'Teacher', '400000-600000', '2024-01-15', 'Iringa');

-- =====================================================
-- INSERT SAMPLE VERIFICATION LOGS
-- =====================================================
INSERT INTO verification_logs (graduate_id, verification_status, verification_source, necta_status, employer_match, notes, date_checked) VALUES
(1, 'verified', 'necta', 'verified', 1, 'Form IV index number verified through NECTA database', '2024-01-20 10:00:00'),
(2, 'verified', 'necta', 'verified', 1, 'Employment confirmed through employer simulation', '2024-01-20 10:05:00'),
(3, 'verified', 'employer_simulation', 'verified', 1, 'TCRA employment record matched', '2024-02-15 14:30:00'),
(4, 'verified', 'necta', 'verified', 1, 'Nursing registration verified', '2024-02-20 09:00:00'),
(7, 'pending', 'necta', 'pending', 0, 'Self-employment requires manual review', '2024-03-01 11:00:00');
