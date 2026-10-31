<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/NotificationController.php';

// =====================================================================================
// MatchingController: ghép sinh viên (hoặc nhóm) với đề tài đồ án.
//
// "Ứng viên" là một sinh viên lẻ hoặc một nhóm (nhóm dùng nguyện vọng của nhóm trưởng và chiếm `size` chỗ).
// Đề tài xếp hạng ứng viên theo "điểm ưu tiên": thứ hạng giảng viên đặt (nếu có) luôn đứng trước,
// còn lại là tổ hợp GPA, thời điểm đăng ký nguyện vọng và mức khớp kỹ năng (trọng số cấu hình được).
// Đồ án đã khóa / đã được sinh viên xác nhận được giữ nguyên và trừ vào sức chứa đề tài trước khi chạy.
// Mỗi lần chạy lưu vào matching_runs (tham số, kết quả, chỉ số) để xem trước, so sánh và công bố.
// =====================================================================================

const MATCHING_ALGORITHMS = [
    'gs_student' => 'Gale-Shapley · sinh viên đề xuất',
    'gs_topic' => 'Gale-Shapley · đề tài đề xuất',
    'serial_dictatorship' => 'Serial Dictatorship · lần lượt theo thứ tự ưu tiên',
    'hungarian' => 'Hungarian · tối ưu tổng mức hài lòng',
];

// Tham số mặc định của một lần chạy.
function matching_default_params(): array
{
    return ['gpa_weight' => 0.6, 'time_weight' => 0.2, 'skill_weight' => 0.2, 'tie_break' => 'gpa', 'order_by' => 'gpa', 'use_lecturer_rankings' => 1, 'keep_existing' => 0];
}

// Đọc tham số từ form, giới hạn trong khoảng hợp lệ.
function matching_params(array $input): array
{
    $params = matching_default_params();
    foreach (['gpa_weight', 'time_weight', 'skill_weight'] as $key) {
        $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_FLOAT);
        $params[$key] = $value === false ? $params[$key] : max(0.0, min(1.0, (float) $value));
    }
    if ($params['gpa_weight'] + $params['time_weight'] + $params['skill_weight'] <= 0) {
        $params['gpa_weight'] = 1.0;
    }
    $params['tie_break'] = in_array($input['tie_break'] ?? '', ['gpa', 'time', 'random'], true) ? $input['tie_break'] : 'gpa';
    $params['order_by'] = in_array($input['order_by'] ?? '', ['gpa', 'time'], true) ? $input['order_by'] : 'gpa';
    $params['use_lecturer_rankings'] = !empty($input['use_lecturer_rankings']) ? 1 : 0;
    $params['keep_existing'] = !empty($input['keep_existing']) ? 1 : 0;
    $params['auto_fill'] = !empty($input['auto_fill']) ? 1 : 0;
    return $params;
}

