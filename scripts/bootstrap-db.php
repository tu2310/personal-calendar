<?php
/**
 * Tự động kiểm tra, khởi tạo database và nâng cấp schema an toàn khi chạy `npm run dev`
 */
require_once __DIR__ . '/../app/Core/Database.php';

$cfg = Database::config();
$host = $cfg['host'];
$port = $cfg['port'];
$dbname = preg_replace('/[^a-zA-Z0-9_]/', '', $cfg['dbname']) ?: 'personal_calendar';
$user = $cfg['username'];
$pass = $cfg['password'];

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "[db] Khong ket noi duoc MySQL tai {$host}:{$port} (user '{$user}'): " . $e->getMessage() . "\n");
    fwrite(STDERR, "[db] Goi y: Bat MySQL trong Laragon/XAMPP, hoac copy .env.example thanh .env de cau hinh DB_PORT / DB_PASS.\n");
    exit(1);
}

$checkDb = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :db");
$checkDb->execute(['db' => $dbname]);
$dbExists = (bool)$checkDb->fetchColumn();

$hasUsersTable = false;
if ($dbExists) {
    $pdo->exec("USE `{$dbname}`");
    $tblStmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $hasUsersTable = (bool)$tblStmt->fetchColumn();
}

function seedRecentHabitLogsIfEmpty(PDO $pdo): void
{
    $recentCount = (int)$pdo->query("SELECT COUNT(*) FROM habit_logs WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    if ($recentCount > 0) {
        return;
    }
    $habits = $pdo->query("SELECT id FROM habits WHERE user_id = 1 ORDER BY id ASC LIMIT 4")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($habits)) {
        return;
    }
    $patterns = [
        [0, 1, 2, 3, 5],
        [0, 1, 2, 3, 4],
        [1, 2, 3, 4, 5],
        [0, 2, 4, 6],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO habit_logs (habit_id, user_id, log_date) VALUES (?, 1, ?)");
    foreach ($habits as $idx => $hid) {
        $days = $patterns[$idx % count($patterns)];
        foreach ($days as $ago) {
            $ins->execute([(int)$hid, date('Y-m-d', strtotime("-{$ago} days"))]);
        }
    }
}

if (!$dbExists || !$hasUsersTable) {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");

    $sqlFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'personal_calendar.sql';
    if (!is_file($sqlFile)) {
        fwrite(STDERR, "[db] Khong tim thay file database/personal_calendar.sql.\n");
        exit(1);
    }

    $rawSql = file_get_contents($sqlFile);
    // Strip single-line SQL comments before splitting statements at end-of-line semicolons
    $cleanLines = [];
    foreach (preg_split('/\r\n|\r|\n/', $rawSql) as $line) {
        $trim = ltrim($line);
        if ($trim === '' || str_starts_with($trim, '--')) {
            continue;
        }
        $cleanLines[] = $line;
    }
    $joined = implode("\n", $cleanLines);
    $statements = preg_split('/;\s*(?:\n|$)/', $joined);

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        if (preg_match('/^(CREATE DATABASE|USE)\s+/i', $stmt)) {
            continue;
        }
        $pdo->exec($stmt);
    }

    seedRecentHabitLogsIfEmpty($pdo);
    echo "[db] Da khoi tao database '{$dbname}' va du lieu mau thanh cong (tai khoan: admin / admin).\n";
    exit(0);
}

// Existing DB: non-destructive schema sync
$pdo->exec("USE `{$dbname}`");

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }
}

ensureColumn($pdo, 'categories', 'icon', "`icon` VARCHAR(50) NOT NULL DEFAULT 'fa-folder' AFTER `color`");
ensureColumn($pdo, 'events', 'reminder_minutes', "`reminder_minutes` INT NULL DEFAULT NULL AFTER `reminder_at`");
ensureColumn($pdo, 'events', 'deleted_at', "`deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`");
ensureColumn($pdo, 'tasks', 'deleted_at', "`deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`");

$pdo->exec("
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

$pdo->exec("
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

$user1Exists = (bool)$pdo->query("SELECT id FROM users WHERE id = 1")->fetchColumn();
$habitCount = (int)$pdo->query("SELECT COUNT(*) FROM habits")->fetchColumn();
if ($user1Exists && $habitCount === 0) {
    $insH = $pdo->prepare("INSERT INTO habits (user_id, name, description, frequency, target_per_week, color) VALUES (1, ?, ?, ?, ?, ?)");
    $seeds = [
        ['Tập thể dục', '30 phút vận động buổi sáng', 'daily', 7, '#15803d'],
        ['Đọc sách', 'Ít nhất 20 phút trước khi ngủ', 'daily', 7, '#c2410c'],
        ['Uống đủ 2L nước', 'Chia đều trong ngày', 'daily', 7, '#2563eb'],
        ['Học ngoại ngữ', 'Ôn từ vựng và nghe 30 phút', 'weekly', 5, '#b45309'],
    ];
    foreach ($seeds as $s) {
        $insH->execute($s);
    }
    seedRecentHabitLogsIfEmpty($pdo);
}

echo "[db] Database '{$dbname}' da san sang.\n";
