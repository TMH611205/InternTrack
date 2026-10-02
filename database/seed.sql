SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
START TRANSACTION;

-- Optional local fixture accounts and sample records. Never import this file in production.
-- Company names and addresses below are illustrative; replace them with verified business data before publication.
UPDATE users
SET password_hash = '$2y$10$e0Iu63wx52DpsU770zzmAeoQHrtjW9sr.WV49yfgFWusmKft3YkzG',
    full_name = 'Quản trị viên'
WHERE username = 'admin'
  AND password_hash LIKE '%REPLACE_WITH_REAL_PASSWORD_HASH';

INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES
('student', 'student@interntrack.local', '$2y$10$Nw6sMcXmOumnmNWpY3SRrOZXF5xC68dMx8IwgutssyjRscrDbQzpq', 'Minh Anh Nguyễn', '+84903248681', 'student', 'active'),
('han', 'han@interntrack.local', '$2y$10$Nw6sMcXmOumnmNWpY3SRrOZXF5xC68dMx8IwgutssyjRscrDbQzpq', 'Trần Gia Hân', '+84903000101', 'student', 'active'),
('bao', 'bao@interntrack.local', '$2y$10$Nw6sMcXmOumnmNWpY3SRrOZXF5xC68dMx8IwgutssyjRscrDbQzpq', 'Phạm Quốc Bảo', '+84903000102', 'student', 'active'),
('vy', 'vy@interntrack.local', '$2y$10$Nw6sMcXmOumnmNWpY3SRrOZXF5xC68dMx8IwgutssyjRscrDbQzpq', 'Lê Ngọc Vy', '+84903000103', 'student', 'active'),
('thao', 'thao@interntrack.local', '$2y$10$Nw6sMcXmOumnmNWpY3SRrOZXF5xC68dMx8IwgutssyjRscrDbQzpq', 'Thảo Nguyên', '+84903000104', 'student', 'active'),
('company', 'company@interntrack.local', '$2y$10$EBP/QLHrLfQfvXg5qZ/WqOZwM1FI59uiZrVra5OrFuuIQFyifmS66', 'Linh Trần', '+842838220188', 'company', 'active'),
('moclab', 'moclab@interntrack.local', '$2y$10$EBP/QLHrLfQfvXg5qZ/WqOZwM1FI59uiZrVra5OrFuuIQFyifmS66', 'Mai Phạm', '+842838220189', 'company', 'active'),
('maycreative', 'maycreative@interntrack.local', '$2y$10$EBP/QLHrLfQfvXg5qZ/WqOZwM1FI59uiZrVra5OrFuuIQFyifmS66', 'Tuấn Lê', '+842838220190', 'company', 'active'),
('lahouse', 'lahouse@interntrack.local', '$2y$10$EBP/QLHrLfQfvXg5qZ/WqOZwM1FI59uiZrVra5OrFuuIQFyifmS66', 'Hà Vũ', '+842838220191', 'company', 'active'),
('lecturer', 'lecturer@interntrack.local', '$2y$10$0ZQsFDPnrfCvJmPdOQypwOz1Cs7T.wTci2ZsZWD35MpuJIixeekSa', 'Nguyễn Hà', '+84909000201', 'lecturer', 'active'),
('lecturer2', 'lecturer2@interntrack.local', '$2y$10$0ZQsFDPnrfCvJmPdOQypwOz1Cs7T.wTci2ZsZWD35MpuJIixeekSa', 'Trần Duy', '+84909000202', 'lecturer', 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), status = 'active';

INSERT INTO students (user_id, student_code, gender, address, major, class_name, faculty, university, bio)
SELECT id, 'UX22-0418', 'female', 'TP. Vinh, Nghệ An', 'Thiết kế trải nghiệm người dùng', 'UXD22A', 'Mỹ thuật công nghiệp', 'Đại học Vinh', 'Quan tâm đến sản phẩm số dễ hiểu và tôn trọng thời gian người dùng.' FROM users WHERE username = 'student'
ON DUPLICATE KEY UPDATE major = VALUES(major), class_name = VALUES(class_name);
INSERT INTO students (user_id, student_code, gender, major, class_name, faculty, university)
SELECT id, 'UX22-0431', 'female', 'Thiết kế trải nghiệm người dùng', 'UXD22C', 'Mỹ thuật công nghiệp', 'Đại học Vinh' FROM users WHERE username = 'han'
ON DUPLICATE KEY UPDATE major = VALUES(major);
INSERT INTO students (user_id, student_code, gender, major, class_name, faculty, university)
SELECT id, 'FE22-0132', 'male', 'Kỹ thuật phần mềm', 'SE22A', 'Công nghệ thông tin', 'Đại học Vinh' FROM users WHERE username = 'bao'
ON DUPLICATE KEY UPDATE major = VALUES(major);
INSERT INTO students (user_id, student_code, gender, major, class_name, faculty, university)
SELECT id, 'UX22-0424', 'female', 'Thiết kế trải nghiệm người dùng', 'UXD22B', 'Mỹ thuật công nghiệp', 'Đại học Vinh' FROM users WHERE username = 'vy'
ON DUPLICATE KEY UPDATE major = VALUES(major);
INSERT INTO students (user_id, student_code, gender, major, class_name, faculty, university)
SELECT id, 'CT22-0079', 'female', 'Truyền thông số', 'DMC22A', 'Truyền thông', 'Đại học Vinh' FROM users WHERE username = 'thao'
ON DUPLICATE KEY UPDATE major = VALUES(major);

