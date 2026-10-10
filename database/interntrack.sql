-- ============================================================
-- InternTrack - Internship Management System
-- Target: MySQL 8.0+
-- Character set: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS interntrack
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE interntrack;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    avatar VARCHAR(255) NULL,
    role ENUM('student', 'company', 'lecturer', 'admin') NOT NULL,
    status ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_users_username UNIQUE (username),
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT ck_users_email CHECK (email LIKE '%_@_%._%'),
    CONSTRAINT ck_users_phone CHECK (phone IS NULL OR phone REGEXP '^[0-9+() .-]{8,20}$'),

    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 2. STUDENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    student_code VARCHAR(30) NOT NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    address VARCHAR(255) NULL,
    major VARCHAR(150) NULL,
    class_name VARCHAR(100) NULL,
    faculty VARCHAR(150) NULL,
    university VARCHAR(200) NULL,
    cv_file VARCHAR(255) NULL,
    bio TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_students_user UNIQUE (user_id),
    CONSTRAINT uq_students_code UNIQUE (student_code),

    CONSTRAINT fk_students_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    INDEX idx_students_major (major),
    INDEX idx_students_class (class_name)
) ENGINE=InnoDB;

-- ============================================================
-- 3. COMPANIES
-- ============================================================
CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    company_code VARCHAR(30) NOT NULL,
    company_name VARCHAR(200) NOT NULL,
    tax_code VARCHAR(50) NULL,
    website VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    description TEXT NULL,
    logo VARCHAR(255) NULL,
    status ENUM('pending', 'active', 'inactive', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_companies_user UNIQUE (user_id),
    CONSTRAINT uq_companies_code UNIQUE (company_code),
    CONSTRAINT uq_companies_tax_code UNIQUE (tax_code),

    CONSTRAINT fk_companies_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    INDEX idx_companies_name (company_name),
    INDEX idx_companies_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 4. LECTURERS
-- ============================================================
CREATE TABLE IF NOT EXISTS lecturers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    lecturer_code VARCHAR(30) NOT NULL,
    department VARCHAR(150) NULL,
    academic_title VARCHAR(100) NULL,
    specialization VARCHAR(200) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_lecturers_user UNIQUE (user_id),
    CONSTRAINT uq_lecturers_code UNIQUE (lecturer_code),

    CONSTRAINT fk_lecturers_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    INDEX idx_lecturers_department (department)
) ENGINE=InnoDB;

-- ============================================================
-- 5. POSITIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS positions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    requirements TEXT NULL,
    benefits TEXT NULL,
    location VARCHAR(255) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    deadline DATE NULL,
    status ENUM('draft', 'open', 'closed', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_positions_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT ck_positions_quantity CHECK (quantity > 0),
    CONSTRAINT ck_positions_deadline CHECK (deadline IS NULL OR deadline >= DATE(created_at)),

    INDEX idx_positions_company (company_id),
    INDEX idx_positions_status_deadline (status, deadline),
    INDEX idx_positions_title (title)
) ENGINE=InnoDB;

