<?php
declare(strict_types=1);

/**
 * Kiểm thử E2E HTTP trực tiếp vào web server đang chạy (mặc định http://127.0.0.1:8080)
 * Mô phỏng trình duyệt thật với cURL cookie jar và CSRF token trích xuất từ HTML.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "[E2E] Thieu extension PHP cURL (curl_init).\n");
    exit(1);
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/app/Core/Database.php';
require_once $rootDir . '/app/Models/User.php';
require_once $rootDir . '/app/Models/Category.php';
require_once $rootDir . '/app/Models/Habit.php';

$baseUrl = rtrim((string)(getenv('E2E_BASE_URL') ?: 'http://127.0.0.1:8080'), '/');
$db = Database::getConnection();
$userModel = new User();
$categoryModel = new Category();
(new Habit())->ensureSchema();

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $detail = ''): void
{
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$name}\n";
    } else {
        $failCount++;
        $msg = $detail !== '' ? "{$name}: {$detail}" : $name;
        echo " [FAIL] {$msg}\n";
    }
}

function httpRequest(
    string $method,
    string $url,
    array|string|null $body = null,
    ?string $cookieJar = null,
    bool $follow = false,
    array $headers = []
): array {
    $ch = curl_init($url);
    $respHeaders = [];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$respHeaders): int {
            $len = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        },
    ]);

    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($body !== null) {
        $encoded = is_string($body) ? $body : http_build_query($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $rawBody = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    $decoded = is_string($rawBody) ? json_decode($rawBody, true) : null;

    return [
        'errno'         => $errno,
        'error'         => $error,
        'status'        => $status,
        'effective_url' => $effectiveUrl,
        'headers'       => $respHeaders,
        'body'          => is_string($rawBody) ? $rawBody : '',
        'json'          => is_array($decoded) ? $decoded : null,
    ];
}

function extractInputCsrf(string $html): string
{
    if (preg_match('/<input[^>]*\bname=["\']csrf_token["\'][^>]*\bvalue=["\']([^"\']+)["\']/i', $html, $m)
        || preg_match('/<input[^>]*\bvalue=["\']([^"\']+)["\'][^>]*\bname=["\']csrf_token["\']/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function extractMetaCsrf(string $html): string
{
    if (preg_match('/<meta[^>]*\bname=["\']csrf-token["\'][^>]*\bcontent=["\']([^"\']+)["\']/i', $html, $m)
        || preg_match('/<meta[^>]*\bcontent=["\']([^"\']+)["\'][^>]*\bname=["\']csrf-token["\']/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function extractFormAction(string $html, string $formId): string
{
    $quoted = preg_quote($formId, '/');
    if (preg_match('/<form[^>]*\bid=["\']' . $quoted . '["\'][^>]*\baction=["\']([^"\']+)["\']/i', $html, $m)
        || preg_match('/<form[^>]*\baction=["\']([^"\']+)["\'][^>]*\bid=["\']' . $quoted . '["\']/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function findNotifInList(array $items, string $refType, ?int $refId = null): ?array
{
    foreach ($items as $item) {
        if (($item['reference_type'] ?? null) !== $refType) {
            continue;
        }
        if ($refId === null) {
            return $item;
        }
        if ((int)($item['reference_id'] ?? 0) === $refId) {
            return $item;
        }
    }
    return null;
}

function hasLeakOrCrash(array $res): bool
{
    if ($res['status'] >= 500) {
        return true;
    }
    $needles = ['Fatal error', 'Uncaught TypeError', 'Stack trace', 'PDOException', '<b>Warning</b>', '<b>Notice</b>'];
    foreach ($needles as $n) {
        if (stripos($res['body'], $n) !== false) {
            return true;
        }
    }
    return false;
}

function cleanupUsersByEmailsOrIds(PDO $db, array $emails, array $extraIds = []): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $extraIds), fn(int $v) => $v > 0)));
    if (!empty($emails)) {
        $ph = implode(',', array_fill(0, count($emails), '?'));
        $stmt = $db->prepare("SELECT id FROM users WHERE email IN ({$ph})");
        $stmt->execute(array_values($emails));
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $ids[] = (int)$uid;
        }
        $ids = array_values(array_unique($ids));
    }

    if (empty($ids)) {
        return;
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $tables = ['reminders', 'notifications', 'habit_logs', 'habits', 'tasks', 'events', 'categories'];
    foreach ($tables as $tbl) {
        $check = $db->query("SHOW TABLES LIKE " . $db->quote($tbl))->fetchColumn();
        if ($check) {
            $db->prepare("DELETE FROM `{$tbl}` WHERE user_id IN ({$ph})")->execute($ids);
        }
    }
    $db->prepare("DELETE FROM `users` WHERE id IN ({$ph})")->execute($ids);
}

$cookieJar = tempnam(sys_get_temp_dir(), 'pc_e2e_cookie_');
$suffix = bin2hex(random_bytes(4));
$e2eName = 'E2E HTTP User';
$e2eEmail = "e2e_http_{$suffix}@example.test";
$e2ePass = 'E2ePass123!';
$otherEmail = "e2e_other_{$suffix}@example.test";
$e2eUserId = 0;
$otherUserId = 0;

echo "================================================================\n";
echo "KIỂM THỬ E2E HTTP LIVE ({$baseUrl}) - PERSONAL CALENDAR\n";
echo "================================================================\n\n";

try {
    // Kiểm tra server sẵn sàng
    $probe = httpRequest('GET', "{$baseUrl}/index.php?page=login", null, null, false);
    if ($probe['errno'] !== 0 || $probe['status'] === 0) {
        assertTest("0. Kết nối tới {$baseUrl}", false, $probe['error'] ?: 'Connection refused');
        throw new RuntimeException("Không kết nối được tới {$baseUrl}");
    }
    assertTest("0. Web server {$baseUrl} đang hoạt động (HTTP {$probe['status']})", $probe['status'] === 200);

    // =========================================================================
    // 1. ĐĂNG KÝ TẠM, WELCOME NOTIFICATION, ĐĂNG NHẬP & LẤY CSRF META
    // =========================================================================
    echo "\n--- 1. Đăng ký, Thông báo Welcome, Đăng nhập & CSRF ---\n";

    $regPage = httpRequest('GET', "{$baseUrl}/index.php?page=register", null, $cookieJar, false);
    $regCsrf = extractInputCsrf($regPage['body']);
    $regAction = ltrim(extractFormAction($regPage['body'], 'registerForm') ?: 'index.php?page=register-post', '/');

    assertTest('1.1 Mở trang đăng ký trích xuất được CSRF token & action form', $regPage['status'] === 200 && $regCsrf !== '');

    $regPost = httpRequest('POST', "{$baseUrl}/{$regAction}", [
        'csrf_token'       => $regCsrf,
        'name'             => $e2eName,
        'email'            => $e2eEmail,
        'password'         => $e2ePass,
        'password_confirm' => $e2ePass,
    ], $cookieJar, false);

    $createdUser = $userModel->findByEmail($e2eEmail);
    $e2eUserId = $createdUser ? (int)$createdUser['id'] : 0;
    $regLoc = $regPost['headers']['location'] ?? '';

    assertTest(
        '1.2 Đăng ký tài khoản E2E thành công và chuyển hướng Dashboard',
        $regPost['status'] === 302 && str_contains($regLoc, 'page=dashboard') && $e2eUserId > 0,
        "status={$regPost['status']}, loc={$regLoc}"
    );

    // Đăng xuất rồi đăng nhập lại qua form login để kiểm thử luồng login + session cookie mới
    httpRequest('GET', "{$baseUrl}/index.php?page=logout", null, $cookieJar, false);

    $loginPage = httpRequest('GET', "{$baseUrl}/index.php?page=login", null, $cookieJar, false);
    $loginCsrf = extractInputCsrf($loginPage['body']);
    $loginAction = ltrim(extractFormAction($loginPage['body'], 'loginForm') ?: 'index.php?page=login-post', '/');

    $loginPost = httpRequest('POST', "{$baseUrl}/{$loginAction}", [
        'csrf_token' => $loginCsrf,
        'email'      => $e2eEmail,
        'password'   => $e2ePass,
    ], $cookieJar, false);
    $loginLoc = $loginPost['headers']['location'] ?? '';

    $dashPage = httpRequest('GET', "{$baseUrl}/index.php?page=dashboard", null, $cookieJar, false);
    $csrfToken = extractMetaCsrf($dashPage['body']);

    assertTest(
        '1.3 Đăng nhập nhận session cookie & trích xuất csrf-token meta từ Dashboard',
        $loginPost['status'] === 302
            && str_contains($loginLoc, 'page=dashboard')
            && $dashPage['status'] === 200
            && $csrfToken !== '',
        "loginStatus={$loginPost['status']}, dashStatus={$dashPage['status']}, csrfLen=" . strlen($csrfToken)
    );

    $initNotifs = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications&type=system", null, $cookieJar);
    $welcomeNotif = findNotifInList($initNotifs['json']['data'] ?? [], 'welcome', $e2eUserId);
    assertTest(
        '1.4 Tài khoản mới có thông báo hệ thống welcome trong api-notifications',
        ($initNotifs['json']['success'] ?? false) === true
            && $welcomeNotif !== null
            && (int)($initNotifs['json']['unread_count'] ?? 0) === 1,
        json_encode($initNotifs['json'], JSON_UNESCAPED_UNICODE)
    );

    // =========================================================================
    // 2. NHẮC HẸN TỰ ĐỘNG & DASHBOARD
    // =========================================================================
    echo "\n--- 2. Nhắc hẹn tự động & Dashboard (HTTP API) ---\n";

    $todayRemaining = strtotime(date('Y-m-d 23:59:59')) - time();
    $todayOffset = $todayRemaining > 7500 ? 7200 : max(60, (int)floor($todayRemaining / 2));

    $evTodayRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-event-save", [
        'csrf_token'   => $csrfToken,
        'title'        => 'E2E Sự kiện hôm nay +2h',
        'start_time'   => date('Y-m-d H:i:s', time() + $todayOffset),
        'end_time'     => date('Y-m-d H:i:s', time() + $todayOffset + 1800),
        'location'     => 'Phòng E2E',
        'priority'     => 'medium',
        'status'       => 'pending',
        'has_reminder' => '0',
    ], $cookieJar);
    $evTodayId = (int)($evTodayRes['json']['id'] ?? 0);

    $evRemRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-event-save", [
        'csrf_token'       => $csrfToken,
        'title'            => 'E2E Sự kiện đến hạn nhắc',
        'start_time'       => date('Y-m-d H:i:s', time() - 300),
        'end_time'         => date('Y-m-d H:i:s', time() + 3300),
        'priority'         => 'high',
        'status'           => 'pending',
        'has_reminder'     => '1',
        'reminder_minutes' => '15',
    ], $cookieJar);
    $evRemId = (int)($evRemRes['json']['id'] ?? 0);

    $tkOverdueRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-task-save", [
        'csrf_token' => $csrfToken,
        'title'      => 'E2E Công việc quá hạn',
        'priority'   => 'high',
        'status'     => 'pending',
        'due_date'   => date('Y-m-d', strtotime('-2 days')),
    ], $cookieJar);
    $tkOverdueId = (int)($tkOverdueRes['json']['id'] ?? 0);

    assertTest(
        '2.1 Tạo 3 mục kích hoạt nhắc hẹn (hôm nay, đến giờ nhắc, quá hạn)',
        $evTodayId > 0 && $evRemId > 0 && $tkOverdueId > 0 && ($tkOverdueRes['json']['unread_count'] ?? 0) === 4
    );

    $remCheckRes = httpRequest('GET', "{$baseUrl}/index.php?page=api-reminders-check", null, $cookieJar);
    $notifListRes = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications", null, $cookieJar);
    $allData = $notifListRes['json']['data'] ?? [];

    $foundEvToday = findNotifInList($allData, 'event_today', $evTodayId);
    $foundEvRem = findNotifInList($allData, 'event_reminder', $evRemId);
    $foundTkOverdue = findNotifInList($allData, 'task_overdue', $tkOverdueId);

    assertTest(
        '2.2 api-reminders-check & api-notifications sinh đủ 3 nhắc hẹn và unread_count tăng từ 4 lên 7',
        ($remCheckRes['json']['success'] ?? false) === true
            && ($remCheckRes['json']['unread_count'] ?? -1) === 7
            && ($notifListRes['json']['unread_count'] ?? -1) === 7
            && $foundEvToday !== null
            && $foundEvRem !== null
            && $foundTkOverdue !== null,
        'unread=' . ($notifListRes['json']['unread_count'] ?? 'null')
    );

    // =========================================================================
    // 3. LUỒNG SỰ KIỆN LỊCH (Tạo / Sửa / Dời giờ / Xóa mềm)
    // =========================================================================
    echo "\n--- 3. Luồng Sự kiện lịch (HTTP API) ---\n";

    $evCRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-event-save", [
        'csrf_token'       => $csrfToken,
        'title'            => 'E2E Event Lifecycle',
        'start_time'       => date('Y-m-d H:i:s', strtotime('+2 days 09:00:00')),
        'end_time'         => date('Y-m-d H:i:s', strtotime('+2 days 10:00:00')),
        'priority'         => 'high',
        'status'           => 'pending',
        'has_reminder'     => '1',
        'reminder_minutes' => '15',
    ], $cookieJar);
    $evCrudId = (int)($evCRes['json']['id'] ?? 0);

    $evURes = httpRequest('POST', "{$baseUrl}/index.php?page=api-event-save", [
        'csrf_token'       => $csrfToken,
        'id'               => $evCrudId,
        'title'            => 'E2E Event Lifecycle Updated',
        'start_time'       => date('Y-m-d H:i:s', strtotime('+2 days 10:30:00')),
        'end_time'         => date('Y-m-d H:i:s', strtotime('+2 days 11:30:00')),
        'priority'         => 'medium',
        'status'           => 'pending',
        'has_reminder'     => '1',
        'reminder_minutes' => '30',
    ], $cookieJar);

    $evTRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-event-time", [
        'csrf_token' => $csrfToken,
        'id'         => $evCrudId,
        'start_time' => date('Y-m-d H:i:s', strtotime('+2 days 14:00:00')),
        'end_time'   => date('Y-m-d H:i:s', strtotime('+2 days 15:00:00')),
    ], $cookieJar);

    $evDRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-event-delete", [
        'csrf_token' => $csrfToken,
        'id'         => $evCrudId,
    ], $cookieJar);

    $evNotifs = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications&type=event", null, $cookieJar);
    $evItems = $evNotifs['json']['data'] ?? [];

    assertTest(
        '3.1 Tạo/sửa/dời giờ/xóa mềm Event tăng unread_count (8->9->10->11) & đủ 4 bản ghi type=event',
        ($evCRes['json']['unread_count'] ?? 0) === 8
            && ($evURes['json']['unread_count'] ?? 0) === 9
            && ($evTRes['json']['unread_count'] ?? 0) === 10
            && ($evDRes['json']['unread_count'] ?? 0) === 11
            && findNotifInList($evItems, 'event_created', $evCrudId) !== null
            && findNotifInList($evItems, 'event_updated', $evCrudId) !== null
            && findNotifInList($evItems, 'event_rescheduled', $evCrudId) !== null
            && findNotifInList($evItems, 'event_trashed', $evCrudId) !== null
    );

    // =========================================================================
    // 4. LUỒNG CÔNG VIỆC & THÙNG RÁC
    // =========================================================================
    echo "\n--- 4. Luồng Công việc & Thùng rác (HTTP API) ---\n";

    $tkCRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-task-save", [
        'csrf_token' => $csrfToken,
        'title'      => 'E2E Task Lifecycle',
        'priority'   => 'high',
        'status'     => 'pending',
        'due_date'   => date('Y-m-d', strtotime('+4 days')),
    ], $cookieJar);
    $tkCrudId = (int)($tkCRes['json']['id'] ?? 0);

    $tkSRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-task-status", [
        'csrf_token' => $csrfToken,
        'id'         => $tkCrudId,
        'status'     => 'completed',
    ], $cookieJar);

    $tkDRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-task-delete", [
        'csrf_token' => $csrfToken,
        'id'         => $tkCrudId,
    ], $cookieJar);

    $tkRRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-task-restore", [
        'csrf_token' => $csrfToken,
        'id'         => $tkCrudId,
        'type'       => 'task',
    ], $cookieJar);

    $tkNotifsBeforeForce = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications&type=task", null, $cookieJar);
    $tkItems1 = $tkNotifsBeforeForce['json']['data'] ?? [];
    assertTest(
        '4.1 Tạo/hoàn thành/xóa mềm/khôi phục Task xuất hiện trong api-notifications?type=task',
        ($tkCRes['json']['success'] ?? false) === true
            && ($tkSRes['json']['success'] ?? false) === true
            && ($tkDRes['json']['success'] ?? false) === true
            && ($tkRRes['json']['success'] ?? false) === true
            && findNotifInList($tkItems1, 'task_created', $tkCrudId) !== null
            && findNotifInList($tkItems1, 'task_completed', $tkCrudId) !== null
            && findNotifInList($tkItems1, 'task_trashed', $tkCrudId) !== null
            && findNotifInList($tkItems1, 'task_restored', $tkCrudId) !== null
    );

    // Xóa mềm lại -> force-delete -> kiểm tra sạch thông báo mồ côi
    httpRequest('POST', "{$baseUrl}/index.php?page=api-task-delete", ['csrf_token' => $csrfToken, 'id' => $tkCrudId], $cookieJar);
    $tkForceRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-task-force-delete", [
        'csrf_token' => $csrfToken,
        'id'         => $tkCrudId,
        'type'       => 'task',
    ], $cookieJar);

    $tkNotifsAfterForce = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications&type=task", null, $cookieJar);
    $hasOrphanTaskNotif = false;
    foreach ($tkNotifsAfterForce['json']['data'] ?? [] as $row) {
        if ((int)($row['reference_id'] ?? 0) === $tkCrudId && str_starts_with((string)($row['reference_type'] ?? ''), 'task')) {
            $hasOrphanTaskNotif = true;
            break;
        }
    }
    assertTest(
        '4.2 api-task-force-delete dọn sạch thông báo mồ côi của task',
        ($tkForceRes['json']['success'] ?? false) === true && !$hasOrphanTaskNotif
    );

    // Tạo thêm 1 task bỏ vào thùng rác cùng với $evCrudId rồi gọi api-trash-empty
    $tkTrashRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-task-save", [
        'csrf_token' => $csrfToken,
        'title'      => 'E2E Task Trash Empty',
        'status'     => 'pending',
    ], $cookieJar);
    $tkTrashId = (int)($tkTrashRes['json']['id'] ?? 0);
    httpRequest('POST', "{$baseUrl}/index.php?page=api-task-delete", ['csrf_token' => $csrfToken, 'id' => $tkTrashId], $cookieJar);

    $emptyTrashRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-trash-empty", ['csrf_token' => $csrfToken], $cookieJar);
    $sysAfterEmpty = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications&type=system", null, $cookieJar);
    $evAfterEmpty = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications&type=event", null, $cookieJar);
    assertTest(
        '4.3 api-trash-empty sinh thông báo trash_emptied (system) & dọn thông báo mồ côi trong rác',
        ($emptyTrashRes['json']['success'] ?? false) === true
            && findNotifInList($sysAfterEmpty['json']['data'] ?? [], 'trash_emptied') !== null
            && findNotifInList($evAfterEmpty['json']['data'] ?? [], 'event_created', $evCrudId) === null
    );

    // =========================================================================
    // 5. LUỒNG THÓI QUEN, CÀI ĐẶT & THỬ NGHIỆM HỆ THỐNG
    // =========================================================================
    echo "\n--- 5. Luồng Thói quen, Cài đặt & System Test (HTTP API) ---\n";

    $hbSave = httpRequest('POST', "{$baseUrl}/index.php?page=api-habit-save", [
        'csrf_token'  => $csrfToken,
        'name'        => 'E2E Đọc sách 20p',
        'description' => 'Kiểm thử HTTP',
        'frequency'   => 'daily',
        'color'       => '#15803d',
    ], $cookieJar);
    $habitId = (int)($hbSave['json']['id'] ?? 0);

    $todayStr = date('Y-m-d');
    $hbT1 = httpRequest('POST', "{$baseUrl}/index.php?page=api-habit-toggle", ['csrf_token' => $csrfToken, 'habit_id' => $habitId, 'date' => $todayStr], $cookieJar);
    $hbT2 = httpRequest('POST', "{$baseUrl}/index.php?page=api-habit-toggle", ['csrf_token' => $csrfToken, 'habit_id' => $habitId, 'date' => $todayStr], $cookieJar);
    $hbT3 = httpRequest('POST', "{$baseUrl}/index.php?page=api-habit-toggle", ['csrf_token' => $csrfToken, 'habit_id' => $habitId, 'date' => $todayStr], $cookieJar);

    $sysAfterHabit = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications&type=system", null, $cookieJar);
    $habitCheckinCount = 0;
    foreach ($sysAfterHabit['json']['data'] ?? [] as $item) {
        if (($item['reference_type'] ?? '') === 'habit_checkin' && (int)($item['reference_id'] ?? 0) === $habitId) {
            $habitCheckinCount++;
        }
    }
    assertTest(
        '5.1 api-habit-save + api-habit-toggle chống trùng habit_checkin trong ngày',
        $habitId > 0
            && ($hbT1['json']['checked'] ?? false) === true
            && ($hbT2['json']['checked'] ?? true) === false
            && ($hbT3['json']['checked'] ?? false) === true
            && findNotifInList($sysAfterHabit['json']['data'] ?? [], 'habit_created', $habitId) !== null
            && $habitCheckinCount === 1,
        "checkinCount={$habitCheckinCount}"
    );

    $hbDel = httpRequest('POST', "{$baseUrl}/index.php?page=api-habit-delete", ['csrf_token' => $csrfToken, 'id' => $habitId], $cookieJar);
    $sysAfterHabitDel = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications&type=system", null, $cookieJar);
    assertTest(
        '5.2 api-habit-delete xóa thói quen và dọn thông báo liên quan',
        ($hbDel['json']['success'] ?? false) === true
            && findNotifInList($sysAfterHabitDel['json']['data'] ?? [], 'habit_created', $habitId) === null
    );

    $settingsRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-settings-save", [
        'csrf_token' => $csrfToken,
        'name'       => 'E2E HTTP User Renamed',
    ], $cookieJar);

    $testNotifRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-notifications-test", [
        'csrf_token' => $csrfToken,
    ], $cookieJar);
    $sysTestId = (int)($testNotifRes['json']['notification_id'] ?? 0);

    $sysFinal = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications&type=system", null, $cookieJar);
    $foundSysTest = false;
    foreach ($sysFinal['json']['data'] ?? [] as $item) {
        if ((int)($item['id'] ?? 0) === $sysTestId && ($item['reference_type'] ?? '') === 'system_test') {
            $foundSysTest = true;
            break;
        }
    }
    assertTest(
        '5.3 api-settings-save (profile_updated) & api-notifications-test (system_test) xuất hiện ở tab system',
        ($settingsRes['json']['success'] ?? false) === true
            && ($testNotifRes['json']['success'] ?? false) === true
            && $sysTestId > 0
            && findNotifInList($sysFinal['json']['data'] ?? [], 'profile_updated', $e2eUserId) !== null
            && $foundSysTest
    );

    // =========================================================================
    // 6. LUỒNG ĐỌC / XÓA THÔNG BÁO
    // =========================================================================
    echo "\n--- 6. Luồng Đọc / Xóa thông báo (HTTP API) ---\n";

    $listForRead = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications", null, $cookieJar);
    $unreadBefore = (int)($listForRead['json']['unread_count'] ?? 0);
    $targetReadId = 0;
    foreach ($listForRead['json']['data'] ?? [] as $row) {
        if ((int)($row['is_read'] ?? 0) === 0) {
            $targetReadId = (int)$row['id'];
            break;
        }
    }

    $readOneRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-notification-read", [
        'csrf_token' => $csrfToken,
        'id'         => $targetReadId,
    ], $cookieJar);
    assertTest(
        '6.1 api-notification-read đánh dấu 1 thông báo đã đọc và giảm unread_count',
        $targetReadId > 0
            && ($readOneRes['json']['success'] ?? false) === true
            && ($readOneRes['json']['unread_count'] ?? -1) === ($unreadBefore - 1)
    );

    $readAllRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-notifications-read-all", ['csrf_token' => $csrfToken], $cookieJar);
    $afterReadAll = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications", null, $cookieJar);
    assertTest(
        '6.2 api-notifications-read-all đưa unread_count về 0',
        ($readAllRes['json']['success'] ?? false) === true
            && ($readAllRes['json']['unread_count'] ?? -1) === 0
            && ($afterReadAll['json']['unread_count'] ?? -1) === 0
    );

    $delOneRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-notification-delete", [
        'csrf_token' => $csrfToken,
        'id'         => $targetReadId,
    ], $cookieJar);
    $afterDelOne = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications", null, $cookieJar);
    $stillHasDeleted = false;
    foreach ($afterDelOne['json']['data'] ?? [] as $row) {
        if ((int)($row['id'] ?? 0) === $targetReadId) {
            $stillHasDeleted = true;
            break;
        }
    }
    assertTest(
        '6.3 api-notification-delete xóa đúng 1 thông báo',
        ($delOneRes['json']['success'] ?? false) === true && !$stillHasDeleted
    );

    $delAllRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-notifications-delete-all", ['csrf_token' => $csrfToken], $cookieJar);
    $stmtLeft = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid');
    $stmtLeft->execute(['uid' => $e2eUserId]);
    $dbNotifLeft = (int)$stmtLeft->fetchColumn();
    assertTest(
        '6.4 api-notifications-delete-all xóa sạch toàn bộ thông báo của user',
        ($delAllRes['json']['success'] ?? false) === true
            && ($delAllRes['json']['unread_count'] ?? -1) === 0
            && $dbNotifLeft === 0,
        "dbNotifLeft={$dbNotifLeft}"
    );

    // =========================================================================
    // 7. KIỂM THỬ BẢO MẬT QUA HTTP THẬT
    // =========================================================================
    echo "\n--- 7. Kiểm thử Bảo mật qua HTTP thật ---\n";

    // 7(a) GET /scripts/bootstrap-db.php phải trả 403
    $secBootstrap = httpRequest('GET', "{$baseUrl}/scripts/bootstrap-db.php", null, null, false);
    assertTest(
        '7.a GET /scripts/bootstrap-db.php trả HTTP 403',
        $secBootstrap['status'] === 403,
        "status={$secBootstrap['status']}"
    );

    // 7(b) GET api-notifications-test phải trả 405
    $secGetTest = httpRequest('GET', "{$baseUrl}/index.php?page=api-notifications-test", null, $cookieJar, false);
    assertTest(
        '7.b GET api-notifications-test trả HTTP 405',
        $secGetTest['status'] === 405 && ($secGetTest['json']['success'] ?? null) === false,
        "status={$secGetTest['status']}"
    );

    // 7(c) POST thiếu CSRF trả 403
    $secMissingCsrf1 = httpRequest('POST', "{$baseUrl}/index.php?page=api-notifications-test", [], $cookieJar, false);
    $secMissingCsrf2 = httpRequest('POST', "{$baseUrl}/index.php?page=api-event-save", ['title' => 'No CSRF'], $cookieJar, false);
    $secMissingCsrf3 = httpRequest('POST', "{$baseUrl}/index.php?page=api-task-save", ['title' => 'No CSRF'], $cookieJar, false);
    assertTest(
        '7.c POST thiếu CSRF trả HTTP 403',
        $secMissingCsrf1['status'] === 403
            && $secMissingCsrf2['status'] === 403
            && $secMissingCsrf3['status'] === 403,
        "statuses=[{$secMissingCsrf1['status']}, {$secMissingCsrf2['status']}, {$secMissingCsrf3['status']}]"
    );

    // 7(d) Gửi csrf_token[]=1 hoặc title[]=1 không lỗi 500 / không lộ stacktrace
    $arrCsrfRes = httpRequest(
        'POST',
        "{$baseUrl}/index.php?page=api-event-save",
        'csrf_token[]=1&title=ArrayCsrf&start_time=2026-12-01+09:00:00&end_time=2026-12-01+10:00:00',
        $cookieJar,
        false
    );
    $arrTitleEvRes = httpRequest(
        'POST',
        "{$baseUrl}/index.php?page=api-event-save",
        'csrf_token=' . urlencode($csrfToken) . '&title[]=1&start_time=2026-12-01+09:00:00&end_time=2026-12-01+10:00:00',
        $cookieJar,
        false
    );
    $arrTitleTkRes = httpRequest(
        'POST',
        "{$baseUrl}/index.php?page=api-task-save",
        'csrf_token=' . urlencode($csrfToken) . '&title[]=1',
        $cookieJar,
        false
    );
    assertTest(
        '7.d Gửi csrf_token[]=1 hoặc title[]=1 không lỗi 500 / không lộ stacktrace',
        !hasLeakOrCrash($arrCsrfRes)
            && !hasLeakOrCrash($arrTitleEvRes)
            && !hasLeakOrCrash($arrTitleTkRes)
            && ($arrCsrfRes['json']['success'] ?? null) === false
            && ($arrTitleEvRes['json']['success'] ?? null) === false
            && ($arrTitleTkRes['json']['success'] ?? null) === false,
        "statuses=[{$arrCsrfRes['status']}, {$arrTitleEvRes['status']}, {$arrTitleTkRes['status']}]"
    );

    // 7(e) IDOR category_id của user khác vào api-event-save / api-task-save phải bị chặn (success=false)
    $otherUserId = $userModel->create('Other E2E User', $otherEmail, 'OtherPass123!');
    $otherCatId = $categoryModel->create($otherUserId, 'Private Other Cat', '#ef4444', 'fa-lock');

    $idorEvCatRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-event-save", [
        'csrf_token'  => $csrfToken,
        'category_id' => $otherCatId,
        'title'       => 'IDOR Category Event',
        'start_time'  => date('Y-m-d H:i:s', strtotime('+3 days 09:00:00')),
        'end_time'    => date('Y-m-d H:i:s', strtotime('+3 days 10:00:00')),
    ], $cookieJar);

    $idorTkCatRes = httpRequest('POST', "{$baseUrl}/index.php?page=api-task-save", [
        'csrf_token'  => $csrfToken,
        'category_id' => $otherCatId,
        'title'       => 'IDOR Category Task',
    ], $cookieJar);

    assertTest(
        '7.e Gửi category_id của user khác vào api-event-save / api-task-save bị chặn (success=false)',
        ($idorEvCatRes['json']['success'] ?? null) === false
            && ($idorTkCatRes['json']['success'] ?? null) === false,
        'ev=' . $idorEvCatRes['body'] . ' | tk=' . $idorTkCatRes['body']
    );
} catch (Throwable $e) {
    if (!str_starts_with($e->getMessage(), 'Không kết nối được tới ')) {
        assertTest('E2E Runtime Error', false, $e->getMessage());
    }
} finally {
    // =========================================================================
    // 8. DỌN SẠCH TÀI KHOẢN E2E TẠM & COOKIE JAR
    // =========================================================================
    cleanupUsersByEmailsOrIds($db, [$e2eEmail, $otherEmail], [$e2eUserId, $otherUserId]);
    if (is_string($cookieJar) && $cookieJar !== '' && is_file($cookieJar)) {
        @unlink($cookieJar);
    }
}

$chkLeft = $db->prepare('SELECT COUNT(*) FROM users WHERE email IN (?, ?)');
$chkLeft->execute([$e2eEmail, $otherEmail]);
assertTest('8. Dọn sạch tài khoản E2E tạm khỏi database', (int)$chkLeft->fetchColumn() === 0);

echo "\n================================================================\n";
echo "TỔNG KẾT E2E HTTP: {$passCount} PASSED / {$failCount} FAILED\n";
echo "================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

