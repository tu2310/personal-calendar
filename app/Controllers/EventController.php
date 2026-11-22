<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Task.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Notification.php';

class EventController
{
    private Event $eventModel;
    private Category $categoryModel;
    private Task $taskModel;
    private User $userModel;
    private Notification $notifModel;

    public function __construct()
    {
        $this->eventModel = new Event();
        $this->categoryModel = new Category();
        $this->taskModel = new Task();
        $this->userModel = new User();
        $this->notifModel = new Notification();
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

    public function index(): array
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $categories = $this->categoryModel->getAll($userId);
        return [
            'categories' => $categories,
            'csrf_token' => Auth::csrfToken()
        ];
    }

    public function tasksData(): array
    {
        Auth::requireLogin();
        $userId = Auth::id();

        $filters = [
            'search'      => is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '',
            'status'      => is_string($_GET['status'] ?? null) ? trim($_GET['status']) : 'all',
            'priority'    => is_string($_GET['priority'] ?? null) ? trim($_GET['priority']) : 'all',
            'category_id' => is_scalar($_GET['category_id'] ?? null) ? (string)$_GET['category_id'] : '',
            'sort'        => is_string($_GET['sort'] ?? null) ? trim($_GET['sort']) : 'created_desc'
        ];

        $tasks = $this->taskModel->allForUser($userId, $filters);
        $categories = $this->categoryModel->getAll($userId);
        $stats = $this->taskModel->getStats($userId);

        return [
            'tasks'      => $tasks,
            'categories' => $categories,
            'stats'      => $stats,
            'filters'    => $filters,
            'csrf_token' => Auth::csrfToken()
        ];
    }

