<?php
require_once __DIR__ . '/../Core/Database.php';

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
                       WHEN t.status != 'completed' AND t.due_date IS NOT NULL AND t.due_date < CURDATE() THEN 1 
                       ELSE 0 
                   END AS is_overdue
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = :userId
        ";
        $params = ['userId' => $userId];

        // 1. Tìm kiếm theo tên hoặc mô tả
        if (!empty($filters['search'])) {
            $sql .= " AND (t.title LIKE :searchTitle OR t.description LIKE :searchDesc)";
            $searchTerm = '%' . trim($filters['search']) . '%';
            $params['searchTitle'] = $searchTerm;
            $params['searchDesc'] = $searchTerm;
        }

        // 2. Lọc theo trạng thái
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $sql .= " AND t.status = 'pending'";
            } elseif ($filters['status'] === 'completed') {
                $sql .= " AND t.status = 'completed'";
            } elseif ($filters['status'] === 'overdue') {
                $sql .= " AND t.status = 'pending' AND t.due_date IS NOT NULL AND t.due_date < CURDATE()";
            }
        }

        // 3. Lọc theo mức độ ưu tiên
        if (!empty($filters['priority']) && in_array($filters['priority'], ['low', 'medium', 'high', 'urgent'], true)) {
            $sql .= " AND t.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        // 4. Lọc theo danh mục
        if (!empty($filters['category_id'])) {
            $sql .= " AND t.category_id = :categoryId";
            $params['categoryId'] = (int)$filters['category_id'];
        }

        // 5. Sắp xếp
        $sortBy = $filters['sort'] ?? 'created_desc';
        switch ($sortBy) {
            case 'due_asc':
                $sql .= " ORDER BY (t.due_date IS NULL), t.due_date ASC, t.id DESC";
                break;
            case 'due_desc':
                $sql .= " ORDER BY (t.due_date IS NULL), t.due_date DESC, t.id DESC";
                break;
            case 'priority_desc':
                $sql .= " ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.id DESC";
                break;
            case 'created_asc':
                $sql .= " ORDER BY t.created_at ASC, t.id ASC";
                break;
            case 'created_desc':
            default:
                $sql .= " ORDER BY t.created_at DESC, t.id DESC";
                break;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
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
                       WHEN t.status != 'completed' AND t.due_date IS NOT NULL AND t.due_date < CURDATE() THEN 1 
                       ELSE 0 
                   END AS is_overdue
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.id = :id AND t.user_id = :userId
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'userId' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $priority = in_array($data['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $data['priority'] : 'medium';
        $status = in_array($data['status'] ?? '', ['pending', 'completed'], true) ? $data['status'] : 'pending';
        $isCompleted = ($status === 'completed') ? 1 : 0;
        $progress = $isCompleted ? 100 : (isset($data['progress']) ? min(100, max(0, (int)$data['progress'])) : 0);
        $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;

        $stmt = $this->db->prepare("
            INSERT INTO tasks (
                user_id, category_id, title, description,
                priority, status, is_completed, progress, due_date, created_at, updated_at
            ) VALUES (
                :user_id, :category_id, :title, :description,
                :priority, :status, :is_completed, :progress, :due_date, NOW(), NOW()
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
            'due_date'     => $dueDate
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
                updated_at   = NOW()
            WHERE id = :id AND user_id = :user_id
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
            'due_date'     => $dueDate
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
            WHERE id = :id AND user_id = :user_id
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
            DELETE FROM tasks 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([
            'id'      => $id,
            'user_id' => $userId
        ]);
    }

    public function getStats(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN priority = 'urgent' AND status = 'pending' THEN 1 ELSE 0 END) AS urgent,
                SUM(CASE WHEN priority = 'high' AND status = 'pending' THEN 1 ELSE 0 END) AS high,
                SUM(CASE WHEN status = 'pending' AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
            FROM tasks
            WHERE user_id = :userId
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
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = :userId
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
