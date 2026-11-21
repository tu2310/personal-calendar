<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/Task.php';

class DashboardController
{
    private Event $eventModel;
    private Task $taskModel;

    public function __construct()
    {
        $this->eventModel = new Event();
        $this->taskModel = new Task();
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
            'taskStats'  => $taskStats,
            'events'     => $events,
            'tasks'      => $tasks,
            'weekly'     => $weeklyStats,
            'csrf_token' => Auth::csrfToken()
        ];
    }
}
