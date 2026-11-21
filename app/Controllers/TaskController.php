<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/Task.php';
require_once __DIR__ . '/../Models/Category.php';

class TaskController
{
    private Task $taskModel;
    private Category $categoryModel;

    public function __construct()
    {
        $this->taskModel = new Task();
        $this->categoryModel = new Category();
    }

    public function index(): array
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

    public function apiList(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $userId = Auth::id();
        $filters = [
            'search'      => $_GET['search'] ?? '',
            'status'      => $_GET['status'] ?? 'all',
            'priority'    => $_GET['priority'] ?? 'all',
            'category_id' => !empty($_GET['category_id']) ? (int)$_GET['category_id'] : '',
            'sort'        => $_GET['sort'] ?? 'created_desc'
        ];

        $tasks = $this->taskModel->allForUser($userId, $filters);
        $stats = $this->taskModel->getStats($userId);

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
        $id = (int)($_GET['id'] ?? 0);

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
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $dueDate = !empty($input['due_date']) ? trim($input['due_date']) : null;
        $priority = in_array($input['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $input['priority'] : 'medium';
        $status = in_array($input['status'] ?? '', ['pending', 'completed'], true) ? $input['status'] : 'pending';

        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên công việc!']);
            exit;
        }

        if (mb_strlen($title) > 255) {
            echo json_encode(['success' => false, 'message' => 'Tên công việc không được vượt quá 255 ký tự.']);
            exit;
        }

        if ($dueDate && !strtotime($dueDate)) {
            echo json_encode(['success' => false, 'message' => 'Định dạng hạn hoàn thành không hợp lệ.']);
            exit;
        }

        $data = [
            'category_id' => $categoryId,
            'title'       => $title,
            'description' => $description,
            'due_date'    => $dueDate,
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
                    'success' => true,
                    'message' => 'Cập nhật công việc thành công!',
                    'stats'   => $this->taskModel->getStats($userId)
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể lưu công việc.']);
            }
        } else {
            $newId = $this->taskModel->create($userId, $data);
            if ($newId) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Thêm công việc mới thành công!',
                    'id'      => $newId,
                    'stats'   => $this->taskModel->getStats($userId)
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
        $status = ($input['status'] ?? '') === 'completed' ? 'completed' : 'pending';

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID công việc không hợp lệ.']);
            exit;
        }

        $existing = $this->taskModel->findById($id, $userId);
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy công việc hoặc không có quyền thao tác.']);
            exit;
        }

        $ok = $this->taskModel->updateStatus($id, $userId, $status);
        if ($ok) {
            echo json_encode([
                'success' => true,
                'status'  => $status,
                'message' => $status === 'completed' ? 'Đã hoàn thành công việc!' : 'Đã chuyển về chưa hoàn thành!',
                'stats'   => $this->taskModel->getStats($userId)
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
            echo json_encode(['success' => false, 'message' => 'ID công việc không hợp lệ.']);
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
                'success' => true,
                'message' => 'Xóa công việc thành công!',
                'stats'   => $this->taskModel->getStats($userId)
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa công việc.']);
        }
        exit;
    }
}
