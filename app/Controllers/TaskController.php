<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/Task.php';
require_once __DIR__ . '/../Models/Category.php';
require_once __DIR__ . '/../Models/Notification.php';

class TaskController
{
    private Task $taskModel;
    private Category $categoryModel;
    private Notification $notifModel;

    public function __construct()
    {
        $this->taskModel = new Task();
        $this->categoryModel = new Category();
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

    private function resolveFromToday(array $input = []): bool
    {
        $raw = $input['scope'] ?? ($_GET['scope'] ?? 'from_today');
        $scope = is_string($raw) && trim($raw) !== '' ? trim($raw) : 'from_today';
        return $scope !== 'all';
    }

    public function index(): array
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $scope = is_string($_GET['scope'] ?? null) && trim($_GET['scope']) !== '' ? trim($_GET['scope']) : 'from_today';
        $fromToday = ($scope !== 'all');
        $sort = is_string($_GET['sort'] ?? null) && trim($_GET['sort']) !== '' ? trim($_GET['sort']) : 'due_asc';

        $filters = [
            'search'      => is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '',
            'status'      => is_string($_GET['status'] ?? null) ? trim($_GET['status']) : 'all',
            'priority'    => is_string($_GET['priority'] ?? null) ? trim($_GET['priority']) : 'all',
            'category_id' => is_scalar($_GET['category_id'] ?? null) ? (string)$_GET['category_id'] : '',
            'sort'        => $sort,
            'scope'       => $scope,
            'from_today'  => $fromToday
        ];

        $tasks = $this->taskModel->allForUser($userId, $filters);
        $categories = $this->categoryModel->getAll($userId);
        $stats = $this->taskModel->getStats($userId, $fromToday);

        return [
            'tasks'      => $tasks,
            'categories' => $categories,
            'stats'      => $stats,
            'filters'    => $filters,
            'csrf_token' => Auth::csrfToken()
        ];
    }

