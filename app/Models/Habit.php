<?php
require_once __DIR__ . '/../Core/Database.php';

class Habit
{
    private PDO $db;
    private static bool $schemaChecked = false;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `habits` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `user_id` int unsigned NOT NULL,
              `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
              `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `frequency` enum('daily','weekly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
              `target_per_week` tinyint unsigned NOT NULL DEFAULT '7',
              `color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#c2410c',
              `is_archived` tinyint(1) NOT NULL DEFAULT '0',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_habits_user` (`user_id`,`is_archived`),
              CONSTRAINT `fk_habits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `habit_logs` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `habit_id` int unsigned NOT NULL,
              `user_id` int unsigned NOT NULL,
              `log_date` date NOT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_habit_date` (`habit_id`,`log_date`),
              KEY `idx_habit_logs_user_date` (`user_id`,`log_date`),
              CONSTRAINT `fk_habit_logs_habit` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_habit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function getRecentDaysHeader(int $days = 7): array
    {
        $days = max(1, $days);
        $weekdayMap = [
            1 => 'T2',
            2 => 'T3',
            3 => 'T4',
            4 => 'T5',
            5 => 'T6',
            6 => 'T7',
            7 => 'CN'
        ];
        $today = date('Y-m-d');
        $header = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $ts = strtotime("-{$i} days");
            $dateStr = date('Y-m-d', $ts);
            $n = (int)date('N', $ts);
            $header[] = [
                'date'     => $dateStr,
                'day_num'  => date('d', $ts),
                'weekday'  => $weekdayMap[$n] ?? '',
                'is_today' => ($dateStr === $today)
            ];
        }

        return $header;
    }

    public function allForUser(int $userId, int $days = 7): array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, name, description, frequency, target_per_week, color, is_archived, created_at, updated_at
            FROM habits
            WHERE user_id = :userId AND is_archived = 0
            ORDER BY id ASC
        ");
        $stmt->execute(['userId' => $userId]);
        $habits = $stmt->fetchAll();

        if (empty($habits)) {
            return [];
        }

        $logStmt = $this->db->prepare("
            SELECT habit_id, log_date
            FROM habit_logs
            WHERE user_id = :userId
            ORDER BY log_date DESC
        ");
        $logStmt->execute(['userId' => $userId]);
        $allLogs = $logStmt->fetchAll();

        $logsByHabit = [];
        foreach ($allLogs as $log) {
            $hid = (int)$log['habit_id'];
            $logsByHabit[$hid][$log['log_date']] = true;
        }

        $daysHeader = $this->getRecentDaysHeader($days);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $windowStart = $daysHeader[0]['date'] ?? $today;

        foreach ($habits as &$habit) {
            $hid = (int)$habit['id'];
            $dateSet = $logsByHabit[$hid] ?? [];
            $habit['completed_today'] = isset($dateSet[$today]);

            // Current streak: consecutive days ending today or yesterday
            $currentStreak = 0;
            $cursor = isset($dateSet[$today]) ? $today : (isset($dateSet[$yesterday]) ? $yesterday : null);
            if ($cursor !== null) {
                $ts = strtotime($cursor);
                while (isset($dateSet[date('Y-m-d', $ts)])) {
                    $currentStreak++;
                    $ts = strtotime('-1 day', $ts);
                }
            }
            $habit['current_streak'] = $currentStreak;

            // Best streak across all historical dates
            $bestStreak = 0;
            if (!empty($dateSet)) {
                $dates = array_keys($dateSet);
                sort($dates);
                $run = 0;
                $prevTs = null;
                foreach ($dates as $d) {
                    $ts = strtotime($d);
                    if ($prevTs !== null && (int)round(($ts - $prevTs) / 86400) === 1) {
                        $run++;
                    } else {
                        $run = 1;
                    }
                    if ($run > $bestStreak) {
                        $bestStreak = $run;
                    }
                    $prevTs = $ts;
                }
            }
            $habit['best_streak'] = $bestStreak;

            // Recent days matrix and weekly count
            $weekDone = 0;
            $recentDays = [];
            foreach ($daysHeader as $col) {
                $checked = isset($dateSet[$col['date']]);
                if ($checked && $col['date'] >= $windowStart && $col['date'] <= $today) {
                    $weekDone++;
                }
                $recentDays[] = [
                    'date'     => $col['date'],
                    'day_num'  => $col['day_num'],
                    'weekday'  => $col['weekday'],
                    'is_today' => $col['is_today'],
                    'checked'  => $checked
                ];
            }

            $target = max(1, (int)$habit['target_per_week']);
            $habit['week_done'] = $weekDone;
            $habit['week_rate'] = min(100, (int)round(($weekDone / $target) * 100));
            $habit['total_done'] = count($dateSet);
            $habit['recent_days'] = $recentDays;
        }
        unset($habit);

        return $habits;
    }

