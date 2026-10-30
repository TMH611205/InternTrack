<?php

declare(strict_types=1);

// Chuẩn hóa dữ liệu cũ theo Trường Kỹ thuật và Công nghệ (chạy SAU scripts/migrate.php, chạy lại nhiều lần vẫn an toàn):
//   C:\xampp\php\php.exe scripts\normalize_school.php
// Đổi mã / ngành của các tài khoản mẫu, xóa ngành ngoài trường, rồi liệt kê hồ sơ còn sai chuẩn để xử lý tay (không tự sửa dữ liệu thật).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$sample = student_code_info('235748020110017');
if ($sample !== ['year' => 2023, 'cohort' => 64, 'major_code' => '7480201'] || student_code_info('UX22-0418') !== null) {
    exit("student_code_info sai\n");
}

$connection = database();
// Chưa chạy migrate thì danh mục ngành của trường chưa có: nếu tiếp tục sẽ xóa sạch bảng majors.
$catalog = $connection->prepare('SELECT COUNT(*) FROM majors WHERE faculty = ?');
$catalog->execute([SCHOOL_FACULTY]);
if ((int) $catalog->fetchColumn() === 0) {
    exit("Chưa có danh mục ngành của trường, hãy chạy scripts/migrate.php trước.\n");
}
$connection->beginTransaction();

// Mã cũ của tài khoản mẫu => [mã mới, ngành, lớp].
$demoStudents = [
    'UX22-0418' => ['225748020110418', 'Công nghệ thông tin', '63K-CNTT1'],
    'UX22-0431' => ['225748020110431', 'Công nghệ thông tin', '63K-CNTT2'],
    'FE22-0132' => ['225748010310132', 'Kỹ thuật phần mềm', '63K-KTPM1'],
    'UX22-0424' => ['225748010110424', 'Khoa học máy tính', '63K-KHMT1'],
    'CT22-0079' => ['225751030210079', 'Công nghệ kỹ thuật điện tử - viễn thông', '63K-DTVT1'],
];
$updateStudent = $connection->prepare('UPDATE students SET student_code = ?, major = ?, class_name = ?, faculty = ? WHERE student_code = ?');
foreach ($demoStudents as $oldCode => [$newCode, $major, $class]) {
    $updateStudent->execute([$newCode, $major, $class, SCHOOL_FACULTY, $oldCode]);
}
$updateLecturer = $connection->prepare('UPDATE lecturers SET lecturer_code = ? WHERE lecturer_code = ?');
foreach (['GV-UX-001' => 'GV000101', 'GV-SE-014' => 'GV000102'] as $oldCode => $newCode) {
    $updateLecturer->execute([$newCode, $oldCode]);
}
$connection->prepare('UPDATE lecturers SET faculty = ? WHERE faculty IS NULL OR faculty = \'\'')->execute([SCHOOL_FACULTY]);

$schoolMajors = 'SELECT id FROM majors WHERE faculty = ' . $connection->quote(SCHOOL_FACULTY);
$linked = (int) $connection->query('SELECT COUNT(*) FROM batches WHERE major_id IS NOT NULL AND major_id NOT IN (' . $schoolMajors . ')')->fetchColumn();
if ($linked > 0) {
    // Xóa ngành sẽ làm major_id về NULL, đợt đó thành "mọi ngành": để quản trị đổi ngành của đợt trước.
    $connection->rollBack();
    exit($linked . " đợt đang gắn với ngành ngoài trường, hãy đổi ngành của đợt rồi chạy lại.\n");
}
$removed = $connection->exec('DELETE FROM majors WHERE id NOT IN (SELECT id FROM (' . $schoolMajors . ') AS keep_majors)');
$connection->commit();
echo 'Đã xóa ' . $removed . " ngành ngoài trường.\n";

foreach ($connection->query('SELECT s.student_code, s.major, u.email FROM students s JOIN users u ON u.id = s.user_id') as $row) {
    $info = student_code_info((string) $row['student_code']);
    $known = $info && (int) $connection->query('SELECT COUNT(*) FROM majors WHERE code = ' . $connection->quote($info['major_code']))->fetchColumn() > 0;
    if (!$known) {
        echo 'Mã sinh viên chưa chuẩn: ' . $row['student_code'] . ' · ' . $row['email'] . "\n";
    }
}
foreach ($connection->query('SELECT l.lecturer_code, u.email FROM lecturers l JOIN users u ON u.id = l.user_id') as $row) {
    if (preg_match(LECTURER_CODE_PATTERN, (string) $row['lecturer_code']) !== 1) {
        echo 'Mã giảng viên chưa chuẩn: ' . $row['lecturer_code'] . ' · ' . $row['email'] . "\n";
    }
}