INSERT INTO companies (user_id, company_code, company_name, website, email, phone, address, description, status)
SELECT id, 'NS-2021-018', 'Sông Lam Digital', 'https://northstar.studio', 'people@northstar.studio', '+842383220188', 'Đường Lê Nin, TP. Vinh, Nghệ An (địa chỉ mẫu)', 'Doanh nghiệp minh họa tại Vinh, đồng hành cùng sinh viên trong các dự án sản phẩm số.', 'active' FROM users WHERE username = 'company'
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name), address = VALUES(address), description = VALUES(description), status = 'active';
INSERT INTO companies (user_id, company_code, company_name, website, email, phone, address, description, status)
SELECT id, 'ML-2020-042', 'Nghệ An UX Lab', 'https://moclab.example', 'hello@moclab.example', '+842383220189', 'Đường Nguyễn Thị Minh Khai, TP. Vinh, Nghệ An (địa chỉ mẫu)', 'Doanh nghiệp minh họa tại Vinh, nghiên cứu trải nghiệm cho sản phẩm giáo dục và cộng đồng.', 'active' FROM users WHERE username = 'moclab'
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name), address = VALUES(address), description = VALUES(description), status = 'active';
INSERT INTO companies (user_id, company_code, company_name, website, email, phone, address, description, status)
SELECT id, 'MC-2024-011', 'Vinh Creative Studio', 'https://maycreative.example', 'careers@maycreative.example', '+842383220190', 'Đường Trường Thi, TP. Vinh, Nghệ An (địa chỉ mẫu)', 'Doanh nghiệp minh họa tại Vinh, phát triển nhận diện thương hiệu và sản phẩm số.', 'active' FROM users WHERE username = 'maycreative'
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name), address = VALUES(address), description = VALUES(description), status = 'active';
INSERT INTO companies (user_id, company_code, company_name, website, email, phone, address, description, status)
SELECT id, 'LH-2023-028', 'Cửa Lò Media', 'https://lahouse.example', 'team@lahouse.example', '+842383220191', 'Đường Nguyễn Văn Cừ, TP. Vinh, Nghệ An (địa chỉ mẫu)', 'Doanh nghiệp minh họa tại Nghệ An, xây dựng nội dung và giải pháp số cho bán lẻ.', 'active' FROM users WHERE username = 'lahouse'
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name), address = VALUES(address), description = VALUES(description), status = 'active';

INSERT INTO lecturers (user_id, lecturer_code, department, academic_title, specialization)
SELECT id, 'GV-UX-001', 'Khoa Công nghệ thông tin', 'Thạc sĩ', 'Thiết kế tương tác và trải nghiệm người dùng' FROM users WHERE username = 'lecturer'
ON DUPLICATE KEY UPDATE department = VALUES(department);
INSERT INTO lecturers (user_id, lecturer_code, department, academic_title, specialization)
SELECT id, 'GV-SE-014', 'Khoa Công nghệ thông tin', 'Thạc sĩ', 'Phát triển phần mềm' FROM users WHERE username = 'lecturer2'
ON DUPLICATE KEY UPDATE department = VALUES(department);

