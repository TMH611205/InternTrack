<?php

declare(strict_types=1);

// Kiểm thử logic rủi ro (điểm, ghép cặp, phân quyền, OTP, đăng nhập, sao lưu). Chỉ chạy trên CSDL thử, từ chối CSDL thật:
//   mysql -uroot -e "CREATE DATABASE interntrack_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
//   C:\xampp\php\php.exe scripts\migrate.php --database=interntrack_test
//   (không import trực tiếp database/interntrack.sql: tệp có USE interntrack nên sẽ ghi vào CSDL thật)
//   PowerShell: $env:DB_NAME='interntrack_test'; C:\xampp\php\php.exe scripts\test.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ActionController.php';

if (!str_contains((string) app_env('DB_NAME', ''), 'test')) {
    exit("Chỉ chạy khi DB_NAME là CSDL thử (tên chứa \"test\"). Biến môi trường thật phải thắng .env.\n");
}

$failures = 0;
function check(string $name, bool $passed): void
{
    global $failures;
    echo($passed ? 'ok    ' : 'FAIL  ') . $name . "\n";
    $failures += $passed ? 0 : 1;
}
function throws(callable $work): bool
{
    try {
        $work();
        return false;
    } catch (DomainException) {
        return true;
    }
}
function insert(string $table, array $row): int
{
    $connection = database();
    $connection->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
    return (int) $connection->lastInsertId();
}
function login_as(int $userId): void
{
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['active_role']);
}

$tag = bin2hex(random_bytes(4));
$account = static function (string $role, string $name, ?string $scope = null) use ($tag): int {
    return insert('users', ['username' => $name . $tag, 'email' => $name . $tag . '@test.local', 'password_hash' => password_hash('Matkhau@12345', PASSWORD_DEFAULT), 'full_name' => $name, 'role' => $role, 'scope_faculty' => $scope]);
};
$student = static function (string $name) use ($account, $tag): int {
    return insert('students', ['user_id' => $account('student', $name), 'student_code' => substr($name . $tag, 0, 20), 'cv_file' => 'uploads/cv/x.pdf']);
};
$company = static function (string $name) use ($account, $tag): array {
    $userId = $account('company', $name);
    $id = insert('companies', ['user_id' => $userId, 'company_code' => substr((string) hexdec(substr(md5($name . $tag), 0, 10)), 0, 10), 'company_name' => $name, 'address' => 'Vinh', 'status' => 'active']);
    return [$id, $userId, insert('positions', ['company_id' => $id, 'title' => 'Vị trí ' . $name, 'location' => 'Vinh', 'status' => 'open', 'quantity' => 5])];
};
$_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 250);

// --- Mã sinh viên.
check('student_code_info đọc đúng mã 15 số', student_code_info('235748020110017') === ['year' => 2023, 'cohort' => 64, 'major_code' => '7480201']);
check('student_code_info từ chối mã sai', student_code_info('UX22-0418') === null);

// --- Phân quyền theo tài khoản.
login_as($account('admin', 'khoa', 'Khoa CNTT'));
check('quản trị khoa không phục hồi được dữ liệu', throws(static fn() => action_require_account_scope('backup_restore')));
check('quản trị khoa vẫn làm được thao tác thường', !throws(static fn() => action_require_account_scope('announcement_save')));
login_as($account('admin', 'phong'));
check('quản trị toàn trường được phục hồi', !throws(static fn() => action_require_account_scope('backup_restore')));
[$companyA, $ownerA, $positionA] = $company('Alpha');
$mentor = $account('company', 'mentor');
insert('company_mentors', ['user_id' => $mentor, 'company_id' => $companyA]);
login_as($mentor);
check('người hướng dẫn không duyệt được hồ sơ', throws(static fn() => action_require_account_scope('application_review')));
login_as($ownerA);
check('chủ doanh nghiệp duyệt được hồ sơ', !throws(static fn() => action_require_account_scope('application_review')));

// --- Một sinh viên không được hai doanh nghiệp cùng nhận.
[$companyB, $ownerB, $positionB] = $company('Beta');
$applicant = $student('ungvien');
$appA = insert('applications', ['student_id' => $applicant, 'position_id' => $positionA]);
$appB = insert('applications', ['student_id' => $applicant, 'position_id' => $positionB]);
login_as($ownerA);
handle_workspace_action('application_review', 'company/applications', ['application_id' => (string) $appA, 'status' => 'accepted'], []);
check('doanh nghiệp đầu nhận được và tạo kỳ thực tập', page_count('SELECT COUNT(*) FROM internships WHERE student_id = ?', [$applicant]) === 1);
login_as($ownerB);
check('doanh nghiệp thứ hai bị từ chối', throws(static fn() => handle_workspace_action('application_review', 'company/applications', ['application_id' => (string) $appB, 'status' => 'accepted'], [])));
check('không sinh thêm kỳ thực tập', page_count('SELECT COUNT(*) FROM internships WHERE student_id = ?', [$applicant]) === 1);

// --- Điểm tổng kết thực tập.
$internship = (int) page_one('SELECT id FROM internships WHERE student_id = ?', [$applicant])['id'];
check('thiếu một bên đánh giá thì chưa có điểm', action_final_score($internship) === null);
$lecturerUser = $account('lecturer', 'gv');
$lecturer = insert('lecturers', ['user_id' => $lecturerUser, 'lecturer_code' => 'GV' . random_int(100000, 999999)]);
foreach ([['company', $ownerA, 80.0], ['lecturer', $lecturerUser, 90.0]] as [$type, $by, $score]) {
    insert('evaluations', ['internship_id' => $internship, 'evaluator_user_id' => $by, 'evaluator_type' => $type, 'overall_score' => $score, 'status' => 'submitted']);
}
$weights = final_score_weights();
check('điểm thực tập đúng trọng số', action_final_score($internship) === round($weights['company'] * 80 + $weights['lecturer'] * 90, 2));