// Nạp dữ liệu đầu vào: đề tài đã duyệt (sức chứa còn lại), ứng viên đủ điều kiện, nguyện vọng và điểm ưu tiên.
function matching_load_inputs(int $batchId, array $params): array
{
    $topics = [];
    foreach (page_all('SELECT id, title, max_students, lecturer_id, requirements, technologies, field FROM topics WHERE batch_id = ? AND status = \'approved\' ORDER BY id', [$batchId]) as $row) {
        $topics[(int) $row['id']] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'capacity' => (int) $row['max_students'],
            'lecturer_id' => $row['lecturer_id'] !== null ? (int) $row['lecturer_id'] : null,
            'terms' => array_unique(page_recommendation_tokens($row['title'] . ' ' . $row['requirements'] . ' ' . $row['technologies'] . ' ' . $row['field'])),
            'ranking' => [],
        ];
    }
    if ($topics) {
        foreach (page_all('SELECT topic_id, student_id, rank_no FROM lecturer_rankings WHERE topic_id IN (' . implode(',', array_fill(0, count($topics), '?')) . ')', array_keys($topics)) as $row) {
            $topics[(int) $row['topic_id']]['ranking'][(int) $row['student_id']] = (int) $row['rank_no'];
        }
    }

    // Đồ án đã chốt giữ nguyên chỗ. Vòng bổ sung (keep_existing) giữ cả kết quả đã công bố chưa bị từ chối.
    $fixedCondition = $params['keep_existing'] ? 'status IN (\'assigned\', \'confirmed\', \'in_progress\', \'completed\')' : '(locked = 1 OR status IN (\'confirmed\', \'in_progress\', \'completed\'))';
    $fixed = [];
    foreach (page_all('SELECT student_id, topic_id FROM theses WHERE batch_id = ? AND ' . $fixedCondition, [$batchId]) as $row) {
        $fixed[(int) $row['student_id']] = (int) $row['topic_id'];
        if (isset($topics[(int) $row['topic_id']])) {
            $topics[(int) $row['topic_id']]['capacity'] = max(0, $topics[(int) $row['topic_id']]['capacity'] - 1);
        }
    }

    // Tổng hạn mức của giảng viên trong đợt: nếu tổng chỗ các đề tài của một giảng viên vượt số chỗ còn lại
    // (hạn mức của đợt trừ sinh viên đã chốt), giảm bớt chỗ của đề tài nhiều chỗ nhất trước.
    $quotas = lecturer_quotas($batchId);
    $fixedLoad = [];
    foreach (page_all('SELECT supervisor_id, COUNT(*) AS total FROM theses WHERE batch_id = ? AND supervisor_id IS NOT NULL AND ' . $fixedCondition . ' GROUP BY supervisor_id', [$batchId]) as $row) {
        $fixedLoad[(int) $row['supervisor_id']] = (int) $row['total'];
    }
    $byLecturer = [];
    foreach ($topics as $topicId => $topic) {
        if ($topic['lecturer_id'] !== null) {
            $byLecturer[$topic['lecturer_id']][] = $topicId;
        }
    }
    $quotaLimited = [];
    foreach ($byLecturer as $lecturerId => $topicIds) {
        $remaining = max(0, ($quotas[$lecturerId] ?? PHP_INT_MAX) - ($fixedLoad[$lecturerId] ?? 0));
        while (array_sum(array_map(static fn($id) => $topics[$id]['capacity'], $topicIds)) > $remaining) {
            usort($topicIds, static fn($a, $b) => $topics[$b]['capacity'] <=> $topics[$a]['capacity']);
            $topics[$topicIds[0]]['capacity']--;
            $quotaLimited[$lecturerId] = true;
        }
    }

    // Có danh sách đủ điều kiện thì chỉ ghép sinh viên trong danh sách; chưa lập danh sách thì lấy mọi sinh viên đã đăng ký nguyện vọng.
    $hasList = page_count('SELECT COUNT(*) FROM batch_students WHERE batch_id = ?', [$batchId]) > 0;
    $studentRows = $hasList
        ? page_all('SELECT s.id, s.gpa, CONCAT_WS(\' \', s.skills, s.interests) AS skills, s.student_code, u.full_name FROM batch_students b JOIN students s ON s.id = b.student_id JOIN users u ON u.id = s.user_id WHERE b.batch_id = ? AND b.eligible = 1', [$batchId])
        : page_all('SELECT DISTINCT s.id, s.gpa, CONCAT_WS(\' \', s.skills, s.interests) AS skills, s.student_code, u.full_name FROM topic_preferences p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id WHERE p.batch_id = ?', [$batchId]);
    $students = [];
    foreach ($studentRows as $row) {
        $students[(int) $row['id']] = $row;
    }

    $prefs = [];
    $registeredAt = [];
    foreach (page_all('SELECT student_id, topic_id, rank_no, created_at FROM topic_preferences WHERE batch_id = ? ORDER BY student_id, rank_no', [$batchId]) as $row) {
        $studentId = (int) $row['student_id'];
        if (isset($topics[(int) $row['topic_id']])) {
            $prefs[$studentId][] = (int) $row['topic_id'];
        }
        $time = (int) strtotime((string) $row['created_at']);
        $registeredAt[$studentId] = min($registeredAt[$studentId] ?? PHP_INT_MAX, $time);
    }

    $groups = [];
    foreach (page_all('SELECT g.id, g.name, g.leader_student_id, m.student_id, m.status FROM thesis_groups g LEFT JOIN thesis_group_members m ON m.group_id = g.id WHERE g.batch_id = ?', [$batchId]) as $row) {
        $groupId = (int) $row['id'];
        $groups[$groupId] ??= ['name' => $row['name'], 'leader' => (int) $row['leader_student_id'], 'members' => [(int) $row['leader_student_id']]];
        if ($row['student_id'] !== null && $row['status'] === 'accepted') {
            $groups[$groupId]['members'][] = (int) $row['student_id'];
        }
    }

    $applicants = [];
    $used = [];
    $makeApplicant = static function (string $key, string $name, array $memberIds, int $leader) use ($students, $prefs, $registeredAt): array {
        $gpas = array_map(static fn($id) => (float) ($students[$id]['gpa'] ?? 0), $memberIds);
        $skills = implode(' ', array_map(static fn($id) => (string) ($students[$id]['skills'] ?? ''), $memberIds));
        return [
            'key' => $key,
            'name' => $name,
            'student_ids' => $memberIds,
            'size' => count($memberIds),
            'prefs' => $prefs[$leader] ?? [],
            'gpa' => $gpas ? array_sum($gpas) / count($gpas) : 0.0,
            'registered' => $registeredAt[$leader] ?? PHP_INT_MAX,
            'terms' => array_unique(page_recommendation_tokens($skills)),
        ];
    };
    foreach ($groups as $groupId => $group) {
        $members = array_values(array_unique(array_filter($group['members'], static fn($id) => isset($students[$id]) && !isset($fixed[$id]))));
        if (!$members || !in_array($group['leader'], $members, true)) {
            continue;
        }
        $applicants['g' . $groupId] = $makeApplicant('g' . $groupId, 'Nhóm ' . $group['name'], $members, $group['leader']);
        foreach ($members as $memberId) {
            $used[$memberId] = true;
        }
    }
    foreach ($students as $studentId => $student) {
        if (!isset($used[$studentId]) && !isset($fixed[$studentId])) {
            $applicants['s' . $studentId] = $makeApplicant('s' . $studentId, $student['full_name'] . ' · ' . $student['student_code'], [$studentId], $studentId);
        }
    }

    // Điểm ưu tiên của từng đề tài với từng ứng viên có nguyện vọng vào đề tài đó.
    $times = array_filter(array_column($applicants, 'registered'), static fn($time) => $time !== PHP_INT_MAX);
    $earliest = $times ? min($times) : 0;
    $latest = $times ? max($times) : 0;
    $priority = [];
    foreach ($applicants as $key => $applicant) {
        foreach ($applicant['prefs'] as $topicId) {
            $priority[$topicId][$key] = matching_priority($applicant, $topics[$topicId], $params, $earliest, $latest);
        }
    }

    return ['topics' => $topics, 'applicants' => $applicants, 'priority' => $priority, 'fixed' => $fixed, 'params' => $params];
}

