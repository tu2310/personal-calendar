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

    public function apiList(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $type = $_GET['type'] ?? null;
        $items = $this->notifModel->allForUser($userId, $type, 30);
        $unreadCount = $this->notifModel->countUnread($userId);

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
            echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
            exit;
        }

        $userId = Auth::id();
        $input = $_POST;
        if (empty($input)) {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? [];
        }

        $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.']);
            exit;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID thông báo không hợp lệ.']);
            exit;
        }

        $ok = $this->notifModel->markAsRead($id, $userId);
        $unreadCount = $this->notifModel->countUnread($userId);

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
            echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
            exit;
        }

        $userId = Auth::id();
        $input = $_POST;
        if (empty($input)) {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? [];
        }

        $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.']);
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
            echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
            exit;
        }

        $userId = Auth::id();
        $input = $_POST;
        if (empty($input)) {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? [];
        }

        $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.']);
            exit;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ.']);
            exit;
        }

        $ok = $this->notifModel->delete($id, $userId);
        $unreadCount = $this->notifModel->countUnread($userId);

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
            echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
            exit;
        }

        $userId = Auth::id();
        $input = $_POST;
        if (empty($input)) {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? [];
        }

        $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.']);
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

    public function sendTest(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $title = 'Thử nghiệm hệ thống thông báo';
        $msg = 'Hệ thống âm thanh chuông, thông báo Toast và kiểm soát deadline đang hoạt động hoàn hảo!';
        $actionUrl = 'index.php?page=settings';

        $notifId = $this->notifModel->create(
            $userId,
            $title,
            $msg,
            'system',
            $actionUrl,
            'system',
            null
        );

        $unreadCount = $this->notifModel->countUnread($userId);
        $recentNotifications = $this->notifModel->allForUser($userId, null, 20);

        echo json_encode([
            'success'              => true,
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

    /**
     * Polling kiểm tra nhắc nhở sự kiện và công việc sắp đến hạn
     * Tự động tạo bản ghi notifications và trả về danh sách thông báo mới
     */
    public function checkReminders(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $newNotifications = [];

        // 1. Kiểm tra các sự kiện đến hạn nhắc nhở
        $pendingEvents = $this->eventModel->getPendingReminders($userId);
        foreach ($pendingEvents as $ev) {
            $eventId = (int)$ev['id'];
            $title = $ev['title'];
            $timeStr = date('H:i, d/m/Y', strtotime($ev['start_time']));
            $loc = !empty($ev['location']) ? " tại {$ev['location']}" : "";
            $msg = "Sự kiện sắp diễn ra vào lúc {$timeStr}{$loc}. Hãy chuẩn bị sẵn sàng!";
            $actionUrl = "index.php?page=calendar&event_id={$eventId}";

            // Tạo notification
            $notifId = $this->notifModel->create(
                $userId,
                "Nhắc lịch: {$title}",
                $msg,
                'event',
                $actionUrl,
                'event',
                $eventId
            );

            // Đánh dấu đã nhắc để không gửi trùng
            $this->eventModel->markReminderSent($eventId, $userId);

            $newNotifications[] = [
                'id'         => $notifId,
                'type'       => 'event',
                'title'      => "Nhắc lịch: {$title}",
                'message'    => $msg,
                'action_url' => $actionUrl,
                'time_ago'   => 'Vừa xong'
            ];
        }

        // 2. Kiểm tra các công việc khẩn cấp / quá hạn hôm nay chưa được nhắc
        $pendingTasks = $this->taskModel->allForUser($userId, ['status' => 'pending']);
        $overdueTasks = [];
        $todayDueTasks = [];
        $urgentTasks = [];
        $todayStr = date('Y-m-d');

        foreach ($pendingTasks as $tk) {
            $taskId = (int)$tk['id'];
            $isOverdue = (!empty($tk['due_date']) && $tk['due_date'] < $todayStr);
            $isDueToday = (!empty($tk['due_date']) && $tk['due_date'] === $todayStr);
            $isUrgent = ($tk['priority'] === 'urgent');

            if ($isOverdue) $overdueTasks[] = $tk;
            if ($isDueToday) $todayDueTasks[] = $tk;
            if ($isUrgent) $urgentTasks[] = $tk;

            if (($isOverdue || $isDueToday || $isUrgent) && !$this->notifModel->hasNotificationFor($userId, 'task', $taskId, 'task')) {
                $statusText = $isOverdue ? 'đã quá hạn' : ($isDueToday ? 'đến hạn hôm nay' : 'ở mức độ khẩn cấp');
                $title = "Công việc cần xử lý: {$tk['title']}";
                $msg = "Công việc \"{$tk['title']}\" {$statusText}. Vui lòng kiểm tra và hoàn thành sớm!";
                $actionUrl = "index.php?page=tasks&task_id={$taskId}";

                $notifId = $this->notifModel->create(
                    $userId,
                    $title,
                    $msg,
                    'task',
                    $actionUrl,
                    'task',
                    $taskId
                );

                $newNotifications[] = [
                    'id'         => $notifId,
                    'type'       => 'task',
                    'title'      => $title,
                    'message'    => $msg,
                    'action_url' => $actionUrl,
                    'time_ago'   => 'Vừa xong'
                ];
            }
        }

        $unreadCount = $this->notifModel->countUnread($userId);
        $recentNotifications = $this->notifModel->allForUser($userId, null, 20);
        $upcomingEventsToday = $this->eventModel->getUpcomingEvents($userId, 5);

        echo json_encode([
            'success'              => true,
            'unread_count'         => $unreadCount,
            'new_notifications'    => $newNotifications,
            'recent_notifications' => $recentNotifications,
            'deadline_summary'     => [
                'overdue_count'    => count($overdueTasks),
                'due_today_count'  => count($todayDueTasks),
                'urgent_count'     => count($urgentTasks),
                'overdue_list'     => array_map(function($t) {
                    return [
                        'id'       => $t['id'],
                        'title'    => $t['title'],
                        'due_date' => $t['due_date'],
                        'priority' => $t['priority']
                    ];
                }, array_slice($overdueTasks, 0, 5)),
                'due_today_list'   => array_map(function($t) {
                    return [
                        'id'       => $t['id'],
                        'title'    => $t['title'],
                        'priority' => $t['priority']
                    ];
                }, array_slice($todayDueTasks, 0, 5))
            ],
            'upcoming_events'      => array_map(function($ev) {
                return [
                    'id'         => $ev['id'],
                    'title'      => $ev['title'],
                    'start_time' => date('H:i', strtotime($ev['start_time']))
                ];
            }, $upcomingEventsToday)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
