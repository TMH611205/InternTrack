<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// Xác thực người dùng khi đăng nhập bằng username hoặc email.
// Nếu thông tin hợp lệ thì tạo session và cập nhật thời gian đăng nhập cuối.
function authenticate_user(string $identifier, string $password): bool
{
    $statement = database()->prepare(
        'SELECT id, password_hash, role, status FROM users WHERE username = :username OR email = :email LIMIT 1'
    );
    $statement->execute(['username' => trim($identifier), 'email' => trim($identifier)]);
    $account = $statement->fetch();

    if (!$account || $account['status'] !== 'active' || !password_verify($password, $account['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $account['id'];
    $_SESSION['role'] = $account['role'];
    unset($_SESSION['active_role']);
    database()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$account['id']]);
    return true;
}

// Giới hạn đăng nhập sai: 10 lần cho một tài khoản + IP, 50 lần cho một IP, trong 15 phút.
// Đếm theo cặp tài khoản + IP để người ngoài không khóa được tài khoản của người khác.
function login_attempt_keys(string $identifier): array
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return ['account' => hash('sha256', mb_strtolower(trim($identifier), 'UTF-8') . '|' . $ip), 'ip' => hash('sha256', '|' . $ip)];
}

function login_is_throttled(string $identifier): bool
{
    $keys = login_attempt_keys($identifier);
    $count = database()->prepare('SELECT COUNT(*) FROM login_attempts WHERE key_hash = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    foreach ([['account', 10], ['ip', 50]] as [$type, $limit]) {
        $count->execute([$keys[$type]]);
        if ((int) $count->fetchColumn() >= $limit) {
            return true;
        }
    }
    return false;
}

function login_record_failure(string $identifier): void
{
    $connection = database();
    $connection->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $insert = $connection->prepare('INSERT INTO login_attempts (key_hash) VALUES (?)');
    foreach (login_attempt_keys($identifier) as $key) {
        $insert->execute([$key]);
    }
}

function login_clear_failures(string $identifier): void
{
    database()->prepare('DELETE FROM login_attempts WHERE key_hash = ?')->execute([login_attempt_keys($identifier)['account']]);
}

// Tạo mã OTP cho chức năng quên mật khẩu.
// Chỉ cho phép tạo mới nếu tài khoản tồn tại, đang active và chưa gửi OTP trong 60 giây gần đây.
function create_password_reset_otp(string $email): ?array
{
    $connection = database();
    $connection->beginTransaction();
    try {
        $statement = $connection->prepare('SELECT id FROM users WHERE email = ? AND status = ? LIMIT 1 FOR UPDATE');
        $statement->execute([trim($email), 'active']);
        $userId = $statement->fetchColumn();
        if (!$userId) {
            $connection->rollBack();
            return null;
        }

        $recent = $connection->prepare('SELECT id FROM password_reset_otps WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1');
        $recent->execute([$userId]);
        if ($recent->fetchColumn()) {
            $connection->rollBack();
            return null;
        }

        $otp = (string) random_int(100000, 999999);
        $connection->prepare('UPDATE password_reset_otps SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
        $connection->prepare(
            'INSERT INTO password_reset_otps (user_id, otp_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 SECOND))'
        )->execute([$userId, hash('sha256', $otp)]);
        $connection->commit();
        return ['email' => trim($email), 'otp' => $otp];
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $error;
    }
}

function invalidate_password_reset_otp(string $otp): void
{
    database()->prepare('UPDATE password_reset_otps SET used_at = NOW() WHERE otp_hash = ? AND used_at IS NULL')
        ->execute([hash('sha256', $otp)]);
}

// Xác minh OTP đã gửi cho email tương ứng.
// Nếu mã đúng, chưa hết hạn và chưa vượt quá 5 lần sai thì trả về thông tin xác thực.
function verify_password_reset_otp(string $email, string $otp): ?array
{
    $connection = database();
    $connection->beginTransaction();
    try {
        $statement = $connection->prepare(
            'SELECT o.id, o.user_id, o.otp_hash, o.attempts FROM password_reset_otps o
             JOIN users u ON u.id = o.user_id
             WHERE u.email = ? AND u.status = \'active\' AND o.used_at IS NULL AND o.verified_at IS NULL AND o.expires_at > NOW()
             ORDER BY o.created_at DESC LIMIT 1 FOR UPDATE'
        );
        $statement->execute([trim($email)]);
        $reset = $statement->fetch();
        if (!$reset || (int) $reset['attempts'] >= 5) {
            $connection->rollBack();
            return null;
        }
        if (!hash_equals($reset['otp_hash'], hash('sha256', $otp))) {
            $connection->prepare('UPDATE password_reset_otps SET attempts = attempts + 1 WHERE id = ?')->execute([$reset['id']]);
            $connection->commit();
            return null;
        }

        $connection->prepare('UPDATE password_reset_otps SET verified_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id = ?')->execute([$reset['id']]);
        $connection->commit();
        return ['otp_id' => (int) $reset['id'], 'user_id' => (int) $reset['user_id']];
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $error;
    }
}

// Cập nhật mật khẩu mới sau khi người dùng đã xác minh OTP thành công.
// Chỉ cho phép nếu OTP còn hiệu lực và chưa được sử dụng.
function reset_verified_user_password(int $userId, int $otpId, string $password): bool
{
    $connection = database();
    $connection->beginTransaction();
    try {
        $statement = $connection->prepare(
            'SELECT id FROM password_reset_otps
             WHERE id = ? AND user_id = ? AND verified_at IS NOT NULL AND used_at IS NULL AND expires_at > NOW()
             FOR UPDATE'
        );
        $statement->execute([$otpId, $userId]);
        if (!$statement->fetchColumn()) {
            $connection->rollBack();
            return false;
        }
        $connection->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $userId,
        ]);
        $connection->prepare('UPDATE password_reset_otps SET used_at = NOW() WHERE id = ?')->execute([$otpId]);
        $connection->commit();
        return true;
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $error;
    }
}

