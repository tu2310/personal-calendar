<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Task.php';
require_once __DIR__ . '/../Models/User.php';

class EventController
{
    private Event $eventModel;
    private Category $categoryModel;
    private Task $taskModel;
    private User $userModel;

    public function __construct()
    {
        $this->eventModel = new Event();
        $this->categoryModel = new Category();
        $this->taskModel = new Task();
        $this->userModel = new User();
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
            'search'      => $_GET['search'] ?? '',
            'status'      => $_GET['status'] ?? 'all',
            'priority'    => $_GET['priority'] ?? 'all',
            'category_id' => $_GET['category_id'] ?? '',
            'sort'        => $_GET['sort'] ?? 'created_desc'
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
        $start = $_GET['start'] ?? date('Y-m-01 00:00:00');
        $end = $_GET['end'] ?? date('Y-m-t 23:59:59');

        $start = date('Y-m-d H:i:s', strtotime($start));
        $end = date('Y-m-d H:i:s', strtotime($end));
        $categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;

        $events = $this->eventModel->getInRange($userId, $start, $end, $categoryId);
        $payload = [];

        foreach ($events as $item) {
            $color = $item['category_color'] ?: '#6658e8';
            $payload[] = [
                'id'              => (string)$item['id'],
                'title'           => $item['title'],
                'start'           => date('c', strtotime($item['start_time'])),
                'end'             => date('c', strtotime($item['end_time'])),
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
                    'raw_start'        => date('Y-m-d\TH:i', strtotime($item['start_time'])),
                    'raw_end'          => date('Y-m-d\TH:i', strtotime($item['end_time'])),
                    'start_formatted'  => date('d/m/Y H:i', strtotime($item['start_time'])),
                    'end_formatted'    => date('d/m/Y H:i', strtotime($item['end_time'])),
                ]
            ];
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiDetail(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $id = (int)($_GET['id'] ?? 0);

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
        $q = trim($_GET['q'] ?? '');

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
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ hoặc đã hết hạn.']);
            exit;
        }

        $id = !empty($input['id']) ? (int)$input['id'] : null;

        // Xử lý cập nhật nhanh trạng thái công việc (Partial Status Update)
        if (($input['_partial'] ?? '') === 'status' || (!empty($id) && empty($input['title']) && isset($input['status']))) {
            $status = ($input['status'] ?? '') === 'completed' ? 'completed' : 'pending';
            $ok = $this->eventModel->updateStatus($id, $userId, $status);
            if ($ok) {
                echo json_encode([
                    'success' => true,
                    'message' => $status === 'completed' ? 'Đã đánh dấu hoàn thành!' : 'Đã khôi phục trạng thái!'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể cập nhật trạng thái.'], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $location = trim($input['location'] ?? '');
        $startTime = $input['start_time'] ?? '';
        $endTime = $input['end_time'] ?? '';
        $priority = $input['priority'] ?? 'medium';
        $status = $input['status'] ?? 'pending';

        // Xử lý thời gian nhắc nhở thông minh
        $hasReminder = !empty($input['has_reminder']) && $input['has_reminder'] !== '0' && $input['has_reminder'] !== 0;
        $reminderMinutes = null;
        $reminderAt = null;

        if ($hasReminder && isset($input['reminder_minutes']) && $input['reminder_minutes'] !== '' && $input['reminder_minutes'] !== null) {
            $reminderMinutes = (int)$input['reminder_minutes'];
        } elseif ($hasReminder && !empty($input['reminder_at'])) {
            $reminderAt = date('Y-m-d H:i:s', strtotime($input['reminder_at']));
        }

        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ tiêu đề lịch trình!']);
            exit;
        }

        if (mb_strlen($title) > 180) {
            echo json_encode(['success' => false, 'message' => 'Tiêu đề không được vượt quá 180 ký tự.']);
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
            'reminder_minutes' => $reminderMinutes
        ];

        if ($id) {
            $existing = $this->eventModel->findById($id, $userId);
            if (!$existing) {
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa lịch trình này.']);
                exit;
            }

            $ok = $this->eventModel->update($id, $userId, $data);
            if ($ok) {
                echo json_encode(['success' => true, 'message' => 'Cập nhật lịch trình thành công!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu dữ liệu.']);
            }
        } else {
            $newId = $this->eventModel->create($userId, $data);
            if ($newId) {
                echo json_encode(['success' => true, 'message' => 'Thêm lịch mới thành công!', 'id' => $newId]);
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
        $startTime = $input['start_time'] ?? '';
        $endTime = $input['end_time'] ?? '';

        if ($id <= 0 || empty($startTime) || empty($endTime)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không đầy đủ.']);
            exit;
        }

        $startTs = strtotime($startTime);
        $endTs = strtotime($endTime);

        if (!$startTs || !$endTs || $endTs <= $startTs) {
            echo json_encode(['success' => false, 'message' => 'Thời gian không hợp lệ. Giờ kết thúc phải sau giờ bắt đầu.']);
            exit;
        }

        $existing = $this->eventModel->findById($id, $userId);
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy lịch trình hoặc không có quyền thao tác.']);
            exit;
        }

        $ok = $this->eventModel->updateTime($id, $userId, date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs));
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật thời gian thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể lưu dữ liệu thời gian.']);
        }
        exit;
    }

    public function delete(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
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

        $existing = $this->eventModel->findById($id, $userId);
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy lịch trình hoặc bạn không có quyền xóa.']);
            exit;
        }

        $ok = $this->eventModel->delete($id, $userId);
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Xóa lịch thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa lịch trình.']);
        }
        exit;
    }

    public function apiCategorySave(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $input = $_POST;
        if (empty($input)) {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? [];
        }

        $csrf = $input['csrf_token'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.']);
            exit;
        }

        $id = !empty($input['id']) ? (int)$input['id'] : null;
        $name = trim($input['name'] ?? '');
        $color = trim($input['color'] ?? '#6658e8');
        $icon = trim($input['icon'] ?? 'fa-folder');

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên danh mục!']);
            exit;
        }

        if ($id) {
            $ok = $this->categoryModel->update($id, $userId, $name, $color, $icon);
            if ($ok) {
                echo json_encode(['success' => true, 'message' => 'Sửa danh mục thành công!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể cập nhật danh mục hệ thống hoặc danh mục không thuộc quyền sở hữu.']);
            }
        } else {
            $newId = $this->categoryModel->create($userId, $name, $color, $icon);
            if ($newId) {
                echo json_encode(['success' => true, 'message' => 'Thêm danh mục thành công!', 'id' => $newId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể tạo mới danh mục.']);
            }
        }
        exit;
    }

    public function apiCategoryDelete(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $input = $_POST;
        if (empty($input)) {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? [];
        }

        $csrf = $input['csrf_token'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.']);
            exit;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID danh mục không hợp lệ.']);
            exit;
        }

        $ok = $this->categoryModel->delete($id, $userId);
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Xóa danh mục thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa danh mục mặc định của hệ thống hoặc bạn không có quyền xóa.']);
        }
        exit;
    }

    public function apiSettingsSave(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $input = $_POST;
        if (empty($input)) {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? [];
        }

        $csrf = $input['csrf_token'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.']);
            exit;
        }

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Họ tên không được để trống!']);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE users SET name = :name WHERE id = :id");
        $stmt->execute(['name' => $name, 'id' => $userId]);

        // Cập nhật session
        $_SESSION['user']['name'] = $name;

        echo json_encode(['success' => true, 'message' => 'Cập nhật thông tin cá nhân thành công!']);
        exit;
    }
}