// Điểm ưu tiên (càng lớn càng được ưu tiên). Thứ hạng giảng viên đặt cho sinh viên luôn thắng các tiêu chí khác.
function matching_priority(array $applicant, array $topic, array $params, int $earliest, int $latest): float
{
    if ($params['use_lecturer_rankings']) {
        $ranks = array_values(array_filter(array_map(static fn($id) => $topic['ranking'][$id] ?? null, $applicant['student_ids']), static fn($rank) => $rank !== null));
        if ($ranks) {
            return 1000.0 - min($ranks);
        }
    }
    $gpa = min(1.0, $applicant['gpa'] / 4.0);
    $time = $applicant['registered'] === PHP_INT_MAX ? 0.0 : ($latest > $earliest ? ($latest - $applicant['registered']) / ($latest - $earliest) : 1.0);
    $skill = $topic['terms'] ? count(array_intersect($topic['terms'], $applicant['terms'])) / count($topic['terms']) : 0.0;
    $score = 100 * ($params['gpa_weight'] * $gpa + $params['time_weight'] * $time + $params['skill_weight'] * min(1.0, $skill));

    // Phá đồng hạng: cộng một lượng rất nhỏ theo tiêu chí đã chọn.
    $tie = match ($params['tie_break']) {
        'time' => $time,
        'random' => (crc32($applicant['key'] . '|' . $topic['id']) % 1000) / 1000,
        default => $gpa,
    };
    return $score + $tie * 0.001;
}

