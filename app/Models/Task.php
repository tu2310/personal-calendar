<?php
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/Notification.php';

class Task
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function allForUser(int $userId, array $filters = []): array
    {
        $sql = "
            SELECT t.*, 
                   c.name AS category_name, 
                   c.color AS category_color, 
                   COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon,
                   CASE 
                       WHEN t.status != 'completed' AND t.due_date IS NOT NULL AND COALESCE(t.end_date, t.due_date) < CURDATE() THEN 1 
                       ELSE 0 
                   END AS is_overdue
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id AND c.user_id = t.user_id
            WHERE t.user_id = :userId AND t.deleted_at IS NULL
        ";
        $params = ['userId' => $userId];

        if (!empty($filters['from_today'])) {
            $sql .= " AND (t.due_date IS NULL OR COALESCE(t.end_date, t.due_date) >= CURDATE() OR (t.status != 'completed' AND COALESCE(t.end_date, t.due_date) < CURDATE()))";
        }

        // 1. Tìm kiếm theo tên hoặc mô tả
        $searchStr = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        if ($searchStr !== '') {
            $sql .= " AND (t.title LIKE :searchTitle OR t.description LIKE :searchDesc)";
            $searchTerm = '%' . $searchStr . '%';
            $params['searchTitle'] = $searchTerm;
            $params['searchDesc'] = $searchTerm;
        }

        // 2. Lọc theo trạng thái
        $filterStatus = is_string($filters['status'] ?? null) ? $filters['status'] : '';
        if ($filterStatus !== '') {
            if ($filterStatus === 'pending') {
                $sql .= " AND t.status = 'pending'";
            } elseif ($filterStatus === 'completed') {
                $sql .= " AND t.status = 'completed'";
            } elseif ($filterStatus === 'overdue') {
                $sql .= " AND t.status = 'pending' AND t.due_date IS NOT NULL AND COALESCE(t.end_date, t.due_date) < CURDATE()";
            }
        }

        // 3. Lọc theo mức độ ưu tiên
        $filterPriority = is_string($filters['priority'] ?? null) ? $filters['priority'] : '';
        if ($filterPriority !== '' && in_array($filterPriority, ['low', 'medium', 'high', 'urgent'], true)) {
            $sql .= " AND t.priority = :priority";
            $params['priority'] = $filterPriority;
        }

        // 4. Lọc theo danh mục
        $filterCatId = is_scalar($filters['category_id'] ?? null) ? (int)$filters['category_id'] : 0;
        if ($filterCatId > 0) {
            $sql .= " AND t.category_id = :categoryId";
            $params['categoryId'] = $filterCatId;
        }

        // 5. Sắp xếp
        $sortBy = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'created_desc';
        $orderPrefix = !empty($filters['from_today']) ? 'is_overdue DESC, ' : '';
        switch ($sortBy) {
            case 'due_asc':
                $sql .= " ORDER BY {$orderPrefix}(t.due_date IS NULL), t.due_date ASC, t.id DESC";
                break;
            case 'due_desc':
                $sql .= " ORDER BY {$orderPrefix}(t.due_date IS NULL), t.due_date DESC, t.id DESC";
                break;
            case 'priority_desc':
                $sql .= " ORDER BY {$orderPrefix}FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.id DESC";
                break;
            case 'created_asc':
                $sql .= " ORDER BY {$orderPrefix}t.created_at ASC, t.id ASC";
                break;
            case 'created_desc':
            default:
                $sql .= " ORDER BY {$orderPrefix}t.created_at DESC, t.id DESC";
                break;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTodayTasks(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   c.name AS category_name, 
                   c.color AS category_color, 
                   COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon,
                   CASE 
                       WHEN t.status != 'completed' AND t.due_date IS NOT NULL AND COALESCE(t.end_date, t.due_date) < CURDATE() THEN 1 
                       ELSE 0 
                   END AS is_overdue
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id AND c.user_id = t.user_id
            WHERE t.user_id = :userId
              AND t.deleted_at IS NULL
              AND (
                  (t.status != 'completed' AND t.due_date IS NOT NULL AND COALESCE(t.end_date, t.due_date) < CURDATE())
                  OR (t.status = 'completed' AND t.due_date IS NOT NULL AND COALESCE(t.end_date, t.due_date) < CURDATE() AND DATE(t.updated_at) = CURDATE())
                  OR (t.due_date = CURDATE())
                  OR (t.due_date IS NOT NULL AND t.end_date IS NOT NULL AND t.due_date <= CURDATE() AND t.end_date >= CURDATE())
              )
            ORDER BY is_overdue DESC, (t.start_time IS NULL) ASC, t.start_time ASC, t.id DESC
        ");
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function getUpcomingTasks(int $userId, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   c.name AS category_name, 
                   c.color AS category_color, 
                   COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon,
                   0 AS is_overdue
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id AND c.user_id = t.user_id
            WHERE t.user_id = :userId
              AND t.deleted_at IS NULL
              AND t.due_date IS NOT NULL
              AND t.due_date > CURDATE()
              AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
            ORDER BY t.due_date ASC, (t.start_time IS NULL) ASC, t.start_time ASC, t.id ASC
        ");
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   c.name AS category_name, 
                   c.color AS category_color, 
                   COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon,
                   CASE 
                       WHEN t.status != 'completed' AND t.due_date IS NOT NULL AND COALESCE(t.end_date, t.due_date) < CURDATE() THEN 1 
                       ELSE 0 
                   END AS is_overdue
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id AND c.user_id = t.user_id
            WHERE t.id = :id AND t.user_id = :userId AND t.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'userId' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function normalizeTimeValue($raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $trimmed, $m)) {
            return $m[1] . ':' . $m[2] . ':' . ($m[3] ?? '00');
        }
        return null;
    }

    public function create(int $userId, array $data): int
    {
        $priority = in_array($data['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $data['priority'] : 'medium';
        $status = in_array($data['status'] ?? '', ['pending', 'completed'], true) ? $data['status'] : 'pending';
        $isCompleted = ($status === 'completed') ? 1 : 0;
        $progress = $isCompleted ? 100 : (isset($data['progress']) ? min(100, max(0, (int)$data['progress'])) : 0);
        $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;
        $startTime = self::normalizeTimeValue($data['start_time'] ?? null);
        $endTime = self::normalizeTimeValue($data['end_time'] ?? null);
        $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
        $repeatType = in_array($data['repeat_type'] ?? '', ['none', 'daily', 'weekly', 'monthly'], true) ? $data['repeat_type'] : 'none';

        $stmt = $this->db->prepare("
            INSERT INTO tasks (
                user_id, category_id, title, description,
                priority, status, is_completed, progress,
                due_date, start_time, end_time, end_date, repeat_type,
                created_at, updated_at
            ) VALUES (
                :user_id, :category_id, :title, :description,
                :priority, :status, :is_completed, :progress,
                :due_date, :start_time, :end_time, :end_date, :repeat_type,
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            'user_id'      => $userId,
            'category_id'  => !empty($data['category_id']) ? (int)$data['category_id'] : null,
            'title'        => trim($data['title']),
            'description'  => !empty($data['description']) ? trim($data['description']) : null,
            'priority'     => $priority,
            'status'       => $status,
            'is_completed' => $isCompleted,
            'progress'     => $progress,
            'due_date'     => $dueDate,
            'start_time'   => $startTime,
            'end_time'     => $endTime,
            'end_date'     => $endDate,
            'repeat_type'  => $repeatType
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $priority = in_array($data['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $data['priority'] : 'medium';
        $status = in_array($data['status'] ?? '', ['pending', 'completed'], true) ? $data['status'] : 'pending';
        $isCompleted = ($status === 'completed') ? 1 : 0;
        $progress = $isCompleted ? 100 : (isset($data['progress']) ? min(100, max(0, (int)$data['progress'])) : 0);
        $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;
        $startTime = self::normalizeTimeValue($data['start_time'] ?? null);
        $endTime = self::normalizeTimeValue($data['end_time'] ?? null);
        $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
        $repeatType = in_array($data['repeat_type'] ?? '', ['none', 'daily', 'weekly', 'monthly'], true) ? $data['repeat_type'] : 'none';

        $stmt = $this->db->prepare("
            UPDATE tasks SET
                category_id  = :category_id,
                title        = :title,
                description  = :description,
                priority     = :priority,
                status       = :status,
                is_completed = :is_completed,
                progress     = :progress,
                due_date     = :due_date,
                start_time   = :start_time,
                end_time     = :end_time,
                end_date     = :end_date,
                repeat_type  = :repeat_type,
                updated_at   = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");

        return $stmt->execute([
            'id'           => $id,
            'user_id'      => $userId,
            'category_id'  => !empty($data['category_id']) ? (int)$data['category_id'] : null,
            'title'        => trim($data['title']),
            'description'  => !empty($data['description']) ? trim($data['description']) : null,
            'priority'     => $priority,
            'status'       => $status,
            'is_completed' => $isCompleted,
            'progress'     => $progress,
            'due_date'     => $dueDate,
            'start_time'   => $startTime,
            'end_time'     => $endTime,
            'end_date'     => $endDate,
            'repeat_type'  => $repeatType
        ]);
    }

    public function updateStatus(int $id, int $userId, string $status): bool
    {
        $status = in_array($status, ['pending', 'completed'], true) ? $status : 'pending';
        $isCompleted = ($status === 'completed') ? 1 : 0;
        $progress = $isCompleted ? 100 : 0;

        $stmt = $this->db->prepare("
            UPDATE tasks SET
                status       = :status,
                is_completed = :is_completed,
                progress     = :progress,
                updated_at   = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");

        return $stmt->execute([
            'id'           => $id,
            'user_id'      => $userId,
            'status'       => $status,
            'is_completed' => $isCompleted,
            'progress'     => $progress
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tasks SET deleted_at = NOW(), updated_at = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        return $stmt->execute([
            'id'      => $id,
            'user_id' => $userId
        ]) && $stmt->rowCount() > 0;
    }

    public function trashForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, c.name AS category_name, c.color AS category_color
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id AND c.user_id = t.user_id
            WHERE t.user_id = :userId AND t.deleted_at IS NOT NULL
            ORDER BY t.deleted_at DESC, t.id DESC
        ");
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function restore(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tasks SET deleted_at = NULL, updated_at = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NOT NULL
        ");
        return $stmt->execute(['id' => $id, 'user_id' => $userId]) && $stmt->rowCount() > 0;
    }

    public function forceDelete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM tasks
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NOT NULL
        ");
        $ok = $stmt->execute(['id' => $id, 'user_id' => $userId]) && $stmt->rowCount() > 0;
        if ($ok) {
            Notification::deleteByReferencePrefix($userId, 'task', $id);
        }
        return $ok;
    }

    public function emptyTrash(int $userId): bool
    {
        $idsStmt = $this->db->prepare("SELECT id FROM tasks WHERE user_id = :user_id AND deleted_at IS NOT NULL");
        $idsStmt->execute(['user_id' => $userId]);
        $taskIds = array_map('intval', $idsStmt->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($taskIds)) {
            Notification::deleteByReferencePrefixBatch($userId, 'task', $taskIds);
        }

        $stmt = $this->db->prepare("
            DELETE FROM tasks
            WHERE user_id = :user_id AND deleted_at IS NOT NULL
        ");
        return $stmt->execute(['user_id' => $userId]);
    }

    public function getIncompleteForReminders(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, title, due_date, priority, status
            FROM tasks
            WHERE user_id = :userId
              AND deleted_at IS NULL
              AND status NOT IN ('done', 'completed')
            ORDER BY (due_date IS NULL), due_date ASC, id DESC
        ");
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function getStats(int $userId, bool $fromToday = false): array
    {
        $whereScope = '';
        if ($fromToday) {
            $whereScope = " AND (t.due_date IS NULL OR COALESCE(t.end_date, t.due_date) >= CURDATE() OR (t.status != 'completed' AND COALESCE(t.end_date, t.due_date) < CURDATE()))";
        }

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN t.priority = 'urgent' AND t.status = 'pending' THEN 1 ELSE 0 END) AS urgent,
                SUM(CASE WHEN t.priority = 'high' AND t.status = 'pending' THEN 1 ELSE 0 END) AS high,
                SUM(CASE WHEN t.status = 'pending' AND t.due_date IS NOT NULL AND COALESCE(t.end_date, t.due_date) < CURDATE() THEN 1 ELSE 0 END) AS overdue
            FROM tasks t
            WHERE t.user_id = :userId AND t.deleted_at IS NULL{$whereScope}
        ");
        $stmt->execute(['userId' => $userId]);
        $row = $stmt->fetch() ?: [];

        $total = (int)($row['total'] ?? 0);
        $completed = (int)($row['completed'] ?? 0);
        $pending = (int)($row['pending'] ?? 0);
        $urgent = (int)($row['urgent'] ?? 0);
        $high = (int)($row['high'] ?? 0);
        $overdue = (int)($row['overdue'] ?? 0);
        $percent = $total > 0 ? (int)round(($completed / $total) * 100) : 0;

        return [
            'total'            => $total,
            'completed'        => $completed,
            'pending'          => $pending,
            'urgent'           => $urgent,
            'high'             => $high,
            'overdue'          => $overdue,
            'progress_percent' => $percent
        ];
    }

    /**
     * Tìm kiếm trực tiếp (Live Search) công việc theo tiêu đề, mô tả, danh mục
     * Tự động chấm điểm phù hợp và đưa kết quả khớp nhất lên ĐẦU TIÊN
     */
    public function search(int $userId, string $keyword, int $limit = 10): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT t.*, c.name AS category_name, c.color AS category_color, COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id AND c.user_id = t.user_id
            WHERE t.user_id = :userId AND t.deleted_at IS NULL
            ORDER BY t.created_at DESC
        ");
        $stmt->execute(['userId' => $userId]);
        $tasks = $stmt->fetchAll();

        $normQuery = self::removeVietnameseTones($keyword);
        $scored = [];

        foreach ($tasks as $task) {
            $title = $task['title'] ?? '';
            $desc  = $task['description'] ?? '';
            $cat   = $task['category_name'] ?? '';

            $titleLower = mb_strtolower($title, 'UTF-8');
            $titleNorm  = self::removeVietnameseTones($title);

            $haystackOriginal = mb_strtolower($title . ' ' . $desc . ' ' . $cat, 'UTF-8');
            $haystackNormalized = self::removeVietnameseTones($haystackOriginal);

            $score = 0;
            // 1. Khớp ngay đầu tiêu đề (Ưu tiên cao nhất -> Hiện lên đầu tiên)
            if (str_starts_with($titleLower, mb_strtolower($keyword, 'UTF-8')) || str_starts_with($titleNorm, $normQuery)) {
                $score = 3;
            } elseif (mb_stripos($titleLower, $keyword) !== false || mb_stripos($titleNorm, $normQuery) !== false) {
                // 2. Khớp trong tiêu đề
                $score = 2;
            } elseif (mb_stripos($haystackOriginal, $keyword) !== false || mb_stripos($haystackNormalized, $normQuery) !== false) {
                // 3. Khớp mô tả hoặc danh mục
                $score = 1;
            }

            if ($score > 0) {
                $task['_score'] = $score;
                $scored[] = $task;
            }
        }

        // Sắp xếp: Điểm khớp cao nhất lên đầu tiên
        usort($scored, function ($a, $b) {
            if ($b['_score'] !== $a['_score']) {
                return $b['_score'] - $a['_score'];
            }
            return (int)$b['id'] - (int)$a['id'];
        });

        return array_slice($scored, 0, $limit);
    }

    /**
     * Bỏ dấu tiếng Việt phục vụ tìm kiếm không phân biệt dấu
     */
    public static function removeVietnameseTones(string $str): string
    {
        $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u", "a", $str);
        $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u", "e", $str);
        $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/u", "i", $str);
        $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u", "o", $str);
        $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u", "u", $str);
        $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/u", "y", $str);
        $str = preg_replace("/(đ)/u", "d", $str);
        $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/u", "A", $str);
        $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/u", "E", $str);
        $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/u", "I", $str);
        $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/u", "O", $str);
        $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/u", "U", $str);
        $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/u", "Y", $str);
        $str = preg_replace("/(Đ)/u", "D", $str);
        return mb_strtolower($str, 'UTF-8');
    }
}
