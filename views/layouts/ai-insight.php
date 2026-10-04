<?php

declare(strict_types=1);

// Hiển thị kết quả phân tích của AI (tóm tắt nhật ký/báo cáo, rủi ro, nhận xét cuối kỳ, kỹ năng).
// Mọi giá trị đều được escape; dữ liệu do AI sinh ra không bao giờ được chèn thô vào HTML.

if (!function_exists('ai_e')) {

    // Escape HTML cho nội dung AI sinh ra (không bao giờ chèn thô vào trang).
    function ai_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Màu huy hiệu của cờ cảnh báo: "Tốt" xanh, còn lại cảnh báo.
    function ai_flag_class(string $flag): string
    {
        return $flag === 'Tốt' ? 'badge--positive' : 'badge--attention';
    }

    // Có cờ cảnh báo nào ngoài "Tốt" không (dùng để tô đỏ nút AI).
    function ai_review_has_problem(?array $insight): bool
    {
        return $insight !== null && array_diff((array) ($insight['flags'] ?? []), ['Tốt']) !== [];
    }

    // Nội dung tóm tắt + điểm sơ bộ + cờ cảnh báo của một nhật ký/báo cáo.
    function ai_review_html(?array $insight): string
    {
        if ($insight === null) {
            return '<p class="ai-empty">Chưa có phân tích. Bấm nút bên dưới để AI tóm tắt và chấm sơ bộ.</p>';
        }
        $html = '<div class="ai-result"><div class="ai-result-head"><span class="ai-score">' . (int) $insight['score'] . '<small>/100</small></span><div class="ai-flags">';
        foreach ((array) $insight['flags'] as $flag) {
            $html .= '<span class="badge ' . ai_flag_class((string) $flag) . '">' . ai_e($flag) . '</span>';
        }
        $html .= '</div></div><p class="ai-summary">' . ai_e($insight['summary']) . '</p>';
        if (!empty($insight['note'])) {
            $html .= '<p class="ai-note"><strong>Gợi ý cho giảng viên:</strong> ' . ai_e($insight['note']) . '</p>';
        }
        if (!empty($insight['similarity'])) {
            $html .= '<p class="ai-meta">Độ tương đồng với nhật ký trước: ' . (int) $insight['similarity'] . '%</p>';
        }
        return $html . '<p class="ai-meta">AI chấm sơ bộ, giảng viên quyết định cuối cùng · ' . ai_e($insight['updated_at'] ?? '') . '</p></div>';
    }

    // Nhãn tiếng Việt của mức nguy cơ (high / medium / low).
    function ai_risk_label(string $level): string
    {
        return ['high' => 'Nguy cơ cao', 'medium' => 'Cần theo dõi', 'low' => 'Ổn định'][$level] ?? 'Ổn định';
    }

    // Màu huy hiệu của mức nguy cơ.
    function ai_risk_class(string $level): string
    {
        return ['high' => 'badge--attention', 'medium' => 'badge--waiting', 'low' => 'badge--positive'][$level] ?? 'badge--neutral';
    }

    // Nhận xét cuối kỳ do AI soạn (khung văn bản chỉ đọc + điểm mạnh / cần cải thiện).
    function ai_final_html(?array $insight): string
    {
        if ($insight === null) {
            return '<p class="ai-empty">Chưa có bản tổng hợp. Cần ít nhất một đánh giá đã gửi, sau đó bấm nút bên dưới.</p>';
        }
        $html = '<div class="ai-result"><textarea class="ai-final-text" rows="8" readonly aria-label="Nhận xét cuối kỳ do AI soạn">' . ai_e($insight['comment']) . '</textarea>';
        foreach (['strengths' => 'Điểm mạnh', 'improvements' => 'Cần cải thiện'] as $key => $label) {
            if (!empty($insight[$key])) {
                $html .= '<p class="ai-list-title">' . $label . '</p><ul class="ai-list">';
                foreach ((array) $insight[$key] as $item) {
                    $html .= '<li>' . ai_e($item) . '</li>';
                }
                $html .= '</ul>';
            }
        }
        return $html . '<p class="ai-meta">Bản nháp do AI soạn từ các đánh giá đã gửi; hãy đọc lại và chỉnh sửa trước khi dùng · ' . ai_e($insight['updated_at'] ?? '') . '</p></div>';
    }

    // Biểu đồ cột ngang các kỹ năng doanh nghiệp đang cần.
    function ai_skills_html(?array $insight): string
    {
        if ($insight === null) {
            return '<p class="ai-empty">Chưa có thống kê. Bấm "Phân tích bằng AI" để AI đọc các tin tuyển dụng và liệt kê kỹ năng được yêu cầu nhiều nhất.</p>';
        }
        $max = max(1, ...array_map(static fn($skill) => (int) $skill['count'], $insight['skills']));
        $html = '<div class="skill-bars">';
        foreach ($insight['skills'] as $skill) {
            $html .= '<div class="skill-bar"><span class="skill-name">' . ai_e($skill['skill']) . '</span><span class="skill-track"><i style="width:' . (int) round((int) $skill['count'] * 100 / $max) . '%"></i></span><span class="skill-count">' . (int) $skill['count'] . '</span></div>';
        }
        $html .= '</div><p class="ai-summary">' . ai_e($insight['summary']) . '</p>';
        if (!empty($insight['suggestions'])) {
            $html .= '<p class="ai-list-title">Gợi ý điều chỉnh chương trình</p><ul class="ai-list">';
            foreach ($insight['suggestions'] as $item) {
                $html .= '<li>' . ai_e($item) . '</li>';
            }
            $html .= '</ul>';
        }
        return $html . '<p class="ai-meta">Tổng hợp từ ' . (int) ($insight['position_count'] ?? 0) . ' tin tuyển dụng · ' . ai_e($insight['updated_at'] ?? '') . '</p>';
    }
}
