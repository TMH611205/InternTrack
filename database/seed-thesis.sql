-- ============================================================
-- Dữ liệu MẪU cho phân hệ đồ án / đợt / rubric (chỉ dùng ở máy phát triển, KHÔNG import lên production).
-- Chạy SAU database/interntrack.sql và database/seed.sql (dựa vào các tài khoản mẫu student, han, bao, vy, thao, lecturer, lecturer2).
-- Chạy lại nhiều lần không nhân bản dữ liệu. Mốc thời gian tính theo thời điểm import để các chức năng đang mở.
-- ============================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
START TRANSACTION;

-- Tài khoản phòng đào tạo demo (vai trò quản trị) để thử các màn hình quản lý: phongdaotao / Daotao@123.
INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES
('phongdaotao', 'phongdaotao@interntrack.local', '$2y$10$S6Q8E40NL6y0BT9RuAOMCeAodE7VhWBj14y/SVa.9ui0LtxVJmYnu', 'Phòng Đào tạo (demo)', 'admin', 'active')
ON DUPLICATE KEY UPDATE status = 'active';

INSERT INTO academic_terms (name, school_year, semester, start_date, end_date, is_current) VALUES
('Học kỳ 1 · 2026-2027', '2026-2027', 1, '2026-08-17', '2027-01-10', 1),
('Học kỳ 2 · 2026-2027', '2026-2027', 2, '2027-01-18', '2027-06-20', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Thông tin học tập (đồng bộ từ phòng đào tạo) và kỹ năng của sinh viên mẫu.
UPDATE students s JOIN users u ON u.id = s.user_id SET s.gpa = 3.42, s.credits = 128, s.training_system = 'Chính quy', s.skills = 'Figma, nghiên cứu người dùng, HTML, CSS, JavaScript', s.interests = 'Thiết kế sản phẩm, UX' WHERE u.username = 'student';
UPDATE students s JOIN users u ON u.id = s.user_id SET s.gpa = 3.05, s.credits = 124, s.training_system = 'Chính quy', s.skills = 'Figma, kiểm thử usability, tiếng Anh', s.interests = 'UX research' WHERE u.username = 'han';
UPDATE students s JOIN users u ON u.id = s.user_id SET s.gpa = 3.61, s.credits = 131, s.training_system = 'Chính quy', s.skills = 'PHP, Laravel, MySQL, React, Git', s.interests = 'Phát triển web, hệ thống thông tin' WHERE u.username = 'bao';
UPDATE students s JOIN users u ON u.id = s.user_id SET s.gpa = 2.88, s.credits = 126, s.training_system = 'Chính quy', s.skills = 'Figma, prototyping, React', s.interests = 'Thiết kế giao diện' WHERE u.username = 'vy';
UPDATE students s JOIN users u ON u.id = s.user_id SET s.gpa = 3.20, s.credits = 129, s.training_system = 'Chính quy', s.skills = 'Vi điều khiển, xử lý tín hiệu, phân tích dữ liệu', s.interests = 'Viễn thông, dữ liệu' WHERE u.username = 'thao';

UPDATE lecturers l JOIN users u ON u.id = l.user_id SET l.research_areas = 'Thiết kế trải nghiệm người dùng, tương tác người - máy, nghiên cứu người dùng', l.max_students = 6, l.is_head = 1, l.specialization = 'Tương tác người - máy' WHERE u.username = 'lecturer';
UPDATE lecturers l JOIN users u ON u.id = l.user_id SET l.research_areas = 'Phát triển web, cơ sở dữ liệu, kiến trúc phần mềm, học máy', l.max_students = 6, l.specialization = 'Kỹ thuật phần mềm' WHERE u.username = 'lecturer2';

-- Thư viện rubric.
INSERT INTO rubrics (name, applies_to, description)
SELECT 'Rubric giảng viên hướng dẫn', 'supervisor', 'Đánh giá quá trình thực hiện và chất lượng sản phẩm.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rubrics WHERE name = 'Rubric giảng viên hướng dẫn');
INSERT INTO rubrics (name, applies_to, description)
SELECT 'Rubric phản biện', 'reviewer', 'Đánh giá quyển đồ án.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rubrics WHERE name = 'Rubric phản biện');
INSERT INTO rubrics (name, applies_to, description)
SELECT 'Rubric hội đồng bảo vệ', 'council', 'Đánh giá buổi bảo vệ.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rubrics WHERE name = 'Rubric hội đồng bảo vệ');

INSERT INTO rubric_criteria (rubric_id, name, weight, max_score, levels, sort_order)
SELECT r.id, c.name, c.weight, 10, c.levels, c.sort_order FROM rubrics r
JOIN (SELECT 'Thái độ, chuyên cần, tiến độ' AS name, 2 AS weight, 'Xuất sắc 9-10: đúng mọi mốc; Tốt 7-8: trễ ít; Đạt 5-6: trễ nhiều; Chưa đạt <5' AS levels, 1 AS sort_order
      UNION ALL SELECT 'Nội dung và kết quả đạt được', 4, 'Xuất sắc: vượt mục tiêu; Tốt: đạt đủ mục tiêu; Đạt: đạt phần lớn; Chưa đạt: thiếu nhiều', 2
      UNION ALL SELECT 'Phương pháp, công nghệ', 2, 'Hợp lý, có so sánh lựa chọn', 3
      UNION ALL SELECT 'Trình bày quyển đồ án', 2, 'Đúng thể thức, mạch lạc, trích dẫn đầy đủ', 4) c
WHERE r.name = 'Rubric giảng viên hướng dẫn' AND NOT EXISTS (SELECT 1 FROM rubric_criteria x WHERE x.rubric_id = r.id);

INSERT INTO rubric_criteria (rubric_id, name, weight, max_score, levels, sort_order)
SELECT r.id, c.name, c.weight, 10, c.levels, c.sort_order FROM rubrics r
JOIN (SELECT 'Tính cấp thiết, mục tiêu' AS name, 1 AS weight, 'Rõ ràng, có ý nghĩa thực tiễn' AS levels, 1 AS sort_order
      UNION ALL SELECT 'Nội dung khoa học, kỹ thuật', 4, 'Đúng, đủ sâu, có đánh giá kết quả', 2
      UNION ALL SELECT 'Hình thức trình bày', 2, 'Đúng quy định của trường', 3
      UNION ALL SELECT 'Trả lời câu hỏi phản biện', 3, 'Trả lời đúng, đủ', 4) c
WHERE r.name = 'Rubric phản biện' AND NOT EXISTS (SELECT 1 FROM rubric_criteria x WHERE x.rubric_id = r.id);

INSERT INTO rubric_criteria (rubric_id, name, weight, max_score, levels, sort_order)
SELECT r.id, c.name, c.weight, 10, c.levels, c.sort_order FROM rubrics r
JOIN (SELECT 'Trình bày, slide, thời gian' AS name, 3 AS weight, 'Rõ ràng, đúng 15 phút' AS levels, 1 AS sort_order
      UNION ALL SELECT 'Sản phẩm / demo', 4, 'Chạy ổn định, đúng chức năng', 2
      UNION ALL SELECT 'Trả lời câu hỏi hội đồng', 3, 'Nắm vững vấn đề', 3) c
WHERE r.name = 'Rubric hội đồng bảo vệ' AND NOT EXISTS (SELECT 1 FROM rubric_criteria x WHERE x.rubric_id = r.id);

-- Đợt đồ án đang mở, cho phép nhóm tối đa 2 sinh viên và 3 nguyện vọng.
INSERT INTO batches (term_id, batch_type, name, major_id, training_system, min_credits, min_gpa, prerequisites, allow_student_proposals, max_preferences, group_max_size,
                     supervisor_weight, reviewer_weight, council_weight, pass_score, supervisor_rubric_id, reviewer_rubric_id, council_rubric_id, status, created_by)
SELECT t.id, 'thesis', 'Đồ án tốt nghiệp · HK1 2026-2027', NULL, 'Chính quy', 120, 2.00, 'Hoàn thành học phần thực tập tốt nghiệp.', 1, 3, 2,
       0.40, 0.20, 0.40, 5.00,
       (SELECT id FROM rubrics WHERE name = 'Rubric giảng viên hướng dẫn'), (SELECT id FROM rubrics WHERE name = 'Rubric phản biện'), (SELECT id FROM rubrics WHERE name = 'Rubric hội đồng bảo vệ'),
       'open', (SELECT id FROM users WHERE username = 'admin')
FROM academic_terms t WHERE t.school_year = '2026-2027' AND t.semester = 1
AND NOT EXISTS (SELECT 1 FROM batches WHERE name = 'Đồ án tốt nghiệp · HK1 2026-2027');

INSERT INTO batches (term_id, batch_type, name, training_system, status, created_by)
SELECT t.id, 'internship', 'Thực tập tốt nghiệp · HK1 2026-2027', 'Chính quy', 'open', (SELECT id FROM users WHERE username = 'admin')
FROM academic_terms t WHERE t.school_year = '2026-2027' AND t.semester = 1
AND NOT EXISTS (SELECT 1 FROM batches WHERE name = 'Thực tập tốt nghiệp · HK1 2026-2027');

INSERT INTO batch_milestones (batch_id, kind, title, starts_at, ends_at)
SELECT b.id, m.kind, m.title, DATE_ADD(NOW(), INTERVAL m.start_day DAY), DATE_ADD(NOW(), INTERVAL m.end_day DAY)
FROM batches b JOIN (
    SELECT 'topic_registration' AS kind, 'Giảng viên đăng ký đề tài' AS title, -30 AS start_day, 10 AS end_day
    UNION ALL SELECT 'preference', 'Sinh viên chọn hướng, xếp nguyện vọng, đề xuất đề tài', -7, 14
    UNION ALL SELECT 'matching', 'Ghép cặp và công bố kết quả', -7, 21
    UNION ALL SELECT 'report_1', 'Nộp báo cáo tiến độ 1', -7, 45
    UNION ALL SELECT 'report_2', 'Nộp báo cáo tiến độ 2', 45, 75
    UNION ALL SELECT 'report_final', 'Nộp quyển đồ án', 75, 105
    UNION ALL SELECT 'evaluation', 'Chấm hướng dẫn và phản biện', 105, 115
    UNION ALL SELECT 'defense', 'Bảo vệ trước hội đồng', 115, 125
    UNION ALL SELECT 'close', 'Đóng đợt', 125, 130
) m
WHERE b.name = 'Đồ án tốt nghiệp · HK1 2026-2027'
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Danh sách đủ điều kiện (theo tín chỉ ≥ 120 và GPA ≥ 2.0).
INSERT IGNORE INTO batch_students (batch_id, student_id, eligible, note)
SELECT b.id, s.id, 1, 'Đủ điều kiện' FROM batches b JOIN students s JOIN users u ON u.id = s.user_id
WHERE b.name = 'Đồ án tốt nghiệp · HK1 2026-2027' AND u.username IN ('student', 'han', 'bao', 'vy', 'thao');

-- Đề tài: 5 đề tài đã duyệt và 1 đề tài chờ duyệt.
INSERT INTO topics (batch_id, lecturer_id, title, description, objectives, requirements, technologies, field, max_students, status, reviewed_at)
SELECT b.id, l.id, t.title, t.description, t.objectives, t.requirements, t.technologies, t.field, t.max_students, t.status, IF(t.status = 'approved', NOW(), NULL)
FROM batches b
JOIN (
    SELECT 'lecturer' AS owner, 'Thiết kế trải nghiệm hệ thống đăng ký học phần cho Trường Đại học Vinh' AS title, 'Nghiên cứu hành vi sinh viên khi đăng ký học phần, đề xuất và kiểm thử thiết kế mới.' AS description, 'Giảm thời gian đăng ký, giảm lỗi thao tác.' AS objectives, 'Nghiên cứu người dùng, Figma' AS requirements, 'Figma, Maze' AS technologies, 'Thiết kế trải nghiệm' AS field, 2 AS max_students, 'approved' AS status
    UNION ALL SELECT 'lecturer', 'Nghiên cứu khả năng tiếp cận (accessibility) của cổng thông tin sinh viên', 'Đánh giá theo WCAG 2.2 và đề xuất cải tiến.', 'Bộ tiêu chí và báo cáo đánh giá.', 'HTML, CSS, kiểm thử usability', 'WCAG, axe', 'Thiết kế trải nghiệm', 1, 'approved'
    UNION ALL SELECT 'lecturer2', 'Xây dựng hệ thống quản lý thực tập và đồ án bằng PHP', 'Phân tích, thiết kế và cài đặt hệ thống web cho khoa.', 'Hệ thống chạy được với đủ vai trò.', 'PHP, MySQL, Git', 'PHP, MySQL, JavaScript', 'Phát triển web', 2, 'approved'
    UNION ALL SELECT 'lecturer2', 'Ứng dụng React hiển thị thời khóa biểu và lịch thi', 'Ứng dụng web đọc dữ liệu thời khóa biểu và nhắc lịch.', 'Ứng dụng responsive, có thông báo.', 'React, REST API', 'React, Node.js', 'Phát triển web', 1, 'approved'
    UNION ALL SELECT 'lecturer2', 'Phân tích dữ liệu tương tác mạng xã hội của các khoa', 'Thu thập và phân tích chỉ số tương tác, đề xuất chiến lược nội dung.', 'Dashboard và báo cáo.', 'Phân tích dữ liệu, SEO', 'Python, Power BI', 'Phân tích dữ liệu', 1, 'approved'
    UNION ALL SELECT 'lecturer', 'Thiết kế lại trải nghiệm thư viện số của trường', 'Khảo sát người dùng thư viện số và thiết kế giao diện mới.', 'Prototype đã kiểm thử.', 'Figma', 'Figma', 'Thiết kế trải nghiệm', 1, 'pending'
) t
JOIN users u ON u.username = t.owner JOIN lecturers l ON l.user_id = u.id
WHERE b.name = 'Đồ án tốt nghiệp · HK1 2026-2027'
AND NOT EXISTS (SELECT 1 FROM topics x WHERE x.batch_id = b.id AND x.title = t.title);

-- Nguyện vọng của sinh viên mẫu (để thử thuật toán ghép).
INSERT IGNORE INTO topic_preferences (batch_id, student_id, topic_id, rank_no, created_at)
SELECT b.id, s.id, tp.id, p.rank_no, DATE_SUB(NOW(), INTERVAL p.hours_ago HOUR)
FROM batches b
JOIN (
    SELECT 'student' AS username, 'Thiết kế trải nghiệm hệ thống đăng ký học phần cho Trường Đại học Vinh' AS title, 1 AS rank_no, 50 AS hours_ago
    UNION ALL SELECT 'student', 'Nghiên cứu khả năng tiếp cận (accessibility) của cổng thông tin sinh viên', 2, 50
    UNION ALL SELECT 'han', 'Thiết kế trải nghiệm hệ thống đăng ký học phần cho Trường Đại học Vinh', 1, 30
    UNION ALL SELECT 'han', 'Nghiên cứu khả năng tiếp cận (accessibility) của cổng thông tin sinh viên', 2, 30
    UNION ALL SELECT 'vy', 'Nghiên cứu khả năng tiếp cận (accessibility) của cổng thông tin sinh viên', 1, 20
    UNION ALL SELECT 'vy', 'Thiết kế trải nghiệm hệ thống đăng ký học phần cho Trường Đại học Vinh', 2, 20
    UNION ALL SELECT 'vy', 'Ứng dụng React hiển thị thời khóa biểu và lịch thi', 3, 20
    UNION ALL SELECT 'bao', 'Xây dựng hệ thống quản lý thực tập và đồ án bằng PHP', 1, 40
    UNION ALL SELECT 'bao', 'Ứng dụng React hiển thị thời khóa biểu và lịch thi', 2, 40
    UNION ALL SELECT 'thao', 'Phân tích dữ liệu tương tác mạng xã hội của các khoa', 1, 10
) p
JOIN users u ON u.username = p.username JOIN students s ON s.user_id = u.id
JOIN topics tp ON tp.batch_id = b.id AND tp.title = p.title
WHERE b.name = 'Đồ án tốt nghiệp · HK1 2026-2027';

INSERT INTO announcements (title, body, audience, batch_id, created_by)
SELECT 'Mở đăng ký nguyện vọng đề tài đồ án', 'Sinh viên đủ điều kiện vào mục Đồ án tốt nghiệp để xem danh sách đề tài, đăng ký nhóm (tối đa 2 người) và xếp tối đa 3 nguyện vọng trước hạn.', 'student', b.id, (SELECT id FROM users WHERE username = 'admin')
FROM batches b WHERE b.name = 'Đồ án tốt nghiệp · HK1 2026-2027'
AND NOT EXISTS (SELECT 1 FROM announcements WHERE title = 'Mở đăng ký nguyện vọng đề tài đồ án');

COMMIT;