// Thứ tự toàn cục của ứng viên (dùng cho Serial Dictatorship): GPA giảm dần hoặc đăng ký sớm trước.
function matching_global_order(array $applicants, string $orderBy): array
{
    $keys = array_keys($applicants);
    usort($keys, static function ($left, $right) use ($applicants, $orderBy) {
        $a = $applicants[$left];
        $b = $applicants[$right];
        $primary = $orderBy === 'time' ? $a['registered'] <=> $b['registered'] : $b['gpa'] <=> $a['gpa'];
        return $primary ?: ($orderBy === 'time' ? $b['gpa'] <=> $a['gpa'] : $a['registered'] <=> $b['registered']) ?: strcmp($left, $right);
    });
    return $keys;
}

// Gale-Shapley ứng viên đề xuất (deferred acceptance có sức chứa): đề tài tạm giữ những ứng viên ưu tiên nhất vừa đủ chỗ.
function matching_gs_student(array $in): array
{
    $next = array_fill_keys(array_keys($in['applicants']), 0);
    $held = array_fill_keys(array_keys($in['topics']), []);
    $free = array_reverse(array_keys($in['applicants']));
    while ($free) {
        $key = array_pop($free);
        $applicant = $in['applicants'][$key];
        if ($next[$key] >= count($applicant['prefs'])) {
            continue;
        }
        $topicId = $applicant['prefs'][$next[$key]++];
        $held[$topicId][] = $key;
        usort($held[$topicId], static fn($a, $b) => $in['priority'][$topicId][$b] <=> $in['priority'][$topicId][$a]);
        $kept = [];
        $seats = 0;
        foreach ($held[$topicId] as $candidate) {
            $size = $in['applicants'][$candidate]['size'];
            if ($seats + $size <= $in['topics'][$topicId]['capacity']) {
                $kept[] = $candidate;
                $seats += $size;
            } else {
                $free[] = $candidate;
            }
        }
        $held[$topicId] = $kept;
    }
    $assignment = array_fill_keys(array_keys($in['applicants']), null);
    foreach ($held as $topicId => $keys) {
        foreach ($keys as $key) {
            $assignment[$key] = $topicId;
        }
    }
    return $assignment;
}

// Gale-Shapley đề tài đề xuất: mỗi đề tài lần lượt mời ứng viên theo thứ tự ưu tiên của mình; ứng viên giữ lời mời tốt nhất theo nguyện vọng.
function matching_gs_topic(array $in): array
{
    $rankOf = [];
    foreach ($in['applicants'] as $key => $applicant) {
        $rankOf[$key] = array_flip($applicant['prefs']);
    }
    $queues = [];
    foreach ($in['topics'] as $topicId => $topic) {
        $list = array_keys($in['priority'][$topicId] ?? []);
        usort($list, static fn($a, $b) => $in['priority'][$topicId][$b] <=> $in['priority'][$topicId][$a]);
        $queues[$topicId] = $list;
    }
    $current = array_fill_keys(array_keys($in['applicants']), null);
    $seats = array_fill_keys(array_keys($in['topics']), 0);
    do {
        $changed = false;
        foreach ($queues as $topicId => $list) {
            while ($queues[$topicId] && $seats[$topicId] < $in['topics'][$topicId]['capacity']) {
                $key = array_shift($queues[$topicId]);
                $size = $in['applicants'][$key]['size'];
                if ($seats[$topicId] + $size > $in['topics'][$topicId]['capacity']) {
                    continue;
                }
                $held = $current[$key];
                if ($held === null || $rankOf[$key][$topicId] < $rankOf[$key][$held]) {
                    if ($held !== null) {
                        $seats[$held] -= $size;
                    }
                    $current[$key] = $topicId;
                    $seats[$topicId] += $size;
                    $changed = true;
                }
            }
        }
    } while ($changed);
    return $current;
}

