<?php
declare(strict_types=1);

/**
 * Kiểm thử tích hợp toàn diện các luồng thông báo của Personal Calendar:
 * 1. Nhắc hẹn tự động (NotificationController::syncUserReminders)
 * 2. Đầy đủ luồng CRUD (Event, Task, Habit, Profile, System Test) & dọn thông báo mồ côi
 * 3. Trung tâm thông báo (lọc 4 tab, time_ago tiếng Việt, đánh dấu đọc/xóa) & chống IDOR
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/app/Core/Database.php';
require_once $rootDir . '/app/Core/Auth.php';
require_once $rootDir . '/app/Models/User.php';
require_once $rootDir . '/app/Models/Event.php';
require_once $rootDir . '/app/Models/Task.php';
require_once $rootDir . '/app/Models/Habit.php';
require_once $rootDir . '/app/Models/Notification.php';
require_once $rootDir . '/app/Controllers/NotificationController.php';

$db = Database::getConnection();
$userModel = new User();
$eventModel = new Event();
$taskModel = new Task();
$habitModel = new Habit();
$notifModel = new Notification();
$notifController = new NotificationController();

$passCount = 0;
$failCount = 0;
$failedCases = [];

function assertTest(string $name, bool $condition, string $detail = ''): void
{
    global $passCount, $failCount, $failedCases;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$name}\n";
    } else {
        $failCount++;
        $msg = $detail !== '' ? "{$name}: {$detail}" : $name;
        $failedCases[] = $msg;
        echo " [FAIL] {$msg}\n";
    }
}

function invokeControllerAction(
    string $controllerFile,
    string $controllerClass,
    string $method,
    int $userId,
    string $userName,
    string $userEmail,
    string $httpMethod = 'POST',
    array $postData = [],
    array $getData = []
): array {
    $rootDir = dirname(__DIR__);
    $phpBin = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';

    $payload = [
        'root'           => $rootDir,
        'controllerFile' => $controllerFile,
        'class'          => $controllerClass,
        'method'         => $method,
        'user'           => [
            'id'     => $userId,
            'name'   => $userName,
            'email'  => $userEmail,
            'avatar' => null,
        ],
        'httpMethod'     => $httpMethod,
        'post'           => $postData,
        'get'            => $getData,
    ];
    $encodedPayload = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));

    $childScript = <<<PHP
<?php
\$cfg = json_decode(base64_decode('{$encodedPayload}'), true, 512, JSON_THROW_ON_ERROR);
require_once \$cfg['root'] . '/app/Core/Database.php';
require_once \$cfg['root'] . '/app/Core/Auth.php';
require_once \$cfg['root'] . '/app/Controllers/' . \$cfg['controllerFile'];
Auth::init();
\$_SESSION['user'] = \$cfg['user'];
\$csrf = Auth::csrfToken();
\$_SERVER['REQUEST_METHOD'] = \$cfg['httpMethod'];
\$_GET = \$cfg['get'];
\$_POST = \$cfg['post'];
if (\$cfg['httpMethod'] === 'POST' && !array_key_exists('csrf_token', \$_POST)) {
    \$_POST['csrf_token'] = \$csrf;
}
\$ctrl = new \$cfg['class']();
\$fn = \$cfg['method'];
\$ctrl->\$fn();
PHP;

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open([$phpBin], $descriptors, $pipes, $rootDir);
    if (!is_resource($proc)) {
        return [
            'exit_code' => -1,
            'stdout'    => '',
            'stderr'    => 'proc_open failed with binary: ' . $phpBin,
            'json'      => null,
        ];
    }

    fwrite($pipes[0], $childScript);
    fclose($pipes[0]);
    $stdout = (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = (string)stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    $decoded = json_decode($stdout, true);
    return [
        'exit_code' => $exitCode,
        'stdout'    => $stdout,
        'stderr'    => trim($stderr),
        'json'      => is_array($decoded) ? $decoded : null,
    ];
}

function countNotificationsByRef(PDO $db, int $userId, string $refType, ?int $refId = null, bool $todayOnly = false): int
{
    $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND reference_type = :rtype";
    $params = ['uid' => $userId, 'rtype' => $refType];

    if ($refId !== null) {
        $sql .= " AND reference_id = :rid";
        $params['rid'] = $refId;
    } else {
        $sql .= " AND reference_id IS NULL";
    }

    if ($todayOnly) {
        $sql .= " AND DATE(created_at) = CURDATE()";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function countReminders(PDO $db, int $userId, int $eventId): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM reminders WHERE user_id = :uid AND event_id = :eid");
    $stmt->execute(['uid' => $userId, 'eid' => $eventId]);
    return (int)$stmt->fetchColumn();
}

function cleanupTestUsers(PDO $db, array $userIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn(int $id) => $id > 0)));
    if (empty($ids)) {
        return;
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tables = ['reminders', 'notifications', 'habit_logs', 'habits', 'tasks', 'events', 'categories'];
    foreach ($tables as $tbl) {
        $stmt = $db->prepare("DELETE FROM `{$tbl}` WHERE user_id IN ({$ph})");
        $stmt->execute($ids);
    }
    $stmtUser = $db->prepare("DELETE FROM `users` WHERE id IN ({$ph})");
    $stmtUser->execute($ids);
}

$testUserIds = [];

echo "================================================================\n";
echo "KIỂM THỬ TÍCH HỢP CÁC LUỒNG THÔNG BÁO - PERSONAL CALENDAR\n";
echo "================================================================\n\n";

try {
    $suffix = bin2hex(random_bytes(4));
    $userAName = 'Test Notif A';
    $userAEmail = "notif_test_a_{$suffix}@example.test";
    $userBName = 'Test Notif B';
    $userBEmail = "notif_test_b_{$suffix}@example.test";

    $userAId = $userModel->create($userAName, $userAEmail, 'TestPass123!');
    $userBId = $userModel->create($userBName, $userBEmail, 'TestPass123!');
    $testUserIds = [$userAId, $userBId];

    assertTest('0. Tạo 2 tài khoản test tạm cô lập dữ liệu', $userAId > 0 && $userBId > 0 && $userAId !== $userBId);

    // =========================================================================
    // 1. NHẮC HẸN TỰ ĐỘNG (NotificationController::syncUserReminders)
    // =========================================================================
    echo "\n--- 1. Nhắc hẹn tự động (syncUserReminders) ---\n";

    // 1.1 Sự kiện đến giờ nhắc (event_reminder): start_time 5 phút trước (trong cửa sổ 2h, <= NOW nên không dính event_today)
    $evReminderId = $eventModel->create($userAId, [
        'title'            => 'Họp nhắc hẹn tự động',
        'start_time'       => date('Y-m-d H:i:s', time() - 300),
        'end_time'         => date('Y-m-d H:i:s', time() + 3300),
        'location'         => 'Phòng A1',
        'priority'         => 'high',
        'status'           => 'pending',
        'reminder_minutes' => 15,
        'reminder_at'      => date('Y-m-d H:i:s', time() - 1200),
    ]);

    // Sự kiện đã xóa mềm (KHÔNG được sinh nhắc lịch / hôm nay)
    $evSoftDeletedRemId = $eventModel->create($userAId, [
        'title'            => 'Sự kiện xóa mềm nhắc lịch',
        'start_time'       => date('Y-m-d H:i:s', time() - 300),
        'end_time'         => date('Y-m-d H:i:s', time() + 3300),
        'status'           => 'pending',
        'reminder_minutes' => 15,
        'reminder_at'      => date('Y-m-d H:i:s', time() - 1200),
    ]);
    $eventModel->delete($evSoftDeletedRemId, $userAId);

    // Thời gian sắp tới trong ngày hôm nay (tránh vượt qua 23:59:59 nếu test chạy sát nửa đêm)
    $todayRemainingSec = strtotime(date('Y-m-d 23:59:59')) - time();
    $todayOffsetSec = $todayRemainingSec > 120
        ? min(1800, (int)floor($todayRemainingSec / 2))
        : min(1800, max(5, (int)floor($todayRemainingSec / 2)));
    $todayUpcomingStart = date('Y-m-d H:i:s', time() + $todayOffsetSec);
    $todayUpcomingEnd = date('Y-m-d H:i:s', time() + $todayOffsetSec + 1800);

    // Sự kiện đã hoàn thành (completed) đến giờ nhắc & trong hôm nay (KHÔNG được sinh nhắc lịch / hôm nay)
    $evCompletedId = $eventModel->create($userAId, [
        'title'            => 'Sự kiện đã hoàn thành',
        'start_time'       => $todayUpcomingStart,
        'end_time'         => $todayUpcomingEnd,
        'status'           => 'completed',
        'reminder_minutes' => 15,
        'reminder_at'      => date('Y-m-d H:i:s', time() - 600),
    ]);

    // Sự kiện xóa mềm sắp diễn ra hôm nay (KHÔNG được sinh event_today)
    $evSoftDeletedTodayId = $eventModel->create($userAId, [
        'title'      => 'Sự kiện hôm nay đã xóa mềm',
        'start_time' => $todayUpcomingStart,
        'end_time'   => $todayUpcomingEnd,
        'status'     => 'pending',
    ]);
    $eventModel->delete($evSoftDeletedTodayId, $userAId);

    // 1.2 Sự kiện sắp diễn ra hôm nay (event_today), không cấu hình reminder riêng
    $evTodayId = $eventModel->create($userAId, [
        'title'      => 'Lịch trình hôm nay quan trọng',
        'start_time' => $todayUpcomingStart,
        'end_time'   => $todayUpcomingEnd,
        'location'   => 'Online Meet',
        'status'     => 'pending',
    ]);

    // Sự kiện ngày mai (KHÔNG được sinh event_today)
    $evTomorrowId = $eventModel->create($userAId, [
        'title'      => 'Lịch trình ngày mai',
        'start_time' => date('Y-m-d H:i:s', strtotime('+1 day 09:00:00')),
        'end_time'   => date('Y-m-d H:i:s', strtotime('+1 day 10:00:00')),
        'status'     => 'pending',
    ]);

    // 1.3 Công việc quá hạn, đến hạn hôm nay, khẩn cấp + công việc hoàn thành/xóa mềm
    $taskOverdueId = $taskModel->create($userAId, [
        'title'    => 'Công việc quá hạn test',
        'priority' => 'medium',
        'status'   => 'pending',
        'due_date' => date('Y-m-d', strtotime('-2 days')),
    ]);
    $taskDueTodayId = $taskModel->create($userAId, [
        'title'    => 'Công việc hạn hôm nay test',
        'priority' => 'high',
        'status'   => 'pending',
        'due_date' => date('Y-m-d'),
    ]);
    $taskUrgentId = $taskModel->create($userAId, [
        'title'    => 'Công việc khẩn cấp test',
        'priority' => 'urgent',
        'status'   => 'pending',
        'due_date' => date('Y-m-d', strtotime('+3 days')),
    ]);
    $taskCompletedId = $taskModel->create($userAId, [
        'title'    => 'Công việc đã hoàn thành',
        'priority' => 'urgent',
        'status'   => 'completed',
        'due_date' => date('Y-m-d', strtotime('-1 day')),
    ]);
    $taskSoftDeletedId = $taskModel->create($userAId, [
        'title'    => 'Công việc đã xóa mềm',
        'priority' => 'urgent',
        'status'   => 'pending',
        'due_date' => date('Y-m-d', strtotime('-1 day')),
    ]);
    $taskModel->delete($taskSoftDeletedId, $userAId);

    // Chèn thông báo cũ từ NGÀY HÔM QUA cho 3 task để chứng minh hôm nay vẫn sinh mới bình thường
    $insOldStmt = $db->prepare("
        INSERT INTO notifications (user_id, type, title, message, action_url, reference_type, reference_id, is_read, time_ago, created_at)
        VALUES (:uid, 'task', :title, :msg, :url, :rtype, :rid, 1, 'Hôm qua', DATE_SUB(NOW(), INTERVAL 1 DAY))
    ");
    foreach ([
        ['task_overdue', $taskOverdueId, 'Cảnh báo quá hạn (hôm qua)'],
        ['task_due_today', $taskDueTodayId, 'Hạn chót hôm nay (hôm qua)'],
        ['task_urgent', $taskUrgentId, 'Việc khẩn cấp (hôm qua)'],
    ] as [$oldRtype, $oldRid, $oldTitle]) {
        $insOldStmt->execute([
            'uid'   => $userAId,
            'title' => $oldTitle,
            'msg'   => 'Thông báo ngày hôm trước',
            'url'   => 'index.php?page=tasks&task_id=' . $oldRid,
            'rtype' => $oldRtype,
            'rid'   => $oldRid,
        ]);
    }

    // Gọi syncUserReminders lần 1
    $sync1 = $notifController->syncUserReminders($userAId);

    $cntEvRem1 = countNotificationsByRef($db, $userAId, 'event_reminder', $evReminderId, true);
    $typeEvRem = $db->query("SELECT type FROM notifications WHERE user_id = {$userAId} AND reference_type = 'event_reminder' AND reference_id = {$evReminderId} LIMIT 1")->fetchColumn();
    $remSentFlag = (int)$db->query("SELECT is_sent FROM reminders WHERE user_id = {$userAId} AND event_id = {$evReminderId} LIMIT 1")->fetchColumn();
    assertTest(
        '1.1a event_reminder sinh đúng 1 bản ghi type=event và đánh dấu reminders.is_sent=1',
        $cntEvRem1 === 1 && $typeEvRem === 'event' && $remSentFlag === 1,
        "count={$cntEvRem1}, type=" . var_export($typeEvRem, true) . ", is_sent={$remSentFlag}"
    );

    $cntSoftDelRem = countNotificationsByRef($db, $userAId, 'event_reminder', $evSoftDeletedRemId)
        + countNotificationsByRef($db, $userAId, 'event_today', $evSoftDeletedRemId)
        + countNotificationsByRef($db, $userAId, 'event_today', $evSoftDeletedTodayId);
    $cntCompletedEv = countNotificationsByRef($db, $userAId, 'event_reminder', $evCompletedId)
        + countNotificationsByRef($db, $userAId, 'event_today', $evCompletedId);
    assertTest(
        '1.1b Sự kiện xóa mềm hoặc completed KHÔNG sinh nhắc lịch / hôm nay',
        $cntSoftDelRem === 0 && $cntCompletedEv === 0,
        "softDeleted={$cntSoftDelRem}, completed={$cntCompletedEv}"
    );

    $cntEvToday1 = countNotificationsByRef($db, $userAId, 'event_today', $evTodayId, true);
    $typeEvToday = $db->query("SELECT type FROM notifications WHERE user_id = {$userAId} AND reference_type = 'event_today' AND reference_id = {$evTodayId} LIMIT 1")->fetchColumn();
    $cntEvTomorrow = countNotificationsByRef($db, $userAId, 'event_today', $evTomorrowId);
    assertTest(
        '1.2a event_today sinh đúng 1 bản ghi type=event cho sự kiện sắp diễn ra hôm nay (bỏ qua ngày mai)',
        $cntEvToday1 === 1 && $typeEvToday === 'event' && $cntEvTomorrow === 0 && !empty($sync1['upcoming_events']),
        "today={$cntEvToday1}, tomorrow={$cntEvTomorrow}"
    );

    $todayOverdueCnt = countNotificationsByRef($db, $userAId, 'task_overdue', $taskOverdueId, true);
    $totalOverdueCnt = countNotificationsByRef($db, $userAId, 'task_overdue', $taskOverdueId, false);
    $todayDueCnt = countNotificationsByRef($db, $userAId, 'task_due_today', $taskDueTodayId, true);
    $totalDueCnt = countNotificationsByRef($db, $userAId, 'task_due_today', $taskDueTodayId, false);
    $todayUrgentCnt = countNotificationsByRef($db, $userAId, 'task_urgent', $taskUrgentId, true);
    $totalUrgentCnt = countNotificationsByRef($db, $userAId, 'task_urgent', $taskUrgentId, false);
    $doneOrDelTaskNotif = countNotificationsByRef($db, $userAId, 'task_overdue', $taskCompletedId)
        + countNotificationsByRef($db, $userAId, 'task_urgent', $taskCompletedId)
        + countNotificationsByRef($db, $userAId, 'task_overdue', $taskSoftDeletedId)
        + countNotificationsByRef($db, $userAId, 'task_urgent', $taskSoftDeletedId);

    $stmtTaskTypes = $db->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE user_id = :uid AND DATE(created_at) = CURDATE()
          AND reference_type IN ('task_overdue', 'task_due_today', 'task_urgent')
          AND type = 'task'
    ");
    $stmtTaskTypes->execute(['uid' => $userAId]);
    $todayTaskTypeMatch = (int)$stmtTaskTypes->fetchColumn();

    assertTest(
        '1.3a task_overdue/task_due_today/task_urgent sinh bản ghi type=task hôm nay dù hôm qua đã có thông báo cũ',
        $todayOverdueCnt === 1 && $totalOverdueCnt === 2
            && $todayDueCnt === 1 && $totalDueCnt === 2
            && $todayUrgentCnt === 1 && $totalUrgentCnt === 2
            && $todayTaskTypeMatch === 3
            && $doneOrDelTaskNotif === 0,
        "overdue=({$todayOverdueCnt}/{$totalOverdueCnt}), dueToday=({$todayDueCnt}/{$totalDueCnt}), urgent=({$todayUrgentCnt}/{$totalUrgentCnt}), doneOrDel={$doneOrDelTaskNotif}"
    );

    // Gọi syncUserReminders lần 2 cùng ngày -> KHÔNG sinh trùng
    $sync2 = $notifController->syncUserReminders($userAId);
    $cntEvRem2 = countNotificationsByRef($db, $userAId, 'event_reminder', $evReminderId, true);
    $cntEvToday2 = countNotificationsByRef($db, $userAId, 'event_today', $evTodayId, true);
    $todayOverdueCnt2 = countNotificationsByRef($db, $userAId, 'task_overdue', $taskOverdueId, true);
    $todayDueCnt2 = countNotificationsByRef($db, $userAId, 'task_due_today', $taskDueTodayId, true);
    $todayUrgentCnt2 = countNotificationsByRef($db, $userAId, 'task_urgent', $taskUrgentId, true);
    assertTest(
        '1.4 Gọi syncUserReminders lần 2 trong ngày KHÔNG sinh trùng lặp',
        $cntEvRem2 === 1 && $cntEvToday2 === 1
            && $todayOverdueCnt2 === 1 && $todayDueCnt2 === 1 && $todayUrgentCnt2 === 1
            && empty($sync2['new_notifications']),
        'new_notifications count=' . count($sync2['new_notifications'])
    );

    // Kiểm tra endpoint NotificationController::checkReminders
    $chkRemRes = invokeControllerAction(
        'NotificationController.php',
        'NotificationController',
        'checkReminders',
        $userAId,
        $userAName,
        $userAEmail,
        'GET'
    );
    assertTest(
        '1.5 NotificationController::checkReminders trả về JSON hợp lệ',
        $chkRemRes['exit_code'] === 0
            && $chkRemRes['stderr'] === ''
            && ($chkRemRes['json']['success'] ?? false) === true
            && isset($chkRemRes['json']['deadline_summary'], $chkRemRes['json']['upcoming_events'], $chkRemRes['json']['recent_notifications']),
        $chkRemRes['stderr'] ?: $chkRemRes['stdout']
    );

    // =========================================================================
    // 2. ĐẦY ĐỦ CÁC LUỒNG CRUD & HỆ THỐNG
    // =========================================================================
    echo "\n--- 2. Luồng CRUD (Event, Task, Habit, Profile, System Test) ---\n";

    // 2.1 Event CRUD: Create -> Update -> Reschedule (updateTime) -> Soft Delete
    $evBaseStart = date('Y-m-d H:i:s', strtotime('+2 days 09:00:00'));
    $evBaseEnd = date('Y-m-d H:i:s', strtotime('+2 days 10:00:00'));
    $evCreateRes = invokeControllerAction(
        'EventController.php',
        'EventController',
        'save',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        [
            'title'            => 'Sự kiện CRUD Test',
            'description'      => 'Mô tả ban đầu',
            'location'         => 'Phòng B2',
            'start_time'       => $evBaseStart,
            'end_time'         => $evBaseEnd,
            'priority'         => 'high',
            'status'           => 'pending',
            'has_reminder'     => '1',
            'reminder_minutes' => '15',
        ]
    );
    $evCrudId = (int)($evCreateRes['json']['id'] ?? 0);
    assertTest(
        '2.1a Tạo sự kiện sinh thông báo type=event (event_created)',
        ($evCreateRes['json']['success'] ?? false) === true
            && $evCrudId > 0
            && countNotificationsByRef($db, $userAId, 'event_created', $evCrudId) === 1,
        $evCreateRes['stderr'] ?: $evCreateRes['stdout']
    );

    // Giả lập đã gửi nhắc nhở trước khi cập nhật
    Event::markReminderSent($evCrudId, $userAId);
    $remBeforeUpdate = countReminders($db, $userAId, $evCrudId);

    $evUpdateStart = date('Y-m-d H:i:s', strtotime('+2 days 11:00:00'));
    $evUpdateEnd = date('Y-m-d H:i:s', strtotime('+2 days 12:00:00'));
    $evUpdateRes = invokeControllerAction(
        'EventController.php',
        'EventController',
        'save',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        [
            'id'               => $evCrudId,
            'title'            => 'Sự kiện CRUD Test Đã Sửa',
            'start_time'       => $evUpdateStart,
            'end_time'         => $evUpdateEnd,
            'priority'         => 'medium',
            'status'           => 'pending',
            'has_reminder'     => '1',
            'reminder_minutes' => '30',
        ]
    );
    $remAfterUpdate = countReminders($db, $userAId, $evCrudId);
    assertTest(
        '2.1b Cập nhật sự kiện sinh event_updated (type=event) và gọi Event::resetReminder',
        ($evUpdateRes['json']['success'] ?? false) === true
            && $remBeforeUpdate === 1
            && $remAfterUpdate === 0
            && countNotificationsByRef($db, $userAId, 'event_updated', $evCrudId) === 1,
        "remBefore={$remBeforeUpdate}, remAfter={$remAfterUpdate}"
    );

    // Giả lập đã gửi nhắc nhở trước khi dời giờ
    Event::markReminderSent($evCrudId, $userAId);
    $evNewStart = date('Y-m-d H:i:s', strtotime('+2 days 14:00:00'));
    $evNewEnd = date('Y-m-d H:i:s', strtotime('+2 days 15:30:00'));
    $evTimeRes = invokeControllerAction(
        'EventController.php',
        'EventController',
        'updateTime',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        [
            'id'         => $evCrudId,
            'start_time' => $evNewStart,
            'end_time'   => $evNewEnd,
        ]
    );
    $remAfterTime = countReminders($db, $userAId, $evCrudId);
    $evUpdatedRow = $eventModel->findById($evCrudId, $userAId);
    $expectedReminderAt = date('Y-m-d H:i:s', strtotime($evNewStart) - 1800);
    assertTest(
        '2.1c Dời giờ sự kiện sinh event_rescheduled (type=event), resetReminder và tính lại reminder_at',
        ($evTimeRes['json']['success'] ?? false) === true
            && $remAfterTime === 0
            && $evUpdatedRow !== null
            && $evUpdatedRow['reminder_at'] === $expectedReminderAt
            && countNotificationsByRef($db, $userAId, 'event_rescheduled', $evCrudId) === 1,
        "remAfter={$remAfterTime}, reminder_at=" . ($evUpdatedRow['reminder_at'] ?? 'null')
    );

    // Giả lập nhắc nhở rồi xóa mềm sự kiện
    Event::markReminderSent($evCrudId, $userAId);
    $evDelRes = invokeControllerAction(
        'EventController.php',
        'EventController',
        'delete',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        ['id' => $evCrudId]
    );
    $remAfterEvDel = countReminders($db, $userAId, $evCrudId);
    assertTest(
        '2.1d Xóa mềm sự kiện sinh event_trashed (type=event) và resetReminder',
        ($evDelRes['json']['success'] ?? false) === true
            && $eventModel->findById($evCrudId, $userAId) === null
            && $remAfterEvDel === 0
            && countNotificationsByRef($db, $userAId, 'event_trashed', $evCrudId) === 1,
        "remAfterDel={$remAfterEvDel}"
    );

    // 2.2 Task CRUD: Create -> Complete -> Soft Delete -> Restore -> ForceDelete dọn sạch thông báo mồ côi
    $tkCreateRes = invokeControllerAction(
        'TaskController.php',
        'TaskController',
        'save',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        [
            'title'    => 'Task CRUD Test',
            'priority' => 'high',
            'status'   => 'pending',
            'due_date' => date('Y-m-d', strtotime('+4 days')),
        ]
    );
    $tkCrudId = (int)($tkCreateRes['json']['id'] ?? 0);
    assertTest(
        '2.2a Tạo công việc sinh task_created (type=task)',
        ($tkCreateRes['json']['success'] ?? false) === true
            && $tkCrudId > 0
            && countNotificationsByRef($db, $userAId, 'task_created', $tkCrudId) === 1,
        $tkCreateRes['stderr'] ?: $tkCreateRes['stdout']
    );

    $tkCompRes = invokeControllerAction(
        'TaskController.php',
        'TaskController',
        'updateStatus',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        ['id' => $tkCrudId, 'status' => 'completed']
    );
    assertTest(
        '2.2b Hoàn thành công việc sinh task_completed (type=task)',
        ($tkCompRes['json']['success'] ?? false) === true
            && ($tkCompRes['json']['status'] ?? '') === 'completed'
            && countNotificationsByRef($db, $userAId, 'task_completed', $tkCrudId) === 1
    );

    $tkDelRes = invokeControllerAction(
        'TaskController.php',
        'TaskController',
        'delete',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        ['id' => $tkCrudId]
    );
    assertTest(
        '2.2c Xóa mềm công việc sinh task_trashed (type=task)',
        ($tkDelRes['json']['success'] ?? false) === true
            && $taskModel->findById($tkCrudId, $userAId) === null
            && countNotificationsByRef($db, $userAId, 'task_trashed', $tkCrudId) === 1
    );

    $tkRestoreRes = invokeControllerAction(
        'TaskController.php',
        'TaskController',
        'restore',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        ['id' => $tkCrudId, 'type' => 'task']
    );
    assertTest(
        '2.2d Khôi phục công việc sinh task_restored (type=task)',
        ($tkRestoreRes['json']['success'] ?? false) === true
            && $taskModel->findById($tkCrudId, $userAId) !== null
            && countNotificationsByRef($db, $userAId, 'task_restored', $tkCrudId) === 1
    );

    // Tạo thêm thông báo nhắc hạn giả lập cho task này + 1 thông báo hệ thống không liên quan để kiểm chứng forceDelete không xóa nhầm
    Notification::createUniqueToday([
        'user_id'        => $userAId,
        'type'           => 'task',
        'title'          => 'Nhắc hạn task sắp xóa vĩnh viễn',
        'reference_type' => 'task_due_today',
        'reference_id'   => $tkCrudId,
    ]);
    $keepNotifId = $notifModel->create($userAId, 'Thông báo giữ lại', 'Không bị xóa theo task', 'system', null, 'system_keep', null);

    // Xóa mềm lại rồi xóa vĩnh viễn (forceDelete)
    invokeControllerAction('TaskController.php', 'TaskController', 'delete', $userAId, $userAName, $userAEmail, 'POST', ['id' => $tkCrudId]);
    $tkForceDelRes = invokeControllerAction(
        'TaskController.php',
        'TaskController',
        'forceDelete',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        ['id' => $tkCrudId, 'type' => 'task']
    );
    $orphanTaskNotifStmt = $db->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE user_id = :uid AND reference_id = :rid
          AND (reference_type = 'task' OR reference_type LIKE 'task\\_%')
    ");
    $orphanTaskNotifStmt->execute(['uid' => $userAId, 'rid' => $tkCrudId]);
    $orphanCount = (int)$orphanTaskNotifStmt->fetchColumn();
    $keepStillExists = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE id = {$keepNotifId}")->fetchColumn();

    assertTest(
        '2.2e Xóa vĩnh viễn công việc (forceDelete) dọn sạch thông báo mồ côi của task và giữ nguyên thông báo khác',
        ($tkForceDelRes['json']['success'] ?? false) === true
            && $orphanCount === 0
            && $keepStillExists === 1,
        "orphanCount={$orphanCount}, keepStillExists={$keepStillExists}"
    );

    // 2.3 Habit (save + toggle habit_checkin chống trùng trong ngày), Profile (apiSettingsSave), System Test (apiTest)
    $habitCreateRes = invokeControllerAction(
        'HabitController.php',
        'HabitController',
        'save',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        [
            'name'        => 'Thiền 15 phút Test',
            'description' => 'Giữ tập trung mỗi sáng',
            'frequency'   => 'daily',
            'color'       => '#15803d',
        ]
    );
    $habitId = (int)($habitCreateRes['json']['id'] ?? 0);
    $habitNotifType = $db->query("SELECT type FROM notifications WHERE user_id = {$userAId} AND reference_type = 'habit_created' AND reference_id = {$habitId} LIMIT 1")->fetchColumn();
    assertTest(
        '2.3a Tạo thói quen sinh habit_created (type=system)',
        ($habitCreateRes['json']['success'] ?? false) === true
            && $habitId > 0
            && $habitNotifType === 'system'
            && countNotificationsByRef($db, $userAId, 'habit_created', $habitId) === 1
    );

    $todayDate = date('Y-m-d');
    $toggleOn1 = invokeControllerAction(
        'HabitController.php',
        'HabitController',
        'toggle',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        ['habit_id' => $habitId, 'date' => $todayDate]
    );
    $toggleOff = invokeControllerAction(
        'HabitController.php',
        'HabitController',
        'toggle',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        ['habit_id' => $habitId, 'date' => $todayDate]
    );
    $toggleOn2 = invokeControllerAction(
        'HabitController.php',
        'HabitController',
        'toggle',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        ['habit_id' => $habitId, 'date' => $todayDate]
    );
    $checkinCnt = countNotificationsByRef($db, $userAId, 'habit_checkin', $habitId, true);
    $checkinType = $db->query("SELECT type FROM notifications WHERE user_id = {$userAId} AND reference_type = 'habit_checkin' AND reference_id = {$habitId} LIMIT 1")->fetchColumn();
    assertTest(
        '2.3b Check-in thói quen sinh habit_checkin (type=system) và chống trùng khi toggle lại trong cùng ngày',
        ($toggleOn1['json']['checked'] ?? false) === true
            && ($toggleOff['json']['checked'] ?? true) === false
            && ($toggleOn2['json']['checked'] ?? false) === true
            && $checkinCnt === 1
            && $checkinType === 'system',
        "checkinCnt={$checkinCnt}, type=" . var_export($checkinType, true)
    );

    $profileRes = invokeControllerAction(
        'EventController.php',
        'EventController',
        'apiSettingsSave',
        $userAId,
        $userAName,
        $userAEmail,
        'POST',
        ['name' => 'Test Notif A Updated']
    );
    $updatedUserRow = $userModel->findById($userAId);
    $profileNotifType = $db->query("SELECT type FROM notifications WHERE user_id = {$userAId} AND reference_type = 'profile_updated' AND reference_id = {$userAId} LIMIT 1")->fetchColumn();
    assertTest(
        '2.3c Cập nhật Hồ sơ sinh profile_updated (type=system)',
        ($profileRes['json']['success'] ?? false) === true
            && ($updatedUserRow['name'] ?? '') === 'Test Notif A Updated'
            && $profileNotifType === 'system'
            && countNotificationsByRef($db, $userAId, 'profile_updated', $userAId) === 1
    );
    $userAName = 'Test Notif A Updated';

    $sysTestRes = invokeControllerAction(
        'NotificationController.php',
        'NotificationController',
        'apiTest',
        $userAId,
        $userAName,
        $userAEmail,
        'POST'
    );
    $sysTestNotifId = (int)($sysTestRes['json']['notification_id'] ?? 0);
    $sysTestRow = $sysTestNotifId > 0
        ? $db->query("SELECT type, reference_type FROM notifications WHERE id = {$sysTestNotifId} AND user_id = {$userAId}")->fetch()
        : false;
    assertTest(
        '2.3d Thông báo thử nghiệm (apiTest) sinh system_test (type=system)',
        ($sysTestRes['json']['success'] ?? false) === true
            && $sysTestNotifId > 0
            && ($sysTestRes['json']['time_ago'] ?? '') === 'Vừa xong'
            && is_array($sysTestRow)
            && $sysTestRow['type'] === 'system'
            && $sysTestRow['reference_type'] === 'system_test'
    );

    // =========================================================================
    // 3. TRUNG TÂM THÔNG BÁO & BẢO MẬT (TABS, TIME_AGO, READ/DELETE, IDOR)
    // =========================================================================
    echo "\n--- 3. Trung tâm thông báo & Bảo mật (Tabs, time_ago, IDOR) ---\n";

    // Dọn các sự kiện/công việc/thông báo ở phần 1-2 của User A để kiểm soát tập dữ liệu phần 3 chính xác
    $db->prepare("DELETE FROM reminders WHERE user_id = ?")->execute([$userAId]);
    $db->prepare("DELETE FROM events WHERE user_id = ?")->execute([$userAId]);
    $db->prepare("DELETE FROM tasks WHERE user_id = ?")->execute([$userAId]);
    $db->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userAId]);

    $nNowId = $notifModel->create($userAId, 'Tab Event 1 - Mới nhất', 'Nội dung vừa xong', 'event', 'index.php?page=calendar', 'manual_test', 101);
    $nMinsId = $notifModel->create($userAId, 'Tab Event 2 - Phút', 'Nội dung vài phút trước', 'event', 'index.php?page=calendar', 'manual_test', 102);
    $nHoursId = $notifModel->create($userAId, 'Tab Task 1 - Giờ', 'Nội dung vài giờ trước', 'task', 'index.php?page=tasks', 'manual_test', 103);
    $nYesterdayId = $notifModel->create($userAId, 'Tab Task 2 - Hôm qua', 'Nội dung hôm qua', 'task', 'index.php?page=tasks', 'manual_test', 104);
    $nDaysId = $notifModel->create($userAId, 'Tab System 1 - Ngày', 'Nội dung vài ngày trước', 'system', 'index.php?page=habits', 'manual_test', 105);
    $nOldId = $notifModel->create($userAId, 'Tab System 2 - Cũ', 'Nội dung trên 7 ngày', 'system', 'index.php?page=settings', 'manual_test', 106);

    // Tạo thông báo riêng của User B để kiểm tra chống IDOR
    $notifBId = $notifModel->create($userBId, 'Thông báo riêng tư User B', 'User A không được phép thấy hoặc sửa', 'event', 'index.php?page=calendar', 'private_b', 999);

    // Thiết lập created_at cụ thể + cố tình đặt time_ago DB thành giá trị tĩnh cũ để kiểm chứng tính động
    $tsNow = time() - 5;
    $tsMins = time() - (15 * 60 + 20);
    $tsHours = time() - (3 * 3600 + 300);
    $tsYesterday = time() - 100000; // nằm chắc chắn trong [86400, 172800) bất kể múi giờ/nửa đêm
    $tsDays = time() - (3 * 86400 + 3600);
    $tsOld = time() - (10 * 86400);
    $expectedOldTimeAgo = date('d/m/Y H:i', $tsOld);

    $updTsStmt = $db->prepare("UPDATE notifications SET created_at = :dt, time_ago = 'stale_value', is_read = :read WHERE id = :id");
    $updTsStmt->execute(['dt' => date('Y-m-d H:i:s', $tsNow), 'read' => 0, 'id' => $nNowId]);
    $updTsStmt->execute(['dt' => date('Y-m-d H:i:s', $tsMins), 'read' => 0, 'id' => $nMinsId]);
    $updTsStmt->execute(['dt' => date('Y-m-d H:i:s', $tsHours), 'read' => 0, 'id' => $nHoursId]);
    $updTsStmt->execute(['dt' => date('Y-m-d H:i:s', $tsYesterday), 'read' => 1, 'id' => $nYesterdayId]);
    $updTsStmt->execute(['dt' => date('Y-m-d H:i:s', $tsDays), 'read' => 0, 'id' => $nDaysId]);
    $updTsStmt->execute(['dt' => date('Y-m-d H:i:s', $tsOld), 'read' => 1, 'id' => $nOldId]);

    // 3.1 Kiểm tra lọc 4 tab (all, event, task, system) + time_ago động + countUnread
    $tabAll = invokeControllerAction('NotificationController.php', 'NotificationController', 'apiList', $userAId, $userAName, $userAEmail, 'GET', [], ['type' => 'all']);
    $tabEvent = invokeControllerAction('NotificationController.php', 'NotificationController', 'apiList', $userAId, $userAName, $userAEmail, 'GET', [], ['type' => 'event']);
    $tabTask = invokeControllerAction('NotificationController.php', 'NotificationController', 'apiList', $userAId, $userAName, $userAEmail, 'GET', [], ['type' => 'task']);
    $tabSystem = invokeControllerAction('NotificationController.php', 'NotificationController', 'apiList', $userAId, $userAName, $userAEmail, 'GET', [], ['type' => 'system']);

    $allItems = $tabAll['json']['data'] ?? [];
    $eventItems = $tabEvent['json']['data'] ?? [];
    $taskItems = $tabTask['json']['data'] ?? [];
    $systemItems = $tabSystem['json']['data'] ?? [];

    $onlyType = static function (array $rows, string $expectedType): bool {
        foreach ($rows as $r) {
            if (($r['type'] ?? '') !== $expectedType) {
                return false;
            }
        }
        return true;
    };

    assertTest(
        '3.1 Lọc 4 tab (all, event, task, system) và countUnread chỉ tính của User A',
        count($allItems) === 6
            && ($tabAll['json']['unread_count'] ?? -1) === 4
            && Notification::countUnread($userAId) === 4
            && count($eventItems) === 2 && $onlyType($eventItems, 'event')
            && count($taskItems) === 2 && $onlyType($taskItems, 'task')
            && count($systemItems) === 2 && $onlyType($systemItems, 'system'),
        'all=' . count($allItems) . ', ev=' . count($eventItems) . ', tk=' . count($taskItems) . ', sys=' . count($systemItems)
    );

    $byId = [];
    foreach ($allItems as $row) {
        $byId[(int)$row['id']] = $row;
    }
    $timeAgoOk = (($byId[$nNowId]['time_ago'] ?? '') === 'Vừa xong')
        && (($byId[$nMinsId]['time_ago'] ?? '') === '15 phút trước')
        && (($byId[$nHoursId]['time_ago'] ?? '') === '3 giờ trước')
        && (($byId[$nYesterdayId]['time_ago'] ?? '') === 'Hôm qua')
        && (($byId[$nDaysId]['time_ago'] ?? '') === '3 ngày trước')
        && (($byId[$nOldId]['time_ago'] ?? '') === $expectedOldTimeAgo);
    assertTest(
        '3.2 time_ago động tiếng Việt (Vừa xong, phút, giờ, Hôm qua, ngày, dd/mm/YYYY HH:ii)',
        $timeAgoOk,
        json_encode(array_map(fn($r) => [$r['id'], $r['time_ago']], $allItems), JSON_UNESCAPED_UNICODE)
    );

    // 3.3 Chống IDOR: User A không thể đọc / đánh dấu đọc / xóa thông báo của User B
    $userASeesB = isset($byId[$notifBId]);
    invokeControllerAction('NotificationController.php', 'NotificationController', 'markRead', $userAId, $userAName, $userAEmail, 'POST', ['id' => $notifBId]);
    $notifBAfterRead = $db->query("SELECT is_read FROM notifications WHERE id = {$notifBId} AND user_id = {$userBId}")->fetchColumn();

    invokeControllerAction('NotificationController.php', 'NotificationController', 'delete', $userAId, $userAName, $userAEmail, 'POST', ['id' => $notifBId]);
    $notifBAfterDelete = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE id = {$notifBId} AND user_id = {$userBId}")->fetchColumn();

    assertTest(
        '3.3 Chống IDOR: User A KHÔNG thể đọc, đánh dấu đọc hoặc xóa thông báo của User B',
        !$userASeesB
            && (int)$notifBAfterRead === 0
            && $notifBAfterDelete === 1
            && Notification::countUnread($userBId) === 1,
        "seesB=" . var_export($userASeesB, true) . ", isReadB={$notifBAfterRead}, existsB={$notifBAfterDelete}"
    );

    // 3.4 Đánh dấu đã đọc 1 / tất cả và Xóa 1 / tất cả (không ảnh hưởng User B)
    $markOneRes = invokeControllerAction('NotificationController.php', 'NotificationController', 'markRead', $userAId, $userAName, $userAEmail, 'POST', ['id' => $nNowId]);
    $nNowIsRead = (int)$db->query("SELECT is_read FROM notifications WHERE id = {$nNowId}")->fetchColumn();
    assertTest(
        '3.4a Đánh dấu đã đọc 1 thông báo giảm unread_count từ 4 xuống 3',
        ($markOneRes['json']['success'] ?? false) === true
            && ($markOneRes['json']['unread_count'] ?? -1) === 3
            && $nNowIsRead === 1
    );

    $markAllRes = invokeControllerAction('NotificationController.php', 'NotificationController', 'markAllRead', $userAId, $userAName, $userAEmail, 'POST');
    $bStillUnread = (int)$db->query("SELECT is_read FROM notifications WHERE id = {$notifBId}")->fetchColumn();
    assertTest(
        '3.4b Đánh dấu tất cả đã đọc đưa unread_count User A về 0 và giữ nguyên User B chưa đọc',
        ($markAllRes['json']['success'] ?? false) === true
            && ($markAllRes['json']['unread_count'] ?? -1) === 0
            && Notification::countUnread($userAId) === 0
            && $bStillUnread === 0
    );

    $delOneRes = invokeControllerAction('NotificationController.php', 'NotificationController', 'delete', $userAId, $userAName, $userAEmail, 'POST', ['id' => $nNowId]);
    $nNowExists = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE id = {$nNowId}")->fetchColumn();
    assertTest(
        '3.4c Xóa 1 thông báo của User A thành công',
        ($delOneRes['json']['success'] ?? false) === true && $nNowExists === 0
    );

    $delAllRes = invokeControllerAction('NotificationController.php', 'NotificationController', 'deleteAll', $userAId, $userAName, $userAEmail, 'POST');
    $aTotalLeft = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$userAId}")->fetchColumn();
    $bTotalLeft = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$userBId}")->fetchColumn();
    assertTest(
        '3.4d Xóa tất cả thông báo User A (còn 0) và KHÔNG xóa thông báo của User B',
        ($delAllRes['json']['success'] ?? false) === true
            && ($delAllRes['json']['unread_count'] ?? -1) === 0
            && $aTotalLeft === 0
            && $bTotalLeft === 1,
        "aLeft={$aTotalLeft}, bLeft={$bTotalLeft}"
    );
} finally {
    cleanupTestUsers($db, $testUserIds);
}

$remainingAfterCleanup = 0;
if (!empty($testUserIds)) {
    $ph = implode(',', array_fill(0, count($testUserIds), '?'));
    $chkClean = $db->prepare("SELECT COUNT(*) FROM users WHERE id IN ({$ph})");
    $chkClean->execute($testUserIds);
    $remainingAfterCleanup = (int)$chkClean->fetchColumn();
}
assertTest('4. Dọn sạch user & dữ liệu test tạm khỏi database', $remainingAfterCleanup === 0);

echo "\n================================================================\n";
echo "TỔNG KẾT: {$passCount} PASSED / {$failCount} FAILED\n";
echo "================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

