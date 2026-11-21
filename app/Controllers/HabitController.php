<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/Habit.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/Task.php';

class HabitController
{
    private Habit $habitModel;

    public function __construct()
    {
        $this->habitModel = new Habit();
    }

    public function index(): array
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $habits = $this->habitModel->allForUser($userId, 7);
        $summary = $this->habitModel->getSummary($habits);
        $daysHeader = $this->habitModel->getRecentDaysHeader(7);
        $habitWeekly = $this->habitModel->getWeeklySeries($userId, 7);

        $eventModel = new Event();
        $taskModel = new Task();
        $eventStats = $eventModel->getStats($userId);
        $weeklyChart = $eventModel->getWeeklyStats($userId);
        $taskStats = $taskModel->getStats($userId);

        return [
            'habits'      => $habits,
            'summary'     => $summary,
            'daysHeader'  => $daysHeader,
            'habitWeekly' => $habitWeekly,
            'eventStats'  => $eventStats,
            'weeklyChart' => $weeklyChart,
            'taskStats'   => $taskStats,
            'today'       => date('Y-m-d'),
            'csrf_token'  => Auth::csrfToken()
        ];
    }

    private function readInput(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '', true);
        return is_array($decoded) ? $decoded : [];
    }

    public function save(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $input = $this->readInput();
        $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $id = !empty($input['id']) ? (int)$input['id'] : null;
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $frequency = ($input['frequency'] ?? 'daily') === 'weekly' ? 'weekly' : 'daily';
        $targetPerWeek = $frequency === 'daily'
            ? 7
            : min(7, max(1, (int)($input['target_per_week'] ?? 5)));
        $color = trim((string)($input['color'] ?? '#c2410c'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#c2410c';
        }

        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            echo json_encode(['success' => false, 'message' => 'Tên thói quen phải từ 1 đến 120 ký tự.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (mb_strlen($description, 'UTF-8') > 255) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú tối đa 255 ký tự.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $payload = [
            'name'            => $name,
            'description'     => $description,
            'frequency'       => $frequency,
            'target_per_week' => $targetPerWeek,
            'color'           => $color
        ];

        if ($id) {
            $existing = $this->habitModel->findById($id, $userId);
            if (!$existing) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy thói quen cần sửa.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $ok = $this->habitModel->update($id, $userId, $payload);
            $habits = $this->habitModel->allForUser($userId, 7);
            echo json_encode([
                'success' => $ok,
                'id'      => $id,
                'message' => $ok ? 'Đã cập nhật thói quen.' : 'Không thể cập nhật thói quen.',
                'summary' => $this->habitModel->getSummary($habits)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $newId = $this->habitModel->create($userId, $payload);
        $habits = $this->habitModel->allForUser($userId, 7);
        echo json_encode([
            'success' => $newId > 0,
            'id'      => $newId,
            'message' => $newId > 0 ? 'Đã thêm thói quen mới.' : 'Không thể tạo thói quen.',
            'summary' => $this->habitModel->getSummary($habits)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function toggle(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $input = $this->readInput();
        $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $habitId = (int)($input['habit_id'] ?? 0);
        $date = trim((string)($input['date'] ?? date('Y-m-d')));

        if ($habitId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID thói quen không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $date);
        $isValidDate = $dt && $dt->format('Y-m-d') === $date;
        $today = date('Y-m-d');
        $minDate = date('Y-m-d', strtotime('-365 days'));
        if (!$isValidDate || $date > $today || $date < $minDate) {
            echo json_encode(['success' => false, 'message' => 'Ngày đánh dấu không hợp lệ hoặc nằm trong tương lai.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $res = $this->habitModel->toggleLog($habitId, $userId, $date);
        if ($res === null) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy thói quen.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $habits = $this->habitModel->allForUser($userId, 7);
        $summary = $this->habitModel->getSummary($habits);
        $habitWeekly = $this->habitModel->getWeeklySeries($userId, 7);
        $updatedHabit = null;
        foreach ($habits as $h) {
            if ((int)$h['id'] === $habitId) {
                $updatedHabit = $h;
                break;
            }
        }

        echo json_encode([
            'success'     => true,
            'checked'     => $res['checked'],
            'habit'       => $updatedHabit,
            'habits'      => $habits,
            'habitWeekly' => $habitWeekly,
            'summary'     => $summary
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function delete(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $input = $this->readInput();
        $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::verifyCsrf($csrf)) {
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0 || !$this->habitModel->findById($id, $userId)) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy thói quen hoặc không có quyền xóa.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $this->habitModel->delete($id, $userId);
        $habits = $this->habitModel->allForUser($userId, 7);
        echo json_encode([
            'success'     => $ok,
            'message'     => $ok ? 'Đã xóa thói quen.' : 'Không thể xóa thói quen.',
            'habits'      => $habits,
            'habitWeekly' => $this->habitModel->getWeeklySeries($userId, 7),
            'summary'     => $this->habitModel->getSummary($habits)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