INSERT INTO positions (company_id, title, description, requirements, benefits, location, quantity, deadline, status)
SELECT c.id, seed.title, seed.description, seed.requirements, seed.benefits, seed.location, seed.quantity, seed.deadline, 'open'
FROM companies c
JOIN (
    SELECT 'NS-2021-018' AS company_code, 'Product Design Intern' AS title, 'Thiết kế luồng onboarding cho nền tảng quản lý bán lẻ và kiểm chứng giải pháp với người dùng.' AS description, 'Sinh viên năm 3–4 ngành thiết kế hoặc công nghệ; có portfolio và tinh thần chủ động.' AS requirements, 'Có mentor đồng hành, hỗ trợ chi phí thực tập và cơ hội tham gia sản phẩm thật.' AS benefits, 'Hybrid · TP. Vinh, Nghệ An' AS location, 2 AS quantity, '2026-10-15' AS deadline
    UNION ALL SELECT 'ML-2020-042', 'UX Research Intern', 'Tham gia nghiên cứu hành vi và kiểm thử sản phẩm giáo dục.', 'Sinh viên ngành UX, tâm lý học hoặc nghiên cứu; biết tổng hợp insight.', 'Mentor nghiên cứu, ngân sách phỏng vấn người dùng.', 'TP. Vinh, Nghệ An', 1, '2026-10-18'
    UNION ALL SELECT 'NS-2021-018', 'Frontend Developer Intern', 'Xây dựng và kiểm thử giao diện web cùng nhóm sản phẩm.', 'Nắm HTML, CSS, JavaScript cơ bản; biết Git là lợi thế.', 'Code review hằng tuần, lịch làm việc linh hoạt.', 'TP. Vinh · Hybrid', 1, '2026-10-25'
    UNION ALL SELECT 'LH-2023-028', 'Content Intern', 'Lên kế hoạch và viết nội dung cho sản phẩm bán lẻ.', 'Có khả năng viết rõ ràng, biết tìm hiểu người dùng.', 'Hướng dẫn nội dung và portfolio sau kỳ thực tập.', 'Từ xa · Doanh nghiệp tại TP. Vinh', 1, '2026-11-01'
    UNION ALL SELECT 'MC-2024-011', 'Visual Designer Intern', 'Phát triển hệ thống hình ảnh cho thương hiệu và chiến dịch số.', 'Sinh viên thiết kế, có portfolio và kỹ năng Figma.', 'Tham gia dự án thực tế cùng đội ngũ sáng tạo.', 'TP. Vinh, Nghệ An', 1, '2026-11-05'
) AS seed ON seed.company_code = c.company_code
WHERE c.status = 'active'
  AND NOT EXISTS (SELECT 1 FROM positions p WHERE p.company_id = c.id AND p.title = seed.title);

UPDATE positions p
JOIN companies c ON c.id = p.company_id
SET p.location = CASE
    WHEN c.company_code = 'NS-2021-018' AND p.title = 'Product Design Intern' THEN 'Hybrid · TP. Vinh, Nghệ An'
    WHEN c.company_code = 'ML-2020-042' AND p.title = 'UX Research Intern' THEN 'TP. Vinh, Nghệ An'
    WHEN c.company_code = 'NS-2021-018' AND p.title = 'Frontend Developer Intern' THEN 'TP. Vinh · Hybrid'
    WHEN c.company_code = 'LH-2023-028' AND p.title = 'Content Intern' THEN 'Từ xa · Doanh nghiệp tại TP. Vinh'
    WHEN c.company_code = 'MC-2024-011' AND p.title = 'Visual Designer Intern' THEN 'TP. Vinh, Nghệ An'
    ELSE p.location
END
WHERE (c.company_code = 'NS-2021-018' AND p.title IN ('Product Design Intern', 'Frontend Developer Intern'))
   OR (c.company_code = 'ML-2020-042' AND p.title = 'UX Research Intern')
   OR (c.company_code = 'LH-2023-028' AND p.title = 'Content Intern')
   OR (c.company_code = 'MC-2024-011' AND p.title = 'Visual Designer Intern');

UPDATE students s
JOIN users u ON u.id = s.user_id
SET s.address = 'TP. Vinh, Nghệ An', s.university = 'Đại học Vinh'
WHERE u.username IN ('student', 'han', 'bao', 'vy', 'thao');

