<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(static function (Throwable $error): void {
    error_log('InternTrack request failed: ' . $error->getMessage());
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Tạm thời không khả dụng</title><body><h1>InternTrack đang tạm thời không khả dụng</h1><p>Hãy kiểm tra MySQL và thử tải lại trang.</p></body></html>';
});
app_start_session();

// Danh sách các route hợp lệ trong ứng dụng.
// Mỗi route phải có view tương ứng trong thư mục views/.
$availablePages = [
    'auth/login',
    'auth/forgot-password',
    'auth/reset-password',
    'admin/dashboard',
    'admin/companies',
    'admin/internship',
    'admin/positions',
    'admin/students',
    'admin/users',
    'company/applications',
    'company/dashboard',
    'company/evaluations',
    'company/inters',
    'company/positions',
    'company/profile',
    'company/tasks',
    'lecturer/dashboard',
    'lecturer/diaries',
    'lecturer/evaluations',
    'lecturer/progress',
    'lecturer/reports',
    'lecturer/students',
    'student/applications',
    'student/dashboard',
    'student/diary',
    'student/evaluation',
    'student/internship-detail',
    'student/internships',
    'student/profile',
    'student/reports',
    'student/tasks',
];
$route = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'auth/login';
if (!in_array($route, $availablePages, true)) {
    http_response_code(404);
    $route = 'auth/login';
}

// Xử lý toàn bộ request POST: login, logout, gửi OTP, xác minh OTP, reset password và các thao tác nghiệp vụ.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bảo vệ CSRF để tránh request giả mạo từ bên ngoài.
    if (!app_valid_csrf()) {
        http_response_code(419);
        exit('Phiên làm việc hết hạn. Hãy tải lại trang và thử lại.');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    try {
        if ($action === 'login') {
            $identifier = is_string($_POST['login'] ?? null) ? $_POST['login'] : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (authenticate_user($identifier, $password)) {
                $user = authenticated_user();
                app_set_flash('success', 'Đăng nhập thành công.');
                app_redirect($user['role'] . '/dashboard');
            }
            app_set_flash('error', 'Thông tin đăng nhập không đúng hoặc tài khoản chưa được kích hoạt.');
            app_redirect('auth/login');
        }

        if ($action === 'logout') {
            end_user_session();
            app_start_session();
            app_set_flash('success', 'Bạn đã đăng xuất.');
            app_redirect('auth/login');
        }

        if ($action === 'password_reset_request') {
            $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
            $reset = create_password_reset_otp($email);
            $sent = $reset !== null && app_send_password_reset_otp($reset['email'], $reset['otp']);
            if ($reset !== null && !$sent) {
                invalidate_password_reset_otp($reset['otp']);
                error_log('InternTrack password reset OTP could not be sent; configure Gmail SMTP environment variables.');
            }
            unset($_SESSION['password_reset_authorized'], $_SESSION['password_reset_email']);
            if ($sent) {
                $_SESSION['password_reset_email'] = $reset['email'];
                app_set_flash('success', 'Mã OTP đã được gửi. Nhập mã gồm 6 chữ số để tiếp tục; hãy kiểm tra cả thư mục Spam.');
                app_redirect('auth/reset-password');
            }
            app_set_flash('success', 'Nếu email thuộc tài khoản đang hoạt động, hướng dẫn sẽ được gửi. Hãy kiểm tra hộp thư và thử lại sau ít phút nếu chưa nhận được.');
            app_redirect('auth/forgot-password');
        }

        if ($action === 'password_reset_verify') {
            $email = is_string($_SESSION['password_reset_email'] ?? null) ? $_SESSION['password_reset_email'] : '';
            $otp = is_string($_POST['otp'] ?? null) ? $_POST['otp'] : '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
                throw new DomainException('Email hoặc mã OTP không hợp lệ.');
            }
            $verified = verify_password_reset_otp($email, $otp);
            if ($verified === null) {
                throw new DomainException('Mã OTP không đúng, đã hết hạn hoặc đã vượt quá số lần thử.');
            }
            $_SESSION['password_reset_authorized'] = [
                'user_id' => $verified['user_id'],
                'otp_id' => $verified['otp_id'],
                'expires_at' => time() + 60,
            ];
            unset($_SESSION['password_reset_email']);
            app_set_flash('success', 'Mã OTP hợp lệ. Hãy tạo mật khẩu mới.');
            app_redirect('auth/reset-password');
        }

        if ($action === 'reset_password') {
            $authorization = $_SESSION['password_reset_authorized'] ?? null;
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
            if (!is_array($authorization) || (int) ($authorization['expires_at'] ?? 0) < time()) {
                unset($_SESSION['password_reset_authorized']);
                throw new DomainException('Phiên xác minh đã hết hạn. Hãy yêu cầu mã OTP mới.');
            }
            if (strlen($password) < 10 || $password !== $confirmation) {
                throw new DomainException('Mật khẩu phải có ít nhất 10 ký tự và hai lần nhập phải khớp.');
            }
            if (!reset_verified_user_password((int) $authorization['user_id'], (int) $authorization['otp_id'], $password)) {
                unset($_SESSION['password_reset_authorized']);
                throw new DomainException('Mã OTP đã hết hạn. Hãy yêu cầu mã mới.');
            }
            unset($_SESSION['password_reset_authorized'], $_SESSION['password_reset_email']);
            app_set_flash('success', 'Mật khẩu đã được cập nhật. Hãy đăng nhập lại.');
            app_redirect('auth/login');
        }

        require_once __DIR__ . '/controllers/ActionController.php';
        $redirectPage = handle_workspace_action($action, $route, $_POST, $_FILES);
        app_redirect($redirectPage ?: $route);
    } catch (DomainException $error) {
        app_set_flash('error', $error->getMessage());
        app_redirect($route);
    } catch (Throwable $error) {
        error_log('InternTrack request failed: ' . $error->getMessage());
        app_set_flash('error', 'Không thể hoàn tất thao tác. Vui lòng kiểm tra dữ liệu và thử lại.');
        app_redirect($route);
    }
}

