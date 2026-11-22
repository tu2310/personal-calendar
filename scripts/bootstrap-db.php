<?php
/**
 * Tự động kiểm tra, khởi tạo database và nâng cấp schema an toàn khi chạy `npm run dev`
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

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
    fwrite(STDERR, "[db] Khong ket noi duoc toi MySQL.\n");
    fwrite(STDERR, "[db] Goi y: Bat MySQL trong Laragon/XAMPP, hoac copy .env.example thanh .env de cau hinh ket noi DB.\n");
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
ensureColumn($pdo, 'events', 'repeat_type', "`repeat_type` ENUM('none','daily','weekly','monthly') NOT NULL DEFAULT 'none' AFTER `reminder_minutes`");
ensureColumn($pdo, 'events', 'deleted_at', "`deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`");
ensureColumn($pdo, 'tasks', 'start_time', "`start_time` TIME NULL DEFAULT NULL AFTER `due_date`");
ensureColumn($pdo, 'tasks', 'end_time', "`end_time` TIME NULL DEFAULT NULL AFTER `start_time`");
ensureColumn($pdo, 'tasks', 'end_date', "`end_date` DATE NULL DEFAULT NULL AFTER `end_time`");
ensureColumn($pdo, 'tasks', 'repeat_type', "`repeat_type` ENUM('none','daily','weekly','monthly') NOT NULL DEFAULT 'none' AFTER `end_date`");
ensureColumn($pdo, 'tasks', 'deleted_at', "`deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`");

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table"
    );
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name"
    );
    $stmt->execute([
        'table'      => $table,
        'index_name' => $indexName,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensureIndex(PDO $pdo, string $table, string $indexName, array $columns, bool $unique = false): void
{
    $allowedTables = ['notifications', 'reminders'];
    $allowedIndexes = [
        'idx_notif_user_ref_created',
        'idx_notif_user_read_created',
        'uniq_reminders_user_event',
    ];
    $allowedColumns = [
        'user_id',
        'reference_type',
        'reference_id',
        'created_at',
        'is_read',
        'event_id',
    ];
    if (empty($columns) || !in_array($table, $allowedTables, true) || !in_array($indexName, $allowedIndexes, true)) {
        throw new InvalidArgumentException('Invalid table, index, or empty column list.');
    }
    $quotedCols = [];
    foreach ($columns as $col) {
        if (!in_array($col, $allowedColumns, true)) {
            throw new InvalidArgumentException('Invalid column name for index.');
        }
        $quotedCols[] = "`{$col}`";
    }
    if (!indexExists($pdo, $table, $indexName)) {
        $keyword = $unique ? 'ADD UNIQUE KEY' : 'ADD INDEX';
        $colsSql = implode(', ', $quotedCols);
        $pdo->exec("ALTER TABLE `{$table}` {$keyword} `{$indexName}` ({$colsSql})");
    }
}

if (tableExists($pdo, 'notifications')) {
    $colStmt = $pdo->prepare(
        "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'reference_type'"
    );
    $colStmt->execute();
    $refCol = $colStmt->fetch();

    if (!$refCol) {
        $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `reference_type` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
    } else {
        $dataType = strtolower((string)($refCol['DATA_TYPE'] ?? ''));
        $maxLen = (int)($refCol['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
        $isNullable = strtoupper((string)($refCol['IS_NULLABLE'] ?? ''));
        $defaultVal = $refCol['COLUMN_DEFAULT'];
        $hasNonNullDefault = $defaultVal !== null && strtoupper((string)$defaultVal) !== 'NULL';

        if ($dataType !== 'varchar' || $maxLen < 50 || $isNullable !== 'YES' || $hasNonNullDefault) {
            $targetLen = $dataType === 'varchar' ? max(50, $maxLen) : 50;
            $pdo->exec("ALTER TABLE `notifications` MODIFY COLUMN `reference_type` VARCHAR({$targetLen}) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
        }
    }

    ensureIndex($pdo, 'notifications', 'idx_notif_user_ref_created', ['user_id', 'reference_type', 'reference_id', 'created_at']);
    ensureIndex($pdo, 'notifications', 'idx_notif_user_read_created', ['user_id', 'is_read', 'created_at']);
}

if (tableExists($pdo, 'reminders')) {
    if (!indexExists($pdo, 'reminders', 'uniq_reminders_user_event')) {
        $pdo->exec("
            DELETE r1
            FROM `reminders` r1
            INNER JOIN `reminders` r2
                ON r1.`user_id` = r2.`user_id`
               AND r1.`event_id` = r2.`event_id`
               AND r1.`id` < r2.`id`
        ");
        ensureIndex($pdo, 'reminders', 'uniq_reminders_user_event', ['user_id', 'event_id'], true);
    }
}

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
