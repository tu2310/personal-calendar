<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/Task.php';
require_once __DIR__ . '/../Models/Category.php';

class DashboardController
{
    private Event $eventModel;
    private Task $taskModel;
    private Category $categoryModel;

    public function __construct()
    {
        $this->eventModel = new Event();
        $this->taskModel = new Task();
        $this->categoryModel = new Category();
    }

    public function index(): array
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $user = Auth::user();

        // Lấy thống kê số liệu thật từ database theo user_id
        $statData = $this->eventModel->getStats($userId);
        $taskStats = $this->taskModel->getStats($userId);
        $todayEventsRaw = $this->eventModel->getTodayEvents($userId);
        $allEvents = $this->eventModel->allForUser($userId);
        $weeklyStats = $this->eventModel->getWeeklyStats($userId);

        $colorMap = [
            '#38bdf8' => 'blue',
            '#f59e0b' => 'orange',
            '#10b981' => 'green',
            '#5b52e0' => 'purple',
            '#f43f5e' => 'orange'
        ];

        // Format danh sách lịch trình hôm nay
        $events = [];
        foreach ($todayEventsRaw as $item) {
            $catColor = $item['category_color'] ?? '#6658e8';
            $tone = $colorMap[$catColor] ?? 'purple';
            $timeLabel = date('H:i', strtotime($item['start_time'])) . ' - ' . date('H:i', strtotime($item['end_time']));
            $events[] = [
                'id'    => $item['id'],
                'time'  => $timeLabel,
                'title' => $item['title'],
                'place' => !empty($item['location']) ? $item['location'] : 'Chưa có địa điểm',
                'color' => $tone,
                'tag'   => !empty($item['category_name']) ? $item['category_name'] : 'Chung'
            ];
        }

        // Format danh sách công việc ưu tiên từ bảng tasks (nếu có) hoặc từ allEvents (fallback)
        $tasks = [];
        $rawTasks = $this->taskModel->allForUser($userId, ['sort' => 'priority_desc']);

        if (!empty($rawTasks)) {
            $slice = array_slice($rawTasks, 0, 6);
            foreach ($slice as $t) {
                $pLabelMap = ['urgent' => 'Khẩn cấp', 'high' => 'Cao', 'medium' => 'Trung bình', 'low' => 'Thấp'];
                $timeText = !empty($t['due_date']) ? 'Hạn: ' . date('d/m', strtotime($t['due_date'])) : 'Không có hạn';
                $tasks[] = [
                    'id'       => $t['id'],
                    'title'    => $t['title'],
                    'time'     => $timeText,
                    'priority' => $pLabelMap[$t['priority']] ?? 'Trung bình',
                    'priority_key' => $t['priority'],
                    'done'     => ($t['status'] === 'completed' || $t['is_completed'] == 1),
                    'is_task'  => true
                ];
            }
        } else {
            $recentSlice = array_slice($allEvents, 0, 6);
            foreach ($recentSlice as $item) {
                $priorityLabel = $item['priority'] === 'high' ? 'Cao' : ($item['priority'] === 'low' ? 'Thấp' : 'Trung bình');
                $tasks[] = [
                    'id'       => $item['id'],
                    'title'    => $item['title'],
                    'time'     => date('d/m H:i', strtotime($item['start_time'])),
                    'priority' => $priorityLabel,
                    'priority_key' => $item['priority'],
                    'done'     => $item['status'] === 'completed',
                    'is_task'  => false
                ];
            }
        }

        // Chuẩn hóa danh sách gộp cho màn "Hôm nay" mobile-first (Sự kiện hôm nay + Việc hôm nay/quá hạn)
        $todayTasksRaw = $this->taskModel->getTodayTasks($userId);
        $rawMerged = [];
        $todayStr = date('Y-m-d');
        $nowHm = date('H:i');