// Serial Dictatorship: theo thứ tự ưu tiên toàn cục, mỗi ứng viên nhận nguyện vọng cao nhất còn đủ chỗ.
function matching_serial_dictatorship(array $in): array
{
    $seats = array_map(static fn($topic) => $topic['capacity'], $in['topics']);
    $assignment = array_fill_keys(array_keys($in['applicants']), null);
    foreach (matching_global_order($in['applicants'], $in['params']['order_by']) as $key) {
        $size = $in['applicants'][$key]['size'];
        foreach ($in['applicants'][$key]['prefs'] as $topicId) {
            if ($seats[$topicId] >= $size) {
                $assignment[$key] = $topicId;
                $seats[$topicId] -= $size;
                break;
            }
        }
    }
    return $assignment;
}

// Hungarian: tối thiểu hóa tổng thứ hạng nguyện vọng (×số thành viên). Mỗi đề tài tách thành `capacity` chỗ;
// mỗi ứng viên có thêm một cột "không được ghép" với chi phí lớn. Nhóm đông có thể vượt sức chứa
// sau bước gán nên được sửa lại: bỏ bớt ứng viên ưu tiên thấp và xếp họ vào nguyện vọng còn chỗ.
function matching_hungarian(array $in): array
{
    $keys = array_keys($in['applicants']);
    $n = count($keys);
    $assignment = array_fill_keys($keys, null);
    if ($n === 0) {
        return $assignment;
    }
    $slots = [];
    foreach ($in['topics'] as $topicId => $topic) {
        for ($seat = 0; $seat < $topic['capacity']; $seat++) {
            $slots[] = $topicId;
        }
    }
    $maxPrefs = max(1, ...array_values(array_map(static fn($applicant) => count($applicant['prefs']), $in['applicants'])));
    $unassignedCost = ($maxPrefs + 1) * 10.0;
    $forbidden = 1.0e7;
    $m = count($slots) + $n;
    $cost = [];
    foreach ($keys as $row => $key) {
        $applicant = $in['applicants'][$key];
        $rankOf = array_flip($applicant['prefs']);
        $line = [];
        foreach ($slots as $topicId) {
            $line[] = isset($rankOf[$topicId]) && $in['topics'][$topicId]['capacity'] >= $applicant['size'] ? ($rankOf[$topicId] + 1) * $applicant['size'] : $forbidden;
        }
        for ($dummy = 0; $dummy < $n; $dummy++) {
            $line[] = $unassignedCost * $applicant['size'];
        }
        $cost[$row] = $line;
    }
    foreach (matching_hungarian_solve($cost, $n, $m) as $row => $column) {
        if ($column < count($slots) && $cost[$row][$column] < $forbidden) {
            $assignment[$keys[$row]] = $slots[$column];
        }
    }

    // Sửa vượt sức chứa do nhóm nhiều thành viên.
    $seats = array_fill_keys(array_keys($in['topics']), 0);
    $byTopic = [];
    foreach ($assignment as $key => $topicId) {
        if ($topicId !== null) {
            $byTopic[$topicId][] = $key;
        }
    }
    $bumped = [];
    foreach ($byTopic as $topicId => $list) {
        usort($list, static fn($a, $b) => $in['priority'][$topicId][$b] <=> $in['priority'][$topicId][$a]);
        foreach ($list as $key) {
            $size = $in['applicants'][$key]['size'];
            if ($seats[$topicId] + $size <= $in['topics'][$topicId]['capacity']) {
                $seats[$topicId] += $size;
            } else {
                $assignment[$key] = null;
                $bumped[] = $key;
            }
        }
    }
    foreach ($bumped as $key) {
        foreach ($in['applicants'][$key]['prefs'] as $topicId) {
            if ($seats[$topicId] + $in['applicants'][$key]['size'] <= $in['topics'][$topicId]['capacity']) {
                $assignment[$key] = $topicId;
                $seats[$topicId] += $in['applicants'][$key]['size'];
                break;
            }
        }
    }
    return $assignment;
}