-- ============================================================
-- 6. INTERNSHIPS
-- One accepted application creates one internship record.
-- ============================================================
CREATE TABLE IF NOT EXISTS internships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    position_id BIGINT UNSIGNED NOT NULL,
    lecturer_id BIGINT UNSIGNED NULL,

    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    actual_end_date DATE NULL,

    status ENUM(
        'planned',
        'active',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'planned',

    description TEXT NULL,
    training_plan TEXT NULL,
    final_score DECIMAL(5,2) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_internships_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_internships_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_internships_position
        FOREIGN KEY (position_id) REFERENCES positions(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_internships_lecturer
        FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT ck_internships_dates
        CHECK (end_date >= start_date),

    CONSTRAINT ck_internships_actual_end
        CHECK (actual_end_date IS NULL OR actual_end_date >= start_date),

    CONSTRAINT ck_internships_score
        CHECK (final_score IS NULL OR (final_score >= 0 AND final_score <= 100)),

    CONSTRAINT uq_active_student_internship
        UNIQUE (student_id, start_date, position_id),

    INDEX idx_internships_student_status (student_id, status),
    INDEX idx_internships_company_status (company_id, status),
    INDEX idx_internships_lecturer_status (lecturer_id, status),
    INDEX idx_internships_dates (start_date, end_date)
) ENGINE=InnoDB;

-- ============================================================
-- 7. APPLICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    position_id BIGINT UNSIGNED NOT NULL,
    cv_file VARCHAR(255) NULL,
    cover_letter TEXT NULL,

    status ENUM(
        'pending',
        'reviewing',
        'accepted',
        'rejected',
        'withdrawn'
    ) NOT NULL DEFAULT 'pending',

    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    note TEXT NULL,

    CONSTRAINT fk_applications_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_applications_position
        FOREIGN KEY (position_id) REFERENCES positions(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_applications_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT uq_application_student_position
        UNIQUE (student_id, position_id),

    INDEX idx_applications_student_status (student_id, status),
    INDEX idx_applications_position_status (position_id, status),
    INDEX idx_applications_reviewed_by (reviewed_by)
) ENGINE=InnoDB;

-- ============================================================
-- 8. TASKS
-- Tasks can belong to an internship.
-- created_by can be company user or lecturer user.
-- assigned_to is the intern/student.
-- ============================================================
CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,

    assigned_to BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,

    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    status ENUM(
        'todo',
        'in_progress',
        'submitted',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'todo',

    start_date DATE NULL,
    due_date DATE NULL,
    completed_at DATETIME NULL,

    submission_link VARCHAR(500) NULL,
    submission_file VARCHAR(255) NULL,
    submission_note TEXT NULL,
    submitted_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_tasks_internship
        FOREIGN KEY (internship_id) REFERENCES internships(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_tasks_student
        FOREIGN KEY (assigned_to) REFERENCES students(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_tasks_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT ck_tasks_dates
        CHECK (
            due_date IS NULL
            OR start_date IS NULL
            OR due_date >= start_date
        ),

    INDEX idx_tasks_internship_status (internship_id, status),
    INDEX idx_tasks_student_status (assigned_to, status),
    INDEX idx_tasks_due_date (due_date)
) ENGINE=InnoDB;

-- ============================================================
-- 9. DIARIES
-- One diary entry per internship per date.
-- ============================================================
CREATE TABLE IF NOT EXISTS diaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    diary_date DATE NOT NULL,
    title VARCHAR(200) NULL,
    content TEXT NOT NULL,
    hours_worked DECIMAL(5,2) NULL,
    status ENUM('draft', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    lecturer_feedback TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_diaries_internship
        FOREIGN KEY (internship_id) REFERENCES internships(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_diaries_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_diaries_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES lecturers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT uq_diary_internship_date
        UNIQUE (internship_id, diary_date),

    CONSTRAINT ck_diaries_hours
        CHECK (hours_worked IS NULL OR (hours_worked >= 0 AND hours_worked <= 24)),

    INDEX idx_diaries_student_date (student_id, diary_date),
    INDEX idx_diaries_internship_status (internship_id, status)
) ENGINE=InnoDB;

-- ============================================================
-- 10. REPORTS
-- Supports multiple reports for one internship:
-- proposal, midterm, final, other.
-- ============================================================
CREATE TABLE IF NOT EXISTS reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,

    report_type ENUM(
        'proposal',
        'midterm',
        'final',
        'other'
    ) NOT NULL,

    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NULL,
    content LONGTEXT NULL,

    status ENUM(
        'draft',
        'submitted',
        'reviewing',
        'approved',
        'rejected'
    ) NOT NULL DEFAULT 'draft',

    submitted_at DATETIME NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    feedback TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_reports_internship
        FOREIGN KEY (internship_id) REFERENCES internships(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_reports_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_reports_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES lecturers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT uq_report_type_per_internship
        UNIQUE (internship_id, report_type),

    INDEX idx_reports_student_status (student_id, status),
    INDEX idx_reports_internship_status (internship_id, status)
) ENGINE=InnoDB;

-- ============================================================
-- 11. EVALUATIONS
-- Generic evaluation table.
-- Evaluator can be lecturer or company representative.
-- Target is always the internship/student.
-- ============================================================
CREATE TABLE IF NOT EXISTS evaluations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    evaluator_user_id BIGINT UNSIGNED NOT NULL,

    evaluator_type ENUM('company', 'lecturer') NOT NULL,

    technical_score DECIMAL(5,2) NULL,
    attitude_score DECIMAL(5,2) NULL,
    communication_score DECIMAL(5,2) NULL,
    discipline_score DECIMAL(5,2) NULL,
    overall_score DECIMAL(5,2) NULL,

    comments TEXT NULL,

    status ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_evaluations_internship
        FOREIGN KEY (internship_id) REFERENCES internships(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_evaluations_evaluator
        FOREIGN KEY (evaluator_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT ck_eval_technical
        CHECK (technical_score IS NULL OR (technical_score BETWEEN 0 AND 100)),

    CONSTRAINT ck_eval_attitude
        CHECK (attitude_score IS NULL OR (attitude_score BETWEEN 0 AND 100)),

    CONSTRAINT ck_eval_communication
        CHECK (communication_score IS NULL OR (communication_score BETWEEN 0 AND 100)),

    CONSTRAINT ck_eval_discipline
        CHECK (discipline_score IS NULL OR (discipline_score BETWEEN 0 AND 100)),

    CONSTRAINT ck_eval_overall
        CHECK (overall_score IS NULL OR (overall_score BETWEEN 0 AND 100)),

    CONSTRAINT uq_evaluation_per_evaluator
        UNIQUE (internship_id, evaluator_user_id),

    INDEX idx_evaluations_internship (internship_id),
    INDEX idx_evaluations_evaluator (evaluator_user_id)
) ENGINE=InnoDB;

-- ============================================================
-- 12. OPTIONAL: PASSWORD RESET TOKENS
-- Supports views/auth/forgot-password.php
-- ============================================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_password_reset_token UNIQUE (token_hash),

    CONSTRAINT fk_password_reset_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    INDEX idx_password_reset_user_expires (user_id, expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_otps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    otp_hash CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_otp_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_password_reset_otp_user (user_id, expires_at, used_at)
) ENGINE=InnoDB;

-- ============================================================
-- 13. OPTIONAL: APPLICATION / INTERNSHIP STATUS HISTORY
-- Useful for auditing workflow changes.
-- ============================================================
CREATE TABLE IF NOT EXISTS application_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    old_status ENUM(
        'pending',
        'reviewing',
        'accepted',
        'rejected',
        'withdrawn'
    ) NULL,
    new_status ENUM(
        'pending',
        'reviewing',
        'accepted',
        'rejected',
        'withdrawn'
    ) NOT NULL,
    changed_by BIGINT UNSIGNED NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_application_history_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_application_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    INDEX idx_application_history_application (application_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS internship_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    old_status ENUM(
        'planned',
        'active',
        'completed',
        'cancelled'
    ) NULL,
    new_status ENUM(
        'planned',
        'active',
        'completed',
        'cancelled'
    ) NOT NULL,
    changed_by BIGINT UNSIGNED NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_internship_history_internship
        FOREIGN KEY (internship_id) REFERENCES internships(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_internship_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    INDEX idx_internship_history_internship (internship_id, created_at)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 14. BASIC VIEWS
-- ============================================================

CREATE OR REPLACE VIEW v_student_internships AS
SELECT
    i.id AS internship_id,
    s.id AS student_id,
    s.student_code,
    u.full_name AS student_name,
    c.id AS company_id,
    c.company_name,
    p.id AS position_id,
    p.title AS position_title,
    i.start_date,
    i.end_date,
    i.actual_end_date,
    i.status,
    i.final_score,
    l.id AS lecturer_id,
    lu.full_name AS lecturer_name
FROM internships i
JOIN students s ON s.id = i.student_id
JOIN users u ON u.id = s.user_id
JOIN companies c ON c.id = i.company_id
JOIN positions p ON p.id = i.position_id
LEFT JOIN lecturers l ON l.id = i.lecturer_id
LEFT JOIN users lu ON lu.id = l.user_id;

CREATE OR REPLACE VIEW v_application_list AS
SELECT
    a.id AS application_id,
    a.student_id,
    s.student_code,
    su.full_name AS student_name,
    a.position_id,
    p.title AS position_title,
    p.company_id,
    c.company_name,
    a.status,
    a.applied_at,
    a.reviewed_at,
    a.note
FROM applications a
JOIN students s ON s.id = a.student_id
JOIN users su ON su.id = s.user_id
JOIN positions p ON p.id = a.position_id
JOIN companies c ON c.id = p.company_id;

CREATE OR REPLACE VIEW v_task_list AS
SELECT
    t.id,
    t.internship_id,
    t.title,
    t.description,
    t.priority,
    t.status,
    t.start_date,
    t.due_date,
    t.completed_at,
    t.assigned_to AS student_id,
    su.full_name AS student_name,
    t.created_by,
    cu.full_name AS creator_name,
    t.created_at,
    t.updated_at
FROM tasks t
JOIN students s ON s.id = t.assigned_to
JOIN users su ON su.id = s.user_id
JOIN users cu ON cu.id = t.created_by;

CREATE OR REPLACE VIEW v_internship_progress AS
SELECT
    i.id AS internship_id,
    i.student_id,
    COUNT(t.id) AS total_tasks,
    SUM(t.status = 'completed') AS completed_tasks,
    SUM(t.status IN ('todo', 'in_progress', 'submitted')) AS pending_tasks,
    CASE
        WHEN COUNT(t.id) = 0 THEN 0
        ELSE ROUND(
            SUM(t.status = 'completed') * 100.0 / COUNT(t.id),
            2
        )
    END AS task_completion_percent
FROM internships i
LEFT JOIN tasks t ON t.internship_id = i.id
GROUP BY i.id, i.student_id;

-- ============================================================
-- 14b. NOTIFICATIONS (thông báo theo từng mục menu)
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    section VARCHAR(40) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message VARCHAR(500) NULL,
    link VARCHAR(255) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    INDEX idx_notifications_user_unread (user_id, read_at, section),
    INDEX idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14c. MESSAGES (nhắn tin trực tiếp 1-1 giữa sinh viên, giảng viên, doanh nghiệp)
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id BIGINT UNSIGNED NOT NULL,
    recipient_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_messages_recipient
        FOREIGN KEY (recipient_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    INDEX idx_messages_pair (sender_id, recipient_id, id),
    INDEX idx_messages_inbox (recipient_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. SAMPLE ADMIN ACCOUNT
-- IMPORTANT:
-- Replace password_hash with a real password_hash generated by
-- PHP password_hash('your-password', PASSWORD_DEFAULT).
-- ============================================================
INSERT IGNORE INTO users (
    username,
    email,
    password_hash,
    full_name,
    role,
    status
) VALUES (
    'admin',
    'admin@interntrack.local',
    '$2y$10$REPLACE_WITH_REAL_PASSWORD_HASH',
    'System Administrator',
    'admin',
    'active'
);



-- ============================================================
-- Gợi ý ghép sinh viên - vị trí thực tập bằng AI (đọc CV)
-- ============================================================
CREATE TABLE IF NOT EXISTS ai_matches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    position_id BIGINT UNSIGNED NOT NULL,
    score TINYINT UNSIGNED NOT NULL,
    reason VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_ai_matches UNIQUE (student_id, position_id),

    CONSTRAINT fk_ai_matches_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_ai_matches_position
        FOREIGN KEY (position_id) REFERENCES positions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    INDEX idx_ai_matches_student_score (student_id, score)
) ENGINE=InnoDB;

-- ============================================================
-- Kết quả phân tích của AI (tóm tắt nhật ký/báo cáo, cảnh báo rủi ro, nhận xét cuối kỳ, thống kê kỹ năng)
-- ============================================================
CREATE TABLE IF NOT EXISTS ai_insights (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(30) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    payload MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_ai_insights UNIQUE (kind, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Tài liệu của khoa (quy định, mốc thời gian, biểu mẫu) làm nguồn cho trợ lý hỏi đáp
-- ============================================================
CREATE TABLE IF NOT EXISTS kb_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_kb_documents_user
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Kỹ năng AI đề xuất từ nhật ký tuần, chờ người hướng dẫn ở doanh nghiệp xác nhận / sửa / bác bỏ
-- ============================================================
CREATE TABLE IF NOT EXISTS skill_suggestions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    diary_id BIGINT UNSIGNED NOT NULL,
    internship_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    skill_type ENUM('chuyen_mon', 'lam_viec') NOT NULL,
    evidence TEXT NOT NULL,
    level ENUM('moi_lam_quen', 'can_huong_dan', 'tu_lam_co_ho_tro', 'tu_lam_doc_lap') NOT NULL,
    existed_before TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending', 'confirmed', 'edited', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_skill_suggestions_diary
        FOREIGN KEY (diary_id) REFERENCES diaries(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_skill_suggestions_internship
        FOREIGN KEY (internship_id) REFERENCES internships(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_skill_suggestions_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    INDEX idx_skill_suggestions_diary (diary_id, status),
    INDEX idx_skill_suggestions_internship (internship_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- END
-- ============================================================
