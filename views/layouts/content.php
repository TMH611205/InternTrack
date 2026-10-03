<?php
// Layout tổng quát dùng để render mọi màn hình chính của hệ thống.
// Dựa trên `kind`, chúng ta sẽ hiển thị theo mẫu dashboard, table, kanban, timeline, cards, profile hoặc detail.
global $screenData, $user, $flash, $screen;
$screenData = is_array($screenData ?? null) ? $screenData : [];
$flash = $flash ?? null;
$user = is_array($user ?? null) ? $user : [];
$screen = $screen ?? '';
$screenData += [
    'kind' => 'dashboard',
    'eyebrow' => 'Tổng quan',
    'title' => 'InternTrack',
    'description' => 'Hệ thống quản lý kỳ thực tập.',
    'progress' => ['label' => 'Tiến độ', 'value' => '0%', 'note' => ''],
    'metrics' => [],
    'bars' => [],
    'activities' => [],
    'columns' => [],
    'rows' => [],
    'boards' => [],
    'cards' => [],
    'fields' => [],
    'criteria' => [],
    'members' => [],
    'profile_record' => [],
    'lecturer_options' => [],
    'row_ids' => [],
    'row_cv_paths' => [],
    'row_student_ids' => [],
    'row_lecturer_ids' => [],
    'application_status' => null,
    'position_id' => null,
];
$user += ['id' => 0, 'full_name' => 'Người dùng', 'avatar' => null, 'student_id' => 0, 'cv_file' => null];
$kind = $screenData['kind'];
// Nút thao tác chính ở đầu trang, tùy theo role và màn hình đang hoạt động.
$primaryActions = [
    'student/dashboard' => ['label' => 'Tìm cơ hội', 'href' => '?page=student/internships'],
    'student/applications' => ['label' => 'Khám phá vị trí', 'href' => '?page=student/internships'],
    'student/diary' => ['label' => 'Viết nhật ký', 'href' => '#new-diary'],
    'company/dashboard' => ['label' => 'Đăng vị trí mới', 'href' => '?page=company/positions'],
    'company/applications' => ['label' => 'Xem vị trí tuyển', 'href' => '?page=company/positions'],
    'lecturer/dashboard' => ['label' => 'Xem sinh viên', 'href' => '?page=lecturer/students'],
    'admin/dashboard' => ['label' => 'Quản lý sinh viên', 'href' => '?page=admin/students'],
];
$primaryActions['company/tasks'] = ['label' => 'Giao nhiệm vụ', 'href' => '#new-task'];
if ($screen === 'student/internship-detail' && !empty($screenData['position_id']) && empty($screenData['application_status'])) {
    $primaryActions[$screen] = ['label' => 'Ứng tuyển vị trí', 'href' => '#application-form'];
}
if ($screen === 'student/diary' && empty($screenData['internship_id'])) {
    unset($primaryActions[$screen]);
}
$primaryAction = $primaryActions[$screen] ?? null;
?>
<div class="page-wrap">
    <!-- Phần tiêu đề của từng màn hình, có thể chứa nút thao tác chính như "Ứng tuyển", "Giao nhiệm vụ"... -->
    <section class="page-heading">
        <div>
            <p class="eyebrow"><?= screen_escape($screenData['eyebrow']) ?></p>
            <h1><?= screen_escape($screenData['title']) ?></h1>
            <p class="page-description"><?= screen_escape($screenData['description']) ?></p>
        </div>
        <?php if ($primaryAction !== null): ?>
            <?php if (isset($primaryAction['toast'])): ?>
                <button class="button button--primary" type="button" data-toast="<?= screen_escape($primaryAction['toast']) ?>"><?= screen_escape($primaryAction['label']) ?><span aria-hidden="true">↗</span></button>
            <?php else: ?>
                <a class="button button--primary" href="<?= screen_escape($primaryAction['href']) ?>"><?= screen_escape($primaryAction['label']) ?><span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($flash !== null): ?><div class="flash-message flash--<?= screen_escape($flash['type']) ?>" role="status"><?= screen_escape($flash['message']) ?></div><?php endif; ?>
    <?php if (!empty($sectionNotices)): ?>
        <section class="section-notices" aria-label="Thông báo của mục này">
            <div class="section-notices-head">
                <strong><?= count($sectionNotices) ?> thông báo mới ở mục này</strong>
                <?php if (count($sectionNotices) > 1): ?><form method="post" action="?page=<?= screen_escape(rawurlencode($screen)) ?>"><input type="hidden" name="_csrf" value="<?= screen_escape(app_csrf_token()) ?>"><input type="hidden" name="action" value="notification_read"><input type="hidden" name="section" value="<?= screen_escape($sectionKey) ?>"><button type="submit" class="link-button">Đã đọc tất cả</button></form><?php endif; ?>
            </div>
            <?php foreach ($sectionNotices as $notice): ?>
                <article class="section-notice">
                    <span class="section-notice-icon"><?= ui_icon('bell') ?></span>
                    <div><strong><?= screen_escape($notice['title']) ?></strong><?php if (!empty($notice['message'])): ?><p><?= screen_escape($notice['message']) ?></p><?php endif; ?><small><?= screen_escape(notification_time((string) $notice['created_at'])) ?></small></div>
                    <form method="post" action="?page=<?= screen_escape(rawurlencode($screen)) ?>"><input type="hidden" name="_csrf" value="<?= screen_escape(app_csrf_token()) ?>"><input type="hidden" name="action" value="notification_read"><input type="hidden" name="notification_id" value="<?= (int) $notice['id'] ?>"><button type="submit" class="link-button">Đã xem</button></form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/forms.php'; ?>

    <?php if (array_key_exists('plan', $screenData)): $plan = $screenData['plan']; ?>
        <section class="panel plan-panel" aria-label="Kế hoạch thực tập">
            <div class="plan-panel-head">
                <div><p class="eyebrow">Kế hoạch thực tập</p><h2><?= $plan ? screen_escape($plan['heading']) : 'Chưa có kỳ thực tập' ?></h2><?php if ($plan): ?><p class="modal-sub"><?= screen_escape($plan['meta']) ?></p><?php endif; ?></div>
                <span class="badge badge--neutral">Chỉ xem</span>
            </div>
            <?php if ($plan && $plan['text'] !== ''): ?><div class="plan-text"><?= screen_escape($plan['text']) ?></div>
            <?php else: ?><p class="plan-empty"><?= $plan ? 'Nhà trường chưa nhập kế hoạch cho kỳ thực tập này.' : 'Kế hoạch sẽ hiện ở đây khi bạn được doanh nghiệp nhận thực tập.' ?></p><?php endif; ?>
            <p class="plan-note">Kế hoạch do nhà trường phân công. Doanh nghiệp có thể giao thêm nhiệm vụ ngoài kế hoạch này ở mục Nhiệm vụ.</p>
        </section>
    <?php endif; ?>

    <?php if (!empty($screenData['metrics'])): ?>
        <section class="metric-grid" aria-label="Chỉ số tổng quan">
            <?php foreach ($screenData['metrics'] as $index => $metric): ?>
                <article class="metric-item metric-item--<?= $index + 1 ?>">
                    <span class="metric-label"><?= screen_escape($metric['label']) ?></span>
                    <strong class="metric-value"><?= screen_escape($metric['value']) ?></strong>
                    <span class="metric-note"><?= screen_escape($metric['note']) ?></span>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($kind === 'dashboard'): ?>
        <section class="dashboard-grid">
            <article class="panel progress-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Bức tranh tuần này</p>
                        <h2><?= screen_escape($screenData['progress']['label']) ?></h2>
                    </div>
                    <span class="progress-number"><?= screen_escape($screenData['progress']['value']) ?></span>
                </div>
                <div class="progress-track"><span style="width: <?= screen_escape($screenData['progress']['value']) ?>"></span></div>
                <p class="muted"><?= screen_escape($screenData['progress']['note']) ?></p>
                <div class="chart-area" role="img" aria-label="Biểu đồ hoạt động bảy ngày gần đây">
                    <?php foreach ($screenData['bars'] as $index => $bar): ?>
                        <div class="chart-column"><span style="height: <?= (int) $bar ?>%"></span><small><?= ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'][$index] ?></small></div>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="panel activity-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Dòng hoạt động</p>
                        <h2>Gần đây</h2>
                    </div><span class="panel-index">0<?= count($screenData['activities']) ?></span>
                </div>
                <div class="activity-list">
                    <?php foreach ($screenData['activities'] as $activity): ?>
                        <div class="activity-row"><span class="activity-dot"></span>
                            <div class="activity-copy">
                                <div class="activity-topline"><strong><?= screen_escape($activity['title']) ?></strong><span class="badge <?= screen_badge_class($activity['status']) ?>"><?= screen_escape($activity['status']) ?></span></div>
                                <p><?= screen_escape($activity['description']) ?></p><small><?= screen_escape($activity['time']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>
        <?php if ($screen === 'admin/dashboard' && ai_match_enabled()): ?>
            <section class="panel skill-panel">
                <div class="panel-heading">
                    <div><p class="eyebrow">Theo nhu cầu doanh nghiệp</p><h2>Kỹ năng doanh nghiệp đang cần</h2></div>
                    <form method="post" action="<?= screen_escape($formAction) ?>"><?= $csrfField ?><input type="hidden" name="action" value="ai_skill_stats"><button class="button button--primary" type="submit" data-ai-submit><?= !empty($screenData['skill_stats']) ? 'Phân tích lại' : 'Phân tích bằng AI' ?></button></form>
                </div>
                <?= ai_skills_html($screenData['skill_stats'] ?? null) ?>
            </section>
        <?php endif; ?>

    <?php elseif ($kind === 'table'): ?>
        <?php if ($screen === 'admin/internship' && !empty($screenData['major_groups'])): ?>
            <section class="panel major-panel">
                <div class="major-head">
                    <div><p class="eyebrow">Theo ngành · <?= count($screenData['major_groups']) ?> ngành</p><h2>Kế hoạch chung theo ngành</h2></div>
                    <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" data-major-search placeholder="Tìm ngành" aria-label="Tìm ngành"></label>
                </div>
                <div class="major-list" data-major-list>
                    <?php foreach ($screenData['major_groups'] as $groupIndex => $group): ?>
                        <article class="major-row">
                            <div class="major-row-info"><strong><?= screen_escape($group['major'] ?: 'Chưa cập nhật ngành') ?></strong><span><?= (int) $group['students'] ?> sinh viên · <?= (int) $group['count'] ?> kỳ thực tập đang chạy hoặc sắp bắt đầu</span></div>
                            <div class="major-card-actions">
                                <button type="button" class="table-edit-button" data-major-filter="<?= screen_escape($group['major'] ?: 'Chưa cập nhật') ?>">Xem danh sách</button>
                                <?php if ($group['major'] !== ''): ?><button type="button" class="table-edit-button" data-dialog-open="major-plan-<?= (int) $groupIndex ?>">Giao kế hoạch</button><?php endif; ?>
                            </div>
                            <?php if ($group['major'] !== ''): ?>
                                <dialog class="modal" id="major-plan-<?= (int) $groupIndex ?>" aria-label="Kế hoạch chung theo ngành">
                                    <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="internship_major_plan"><input type="hidden" name="major" value="<?= screen_escape($group['major']) ?>">
                                        <header class="modal-head"><div><p class="eyebrow">Kế hoạch chung</p><h2><?= screen_escape($group['major']) ?></h2><p class="modal-sub">Áp dụng cho <?= (int) $group['count'] ?> kỳ thực tập đang chạy hoặc sắp bắt đầu của <?= (int) $group['students'] ?> sinh viên ngành này</p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                        <label>Kế hoạch thực tập<textarea name="training_plan" rows="8" maxlength="20000" placeholder="Mục tiêu, lộ trình theo tuần, yêu cầu đầu ra..."><?= screen_escape($group['plan']) ?></textarea></label>
                                        <p class="form-hint">Kế hoạch mới sẽ thay thế kế hoạch hiện có của từng sinh viên trong ngành.</p>
                                        <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Giao cho cả ngành</button></div>
                                    </form>
                                </dialog>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <p class="major-empty" data-major-empty hidden>Không có ngành phù hợp.</p>
                </div>
            </section>
        <?php endif; ?>
        <section class="panel table-panel">
            <?php if ($screen === 'admin/users' && !empty($screenData['groups'])): ?>
                <nav class="group-tabs" aria-label="Nhóm tài khoản">
                    <?php foreach ($screenData['groups'] as $groupTab): ?>
                        <a class="group-tab <?= $screenData['group'] === $groupTab['key'] ? 'is-active' : '' ?>" href="?page=admin/users&amp;group=<?= screen_escape($groupTab['key']) ?>" <?= $screenData['group'] === $groupTab['key'] ? 'aria-current="page"' : '' ?>><?= screen_escape($groupTab['label']) ?><span><?= (int) $groupTab['count'] ?></span></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div class="table-toolbar">
                <div>
                    <p class="eyebrow">Danh sách</p>
                    <h2><?= screen_escape($screenData['title']) ?></h2>
                </div>
                <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" data-table-search placeholder="Tìm trong danh sách" aria-label="Tìm trong danh sách"></label>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><?php foreach ($screenData['columns'] as $column): ?><th><?= screen_escape($column) ?></th><?php endforeach; ?><th><span class="visually-hidden">Thao tác</span></th>
                        </tr>
                    </thead>
                    <tbody data-table-body>
                        <?php foreach ($screenData['rows'] as $rowIndex => $row): ?>
                            <?php $recordId = $screenData['row_ids'][$rowIndex] ?? null; ?>
                            <tr><?php foreach ($row as $cellIndex => $cell): ?><td><?php if ($screen === 'company/applications' && $cellIndex === 4 && !empty($screenData['row_cv_paths'][$rowIndex])): ?><a class="table-download" href="?download=cv&amp;id=<?= (int) $screenData['row_student_ids'][$rowIndex] ?>"><?= screen_escape($cell) ?> ↓</a><?php elseif ($screen === 'company/applications' && $cellIndex === 4): ?><?= screen_escape($cell) ?><?php elseif ($cellIndex === array_key_last($row) && !preg_match('/^\d+%$/', (string) $cell)): ?><span class="badge <?= screen_badge_class((string) $cell) ?>"><?= screen_escape($cell) ?></span><?php else: ?><?= screen_escape($cell) ?><?php endif; ?></td><?php endforeach; ?><td>
                                    <?php if ($screen === 'student/applications' && $recordId && in_array($row[3], ['Chờ xem', 'Đang xem'], true)): ?>
                                        <form method="post" action="<?= screen_escape($formAction) ?>" class="inline-action"><?= $csrfField ?><input type="hidden" name="action" value="application_withdraw"><input type="hidden" name="application_id" value="<?= (int) $recordId ?>"><button type="submit">Rút hồ sơ</button></form>
                                    <?php elseif ($screen === 'company/applications' && $recordId): ?>
                                        <form method="post" action="<?= screen_escape($formAction) ?>" class="inline-action"><?= $csrfField ?><input type="hidden" name="action" value="application_review"><input type="hidden" name="application_id" value="<?= (int) $recordId ?>"><select name="status" aria-label="Trạng thái hồ sơ">
                                                <option value="reviewing">Xem xét</option>
                                                <option value="accepted">Nhận</option>
                                                <option value="rejected">Từ chối</option>
                                            </select><button type="submit">Lưu</button></form>
                                    <?php elseif ($screen === 'lecturer/diaries' && $recordId): ?>
                                        <form method="post" action="<?= screen_escape($formAction) ?>" class="inline-action"><?= $csrfField ?><input type="hidden" name="action" value="diary_review"><input type="hidden" name="diary_id" value="<?= (int) $recordId ?>"><select name="status" aria-label="Kết quả duyệt">
                                                <option value="approved">Duyệt</option>
                                                <option value="rejected">Yêu cầu sửa</option>
                                            </select><button type="submit">Lưu</button></form>
                                        <?php if (ai_match_enabled()): ?>
                                            <?php $insight = $screenData['row_insights'][$rowIndex] ?? null; ?>
                                            <button type="button" class="table-edit-button ai-button<?= ai_review_has_problem($insight) ? ' ai-button--warn' : '' ?>" data-dialog-open="ai-diary-<?= (int) $recordId ?>"><?= $insight ? 'AI · ' . (int) $insight['score'] : 'AI tóm tắt' ?></button>
                                            <dialog class="modal" id="ai-diary-<?= (int) $recordId ?>" aria-label="Phân tích nhật ký bằng AI">
                                                <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="ai_review_diary"><input type="hidden" name="diary_id" value="<?= (int) $recordId ?>">
                                                    <header class="modal-head"><div><p class="eyebrow">Trợ lý AI · Nhật ký</p><h2><?= screen_escape($row[0]) ?></h2><p class="modal-sub"><?= screen_escape($row[1]) ?> · <?= screen_escape($row[2]) ?></p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                                    <?= ai_review_html($insight) ?>
                                                    <?php if (!empty($screenData['row_skill_notes'][$rowIndex])): ?><p class="ai-note"><strong>Ghi chú về kỹ năng (AI):</strong> <?= screen_escape($screenData['row_skill_notes'][$rowIndex]) ?></p><?php endif; ?>
                                                    <details class="ai-original"><summary>Xem nguyên văn nhật ký</summary><div class="plan-text"><?= screen_escape($screenData['row_contents'][$rowIndex] ?? '') ?></div></details>
                                                    <div class="modal-actions"><button type="button" class="button" data-dialog-close>Đóng</button><button class="button button--primary" type="submit" data-ai-submit><?= $insight ? 'Phân tích lại' : 'Phân tích bằng AI' ?></button></div>
                                                </form>
                                            </dialog>
                                        <?php endif; ?>
                                    <?php elseif (in_array($screen, ['company/evaluations', 'lecturer/evaluations'], true) && $recordId): ?>
                                        <?php $evaluation = $screenData['row_evaluations'][$rowIndex] ?? null; $evalScore = static fn(string $field): int => (int) round((float) ($evaluation[$field] ?? 80)); ?>
                                        <button type="button" class="table-edit-button" data-dialog-open="evaluation-<?= (int) $recordId ?>"><?= ($evaluation['status'] ?? null) === 'submitted' ? 'Điều chỉnh' : 'Đánh giá' ?></button>
                                        <dialog class="modal" id="evaluation-<?= (int) $recordId ?>" aria-label="Tạo đánh giá">
                                            <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="evaluation_save"><input type="hidden" name="internship_id" value="<?= (int) $recordId ?>">
                                                <header class="modal-head"><div><p class="eyebrow"><?= $evaluation ? 'Điều chỉnh đánh giá' : 'Tạo đánh giá' ?></p><h2><?= screen_escape($row[0]) ?></h2><p class="modal-sub"><?= screen_escape($row[1]) ?></p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                                <div class="form-grid"><label>Chuyên môn<input name="technical_score" type="number" min="0" max="100" value="<?= $evalScore('technical_score') ?>" required></label><label>Thái độ<input name="attitude_score" type="number" min="0" max="100" value="<?= $evalScore('attitude_score') ?>" required></label><label>Giao tiếp<input name="communication_score" type="number" min="0" max="100" value="<?= $evalScore('communication_score') ?>" required></label><label>Kỷ luật<input name="discipline_score" type="number" min="0" max="100" value="<?= $evalScore('discipline_score') ?>" required></label></div>
                                                <label>Nhận xét<textarea name="comments" rows="3" maxlength="5000"><?= screen_escape($evaluation['comments'] ?? '') ?></textarea></label>
                                                <label>Trạng thái<select name="status"><option value="submitted">Gửi đánh giá</option><option value="draft" <?= ($evaluation['status'] ?? null) === 'draft' ? 'selected' : '' ?>>Lưu bản nháp</option></select></label>
                                                <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu đánh giá</button></div>
                                            </form>
                                        </dialog>
                                        <?php if ($screen === 'lecturer/evaluations' && ai_match_enabled()): ?>
                                            <?php $finalComment = $screenData['row_final'][$rowIndex] ?? null; ?>
                                            <button type="button" class="table-edit-button ai-button" data-dialog-open="ai-final-<?= (int) $recordId ?>">Nhận xét AI</button>
                                            <dialog class="modal" id="ai-final-<?= (int) $recordId ?>" aria-label="Nhận xét cuối kỳ do AI soạn">
                                                <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="ai_final_comment"><input type="hidden" name="internship_id" value="<?= (int) $recordId ?>">
                                                    <header class="modal-head"><div><p class="eyebrow">Trợ lý AI · Nhận xét cuối kỳ</p><h2><?= screen_escape($row[0]) ?></h2><p class="modal-sub"><?= screen_escape($row[1]) ?></p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                                    <?= ai_final_html($finalComment) ?>
                                                    <div class="modal-actions"><button type="button" class="button" data-dialog-close>Đóng</button><button class="button button--primary" type="submit" data-ai-submit><?= $finalComment ? 'Soạn lại' : 'Tổng hợp nhận xét' ?></button></div>
                                                </form>
                                            </dialog>
                                        <?php endif; ?>
                                    <?php elseif ($screen === 'company/skills' && $recordId): ?>
                                        <?php $suggestions = $screenData['row_suggestions'][$rowIndex] ?? []; $skillNote = (string) ($screenData['row_skill_notes'][$rowIndex] ?? ''); ?>
                                        <button type="button" class="table-edit-button ai-button" data-dialog-open="skills-<?= (int) $recordId ?>">Xem kỹ năng</button>
                                        <dialog class="modal modal--wide" id="skills-<?= (int) $recordId ?>" aria-label="Kỹ năng đề xuất từ nhật ký">
                                            <div class="workspace-form modal-form">
                                                <header class="modal-head"><div><p class="eyebrow">Kỹ năng đề xuất · <?= screen_escape($row[1]) ?></p><h2><?= screen_escape($row[0]) ?></h2><p class="modal-sub">Nhật ký ngày <?= screen_escape($row[2]) ?></p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                                <details class="ai-original"><summary>Xem nguyên văn nhật ký</summary><div class="plan-text"><?= screen_escape($screenData['row_contents'][$rowIndex] ?? '') ?></div></details>
                                                <?php if ($skillNote !== ''): ?><p class="ai-note"><strong>Ghi chú của AI:</strong> <?= screen_escape($skillNote) ?></p><?php endif; ?>
                                                <?php if (!$suggestions): ?><p class="ai-empty"><?= $skillNote !== '' ? 'AI không đề xuất kỹ năng nào cho nhật ký này.' : 'Chưa phân tích. Bấm nút bên dưới để AI đề xuất kỹ năng từ nhật ký.' ?></p><?php endif; ?>
                                                <?php foreach ($suggestions as $suggestion): ?>
                                                    <article class="skill-item skill-item--<?= screen_escape($suggestion['status']) ?>">
                                                        <div class="skill-item-head">
                                                            <strong><?= screen_escape($suggestion['name']) ?></strong>
                                                            <span class="badge badge--neutral"><?= screen_escape(AI_SKILL_TYPES[$suggestion['skill_type']] ?? '') ?></span>
                                                            <span class="badge badge--active"><?= screen_escape(AI_SKILL_LEVELS[$suggestion['level']] ?? '') ?></span>
                                                            <?php if ($suggestion['existed_before']): ?><span class="badge badge--neutral">Cộng dồn các tuần trước</span><?php endif; ?>
                                                            <?php if ($suggestion['status'] !== 'pending'): ?><span class="badge <?= $suggestion['status'] === 'rejected' ? 'badge--attention' : 'badge--positive' ?>"><?= ['confirmed' => 'Đã xác nhận', 'edited' => 'Đã sửa', 'rejected' => 'Đã bác bỏ'][$suggestion['status']] ?? '' ?></span><?php endif; ?>
                                                        </div>
                                                        <blockquote class="skill-evidence">&ldquo;<?= screen_escape($suggestion['evidence']) ?>&rdquo;</blockquote>
                                                        <?php if ($suggestion['status'] === 'pending'): ?>
                                                            <div class="skill-actions">
                                                                <form method="post" action="<?= screen_escape($formAction) ?>"><?= $csrfField ?><input type="hidden" name="action" value="skill_review"><input type="hidden" name="decision" value="confirm"><input type="hidden" name="suggestion_id" value="<?= (int) $suggestion['id'] ?>"><input type="hidden" name="diary_id" value="<?= (int) $recordId ?>"><button class="button button--primary" type="submit">Xác nhận</button></form>
                                                                <form method="post" action="<?= screen_escape($formAction) ?>"><?= $csrfField ?><input type="hidden" name="action" value="skill_review"><input type="hidden" name="decision" value="reject"><input type="hidden" name="suggestion_id" value="<?= (int) $suggestion['id'] ?>"><input type="hidden" name="diary_id" value="<?= (int) $recordId ?>"><button class="button" type="submit">Bác bỏ</button></form>
                                                                <details class="skill-edit"><summary>Sửa</summary>
                                                                    <form method="post" action="<?= screen_escape($formAction) ?>" class="skill-edit-form"><?= $csrfField ?><input type="hidden" name="action" value="skill_review"><input type="hidden" name="decision" value="edit"><input type="hidden" name="suggestion_id" value="<?= (int) $suggestion['id'] ?>"><input type="hidden" name="diary_id" value="<?= (int) $recordId ?>">
                                                                        <label>Tên kỹ năng<input name="name" maxlength="80" value="<?= screen_escape($suggestion['name']) ?>" required></label>
                                                                        <label>Mức độ<select name="level"><?php foreach (AI_SKILL_LEVELS as $levelKey => $levelLabel): ?><option value="<?= $levelKey ?>" <?= $suggestion['level'] === $levelKey ? 'selected' : '' ?>><?= screen_escape($levelLabel) ?></option><?php endforeach; ?></select></label>
                                                                        <button class="button button--primary" type="submit">Lưu và xác nhận</button>
                                                                    </form>
                                                                </details>
                                                            </div>
                                                        <?php endif; ?>
                                                    </article>
                                                <?php endforeach; ?>
                                                <?php if (ai_match_enabled()): ?>
                                                    <form method="post" action="<?= screen_escape($formAction) ?>" class="modal-actions"><?= $csrfField ?><input type="hidden" name="action" value="skill_suggest"><input type="hidden" name="diary_id" value="<?= (int) $recordId ?>"><button type="button" class="button" data-dialog-close>Đóng</button><button class="button button--primary" type="submit" data-ai-submit><?= $suggestions || $skillNote !== '' ? 'Phân tích lại' : 'AI đề xuất kỹ năng' ?></button></form>
                                                <?php else: ?>
                                                    <div class="modal-actions"><button type="button" class="button" data-dialog-close>Đóng</button></div>
                                                <?php endif; ?>
                                            </div>
                                        </dialog>
                                    <?php elseif ($screen === 'admin/documents' && $recordId): ?>
                                        <button type="button" class="table-edit-button" data-dialog-open="kb-edit-<?= (int) $recordId ?>">Chỉnh sửa</button>
                                        <dialog class="modal" id="kb-edit-<?= (int) $recordId ?>" aria-label="Chỉnh sửa tài liệu">
                                            <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="kb_save"><input type="hidden" name="document_id" value="<?= (int) $recordId ?>">
                                                <header class="modal-head"><div><p class="eyebrow">Tài liệu của khoa</p><h2><?= screen_escape($row[0]) ?></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                                <label>Tiêu đề<input name="title" maxlength="200" value="<?= screen_escape($row[0]) ?>" required></label>
                                                <label>Nội dung<textarea name="content" rows="12" maxlength="60000" required><?= screen_escape($screenData['row_contents'][$rowIndex] ?? '') ?></textarea></label>
                                                <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu thay đổi</button></div>
                                            </form>
                                            <form method="post" action="<?= screen_escape($formAction) ?>" class="modal-form kb-delete-form" onsubmit="return confirm('Xóa tài liệu này? Trợ lý AI sẽ không còn dùng nó để trả lời.');"><?= $csrfField ?><input type="hidden" name="action" value="kb_delete"><input type="hidden" name="document_id" value="<?= (int) $recordId ?>"><button class="button" type="submit">Xóa tài liệu</button></form>
                                        </dialog>
                                    <?php elseif ($screen === 'admin/companies' && $recordId): ?>
                                        <?php $currentStatus = (string) ($screenData['row_status'][$rowIndex] ?? 'active'); ?>
                                        <button type="button" class="table-edit-button" data-dialog-open="company-edit-<?= (int) $recordId ?>">Chỉnh sửa</button>
                                        <dialog class="modal" id="company-edit-<?= (int) $recordId ?>" aria-label="Chỉnh sửa doanh nghiệp">
                                            <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="company_update"><input type="hidden" name="company_id" value="<?= (int) $recordId ?>">
                                                <header class="modal-head"><div><p class="eyebrow">Chỉnh sửa doanh nghiệp</p><h2><?= screen_escape($row[0]) ?></h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                                <label>Mã doanh nghiệp<input value="<?= screen_escape($row[1]) ?>" readonly aria-readonly="true" title="Quản trị viên không được chỉnh sửa mã doanh nghiệp"></label>
                                                <label>Trạng thái<select name="status">
                                                        <?php foreach (['active' => 'Hoạt động (đã xác minh)', 'pending' => 'Chờ xác minh', 'inactive' => 'Tạm ngưng', 'rejected' => 'Từ chối'] as $value => $label): ?><option value="<?= $value ?>" <?= $currentStatus === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                                                    </select></label>
                                                <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu thay đổi</button></div>
                                            </form>
                                        </dialog>
                                    <?php elseif (in_array($screen, ['company/inters', 'lecturer/students'], true) && $recordId): ?>
                                        <?php $planText = (string) ($screenData['row_plans'][$rowIndex] ?? ''); ?>
                                        <button type="button" class="table-edit-button" data-dialog-open="plan-<?= (int) $recordId ?>">Xem kế hoạch</button>
                                        <dialog class="modal" id="plan-<?= (int) $recordId ?>" aria-label="Kế hoạch thực tập">
                                            <div class="workspace-form modal-form">
                                                <header class="modal-head"><div><p class="eyebrow">Kế hoạch thực tập</p><h2><?= screen_escape(preg_replace('/ · .*/u', '', (string) $row[0])) ?></h2><p class="modal-sub"><?= screen_escape($row[1]) ?></p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                                <?php if ($planText !== ''): ?><div class="plan-text"><?= screen_escape($planText) ?></div><?php else: ?><p class="plan-empty">Nhà trường chưa nhập kế hoạch cho kỳ thực tập này.</p><?php endif; ?>
                                                <p class="plan-note">Kế hoạch do nhà trường phân công, chỉ xem.<?= $screen === 'company/inters' ? ' Bạn có thể giao thêm nhiệm vụ ngoài kế hoạch ở mục Nhiệm vụ.' : '' ?></p>
                                                <div class="modal-actions"><button type="button" class="button button--primary" data-dialog-close>Đóng</button></div>
                                            </div>
                                        </dialog>
                                    <?php elseif ($screen === 'admin/users' && $recordId && (int) $recordId !== (int) $user['id']): ?>
                                        <form method="post" action="<?= screen_escape($formAction) ?>" class="inline-action"><?= $csrfField ?><input type="hidden" name="action" value="user_status"><input type="hidden" name="user_id" value="<?= (int) $recordId ?>"><input type="hidden" name="status" value="<?= !empty($screenData['row_active'][$rowIndex]) ? 'inactive' : 'active' ?>"><button type="submit"><?= !empty($screenData['row_active'][$rowIndex]) ? 'Tạm khóa' : 'Kích hoạt' ?></button></form>
                                    <?php elseif ($screen === 'admin/positions' && $recordId): ?>
                                        <form method="post" action="<?= screen_escape($formAction) ?>" class="inline-action"><?= $csrfField ?><input type="hidden" name="action" value="position_status"><input type="hidden" name="position_id" value="<?= (int) $recordId ?>"><input type="hidden" name="status" value="<?= $row[4] === 'Đang mở' ? 'closed' : 'open' ?>"><button type="submit"><?= $row[4] === 'Đang mở' ? 'Đóng tin' : 'Mở tin' ?></button></form>
                                    <?php elseif ($screen === 'admin/internship' && $recordId): ?>
                                        <?php $currentStatus = (string) ($screenData['row_status'][$rowIndex] ?? 'planned'); ?>
                                        <button type="button" class="table-edit-button" data-dialog-open="internship-edit-<?= (int) $recordId ?>">Cập nhật</button>
                                        <dialog class="modal" id="internship-edit-<?= (int) $recordId ?>" aria-label="Cập nhật kỳ thực tập">
                                            <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="internship_update"><input type="hidden" name="internship_id" value="<?= (int) $recordId ?>">
                                                <header class="modal-head"><div><p class="eyebrow">Cập nhật kỳ thực tập</p><h2><?= screen_escape($row[0]) ?></h2><p class="modal-sub"><?= screen_escape($row[2]) ?></p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                                <div class="form-grid">
                                                    <label>Trạng thái<select name="status">
                                                            <?php foreach (['planned' => 'Chưa bắt đầu', 'active' => 'Đang diễn ra', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã hủy'] as $value => $label): ?><option value="<?= $value ?>" <?= $currentStatus === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                                                        </select></label>
                                                    <label>Giảng viên phụ trách<select name="lecturer_id">
                                                            <option value="">Chưa phân công</option><?php foreach ($screenData['lecturer_options'] as $lecturer): ?><option value="<?= (int) $lecturer['id'] ?>" <?= (string) ($screenData['row_lecturer_ids'][$rowIndex] ?? '') === (string) $lecturer['id'] ? 'selected' : '' ?>><?= screen_escape($lecturer['full_name']) ?></option><?php endforeach; ?>
                                                        </select></label>
                                                </div>
                                                <label>Kế hoạch thực tập<textarea name="training_plan" rows="6" maxlength="20000" placeholder="Mục tiêu, lộ trình theo tuần, yêu cầu đầu ra..."><?= screen_escape($screenData['row_training_plans'][$rowIndex] ?? '') ?></textarea></label>
                                                <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu thay đổi</button></div>
                                            </form>
                                        </dialog>
                                    <?php else: ?><span class="table-action-note">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-foot"><span data-table-count data-total="<?= count($screenData['rows']) ?>"><?= count($screenData['rows']) ?> mục · Theo dữ liệu hiện tại</span></div>
        </section>

    <?php elseif ($kind === 'kanban'): ?>
        <?php if ($screen === 'company/tasks' && !empty($screenData['task_students'])): ?>
            <div class="board-filter" data-board-filter>
                <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" data-board-search placeholder="Tìm nhiệm vụ" aria-label="Tìm nhiệm vụ"></label>
                <label class="board-filter-student">Sinh viên<select data-board-student>
                        <option value="">Tất cả sinh viên (<?= array_sum($screenData['task_students']) ?>)</option>
                        <?php foreach ($screenData['task_students'] as $studentName => $studentTasks): ?><option value="<?= screen_escape($studentName) ?>"><?= screen_escape($studentName) ?> (<?= (int) $studentTasks ?>)</option><?php endforeach; ?>
                    </select></label>
                <label class="board-filter-attention"><input type="checkbox" data-board-attention> Chỉ việc chờ xác nhận</label>
                <span class="board-filter-count" data-board-count aria-live="polite"></span>
            </div>
        <?php endif; ?>
        <section class="board-grid">
            <?php foreach ($screenData['boards'] as $board): ?>
                <div class="board-column">
                    <div class="board-heading">
                        <h2><?= screen_escape($board['title']) ?></h2><span><?= count($board['items']) ?></span>
                    </div>
                    <div class="board-cards" data-board-cards>
                    <?php foreach ($board['items'] as $item): ?><article class="task-card" data-task-student="<?= screen_escape($item['student'] ?? '') ?>" data-task-status="<?= screen_escape($item['status'] ?? '') ?>"><span class="task-marker"></span>
                            <h3><?= screen_escape($item['title']) ?></h3>
                            <p><?= screen_escape($item['meta']) ?></p><?php if (!empty($item['description']) && $screen === 'student/tasks'): ?><p class="task-description"><?= screen_escape($item['description']) ?></p><?php endif; ?>
                            <?php if (!empty($item['submission'])): $evidence = $item['submission']; ?>
                                <div class="task-evidence">
                                    <strong>Minh chứng hoàn thành<?= !empty($evidence['submitted_at']) ? ' · ' . screen_escape($evidence['submitted_at']) : '' ?></strong>
                                    <?php if (!empty($evidence['link'])): ?><a href="<?= screen_escape($evidence['link']) ?>" target="_blank" rel="noopener noreferrer">Liên kết: <?= screen_escape(mb_strimwidth($evidence['link'], 0, 60, '…', 'UTF-8')) ?></a><?php endif; ?>
                                    <?php if (!empty($evidence['has_file'])): ?><a href="?download=task&amp;id=<?= (int) $item['id'] ?>">Tải tệp đính kèm (<?= screen_escape(strtoupper(pathinfo((string) $evidence['file_name'], PATHINFO_EXTENSION))) ?>)</a><?php endif; ?>
                                    <?php if (!empty($evidence['note'])): ?><p><?= screen_escape($evidence['note']) ?></p><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['id']) && $screen === 'student/tasks' && $item['status'] === 'todo'): ?>
                                <form method="post" action="<?= screen_escape($formAction) ?>" class="task-status-form"><?= $csrfField ?><input type="hidden" name="action" value="task_status"><input type="hidden" name="task_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="status" value="in_progress"><button type="submit">Bắt đầu</button></form>
                            <?php elseif (!empty($item['id']) && $screen === 'student/tasks' && $item['status'] === 'in_progress'): ?>
                                <button type="button" class="task-submit-trigger" data-dialog-open="task-submit-<?= (int) $item['id'] ?>">Báo cáo hoàn thành</button>
                                <dialog class="modal" id="task-submit-<?= (int) $item['id'] ?>" aria-labelledby="task-submit-title-<?= (int) $item['id'] ?>">
                                    <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="task_status"><input type="hidden" name="task_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="status" value="submitted">
                                        <header class="modal-head">
                                            <div><p class="eyebrow">Báo cáo hoàn thành</p><h2 id="task-submit-title-<?= (int) $item['id'] ?>"><?= screen_escape($item['title']) ?></h2></div>
                                            <button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button>
                                        </header>
                                        <p class="form-hint">Gửi kèm minh chứng để doanh nghiệp kiểm tra: liên kết (Git, Drive...) và/hoặc tệp (Word, PDF, ZIP...). Cần ít nhất một trong hai.</p>
                                        <label>Liên kết (Git, Drive, Figma...)<input name="submission_link" type="url" maxlength="500" placeholder="https://github.com/ten-ban/du-an"></label>
                                        <label>Tệp minh chứng (tối đa 20 MB)<input name="submission_file" type="file" accept=".doc,.docx,.pdf,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.jpg,.jpeg,.png,.txt"></label>
                                        <label>Ghi chú<textarea name="submission_note" rows="3" maxlength="2000" placeholder="Mô tả ngắn những gì bạn đã làm"></textarea></label>
                                        <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Gửi hoàn thành</button></div>
                                    </form>
                                </dialog>
                            <?php elseif (!empty($item['id']) && $screen === 'student/tasks' && $item['status'] === 'submitted'): ?>
                                <?php $evidence = $item['submission'] ?? []; ?>
                                <p class="task-state">Đã gửi, chờ doanh nghiệp xác nhận.</p>
                                <button type="button" class="task-edit-trigger" data-dialog-open="task-resubmit-<?= (int) $item['id'] ?>">Chỉnh sửa bài nộp</button>
                                <dialog class="modal" id="task-resubmit-<?= (int) $item['id'] ?>" aria-labelledby="task-resubmit-title-<?= (int) $item['id'] ?>">
                                    <form method="post" enctype="multipart/form-data" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="task_status"><input type="hidden" name="task_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="status" value="submitted">
                                        <header class="modal-head">
                                            <div><p class="eyebrow">Chỉnh sửa bài nộp</p><h2 id="task-resubmit-title-<?= (int) $item['id'] ?>"><?= screen_escape($item['title']) ?></h2></div>
                                            <button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button>
                                        </header>
                                        <p class="form-hint">Doanh nghiệp chưa xác nhận nên bạn vẫn sửa được. Lưu xong, bài nộp được gửi lại cho doanh nghiệp.</p>
                                        <label>Liên kết (Git, Drive, Figma...)<input name="submission_link" type="url" maxlength="500" value="<?= screen_escape($evidence['link'] ?? '') ?>" placeholder="https://github.com/ten-ban/du-an"></label>
                                        <label>Tệp minh chứng (tối đa 20 MB)<input name="submission_file" type="file" accept=".doc,.docx,.pdf,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.jpg,.jpeg,.png,.txt"></label>
                                        <?php if (!empty($evidence['has_file'])): ?><p class="form-hint">Đang đính kèm: <?= screen_escape($evidence['file_name'] ?? 'tệp đã nộp') ?>. Chọn tệp mới để thay thế, để trống nếu giữ nguyên.</p><?php endif; ?>
                                        <label>Ghi chú<textarea name="submission_note" rows="3" maxlength="2000" placeholder="Mô tả ngắn những gì bạn đã làm"><?= screen_escape($evidence['note'] ?? '') ?></textarea></label>
                                        <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu và gửi lại</button></div>
                                    </form>
                                </dialog>
                            <?php elseif (!empty($item['id']) && $screen === 'company/tasks'): ?>
                                <div class="task-company-actions">
                                    <?php $taskEdit = $item['edit'] ?? []; ?>
                                    <button type="button" class="task-edit-trigger" data-dialog-open="task-edit-<?= (int) $item['id'] ?>">Chỉnh sửa</button>
                                    <dialog class="modal" id="task-edit-<?= (int) $item['id'] ?>" aria-label="Chỉnh sửa nhiệm vụ">
                                        <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="task_update"><input type="hidden" name="task_id" value="<?= (int) $item['id'] ?>">
                                            <header class="modal-head"><div><p class="eyebrow">Chỉnh sửa nhiệm vụ</p><h2><?= screen_escape($taskEdit['title'] ?? $item['title']) ?></h2><p class="modal-sub"><?= screen_escape($item['student'] ?? '') ?></p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                            <label>Tên nhiệm vụ<input name="title" maxlength="200" value="<?= screen_escape($taskEdit['title'] ?? '') ?>" required></label>
                                            <label>Mô tả<textarea name="description" rows="3"><?= screen_escape($taskEdit['description'] ?? '') ?></textarea></label>
                                            <div class="form-grid">
                                                <label>Mức ưu tiên<select name="priority"><?php foreach (['low' => 'Thấp', 'medium' => 'Vừa', 'high' => 'Cao', 'urgent' => 'Khẩn'] as $priorityKey => $priorityLabel): ?><option value="<?= $priorityKey ?>" <?= ($taskEdit['priority'] ?? 'medium') === $priorityKey ? 'selected' : '' ?>><?= $priorityLabel ?></option><?php endforeach; ?></select></label>
                                                <label>Ngày bắt đầu<input name="start_date" type="date" value="<?= screen_escape($taskEdit['start_date'] ?? '') ?>"></label>
                                                <label>Hạn hoàn thành<input name="due_date" type="date" value="<?= screen_escape($taskEdit['due_date'] ?? '') ?>"></label>
                                            </div>
                                            <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu thay đổi</button></div>
                                        </form>
                                    </dialog>
                                    <?php if ($item['status'] !== 'completed'): ?><form method="post" action="<?= screen_escape($formAction) ?>" class="task-status-form"><?= $csrfField ?><input type="hidden" name="action" value="task_status"><input type="hidden" name="task_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="status" value="completed"><button type="submit">Xác nhận hoàn tất</button></form><?php endif; ?>
                                    <?php if ($item['status'] === 'submitted'): ?><form method="post" action="<?= screen_escape($formAction) ?>" class="task-status-form"><?= $csrfField ?><input type="hidden" name="action" value="task_status"><input type="hidden" name="task_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="status" value="in_progress"><button type="submit">Yêu cầu làm lại</button></form><?php endif; ?>
                                    <?php if ($item['status'] === 'completed'): ?><form method="post" action="<?= screen_escape($formAction) ?>" class="task-status-form"><?= $csrfField ?><input type="hidden" name="action" value="task_status"><input type="hidden" name="task_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="status" value="in_progress"><button type="submit">Mở lại</button></form><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article><?php endforeach; ?>
                    <p class="board-empty" data-board-empty hidden>Không có nhiệm vụ phù hợp.</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

    <?php elseif ($kind === 'timeline'): ?>
        <section class="timeline-layout">
            <div class="timeline-list">
                <?php foreach ($screenData['activities'] as $activity): ?><article class="timeline-entry">
                        <div class="timeline-marker"></div>
                        <div class="timeline-entry-head"><span><?= screen_escape($activity['time']) ?></span><?php if (!empty($activity['status'])): ?><span class="badge <?= screen_badge_class($activity['status']) ?>"><?= screen_escape($activity['status']) ?></span><?php endif; ?></div>
                        <h2><?= screen_escape($activity['title']) ?></h2>
                        <p><?= screen_escape($activity['description']) ?></p>
                    </article><?php endforeach; ?>
            </div>
            <aside class="panel side-note">
                <p class="eyebrow">Một nhịp đều</p>
                <h2>Ghi nhận điều bạn học được.</h2>
                <p class="muted">Nhật ký là nơi phản tư về quá trình, không chỉ là danh sách đầu việc.</p>
            </aside>
        </section>

    <?php elseif ($kind === 'cards' || $kind === 'reports'): ?>
        <?php if ($screen === 'student/internships' && !empty($screenData['ai_match']['enabled'])): ?>
            <section class="panel ai-match-panel">
                <div>
                    <p class="eyebrow">Trợ lý AI</p>
                    <h2>Gợi ý vị trí phù hợp từ CV của bạn</h2>
                    <p class="muted"><?php if (!$screenData['ai_match']['has_cv']): ?>Hãy <a href="?page=student/profile">tải CV (PDF) lên hồ sơ</a> để AI đọc và xếp hạng các vị trí cho bạn.<?php elseif ($screenData['ai_match']['generated_at']): ?>Đã xếp hạng lúc <?= screen_escape($screenData['ai_match']['generated_at']) ?>. Cập nhật CV xong, bạn có thể chạy lại.<?php else: ?>AI sẽ đọc CV (PDF), chấm độ phù hợp với từng vị trí đang mở và giải thích lý do.<?php endif; ?></p>
                </div>
                <?php if ($screenData['ai_match']['has_cv']): ?>
                    <form method="post" action="<?= screen_escape($formAction) ?>" data-ai-match-form><?= $csrfField ?><input type="hidden" name="action" value="ai_match"><button class="button button--primary" type="submit" data-ai-match-button><?= $screenData['ai_match']['generated_at'] ? 'Chạy lại gợi ý AI' : 'Gợi ý bằng AI' ?></button></form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php if ($screen === 'student/internships'): ?>
            <div class="opportunity-toolbar">
                <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" data-opportunity-search placeholder="Tìm vị trí, kỹ năng hoặc doanh nghiệp" aria-label="Tìm cơ hội thực tập"></label>
                <div class="opportunity-sort">
                    <span class="opportunity-sort-label">Sắp xếp theo</span>
                    <div class="opportunity-sort-options" role="group" aria-label="Sắp xếp cơ hội">
                        <button type="button" class="opportunity-sort-option is-selected" data-sort-mode="match" aria-pressed="true">Độ phù hợp</button>
                        <button type="button" class="opportunity-sort-option" data-sort-mode="deadline" aria-pressed="false">Hạn nộp</button>
                    </div>
                </div>
                <span class="opportunity-count" data-opportunity-count aria-live="polite"></span>
            </div>
        <?php endif; ?>
        <section class="content-card-grid <?= $kind === 'reports' ? 'content-card-grid--reports' : '' ?>" <?= $screen === 'student/internships' ? 'data-opportunity-list' : '' ?>>
            <?php foreach ($screenData['cards'] as $card): ?>
                <?php $isOpportunity = $screen === 'student/internships'; ?>
                <article class="opportunity-card" <?= $isOpportunity ? 'data-opportunity-card data-match-score="' . (int) ($card['match_score'] ?? 0) . '" data-deadline="' . screen_escape($card['deadline'] ?? '') . '"' : '' ?>>
                    <div class="card-topline"><span class="card-mark" aria-hidden="true"></span><span class="badge <?= screen_badge_class($card['tag']) ?>"><?= screen_escape($card['tag']) ?></span></div>
                    <h2><?= screen_escape($card['title']) ?></h2>
                    <?php if ($isOpportunity): ?>
                        <div class="card-meta opportunity-company-details">
                            <strong><?= screen_escape($card['company_name']) ?></strong>
                            <span>Địa chỉ công ty: <?= screen_escape($card['company_address']) ?></span>
                            <span>Địa điểm làm việc: <?= screen_escape($card['work_location']) ?></span>
                        </div>
                    <?php else: ?>
                        <p class="card-meta"><?= screen_escape($card['meta']) ?></p>
                    <?php endif; ?>
                    <p><?= screen_escape($card['description']) ?></p>
                    <?php if ($isOpportunity && !empty($card['ai_reason'])): ?><p class="ai-reason"><strong>AI nhận xét:</strong> <?= screen_escape($card['ai_reason']) ?></p><?php endif; ?>
                    <?php if ($screen === 'student/internships'): ?>
                        <a href="?page=student/internship-detail&amp;id=<?= (int) ($card['id'] ?? 0) ?>" class="text-link">Xem chi tiết<span aria-hidden="true"> ↗</span></a>
                    <?php elseif ($screen === 'company/positions' && !empty($card['id'])): ?>
                        <form method="post" action="<?= screen_escape($formAction) ?>" class="task-status-form"><?= $csrfField ?><input type="hidden" name="action" value="position_status"><input type="hidden" name="position_id" value="<?= (int) $card['id'] ?>"><input type="hidden" name="status" value="<?= $card['status'] === 'open' ? 'closed' : 'open' ?>"><button type="submit"><?= $card['status'] === 'open' ? 'Đóng nhận hồ sơ' : 'Mở lại vị trí' ?></button></form>
                        <button type="button" class="action-trigger" data-dialog-open="position-edit-<?= (int) $card['id'] ?>"><span>Chỉnh sửa tin</span><small>Bổ sung nơi làm việc nếu còn thiếu</small></button>
    <dialog class="modal" id="position-edit-<?= (int) $card['id'] ?>" aria-label="Chỉnh sửa tin">
        <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form">
                <header class="modal-head"><div><p class="eyebrow">Bổ sung nơi làm việc nếu còn thiếu</p><h2>Chỉnh sửa tin</h2></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                <?= $csrfField ?><input type="hidden" name="action" value="position_save"><input type="hidden" name="position_id" value="<?= (int) $card['id'] ?>">
                                <label>Tên vị trí<input name="title" maxlength="200" value="<?= screen_escape($card['title']) ?>" required></label>
                                <label>Mô tả công việc<textarea name="description" rows="3" required><?= screen_escape($card['position_description']) ?></textarea></label>
                                <label>Yêu cầu<textarea name="requirements" rows="2"><?= screen_escape($card['requirements']) ?></textarea></label>
                                <label>Quyền lợi<textarea name="benefits" rows="2"><?= screen_escape($card['benefits']) ?></textarea></label>
                                <div class="form-grid"><label>Nơi làm việc<input name="location" maxlength="255" placeholder="TP. Vinh, Nghệ An hoặc Từ xa" value="<?= screen_escape($card['location']) ?>"></label><label>Số lượng<input name="quantity" type="number" min="1" value="<?= (int) $card['quantity'] ?>" required></label><label>Hạn nhận hồ sơ<input name="deadline" type="date" value="<?= screen_escape($card['deadline']) ?>"></label><label>Trạng thái<select name="status">
                                            <option value="draft" <?= $card['status'] === 'draft' ? 'selected' : '' ?>>Bản nháp</option>
                                            <option value="open" <?= $card['status'] === 'open' ? 'selected' : '' ?>>Đang mở</option>
                                            <option value="closed" <?= $card['status'] === 'closed' ? 'selected' : '' ?>>Đã đóng</option>
                                            <option value="cancelled" <?= $card['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                                        </select></label></div>
                                <div class="modal-actions"><button type="button" class="button" data-dialog-close>Hủy</button><button class="button button--primary" type="submit">Lưu tin tuyển dụng</button></div>
                            </form>
                        </dialog>
                    <?php elseif ($kind === 'reports' && !empty($card['id'])): ?>
                        <?php if (!empty($card['file_path'])): ?><a href="?download=report&amp;id=<?= (int) $card['id'] ?>" class="text-link">Tải báo cáo<span aria-hidden="true"> ↓</span></a><?php else: ?><span class="text-link">Chưa có tệp đính kèm</span><?php endif; ?>
                        <?php if ($screen === 'lecturer/reports'): ?><form method="post" action="<?= screen_escape($formAction) ?>" class="review-card-form"><?= $csrfField ?><input type="hidden" name="action" value="report_review"><input type="hidden" name="report_id" value="<?= (int) $card['id'] ?>"><textarea name="feedback" rows="2" maxlength="5000" placeholder="Nhận xét cho sinh viên"></textarea>
                                <div class="review-actions"><button name="status" value="approved" type="submit">Duyệt</button><button name="status" value="rejected" type="submit">Yêu cầu sửa</button></div>
                            </form><?php endif; ?>
                        <?php if ($screen === 'lecturer/reports' && ai_match_enabled()): ?>
                            <?php $insight = $card['insight'] ?? null; ?>
                            <button type="button" class="table-edit-button ai-button<?= ai_review_has_problem($insight) ? ' ai-button--warn' : '' ?>" data-dialog-open="ai-report-<?= (int) $card['id'] ?>"><?= $insight ? 'AI · ' . (int) $insight['score'] . '/100' : 'AI tóm tắt' ?></button>
                            <dialog class="modal" id="ai-report-<?= (int) $card['id'] ?>" aria-label="Phân tích báo cáo bằng AI">
                                <form method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form modal-form"><?= $csrfField ?><input type="hidden" name="action" value="ai_review_report"><input type="hidden" name="report_id" value="<?= (int) $card['id'] ?>">
                                    <header class="modal-head"><div><p class="eyebrow">Trợ lý AI · Báo cáo</p><h2><?= screen_escape($card['student_name'] ?? '') ?></h2><p class="modal-sub"><?= screen_escape($card['meta']) ?></p></div><button type="button" class="modal-close" data-dialog-close aria-label="Đóng">&times;</button></header>
                                    <?= ai_review_html($insight) ?>
                                    <div class="modal-actions"><button type="button" class="button" data-dialog-close>Đóng</button><button class="button button--primary" type="submit" data-ai-submit><?= $insight ? 'Phân tích lại' : 'Phân tích bằng AI' ?></button></div>
                                </form>
                            </dialog>
                        <?php endif; ?>
                    <?php else: ?><span class="text-link">Xem chi tiết</span><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
        <?php if ($screen === 'student/internships'): ?>
            <?php if (!$screenData['cards']): ?><p class="opportunity-empty">Hiện chưa có vị trí phù hợp đang mở.</p><?php endif; ?>
            <p class="opportunity-empty" data-opportunity-empty hidden>Không tìm thấy cơ hội khớp với nội dung tìm kiếm.</p>
        <?php endif; ?>

    <?php elseif ($kind === 'profile'): ?>
        <section class="profile-layout">
            <article class="profile-intro">
                <div class="profile-avatar"><?php if (!empty($user['avatar'])): ?><img src="<?= screen_escape(app_avatar_url($user)) ?>" alt="Ảnh đại diện của <?= screen_escape($screenData['title']) ?>"><?php else: ?><?= screen_escape(screen_initial($screenData['title'])) ?><?php endif; ?></div>
                <p class="eyebrow">Hồ sơ đang hiển thị</p>
                <h2><?= screen_escape($screenData['title']) ?></h2>
                <p><?= screen_escape($screenData['description']) ?></p>
            </article>
            <div class="profile-fields">
                <?php foreach ($screenData['fields'] as $field): ?><article class="field-row"><span><?= screen_escape($field['label']) ?></span><strong><?= screen_escape($field['value']) ?></strong></article><?php endforeach; ?>
                <?php if ($screen === 'student/profile' && !empty($screenData['profile_record']['cv_file'])): ?><article class="field-row"><span>CV đã tải lên</span><strong><a href="?download=cv&amp;id=<?= (int) $user['student_id'] ?>">Tải CV <span aria-hidden="true">↓</span></a></strong></article><?php endif; ?>
            </div>
        </section>

    <?php elseif ($kind === 'detail'): ?>
        <section class="detail-layout">
            <article class="detail-main">
                <p class="eyebrow">Mô tả công việc</p>
                <h2><?= screen_escape($screenData['title']) ?></h2>
                <p class="detail-copy"><?= screen_escape($screenData['description']) ?></p>
                <div class="detail-fields"><?php foreach ($screenData['fields'] as $field): ?><div class="field-row"><span><?= screen_escape($field['label']) ?></span><strong><?= screen_escape($field['value']) ?></strong></div><?php endforeach; ?></div>
                <?php if (!empty($screenData['position_id'])): ?><?php if (!empty($screenData['application_status'])): ?><p class="flash-message">Bạn đã ứng tuyển vị trí này: <?= screen_escape(page_status_label($screenData['application_status'], ['pending' => 'Chờ xem', 'reviewing' => 'Đang xem', 'accepted' => 'Được nhận', 'rejected' => 'Chưa phù hợp', 'withdrawn' => 'Đã rút'])) ?>.</p><?php elseif (empty($user['cv_file'])): ?><p class="flash-message">Bạn cần tải CV lên hồ sơ trước khi ứng tuyển. <a href="?page=student/profile">Cập nhật hồ sơ và CV</a></p><?php else: ?><form id="application-form" method="post" action="<?= screen_escape($formAction) ?>" class="workspace-form apply-form"><?= $csrfField ?><input type="hidden" name="action" value="apply"><input type="hidden" name="position_id" value="<?= (int) $screenData['position_id'] ?>"><label>Lời nhắn cho doanh nghiệp<textarea name="cover_letter" rows="3" maxlength="5000" placeholder="Chia sẻ ngắn về mong muốn và kinh nghiệm phù hợp."></textarea></label><button class="button button--primary" type="submit">Gửi hồ sơ ứng tuyển</button></form><?php endif; ?><?php endif; ?>
            </article>
            <aside class="panel milestone-panel">
                <p class="eyebrow">Quy trình ứng tuyển</p>
                <div class="milestone"><span class="milestone-state is-done"></span>
                    <div><strong>Gửi hồ sơ</strong><small>CV và portfolio</small></div>
                </div>
                <div class="milestone"><span class="milestone-state is-current"></span>
                    <div><strong>Trao đổi cùng nhóm</strong><small>Phỏng vấn 30 phút</small></div>
                </div>
                <div class="milestone"><span class="milestone-state"></span>
                    <div><strong>Nhận kết quả</strong><small>Trong 5 ngày làm việc</small></div>
                </div>
            </aside>
        </section>

    <?php elseif ($kind === 'evaluation'): ?>
        <section class="evaluation-layout">
            <article class="panel criteria-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Năng lực cốt lõi</p>
                        <h2>Kết quả giữa kỳ</h2>
                    </div><strong class="score-total"><?= screen_escape($screenData['metrics'][0]['value'] ?? '0/100') ?></strong>
                </div>
                <?php foreach ($screenData['criteria'] as $criterion): ?><div class="criterion">
                        <div><span><?= screen_escape($criterion['name']) ?></span><strong><?= screen_escape($criterion['score']) ?></strong></div>
                        <div class="progress-track"><span style="width: <?= screen_escape($criterion['score']) ?>%"></span></div>
                    </div><?php endforeach; ?>
            </article>
            <article class="quote-panel">
                <p class="eyebrow">Nhận xét từ người hướng dẫn</p>
                <blockquote>“<?= screen_escape($screenData['quote']) ?>”</blockquote>
                <p class="quote-byline"><?= screen_escape($screenData['evaluatorName'] ?? 'Người hướng dẫn') ?> <span>· <?= screen_escape($screenData['evaluatorRole'] ?? 'Chưa có vai trò') ?></span></p>
            </article>
        </section>

    <?php elseif ($kind === 'progress'): ?>
        <section class="panel progress-list-panel">
            <div class="table-toolbar">
                <div>
                    <p class="eyebrow">Theo dõi theo cá nhân</p>
                    <h2>Nhịp tiến độ sinh viên</h2>
                </div><?php if (ai_match_enabled() && !empty($screenData['members'])): ?><form method="post" action="<?= screen_escape($formAction) ?>" class="ai-risk-form"><?= $csrfField ?><input type="hidden" name="action" value="ai_risk"><button class="button button--primary" type="submit" data-ai-submit>Phân tích rủi ro bằng AI</button></form><?php endif; ?><label class="search-field"><span aria-hidden="true">⌕</span><input type="search" data-list-search placeholder="Tìm sinh viên" aria-label="Tìm sinh viên"></label>
            </div>
            <?php foreach ($screenData['members'] as $member): ?><article class="member-row" data-search-item>
                    <div class="member-avatar"><?php if (!empty($member['avatar'])): ?><img src="<?= screen_escape(app_avatar_url(['user_id' => $member['user_id'], 'avatar' => $member['avatar']])) ?>" alt=""><?php else: ?><?= screen_escape(screen_initial($member['name'])) ?><?php endif; ?></div>
                    <div class="member-info"><strong><?= screen_escape($member['name']) ?></strong><span><?= screen_escape($member['detail']) ?></span></div>
                    <div class="member-progress">
                        <div class="member-progress-label"><span>Tiến độ</span><strong><?= (int) $member['progress'] ?>%</strong></div>
                        <div class="progress-track"><span style="width: <?= (int) $member['progress'] ?>%"></span></div>
                    </div><span class="badge <?= screen_badge_class($member['status']) ?>"><?= screen_escape($member['status']) ?></span>
                    <?php $risk = $member['risk'] ?? null; ?>
                    <?php if ($risk): ?>
                        <div class="member-risk member-risk--<?= screen_escape($risk['level']) ?>">
                            <span class="badge <?= ai_risk_class($risk['level']) ?>"><?= screen_escape(ai_risk_label($risk['level'])) ?></span>
                            <div>
                                <?php if ($risk['reasons']): ?><span><?= screen_escape(implode(' · ', $risk['reasons'])) ?></span><?php else: ?><span>Chưa thấy dấu hiệu bất thường.</span><?php endif; ?>
                                <?php if (!empty($risk['suggestion'])): ?><em>Gợi ý: <?= screen_escape($risk['suggestion']) ?></em><?php endif; ?>
                                <small><?= $risk['ai'] ? 'AI phân tích ' . screen_escape($risk['updated_at']) : 'Tính theo số liệu theo dõi · bấm "Phân tích rủi ro bằng AI" để có gợi ý can thiệp' ?></small>
                            </div>
                        </div>
                    <?php endif; ?>
                </article><?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>