// --- Điểm đồ án và công bố ghép cặp.
$batch = insert('batches', ['batch_type' => 'thesis', 'name' => 'Đợt thử ' . $tag, 'status' => 'open', 'supervisor_weight' => 0.5, 'reviewer_weight' => 0.5, 'council_weight' => 0]);
$topicOld = insert('topics', ['batch_id' => $batch, 'title' => 'Đề tài cũ', 'lecturer_id' => $lecturer, 'status' => 'approved']);
$topicNew = insert('topics', ['batch_id' => $batch, 'title' => 'Đề tài mới', 'lecturer_id' => $lecturer, 'status' => 'approved']);
$confirmed = $student('daxacnhan');
$free = $student('tudo');
$thesis = insert('theses', ['batch_id' => $batch, 'student_id' => $confirmed, 'topic_id' => $topicOld, 'supervisor_id' => $lecturer, 'status' => 'confirmed']);
$old = date('Y-m-d H:i:s', time() - 3600);
database()->prepare('UPDATE theses SET updated_at = ? WHERE id = ?')->execute([$old, $thesis]);
$result = json_encode([['key' => 's' . $confirmed, 'student_ids' => [$confirmed], 'topic_id' => $topicNew, 'rank' => 1], ['key' => 's' . $free, 'student_ids' => [$free], 'topic_id' => $topicNew, 'rank' => 1]]);
$run = static fn() => insert('matching_runs', ['batch_id' => $batch, 'algorithm' => 'test', 'params' => '{}', 'result' => $result, 'metrics' => '{}']);
$fresh = $run();
check('công bố chỉ ghi sinh viên chưa chốt', matching_publish($fresh) === 1);
$kept = page_one('SELECT topic_id, status FROM theses WHERE id = ?', [$thesis]);
check('đồ án đã xác nhận giữ nguyên', (int) $kept['topic_id'] === $topicOld && $kept['status'] === 'confirmed');
check('không công bố lại lần chạy đã công bố', throws(static fn() => matching_publish($fresh)));
$stale = $run();
database()->prepare('UPDATE matching_runs SET created_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', time() - 7200), $stale]);
check('lần chạy cũ hơn thay đổi bị từ chối', throws(static fn() => matching_publish($stale)));
insert('thesis_scores', ['thesis_id' => $thesis, 'evaluator_user_id' => $lecturerUser, 'evaluator_role' => 'supervisor', 'total' => 8, 'status' => 'submitted']);
check('thiếu phản biện thì chưa có điểm đồ án', thesis_compute_final($thesis) === null);
insert('thesis_scores', ['thesis_id' => $thesis, 'evaluator_user_id' => $ownerA, 'evaluator_role' => 'reviewer', 'total' => 6, 'status' => 'submitted']);
check('điểm đồ án đúng trọng số', thesis_compute_final($thesis) === 7.0);

// --- OTP: sau khi xác minh vẫn còn thời gian đặt mật khẩu.
$email = 'otp' . $tag . '@test.local';
$otpUser = insert('users', ['username' => 'otp' . $tag, 'email' => $email, 'password_hash' => 'x', 'full_name' => 'OTP', 'role' => 'student']);
$reset = create_password_reset_otp($email);
check('nhập sai OTP bị từ chối', verify_password_reset_otp($email, $reset['otp'] === '111111' ? '222222' : '111111') === null);
$verified = verify_password_reset_otp($email, $reset['otp']);
database()->prepare('UPDATE password_reset_otps SET created_at = DATE_SUB(NOW(), INTERVAL 90 SECOND) WHERE id = ?')->execute([$verified['otp_id']]);
check('đặt được mật khẩu quá 60 giây kể từ lúc tạo mã', reset_verified_user_password($otpUser, $verified['otp_id'], 'MatKhauMoi@123'));

// --- Giới hạn đăng nhập sai.
$identifier = 'nobody' . $tag;
for ($attempt = 0; $attempt < 10; $attempt++) {
    check('chưa bị chặn trước lần sai thứ ' . ($attempt + 1), !login_is_throttled($identifier));
    login_record_failure($identifier);
}
check('bị chặn sau 10 lần sai', login_is_throttled($identifier));
check('tài khoản khác cùng IP chưa bị chặn', !login_is_throttled('other' . $tag));

// --- Sao lưu: tệp zip ghi theo luồng đọc lại được và đúng chữ ký.
$zipPath = (string) tempnam(sys_get_temp_dir(), 'ittest');
backup_zip_write($zipPath);
$archive = zip_read($zipPath, ['database.sql']);
check('bản sao lưu có chữ ký và dữ liệu', str_starts_with($archive['database.sql'], '-- Sao lưu InternTrack') && str_contains($archive['database.sql'], $tag));
unlink($zipPath);
check('tệp lạ không qua được kiểm tra chữ ký', throws(static fn() => backup_require_signature('DROP TABLE users;')));

echo $failures === 0 ? "\nTất cả đều đạt.\n" : "\n$failures kiểm thử thất bại.\n";
exit($failures === 0 ? 0 : 1);
