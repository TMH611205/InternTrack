<?php

declare(strict_types=1);

require_once __DIR__ . '/AiMatchController.php';
require_once __DIR__ . '/AiInsightController.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/PageController.php';
require_once __DIR__ . '/NotificationController.php';

// Các hàm trong file này xử lý các thao tác nghiệp vụ chính của hệ thống.
// Mỗi thao tác kiểm tra quyền, validate dữ liệu đầu vào và cập nhật DB theo role tương ứng.
function action_user(array $allowedRoles): array
{
    $user = authenticated_user();
    if (!$user) {
        throw new DomainException('Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại.');
    }
    if (!in_array($user['role'], $allowedRoles, true)) {
        throw new DomainException('Bạn không có quyền thực hiện thao tác này.');
    }
    return $user;
}

// Đọc và validate dữ liệu text từ form trước khi lưu vào database.
// Đảm bảo không có dữ liệu rỗng quá dài hoặc không hợp lệ đi qua.
function action_value(array $input, string $key, int $maximum = 5000): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value)) {
        throw new DomainException('Dữ liệu gửi lên không hợp lệ.');
    }
    $value = trim($value);
    if (mb_strlen($value, 'UTF-8') > $maximum) {
        throw new DomainException('Một trường dữ liệu vượt quá độ dài cho phép.');
    }
    return $value;
}

function action_internship(int $internshipId, array $user): array
{
    $scope = match ($user['role']) {
        'student' => ['i.student_id = ?', [(int) $user['student_id']]],
        'company' => ['i.company_id = ?', [(int) $user['company_id']]],
        'lecturer' => ['i.lecturer_id = ?', [(int) $user['lecturer_id']]],
        'admin' => ['1 = 1', []],
        default => throw new DomainException('Không có quyền truy cập kỳ thực tập này.'),
    };
    $statement = database()->prepare('SELECT i.* FROM internships i WHERE i.id = ? AND ' . $scope[0] . ' LIMIT 1');
    $statement->execute(array_merge([$internshipId], $scope[1]));
    $internship = $statement->fetch();
    if (!$internship) {
        throw new DomainException('Không tìm thấy kỳ thực tập hoặc bạn không có quyền truy cập.');
    }
    return $internship;
}

function action_require_company_address(int $companyId): void
{
    $company = page_one('SELECT address FROM companies WHERE id = ?', [$companyId]);
    if (!$company || trim((string) $company['address']) === '') {
        throw new DomainException('Doanh nghiệp cần cập nhật địa chỉ công ty trước khi mở tin tuyển dụng.');
    }
}

// Lưu file upload vào thư mục uploads/ và kiểm tra MIME cũng như dung lượng trước khi lưu.
function store_uploaded_file(array $file, string $folder, int $maximumBytes): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > $maximumBytes) {
        throw new DomainException('Tệp tải lên không hợp lệ hoặc vượt quá dung lượng cho phép.');
    }

    $documentTypes = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    $mimeExtensions = match ($folder) {
        'avatars' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        // Minh chứng hoàn thành nhiệm vụ: tài liệu, bảng tính, bài trình bày, ảnh chụp, văn bản và tệp nén.
        'submissions' => $documentTypes + [
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'application/vnd.rar' => 'rar',
            'application/x-rar' => 'rar',
            'application/x-7z-compressed' => '7z',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'text/plain' => 'txt',
        ],
        default => $documentTypes,
    };
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($mimeExtensions[$mime])) {
        throw new DomainException(match ($folder) {
            'avatars' => 'Ảnh đại diện phải là JPG, PNG hoặc WebP.',
            'submissions' => 'Minh chứng chấp nhận: Word, PDF, Excel, PowerPoint, ZIP/RAR/7z, ảnh JPG/PNG hoặc văn bản .txt.',
            default => 'Chỉ chấp nhận tệp PDF, DOC hoặc DOCX.',
        });
    }
    $extension = $mimeExtensions[$mime];
    // Tệp Office hiện đại thực chất là ZIP nên có thể bị nhận là application/zip: giữ đuôi gốc nếu là docx/xlsx/pptx.
    if ($extension === 'zip') {
        $original = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $extension = in_array($original, ['docx', 'xlsx', 'pptx'], true) ? $original : 'zip';
    }

    $relativePath = 'uploads/' . $folder . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($absolutePath);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Không thể tạo thư mục lưu tệp.');
    }
    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('Không thể lưu tệp tải lên.');
    }
    return $relativePath;
}

