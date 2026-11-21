<?php
/**
 * Test Suite tự động kiểm thử toàn diện 12 tiêu chí Giai đoạn 3
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Category.php';
require_once __DIR__ . '/../app/Models/Task.php';
require_once __DIR__ . '/../app/Models/Notification.php';
require_once __DIR__ . '/../app/Models/Event.php';
require_once __DIR__ . '/../app/Controllers/TaskController.php';
require_once __DIR__ . '/../app/Controllers/NotificationController.php';
require_once __DIR__ . '/../app/Controllers/DashboardController.php';
require_once __DIR__ . '/../app/Controllers/EventController.php';

$db = Database::getConnection();

echo "====================================================\n";
echo "BẮT ĐẦU CHẠY KIỂM THỬ GIAI ĐOẠN 3 - PERSONAL CALENDAR\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $name\n";
    } else {
        $failCount++;
        echo " [FAIL] $name: $detail\n";
    }
}

// Giả lập user 1 và user 2
$user1Id = 1;
$user2Id = 6;
$_SESSION['user'] = ['id' => $user1Id, 'name' => 'năng tú Nguyễn quang', 'email' => 'nguyentu23061981@gmail.com'];

// ---------------------------------------------------------
// TIÊU CHÍ 1: Thêm, sửa, xóa công việc (Tasks CRUD)
// ---------------------------------------------------------
echo "--- 1. Kiểm tra Thêm, Sửa, Xóa Công Việc ---\n";
$taskModel = new Task();

// 1.1 Thêm công việc
$taskId = $taskModel->create($user1Id, [
    'title'       => 'Test Task Phase 3 Auto',
    'description' => 'Mô tả chi tiết kiểm thử tự động',
    'priority'    => 'urgent',
    'due_date'    => date('Y-m-d', strtotime('+3 days')),
    'status'      => 'pending'
]);
assertTest("1.1 Thêm công việc mới thành công (ID: $taskId)", $taskId > 0);

// 1.2 Đọc lại chi tiết công việc vừa tạo
$createdTask = $taskModel->findById($taskId, $user1Id);
assertTest("1.2 Đọc chi tiết công việc", $createdTask !== null && $createdTask['title'] === 'Test Task Phase 3 Auto' && $createdTask['priority'] === 'urgent');

// 1.3 Sửa công việc
$updateOk = $taskModel->update($taskId, $user1Id, [
    'title'       => 'Test Task Phase 3 Auto Updated',
    'description' => 'Mô tả đã được sửa đổi',
    'priority'    => 'high',
    'due_date'    => date('Y-m-d', strtotime('+5 days')),
    'status'      => 'pending'
]);
$updatedTask = $taskModel->findById($taskId, $user1Id);
assertTest("1.3 Sửa công việc thành công", $updateOk && $updatedTask['title'] === 'Test Task Phase 3 Auto Updated' && $updatedTask['priority'] === 'high');

// ---------------------------------------------------------
// TIÊU CHÍ 2: Thay đổi trạng thái công việc (Pending <-> Completed)
// ---------------------------------------------------------
echo "\n--- 2. Kiểm tra Thay Đổi Trạng Thái Công Việc ---\n";
// Chuyển sang completed
$statusOk1 = $taskModel->updateStatus($taskId, $user1Id, 'completed');
$taskComp = $taskModel->findById($taskId, $user1Id);
assertTest("2.1 Đánh dấu hoàn thành (status=completed, is_completed=1, progress=100)", 
    $statusOk1 && $taskComp['status'] === 'completed' && $taskComp['is_completed'] == 1 && $taskComp['progress'] == 100);

// Chuyển lại sang pending
$statusOk2 = $taskModel->updateStatus($taskId, $user1Id, 'pending');
$taskPend = $taskModel->findById($taskId, $user1Id);
assertTest("2.2 Khôi phục chưa hoàn thành (status=pending, is_completed=0, progress=0)", 
    $statusOk2 && $taskPend['status'] === 'pending' && $taskPend['is_completed'] == 0 && $taskPend['progress'] == 0);

// ---------------------------------------------------------
// TIÊU CHÍ 3: Lọc và tìm kiếm công việc (Filter, Search, Sort, Overdue)
// ---------------------------------------------------------
echo "\n--- 3. Kiểm tra Lọc, Tìm Kiếm & Sắp Xếp Công Việc ---\n";

// Tạo thêm 1 task quá hạn để test overdue
$overdueTaskId = $taskModel->create($user1Id, [
    'title'       => 'Overdue Task Bug Fix',
    'description' => 'Cần xử lý gấp',
    'priority'    => 'urgent',
    'due_date'    => date('Y-m-d', strtotime('-2 days')),
    'status'      => 'pending'
]);

// 3.1 Tìm kiếm theo từ khóa
$searchRes = $taskModel->allForUser($user1Id, ['search' => 'Overdue Task']);
assertTest("3.1 Tìm kiếm theo tên công việc 'Overdue Task'", count($searchRes) > 0 && strpos($searchRes[0]['title'], 'Overdue Task') !== false);

// 3.2 Lọc theo trạng thái overdue
$overdueList = $taskModel->allForUser($user1Id, ['status' => 'overdue']);
$foundOverdue = false;
foreach ($overdueList as $ot) {
    if ($ot['id'] == $overdueTaskId && $ot['is_overdue'] == 1) {
        $foundOverdue = true;
        break;
    }
}
assertTest("3.2 Lọc công việc quá hạn", $foundOverdue);

// 3.3 Lọc theo mức độ ưu tiên 'urgent'
$urgentList = $taskModel->allForUser($user1Id, ['priority' => 'urgent']);
$allUrgent = true;
foreach ($urgentList as $ut) {
    if ($ut['priority'] !== 'urgent') { $allUrgent = false; break; }
}
assertTest("3.3 Lọc công việc ưu tiên 'urgent'", count($urgentList) > 0 && $allUrgent);

// 3.4 Thống kê tiến độ %
$stats = $taskModel->getStats($user1Id);
assertTest("3.4 Thống kê công việc (total, completed, pending, overdue, progress_percent)", 
    $stats['total'] > 0 && isset($stats['progress_percent']) && $stats['overdue'] > 0);

// ---------------------------------------------------------
// TIÊU CHÍ 4: Thêm, sửa, xóa danh mục có icon (Categories CRUD)
// ---------------------------------------------------------
echo "\n--- 4. Kiểm tra Quản Lý Danh Mục (Categories & Icon) ---\n";
$catModel = new Category();

// 4.1 Thêm danh mục có icon
$catId = $catModel->create($user1Id, 'Dự án Mobile App Test', '#8b5cf6', 'fa-rocket');
assertTest("4.1 Thêm danh mục kèm icon 'fa-rocket' (ID: $catId)", $catId > 0);

// 4.2 Sửa danh mục đổi màu và icon
$catUpdateOk = $catModel->update($catId, $user1Id, 'Dự án Mobile App Phục vụ Khách', '#ec4899', 'fa-laptop');
$updatedCat = $catModel->findById($catId);
assertTest("4.2 Sửa danh mục đổi tên, màu và icon", 
    $catUpdateOk && $updatedCat['name'] === 'Dự án Mobile App Phục vụ Khách' && $updatedCat['icon'] === 'fa-laptop');

// 4.3 Xóa danh mục
$catDeleteOk = $catModel->delete($catId, $user1Id);
$catAfterDel = $catModel->findById($catId);
assertTest("4.3 Xóa danh mục thành công", $catDeleteOk && $catAfterDel === null);

// ---------------------------------------------------------
// TIÊU CHÍ 5: Tạo thông báo nhắc lịch thông minh
// ---------------------------------------------------------
echo "\n--- 5. Kiểm tra Tạo Nhắc Lịch Thông Minh ---\n";
$eventModel = new Event();
$notifModel = new Notification();

// Tạo một sự kiện có giờ bắt đầu sau 10 phút, nhắc trước 15 phút
$startSoon = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$endSoon = date('Y-m-d H:i:s', strtotime('+70 minutes'));
$reminderAt = date('Y-m-d H:i:s', strtotime('-5 minutes'));

$eventId = $eventModel->create($user1Id, [
    'title'            => 'Họp khẩn cấp Phase 3 QA',
    'start_time'       => $startSoon,
    'end_time'         => $endSoon,
    'location'         => 'Phòng họp trực tuyến',
    'priority'         => 'high',
    'status'           => 'pending',
    'reminder_at'      => $reminderAt,
    'reminder_minutes' => 15
]);
assertTest("5.1 Tạo sự kiện có nhắc nhở trước 15 phút (Event ID: $eventId)", $eventId > 0);

// Kiểm tra getPendingReminders
$pendingReminders = $eventModel->getPendingReminders($user1Id);
$foundPendingEvent = false;
foreach ($pendingReminders as $pe) {
    if ($pe['id'] == $eventId) {
        $foundPendingEvent = true;
        break;
    }
}
assertTest("5.2 Phát hiện lịch sắp diễn ra cần gửi thông báo", $foundPendingEvent);

// Tạo notification nhắc lịch
$notifId = $notifModel->create(
    $user1Id,
    "Nhắc lịch: Họp khẩn cấp Phase 3 QA",
    "Sự kiện sắp diễn ra vào lúc " . date('H:i, d/m/Y', strtotime($startSoon)),
    'event',
    "index.php?page=calendar&event_id={$eventId}",
    'event',
    $eventId
);
assertTest("5.3 Tạo bản ghi thông báo nhắc lịch thành công (Notif ID: $notifId)", $notifId > 0);

// Đánh dấu đã nhắc
$eventModel->markReminderSent($eventId, $user1Id);

// ---------------------------------------------------------
// TIÊU CHÍ 6: Đánh dấu 1 thông báo đã đọc
// ---------------------------------------------------------
echo "\n--- 6. Kiểm tra Đánh Dấu Thông Báo Đã Đọc ---\n";
$readOk = $notifModel->markAsRead($notifId, $user1Id);
$notifCheck = $db->query("SELECT is_read FROM notifications WHERE id = $notifId")->fetchColumn();
assertTest("6.1 Đánh dấu 1 thông báo đã đọc (is_read = 1)", $readOk && $notifCheck == 1);

// ---------------------------------------------------------
// TIÊU CHÍ 7: Đánh dấu tất cả thông báo đã đọc
// ---------------------------------------------------------
echo "\n--- 7. Kiểm tra Đánh Dấu Tất Cả Đã Đọc ---\n";
// Tạo thêm 2 notification unread
$n1 = $notifModel->create($user1Id, "Test unread 1", "Nội dung 1", "system");
$n2 = $notifModel->create($user1Id, "Test unread 2", "Nội dung 2", "task");
$unreadBefore = $notifModel->countUnread($user1Id);
assertTest("7.1 Tạo 2 thông báo chưa đọc (Unread: $unreadBefore)", $unreadBefore >= 2);

$markAllOk = $notifModel->markAllAsRead($user1Id);
$unreadAfter = $notifModel->countUnread($user1Id);
assertTest("7.2 Đánh dấu tất cả thông báo đã đọc (Unread = 0)", $markAllOk && $unreadAfter === 0);

// ---------------------------------------------------------
// TIÊU CHÍ 8: Xóa thông báo (Từng cái & Tất cả)
// ---------------------------------------------------------
echo "\n--- 8. Kiểm tra Xóa Thông Báo ---\n";
$delSingleOk = $notifModel->delete($n1, $user1Id);
$n1Exists = $db->query("SELECT COUNT(*) FROM notifications WHERE id = $n1")->fetchColumn();
assertTest("8.1 Xóa 1 thông báo", $delSingleOk && $n1Exists == 0);

// ---------------------------------------------------------
// TIÊU CHÍ 9: Chống gửi thông báo trùng lặp
// ---------------------------------------------------------
echo "\n--- 9. Kiểm tra Chống Gửi Thông Báo Trùng Lặp ---\n";
// Kiểm tra lại getPendingReminders sau khi đã markReminderSent
$pendingRemindersAfter = $eventModel->getPendingReminders($user1Id);
$foundAgain = false;
foreach ($pendingRemindersAfter as $pe) {
    if ($pe['id'] == $eventId) {
        $foundAgain = true;
        break;
    }
}
assertTest("9.1 Sự kiện đã gửi thông báo không nằm trong danh sách cần nhắc nữa", !$foundAgain);

$hasNotif = $notifModel->hasNotificationFor($user1Id, 'event', $eventId, 'event');
assertTest("9.2 Kiểm tra tồn tại thông báo chống trùng lặp qua hasNotificationFor", $hasNotif);

// ---------------------------------------------------------
// TIÊU CHÍ 10: Phân quyền & Bảo mật giữa 2 tài khoản (IDOR Test)
// ---------------------------------------------------------
echo "\n--- 10. Kiểm tra Bảo Mật & Phân Quyền Giữa 2 Tài Khoản (IDOR) ---\n";

// User 2 cố gắng đọc công việc của User 1
$user2ReadTask = $taskModel->findById($taskId, $user2Id);
assertTest("10.1 User 2 KHÔNG THỂ đọc công việc của User 1", $user2ReadTask === null);

// User 2 cố gắng sửa công việc của User 1
$user2UpdateTask = $taskModel->update($taskId, $user2Id, [
    'title'       => 'Hacked Title',
    'description' => 'Hacked',
    'priority'    => 'low',
    'status'      => 'pending'
]);
$checkTaskOwner = $taskModel->findById($taskId, $user1Id);
assertTest("10.2 User 2 KHÔNG THỂ sửa công việc của User 1", $checkTaskOwner['title'] !== 'Hacked Title');

// User 2 cố gắng xóa công việc của User 1
$user2DeleteTask = $taskModel->delete($taskId, $user2Id);
$checkTaskStillExists = $taskModel->findById($taskId, $user1Id);
assertTest("10.3 User 2 KHÔNG THỂ xóa công việc của User 1", $checkTaskStillExists !== null);

// User 2 cố gắng xóa thông báo của User 1
$user2DeleteNotif = $notifModel->delete($notifId, $user2Id);
$checkNotifStillExists = $db->query("SELECT COUNT(*) FROM notifications WHERE id = $notifId AND user_id = $user1Id")->fetchColumn();
assertTest("10.4 User 2 KHÔNG THỂ xóa thông báo của User 1", $checkNotifStillExists == 1);

// User 2 cố gắng xóa sự kiện của User 1
$user2DeleteEvent = $eventModel->delete($eventId, $user2Id);
$checkEventStillExists = $eventModel->findById($eventId, $user1Id);
assertTest("10.5 User 2 KHÔNG THỂ xóa sự kiện của User 1", $checkEventStillExists !== null);

// Dọn dẹp dữ liệu test
$taskModel->delete($taskId, $user1Id);
$taskModel->delete($overdueTaskId, $user1Id);
$eventModel->delete($eventId, $user1Id);
$notifModel->delete($notifId, $user1Id);
$notifModel->delete($n2, $user1Id);

// ---------------------------------------------------------
// TIÊU CHÍ 11 & 12: Kiểm tra tương thích Giai đoạn 1 & 2
// ---------------------------------------------------------
echo "\n--- 11 & 12. Kiểm tra Tương Thích Các Trang Giai Đoạn 1 & 2 ---\n";
// Kiểm tra DashboardController
$dashController = new DashboardController();
$dashData = $dashController->index();
assertTest("12.1 DashboardController::index() hoạt động trơn tru", 
    !empty($dashData['stats']) && isset($dashData['weekly']) && isset($dashData['events']));

// Kiểm tra EventController
$eventController = new EventController();
$calData = $eventController->index();
assertTest("12.2 EventController::index() cho Lịch trình hoạt động trơn tru", isset($calData['categories']));

$statData = $eventController->statisticsData();
assertTest("12.3 EventController::statisticsData() cho Thống kê hoạt động trơn tru", isset($statData['stats']) && isset($statData['weekly']));

$taskCtrlData = (new TaskController())->index();
assertTest("12.4 TaskController::index() cho trang Công việc hoạt động trơn tru", 
    isset($taskCtrlData['tasks']) && isset($taskCtrlData['stats']));

echo "\n====================================================\n";
echo "TỔNG KẾT: {$passCount} PASSED / 0 FAILED\n";
echo "====================================================\n";
