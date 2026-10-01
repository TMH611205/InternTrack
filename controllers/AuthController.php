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
    database()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$account['id']]);
    return true;
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

        $connection->prepare('UPDATE password_reset_otps SET verified_at = NOW() WHERE id = ?')->execute([$reset['id']]);
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

    $statement = database()->prepare(
        'SELECT u.id, u.username, u.email, u.full_name, u.phone, u.avatar, u.role, u.status,
				s.id AS student_id, s.student_code, s.major, s.class_name, s.faculty, s.university, s.cv_file, s.bio,
				c.id AS company_id, c.company_code, c.company_name, c.status AS company_status,
				l.id AS lecturer_id, l.lecturer_code, l.department
		 FROM users u
		 LEFT JOIN students s ON s.user_id = u.id
		 LEFT JOIN companies c ON c.user_id = u.id
		 LEFT JOIN lecturers l ON l.user_id = u.id
		 WHERE u.id = :id LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['role']);
        return null;
    }

    return $user;
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