INSERT INTO internships (student_id, company_id, position_id, lecturer_id, start_date, end_date, status, description)
SELECT s.id, c.id, p.id, l.id, '2026-09-01', '2026-11-25', 'active', 'Thiết kế trải nghiệm onboarding và kiểm chứng giải pháp qua thử nghiệm người dùng.'
FROM students s
JOIN users su ON su.id = s.user_id AND su.username = 'student'
JOIN companies c ON c.company_code = 'NS-2021-018'
JOIN positions p ON p.company_id = c.id AND p.title = 'Product Design Intern'
JOIN lecturers l ON l.lecturer_code = 'GV-UX-001'
WHERE NOT EXISTS (SELECT 1 FROM internships i WHERE i.student_id = s.id AND i.position_id = p.id AND i.start_date = '2026-09-01');
INSERT INTO internships (student_id, company_id, position_id, lecturer_id, start_date, end_date, actual_end_date, status, description, final_score)
SELECT s.id, c.id, p.id, l.id, '2026-01-06', '2026-03-28', '2026-03-27', 'completed', 'Hỗ trợ nghiên cứu và thiết kế trải nghiệm học tập.', 89.00
FROM students s
JOIN users su ON su.id = s.user_id AND su.username = 'bao'
JOIN companies c ON c.company_code = 'ML-2020-042'
JOIN positions p ON p.company_id = c.id AND p.title = 'UX Research Intern'
JOIN lecturers l ON l.lecturer_code = 'GV-SE-014'
WHERE NOT EXISTS (SELECT 1 FROM internships i WHERE i.student_id = s.id AND i.position_id = p.id AND i.start_date = '2026-01-06');

INSERT INTO applications (student_id, position_id, status, applied_at, reviewed_at, reviewed_by, note)
SELECT s.id, p.id, seed.status, seed.applied_at, seed.reviewed_at, reviewer.id, seed.note
FROM (
    SELECT 'student' AS username, 'Product Design Intern' AS title, 'accepted' AS status, '2026-08-18 09:00:00' AS applied_at, '2026-08-20 10:00:00' AS reviewed_at, 'company' AS reviewer, 'Hồ sơ phù hợp với nhóm Product Experience.' AS note
    UNION ALL SELECT 'student', 'UX Research Intern', 'pending', '2026-09-21 10:00:00', NULL, NULL, NULL
    UNION ALL SELECT 'han', 'Frontend Developer Intern', 'reviewing', '2026-09-28 14:30:00', NULL, NULL, NULL
    UNION ALL SELECT 'vy', 'Visual Designer Intern', 'rejected', '2026-09-22 08:00:00', '2026-09-25 09:00:00', 'maycreative', 'Đợt này ưu tiên ứng viên có kinh nghiệm motion design.'
) AS seed
JOIN users su ON su.username = seed.username
JOIN students s ON s.user_id = su.id
JOIN positions p ON p.title = seed.title
LEFT JOIN users reviewer ON reviewer.username = seed.reviewer
WHERE NOT EXISTS (SELECT 1 FROM applications a WHERE a.student_id = s.id AND a.position_id = p.id);

INSERT INTO tasks (internship_id, title, description, assigned_to, created_by, priority, status, start_date, due_date)
SELECT i.id, seed.title, seed.description, i.student_id, creator.id, seed.priority, seed.status, seed.start_date, seed.due_date
FROM internships i
JOIN students s ON s.id = i.student_id
JOIN users student_user ON student_user.id = s.user_id AND student_user.username = 'student'
JOIN users creator ON creator.username = 'company'
JOIN (
    SELECT 'Hoàn thiện luồng onboarding' AS title, 'Cập nhật prototype và ghi lại các quyết định thiết kế.' AS description, 'high' AS priority, 'in_progress' AS status, '2026-09-28' AS start_date, '2026-10-02' AS due_date
    UNION ALL SELECT 'Tổng hợp insight phỏng vấn', 'Gom nhóm phát hiện từ các buổi phỏng vấn và đề xuất hướng xử lý.', 'medium', 'todo', '2026-10-01', '2026-10-05'
    UNION ALL SELECT 'Audit trải nghiệm mobile', 'Kiểm tra các trạng thái chính trên kích thước màn hình nhỏ.', 'low', 'completed', '2026-09-21', '2026-09-25'
) AS seed ON TRUE
WHERE i.status = 'active'
  AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.internship_id = i.id AND t.title = seed.title);