// Thuật toán Hungarian (Kuhn–Munkres) cho ma trận chi phí n × m (n <= m). Trả về [hàng => cột], đánh số từ 0.
function matching_hungarian_solve(array $cost, int $n, int $m): array
{
    $u = array_fill(0, $n + 1, 0.0);
    $v = array_fill(0, $m + 1, 0.0);
    $p = array_fill(0, $m + 1, 0);
    $way = array_fill(0, $m + 1, 0);
    for ($i = 1; $i <= $n; $i++) {
        $p[0] = $i;
        $j0 = 0;
        $minv = array_fill(0, $m + 1, INF);
        $used = array_fill(0, $m + 1, false);
        do {
            $used[$j0] = true;
            $i0 = $p[$j0];
            $delta = INF;
            $j1 = 0;
            $row = $cost[$i0 - 1];
            for ($j = 1; $j <= $m; $j++) {
                if (!$used[$j]) {
                    $current = $row[$j - 1] - $u[$i0] - $v[$j];
                    if ($current < $minv[$j]) {
                        $minv[$j] = $current;
                        $way[$j] = $j0;
                    }
                    if ($minv[$j] < $delta) {
                        $delta = $minv[$j];
                        $j1 = $j;
                    }
                }
            }
            for ($j = 0; $j <= $m; $j++) {
                if ($used[$j]) {
                    $u[$p[$j]] += $delta;
                    $v[$j] -= $delta;
                } else {
                    $minv[$j] -= $delta;
                }
            }
            $j0 = $j1;
        } while ($p[$j0] !== 0);
        do {
            $j1 = $way[$j0];
            $p[$j0] = $p[$j1];
            $j0 = $j1;
        } while ($j0 !== 0);
    }
    $result = [];
    for ($j = 1; $j <= $m; $j++) {
        if ($p[$j] !== 0) {
            $result[$p[$j] - 1] = $j - 1;
        }
    }
    return $result;
}

// Chỉ số đánh giá kết quả: tỷ lệ được nguyện vọng 1/2/3, số chưa ghép, số cặp bất ổn (blocking pairs), độ lấp đầy đề tài.
function matching_metrics(array $in, array $assignment): array
{
    $seats = array_fill_keys(array_keys($in['topics']), 0);
    $holders = [];
    foreach ($assignment as $key => $topicId) {
        if ($topicId !== null) {
            $seats[$topicId] += $in['applicants'][$key]['size'];
            $holders[$topicId][] = $key;
        }
    }
    $rankCounts = ['1' => 0, '2' => 0, '3' => 0, '4+' => 0];
    $students = 0;
    $matched = 0;
    $rankSum = 0;
    $noPrefs = 0;
    foreach ($in['applicants'] as $key => $applicant) {
        $students += $applicant['size'];
        if (!$applicant['prefs']) {
            $noPrefs += $applicant['size'];
        }
        $topicId = $assignment[$key] ?? null;
        if ($topicId === null) {
            continue;
        }
        $matched += $applicant['size'];
        // Được tự xếp (đề tài ngoài nguyện vọng): tính là đã ghép nhưng không vào thống kê thứ hạng nguyện vọng.
        if (!in_array($topicId, $applicant['prefs'], true)) {
            continue;
        }
        $rank = array_search($topicId, $applicant['prefs'], true) + 1;
        $rankSum += $rank * $applicant['size'];
        $rankCounts[$rank >= 4 ? '4+' : (string) $rank] += $applicant['size'];
    }

    // Cặp bất ổn: ứng viên a thích đề tài t hơn kết quả hiện tại, và t còn đủ chỗ hoặc t ưu tiên a hơn một người đang giữ chỗ.
    $blocking = 0;
    foreach ($in['applicants'] as $key => $applicant) {
        $current = $assignment[$key] ?? null;
        foreach ($applicant['prefs'] as $topicId) {
            if ($topicId === $current) {
                break;
            }
            $free = $in['topics'][$topicId]['capacity'] - $seats[$topicId];
            $better = false;
            foreach ($holders[$topicId] ?? [] as $holder) {
                if (($in['priority'][$topicId][$holder] ?? -INF) < $in['priority'][$topicId][$key] && $in['applicants'][$holder]['size'] + $free >= $applicant['size']) {
                    $better = true;
                    break;
                }
            }
            if ($free >= $applicant['size'] || $better) {
                $blocking++;
            }
        }
    }

    $demand = [];
    foreach ($in['applicants'] as $applicant) {
        foreach ($applicant['prefs'] as $index => $topicId) {
            $demand[$topicId]['total'] = ($demand[$topicId]['total'] ?? 0) + $applicant['size'];
            if ($index === 0) {
                $demand[$topicId]['first'] = ($demand[$topicId]['first'] ?? 0) + $applicant['size'];
            }
        }
    }
    $capacity = array_sum(array_column($in['topics'], 'capacity'));

    return [
        'applicants' => count($in['applicants']),
        'students' => $students,
        'matched' => $matched,
        'unmatched' => $students - $matched,
        'no_prefs' => $noPrefs,
        'rank_counts' => $rankCounts,
        'rank_rate' => array_map(static fn($count) => $students ? round($count * 100 / $students, 1) : 0, $rankCounts),
        'average_rank' => $matched ? round($rankSum / $matched, 2) : null,
        'blocking_pairs' => $blocking,
        'capacity' => $capacity,
        'fill_rate' => $capacity ? round(array_sum($seats) * 100 / $capacity, 1) : 0,
        'fixed' => count($in['fixed']),
        'demand' => $demand,
    ];
}