        foreach ($todayEventsRaw as $ev) {
            $id = (int)$ev['id'];
            $done = (($ev['status'] ?? '') === 'completed');
            $startDatePart = substr((string)$ev['start_time'], 0, 10);
            $endDatePart = substr((string)$ev['end_time'], 0, 10);
            $startHm = substr((string)$ev['start_time'], 11, 5);
            $endHm = substr((string)$ev['end_time'], 11, 5);
            $isMultiday = ($startDatePart !== $endDatePart);
            $isFullDay = ($startHm === '00:00' && $endHm === '23:59');

            if ($isFullDay && !$isMultiday) {
                $timeLabel = 'Cả ngày';
            } elseif ($isMultiday) {
                $rangeStr = date('d/m', strtotime($ev['start_time'])) . ' – ' . date('d/m', strtotime($ev['end_time']));
                $timeLabel = $isFullDay ? ('Cả ngày · ' . $rangeStr) : ($startHm . ' · ' . $rangeStr);
            } else {
                $timeLabel = $startHm . ' – ' . $endHm;
            }

            $sortKey = ($isMultiday || $isFullDay)
                ? ('1_00:00_' . str_pad((string)$id, 8, '0', STR_PAD_LEFT))
                : ('2_' . $startHm . '_' . $endHm . '_0_' . str_pad((string)$id, 8, '0', STR_PAD_LEFT));

            $rawMerged[] = [
                'id'             => $id,
                'is_task'        => false,
                'title'          => (string)$ev['title'],
                'done'           => $done,
                'is_overdue'     => false,
                'time_label'     => $timeLabel,
                'sort_key'       => $sortKey,
                'is_current'     => false,
                'location'       => (string)($ev['location'] ?? ''),
                'category_name'  => (string)($ev['category_name'] ?? ''),
                'category_color' => (string)($ev['category_color'] ?: '#6658e8'),
                'priority'       => (string)($ev['priority'] ?? 'medium'),
                'repeat_type'    => (string)($ev['repeat_type'] ?? 'none'),
                'is_multiday'    => $isMultiday,
                '_start_hm'      => ($isMultiday || $isFullDay) ? null : $startHm,
                '_end_hm'        => ($isMultiday || $isFullDay) ? null : $endHm,
            ];
        }

        foreach ($todayTasksRaw as $t) {
            $id = (int)$t['id'];
            $done = (($t['status'] ?? '') === 'completed' || (int)($t['is_completed'] ?? 0) === 1);
            $dueDate = !empty($t['due_date']) ? (string)$t['due_date'] : null;
            $endDate = !empty($t['end_date']) ? (string)$t['end_date'] : null;
            $effectiveEnd = $endDate ?: $dueDate;
            $isOverdue = !$done && ($effectiveEnd !== null && $effectiveEnd < $todayStr);
            $isMultiday = ($dueDate !== null && $endDate !== null && $endDate > $dueDate);
            $st = !empty($t['start_time']) ? substr((string)$t['start_time'], 0, 5) : null;
            $et = !empty($t['end_time']) ? substr((string)$t['end_time'], 0, 5) : null;
            $isFullDay = ($st === '00:00' && $et === '23:59');

            if ($isOverdue) {
                $overdueDate = ($endDate !== null && $endDate < $todayStr) ? $endDate : $dueDate;
                $timeLabel = 'Quá hạn' . ($overdueDate ? (' · ' . date('d/m', strtotime($overdueDate))) : '');
                $sortKey = '0_' . ($dueDate ?? '0000-00-00') . '_' . ($st ?? '99:99') . '_' . str_pad((string)$id, 8, '0', STR_PAD_LEFT);
            } elseif ($isMultiday) {
                $rangeStr = date('d/m', strtotime($dueDate)) . ' – ' . date('d/m', strtotime($endDate));
                $timeLabel = ($st && $et && !$isFullDay) ? ("{$st} – {$et} · {$rangeStr}") : ("Cả ngày · {$rangeStr}");
                $sortKey = '1_00:00_' . str_pad((string)$id, 8, '0', STR_PAD_LEFT);
            } elseif ($st !== null) {
                if ($isFullDay) {
                    $timeLabel = 'Cả ngày';
                    $sortKey = '1_00:00_' . str_pad((string)$id, 8, '0', STR_PAD_LEFT);
                } else {
                    $timeLabel = $et !== null ? ("{$st} – {$et}") : $st;
                    $sortKey = '2_' . $st . '_' . ($et ?? $st) . '_1_' . str_pad((string)$id, 8, '0', STR_PAD_LEFT);
                }
            } else {
                $timeLabel = 'Chưa gắn giờ';
                $sortKey = '3_99:99_' . str_pad((string)$id, 8, '0', STR_PAD_LEFT);
            }

            $rawMerged[] = [
                'id'             => $id,
                'is_task'        => true,
                'title'          => (string)$t['title'],
                'done'           => $done,
                'is_overdue'     => $isOverdue,
                'time_label'     => $timeLabel,
                'sort_key'       => $sortKey,
                'is_current'     => false,
                'location'       => '',
                'category_name'  => (string)($t['category_name'] ?? ''),
                'category_color' => (string)($t['category_color'] ?: '#6658e8'),
                'priority'       => (string)($t['priority'] ?? 'medium'),
                'repeat_type'    => (string)($t['repeat_type'] ?? 'none'),
                'is_multiday'    => $isMultiday,
                '_start_hm'      => (!$isOverdue && !$isMultiday && !$isFullDay) ? $st : null,
                '_end_hm'        => (!$isOverdue && !$isMultiday && !$isFullDay) ? ($et ?? $st) : null,
            ];
        }