INSERT INTO diaries (internship_id, student_id, diary_date, title, content, hours_worked, status, reviewed_by, reviewed_at, lecturer_feedback)
SELECT i.id, i.student_id, seed.diary_date, seed.title, seed.content, seed.hours, seed.status, l.id, seed.reviewed_at, seed.feedback
FROM internships i
JOIN students s ON s.id = i.student_id
JOIN users su ON su.id = s.user_id AND su.username = 'student'
JOIN lecturers l ON l.lecturer_code = 'GV-UX-001'
JOIN (
    SELECT '2026-09-28' AS diary_date, 'Rà soát prototype onboarding' AS title, 'Kiểm tra luồng tạo tài khoản trên mobile, ghi nhận ba điểm gây nhầm lẫn và cập nhật prototype.' AS content, 7.5 AS hours, 'submitted' AS status, NULL AS reviewed_at, NULL AS feedback
    UNION ALL SELECT '2026-09-27', 'Phỏng vấn người dùng nội bộ', 'Thực hiện hai buổi phỏng vấn với nhóm hỗ trợ khách hàng và tổng hợp insight.', 8.0, 'approved', '2026-09-28 09:30:00', 'Câu hỏi rõ ràng, ghi lại thêm trích dẫn người dùng ở lần sau.'
    UNION ALL SELECT '2026-09-26', 'Workshop cùng nhóm sản phẩm', 'Đồng xây dựng journey map và thống nhất phạm vi thử nghiệm tiếp theo.', 8.0, 'approved', '2026-09-27 10:00:00', 'Tốt, tiếp tục liên kết insight với quyết định thiết kế.'
) AS seed ON TRUE
WHERE i.status = 'active'
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), status = VALUES(status);

INSERT INTO reports (internship_id, student_id, report_type, title, content, status, submitted_at, reviewed_by, reviewed_at, feedback)
SELECT i.id, i.student_id, seed.report_type, seed.title, seed.content, seed.status, seed.submitted_at, l.id, seed.reviewed_at, seed.feedback
FROM internships i
JOIN students s ON s.id = i.student_id
JOIN users su ON su.id = s.user_id AND su.username = 'student'
JOIN lecturers l ON l.lecturer_code = 'GV-UX-001'
JOIN (
    SELECT 'proposal' AS report_type, 'Đề cương thực tập Product Design' AS title, 'Mục tiêu: nghiên cứu và cải thiện luồng onboarding cho nền tảng quản lý bán lẻ.' AS content, 'approved' AS status, '2026-09-05 10:00:00' AS submitted_at, '2026-09-08 14:00:00' AS reviewed_at, 'Phạm vi phù hợp, bổ sung tiêu chí đo lường cho thử nghiệm.' AS feedback
    UNION ALL SELECT 'midterm', 'Báo cáo thực tập giữa kỳ', 'Tổng kết quá trình tìm hiểu sản phẩm, nghiên cứu người dùng và prototype giữa kỳ.', 'submitted', '2026-09-29 08:42:00', NULL, NULL
) AS seed ON TRUE
WHERE i.status = 'active'
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), status = VALUES(status);

INSERT INTO evaluations (internship_id, evaluator_user_id, evaluator_type, technical_score, attitude_score, communication_score, discipline_score, overall_score, comments, status, submitted_at)
SELECT i.id, evaluator.id, seed.evaluator_type, seed.technical_score, seed.attitude_score, seed.communication_score, seed.discipline_score, seed.overall_score, seed.comments, 'submitted', '2026-09-20 15:00:00'
FROM internships i
JOIN students s ON s.id = i.student_id
JOIN users student_user ON student_user.id = s.user_id AND student_user.username = 'student'
JOIN (
    SELECT 'company' AS username, 'company' AS evaluator_type, 88.00 AS technical_score, 91.00 AS attitude_score, 82.00 AS communication_score, 85.00 AS discipline_score, 86.00 AS overall_score, 'Tiếp nhận phản hồi nhanh, chủ động kiểm chứng phương án với người dùng.' AS comments
    UNION ALL SELECT 'lecturer', 'lecturer', 86.00, 90.00, 84.00, 88.00, 87.00, 'Tiến độ ổn định, cần trình bày rõ hơn cách insight dẫn đến quyết định thiết kế.'
) AS seed ON TRUE
JOIN users evaluator ON evaluator.username = seed.username
WHERE i.status = 'active'
ON DUPLICATE KEY UPDATE comments = VALUES(comments), overall_score = VALUES(overall_score);

INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, note)
SELECT a.id, NULL, a.status, a.reviewed_by, COALESCE(a.note, 'Đơn ứng tuyển được tạo.')
FROM applications a
WHERE NOT EXISTS (SELECT 1 FROM application_status_history h WHERE h.application_id = a.id);

INSERT INTO internship_status_history (internship_id, old_status, new_status, changed_by, note)
SELECT i.id, NULL, i.status, u.id, 'Kỳ thực tập được khởi tạo từ dữ liệu mẫu.'
FROM internships i
JOIN students s ON s.id = i.student_id
JOIN users u ON u.id = s.user_id
WHERE NOT EXISTS (SELECT 1 FROM internship_status_history h WHERE h.internship_id = i.id);

COMMIT;