// Chạy một thuật toán, lưu vào lịch sử ở trạng thái xem trước và trả về mã lần chạy.
// Xếp sinh viên / nhóm chưa được ghép (không có nguyện vọng hợp lệ hoặc trượt mọi nguyện vọng) vào đề tài còn chỗ,
// chọn đề tài có nhiều từ khóa trùng với kỹ năng + lĩnh vực quan tâm nhất (đồng điểm: đề tài còn nhiều chỗ hơn).
function matching_auto_fill(array $in, array &$assignment): array
{
    $left = [];
    foreach ($in['topics'] as $topicId => $topic) {
        $left[$topicId] = $topic['capacity'];
    }
    foreach ($assignment as $key => $topicId) {
        if ($topicId !== null && isset($left[$topicId])) {
            $left[$topicId] -= $in['applicants'][$key]['size'] ?? 1;
        }
    }
    $filled = [];
    $waiting = array_filter($in['applicants'], static fn($applicant) => ($assignment[$applicant['key']] ?? null) === null);
    uasort($waiting, static fn($a, $b) => $b['gpa'] <=> $a['gpa']);
    foreach ($waiting as $key => $applicant) {
        $best = null;
        $bestScore = -1.0;
        foreach ($in['topics'] as $topicId => $topic) {
            if ($left[$topicId] < $applicant['size']) {
                continue;
            }
            $score = count(array_intersect($applicant['terms'], $topic['terms'])) + $left[$topicId] / 100;
            if ($score > $bestScore) {
                $best = $topicId;
                $bestScore = $score;
            }
        }
        if ($best !== null) {
            $assignment[$key] = $best;
            $left[$best] -= $applicant['size'];
            $filled[$key] = $best;
        }
    }
    return $filled;
}

function matching_run(int $batchId, string $algorithm, array $params, int $userId): int
{
    if (!isset(MATCHING_ALGORITHMS[$algorithm])) {
        throw new DomainException('Thuật toán ghép không hợp lệ.');
    }
    $in = matching_load_inputs($batchId, $params);
    if (!$in['topics']) {
        throw new DomainException('Đợt này chưa có đề tài nào được duyệt để ghép.');
    }
    if (!$in['applicants']) {
        throw new DomainException('Không còn sinh viên nào cần ghép (chưa có nguyện vọng hoặc đều đã được chốt).');
    }
    $started = microtime(true);
    $assignment = match ($algorithm) {
        'gs_student' => matching_gs_student($in),
        'gs_topic' => matching_gs_topic($in),
        'serial_dictatorship' => matching_serial_dictatorship($in),
        'hungarian' => matching_hungarian($in),
    };
    $autoFilled = !empty($params['auto_fill']) ? matching_auto_fill($in, $assignment) : [];
    $metrics = matching_metrics($in, $assignment);
    $metrics['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);
    $metrics['auto_filled'] = count($autoFilled);

    $result = [];
    foreach ($in['applicants'] as $key => $applicant) {
        $topicId = $assignment[$key] ?? null;
        $result[] = [
            'key' => $key,
            'name' => $applicant['name'],
            'student_ids' => $applicant['student_ids'],
            'topic_id' => $topicId,
            'rank' => $topicId !== null && in_array($topicId, $applicant['prefs'], true) ? array_search($topicId, $applicant['prefs'], true) + 1 : null,
            'auto' => isset($autoFilled[$key]),
            'prefs' => count($applicant['prefs']),
        ];
    }
    database()->prepare('INSERT INTO matching_runs (batch_id, algorithm, params, result, metrics, created_by) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        $batchId,
        $algorithm,
        json_encode($params, JSON_UNESCAPED_UNICODE),
        json_encode($result, JSON_UNESCAPED_UNICODE),
        json_encode($metrics, JSON_UNESCAPED_UNICODE),
        $userId,
    ]);
    return (int) database()->lastInsertId();
}