$user = authenticated_user();
$downloadType = is_string($_GET['download'] ?? null) ? $_GET['download'] : '';
if ($downloadType !== '') {
    if ($user === null) {
        http_response_code(401);
        exit('Vui lòng đăng nhập để tải tệp.');
    }
    require_once __DIR__ . '/controllers/ActionController.php';
    $downloadId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$downloadId) {
        http_response_code(404);
        exit('Không tìm thấy tệp.');
    }
    serve_workspace_download($downloadType, $downloadId, $user);
}
$publicPages = ['auth/login', 'auth/forgot-password', 'auth/reset-password'];
if ($user === null && !in_array($route, $publicPages, true)) {
    app_set_flash('error', 'Vui lòng đăng nhập để tiếp tục.');
    app_redirect('auth/login');
}
if ($user !== null && in_array($route, ['auth/login', 'auth/forgot-password'], true)) {
    app_redirect($user['role'] . '/dashboard');
}
if ($user === null && $route === 'auth/reset-password') {
    $authorization = $_SESSION['password_reset_authorized'] ?? null;
    $hasVerifiedOtp = is_array($authorization) && (int) ($authorization['expires_at'] ?? 0) >= time();
    if (!$hasVerifiedOtp && empty($_SESSION['password_reset_email'])) {
        app_redirect('auth/forgot-password');
    }
}
if ($user !== null && !in_array($route, $publicPages, true)) {
    $requiredRole = explode('/', $route, 2)[0];
    if ($requiredRole !== $user['role']) {
        http_response_code(403);
        app_set_flash('error', 'Bạn không có quyền truy cập trang này.');
        app_redirect($user['role'] . '/dashboard');
    }
}

$flash = app_take_flash();
require __DIR__ . '/views/' . $route . '.php';