        usort($rawMerged, fn($a, $b) => strcmp($a['sort_key'], $b['sort_key']));

        // Đánh dấu is_current cho mục chưa xong đang diễn ra hoặc có khung giờ gần nhất so với hiện tại
        $currentIdx = null;
        foreach ($rawMerged as $idx => $item) {
            if (!$item['done'] && $item['_start_hm'] !== null && $item['_end_hm'] !== null) {
                if ($item['_start_hm'] <= $nowHm && $item['_end_hm'] >= $nowHm) {
                    $currentIdx = $idx;
                    break;
                }
            }
        }
        if ($currentIdx === null) {
            foreach ($rawMerged as $idx => $item) {
                if (!$item['done'] && $item['_start_hm'] !== null && $item['_start_hm'] >= $nowHm) {
                    $currentIdx = $idx;
                    break;
                }
            }
        }
        if ($currentIdx === null) {
            for ($idx = count($rawMerged) - 1; $idx >= 0; $idx--) {
                if (!$rawMerged[$idx]['done'] && $rawMerged[$idx]['_start_hm'] !== null) {
                    $currentIdx = $idx;
                    break;
                }
            }
        }

        $todayItems = [];
        $doneCount = 0;
        $overdueCount = 0;
        foreach ($rawMerged as $idx => $item) {
            if ($currentIdx !== null && $idx === $currentIdx) {
                $item['is_current'] = true;
            }
            if ($item['done']) {
                $doneCount++;
                continue;
            }
            if ($item['is_overdue']) {
                $overdueCount++;
            }
            unset($item['_start_hm'], $item['_end_hm']);
            $todayItems[] = $item;
        }

        $totalTodayCount = count($rawMerged);
        $todayPct = $totalTodayCount > 0 ? (int)round(($doneCount / $totalTodayCount) * 100) : 0;
        $todayProgress = [
            'done'    => $doneCount,
            'total'   => $totalTodayCount,
            'overdue' => $overdueCount,
            'percent' => $todayPct,
        ];

        return [
            'user' => $user,
            'stats' => [
                [
                    'label'  => 'Lịch trong ngày',
                    'value'  => str_pad((string)$statData['today_count'], 2, '0', STR_PAD_LEFT),
                    'change' => 'Hôm nay: ' . date('d/m/Y'),
                    'icon'   => 'fa-calendar-day',
                    'tone'   => 'blue'
                ],
                [
                    'label'  => 'Sắp diễn ra',
                    'value'  => str_pad((string)$statData['upcoming_count'], 2, '0', STR_PAD_LEFT),
                    'change' => 'Trong hôm nay',
                    'icon'   => 'fa-clock',
                    'tone'   => 'green'
                ],
                [
                    'label'  => 'Chưa hoàn thành',
                    'value'  => str_pad((string)($taskStats['pending'] > 0 ? $taskStats['pending'] : $statData['pending_count']), 2, '0', STR_PAD_LEFT),
                    'change' => $taskStats['overdue'] > 0 ? "Quá hạn: {$taskStats['overdue']} việc" : 'Cần xử lý',
                    'icon'   => 'fa-list-check',
                    'tone'   => $taskStats['overdue'] > 0 ? 'orange' : 'orange'
                ],
                [
                    'label'  => 'Đã hoàn thành',
                    'value'  => str_pad((string)($taskStats['completed'] > 0 ? $taskStats['completed'] : $statData['completed_count']), 2, '0', STR_PAD_LEFT),
                    'change' => $taskStats['total'] > 0 ? "Tiến độ: {$taskStats['progress_percent']}%" : 'Đã hoàn tất',
                    'icon'   => 'fa-circle-check',
                    'tone'   => 'purple'
                ]
            ],
            'taskStats'     => $taskStats,
            'events'        => $events,
            'tasks'         => $tasks,
            'weekly'        => $weeklyStats,
            'todayItems'    => $todayItems,
            'todayProgress' => $todayProgress,
            'categories'    => $this->categoryModel->getAll($userId),
            'csrf_token'    => Auth::csrfToken()
        ];
    }
}
