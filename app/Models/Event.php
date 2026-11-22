<?php
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/Notification.php';

class Event
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color, COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            WHERE e.user_id = :userId AND e.deleted_at IS NULL
            ORDER BY e.start_time ASC
        ");
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function getInRange(int $userId, string $start, string $end, ?int $categoryId = null): array
    {
        $sql = "
            SELECT e.*, c.name AS category_name, c.color AS category_color, COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            WHERE e.user_id = :userId AND e.deleted_at IS NULL
              AND e.start_time <= :end
              AND e.end_time >= :start
        ";
        $params = [
            'userId' => $userId,
            'start'  => $start,
            'end'    => $end
        ];

        if ($categoryId) {
            $sql .= " AND e.category_id = :categoryId";
            $params['categoryId'] = $categoryId;
        }

        $sql .= " ORDER BY e.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color, COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            WHERE e.id = :id AND e.user_id = :userId AND e.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'userId' => $userId]);
        $event = $stmt->fetch();
        return $event ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $reminderMinutes = isset($data['reminder_minutes']) && $data['reminder_minutes'] !== '' ? (int)$data['reminder_minutes'] : null;
        $reminderAt = !empty($data['reminder_at']) ? $data['reminder_at'] : null;

        // Nếu có reminder_minutes nhưng chưa có reminder_at, tự tính toán
        if ($reminderMinutes !== null && !$reminderAt && !empty($data['start_time'])) {
            $startTs = strtotime($data['start_time']);
            if ($startTs) {
                $reminderAt = date('Y-m-d H:i:s', $startTs - ($reminderMinutes * 60));
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO events (
                user_id, category_id, title, description,
                start_time, end_time, location, priority, status, 
                reminder_at, reminder_minutes, repeat_type, created_at, updated_at
            ) VALUES (
                :user_id, :category_id, :title, :description,
                :start_time, :end_time, :location, :priority, :status, 
                :reminder_at, :reminder_minutes, :repeat_type, NOW(), NOW()
            )
        ");

        $stmt->execute([
            'user_id'          => $userId,
            'category_id'      => !empty($data['category_id']) ? (int)$data['category_id'] : null,
            'title'            => trim($data['title']),
            'description'      => !empty($data['description']) ? trim($data['description']) : null,
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'location'         => !empty($data['location']) ? trim($data['location']) : null,
            'priority'         => in_array($data['priority'] ?? '', ['low', 'medium', 'high'], true) ? $data['priority'] : 'medium',
            'status'           => in_array($data['status'] ?? '', ['pending', 'completed'], true) ? $data['status'] : 'pending',
            'reminder_at'      => $reminderAt,
            'reminder_minutes' => $reminderMinutes,
            'repeat_type'      => in_array($data['repeat_type'] ?? '', ['none', 'daily', 'weekly', 'monthly'], true) ? $data['repeat_type'] : 'none'
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $reminderMinutes = isset($data['reminder_minutes']) && $data['reminder_minutes'] !== '' ? (int)$data['reminder_minutes'] : null;
        $reminderAt = !empty($data['reminder_at']) ? $data['reminder_at'] : null;

        // Nếu có reminder_minutes nhưng chưa có reminder_at, tự tính toán
        if ($reminderMinutes !== null && !$reminderAt && !empty($data['start_time'])) {
            $startTs = strtotime($data['start_time']);
            if ($startTs) {
                $reminderAt = date('Y-m-d H:i:s', $startTs - ($reminderMinutes * 60));
            }
        }

        $stmt = $this->db->prepare("
            UPDATE events SET
                category_id      = :category_id,
                title            = :title,
                description      = :description,
                start_time       = :start_time,
                end_time         = :end_time,
                location         = :location,
                priority         = :priority,
                status           = :status,
                reminder_at      = :reminder_at,
                reminder_minutes = :reminder_minutes,
                repeat_type      = :repeat_type,
                updated_at       = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");

        $ok = $stmt->execute([
            'id'               => $id,
            'user_id'          => $userId,
            'category_id'      => !empty($data['category_id']) ? (int)$data['category_id'] : null,
            'title'            => trim($data['title']),
            'description'      => !empty($data['description']) ? trim($data['description']) : null,
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'location'         => !empty($data['location']) ? trim($data['location']) : null,
            'priority'         => in_array($data['priority'] ?? '', ['low', 'medium', 'high'], true) ? $data['priority'] : 'medium',
            'status'           => in_array($data['status'] ?? '', ['pending', 'completed'], true) ? $data['status'] : 'pending',
            'reminder_at'      => $reminderAt,
            'reminder_minutes' => $reminderMinutes,
            'repeat_type'      => in_array($data['repeat_type'] ?? '', ['none', 'daily', 'weekly', 'monthly'], true) ? $data['repeat_type'] : 'none'
        ]);

        if ($ok) {
            self::resetReminder($userId, $id);
        }

        return $ok;
    }

    public function updateTime(int $id, int $userId, string $startTime, string $endTime): bool
    {
        $existing = $this->findById($id, $userId);
        $reminderAt = null;
        if ($existing && $existing['reminder_minutes'] !== null) {
            $startTs = strtotime($startTime);
            $reminderAt = date('Y-m-d H:i:s', $startTs - ((int)$existing['reminder_minutes'] * 60));
        }

        $stmt = $this->db->prepare("
            UPDATE events SET
                start_time  = :start_time,
                end_time    = :end_time,
                reminder_at = COALESCE(:reminder_at, reminder_at),
                updated_at  = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");

        $ok = $stmt->execute([
            'id'          => $id,
            'user_id'     => $userId,
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'reminder_at' => $reminderAt
        ]);

        if ($ok) {
            self::resetReminder($userId, $id);
        }

        return $ok;
    }

    public function updateStatus(int $id, int $userId, string $status): bool
    {
        $status = in_array($status, ['pending', 'completed'], true) ? $status : 'pending';
        $stmt = $this->db->prepare("
            UPDATE events SET
                status     = :status,
                updated_at = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        return $stmt->execute([
            'id'      => $id,
            'user_id' => $userId,
            'status'  => $status
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE events SET deleted_at = NOW(), updated_at = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        $ok = $stmt->execute(['id' => $id, 'user_id' => $userId]) && $stmt->rowCount() > 0;
        if ($ok) {
            self::resetReminder($userId, $id);
        }
        return $ok;
    }

    public function trashForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            WHERE e.user_id = :userId AND e.deleted_at IS NOT NULL
            ORDER BY e.deleted_at DESC, e.id DESC
        ");
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll();
    }

    public function restore(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE events SET deleted_at = NULL, updated_at = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NOT NULL
        ");
        return $stmt->execute(['id' => $id, 'user_id' => $userId]) && $stmt->rowCount() > 0;
    }

    public function forceDelete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM events
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NOT NULL
        ");
        $ok = $stmt->execute(['id' => $id, 'user_id' => $userId]) && $stmt->rowCount() > 0;
        if ($ok) {
            self::resetReminder($userId, $id);
            Notification::deleteByReferencePrefix($userId, 'event', $id);
        }
        return $ok;
    }

    public function emptyTrash(int $userId): bool
    {
        $idsStmt = $this->db->prepare("SELECT id FROM events WHERE user_id = :user_id AND deleted_at IS NOT NULL");
        $idsStmt->execute(['user_id' => $userId]);
        $evIds = array_map('intval', $idsStmt->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($evIds)) {
            $placeholders = [];
            $params = ['user_id' => $userId];
            foreach ($evIds as $idx => $evId) {
                $ph = 'eid_' . $idx;
                $placeholders[] = ':' . $ph;
                $params[$ph] = $evId;
            }
            $inClause = implode(', ', $placeholders);
            $this->db->prepare("DELETE FROM reminders WHERE user_id = :user_id AND event_id IN ({$inClause})")->execute($params);
            Notification::deleteByReferencePrefixBatch($userId, 'event', $evIds);
        }

        $stmt = $this->db->prepare("DELETE FROM events WHERE user_id = :user_id AND deleted_at IS NOT NULL");
        return $stmt->execute(['user_id' => $userId]);
    }

    public function getTodayEvents(int $userId): array
    {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color, COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            WHERE e.user_id = :userId AND e.deleted_at IS NULL
              AND DATE(e.start_time) <= :todayEnd
              AND DATE(e.end_time) >= :todayStart
            ORDER BY e.start_time ASC
        ");
        $stmt->execute([
            'userId'     => $userId,
            'todayStart' => $today,
            'todayEnd'   => $today
        ]);
        return $stmt->fetchAll();
    }

    public function getTodayUpcomingEvents(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color, COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            WHERE e.user_id = :userId
              AND e.deleted_at IS NULL
              AND e.status NOT IN ('completed', 'cancelled')
              AND DATE(e.start_time) = CURDATE()
              AND e.start_time > NOW()
            ORDER BY e.start_time ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUpcomingByDays(int $userId, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $stmt = $this->db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color, COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            WHERE e.user_id = :userId
              AND e.deleted_at IS NULL
              AND DATE(e.start_time) > CURDATE()
              AND DATE(e.start_time) <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
            ORDER BY e.start_time ASC
        ");
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUpcomingEvents(int $userId, int $limit = 5): array
    {
        $limit = max(1, min(100, $limit));
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color, COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            WHERE e.user_id = :userId AND e.deleted_at IS NULL
              AND e.start_time >= :now
            ORDER BY e.start_time ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':now', $now, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Lấy các sự kiện cần kích hoạt nhắc nhở cho người dùng
     */
    public static function getPendingReminders(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            LEFT JOIN reminders r ON r.event_id = e.id AND r.user_id = e.user_id AND r.is_sent = 1
            WHERE e.user_id = :userId
              AND e.deleted_at IS NULL
              AND e.status NOT IN ('completed', 'cancelled')
              AND (
                (e.reminder_at IS NOT NULL AND e.reminder_at <= NOW())
                OR (e.reminder_minutes IS NOT NULL AND DATE_SUB(e.start_time, INTERVAL e.reminder_minutes MINUTE) <= NOW())
              )
              AND e.start_time >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
              AND r.id IS NULL
            ORDER BY e.start_time ASC
            LIMIT 10
        ");
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Xóa trạng thái đã nhắc nhở khi sự kiện được cập nhật giờ hoặc cấu hình nhắc hẹn
     */
    public static function resetReminder(int $userId, int $eventId): void
    {
        if ($userId <= 0 || $eventId <= 0) {
            return;
        }
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM reminders WHERE user_id = :uid AND event_id = :eid");
        $stmt->execute([
            'uid' => $userId,
            'eid' => $eventId
        ]);
    }

    /**
     * Đánh dấu đã nhắc nhở sự kiện để không gửi trùng lặp (atomic chống 2 tab poll song song)
     */
    public static function markReminderSent(int $eventId, int $userId): bool
    {
        if ($eventId <= 0 || $userId <= 0) {
            return false;
        }
        $db = Database::getConnection();

        $upd = $db->prepare("
            UPDATE reminders
            SET is_sent = 1, sent_at = NOW()
            WHERE user_id = :uid AND event_id = :eid AND is_sent = 0
        ");
        $upd->execute([
            'uid' => $userId,
            'eid' => $eventId
        ]);
        if ($upd->rowCount() > 0) {
            return true;
        }

        $ins = $db->prepare("
            INSERT IGNORE INTO reminders (user_id, event_id, remind_at, is_sent, sent_at, created_at)
            VALUES (:user_id, :event_id, NOW(), 1, NOW(), NOW())
        ");
        $ins->execute([
            'user_id'  => $userId,
            'event_id' => $eventId
        ]);
        return $ins->rowCount() > 0;
    }

    public function getStats(int $userId): array
    {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $inTwoHours = date('Y-m-d H:i:s', strtotime('+2 hours'));

        // 1. Lịch trong ngày
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM events 
            WHERE user_id = :userId 
              AND DATE(start_time) <= :todayEnd 
              AND DATE(end_time) >= :todayStart
        ");
        $stmt->execute(['userId' => $userId, 'todayStart' => $today, 'todayEnd' => $today]);
        $todayCount = (int)$stmt->fetchColumn();

        // 2. Sắp diễn ra (trong 2 giờ tới hoặc các sự kiện sắp tới hôm nay)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM events 
            WHERE user_id = :userId 
              AND start_time >= :now 
              AND start_time <= :inTwoHours
        ");
        $stmt->execute(['userId' => $userId, 'now' => $now, 'inTwoHours' => $inTwoHours]);
        $upcomingCount = (int)$stmt->fetchColumn();

        if ($upcomingCount === 0) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM events 
                WHERE user_id = :userId 
                  AND start_time >= :now 
                  AND DATE(start_time) = :today
            ");
            $stmt->execute(['userId' => $userId, 'now' => $now, 'today' => $today]);
            $upcomingCount = (int)$stmt->fetchColumn();
        }

        // 3. Chưa hoàn thành
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM events 
            WHERE user_id = :userId AND status = 'pending'
        ");
        $stmt->execute(['userId' => $userId]);
        $pendingCount = (int)$stmt->fetchColumn();

        // 4. Đã hoàn thành
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM events 
            WHERE user_id = :userId AND status = 'completed'
        ");
        $stmt->execute(['userId' => $userId]);
        $completedCount = (int)$stmt->fetchColumn();

        return [
            'today_count'     => $todayCount,
            'upcoming_count'  => $upcomingCount,
            'pending_count'   => $pendingCount,
            'completed_count' => $completedCount
        ];
    }

    public function getWeeklyStats(int $userId): array
    {
        $monday = date('Y-m-d', strtotime('monday this week'));
        $dayLabels = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
        $completed = [];
        $pending = [];

        for ($i = 0; $i < 7; $i++) {
            $day = date('Y-m-d', strtotime("$monday +$i days"));
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS comp,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pend
                FROM events
                WHERE user_id = :userId
                  AND DATE(start_time) = :day
            ");
            $stmt->execute(['userId' => $userId, 'day' => $day]);
            $row = $stmt->fetch();
            $completed[] = (int)($row['comp'] ?? 0);
            $pending[] = (int)($row['pend'] ?? 0);
        }

        return [
            'labels'    => $dayLabels,
            'completed' => $completed,
            'pending'   => $pending,
            'total'     => array_sum($completed) + array_sum($pending)
        ];
    }

    /**
     * Tìm kiếm trực tiếp (Live Search) theo tiêu đề, mô tả, địa điểm, danh mục
     * Hỗ trợ không phân biệt hoa thường và tiếng Việt có dấu / không dấu
     */
    public function search(int $userId, string $keyword, int $limit = 15): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT e.*, c.name AS category_name, c.color AS category_color, COALESCE(NULLIF(c.icon, ''), 'fa-folder') AS category_icon
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.id AND c.user_id = e.user_id
            WHERE e.user_id = :userId AND e.deleted_at IS NULL
            ORDER BY e.start_time DESC
        ");
        $stmt->execute(['userId' => $userId]);
        $events = $stmt->fetchAll();

        $normQuery = self::removeVietnameseTones($keyword);
        $scored = [];

        foreach ($events as $event) {
            $title = $event['title'] ?? '';
            $desc  = $event['description'] ?? '';
            $loc   = $event['location'] ?? '';
            $cat   = $event['category_name'] ?? '';

            $titleLower = mb_strtolower($title, 'UTF-8');
            $titleNorm  = self::removeVietnameseTones($title);

            // Ghép 4 trường tìm kiếm: tiêu đề, mô tả, địa điểm, danh mục
            $haystackOriginal = mb_strtolower($title . ' ' . $desc . ' ' . $loc . ' ' . $cat, 'UTF-8');
            $haystackNormalized = self::removeVietnameseTones($haystackOriginal);

            $score = 0;
            // 1. Khớp ngay đầu tiêu đề (Ưu tiên cao nhất -> Hiện lên đầu tiên)
            if (str_starts_with($titleLower, mb_strtolower($keyword, 'UTF-8')) || str_starts_with($titleNorm, $normQuery)) {
                $score = 3;
            } elseif (mb_stripos($titleLower, $keyword) !== false || mb_stripos($titleNorm, $normQuery) !== false) {
                // 2. Khớp trong tiêu đề
                $score = 2;
            } elseif (mb_stripos($haystackOriginal, $keyword) !== false || mb_stripos($haystackNormalized, $normQuery) !== false) {
                // 3. Khớp mô tả, địa điểm hoặc danh mục
                $score = 1;
            }

            if ($score > 0) {
                $event['_score'] = $score;
                $scored[] = $event;
            }
        }

        // Sắp xếp: Điểm khớp cao nhất lên đầu tiên, sau đó xếp theo thời gian mới nhất
        usort($scored, function ($a, $b) {
            if ($b['_score'] !== $a['_score']) {
                return $b['_score'] - $a['_score'];
            }
            return strtotime($b['start_time']) - strtotime($a['start_time']);
        });

        return array_slice($scored, 0, $limit);
    }

    /**
     * Chuyển đổi chuỗi tiếng Việt có dấu thành không dấu chữ thường
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
