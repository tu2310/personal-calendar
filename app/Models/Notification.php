<?php
require_once __DIR__ . '/../Core/Database.php';

class Notification
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function allForUser(int $userId, ?string $type = null, int $limit = 30): array
    {
        $sql = "
            SELECT * FROM notifications 
            WHERE user_id = :userId
        ";
        $params = ['userId' => $userId];

        if (!empty($type) && in_array($type, ['event', 'task', 'system'], true)) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }

        $sql .= " ORDER BY created_at DESC, id DESC LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$item) {
            $item['time_ago'] = $this->formatTimeAgo($item['created_at']);
        }
        unset($item);

        return $rows;
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = :userId AND is_read = 0
        ");
        $stmt->execute(['userId' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = :id AND user_id = :userId
        ");
        return $stmt->execute(['id' => $id, 'userId' => $userId]);
    }

    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = :userId AND is_read = 0
        ");
        return $stmt->execute(['userId' => $userId]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE id = :id AND user_id = :userId
        ");
        return $stmt->execute(['id' => $id, 'userId' => $userId]);
    }

    public function deleteAll(int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE user_id = :userId
        ");
        return $stmt->execute(['userId' => $userId]);
    }

    public function create(
        int $userId,
        string $title,
        ?string $message = null,
        string $type = 'system',
        ?string $actionUrl = null,
        ?string $refType = null,
        ?int $refId = null
    ): int {
        $type = in_array($type, ['event', 'task', 'system'], true) ? $type : 'system';

        $stmt = $this->db->prepare("
            INSERT INTO notifications (
                user_id, type, title, message, action_url, reference_type, reference_id, is_read, time_ago, created_at
            ) VALUES (
                :user_id, :type, :title, :message, :action_url, :reference_type, :reference_id, 0, 'Vừa xong', NOW()
            )
        ");

        $stmt->execute([
            'user_id'        => $userId,
            'type'           => $type,
            'title'          => trim($title),
            'message'        => !empty($message) ? trim($message) : null,
            'action_url'     => !empty($actionUrl) ? trim($actionUrl) : null,
            'reference_type' => !empty($refType) ? trim($refType) : null,
            'reference_id'   => !empty($refId) ? (int)$refId : null
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function hasNotificationFor(int $userId, string $refType, int $refId, string $type): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = :userId 
              AND reference_type = :refType 
              AND reference_id = :refId 
              AND type = :type
        ");
        $stmt->execute([
            'userId'  => $userId,
            'refType' => $refType,
            'refId'   => $refId,
            'type'    => $type
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function formatTimeAgo(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) return 'Gần đây';

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Vừa xong';
        } elseif ($diff < 3600) {
            $mins = max(1, (int)floor($diff / 60));
            return "{$mins} phút trước";
        } elseif ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return "{$hours} giờ trước";
        } elseif ($diff < 604800) {
            $days = (int)floor($diff / 86400);
            return "{$days} ngày trước";
        } else {
            return date('d/m/Y H:i', $timestamp);
        }
    }
}