// Lấy thông tin người dùng đang đăng nhập hiện tại từ session.
// Nếu session không hợp lệ hoặc user bị vô hiệu hóa thì tự động hủy phiên làm việc.
function authenticated_user(): ?array
{
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) {
        return null;
    }

    // Tài khoản doanh nghiệp: chủ hồ sơ (companies.user_id) hoặc người hướng dẫn thuộc doanh nghiệp (company_mentors).
    $statement = database()->prepare(
        'SELECT u.id, u.username, u.email, u.full_name, u.phone, u.avatar, u.role AS primary_role, u.status, u.scope_faculty,
				s.id AS student_id, s.student_code, s.major, s.class_name, s.faculty, s.university, s.cv_file, s.bio,
				c.id AS company_id, c.company_code, c.company_name, c.status AS company_status, cm.user_id IS NOT NULL AS is_mentor,
				l.id AS lecturer_id, l.lecturer_code, l.department, l.faculty AS lecturer_faculty, l.is_head
		 FROM users u
		 LEFT JOIN students s ON s.user_id = u.id
		 LEFT JOIN company_mentors cm ON cm.user_id = u.id
		 LEFT JOIN companies c ON c.id = COALESCE(cm.company_id, (SELECT id FROM companies WHERE user_id = u.id LIMIT 1))
		 LEFT JOIN lecturers l ON l.user_id = u.id
		 WHERE u.id = :id LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['active_role']);
        return null;
    }

    // Một tài khoản có thể có thêm vai trò (user_roles); vai trò đang dùng lưu trong phiên và đổi được ở thanh bên.
    $roles = [$user['primary_role']];
    foreach (auth_extra_roles((int) $user['id']) as $extra) {
        if (!in_array($extra, $roles, true) && ($extra !== 'lecturer' || $user['lecturer_id'])) {
            $roles[] = $extra;
        }
    }
    $active = $_SESSION['active_role'] ?? $user['primary_role'];
    $user['roles'] = $roles;
    $user['role'] = in_array($active, $roles, true) ? $active : $user['primary_role'];
    $user['is_mentor'] = (bool) $user['is_mentor'];
    // Người hướng dẫn tại doanh nghiệp: các truy vấn thực tập của doanh nghiệp lọc thêm theo @it_mentor (chỉ thực tập sinh được giao).
    database()->exec('SET @it_mentor = ' . ($user['is_mentor'] && $user['role'] === 'company' ? (int) $user['id'] : 'NULL'));
    // Phạm vi khoa chỉ áp dụng khi đang dùng vai trò quản trị.
    $user['scope_faculty'] = $user['role'] === 'admin' ? ($user['scope_faculty'] ?: null) : null;
    // Quản trị cấp khoa: các truy vấn danh sách của quản trị lọc theo @it_faculty (khoa của sinh viên, giảng viên, ngành của đợt).
    database()->prepare('SET @it_faculty = ?')->execute([$user['scope_faculty']]);

    return $user;
}

function auth_extra_roles(int $userId): array
{
    $statement = database()->prepare('SELECT role FROM user_roles WHERE user_id = ?');
    $statement->execute([$userId]);
    return array_column($statement->fetchAll(), 'role');
}

// Hủy toàn bộ session người dùng khi đăng xuất hoặc hết phiên.
function end_user_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parameters['path'],
            'domain' => $parameters['domain'],
            'secure' => $parameters['secure'],
            'httponly' => $parameters['httponly'],
            'samesite' => $parameters['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
