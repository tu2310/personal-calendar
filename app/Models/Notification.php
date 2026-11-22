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
        $limit = max(1, min(100, $limit));
        $sql = "
            SELECT * FROM notifications 
            WHERE user_id = :userId
        ";
        $params = ['userId' => $userId];

        if (!empty($type) && in_array($type, ['event', 'task', 'system'], true)) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }

        $sql .= " ORDER BY created_at DESC, id DESC LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$item) {
            $fallback = !empty($item['time_ago']) ? (string)$item['time_ago'] : 'Vừa xong';
            $item['time_ago'] = !empty($item['created_at'])
                ? $this->formatTimeAgo((string)$item['created_at'], $fallback)
                : $fallback;
            $item['action_url'] = self::sanitizeActionUrl(is_string($item['action_url'] ?? null) ? $item['action_url'] : null);
        }
        unset($item);

        return $rows;
    }

    public static function countUnread(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $db = Database::getConnection();
        $stmt = $db->prepare("
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

    private static function sanitizeActionUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $clean = trim($url);
        if ($clean === '' || preg_match('/[\r\n\0]/', $clean) || !str_starts_with($clean, 'index.php?')) {
            return null;
        }
        return mb_substr($clean, 0, 255, 'UTF-8');
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
        $cleanTitle = mb_substr(trim($title), 0, 255, 'UTF-8');
        $safeActionUrl = self::sanitizeActionUrl($actionUrl);

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
            'title'          => $cleanTitle,
            'message'        => !empty($message) ? trim($message) : null,
            'action_url'     => $safeActionUrl,
            'reference_type' => !empty($refType) ? mb_substr(trim($refType), 0, 50, 'UTF-8') : null,
            'reference_id'   => !empty($refId) ? (int)$refId : null
        ]);

        return (int)$this->db->lastInsertId();
    }

    public static function hasNotificationToday(int $userId, string $referenceType, int $referenceId): bool
    {
        $referenceType = trim($referenceType);
        if ($userId <= 0 || $referenceType === '' || $referenceId <= 0) {
            return false;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 1 FROM notifications
            WHERE user_id = :userId
              AND reference_type = :refType
              AND reference_id = :refId
              AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");
        $stmt->execute([
            'userId'  => $userId,
            'refType' => $referenceType,
            'refId'   => $referenceId
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public static function createUniqueToday(array $data): ?int
    {
        $userId = is_scalar($data['user_id'] ?? null) ? (int)$data['user_id'] : 0;
        $title = is_string($data['title'] ?? null) ? trim($data['title']) : '';
        if ($userId <= 0 || $title === '') {
            return null;
        }

        $rawType = is_string($data['type'] ?? null) ? $data['type'] : '';
        $type = in_array($rawType, ['event', 'task', 'system'], true) ? $rawType : 'system';
        $cleanTitle = mb_substr($title, 0, 255, 'UTF-8');
        $message = is_string($data['message'] ?? null) && trim($data['message']) !== '' ? trim($data['message']) : null;
        $actionUrl = self::sanitizeActionUrl(is_string($data['action_url'] ?? null) ? $data['action_url'] : null);
        $refType = is_string($data['reference_type'] ?? null) && trim($data['reference_type']) !== ''
            ? mb_substr(trim($data['reference_type']), 0, 50, 'UTF-8')
            : null;
        $refId = is_scalar($data['reference_id'] ?? null) && (int)$data['reference_id'] > 0 ? (int)$data['reference_id'] : null;

        if ($refType !== null && $refId !== null && $refId > 0) {
            if (self::hasNotificationToday($userId, $refType, $refId)) {
                return null;
            }

            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO notifications (
                    user_id, type, title, message, action_url, reference_type, reference_id, is_read, time_ago, created_at
                )
                SELECT :user_id, :type, :title, :message, :action_url, :reference_type, :reference_id, 0, 'Vừa xong', NOW()
                FROM DUAL
                WHERE NOT EXISTS (
                    SELECT 1 FROM notifications
                    WHERE user_id = :chk_uid
                      AND reference_type = :chk_rtype
                      AND reference_id = :chk_rid
                      AND DATE(created_at) = CURDATE()
                )
            ");
            $stmt->execute([
                'user_id'        => $userId,
                'type'           => $type,
                'title'          => $cleanTitle,
                'message'        => $message,
                'action_url'     => $actionUrl,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'chk_uid'        => $userId,
                'chk_rtype'      => $refType,
                'chk_rid'        => $refId
            ]);

            return $stmt->rowCount() > 0 ? (int)$db->lastInsertId() : null;
        }

        return (new self())->create($userId, $cleanTitle, $message, $type, $actionUrl, $refType, $refId);
    }

    public static function deleteByReferencePrefix(int $userId, string $prefix, int $referenceId): void
    {
        $prefix = trim($prefix);
        if ($userId <= 0 || $prefix === '' || $referenceId <= 0) {
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            DELETE FROM notifications
            WHERE user_id = :userId
              AND reference_id = :refId
              AND (reference_type = :exactRef OR reference_type LIKE :prefixRef ESCAPE '\\\\')
        ");
        $stmt->execute([
            'userId'    => $userId,
            'refId'     => $referenceId,
            'exactRef'  => $prefix,
            'prefixRef' => $prefix . '\\_%'
        ]);
    }

    public static function deleteByReferencePrefixBatch(int $userId, string $prefix, array $referenceIds): void
    {
        $prefix = trim($prefix);
        $ids = array_values(array_unique(array_filter(array_map('intval', $referenceIds), fn(int $v) => $v > 0)));
        if ($userId <= 0 || $prefix === '' || empty($ids)) {
            return;
        }

        $db = Database::getConnection();
        $placeholders = [];
        $params = [
            'userId'    => $userId,
            'exactRef'  => $prefix,
            'prefixRef' => $prefix . '\\_%'
        ];
        foreach ($ids as $idx => $id) {
            $ph = 'rid_' . $idx;
            $placeholders[] = ':' . $ph;
            $params[$ph] = $id;
        }

        $inClause = implode(', ', $placeholders);
        $stmt = $db->prepare("
            DELETE FROM notifications
            WHERE user_id = :userId
              AND reference_id IN ({$inClause})
              AND (reference_type = :exactRef OR reference_type LIKE :prefixRef ESCAPE '\\\\')
        ");
        $stmt->execute($params);
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

    private function formatTimeAgo(?string $datetime, string $fallback = 'Vừa xong'): string
    {
        if (empty($datetime)) {
            return $fallback;
        }

        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return $fallback;
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Vừa xong';
        } elseif ($diff < 3600) {
            $mins = max(1, (int)floor($diff / 60));
            return "{$mins} phút trước";
        } elseif ($diff < 86400) {
            $hours = max(1, (int)floor($diff / 3600));
            return "{$hours} giờ trước";
        } elseif ($diff < 172800 || date('Y-m-d', $timestamp) === date('Y-m-d', strtotime('-1 day'))) {
            return 'Hôm qua';
        } elseif ($diff < 604800) {
            $days = max(2, (int)floor($diff / 86400));
            return "{$days} ngày trước";
        } else {
            return date('d/m/Y H:i', $timestamp);
        }
    }
}