    public function categoriesData(): array
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $categories = $this->categoryModel->getAll($userId);
        return [
            'categories' => $categories,
            'csrf_token' => Auth::csrfToken()
        ];
    }

    public function statisticsData(): array
    {
        Auth::requireLogin();
        require_once __DIR__ . '/../Models/Habit.php';
        $userId = Auth::id();
        $stats = $this->eventModel->getStats($userId);
        $weekly = $this->eventModel->getWeeklyStats($userId);
        $allEvents = $this->eventModel->allForUser($userId);
        $taskStats = $this->taskModel->getStats($userId);

        $habitModel = new Habit();
        $habits = $habitModel->allForUser($userId, 7);
        $habitSummary = $habitModel->getSummary($habits);
        $habitWeekly = $habitModel->getWeeklySeries($userId, 7);

        return [
            'stats'        => $stats,
            'weekly'       => $weekly,
            'taskStats'    => $taskStats,
            'total'        => count($allEvents),
            'habits'       => $habits,
            'habitSummary' => $habitSummary,
            'habitWeekly'  => $habitWeekly,
            'csrf_token'   => Auth::csrfToken()
        ];
    }

    public function settingsData(): array
    {
        Auth::requireLogin();
        $user = Auth::user();
        return [
            'user'       => $user,
            'csrf_token' => Auth::csrfToken()
        ];
    }

    public function apiEvents(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $rawStart = is_string($_GET['start'] ?? null) ? $_GET['start'] : date('Y-m-01 00:00:00');
        $rawEnd = is_string($_GET['end'] ?? null) ? $_GET['end'] : date('Y-m-t 23:59:59');

        $startTs = strtotime($rawStart) ?: strtotime(date('Y-m-01 00:00:00'));
        $endTs = strtotime($rawEnd) ?: strtotime(date('Y-m-t 23:59:59'));
        $start = date('Y-m-d H:i:s', $startTs);
        $end = date('Y-m-d H:i:s', $endTs);
        $categoryId = is_scalar($_GET['category_id'] ?? null) && (int)$_GET['category_id'] > 0 ? (int)$_GET['category_id'] : null;

        $events = $this->eventModel->getInRange($userId, $start, $end, $categoryId);
        $payload = [];

        foreach ($events as $item) {
            $color = $item['category_color'] ?: '#6658e8';
            $isMultiday = substr($item['start_time'], 0, 10) !== substr($item['end_time'], 0, 10);
            $isFullDay = substr($item['start_time'], 11, 5) === '00:00' && substr($item['end_time'], 11, 5) === '23:59';
            $isAllDay = $isMultiday || $isFullDay;

            $entry = [
                'id'              => (string)$item['id'],
                'title'           => $item['title'],
                'start'           => $isAllDay ? substr($item['start_time'], 0, 10) : date('c', strtotime($item['start_time'])),
                'end'             => $isAllDay
                    ? date('Y-m-d', strtotime(substr($item['end_time'], 0, 10) . ' +1 day'))
                    : date('c', strtotime($item['end_time'])),
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'textColor'       => '#ffffff',
                'extendedProps'   => [
                    'description'      => $item['description'] ?? '',
                    'location'         => $item['location'] ?? '',
                    'priority'         => $item['priority'],
                    'status'           => $item['status'],
                    'category_id'      => $item['category_id'],
                    'category_name'    => $item['category_name'] ?? 'Chưa phân loại',
                    'category_color'   => $color,
                    'category_icon'    => $item['category_icon'] ?? 'fa-folder',
                    'reminder_at'      => $item['reminder_at'] ? date('Y-m-d\TH:i', strtotime($item['reminder_at'])) : '',
                    'reminder_minutes' => $item['reminder_minutes'] !== null ? (int)$item['reminder_minutes'] : null,
                    'repeat_type'      => $item['repeat_type'] ?? 'none',
                    'is_multiday'      => $isMultiday,
                    'raw_start'        => date('Y-m-d\TH:i', strtotime($item['start_time'])),
                    'raw_end'          => date('Y-m-d\TH:i', strtotime($item['end_time'])),
                    'start_formatted'  => date('d/m/Y H:i', strtotime($item['start_time'])),
                    'end_formatted'    => date('d/m/Y H:i', strtotime($item['end_time'])),
                ]
            ];
            if ($isAllDay) {
                $entry['allDay'] = true;
            }
            $payload[] = $entry;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiDetail(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $id = is_scalar($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID không hợp lệ.']);
            exit;
        }

        $event = $this->eventModel->findById($id, $userId);
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Lịch trình không tồn tại hoặc bạn không có quyền truy cập.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $event], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * API Live Search lịch trình cá nhân theo từ khóa
     */
    public function apiSearch(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';

        if ($q === '') {
            echo json_encode(['success' => true, 'query' => '', 'total' => 0, 'data' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once __DIR__ . '/../Models/Task.php';
        $taskModel = new Task();

        $eventResults = $this->eventModel->search($userId, $q, 15);
        $taskResults  = $taskModel->search($userId, $q, 15);

        $statusLabels = [
            'completed'   => 'Đã hoàn thành',
            'in_progress' => 'Đang diễn ra',
            'pending'     => 'Chưa hoàn thành',
            'cancelled'   => 'Đã hủy'
        ];
        $statusColors = [
            'completed'   => 'success',
            'in_progress' => 'info',
            'pending'     => 'warning',
            'cancelled'   => 'secondary'
        ];

        $eventFormatted = array_map(function ($ev) use ($statusLabels, $statusColors) {
            $start = strtotime($ev['start_time']);
            $end = strtotime($ev['end_time']);

            $dateFormatted = date('d/m/Y', $start);
            $timeFormatted = date('H:i', $start) . ' - ' . date('H:i', $end);

            return [
                'id'             => (int)$ev['id'],
                'type'           => 'event',
                'title'          => $ev['title'],
                'description'    => $ev['description'] ?? '',
                'location'       => $ev['location'] ?? '',
                'start_time'     => $ev['start_time'],
                'end_time'       => $ev['end_time'],
                'date_formatted' => $dateFormatted,
                'time_formatted' => $timeFormatted,
                'status'         => $ev['status'] ?? 'pending',
                'status_label'   => $statusLabels[$ev['status'] ?? ''] ?? 'Chưa hoàn thành',
                'status_color'   => $statusColors[$ev['status'] ?? ''] ?? 'warning',
                'priority'       => $ev['priority'] ?? 'medium',
                'category_id'    => $ev['category_id'],
                'category_name'  => $ev['category_name'] ?: 'Chưa phân loại',
                'category_color' => $ev['category_color'] ?: '#6658e8',
                'category_icon'  => $ev['category_icon'] ?: 'fa-folder',
                '_score'         => $ev['_score'] ?? 1
            ];
        }, $eventResults);

        $taskFormatted = array_map(function ($t) {
            $isOverdue = (!empty($t['due_date']) && $t['status'] === 'pending' && $t['due_date'] < date('Y-m-d'));
            $dateFormatted = !empty($t['due_date']) ? date('d/m/Y', strtotime($t['due_date'])) : 'Không có hạn';
            $timeFormatted = $isOverdue ? 'Quá hạn' : ($t['status'] === 'completed' ? 'Đã xong' : 'Công việc');

            return [
                'id'             => (int)$t['id'],
                'type'           => 'task',
                'title'          => $t['title'],
                'description'    => $t['description'] ?? '',
                'location'       => '',
                'start_time'     => $t['due_date'] ? $t['due_date'] . ' 00:00:00' : null,
                'end_time'       => null,
                'date_formatted' => $dateFormatted,
                'time_formatted' => $timeFormatted,
                'status'         => $t['status'] ?? 'pending',
                'status_label'   => $t['status'] === 'completed' ? 'Đã hoàn thành' : ($isOverdue ? 'Quá hạn' : 'Chưa hoàn thành'),
                'status_color'   => $t['status'] === 'completed' ? 'success' : ($isOverdue ? 'danger' : 'warning'),
                'priority'       => $t['priority'] ?? 'medium',
                'category_id'    => $t['category_id'] ?? null,
                'category_name'  => $t['category_name'] ?: 'Công việc',
                'category_color' => $t['category_color'] ?: '#6658e8',
                'category_icon'  => 'fa-list-check',
                '_score'         => $t['_score'] ?? 1
            ];
        }, $taskResults);

        // Gộp lịch trình và công việc
        $all = array_merge($eventFormatted, $taskFormatted);

        // Sắp xếp: Kết quả có điểm khớp cao nhất (khớp tiêu đề) HIỆN LÊN ĐẦU TIÊN
        usort($all, function ($a, $b) {
            if ($b['_score'] !== $a['_score']) {
                return $b['_score'] - $a['_score'];
            }
            return (int)$b['id'] - (int)$a['id'];
        });

        $formatted = array_slice($all, 0, 15);

        echo json_encode([
            'success' => true,
            'query'   => $q,
            'total'   => count($formatted),
            'data'    => $formatted
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function save(): void
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
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ hoặc đã hết hạn.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $id = is_scalar($input['id'] ?? null) && (int)$input['id'] > 0 ? (int)$input['id'] : null;
        $partialFlag = is_string($input['_partial'] ?? null) ? trim($input['_partial']) : '';
        $rawTitleCheck = is_string($input['title'] ?? null) ? trim($input['title']) : '';

        // Xử lý cập nhật nhanh trạng thái công việc (Partial Status Update)
        if ($partialFlag === 'status' || ($id !== null && $rawTitleCheck === '' && isset($input['status']))) {
            $existing = $id ? $this->eventModel->findById($id, $userId) : null;
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy lịch trình hoặc không có quyền thao tác.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $rawStatus = is_string($input['status'] ?? null) ? trim($input['status']) : '';
            $status = $rawStatus === 'completed' ? 'completed' : 'pending';
            $ok = $this->eventModel->updateStatus($id, $userId, $status);
            if ($ok) {
                if ($status !== 'completed') {
                    Event::resetReminder($userId, $id);
                }
                echo json_encode([
                    'success'      => true,
                    'unread_count' => Notification::countUnread($userId),
                    'message'      => $status === 'completed' ? 'Đã đánh dấu hoàn thành!' : 'Đã khôi phục trạng thái!'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể cập nhật trạng thái.'], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        $title = is_string($input['title'] ?? null) ? trim($input['title']) : '';
        $description = is_string($input['description'] ?? null) ? trim($input['description']) : '';
        $location = is_string($input['location'] ?? null) ? trim($input['location']) : '';
        $startTime = is_string($input['start_time'] ?? null) ? trim($input['start_time']) : '';
        $endTime = is_string($input['end_time'] ?? null) ? trim($input['end_time']) : '';
        $priority = is_string($input['priority'] ?? null) ? trim($input['priority']) : 'medium';
        $status = is_string($input['status'] ?? null) ? trim($input['status']) : 'pending';

        // Kiểm tra chống IDOR cho category_id
        $rawCat = $input['category_id'] ?? null;
        $categoryId = null;
        if ($rawCat !== null && $rawCat !== '' && $rawCat !== '0' && $rawCat !== 0) {
            if (!is_scalar($rawCat) || !preg_match('/^\d+$/', trim((string)$rawCat)) || (int)$rawCat <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Danh mục không hợp lệ.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $categoryId = (int)$rawCat;
            $catRow = $this->categoryModel->findById($categoryId);
            if (!$catRow || (int)($catRow['user_id'] ?? 0) !== $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Danh mục không hợp lệ.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // Xử lý thời gian nhắc nhở thông minh
        $rawHasRem = $input['has_reminder'] ?? null;
        $hasReminder = is_scalar($rawHasRem) && !empty($rawHasRem) && $rawHasRem !== '0' && $rawHasRem !== 0;
        $reminderMinutes = null;
        $reminderAt = null;

        if ($hasReminder && isset($input['reminder_minutes']) && $input['reminder_minutes'] !== '' && $input['reminder_minutes'] !== null) {
            $rawRemMin = $input['reminder_minutes'];
            if (!is_int($rawRemMin) && !(is_string($rawRemMin) && preg_match('/^-?\d+$/', trim($rawRemMin)))) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Thời gian nhắc nhở không hợp lệ.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $reminderMinutes = (int)trim((string)$rawRemMin);
            if ($reminderMinutes < 0 || $reminderMinutes > 43200) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Thời gian nhắc nhở phải từ 0 đến 43200 phút.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } elseif ($hasReminder && is_string($input['reminder_at'] ?? null) && trim($input['reminder_at']) !== '') {
            $remTs = strtotime(trim($input['reminder_at']));
            if ($remTs === false) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Định dạng thời gian nhắc nhở không hợp lệ.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $reminderAt = date('Y-m-d H:i:s', $remTs);
        }

        if ($title === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ tiêu đề lịch trình!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (mb_strlen($title, 'UTF-8') > 180) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tiêu đề không được vượt quá 180 ký tự.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (mb_strlen($location, 'UTF-8') > 255) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Địa điểm không được vượt quá 255 ký tự.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (empty($startTime) || empty($endTime)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng chọn thời gian bắt đầu và kết thúc.']);
            exit;
        }

        $startTimestamp = strtotime($startTime);
        $endTimestamp = strtotime($endTime);

        if (!$startTimestamp || !$endTimestamp) {
            echo json_encode(['success' => false, 'message' => 'Định dạng ngày giờ không hợp lệ.']);
            exit;
        }

        if ($endTimestamp <= $startTimestamp) {
            echo json_encode(['success' => false, 'message' => 'Thời gian kết thúc phải diễn ra sau thời gian bắt đầu!']);
            exit;
        }

        $formattedStart = date('Y-m-d H:i:s', $startTimestamp);
        $formattedEnd = date('Y-m-d H:i:s', $endTimestamp);

        $rawRepeat = is_string($input['repeat_type'] ?? null) ? trim($input['repeat_type']) : 'none';
        if ($rawRepeat === '') {
            $rawRepeat = 'none';
        }
        if (!in_array($rawRepeat, ['none', 'daily', 'weekly', 'monthly'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Chu kỳ lặp lại không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Tự động tính reminder_at từ reminder_minutes nếu có
        if ($reminderMinutes !== null) {
            $reminderAt = date('Y-m-d H:i:s', $startTimestamp - ($reminderMinutes * 60));
        }

        $data = [
            'category_id'      => $categoryId,
            'title'            => $title,
            'description'      => $description,
            'start_time'       => $formattedStart,
            'end_time'         => $formattedEnd,
            'location'         => $location,
            'priority'         => in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium',
            'status'           => in_array($status, ['pending', 'completed'], true) ? $status : 'pending',
            'reminder_at'      => $reminderAt,
            'reminder_minutes' => $reminderMinutes,
            'repeat_type'      => $rawRepeat
        ];

        if ($id) {
            $existing = $this->eventModel->findById($id, $userId);
            if (!$existing) {
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa lịch trình này.']);
                exit;
            }

            $ok = $this->eventModel->update($id, $userId, $data);
            if ($ok) {
                Event::resetReminder($userId, $id);
                echo json_encode([
                    'success'      => true,
                    'id'           => $id,
                    'unread_count' => Notification::countUnread($userId),
                    'message'      => 'Cập nhật lịch trình thành công!'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu dữ liệu.']);
            }
        } else {
            $newId = $this->eventModel->create($userId, $data);
            if ($newId) {
                echo json_encode([
                    'success'      => true,
                    'message'      => 'Thêm lịch mới thành công!',
                    'id'           => $newId,
                    'unread_count' => Notification::countUnread($userId)
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu lịch trình.']);
            }
        }
        exit;
    }

    public function updateTime(): void
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
        $startTime = is_string($input['start_time'] ?? null) ? trim($input['start_time']) : '';
        $endTime = is_string($input['end_time'] ?? null) ? trim($input['end_time']) : '';

        if ($id <= 0 || $startTime === '' || $endTime === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không đầy đủ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $startTs = strtotime($startTime);
        $endTs = strtotime($endTime);

        if (!$startTs || !$endTs || $endTs <= $startTs) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Thời gian không hợp lệ. Giờ kết thúc phải sau giờ bắt đầu.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $existing = $this->eventModel->findById($id, $userId);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy lịch trình hoặc không có quyền thao tác.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $this->eventModel->updateTime($id, $userId, date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs));
        if ($ok) {
            Event::resetReminder($userId, $id);
            echo json_encode([
                'success'      => true,
                'unread_count' => Notification::countUnread($userId),
                'message'      => 'Cập nhật thời gian thành công!'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể lưu dữ liệu thời gian.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function delete(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
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

        $existing = $this->eventModel->findById($id, $userId);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy lịch trình hoặc bạn không có quyền xóa.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $this->eventModel->delete($id, $userId);
        if ($ok) {
            echo json_encode([
                'success'      => true,
                'unread_count' => Notification::countUnread($userId),
                'message'      => 'Xóa lịch thành công!'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa lịch trình.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function apiCategorySave(): void
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

        $id = is_scalar($input['id'] ?? null) && (int)$input['id'] > 0 ? (int)$input['id'] : null;
        $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        $hasColorInput = array_key_exists('color', $input) && $input['color'] !== null && $input['color'] !== '';
        $color = $hasColorInput
            ? (is_string($input['color']) ? trim($input['color']) : '')
            : '#6658e8';
        $icon = is_string($input['icon'] ?? null) && trim($input['icon']) !== '' ? trim($input['icon']) : 'fa-folder';

        if ($name === '' || mb_strlen($name, 'UTF-8') > 80) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tên danh mục phải từ 1 đến 80 ký tự!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Mã màu danh mục không hợp lệ (#RRGGBB).'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            if ($id) {
                $existingCat = $this->categoryModel->findById($id);
                if (!$existingCat || (int)($existingCat['user_id'] ?? 0) !== $userId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật danh mục hệ thống hoặc danh mục không thuộc quyền sở hữu.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $ok = $this->categoryModel->update($id, $userId, $name, $color, $icon);
                if ($ok) {
                    echo json_encode(['success' => true, 'message' => 'Sửa danh mục thành công!'], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật danh mục.'], JSON_UNESCAPED_UNICODE);
                }
            } else {
                $newId = $this->categoryModel->create($userId, $name, $color, $icon);
                if ($newId) {
                    echo json_encode(['success' => true, 'message' => 'Thêm danh mục thành công!', 'id' => $newId], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Không thể tạo mới danh mục.'], JSON_UNESCAPED_UNICODE);
                }
            }
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Tên danh mục này đã tồn tại!'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            throw $e;
        }
        exit;
    }

    public function apiCategoryDelete(): void
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
            echo json_encode(['success' => false, 'message' => 'ID danh mục không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $existingCat = $this->categoryModel->findById($id);
        if (!$existingCat || (int)($existingCat['user_id'] ?? 0) !== $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Không thể xóa danh mục mặc định của hệ thống hoặc bạn không có quyền xóa.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $this->categoryModel->delete($id, $userId);
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Xóa danh mục thành công!'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa danh mục.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function apiSettingsSave(): void
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

        $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Họ tên phải từ 1 đến 100 ký tự!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE users SET name = :name WHERE id = :id");
        $stmt->execute(['name' => $name, 'id' => $userId]);

        // Cập nhật session
        $_SESSION['user']['name'] = $name;

        echo json_encode([
            'success'      => true,
            'unread_count' => Notification::countUnread($userId),
            'message'      => 'Cập nhật thông tin cá nhân thành công!'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