// Công bố một lần chạy: thay kết quả chưa chốt bằng kết quả mới, giảng viên đăng đề tài mặc nhiên là người hướng dẫn.
function matching_publish(int $runId): int
{
    $run = page_one('SELECT * FROM matching_runs WHERE id = ?', [$runId]);
    if (!$run || $run['status'] !== 'preview') {
        throw new DomainException('Chỉ công bố được lần chạy thử chưa công bố.');
    }
    $params = json_decode((string) $run['params'], true) ?: [];
    $result = json_decode((string) $run['result'], true) ?: [];
    $topics = [];
    foreach (page_all('SELECT id, lecturer_id, title FROM topics WHERE batch_id = ?', [(int) $run['batch_id']]) as $topic) {
        $topics[(int) $topic['id']] = $topic;
    }
    $inserted = 0;
    $notices = [];
    database_transaction(static function (PDO $connection) use ($run, $params, $result, $topics, &$inserted, &$notices): void {
        // Đồ án đã khóa / đã xác nhận không bao giờ bị ghi đè; đổi sau khi chạy thử thì kết quả đã cũ (sức chứa đã khác).
        $fixedStudents = [];
        foreach (page_all('SELECT student_id, updated_at FROM theses WHERE batch_id = ? AND (locked = 1 OR status IN (\'confirmed\', \'in_progress\', \'completed\')) FOR UPDATE', [(int) $run['batch_id']]) as $fixed) {
            if ($fixed['updated_at'] > $run['created_at']) {
                throw new DomainException('Có đồ án đã được xác nhận hoặc khóa sau lần chạy này. Hãy chạy thử lại trước khi công bố.');
            }
            $fixedStudents[(int) $fixed['student_id']] = true;
        }
        $connection->prepare(
            'DELETE FROM theses WHERE batch_id = ? AND locked = 0 AND ' . (!empty($params['keep_existing']) ? 'status = \'declined\'' : 'status IN (\'assigned\', \'declined\')')
        )->execute([(int) $run['batch_id']]);
        $insert = $connection->prepare(
            'INSERT INTO theses (batch_id, student_id, topic_id, group_id, supervisor_id, source, matched_rank, status) VALUES (?, ?, ?, ?, ?, \'auto\', ?, \'assigned\')
             ON DUPLICATE KEY UPDATE topic_id = VALUES(topic_id), group_id = VALUES(group_id), supervisor_id = VALUES(supervisor_id), source = \'auto\', matched_rank = VALUES(matched_rank), status = \'assigned\', student_confirmed_at = NULL'
        );
        foreach ($result as $row) {
            if (empty($row['topic_id']) || !isset($topics[(int) $row['topic_id']])) {
                continue;
            }
            $groupId = str_starts_with((string) $row['key'], 'g') ? (int) substr((string) $row['key'], 1) : null;
            foreach ($row['student_ids'] as $studentId) {
                if (isset($fixedStudents[(int) $studentId])) {
                    continue;
                }
                $insert->execute([(int) $run['batch_id'], (int) $studentId, (int) $row['topic_id'], $groupId, $topics[(int) $row['topic_id']]['lecturer_id'], $row['rank']]);
                $inserted++;
                $notices[] = [(int) $studentId, $topics[(int) $row['topic_id']]['title']];
            }
        }
        $connection->prepare('UPDATE matching_runs SET status = \'published\', published_at = NOW() WHERE id = ?')->execute([(int) $run['id']]);
    });
    foreach ($notices as [$studentId, $title]) {
        notify_student($studentId, 'thesis', 'Đã có kết quả ghép đề tài', 'Bạn được ghép với đề tài "' . $title . '". Hãy vào mục Đồ án để xác nhận.', 'student/thesis', true);
    }
    return $inserted;
}