    public function apiList(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $scope = is_string($_GET['scope'] ?? null) && trim($_GET['scope']) !== '' ? trim($_GET['scope']) : 'from_today';
        $fromToday = ($scope !== 'all');
        $sort = is_string($_GET['sort'] ?? null) && trim($_GET['sort']) !== '' ? trim($_GET['sort']) : 'due_asc';
        $filters = [
            'search'      => is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '',
            'status'      => is_string($_GET['status'] ?? null) ? trim($_GET['status']) : 'all',
            'priority'    => is_string($_GET['priority'] ?? null) ? trim($_GET['priority']) : 'all',
            'category_id' => is_scalar($_GET['category_id'] ?? null) && (int)$_GET['category_id'] > 0 ? (int)$_GET['category_id'] : '',
            'sort'        => $sort,
            'scope'       => $scope,
            'from_today'  => $fromToday
        ];

        $tasks = $this->taskModel->allForUser($userId, $filters);
        $stats = $this->taskModel->getStats($userId, $fromToday);

        echo json_encode([
            'success' => true,
            'tasks'   => $tasks,
            'stats'   => $stats
        ], JSON_UNESCAPED_UNICODE);
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

        $task = $this->taskModel->findById($id, $userId);
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Công việc không tồn tại hoặc bạn không có quyền truy cập.']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $task], JSON_UNESCAPED_UNICODE);
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
        $title = is_string($input['title'] ?? null) ? trim($input['title']) : '';
        $description = is_string($input['description'] ?? null) ? trim($input['description']) : '';
        $rawDueDate = is_string($input['due_date'] ?? null) ? trim($input['due_date']) : '';
        $dueDate = $rawDueDate !== '' ? $rawDueDate : null;
        $rawPriority = is_string($input['priority'] ?? null) ? trim($input['priority']) : '';
        $priority = in_array($rawPriority, ['low', 'medium', 'high', 'urgent'], true) ? $rawPriority : 'medium';
        $rawStatus = is_string($input['status'] ?? null) ? trim($input['status']) : '';
        $status = in_array($rawStatus, ['pending', 'completed'], true) ? $rawStatus : 'pending';

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

        if ($title === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên công việc!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (mb_strlen($title, 'UTF-8') > 255) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Tên công việc không được vượt quá 255 ký tự.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($dueDate !== null) {
            $dt = DateTime::createFromFormat('Y-m-d', $dueDate);
            if (!$dt || $dt->format('Y-m-d') !== $dueDate) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Định dạng hạn hoàn thành không hợp lệ (YYYY-MM-DD).'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $rawStartTime = is_string($input['start_time'] ?? null) ? trim($input['start_time']) : '';
        $startTime = null;
        if ($rawStartTime !== '') {
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $rawStartTime, $mStart)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Định dạng giờ bắt đầu không hợp lệ (HH:MM).'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $startTime = $mStart[1] . ':' . $mStart[2] . ':00';
        }

        $rawEndTime = is_string($input['end_time'] ?? null) ? trim($input['end_time']) : '';
        $endTime = null;
        if ($rawEndTime !== '') {
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $rawEndTime, $mEnd)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Định dạng giờ kết thúc không hợp lệ (HH:MM).'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $endTime = $mEnd[1] . ':' . $mEnd[2] . ':00';
        }

        $rawEndDate = is_string($input['end_date'] ?? null) ? trim($input['end_date']) : '';
        $endDate = $rawEndDate !== '' ? $rawEndDate : null;
        if ($endDate !== null) {
            $dtEnd = DateTime::createFromFormat('Y-m-d', $endDate);
            if (!$dtEnd || $dtEnd->format('Y-m-d') !== $endDate) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Định dạng ngày kết thúc không hợp lệ (YYYY-MM-DD).'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($dueDate !== null && $endDate < $dueDate) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày thực hiện.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $isSameDay = ($endDate === null || $dueDate === null || $endDate === $dueDate);
        if ($isSameDay && $startTime !== null && $endTime !== null && $endTime < $startTime) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Giờ kết thúc phải lớn hơn hoặc bằng giờ bắt đầu!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rawRepeat = is_string($input['repeat_type'] ?? null) ? trim($input['repeat_type']) : 'none';
        if ($rawRepeat === '') {
            $rawRepeat = 'none';
        }
        if (!in_array($rawRepeat, ['none', 'daily', 'weekly', 'monthly'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Chu kỳ lặp lại không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = [
            'category_id' => $categoryId,
            'title'       => $title,
            'description' => $description,
            'due_date'    => $dueDate,
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'end_date'    => $endDate,
            'repeat_type' => $rawRepeat,
            'priority'    => $priority,
            'status'      => $status
        ];

        if ($id) {
            $existing = $this->taskModel->findById($id, $userId);
            if (!$existing) {
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa công việc này.']);
                exit;
            }

            $ok = $this->taskModel->update($id, $userId, $data);
            if ($ok) {
                echo json_encode([
                    'success'      => true,
                    'id'           => $id,
                    'message'      => 'Cập nhật công việc thành công!',
                    'stats'        => $this->taskModel->getStats($userId, $this->resolveFromToday($input)),
                    'unread_count' => Notification::countUnread($userId)
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể lưu công việc.']);
            }
        } else {
            $newId = $this->taskModel->create($userId, $data);
            if ($newId) {
                echo json_encode([
                    'success'      => true,
                    'message'      => 'Thêm công việc mới thành công!',
                    'id'           => $newId,
                    'stats'        => $this->taskModel->getStats($userId, $this->resolveFromToday($input)),
                    'unread_count' => Notification::countUnread($userId)
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể tạo công việc.']);
            }
        }
        exit;
    }

    public function updateStatus(): void
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
        $rawStatus = is_string($input['status'] ?? null) ? trim($input['status']) : '';
        $status = $rawStatus === 'completed' ? 'completed' : 'pending';

        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ID công việc không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $existing = $this->taskModel->findById($id, $userId);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy công việc hoặc không có quyền thao tác.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $this->taskModel->updateStatus($id, $userId, $status);
        if ($ok) {
            echo json_encode([
                'success'      => true,
                'status'       => $status,
                'message'      => $status === 'completed' ? 'Đã hoàn thành công việc!' : 'Đã chuyển về chưa hoàn thành!',
                'stats'        => $this->taskModel->getStats($userId, $this->resolveFromToday($input)),
                'unread_count' => Notification::countUnread($userId)
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể cập nhật trạng thái công việc.']);
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
            echo json_encode(['success' => false, 'message' => 'ID công việc không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $existing = $this->taskModel->findById($id, $userId);
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy công việc hoặc bạn không có quyền xóa.']);
            exit;
        }

        $ok = $this->taskModel->delete($id, $userId);
        if ($ok) {
            echo json_encode([
                'success'      => true,
                'message'      => 'Đã chuyển công việc vào thùng rác.',
                'stats'        => $this->taskModel->getStats($userId, $this->resolveFromToday($input)),
                'unread_count' => Notification::countUnread($userId)
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa công việc.']);
        }
        exit;
    }

    public function upcomingData(): array
    {
        Auth::requireLogin();
        require_once __DIR__ . '/../Models/Event.php';

        $userId = Auth::id();
        $eventModel = new Event();
        $upcomingTasks = $this->taskModel->getUpcomingTasks($userId, 30);
        $upcomingEvents = $eventModel->getUpcomingByDays($userId, 30);

        $dowMap = [
            0 => 'Chủ Nhật',
            1 => 'Thứ Hai',
            2 => 'Thứ Ba',
            3 => 'Thứ Tư',
            4 => 'Thứ Năm',
            5 => 'Thứ Sáu',
            6 => 'Thứ Bảy',
        ];
        $tomorrowStr = date('Y-m-d', strtotime('+1 day'));
        $buckets = [];

        foreach ($upcomingEvents as $ev) {
            $id = (int)$ev['id'];
            $dateKey = substr((string)$ev['start_time'], 0, 10);
            $endDatePart = substr((string)$ev['end_time'], 0, 10);
            $startHm = substr((string)$ev['start_time'], 11, 5);
            $endHm = substr((string)$ev['end_time'], 11, 5);
            $isMultiday = ($dateKey !== $endDatePart);
            $isFullDay = ($startHm === '00:00' && $endHm === '23:59');
            $done = (($ev['status'] ?? '') === 'completed');

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

            $buckets[$dateKey][] = [
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
            ];
        }

        foreach ($upcomingTasks as $t) {
            $id = (int)$t['id'];
            $dateKey = (string)$t['due_date'];
            $endDate = !empty($t['end_date']) ? (string)$t['end_date'] : null;
            $isMultiday = ($endDate !== null && $endDate > $dateKey);
            $st = !empty($t['start_time']) ? substr((string)$t['start_time'], 0, 5) : null;
            $et = !empty($t['end_time']) ? substr((string)$t['end_time'], 0, 5) : null;
            $isFullDay = ($st === '00:00' && $et === '23:59');
            $done = (($t['status'] ?? '') === 'completed' || (int)($t['is_completed'] ?? 0) === 1);

            if ($isMultiday) {
                $rangeStr = date('d/m', strtotime($dateKey)) . ' – ' . date('d/m', strtotime($endDate));
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

            $buckets[$dateKey][] = [
                'id'             => $id,
                'is_task'        => true,
                'title'          => (string)$t['title'],
                'done'           => $done,
                'is_overdue'     => false,
                'time_label'     => $timeLabel,
                'sort_key'       => $sortKey,
                'is_current'     => false,
                'location'       => '',
                'category_name'  => (string)($t['category_name'] ?? ''),
                'category_color' => (string)($t['category_color'] ?: '#6658e8'),
                'priority'       => (string)($t['priority'] ?? 'medium'),
                'repeat_type'    => (string)($t['repeat_type'] ?? 'none'),
                'is_multiday'    => $isMultiday,
            ];
        }

        ksort($buckets);
        $groupedDays = [];
        $totalCount = 0;

        foreach ($buckets as $dateStr => $items) {
            usort($items, fn($a, $b) => strcmp($a['sort_key'], $b['sort_key']));
            $ts = strtotime($dateStr);
            $dow = $dowMap[(int)date('w', $ts)] ?? '';
            $dm = date('d/m', $ts);
            $label = ($dateStr === $tomorrowStr)
                ? "Ngày mai · {$dow}, {$dm}"
                : "{$dow}, {$dm}";

            $groupedDays[$dateStr] = [
                'date'  => $dateStr,
                'label' => $label,
                'items' => $items,
            ];
            $totalCount += count($items);
        }

        return [
            'groupedDays' => $groupedDays,
            'totalCount'  => $totalCount,
            'categories'  => $this->categoryModel->getAll($userId),
            'csrf_token'  => Auth::csrfToken()
        ];
    }

    public function trashData(): array
    {
        require_once __DIR__ . '/../Models/Event.php';
        $userId = Auth::id();
        $eventModel = new Event();

        $tasks = array_map(function ($t) {
            $t['item_type'] = 'task';
            return $t;
        }, $this->taskModel->trashForUser($userId));

        $events = array_map(function ($e) {
            $e['item_type'] = 'event';
            return $e;
        }, $eventModel->trashForUser($userId));

        $merged = array_merge($tasks, $events);
        usort($merged, fn($a, $b) => strcmp($b['deleted_at'] ?? '', $a['deleted_at'] ?? ''));

        return [
            'csrf_token' => Auth::csrfToken(),
            'trash'      => $merged
        ];
    }

    public function restore(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $input = $this->readInput();
        if (!Auth::verifyCsrf($this->extractCsrf($input))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $id = is_scalar($input['id'] ?? null) ? (int)$input['id'] : 0;
        $rawType = is_string($input['type'] ?? null) ? trim($input['type']) : 'task';
        $type = $rawType === 'event' ? 'event' : 'task';

        if ($type === 'event') {
            require_once __DIR__ . '/../Models/Event.php';
            $eventModel = new Event();
            $ok = $id > 0 && $eventModel->restore($id, $userId);
        } else {
            $ok = $id > 0 && $this->taskModel->restore($id, $userId);
        }

        echo json_encode([
            'success'      => $ok,
            'unread_count' => Notification::countUnread($userId),
            'message'      => $ok ? 'Đã khôi phục thành công.' : 'Không thể khôi phục mục này.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function forceDelete(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $input = $this->readInput();
        if (!Auth::verifyCsrf($this->extractCsrf($input))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $id = is_scalar($input['id'] ?? null) ? (int)$input['id'] : 0;
        $rawType = is_string($input['type'] ?? null) ? trim($input['type']) : 'task';
        $type = $rawType === 'event' ? 'event' : 'task';

        if ($type === 'event') {
            require_once __DIR__ . '/../Models/Event.php';
            $ok = $id > 0 && (new Event())->forceDelete($id, $userId);
        } else {
            $ok = $id > 0 && $this->taskModel->forceDelete($id, $userId);
        }

        echo json_encode([
            'success'      => $ok,
            'unread_count' => Notification::countUnread($userId),
            'message'      => $ok ? 'Đã xóa vĩnh viễn.' : 'Không thể xóa mục này.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function emptyTrash(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $input = $this->readInput();
        if (!Auth::verifyCsrf($this->extractCsrf($input))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        require_once __DIR__ . '/../Models/Event.php';
        $ok1 = $this->taskModel->emptyTrash($userId);
        $ok2 = (new Event())->emptyTrash($userId);
        $ok = ($ok1 && $ok2);

        echo json_encode([
            'success'      => $ok,
            'unread_count' => Notification::countUnread($userId),
            'message'      => $ok ? 'Đã dọn sạch thùng rác.' : 'Không thể dọn thùng rác.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