    public function getSummary(array $habits): array
    {
        $total = count($habits);
        $doneToday = 0;
        $longestCurrent = 0;
        $bestEver = 0;
        $sumWeekRate = 0;

        foreach ($habits as $h) {
            if (!empty($h['completed_today'])) {
                $doneToday++;
            }
            if (($h['current_streak'] ?? 0) > $longestCurrent) {
                $longestCurrent = (int)$h['current_streak'];
            }
            if (($h['best_streak'] ?? 0) > $bestEver) {
                $bestEver = (int)$h['best_streak'];
            }
            $sumWeekRate += (int)($h['week_rate'] ?? 0);
        }

        return [
            'total_habits'           => $total,
            'done_today'             => $doneToday,
            'today_rate'             => $total > 0 ? (int)round(($doneToday / $total) * 100) : 0,
            'longest_current_streak' => $longestCurrent,
            'best_streak_ever'       => $bestEver,
            'weekly_avg_rate'        => $total > 0 ? (int)round($sumWeekRate / $total) : 0
        ];
    }

    public function getWeeklySeries(int $userId, int $days = 7): array
    {
        $header = $this->getRecentDaysHeader($days);
        $startDate = $header[0]['date'] ?? date('Y-m-d');
        $endDate = $header[count($header) - 1]['date'] ?? date('Y-m-d');

        $totalStmt = $this->db->prepare("
            SELECT COUNT(*) FROM habits WHERE user_id = :userId AND is_archived = 0
        ");
        $totalStmt->execute(['userId' => $userId]);
        $totalHabits = (int)$totalStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT l.log_date, COUNT(*) AS cnt
            FROM habit_logs l
            INNER JOIN habits h ON l.habit_id = h.id
            WHERE l.user_id = :userId
              AND h.is_archived = 0
              AND l.log_date BETWEEN :startDate AND :endDate
            GROUP BY l.log_date
        ");
        $stmt->execute([
            'userId'    => $userId,
            'startDate' => $startDate,
            'endDate'   => $endDate
        ]);

        $countsByDate = [];
        foreach ($stmt->fetchAll() as $row) {
            $countsByDate[$row['log_date']] = (int)$row['cnt'];
        }

        $labels = [];
        $dates = [];
        $counts = [];
        foreach ($header as $col) {
            $labels[] = $col['weekday'];
            $dates[] = $col['date'];
            $counts[] = $countsByDate[$col['date']] ?? 0;
        }

        return [
            'labels'       => $labels,
            'dates'        => $dates,
            'counts'       => $counts,
            'total_habits' => $totalHabits
        ];
    }

    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, name, description, frequency, target_per_week, color, is_archived, created_at, updated_at
            FROM habits
            WHERE id = :id AND user_id = :userId AND is_archived = 0
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'userId' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $frequency = ($data['frequency'] ?? 'daily') === 'weekly' ? 'weekly' : 'daily';
        $target = $frequency === 'daily' ? 7 : min(7, max(1, (int)($data['target_per_week'] ?? 5)));
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'] ?? '') ? $data['color'] : '#c2410c';

        $stmt = $this->db->prepare("
            INSERT INTO habits (user_id, name, description, frequency, target_per_week, color, created_at, updated_at)
            VALUES (:userId, :name, :description, :frequency, :target, :color, NOW(), NOW())
        ");
        $stmt->execute([
            'userId'      => $userId,
            'name'        => $data['name'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'frequency'   => $frequency,
            'target'      => $target,
            'color'       => $color
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $frequency = ($data['frequency'] ?? 'daily') === 'weekly' ? 'weekly' : 'daily';
        $target = $frequency === 'daily' ? 7 : min(7, max(1, (int)($data['target_per_week'] ?? 5)));
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'] ?? '') ? $data['color'] : '#c2410c';

        $stmt = $this->db->prepare("
            UPDATE habits SET
                name            = :name,
                description     = :description,
                frequency       = :frequency,
                target_per_week = :target,
                color           = :color,
                updated_at      = NOW()
            WHERE id = :id AND user_id = :userId AND is_archived = 0
        ");
        return $stmt->execute([
            'id'          => $id,
            'userId'      => $userId,
            'name'        => $data['name'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'frequency'   => $frequency,
            'target'      => $target,
            'color'       => $color
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM habits
            WHERE id = :id AND user_id = :userId
        ");
        return $stmt->execute(['id' => $id, 'userId' => $userId]);
    }

    public function toggleLog(int $habitId, int $userId, string $logDate): ?array
    {
        $habit = $this->findById($habitId, $userId);
        if (!$habit) {
            return null;
        }

        $checkStmt = $this->db->prepare("
            SELECT id FROM habit_logs
            WHERE habit_id = :habitId AND user_id = :userId AND log_date = :logDate
            LIMIT 1
        ");
        $checkStmt->execute([
            'habitId' => $habitId,
            'userId'  => $userId,
            'logDate' => $logDate
        ]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            $delStmt = $this->db->prepare("
                DELETE FROM habit_logs
                WHERE id = :id AND user_id = :userId
            ");
            $delStmt->execute(['id' => (int)$existingId, 'userId' => $userId]);
            return ['checked' => false];
        }

        $insStmt = $this->db->prepare("
            INSERT IGNORE INTO habit_logs (habit_id, user_id, log_date, created_at)
            VALUES (:habitId, :userId, :logDate, NOW())
        ");
        $insStmt->execute([
            'habitId' => $habitId,
            'userId'  => $userId,
            'logDate' => $logDate
        ]);
        return ['checked' => true];
    }
}
