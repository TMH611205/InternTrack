<?php
// Hàm hiển thị dùng chung cho các màn hình kiểu "custom" (views/layouts/screens/*.php).
// Mỗi màn hình require tệp này; các biến $screen, $screenData, $user, $formAction, $csrfField có sẵn từ content.php / forms.php.

// Địa chỉ gửi form giữ nguyên đợt đang xem để báo lỗi và chuyển hướng quay về đúng chỗ.
$xAction = '?page=' . rawurlencode($screen) . (!empty($screenData['batch']['id']) ? '&batch=' . (int) $screenData['batch']['id'] : '');

// Form POST gọn một nút (xác nhận trước khi gửi nếu có $confirm).
$xPost = static function (string $action, array $hidden, string $label, string $class = 'inline-action', ?string $confirm = null, string $buttonClass = '') use ($xAction, $csrfField): string {
    $fields = '';
    foreach ($hidden as $name => $value) {
        $fields .= '<input type="hidden" name="' . screen_escape($name) . '" value="' . screen_escape($value) . '">';
    }
    return '<form method="post" action="' . screen_escape($xAction) . '" class="' . screen_escape($class) . '"' . ($confirm ? ' onsubmit="return confirm(' . screen_escape(json_encode($confirm, JSON_UNESCAPED_UNICODE)) . ');"' : '') . '>'
        . $csrfField . '<input type="hidden" name="action" value="' . screen_escape($action) . '">' . $fields
        . '<button type="submit"' . ($buttonClass ? ' class="' . screen_escape($buttonClass) . '"' : '') . '>' . screen_escape($label) . '</button></form>';
};

if (!function_exists('x_badge')) {
    function x_badge(string $text, ?string $class = null): string
    {
        return '<span class="badge ' . ($class ?? screen_badge_class($text)) . '">' . screen_escape($text) . '</span>';
    }

    // Giá trị cho ô <input type="datetime-local">.
    function x_input_datetime(?string $value): string
    {
        return $value ? date('Y-m-d\TH:i', (int) strtotime($value)) : '';
    }

    // Bộ chọn đợt (GET): đổi đợt là tải lại trang với ?batch=.
    function x_batch_switcher(array $batches, ?array $current, string $screen, array $extra = []): string
    {
        if (!$batches) {
            return '';
        }
        $html = '<form method="get" class="batch-switcher"><input type="hidden" name="page" value="' . screen_escape($screen) . '">';
        foreach ($extra as $name => $value) {
            $html .= '<input type="hidden" name="' . screen_escape($name) . '" value="' . screen_escape($value) . '">';
        }
        $html .= '<label>Đợt<select name="batch" onchange="this.form.submit()">';
        foreach ($batches as $batch) {
            $html .= '<option value="' . (int) $batch['id'] . '"' . ((int) ($current['id'] ?? 0) === (int) $batch['id'] ? ' selected' : '') . '>' . screen_escape($batch['name'] . ' · ' . ['open' => 'đang mở', 'draft' => 'nháp', 'closed' => 'đã đóng'][$batch['status']]) . '</option>';
        }
        return $html . '</select></label><noscript><button type="submit">Xem</button></noscript></form>';
    }

    // Biểu đồ thanh ngang đơn giản: [nhãn => giá trị].
    function x_bars(array $values, string $suffix = ''): string
    {
        $maximum = max(1, ...array_values($values ?: [0]));
        $html = '<div class="x-bars">';
        foreach ($values as $label => $value) {
            $html .= '<div class="x-bar"><span class="x-bar-label">' . screen_escape((string) $label) . '</span><span class="x-bar-track"><span style="width:' . round($value * 100 / $maximum, 1) . '%"></span></span><strong>' . screen_escape($value . $suffix) . '</strong></div>';
        }
        return $html . '</div>';
    }

    function x_empty(string $text): string
    {
        return '<p class="x-empty">' . screen_escape($text) . '</p>';
    }
}
