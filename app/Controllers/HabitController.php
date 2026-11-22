<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/Habit.php';
require_once __DIR__ . '/../Models/Event.php';
require_once __DIR__ . '/../Models/Task.php';
require_once __DIR__ . '/../Models/Notification.php';

class HabitController
{
    private Habit $habitModel;
    private Notification $notifModel;

    public function __construct()
    {
        $this->habitModel = new Habit();
        $this->notifModel = new Notification();
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

    public function save(): void
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
        $id = is_scalar($input['id'] ?? null) && (int)$input['id'] > 0 ? (int)$input['id'] : null;
        $name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        $description = is_string($input['description'] ?? null) ? trim($input['description']) : '';
        $rawFreq = is_string($input['frequency'] ?? null) ? trim($input['frequency']) : 'daily';
        $frequency = $rawFreq === 'weekly' ? 'weekly' : 'daily';
        $rawTarget = is_scalar($input['target_per_week'] ?? null) ? (int)$input['target_per_week'] : 5;
        $targetPerWeek = $frequency === 'daily'
            ? 7
            : min(7, max(1, $rawTarget));
        $color = is_string($input['color'] ?? null) ? trim($input['color']) : '#c2410c';
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
                'success'      => $ok,
                'id'           => $id,
                'message'      => $ok ? 'Đã cập nhật thói quen.' : 'Không thể cập nhật thói quen.',
                'summary'      => $this->habitModel->getSummary($habits),
                'unread_count' => Notification::countUnread($userId)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $newId = $this->habitModel->create($userId, $payload);
        $habits = $this->habitModel->allForUser($userId, 7);
        echo json_encode([
            'success'      => $newId > 0,
            'id'           => $newId,
            'message'      => $newId > 0 ? 'Đã thêm thói quen mới.' : 'Không thể tạo thói quen.',
            'summary'      => $this->habitModel->getSummary($habits),
            'unread_count' => Notification::countUnread($userId)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function toggle(): void
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
        $habitId = is_scalar($input['habit_id'] ?? null) ? (int)$input['habit_id'] : 0;
        $date = is_string($input['date'] ?? null) && trim($input['date']) !== '' ? trim($input['date']) : date('Y-m-d');

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

        if (!empty($res['checked'])) {
            $habitName = $updatedHabit['name'] ?? 'Thói quen';
            $streak = (int)($updatedHabit['current_streak'] ?? 1);
            Notification::createUniqueToday([
                'user_id'        => $userId,
                'type'           => 'system',
                'title'          => 'Hoàn thành thói quen: ' . $habitName,
                'message'        => "Bạn đã hoàn thành thói quen \"{$habitName}\" (Chuỗi hiện tại: {$streak} ngày).",
                'action_url'     => 'index.php?page=habits',
                'reference_type' => 'habit_checkin',
                'reference_id'   => $habitId
            ]);
        }

        echo json_encode([
            'success'      => true,
            'checked'      => $res['checked'],
            'habit'        => $updatedHabit,
            'habits'       => $habits,
            'habitWeekly'  => $habitWeekly,
            'summary'      => $summary,
            'unread_count' => Notification::countUnread($userId)
        ], JSON_UNESCAPED_UNICODE);
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

        $input = $this->readInput();
        if (!Auth::verifyCsrf($this->extractCsrf($input))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $id = is_scalar($input['id'] ?? null) ? (int)$input['id'] : 0;
        if ($id <= 0 || !$this->habitModel->findById($id, $userId)) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy thói quen hoặc không có quyền xóa.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ok = $this->habitModel->delete($id, $userId);
        if ($ok) {
            Notification::deleteByReferencePrefix($userId, 'habit', $id);
        }
        $habits = $this->habitModel->allForUser($userId, 7);
        echo json_encode([
            'success'      => $ok,
            'message'      => $ok ? 'Đã xóa thói quen.' : 'Không thể xóa thói quen.',
            'habits'       => $habits,
            'habitWeekly'  => $this->habitModel->getWeeklySeries($userId, 7),
            'summary'      => $this->habitModel->getSummary($habits),
            'unread_count' => Notification::countUnread($userId)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
