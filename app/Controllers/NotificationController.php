<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/Notification.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/Task.php';

class NotificationController
{
    private Notification $notifModel;
    private Event $eventModel;
    private Task $taskModel;

    public function __construct()
    {
        $this->notifModel = new Notification();
        $this->eventModel = new Event();
        $this->taskModel = new Task();
    }

    private function readInput(): array
    {
        if (!empty($_POST) && is_array($_POST)) {
            return $_POST;
        }
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function extractCsrf(array $input): string
    {
        $bodyToken = $input['csrf_token'] ?? null;
        if (is_string($bodyToken) && $bodyToken !== '') {
            return $bodyToken;
        }
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return is_string($headerToken) ? $headerToken : '';
    }

    public function apiList(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $this->syncUserReminders($userId);

        $rawType = $_GET['type'] ?? null;
        $type = is_string($rawType) ? trim($rawType) : null;
        $items = $this->notifModel->allForUser($userId, $type, 50);
        $unreadCount = Notification::countUnread($userId);

        echo json_encode([
            'success'      => true,
            'unread_count' => $unreadCount,
            'data'         => $items
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function markRead(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $input = $this->readInput();
        if (!Auth::verifyCsrf($this->extractCsrf($input))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $id = is_scalar($input['id'] ?? null) ? (int)$input['id'] : 0;
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID thông báo không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $this->notifModel->markAsRead($id, $userId);
        $unreadCount = Notification::countUnread($userId);

        echo json_encode([
            'success'      => $ok,
            'unread_count' => $unreadCount,
            'message'      => $ok ? 'Đã đánh dấu thông báo là đã đọc.' : 'Không thể cập nhật thông báo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function markAllRead(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $input = $this->readInput();
        if (!Auth::verifyCsrf($this->extractCsrf($input))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $this->notifModel->markAllAsRead($userId);

        echo json_encode([
            'success'      => true,
            'unread_count' => 0,
            'message'      => 'Đã đánh dấu tất cả thông báo là đã đọc!'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function delete(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $input = $this->readInput();
        if (!Auth::verifyCsrf($this->extractCsrf($input))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $id = is_scalar($input['id'] ?? null) ? (int)$input['id'] : 0;
        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $this->notifModel->delete($id, $userId);
        $unreadCount = Notification::countUnread($userId);

        echo json_encode([
            'success'      => $ok,
            'unread_count' => $unreadCount,
            'message'      => $ok ? 'Đã xóa thông báo.' : 'Không thể xóa thông báo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function deleteAll(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $input = $this->readInput();
        if (!Auth::verifyCsrf($this->extractCsrf($input))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $this->notifModel->deleteAll($userId);

        echo json_encode([
            'success'      => $ok,
            'unread_count' => 0,
            'message'      => 'Đã xóa tất cả thông báo!'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiTest(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Phương thức không được hỗ trợ. Vui lòng sử dụng POST.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $input = $this->readInput();
        if (!Auth::verifyCsrf($this->extractCsrf($input))) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Mã xác thực CSRF không hợp lệ.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $title = 'Thử nghiệm hệ thống thông báo';
        $msg = 'Hệ thống âm thanh chuông, thông báo Toast và kiểm soát deadline đang hoạt động hoàn hảo!';
        $actionUrl = 'index.php?page=settings';

        $notifId = $this->notifModel->create(
            $userId,
            $title,
            $msg,
            'system',
            $actionUrl,
            'system_test',
            null
        );

        $unreadCount = Notification::countUnread($userId);
        $recentNotifications = $this->notifModel->allForUser($userId, null, 20);

        echo json_encode([
            'success'              => true,
            'notification_id'      => $notifId,
            'notif_id'             => $notifId,
            'unread_count'         => $unreadCount,
            'title'                => $title,
            'message'              => $msg,
            'action_url'           => $actionUrl,
            'time_ago'             => 'Vừa xong',
            'recent_notifications' => $recentNotifications
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function sendTest(): void
    {
        $this->apiTest();
    }

    /**
     * Đồng bộ nhắc hẹn tự động (nhắc lịch đến giờ, lịch sắp diễn ra hôm nay, công việc quá hạn/hôm nay/khẩn cấp)
     * vào bảng notifications (mỗi mục tối đa 1 bản ghi/ngày, chống trùng lặp).
     */
    public function syncUserReminders(int $userId): array
    {
        $newNotifications = [];
        $upcomingEvents = [];
        $overdueTasks = [];
        $todayDueTasks = [];
        $urgentTasks = [];

        if ($userId <= 0) {
            return [
                'new_notifications' => [],
                'upcoming_events'   => [],
                'deadline_summary'  => [
                    'overdue_count'   => 0,
                    'due_today_count' => 0,
                    'urgent_count'    => 0,
                    'overdue_list'    => [],
                    'due_today_list'  => []
                ]
            ];
        }

        // a) Quét sự kiện đến giờ nhắc hẹn (pending reminders)
        $pendingEvents = Event::getPendingReminders($userId);
        foreach ($pendingEvents as $ev) {
            $eventId = (int)$ev['id'];
            if (!Event::markReminderSent($eventId, $userId)) {
                continue;
            }

            $title = 'Nhắc lịch: ' . $ev['title'];
            $timeStr = date('H:i, d/m/Y', strtotime($ev['start_time']));
            $loc = !empty($ev['location']) ? " tại {$ev['location']}" : '';
            $msg = "Sự kiện \"{$ev['title']}\" diễn ra vào lúc {$timeStr}{$loc}. Hãy chuẩn bị sẵn sàng!";
            $actionUrl = 'index.php?page=calendar&event_id=' . $eventId;

            $notifId = Notification::createUniqueToday([
                'user_id'        => $userId,
                'type'           => 'event',
                'title'          => $title,
                'message'        => $msg,
                'action_url'     => $actionUrl,
                'reference_type' => 'event_reminder',
                'reference_id'   => $eventId
            ]);

            if ($notifId !== null) {
                $newNotifications[] = [
                    'id'         => $notifId,
                    'type'       => 'event',
                    'title'      => $title,
                    'message'    => $msg,
                    'action_url' => $actionUrl,
                    'severity'   => 'info',
                    'time_ago'   => 'Vừa xong'
                ];
            }
        }

        // b) Quét sự kiện sắp diễn ra trong hôm nay
        $todayUpcoming = $this->eventModel->getTodayUpcomingEvents($userId, 20);
        foreach ($todayUpcoming as $ev) {
            $eventId = (int)$ev['id'];
            $startHm = date('H:i', strtotime($ev['start_time']));
            $loc = !empty($ev['location']) ? " tại {$ev['location']}" : '';
            $title = 'Lịch trình sắp diễn ra: ' . $ev['title'];
            $msg = "Hôm nay có lịch '{$ev['title']}' lúc {$startHm}{$loc}.";
            $actionUrl = 'index.php?page=calendar&event_id=' . $eventId;

            $notifId = Notification::createUniqueToday([
                'user_id'        => $userId,
                'type'           => 'event',
                'title'          => $title,
                'message'        => $msg,
                'action_url'     => $actionUrl,
                'reference_type' => 'event_today',
                'reference_id'   => $eventId
            ]);

            if ($notifId !== null) {
                $newNotifications[] = [
                    'id'         => $notifId,
                    'type'       => 'event',
                    'title'      => $title,
                    'message'    => $msg,
                    'action_url' => $actionUrl,
                    'severity'   => 'info',
                    'time_ago'   => 'Vừa xong'
                ];
            }

            if (count($upcomingEvents) < 5) {
                $upcomingEvents[] = [
                    'id'         => $eventId,
                    'title'      => $ev['title'],
                    'start_time' => $startHm,
                    'location'   => $ev['location'] ?? ''
                ];
            }
        }

        // c) Quét công việc chưa hoàn thành (quá hạn / hạn hôm nay / khẩn cấp)
        $incompleteTasks = $this->taskModel->getIncompleteForReminders($userId);
        $todayStr = date('Y-m-d');

        foreach ($incompleteTasks as $t) {
            $taskId = (int)$t['id'];
            $dueDate = !empty($t['due_date']) ? substr((string)$t['due_date'], 0, 10) : null;
            $isOverdue = ($dueDate !== null && $dueDate < $todayStr);
            $isDueToday = ($dueDate !== null && $dueDate === $todayStr);
            $isUrgent = (($t['priority'] ?? '') === 'urgent');

            if ($isOverdue) {
                $overdueTasks[] = $t;
            }
            if ($isDueToday) {
                $todayDueTasks[] = $t;
            }
            if ($isUrgent) {
                $urgentTasks[] = $t;
            }

            $refType = null;
            $title = '';
            $msg = '';
            $severity = 'info';

            if ($isOverdue) {
                $refType = 'task_overdue';
                $title = 'Cảnh báo quá hạn: ' . $t['title'];
                $dueFmt = date('d/m/Y', strtotime($dueDate));
                $msg = "Công việc \"{$t['title']}\" đã quá hạn từ ngày {$dueFmt}. Vui lòng kiểm tra và hoàn thành sớm!";
                $severity = 'warning';
            } elseif ($isDueToday) {
                $refType = 'task_due_today';
                $title = 'Hạn chót hôm nay: ' . $t['title'];
                $msg = "Công việc \"{$t['title']}\" đến hạn hoàn thành trong hôm nay. Vui lòng hoàn tất đúng hạn!";
                $severity = 'warning';
            } elseif ($isUrgent) {
                $refType = 'task_urgent';
                $title = 'Việc khẩn cấp: ' . $t['title'];
                $msg = "Công việc khẩn cấp \"{$t['title']}\" đang chờ xử lý.";
                $severity = 'info';
            }

            if ($refType !== null) {
                $actionUrl = 'index.php?page=tasks&task_id=' . $taskId;
                $notifId = Notification::createUniqueToday([
                    'user_id'        => $userId,
                    'type'           => 'task',
                    'title'          => $title,
                    'message'        => $msg,
                    'action_url'     => $actionUrl,
                    'reference_type' => $refType,
                    'reference_id'   => $taskId
                ]);

                if ($notifId !== null) {
                    $newNotifications[] = [
                        'id'         => $notifId,
                        'type'       => 'task',
                        'title'      => $title,
                        'message'    => $msg,
                        'action_url' => $actionUrl,
                        'severity'   => $severity,
                        'time_ago'   => 'Vừa xong'
                    ];
                }
            }
        }

        return [
            'new_notifications' => $newNotifications,
            'upcoming_events'   => $upcomingEvents,
            'deadline_summary'  => [
                'overdue_count'   => count($overdueTasks),
                'due_today_count' => count($todayDueTasks),
                'urgent_count'    => count($urgentTasks),
                'overdue_list'    => array_map(function ($t) {
                    return [
                        'id'       => (int)$t['id'],
                        'title'    => $t['title'],
                        'due_date' => $t['due_date'],
                        'priority' => $t['priority']
                    ];
                }, array_slice($overdueTasks, 0, 5)),
                'due_today_list'  => array_map(function ($t) {
                    return [
                        'id'       => (int)$t['id'],
                        'title'    => $t['title'],
                        'priority' => $t['priority']
                    ];
                }, array_slice($todayDueTasks, 0, 5))
            ]
        ];
    }

    /**
     * Polling kiểm tra nhắc nhở sự kiện và công việc sắp đến hạn
     */
    public function checkReminders(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $synced = $this->syncUserReminders($userId);
        $unreadCount = Notification::countUnread($userId);
        $recentNotifications = $this->notifModel->allForUser($userId, null, 20);

        echo json_encode([
            'success'              => true,
            'new_notifications'    => $synced['new_notifications'],
            'unread_count'         => $unreadCount,
            'upcoming_events'      => $synced['upcoming_events'],
            'deadline_summary'     => $synced['deadline_summary'],
            'recent_notifications' => $recentNotifications
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