// Tải xuống file có quyền truy cập hợp lệ theo role của người dùng.
// Đảm bảo chỉ những người có quyền mới có thể xem CV, báo cáo hoặc avatar.
function serve_workspace_download(string $type, int $recordId, array $user): never
{
    $connection = database();
    if ($type === 'report') {
        $statement = $connection->prepare(
            'SELECT r.file_path, r.title FROM reports r JOIN internships i ON i.id = r.internship_id
             WHERE r.id = ? AND (r.student_id = ? OR i.lecturer_id = ? OR i.company_id = ? OR ? = \'admin\') LIMIT 1'
        );
        $statement->execute([$recordId, $user['student_id'] ?? 0, $user['lecturer_id'] ?? 0, $user['company_id'] ?? 0, $user['role']]);
    } elseif ($type === 'cv') {
        $statement = $connection->prepare(
            'SELECT s.cv_file AS file_path, CONCAT(u.full_name, \' - CV\') AS title FROM students s JOIN users u ON u.id = s.user_id
             WHERE s.id = ? AND (s.user_id = ? OR ? = \'admin\' OR EXISTS (SELECT 1 FROM internships i WHERE i.student_id = s.id AND (i.lecturer_id = ? OR i.company_id = ?)) OR EXISTS (SELECT 1 FROM applications a JOIN positions p ON p.id = a.position_id WHERE a.student_id = s.id AND p.company_id = ?)) LIMIT 1'
        );
        $statement->execute([$recordId, $user['id'], $user['role'], $user['lecturer_id'] ?? 0, $user['company_id'] ?? 0, $user['company_id'] ?? 0]);
    } elseif ($type === 'task') {
        $statement = $connection->prepare(
            'SELECT t.submission_file AS file_path, CONCAT(\'Minh chung - \', t.title) AS title FROM tasks t JOIN internships i ON i.id = t.internship_id
             WHERE t.id = ? AND (t.assigned_to = ? OR i.company_id = ? OR i.lecturer_id = ? OR ? = \'admin\') LIMIT 1'
        );
        $statement->execute([$recordId, $user['student_id'] ?? 0, $user['company_id'] ?? 0, $user['lecturer_id'] ?? 0, $user['role']]);
    } elseif ($type === 'avatar') {
        // Ảnh đại diện hiển thị cho mọi người dùng đã đăng nhập để ảnh của một người đồng bộ ở mọi nơi họ xuất hiện.
        $statement = $connection->prepare('SELECT avatar AS file_path, full_name AS title FROM users WHERE id = ? AND status = \'active\' LIMIT 1');
        $statement->execute([$recordId]);
    } else {
        http_response_code(404);
        exit('Không tìm thấy tệp.');
    }

    $file = $statement->fetch();
    $uploadRoot = realpath(dirname(__DIR__) . '/uploads');
    $absolutePath = $file && $file['file_path'] ? realpath(dirname(__DIR__) . '/' . $file['file_path']) : false;
    if (!$uploadRoot || !$absolutePath || !str_starts_with($absolutePath, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($absolutePath)) {
        http_response_code(404);
        exit('Không tìm thấy tệp.');
    }

    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $mime = $type === 'avatar'
        ? (['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$extension] ?? 'application/octet-stream')
        : (['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'zip' => 'application/zip', 'rar' => 'application/vnd.rar', '7z' => 'application/x-7z-compressed', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'txt' => 'text/plain'][$extension] ?? 'application/octet-stream');
    $downloadName = preg_replace('/[^\pL\pN ._-]/u', '', (string) $file['title']) ?: 'InternTrack';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($absolutePath));
    header('X-Content-Type-Options: nosniff');
    if ($type === 'avatar') {
        header('Cache-Control: private, max-age=86400');
    }
    header($type === 'avatar' ? 'Content-Disposition: inline; filename="avatar.' . $extension . '"' : "Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName . '.' . $extension));
    readfile($absolutePath);
    exit;
}

// Router điều hướng xử lý nghiệp vụ dựa trên action gửi từ form.
// Mỗi action tương ứng với một chức năng như ứng tuyển, đánh giá, nhiệm vụ, profile, quản trị.
function handle_workspace_action(string $action, string $route, array $input, array $files): string
{
    $connection = database();
    $successMessage = 'Đã lưu thay đổi.';

    switch ($action) {
        case 'notification_read':
            $user = action_user(['student', 'company', 'lecturer', 'admin']);
            $notificationId = filter_var($input['notification_id'] ?? null, FILTER_VALIDATE_INT);
            $notificationSection = action_value($input, 'section', 40);
            notification_mark_read((int) $user['id'], $notificationId ?: null, $notificationId ? null : ($notificationSection ?: null));
            $successMessage = 'Đã đánh dấu đã đọc.';
            break;

        case 'ai_match':
            $user = action_user(['student']);
            $matched = ai_match_run($user);
            $successMessage = 'AI đã đánh giá ' . $matched . ' vị trí dựa trên CV của bạn.';
            break;

        case 'ai_review_diary':
            $user = action_user(['lecturer', 'admin']);
            $diaryId = (int) filter_var($input['diary_id'] ?? null, FILTER_VALIDATE_INT);
            ai_review_diary($user, $diaryId);
            $_SESSION['_open_dialog'] = 'ai-diary-' . $diaryId;
            $successMessage = 'AI đã tóm tắt và chấm sơ bộ nhật ký.';
            break;

        case 'ai_review_report':
            $user = action_user(['lecturer', 'admin']);
            $reportId = (int) filter_var($input['report_id'] ?? null, FILTER_VALIDATE_INT);
            ai_review_report($user, $reportId);
            $_SESSION['_open_dialog'] = 'ai-report-' . $reportId;
            $successMessage = 'AI đã tóm tắt và chấm sơ bộ báo cáo.';
            break;

        case 'skill_suggest':
            $user = action_user(['company', 'admin']);
            $diaryId = (int) filter_var($input['diary_id'] ?? null, FILTER_VALIDATE_INT);
            $created = ai_skill_extract($user, $diaryId);
            $_SESSION['_open_dialog'] = 'skills-' . $diaryId;
            $successMessage = $created > 0 ? 'AI đã đề xuất ' . $created . ' kỹ năng, chờ bạn xác nhận.' : 'AI không thấy kỹ năng nào đủ căn cứ trong nhật ký này.';
            break;

        case 'skill_review':
            $user = action_user(['company', 'admin']);
            $suggestionId = (int) filter_var($input['suggestion_id'] ?? null, FILTER_VALIDATE_INT);
            $diaryId = (int) filter_var($input['diary_id'] ?? null, FILTER_VALIDATE_INT);
            $decision = action_value($input, 'decision', 10);
            ai_skill_review($user, $suggestionId, $decision, action_value($input, 'name', 80), action_value($input, 'level', 30));
            $_SESSION['_open_dialog'] = 'skills-' . $diaryId;
            $successMessage = ['confirm' => 'Đã xác nhận kỹ năng.', 'edit' => 'Đã sửa và xác nhận kỹ năng.', 'reject' => 'Đã bác bỏ đề xuất.'][$decision] ?? 'Đã cập nhật.';
            break;

        case 'ai_risk':
            $user = action_user(['lecturer']);
            $analysed = ai_risk_run($user);
            $successMessage = 'AI đã phân tích nguy cơ của ' . $analysed . ' sinh viên.';
            break;

        case 'ai_final_comment':
            $user = action_user(['lecturer', 'admin']);
            $internshipId = (int) filter_var($input['internship_id'] ?? null, FILTER_VALIDATE_INT);
            ai_final_comment_run($user, $internshipId);
            $_SESSION['_open_dialog'] = 'ai-final-' . $internshipId;
            $successMessage = 'AI đã soạn bản nhận xét cuối kỳ.';
            break;

        case 'ai_skill_stats':
            action_user(['admin']);
            ai_skill_stats_run();
            $successMessage = 'AI đã cập nhật thống kê kỹ năng doanh nghiệp đang cần.';
            break;

        case 'kb_save':
            $user = action_user(['admin']);
            $documentId = filter_var($input['document_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $title = action_value($input, 'title', 200);
            $content = action_value($input, 'content', 60000);
            $upload = $files['kb_file'] ?? null;
            if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, ['txt', 'md'], true) || (int) $upload['size'] > 300 * 1024) {
                    throw new DomainException('Chỉ nhận tệp văn bản .txt hoặc .md tối đa 300 KB.');
                }
                $fileText = trim((string) file_get_contents($upload['tmp_name']));
                if (!mb_check_encoding($fileText, 'UTF-8')) {
                    throw new DomainException('Tệp phải được lưu bằng mã hóa UTF-8.');
                }
                $content = mb_substr($fileText, 0, 60000, 'UTF-8');
                $title = $title !== '' ? $title : pathinfo((string) $upload['name'], PATHINFO_FILENAME);
            }
            if ($title === '' || $content === '') {
                throw new DomainException('Tài liệu cần có tiêu đề và nội dung (nhập trực tiếp hoặc tải tệp .txt/.md).');
            }
            if ($documentId) {
                if (!page_one('SELECT id FROM kb_documents WHERE id = ?', [$documentId])) {
                    throw new DomainException('Không tìm thấy tài liệu.');
                }
                $connection->prepare('UPDATE kb_documents SET title = ?, content = ? WHERE id = ?')->execute([$title, $content, $documentId]);
            } else {
                $connection->prepare('INSERT INTO kb_documents (title, content, created_by) VALUES (?, ?, ?)')->execute([$title, $content, $user['id']]);
            }
            $successMessage = 'Đã lưu tài liệu cho trợ lý hỏi đáp.';
            break;

        case 'kb_delete':
            action_user(['admin']);
            $documentId = filter_var($input['document_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$documentId) {
                throw new DomainException('Không tìm thấy tài liệu.');
            }
            $connection->prepare('DELETE FROM kb_documents WHERE id = ?')->execute([$documentId]);
            $successMessage = 'Đã xóa tài liệu.';
            break;

        case 'apply':
            $user = action_user(['student']);
            $positionId = filter_var($input['position_id'] ?? null, FILTER_VALIDATE_INT);
            $position = $positionId ? page_one('SELECT id FROM positions WHERE id = ? AND status = \'open\' AND (deadline IS NULL OR deadline >= CURDATE())', [$positionId]) : null;
            if (!$position) {
                throw new DomainException('Vị trí đã đóng hoặc không còn nhận hồ sơ.');
            }
            $studentProfile = page_one('SELECT cv_file FROM students WHERE id = ?', [$user['student_id']]);
            if (empty($studentProfile['cv_file'])) {
                throw new DomainException('Hãy tải CV lên hồ sơ trước khi ứng tuyển.');
            }
            if (page_one('SELECT id FROM applications WHERE student_id = ? AND position_id = ?', [$user['student_id'], $positionId])) {
                throw new DomainException('Bạn đã ứng tuyển vị trí này rồi.');
            }
            $connection->prepare('INSERT INTO applications (student_id, position_id, cv_file, cover_letter) VALUES (?, ?, ?, ?)')->execute([
                $user['student_id'],
                $positionId,
                $studentProfile['cv_file'] ?? null,
                action_value($input, 'cover_letter', 5000),
            ]);
            $applicationId = (int) $connection->lastInsertId();
            $connection->prepare('INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, note) VALUES (?, NULL, \'pending\', ?, \'Ứng tuyển qua InternTrack.\')')->execute([$applicationId, $user['id']]);
            $appliedPosition = page_one('SELECT title, company_id FROM positions WHERE id = ?', [$positionId]);
            if ($appliedPosition) {
                notify_company((int) $appliedPosition['company_id'], 'applications', 'Hồ sơ ứng tuyển mới', $user['full_name'] . ' vừa ứng tuyển vị trí ' . $appliedPosition['title'] . '.', 'company/applications');
            }
            $successMessage = 'Đã gửi hồ sơ ứng tuyển.';
            $route = 'student/applications';
            break;

        case 'application_review':
            $user = action_user(['company']);
            $applicationId = filter_var($input['application_id'] ?? null, FILTER_VALIDATE_INT);
            $status = action_value($input, 'status', 20);
            if (!in_array($status, ['reviewing', 'accepted', 'rejected'], true)) {
                throw new DomainException('Trạng thái ứng tuyển không hợp lệ.');
            }
            $connection->beginTransaction();
            try {
                $statement = $connection->prepare(
                    'SELECT a.*, p.company_id, p.id AS position_id, p.quantity FROM applications a JOIN positions p ON p.id = a.position_id WHERE a.id = ? AND p.company_id = ? FOR UPDATE'
                );
                $statement->execute([$applicationId, $user['company_id']]);
                $application = $statement->fetch();
                if (!$application) {
                    throw new DomainException('Không tìm thấy hồ sơ ứng tuyển.');
                }
                if (!in_array($application['status'], ['pending', 'reviewing'], true)) {
                    throw new DomainException('Hồ sơ này đã được quyết định trước đó.');
                }
                $connection->prepare('UPDATE applications SET status = ?, reviewed_at = NOW(), reviewed_by = ?, note = ? WHERE id = ?')->execute([
                    $status,
                    $user['id'],
                    action_value($input, 'note', 2000) ?: null,
                    $applicationId,
                ]);
                $connection->prepare('INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)')->execute([
                    $applicationId,
                    $application['status'],
                    $status,
                    $user['id'],
                    action_value($input, 'note', 2000) ?: null,
                ]);
                if ($status === 'accepted') {
                    $acceptedCount = page_count('SELECT COUNT(*) FROM applications WHERE position_id = ? AND status = \'accepted\' AND id <> ?', [$application['position_id'], $applicationId]);
                    if ($acceptedCount >= (int) $application['quantity']) {
                        throw new DomainException('Vị trí đã đủ số lượng thực tập sinh.');
                    }
                    $startDate = action_value($input, 'start_date', 10) ?: date('Y-m-d');
                    $endDate = action_value($input, 'end_date', 10) ?: date('Y-m-d', strtotime('+12 weeks'));
                    if (!strtotime($startDate) || !strtotime($endDate) || $endDate < $startDate) {
                        throw new DomainException('Ngày bắt đầu và kết thúc kỳ thực tập không hợp lệ.');
                    }
                    $internshipInsert = $connection->prepare(
                        'INSERT INTO internships (student_id, company_id, position_id, start_date, end_date, status, description, training_plan)
                        SELECT ?, ?, ?, ?, ?, \'planned\', \'Kỳ thực tập được tạo khi hồ sơ ứng tuyển được chấp nhận.\', \'Kế hoạch thực tập: tuần 1 làm quen quy trình, tuần 2 thực hiện nhiệm vụ chính, tuần 3 báo cáo tiến độ và đánh giá.\nKỳ thực tập được xây dựng theo lộ trình rõ ràng để doanh nghiệp và sinh viên đồng bộ mục tiêu học tập và thực hành.\'
                         WHERE NOT EXISTS (SELECT 1 FROM internships WHERE student_id = ? AND position_id = ? AND start_date = ?)'
                    );
                    $internshipInsert->execute([
                        $application['student_id'],
                        $application['company_id'],
                        $application['position_id'],
                        $startDate,
                        $endDate,
                        $application['student_id'],
                        $application['position_id'],
                        $startDate,
                    ]);
                    if ($internshipInsert->rowCount() > 0) {
                        $newInternshipId = (int) $connection->lastInsertId();
                        $connection->prepare('INSERT INTO internship_status_history (internship_id, old_status, new_status, changed_by, note) VALUES (?, NULL, \'planned\', ?, \'Kỳ thực tập được tạo từ hồ sơ được chấp nhận.\')')->execute([$newInternshipId, $user['id']]);
                        // Tự phân công giảng viên đang phụ trách ít kỳ thực tập nhất để sinh viên hiện ngay trong danh sách của giảng viên; quản trị viên vẫn có thể đổi lại.
                        $autoLecturerId = $connection->query(
                            'SELECT l.id FROM lecturers l JOIN users lu ON lu.id = l.user_id WHERE lu.status = \'active\'
                             ORDER BY (SELECT COUNT(*) FROM internships i WHERE i.lecturer_id = l.id AND i.status IN (\'planned\', \'active\')), l.id LIMIT 1'
                        )->fetchColumn();
                        if ($autoLecturerId) {
                            $connection->prepare('UPDATE internships SET lecturer_id = ? WHERE id = ?')->execute([$autoLecturerId, $newInternshipId]);
                        }
                    }
                }
                $connection->commit();
            } catch (Throwable $error) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $error;
            }
            $reviewLabels = ['reviewing' => 'đang được doanh nghiệp xem xét', 'accepted' => 'đã được chấp nhận', 'rejected' => 'chưa phù hợp lần này'];
            $reviewedPosition = page_one('SELECT title FROM positions WHERE id = ?', [$application['position_id']]);
            if ($status === 'accepted' && !empty($autoLecturerId)) {
                $acceptedStudent = page_one('SELECT u.full_name FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$application['student_id']]);
                notify_lecturer((int) $autoLecturerId, 'students', 'Được phân công sinh viên mới', ($acceptedStudent['full_name'] ?? 'Một sinh viên') . ' thực tập tại ' . ($user['company_name'] ?? 'doanh nghiệp') . ' (' . ($reviewedPosition['title'] ?? '') . ').', 'lecturer/students');
            }
            notify_student((int) $application['student_id'], 'applications', 'Hồ sơ ứng tuyển cập nhật', 'Hồ sơ vị trí ' . ($reviewedPosition['title'] ?? '') . ' ' . $reviewLabels[$status] . '.', 'student/applications');
            $successMessage = $status === 'accepted' ? 'Ứng viên đã được nhận và kỳ thực tập đã được tạo.' : 'Đã cập nhật trạng thái hồ sơ.';
            break;

        case 'application_withdraw':
            $user = action_user(['student']);
            $applicationId = filter_var($input['application_id'] ?? null, FILTER_VALIDATE_INT);
            $connection->beginTransaction();
            try {
                $statement = $connection->prepare('SELECT status FROM applications WHERE id = ? AND student_id = ? FOR UPDATE');
                $statement->execute([$applicationId, $user['student_id']]);
                $oldStatus = $statement->fetchColumn();
                if (!in_array($oldStatus, ['pending', 'reviewing'], true)) {
                    throw new DomainException('Chỉ có thể rút hồ sơ đang chờ xử lý.');
                }
                $connection->prepare('UPDATE applications SET status = \'withdrawn\' WHERE id = ?')->execute([$applicationId]);
                $connection->prepare('INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, note) VALUES (?, ?, \'withdrawn\', ?, \'Sinh viên chủ động rút hồ sơ.\')')->execute([$applicationId, $oldStatus, $user['id']]);
                $connection->commit();
            } catch (Throwable $error) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $error;
            }
            $withdrawn = page_one('SELECT p.title, p.company_id FROM applications a JOIN positions p ON p.id = a.position_id WHERE a.id = ?', [$applicationId]);
            if ($withdrawn) {
                notify_company((int) $withdrawn['company_id'], 'applications', 'Ứng viên rút hồ sơ', $user['full_name'] . ' đã rút hồ sơ vị trí ' . $withdrawn['title'] . '.', 'company/applications');
            }
            $successMessage = 'Đã rút hồ sơ ứng tuyển.';
            break;

        case 'position_save':
            $user = action_user(['company', 'admin']);
            $positionId = filter_var($input['position_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $title = action_value($input, 'title', 200);
            $description = action_value($input, 'description');
            $requirements = action_value($input, 'requirements');
            $benefits = action_value($input, 'benefits');
            $location = action_value($input, 'location', 255);
            $quantity = filter_var($input['quantity'] ?? 1, FILTER_VALIDATE_INT);
            $deadline = action_value($input, 'deadline', 10) ?: null;
            $status = action_value($input, 'status', 20) ?: 'draft';
            if ($title === '' || !$quantity || $quantity < 1 || !in_array($status, ['draft', 'open', 'closed', 'cancelled'], true)) {
                throw new DomainException('Hãy nhập tiêu đề, số lượng hợp lệ và trạng thái vị trí.');
            }
            if ($deadline && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
                throw new DomainException('Hạn nhận hồ sơ không hợp lệ.');
            }
            if ($status === 'open') {
                if ($location === '') {
                    throw new DomainException('Hãy nhập nơi làm việc; nếu làm từ xa, ghi rõ Từ xa.');
                }
                $companyId = (int) ($user['company_id'] ?? 0);
                if ($user['role'] === 'admin' && $positionId) {
                    $positionCompany = page_one('SELECT company_id FROM positions WHERE id = ?', [$positionId]);
                    $companyId = (int) ($positionCompany['company_id'] ?? 0);
                }
                action_require_company_address($companyId);
            }
            if ($user['role'] === 'company') {
                if ($positionId) {
                    $statement = $connection->prepare('UPDATE positions SET title = ?, description = ?, requirements = ?, benefits = ?, location = ?, quantity = ?, deadline = ?, status = ? WHERE id = ? AND company_id = ?');
                    $statement->execute([$title, $description, $requirements, $benefits, $location, $quantity, $deadline, $status, $positionId, $user['company_id']]);
                    if ($statement->rowCount() === 0 && !page_one('SELECT id FROM positions WHERE id = ? AND company_id = ?', [$positionId, $user['company_id']])) {
                        throw new DomainException('Không tìm thấy vị trí để cập nhật.');
                    }
                } else {
                    $connection->prepare('INSERT INTO positions (company_id, title, description, requirements, benefits, location, quantity, deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['company_id'], $title, $description, $requirements, $benefits, $location, $quantity, $deadline, $status]);
                }
            } else {
                if (!$positionId) {
                    throw new DomainException('Quản trị viên cần chọn một vị trí để cập nhật.');
                }
                $connection->prepare('UPDATE positions SET title = ?, description = ?, requirements = ?, benefits = ?, location = ?, quantity = ?, deadline = ?, status = ? WHERE id = ?')->execute([$title, $description, $requirements, $benefits, $location, $quantity, $deadline, $status, $positionId]);
            }
            $successMessage = 'Đã lưu vị trí thực tập.';
            break;

        case 'position_status':
            $user = action_user(['company', 'admin']);
            $positionId = filter_var($input['position_id'] ?? null, FILTER_VALIDATE_INT);
            $status = action_value($input, 'status', 20);
            if (!in_array($status, ['draft', 'open', 'closed', 'cancelled'], true)) {
                throw new DomainException('Trạng thái vị trí không hợp lệ.');
            }
            if ($status === 'open') {
                $companySql = 'SELECT company_id, location FROM positions WHERE id = ?';
                $companyParameters = [$positionId];
                if ($user['role'] === 'company') {
                    $companySql .= ' AND company_id = ?';
                    $companyParameters[] = $user['company_id'];
                }
                $positionCompany = page_one($companySql, $companyParameters);
                if (!$positionCompany) {
                    throw new DomainException('Không tìm thấy vị trí hoặc bạn không có quyền cập nhật.');
                }
                if (trim((string) $positionCompany['location']) === '') {
                    throw new DomainException('Hãy bổ sung địa điểm làm việc trước khi mở tin tuyển dụng.');
                }
                action_require_company_address((int) ($positionCompany['company_id'] ?? 0));
            }
            $sql = 'UPDATE positions SET status = ? WHERE id = ?';
            $parameters = [$status, $positionId];
            if ($user['role'] === 'company') {
                $sql .= ' AND company_id = ?';
                $parameters[] = $user['company_id'];
            }
            $ownerSql = 'SELECT id FROM positions WHERE id = ?' . ($user['role'] === 'company' ? ' AND company_id = ?' : '');
            if (!page_one($ownerSql, array_slice($parameters, 1))) {
                throw new DomainException('Không tìm thấy vị trí hoặc bạn không có quyền cập nhật.');
            }
            $connection->prepare($sql)->execute($parameters);
            $successMessage = 'Đã cập nhật trạng thái vị trí.';
            break;

        case 'task_create':
            $user = action_user(['company', 'lecturer', 'admin']);
            $internshipId = filter_var($input['internship_id'] ?? null, FILTER_VALIDATE_INT);
            $internship = $internshipId ? action_internship($internshipId, $user) : null;
            if (!$internship) {
                throw new DomainException('Hãy chọn một kỳ thực tập được phân công.');
            }
            $title = action_value($input, 'title', 200);
            $priority = action_value($input, 'priority', 20) ?: 'medium';
            if ($title === '' || !in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
                throw new DomainException('Tiêu đề hoặc mức ưu tiên không hợp lệ.');
            }
            $connection->prepare('INSERT INTO tasks (internship_id, title, description, assigned_to, created_by, priority, start_date, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $internshipId,
                $title,
                action_value($input, 'description'),
                $internship['student_id'],
                $user['id'],
                $priority,
                action_value($input, 'start_date', 10) ?: null,
                action_value($input, 'due_date', 10) ?: null,
            ]);
            notify_student((int) $internship['student_id'], 'tasks', 'Nhiệm vụ mới', 'Bạn được giao nhiệm vụ: ' . $title . '.', 'student/tasks');
            $successMessage = 'Đã giao nhiệm vụ.';
            break;

        case 'task_update':
            $user = action_user(['company', 'lecturer', 'admin']);
            $taskId = filter_var($input['task_id'] ?? null, FILTER_VALIDATE_INT);
            $task = $taskId ? page_one('SELECT id, internship_id, assigned_to, title, due_date FROM tasks WHERE id = ?', [$taskId]) : null;
            // Dùng lại phạm vi theo vai trò của action_internship() để chỉ sửa nhiệm vụ thuộc kỳ thực tập của mình.
            if (!$task || !action_internship((int) $task['internship_id'], $user)) {
                throw new DomainException('Không tìm thấy nhiệm vụ.');
            }
            $title = action_value($input, 'title', 200);
            $priority = action_value($input, 'priority', 20);
            $startDate = action_value($input, 'start_date', 10) ?: null;
            $dueDate = action_value($input, 'due_date', 10) ?: null;
            if ($title === '' || !in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
                throw new DomainException('Tiêu đề hoặc mức ưu tiên không hợp lệ.');
            }
            foreach ([$startDate, $dueDate] as $date) {
                if ($date !== null && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || !strtotime($date))) {
                    throw new DomainException('Ngày không hợp lệ.');
                }
            }
            if ($startDate !== null && $dueDate !== null && $dueDate < $startDate) {
                throw new DomainException('Hạn hoàn thành không được trước ngày bắt đầu.');
            }
            $connection->prepare('UPDATE tasks SET title = ?, description = ?, priority = ?, start_date = ?, due_date = ? WHERE id = ?')
                ->execute([$title, action_value($input, 'description'), $priority, $startDate, $dueDate, $taskId]);
            notify_student((int) $task['assigned_to'], 'tasks', 'Nhiệm vụ được chỉnh sửa', 'Nhiệm vụ "' . $title . '" vừa được cập nhật nội dung hoặc hạn hoàn thành.', 'student/tasks');
            $successMessage = 'Đã cập nhật nhiệm vụ.';
            break;

        case 'task_status':
            $user = action_user(['student', 'company', 'lecturer', 'admin']);
            $taskId = filter_var($input['task_id'] ?? null, FILTER_VALIDATE_INT);
            $status = action_value($input, 'status', 20);
            if (!in_array($status, ['todo', 'in_progress', 'submitted', 'completed', 'cancelled'], true)) {
                throw new DomainException('Trạng thái nhiệm vụ không hợp lệ.');
            }
            $task = page_one('SELECT t.*, i.company_id, i.lecturer_id FROM tasks t JOIN internships i ON i.id = t.internship_id WHERE t.id = ?', [$taskId]);
            if (!$task) {
                throw new DomainException('Không tìm thấy nhiệm vụ.');
            }
            $allowed = match ($user['role']) {
                'student' => (int) $task['assigned_to'] === (int) $user['student_id'] && in_array($status, ['in_progress', 'submitted'], true),
                'company' => (int) $task['company_id'] === (int) $user['company_id'] && in_array($status, ['todo', 'in_progress', 'completed', 'cancelled'], true),
                'lecturer' => (int) $task['lecturer_id'] === (int) $user['lecturer_id'] && in_array($status, ['todo', 'in_progress', 'completed', 'cancelled'], true),
                'admin' => true,
            };
            if (!$allowed) {
                throw new DomainException('Bạn không có quyền cập nhật nhiệm vụ này.');
            }
            if ($user['role'] === 'student' && in_array($task['status'], ['completed', 'cancelled'], true)) {
                throw new DomainException('Nhiệm vụ đã được đóng nên không thể cập nhật.');
            }
            if ($user['role'] === 'student' && $status === 'submitted') {
                // Báo hoàn thành phải kèm minh chứng: liên kết (ví dụ Git) và/hoặc tệp (Word, PDF, ZIP...).
                $link = action_value($input, 'submission_link', 500);
                if ($link !== '' && (filter_var($link, FILTER_VALIDATE_URL) === false || preg_match('~^https?://~i', $link) !== 1)) {
                    throw new DomainException('Liên kết minh chứng phải bắt đầu bằng http:// hoặc https://.');
                }
                $uploadedFile = $files['submission_file'] ?? [];
                $hasNewFile = isset($uploadedFile['error']) && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE;
                if ($link === '' && !$hasNewFile && empty($task['submission_link']) && empty($task['submission_file'])) {
                    throw new DomainException('Hãy đính kèm minh chứng hoàn thành: liên kết (ví dụ Git) hoặc tệp (Word, PDF, ZIP...).');
                }
                $newFile = store_uploaded_file($uploadedFile, 'submissions', 20 * 1024 * 1024);
                if ($newFile !== null && !empty($task['submission_file'])) {
                    $oldPath = realpath(dirname(__DIR__) . '/' . $task['submission_file']);
                    $uploadRoot = realpath(dirname(__DIR__) . '/uploads');
                    if ($oldPath && $uploadRoot && str_starts_with($oldPath, $uploadRoot . DIRECTORY_SEPARATOR) && is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }
                $connection->prepare('UPDATE tasks SET status = \'submitted\', completed_at = NULL, submission_link = ?, submission_file = ?, submission_note = ?, submitted_at = NOW() WHERE id = ?')->execute([
                    $link !== '' ? $link : ($task['submission_link'] ?: null),
                    $newFile ?? ($task['submission_file'] ?: null),
                    action_value($input, 'submission_note', 2000) ?: null,
                    $taskId,
                ]);
            } else {
                $connection->prepare('UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?')->execute([$status, $status === 'completed' ? date('Y-m-d H:i:s') : null, $taskId]);
            }
            $taskStatusText = ['todo' => 'được chuyển về cần làm', 'in_progress' => 'đang thực hiện', 'submitted' => 'đã được nộp, chờ phản hồi', 'completed' => 'đã hoàn tất', 'cancelled' => 'đã bị hủy'];
            $taskMessage = 'Nhiệm vụ "' . $task['title'] . '" ' . $taskStatusText[$status] . '.';
            if ($user['role'] === 'student') {
                notify_company((int) $task['company_id'], 'tasks', 'Sinh viên cập nhật nhiệm vụ', $taskMessage, 'company/tasks');
                if (!empty($task['lecturer_id'])) {
                    notify_lecturer((int) $task['lecturer_id'], 'progress', 'Sinh viên cập nhật nhiệm vụ', $taskMessage, 'lecturer/progress');
                }
            } else {
                notify_student((int) $task['assigned_to'], 'tasks', 'Nhiệm vụ được cập nhật', $taskMessage, 'student/tasks');
            }
            $successMessage = 'Đã cập nhật nhiệm vụ.';
            break;

        case 'diary_save':
            $user = action_user(['student']);
            $internshipId = filter_var($input['internship_id'] ?? null, FILTER_VALIDATE_INT);
            $internship = $internshipId ? action_internship($internshipId, $user) : null;
            if (!$internship || !in_array($internship['status'], ['planned', 'active'], true)) {
                throw new DomainException('Không có kỳ thực tập đang nhận nhật ký.');
            }
            $date = action_value($input, 'diary_date', 10) ?: date('Y-m-d');
            $hours = filter_var($input['hours_worked'] ?? 0, FILTER_VALIDATE_FLOAT);
            $title = action_value($input, 'title', 200);
            $content = action_value($input, 'content', 10000);
            $status = action_value($input, 'status', 20) === 'draft' ? 'draft' : 'submitted';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $hours === false || $hours < 0 || $hours > 24 || $content === '') {
                throw new DomainException('Ngày, số giờ hoặc nội dung nhật ký không hợp lệ.');
            }
            $existing = page_one('SELECT id, status FROM diaries WHERE internship_id = ? AND diary_date = ?', [$internshipId, $date]);
            if ($existing && !in_array($existing['status'], ['draft', 'rejected'], true)) {
                throw new DomainException('Nhật ký ngày này đã được gửi và không thể chỉnh sửa.');
            }
            if ($existing) {
                $connection->prepare('UPDATE diaries SET title = ?, content = ?, hours_worked = ?, status = ?, lecturer_feedback = NULL, reviewed_by = NULL, reviewed_at = NULL WHERE id = ?')->execute([$title, $content, $hours, $status, $existing['id']]);
            } else {
                $connection->prepare('INSERT INTO diaries (internship_id, student_id, diary_date, title, content, hours_worked, status) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$internshipId, $user['student_id'], $date, $title, $content, $hours, $status]);
            }
            if ($status === 'submitted' && !empty($internship['lecturer_id'])) {
                notify_lecturer((int) $internship['lecturer_id'], 'diaries', 'Nhật ký mới chờ duyệt', $user['full_name'] . ' đã gửi nhật ký ngày ' . date('d/m/Y', (int) strtotime($date)) . '.', 'lecturer/diaries');
            }
            $successMessage = $status === 'draft' ? 'Đã lưu bản nháp nhật ký.' : 'Đã gửi nhật ký cho giảng viên.';
            break;

        case 'diary_review':
            $user = action_user(['lecturer']);
            $diaryId = filter_var($input['diary_id'] ?? null, FILTER_VALIDATE_INT);
            $status = action_value($input, 'status', 20);
            if (!in_array($status, ['approved', 'rejected'], true)) {
                throw new DomainException('Kết quả duyệt nhật ký không hợp lệ.');
            }
            $statement = $connection->prepare('UPDATE diaries d JOIN internships i ON i.id = d.internship_id SET d.status = ?, d.lecturer_feedback = ?, d.reviewed_by = ?, d.reviewed_at = NOW() WHERE d.id = ? AND i.lecturer_id = ? AND d.status = \'submitted\'');
            $statement->execute([$status, action_value($input, 'feedback', 3000), $user['lecturer_id'], $diaryId, $user['lecturer_id']]);
            if ($statement->rowCount() === 0) {
                throw new DomainException('Nhật ký không tồn tại, chưa gửi hoặc không thuộc nhóm bạn phụ trách.');
            }
            $reviewedDiary = page_one('SELECT student_id, diary_date FROM diaries WHERE id = ?', [$diaryId]);
            if ($reviewedDiary) {
                notify_student((int) $reviewedDiary['student_id'], 'diary', $status === 'approved' ? 'Nhật ký đã được duyệt' : 'Nhật ký cần chỉnh sửa', 'Nhật ký ngày ' . date('d/m/Y', (int) strtotime((string) $reviewedDiary['diary_date'])) . ($status === 'approved' ? ' đã được giảng viên duyệt.' : ' cần chỉnh sửa theo phản hồi của giảng viên.'), 'student/diary');
            }
            $successMessage = $status === 'approved' ? 'Đã duyệt nhật ký.' : 'Đã yêu cầu sinh viên chỉnh sửa nhật ký.';
            break;

        case 'report_save':
            $user = action_user(['student']);
            $internshipId = filter_var($input['internship_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$internshipId || !action_internship($internshipId, $user)) {
                throw new DomainException('Không tìm thấy kỳ thực tập.');
            }
            $type = action_value($input, 'report_type', 20);
            $title = action_value($input, 'title', 255);
            $content = action_value($input, 'content', 20000);
            $status = action_value($input, 'status', 20) === 'draft' ? 'draft' : 'submitted';
            if (!in_array($type, ['proposal', 'midterm', 'final', 'other'], true) || $title === '' || $content === '') {
                throw new DomainException('Loại, tiêu đề và nội dung báo cáo là bắt buộc.');
            }
            $existingReport = page_one('SELECT status FROM reports WHERE internship_id = ? AND report_type = ?', [$internshipId, $type]);
            if ($existingReport && !in_array($existingReport['status'], ['draft', 'rejected'], true)) {
                throw new DomainException('Báo cáo này đã được nộp và không thể chỉnh sửa.');
            }
            $filePath = store_uploaded_file($files['report_file'] ?? [], 'reports', 10 * 1024 * 1024);
            $connection->prepare(
                'INSERT INTO reports (internship_id, student_id, report_type, title, content, file_path, status, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, IF(? = \'submitted\', NOW(), NULL))
                 ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), file_path = COALESCE(VALUES(file_path), file_path), status = VALUES(status), submitted_at = VALUES(submitted_at), feedback = NULL, reviewed_by = NULL, reviewed_at = NULL'
            )->execute([$internshipId, $user['student_id'], $type, $title, $content, $filePath, $status, $status]);
            $reportInternship = action_internship($internshipId, $user);
            if ($status === 'submitted' && !empty($reportInternship['lecturer_id'])) {
                notify_lecturer((int) $reportInternship['lecturer_id'], 'reports', 'Báo cáo mới chờ xem', $user['full_name'] . ' đã nộp báo cáo "' . $title . '".', 'lecturer/reports');
            }
            $successMessage = $status === 'draft' ? 'Đã lưu bản nháp báo cáo.' : 'Đã nộp báo cáo.';
            break;

        case 'report_review':
            $user = action_user(['lecturer']);
            $reportId = filter_var($input['report_id'] ?? null, FILTER_VALIDATE_INT);
            $status = action_value($input, 'status', 20);
            if (!in_array($status, ['approved', 'rejected'], true)) {
                throw new DomainException('Kết quả đánh giá báo cáo không hợp lệ.');
            }
            $statement = $connection->prepare('UPDATE reports r JOIN internships i ON i.id = r.internship_id SET r.status = ?, r.feedback = ?, r.reviewed_by = ?, r.reviewed_at = NOW() WHERE r.id = ? AND i.lecturer_id = ? AND r.status IN (\'submitted\', \'reviewing\')');
            $statement->execute([$status, action_value($input, 'feedback', 5000), $user['lecturer_id'], $reportId, $user['lecturer_id']]);
            if ($statement->rowCount() === 0) {
                throw new DomainException('Báo cáo không tồn tại hoặc không thuộc nhóm bạn phụ trách.');
            }
            $reviewedReport = page_one('SELECT student_id, title FROM reports WHERE id = ?', [$reportId]);
            if ($reviewedReport) {
                notify_student((int) $reviewedReport['student_id'], 'reports', $status === 'approved' ? 'Báo cáo đã được duyệt' : 'Báo cáo cần chỉnh sửa', 'Báo cáo "' . $reviewedReport['title'] . '" ' . ($status === 'approved' ? 'đã được giảng viên duyệt.' : 'cần chỉnh sửa theo phản hồi.'), 'student/reports');
            }
            $successMessage = 'Đã lưu phản hồi báo cáo.';
            break;

        case 'evaluation_save':
            $user = action_user(['company', 'lecturer']);
            $internshipId = filter_var($input['internship_id'] ?? null, FILTER_VALIDATE_INT);
            $internship = $internshipId ? action_internship($internshipId, $user) : null;
            if (!$internship) {
                throw new DomainException('Không tìm thấy kỳ thực tập để đánh giá.');
            }
            $scores = [];
            foreach (['technical_score', 'attitude_score', 'communication_score', 'discipline_score'] as $field) {
                $score = filter_var($input[$field] ?? null, FILTER_VALIDATE_FLOAT);
                if ($score === false || $score < 0 || $score > 100) {
                    throw new DomainException('Mỗi tiêu chí đánh giá phải nằm trong khoảng 0–100.');
                }
                $scores[] = $score;
            }
            $overall = round(array_sum($scores) / count($scores), 2);
            $type = $user['role'];
            $status = action_value($input, 'status', 20) === 'draft' ? 'draft' : 'submitted';
            $connection->prepare(
                'INSERT INTO evaluations (internship_id, evaluator_user_id, evaluator_type, technical_score, attitude_score, communication_score, discipline_score, overall_score, comments, status, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = \'submitted\', NOW(), NULL))
                 ON DUPLICATE KEY UPDATE technical_score = VALUES(technical_score), attitude_score = VALUES(attitude_score), communication_score = VALUES(communication_score), discipline_score = VALUES(discipline_score), overall_score = VALUES(overall_score), comments = VALUES(comments), status = VALUES(status), submitted_at = VALUES(submitted_at)'
            )->execute([$internshipId, $user['id'], $type, ...$scores, $overall, action_value($input, 'comments', 5000), $status, $status]);
            if ($status === 'submitted') {
                notify_student((int) $internship['student_id'], 'evaluation', 'Bạn có đánh giá mới', ($type === 'company' ? 'Doanh nghiệp' : 'Giảng viên') . ' vừa gửi đánh giá kỳ thực tập của bạn.', 'student/evaluation');
            }
            $successMessage = $status === 'draft' ? 'Đã lưu bản nháp đánh giá.' : 'Đã gửi đánh giá.';
            break;

        case 'profile_save':
            $user = action_user(['student', 'company', 'lecturer', 'admin']);
            $fullName = action_value($input, 'full_name', 150);
            $phone = action_value($input, 'phone', 20);
            $companyAddress = $user['role'] === 'company' ? action_value($input, 'address', 255) : '';
            if ($fullName === '' || ($phone !== '' && !preg_match('/^[0-9+() .-]{8,20}$/', $phone))) {
                throw new DomainException('Họ tên hoặc số điện thoại không hợp lệ.');
            }
            if ($user['role'] === 'company' && $companyAddress === '') {
                throw new DomainException('Hãy nhập địa chỉ đầy đủ của doanh nghiệp tại Vinh hoặc nơi doanh nghiệp hoạt động.');
            }
            $connection->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')->execute([$fullName, $phone ?: null, $user['id']]);
            if ($user['role'] === 'student') {
                // Tài khoản được tạo trực tiếp trong bảng users có thể chưa có hồ sơ sinh viên: tạo hồ sơ tối thiểu.
                $connection->prepare('INSERT IGNORE INTO students (user_id, student_code) VALUES (?, ?)')->execute([$user['id'], 'SV' . str_pad((string) $user['id'], 6, '0', STR_PAD_LEFT)]);
                $cvPath = store_uploaded_file($files['cv_file'] ?? [], 'cv', 5 * 1024 * 1024);
                $avatarPath = store_uploaded_file($files['avatar_file'] ?? [], 'avatars', 3 * 1024 * 1024);
                $connection->prepare('UPDATE users SET avatar = COALESCE(?, avatar) WHERE id = ?')->execute([$avatarPath, $user['id']]);
                $connection->prepare('UPDATE students SET address = ?, bio = ?, cv_file = COALESCE(?, cv_file) WHERE user_id = ?')->execute([action_value($input, 'address', 255), action_value($input, 'bio', 5000), $cvPath, $user['id']]);
            } else {
                $avatarPath = store_uploaded_file($files['avatar_file'] ?? [], 'avatars', 3 * 1024 * 1024);
                $connection->prepare('UPDATE users SET avatar = COALESCE(?, avatar) WHERE id = ?')->execute([$avatarPath, $user['id']]);
            }
            if ($user['role'] === 'lecturer') {
                $connection->prepare('UPDATE lecturers SET department = ? WHERE user_id = ?')->execute([action_value($input, 'department', 150) ?: null, $user['id']]);
            } elseif ($user['role'] === 'company') {
                $connection->prepare('UPDATE companies SET website = ?, email = ?, phone = ?, address = ?, description = ? WHERE user_id = ?')->execute([
                    action_value($input, 'website', 255) ?: null,
                    action_value($input, 'company_email', 255) ?: null,
                    $phone ?: null,
                    $companyAddress,
                    action_value($input, 'description', 10000) ?: null,
                    $user['id'],
                ]);
            }
            $successMessage = 'Đã cập nhật hồ sơ.';
            break;

        case 'company_status':
            action_user(['admin']);
            $companyId = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT);
            $status = action_value($input, 'status', 20);
            if (!in_array($status, ['pending', 'active', 'inactive', 'rejected'], true)) {
                throw new DomainException('Trạng thái doanh nghiệp không hợp lệ.');
            }
            $connection->prepare('UPDATE companies SET status = ? WHERE id = ?')->execute([$status, $companyId]);
            $successMessage = 'Đã cập nhật trạng thái doanh nghiệp.';
            break;

        case 'company_update':
            action_user(['admin']);
            $companyId = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT);
            $status = action_value($input, 'status', 20);
            if (!$companyId || !page_one('SELECT id FROM companies WHERE id = ?', [$companyId])) {
                throw new DomainException('Không tìm thấy doanh nghiệp.');
            }
            if (!in_array($status, ['pending', 'active', 'inactive', 'rejected'], true)) {
                throw new DomainException('Trạng thái doanh nghiệp không hợp lệ.');
            }
            $connection->prepare('UPDATE companies SET status = ? WHERE id = ?')->execute([$status, $companyId]);
            $successMessage = 'Đã cập nhật doanh nghiệp.';
            break;

        case 'user_status':
            $user = action_user(['admin']);
            $targetId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
            $status = action_value($input, 'status', 20);
            if ((int) $targetId === (int) $user['id'] || !in_array($status, ['active', 'inactive', 'locked'], true)) {
                throw new DomainException('Không thể thay đổi trạng thái tài khoản này.');
            }
            $connection->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $targetId]);
            $successMessage = 'Đã cập nhật tài khoản.';
            break;

        case 'internship_update':
            $user = action_user(['admin', 'lecturer']);
            $internshipId = filter_var($input['internship_id'] ?? null, FILTER_VALIDATE_INT);
            $status = action_value($input, 'status', 20);
            $assignLecturer = $user['role'] === 'admin' && array_key_exists('lecturer_id', $input);
            $lecturerId = $assignLecturer ? (filter_var($input['lecturer_id'], FILTER_VALIDATE_INT) ?: null) : null;
            // Kế hoạch thực tập do nhà trường phân công: chỉ quản trị viên được sửa; các vai trò khác chỉ xem.
            $trainingPlan = $user['role'] === 'admin' && array_key_exists('training_plan', $input) ? action_value($input, 'training_plan', 20000) : null;
            if (!in_array($status, ['planned', 'active', 'completed', 'cancelled'], true)) {
                throw new DomainException('Trạng thái kỳ thực tập không hợp lệ.');
            }
            if ($assignLecturer && $lecturerId !== null && !page_one('SELECT id FROM lecturers WHERE id = ?', [$lecturerId])) {
                throw new DomainException('Giảng viên được chọn không tồn tại.');
            }
            $scopeSql = $user['role'] === 'lecturer' ? ' AND lecturer_id = ?' : '';
            $parameters = [$internshipId];
            if ($scopeSql !== '') {
                $parameters[] = $user['lecturer_id'];
            }
            $connection->beginTransaction();
            try {
                $statement = $connection->prepare('SELECT status, lecturer_id FROM internships WHERE id = ?' . $scopeSql . ' FOR UPDATE');
                $statement->execute($parameters);
                $internship = $statement->fetch();
                if (!$internship) {
                    throw new DomainException('Không tìm thấy kỳ thực tập hoặc bạn không phụ trách kỳ này.');
                }
                $oldStatus = $internship['status'];
                $setParts = ['status = ?'];
                $parameters = [$status];
                if ($assignLecturer) {
                    $setParts[] = 'lecturer_id = ?';
                    $parameters[] = $lecturerId;
                }
                if ($trainingPlan !== null) {
                    $setParts[] = 'training_plan = ?';
                    $parameters[] = $trainingPlan === '' ? null : $trainingPlan;
                }
                $parameters[] = $internshipId;
                $connection->prepare('UPDATE internships SET ' . implode(', ', $setParts) . ' WHERE id = ?')->execute($parameters);
                if ($oldStatus !== $status) {
                    $historyNote = action_value($input, 'note', 2000) ?: ($trainingPlan !== null ? 'Cập nhật kế hoạch thực tập cho kỳ thực tập.' : null);
                    $connection->prepare('INSERT INTO internship_status_history (internship_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)')->execute([$internshipId, $oldStatus, $status, $user['id'], $historyNote]);
                }
                $connection->commit();
            } catch (Throwable $error) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $error;
            }
            if ($assignLecturer && $lecturerId !== null && (int) ($internship['lecturer_id'] ?? 0) !== $lecturerId) {
                notify_lecturer($lecturerId, 'students', 'Được phân công sinh viên mới', 'Bạn được phân công phụ trách một kỳ thực tập mới.', 'lecturer/students');
            }
            $successMessage = $assignLecturer ? 'Đã cập nhật trạng thái và giảng viên phụ trách.' : 'Đã cập nhật kỳ thực tập.';
            break;

        case 'internship_major_plan':
            action_user(['admin']);
            $major = action_value($input, 'major', 150);
            $trainingPlan = action_value($input, 'training_plan', 20000);
            if ($major === '') {
                throw new DomainException('Chưa chọn ngành để giao kế hoạch.');
            }
            // Chỉ áp dụng cho kỳ thực tập chưa kết thúc của sinh viên thuộc ngành này.
            $targets = page_all('SELECT i.id, i.student_id FROM internships i JOIN students s ON s.id = i.student_id WHERE TRIM(s.major) = ? AND i.status IN (\'planned\', \'active\')', [$major]);
            if (!$targets) {
                throw new DomainException('Ngành này chưa có kỳ thực tập nào đang chạy hoặc sắp bắt đầu để giao kế hoạch.');
            }
            database_transaction(static function (PDO $transaction) use ($targets, $trainingPlan): void {
                $update = $transaction->prepare('UPDATE internships SET training_plan = ? WHERE id = ?');
                foreach ($targets as $target) {
                    $update->execute([$trainingPlan === '' ? null : $trainingPlan, $target['id']]);
                }
            });
            if ($trainingPlan !== '') {
                foreach ($targets as $target) {
                    notify_student((int) $target['student_id'], 'internships', 'Kế hoạch thực tập mới', 'Nhà trường vừa cập nhật kế hoạch thực tập chung cho ngành ' . $major . '.', 'student/internships');
                }
            }
            $successMessage = 'Đã giao kế hoạch chung cho ' . count($targets) . ' kỳ thực tập ngành ' . $major . '.';
            break;

        case 'user_create':
            action_user(['admin']);
            $username = action_value($input, 'username', 50);
            $email = action_value($input, 'email', 255);
            $fullName = action_value($input, 'full_name', 150);
            $password = action_value($input, 'password', 200);
            $role = action_value($input, 'role', 20);
            if (!in_array($role, ['student', 'company', 'lecturer'], true) || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $fullName === '' || strlen($password) < 10) {
                throw new DomainException('Hãy kiểm tra vai trò, họ tên, email và mật khẩu tối thiểu 10 ký tự.');
            }
            if (page_one('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email])) {
                throw new DomainException('Tên đăng nhập hoặc email đã tồn tại.');
            }
            $connection->beginTransaction();
            try {
                $connection->prepare('INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?, \'active\')')->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, $role]);
                $newUserId = (int) $connection->lastInsertId();
                if ($role === 'student') {
                    $studentCode = action_value($input, 'student_code', 30);
                    if ($studentCode === '') {
                        throw new DomainException('Mã sinh viên là bắt buộc.');
                    }
                    $connection->prepare('INSERT INTO students (user_id, student_code, major, class_name, faculty, university) VALUES (?, ?, ?, ?, ?, ?)')->execute([$newUserId, $studentCode, action_value($input, 'major', 150), action_value($input, 'class_name', 100), action_value($input, 'faculty', 150), action_value($input, 'university', 200)]);
                } elseif ($role === 'company') {
                    $companyCode = action_value($input, 'company_code', 30);
                    $companyName = action_value($input, 'company_name', 200);
                    if ($companyCode === '' || $companyName === '') {
                        throw new DomainException('Mã và tên doanh nghiệp là bắt buộc.');
                    }
                    if (preg_match('/^\d{10}$/', $companyCode) !== 1) {
                        throw new DomainException('Mã doanh nghiệp phải gồm đúng 10 chữ số.');
                    }
                    if (page_one('SELECT id FROM companies WHERE company_code = ?', [$companyCode])) {
                        throw new DomainException('Mã doanh nghiệp này đã được sử dụng.');
                    }
                    $connection->prepare('INSERT INTO companies (user_id, company_code, company_name, email, status) VALUES (?, ?, ?, ?, \'pending\')')->execute([$newUserId, $companyCode, $companyName, $email]);
                } else {
                    $lecturerCode = action_value($input, 'lecturer_code', 30);
                    if ($lecturerCode === '') {
                        throw new DomainException('Mã giảng viên là bắt buộc.');
                    }
                    $connection->prepare('INSERT INTO lecturers (user_id, lecturer_code, department) VALUES (?, ?, ?)')->execute([$newUserId, $lecturerCode, action_value($input, 'department', 150)]);
                }
                $connection->commit();
            } catch (Throwable $error) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $error;
            }
            $successMessage = 'Đã tạo tài khoản và hồ sơ vai trò.';
            break;

        default:
            throw new DomainException('Thao tác không được hỗ trợ.');
    }

    app_set_flash('success', $successMessage);
    return $route;
}
