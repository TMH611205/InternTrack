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
    -- google_sub: tài khoản Google (email trường) đã liên kết để đăng nhập SSO.
    google_sub VARCHAR(64) NULL,
    -- scope_faculty: quản trị cấp khoa (trưởng khoa) chỉ thấy dữ liệu của khoa này; NULL = toàn trường.
    scope_faculty VARCHAR(150) NULL,
    role ENUM('student', 'company', 'lecturer', 'admin') NOT NULL,
    status ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_users_username UNIQUE (username),
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT uq_users_google_sub UNIQUE (google_sub),
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
    specialization VARCHAR(150) NULL,
    class_name VARCHAR(100) NULL,
    faculty VARCHAR(150) NULL,
    university VARCHAR(200) NULL,
    cv_file VARCHAR(255) NULL,
    bio TEXT NULL,
    gpa DECIMAL(3,2) NULL,
    credits SMALLINT UNSIGNED NULL,
    training_system VARCHAR(60) NULL,
    skills TEXT NULL,
    interests TEXT NULL,
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
    field VARCHAR(150) NULL,
    contact_name VARCHAR(150) NULL,
    contact_position VARCHAR(150) NULL,
    mou_status ENUM('none', 'negotiating', 'signed', 'expired') NOT NULL DEFAULT 'none',
    mou_expires DATE NULL,
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
    faculty VARCHAR(150) NULL,
    academic_title VARCHAR(100) NULL,
    specialization VARCHAR(200) NULL,
    research_areas TEXT NULL,
    max_students TINYINT UNSIGNED NOT NULL DEFAULT 8,
    max_reviews TINYINT UNSIGNED NOT NULL DEFAULT 10,
    max_councils TINYINT UNSIGNED NOT NULL DEFAULT 6,
    is_head TINYINT(1) NOT NULL DEFAULT 0,
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
    -- pending: doanh nghiệp gửi đăng, chờ khoa duyệt; approved_at: thời điểm khoa duyệt (tin đã duyệt mở lại không cần duyệt lại).
    status ENUM('draft', 'pending', 'open', 'closed', 'cancelled') NOT NULL DEFAULT 'draft',
    approved_at DATETIME NULL,
    review_note VARCHAR(500) NULL,
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
    -- Người hướng dẫn tại doanh nghiệp (tài khoản trong company_mentors).
    mentor_user_id BIGINT UNSIGNED NULL,

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
    batch_id BIGINT UNSIGNED NULL,
    self_found TINYINT(1) NOT NULL DEFAULT 0,

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
    interview_at DATETIME NULL,
    interview_place VARCHAR(255) NULL,

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
    -- Người hướng dẫn tại doanh nghiệp xác nhận nội dung nhật ký (confirmed) hoặc ghi chú chưa đúng (flagged).
    company_status ENUM('confirmed', 'flagged') NULL,
    company_note VARCHAR(1000) NULL,
    company_reviewed_at DATETIME NULL,

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
    rubric_id BIGINT UNSIGNED NULL,

    technical_score DECIMAL(5,2) NULL,
    attitude_score DECIMAL(5,2) NULL,
    communication_score DECIMAL(5,2) NULL,
    discipline_score DECIMAL(5,2) NULL,
    overall_score DECIMAL(5,2) NULL,

    comments TEXT NULL,
    -- Doanh nghiệp xác nhận tuyển dụng / giữ lại sinh viên sau thực tập.
    offered TINYINT(1) NOT NULL DEFAULT 0,

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
-- 16. DANH MỤC CHUNG: năm học/học kỳ, ngành, cấu hình hệ thống
-- ============================================================
CREATE TABLE IF NOT EXISTS academic_terms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
    start_date DATE NULL,
    end_date DATE NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_academic_terms UNIQUE (school_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS majors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(150) NOT NULL,
    faculty VARCHAR(150) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_majors_code UNIQUE (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chuyên ngành thuộc một ngành (danh mục chung).
CREATE TABLE IF NOT EXISTS specializations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    major_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    CONSTRAINT uq_specializations UNIQUE (major_id, name),
    CONSTRAINT fk_specializations_major FOREIGN KEY (major_id) REFERENCES majors(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. NHẬT KÝ HỆ THỐNG (audit log): ai làm gì, lúc nào
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    role VARCHAR(20) NULL,
    action VARCHAR(60) NOT NULL,
    target VARCHAR(255) NULL,
    details TEXT NULL,
    -- Giá trị trước / sau khi sửa: JSON {trường: [cũ, mới]}.
    changes TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_audit_logs_created (created_at),
    INDEX idx_audit_logs_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. ĐỢT THỰC TẬP / ĐỢT ĐỒ ÁN, mốc thời gian và danh sách đủ điều kiện
-- ============================================================
CREATE TABLE IF NOT EXISTS batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id BIGINT UNSIGNED NULL,
    batch_type ENUM('internship', 'thesis') NOT NULL,
    name VARCHAR(200) NOT NULL,
    major_id BIGINT UNSIGNED NULL,
    specialization_id BIGINT UNSIGNED NULL,
    training_system VARCHAR(60) NULL,
    min_credits SMALLINT UNSIGNED NULL,
    min_gpa DECIMAL(3,2) NULL,
    prerequisites TEXT NULL,
    -- Mã học phần bắt buộc đã đạt (ngăn cách bằng dấu phẩy), đối chiếu với student_courses.
    required_courses VARCHAR(500) NULL,
    allow_student_proposals TINYINT(1) NOT NULL DEFAULT 1,
    -- Đợt thực tập: 1 = khoa phân bổ sinh viên vào vị trí theo nguyện vọng (ngoài ứng tuyển trực tiếp).
    allocation_mode TINYINT(1) NOT NULL DEFAULT 0,
    max_preferences TINYINT UNSIGNED NOT NULL DEFAULT 5,
    group_max_size TINYINT UNSIGNED NOT NULL DEFAULT 1,
    supervisor_weight DECIMAL(4,2) NOT NULL DEFAULT 0.40,
    reviewer_weight DECIMAL(4,2) NOT NULL DEFAULT 0.20,
    council_weight DECIMAL(4,2) NOT NULL DEFAULT 0.40,
    pass_score DECIMAL(4,2) NOT NULL DEFAULT 5.00,
    supervisor_rubric_id BIGINT UNSIGNED NULL,
    reviewer_rubric_id BIGINT UNSIGNED NULL,
    council_rubric_id BIGINT UNSIGNED NULL,
    -- Đợt thực tập: rubric doanh nghiệp / giảng viên dùng khi đánh giá (trống = 4 tiêu chí mặc định).
    company_rubric_id BIGINT UNSIGNED NULL,
    lecturer_rubric_id BIGINT UNSIGNED NULL,
    results_published_at DATETIME NULL,
    status ENUM('draft', 'open', 'closed') NOT NULL DEFAULT 'draft',
    locked_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_batches_term FOREIGN KEY (term_id) REFERENCES academic_terms(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_batches_major FOREIGN KEY (major_id) REFERENCES majors(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_batches_type_status (batch_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mốc thời gian của đợt. kind: topic_registration, preference, matching, report_1, report_2, report_final, evaluation, defense, close.
-- Chức năng gắn với một mốc chỉ mở trong khoảng starts_at – ends_at (mốc chưa đặt thì không giới hạn).
CREATE TABLE IF NOT EXISTS batch_milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(30) NOT NULL,
    title VARCHAR(200) NOT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    CONSTRAINT fk_batch_milestones_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT uq_batch_milestone_kind UNIQUE (batch_id, kind),
    INDEX idx_batch_milestones_end (ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batch_students (
    batch_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    eligible TINYINT(1) NOT NULL DEFAULT 1,
    note VARCHAR(255) NULL,
    PRIMARY KEY (batch_id, student_id),
    CONSTRAINT fk_batch_students_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_batch_students_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. ĐỀ TÀI ĐỒ ÁN, nhóm, nguyện vọng và xếp hạng hai phía
-- ============================================================
CREATE TABLE IF NOT EXISTS topics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    lecturer_id BIGINT UNSIGNED NULL,
    proposed_by_student_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    objectives TEXT NULL,
    requirements TEXT NULL,
    technologies VARCHAR(255) NULL,
    field VARCHAR(150) NULL,
    max_students TINYINT UNSIGNED NOT NULL DEFAULT 1,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    -- dept_approved: trưởng bộ môn đã duyệt, chờ khoa / phòng đào tạo duyệt (khi bật duyệt 2 cấp).
    status ENUM('draft', 'pending', 'dept_approved', 'revision', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    review_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_topics_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_topics_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_topics_student FOREIGN KEY (proposed_by_student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT ck_topics_max CHECK (max_students BETWEEN 1 AND 10),
    INDEX idx_topics_batch_status (batch_id, status),
    INDEX idx_topics_lecturer (lecturer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thesis_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    leader_student_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_thesis_groups_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_thesis_groups_leader FOREIGN KEY (leader_student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thesis_group_members (
    group_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    status ENUM('invited', 'accepted', 'declined') NOT NULL DEFAULT 'invited',
    responded_at DATETIME NULL,
    PRIMARY KEY (group_id, student_id),
    CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES thesis_groups(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_group_members_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS topic_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    topic_id BIGINT UNSIGNED NOT NULL,
    rank_no TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_topic_preferences_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_topic_preferences_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_topic_preferences_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT uq_topic_preference_rank UNIQUE (batch_id, student_id, rank_no),
    CONSTRAINT uq_topic_preference_topic UNIQUE (batch_id, student_id, topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lecturer_rankings (
    topic_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    rank_no SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (topic_id, student_id),
    CONSTRAINT fk_lecturer_rankings_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_lecturer_rankings_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. GHÉP CẶP: lịch sử mỗi lần chạy (tham số, kết quả, chỉ số)
-- ============================================================
CREATE TABLE IF NOT EXISTS matching_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    algorithm VARCHAR(40) NOT NULL,
    params TEXT NOT NULL,
    result MEDIUMTEXT NOT NULL,
    metrics TEXT NOT NULL,
    status ENUM('preview', 'published', 'discarded') NOT NULL DEFAULT 'preview',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    CONSTRAINT fk_matching_runs_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_matching_runs_batch (batch_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 21. HỘI ĐỒNG BẢO VỆ và ĐỒ ÁN (kết quả ghép sinh viên – đề tài – giảng viên)
-- ============================================================
CREATE TABLE IF NOT EXISTS councils (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    room VARCHAR(100) NULL,
    defense_date DATE NULL,
    start_time TIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_councils_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS council_members (
    council_id BIGINT UNSIGNED NOT NULL,
    lecturer_id BIGINT UNSIGNED NOT NULL,
    member_role ENUM('chair', 'secretary', 'member') NOT NULL DEFAULT 'member',
    PRIMARY KEY (council_id, lecturer_id),
    CONSTRAINT fk_council_members_council FOREIGN KEY (council_id) REFERENCES councils(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_council_members_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS theses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    topic_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED NULL,
    supervisor_id BIGINT UNSIGNED NULL,
    reviewer_id BIGINT UNSIGNED NULL,
    council_id BIGINT UNSIGNED NULL,
    defense_at DATETIME NULL,
    source ENUM('auto', 'manual', 'proposal') NOT NULL DEFAULT 'auto',
    matched_rank TINYINT UNSIGNED NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('assigned', 'confirmed', 'declined', 'in_progress', 'completed') NOT NULL DEFAULT 'assigned',
    student_confirmed_at DATETIME NULL,
    final_score DECIMAL(4,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_theses_batch_student UNIQUE (batch_id, student_id),
    CONSTRAINT fk_theses_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_theses_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_theses_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_theses_group FOREIGN KEY (group_id) REFERENCES thesis_groups(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_theses_supervisor FOREIGN KEY (supervisor_id) REFERENCES lecturers(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_theses_reviewer FOREIGN KEY (reviewer_id) REFERENCES lecturers(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_theses_council FOREIGN KEY (council_id) REFERENCES councils(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_theses_topic (topic_id),
    INDEX idx_theses_supervisor (supervisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kế hoạch làm việc / mốc công việc giữa sinh viên và giảng viên hướng dẫn.
CREATE TABLE IF NOT EXISTS thesis_milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thesis_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    due_date DATE NULL,
    status ENUM('todo', 'done') NOT NULL DEFAULT 'todo',
    created_by BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_thesis_milestones_thesis FOREIGN KEY (thesis_id) REFERENCES theses(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nhật ký gặp mặt / lịch hẹn hướng dẫn; giảng viên xác nhận buổi đã diễn ra.
CREATE TABLE IF NOT EXISTS thesis_meetings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thesis_id BIGINT UNSIGNED NOT NULL,
    meeting_at DATETIME NOT NULL,
    location VARCHAR(200) NULL,
    agenda TEXT NULL,
    notes TEXT NULL,
    status ENUM('scheduled', 'held', 'cancelled') NOT NULL DEFAULT 'scheduled',
    confirmed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_thesis_meetings_thesis FOREIGN KEY (thesis_id) REFERENCES theses(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_thesis_meetings_time (meeting_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bài nộp theo giai đoạn; mỗi lần nộp lại là một phiên bản mới, đánh dấu nộp trễ theo mốc của đợt.
CREATE TABLE IF NOT EXISTS thesis_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thesis_id BIGINT UNSIGNED NOT NULL,
    stage VARCHAR(30) NOT NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    note TEXT NULL,
    is_late TINYINT(1) NOT NULL DEFAULT 0,
    -- Độ trùng nội dung cao nhất (%) với bài nộp của đồ án khác trong hệ thống, và bài nộp đó.
    similarity DECIMAL(5,2) NULL,
    similar_submission_id BIGINT UNSIGNED NULL,
    status ENUM('submitted', 'reviewed', 'revision') NOT NULL DEFAULT 'submitted',
    submitted_by BIGINT UNSIGNED NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_thesis_submissions_thesis FOREIGN KEY (thesis_id) REFERENCES theses(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_thesis_submissions_stage (thesis_id, stage, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Góp ý trực tiếp trên từng phiên bản bài nộp.
CREATE TABLE IF NOT EXISTS submission_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_comments_submission FOREIGN KEY (submission_id) REFERENCES thesis_submissions(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_submission_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 22. RUBRIC (thư viện tiêu chí chấm, dùng lại giữa các đợt) và phiếu chấm đồ án
-- ============================================================
CREATE TABLE IF NOT EXISTS rubrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    applies_to ENUM('supervisor', 'reviewer', 'council', 'any', 'internship_company', 'internship_lecturer') NOT NULL DEFAULT 'any',
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rubric_criteria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rubric_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    max_score DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    levels TEXT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_rubric_criteria_rubric FOREIGN KEY (rubric_id) REFERENCES rubrics(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thesis_scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thesis_id BIGINT UNSIGNED NOT NULL,
    evaluator_user_id BIGINT UNSIGNED NOT NULL,
    evaluator_role ENUM('supervisor', 'reviewer', 'council') NOT NULL,
    rubric_id BIGINT UNSIGNED NULL,
    total DECIMAL(4,2) NULL,
    comments TEXT NULL,
    status ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_thesis_scores UNIQUE (thesis_id, evaluator_user_id, evaluator_role),
    CONSTRAINT fk_thesis_scores_thesis FOREIGN KEY (thesis_id) REFERENCES theses(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_thesis_scores_user FOREIGN KEY (evaluator_user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_thesis_scores_rubric FOREIGN KEY (rubric_id) REFERENCES rubrics(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thesis_score_items (
    score_id BIGINT UNSIGNED NOT NULL,
    criterion_id BIGINT UNSIGNED NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (score_id, criterion_id),
    CONSTRAINT fk_thesis_score_items_score FOREIGN KEY (score_id) REFERENCES thesis_scores(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_thesis_score_items_criterion FOREIGN KEY (criterion_id) REFERENCES rubric_criteria(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Điểm từng tiêu chí khi đánh giá thực tập theo rubric (evaluations.rubric_id).
CREATE TABLE IF NOT EXISTS evaluation_items (
    evaluation_id BIGINT UNSIGNED NOT NULL,
    criterion_id BIGINT UNSIGNED NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (evaluation_id, criterion_id),
    CONSTRAINT fk_evaluation_items_evaluation FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_evaluation_items_criterion FOREIGN KEY (criterion_id) REFERENCES rubric_criteria(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phúc khảo / yêu cầu đánh giá lại điểm đồ án.
CREATE TABLE IF NOT EXISTS regrade_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thesis_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    response TEXT NULL,
    handled_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    handled_at DATETIME NULL,
    CONSTRAINT fk_regrade_thesis FOREIGN KEY (thesis_id) REFERENCES theses(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_regrade_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 23. THÔNG BÁO CHUNG theo đợt / lớp / vai trò
-- ============================================================
CREATE TABLE IF NOT EXISTS announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    audience ENUM('all', 'student', 'lecturer', 'company') NOT NULL DEFAULT 'all',
    batch_id BIGINT UNSIGNED NULL,
    class_name VARCHAR(100) NULL,
    -- Gửi theo nhóm (nhóm đồ án, sinh viên một giảng viên hướng dẫn, thực tập sinh của đơn vị): id người nhận, cách nhau dấu phẩy.
    recipients MEDIUMTEXT NULL,
    group_label VARCHAR(200) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcements_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_announcements_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 24. THỰC TẬP MỞ RỘNG: tự tìm nơi thực tập, giấy tờ, chuyên cần, sinh viên đánh giá đơn vị
-- ============================================================
CREATE TABLE IF NOT EXISTS self_placements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    company_name VARCHAR(200) NOT NULL,
    tax_code VARCHAR(50) NULL,
    address VARCHAR(255) NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    contact_phone VARCHAR(20) NULL,
    contact_email VARCHAR(255) NULL,
    position_title VARCHAR(200) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    review_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    internship_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_self_placements_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_self_placements_internship FOREIGN KEY (internship_id) REFERENCES internships(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internship_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    doc_type ENUM('gioi_thieu', 'tiep_nhan', 'cam_ket', 'xac_nhan', 'khac') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_internship_documents_internship FOREIGN KEY (internship_id) REFERENCES internships(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    att_date DATE NOT NULL,
    status ENUM('present', 'late', 'absent', 'excused') NOT NULL DEFAULT 'present',
    note VARCHAR(255) NULL,
    recorded_by BIGINT UNSIGNED NULL,
    CONSTRAINT uq_attendance_day UNIQUE (internship_id, att_date),
    CONSTRAINT fk_attendance_internship FOREIGN KEY (internship_id) REFERENCES internships(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    mentoring_rating TINYINT UNSIGNED NOT NULL,
    would_recommend TINYINT(1) NOT NULL DEFAULT 1,
    got_offer TINYINT(1) NOT NULL DEFAULT 0,
    comments TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_company_reviews_internship UNIQUE (internship_id),
    CONSTRAINT fk_company_reviews_internship FOREIGN KEY (internship_id) REFERENCES internships(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT ck_company_reviews_rating CHECK (rating BETWEEN 1 AND 5 AND mentoring_rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 25. NHIỀU VAI TRÒ VÀ NGƯỜI HƯỚNG DẪN TẠI DOANH NGHIỆP
-- ============================================================
-- Vai trò bổ sung ngoài users.role (ví dụ giảng viên kiêm quản trị khoa); người dùng chuyển vai trò ở thanh bên.
CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('lecturer', 'admin') NOT NULL,
    PRIMARY KEY (user_id, role),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tài khoản người hướng dẫn tại doanh nghiệp (users.role = 'company'): chỉ thấy thực tập sinh được giao (internships.mentor_user_id).
CREATE TABLE IF NOT EXISTS company_mentors (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    job_title VARCHAR(150) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_company_mentors_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_company_mentors_company FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 26. HỌC PHẦN ĐÃ ĐẠT, HẠN MỨC THEO ĐỢT, CHẤM THEO GIAI ĐOẠN
-- ============================================================
-- Học phần sinh viên đã đạt (nhập từ hệ thống đào tạo), dùng kiểm tra học phần tiên quyết của đợt.
CREATE TABLE IF NOT EXISTS student_courses (
    student_id BIGINT UNSIGNED NOT NULL,
    course_code VARCHAR(30) NOT NULL,
    course_name VARCHAR(200) NULL,
    PRIMARY KEY (student_id, course_code),
    CONSTRAINT fk_student_courses_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hạn mức hướng dẫn riêng của giảng viên trong một đợt (không có thì dùng lecturers.max_students).
CREATE TABLE IF NOT EXISTS batch_lecturer_quotas (
    batch_id BIGINT UNSIGNED NOT NULL,
    lecturer_id BIGINT UNSIGNED NOT NULL,
    max_students TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (batch_id, lecturer_id),
    CONSTRAINT fk_batch_quotas_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_batch_quotas_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Điểm giảng viên hướng dẫn chấm cho từng giai đoạn nộp bài (báo cáo tiến độ, mốc tùy chọn...).
CREATE TABLE IF NOT EXISTS thesis_stage_scores (
    thesis_id BIGINT UNSIGNED NOT NULL,
    stage VARCHAR(30) NOT NULL,
    score DECIMAL(4,2) NOT NULL,
    comments TEXT NULL,
    scored_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (thesis_id, stage),
    CONSTRAINT fk_stage_scores_thesis FOREIGN KEY (thesis_id) REFERENCES theses(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dấu vân tay nội dung (crc32 các cụm 5 từ) của bài nộp, để so trùng lặp giữa các đồ án.
CREATE TABLE IF NOT EXISTS submission_fingerprints (
    submission_id BIGINT UNSIGNED PRIMARY KEY,
    word_count INT UNSIGNED NOT NULL DEFAULT 0,
    hashes MEDIUMTEXT NULL,
    CONSTRAINT fk_fingerprints_submission FOREIGN KEY (submission_id) REFERENCES thesis_submissions(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 27. THỰC TẬP: NGUYỆN VỌNG PHÂN BỔ, ĐỔI / RÚT ĐƠN VỊ, ĐỀ CƯƠNG THEO TUẦN
-- ============================================================
CREATE TABLE IF NOT EXISTS internship_preferences (
    batch_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    rank_no TINYINT UNSIGNED NOT NULL,
    position_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (batch_id, student_id, rank_no),
    CONSTRAINT fk_internship_prefs_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_internship_prefs_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_internship_prefs_position FOREIGN KEY (position_id) REFERENCES positions(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internship_change_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    request_type ENUM('withdraw', 'change') NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    response TEXT NULL,
    handled_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    handled_at DATETIME NULL,
    CONSTRAINT fk_change_requests_internship FOREIGN KEY (internship_id) REFERENCES internships(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_change_requests_student FOREIGN KEY (student_id) REFERENCES students(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internship_weekly_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internship_id BIGINT UNSIGNED NOT NULL,
    week_no TINYINT UNSIGNED NOT NULL,
    goals TEXT NOT NULL,
    result TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_weekly_plans UNIQUE (internship_id, week_no),
    CONSTRAINT fk_weekly_plans_internship FOREIGN KEY (internship_id) REFERENCES internships(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 28. BÁO CÁO THỰC TẬP THEO PHIÊN BẢN, BÌNH LUẬN BÁO CÁO / NHẬT KÝ
-- ============================================================
CREATE TABLE IF NOT EXISTS report_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    version SMALLINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NULL,
    file_path VARCHAR(255) NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_report_versions UNIQUE (report_id, version),
    CONSTRAINT fk_report_versions_report FOREIGN KEY (report_id) REFERENCES reports(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('report', 'diary') NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_item_comments_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- END
-- ============================================================
