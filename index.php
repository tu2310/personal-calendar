<?php
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/DashboardController.php';
require_once __DIR__ . '/app/Controllers/EventController.php';
require_once __DIR__ . '/app/Controllers/TaskController.php';
require_once __DIR__ . '/app/Controllers/NotificationController.php';

Auth::init();

$page = $_GET['page'] ?? 'dashboard';

// 1. Định tuyến các trang Xác thực (Auth)
if ($page === 'login') {
    (new AuthController())->showLogin();
    exit;
}
if ($page === 'login-post') {
    (new AuthController())->login();
    exit;
}
if ($page === 'register') {
    (new AuthController())->showRegister();
    exit;
}
if ($page === 'register-post') {
    (new AuthController())->register();
    exit;
}
if ($page === 'logout') {
    (new AuthController())->logout();
    exit;
}

// 2. Bảo vệ tất cả các trang yêu cầu đăng nhập
Auth::requireLogin();

// 3. Định tuyến các API JSON cho FullCalendar, Events, Tasks, Categories, Notifications
// 3.1. Sự kiện & Lịch trình
if ($page === 'api-events') {
    (new EventController())->apiEvents();
    exit;
}
if ($page === 'api-event-detail') {
    (new EventController())->apiDetail();
    exit;
}
if ($page === 'api-search') {
    (new EventController())->apiSearch();
    exit;
}
if ($page === 'api-event-save') {
    (new EventController())->save();
    exit;
}
if ($page === 'api-event-time') {
    (new EventController())->updateTime();
    exit;
}
if ($page === 'api-event-delete') {
    (new EventController())->delete();
    exit;
}

// 3.2. Danh mục
if ($page === 'api-category-save') {
    (new EventController())->apiCategorySave();
    exit;
}
if ($page === 'api-category-delete') {
    (new EventController())->apiCategoryDelete();
    exit;
}

// 3.3. Công việc nâng cao (Tasks)
if ($page === 'api-tasks-list') {
    (new TaskController())->apiList();
    exit;
}
if ($page === 'api-task-detail') {
    (new TaskController())->apiDetail();
    exit;
}
if ($page === 'api-task-save') {
    (new TaskController())->save();
    exit;
}
if ($page === 'api-task-status') {
    (new TaskController())->updateStatus();
    exit;
}
if ($page === 'api-task-delete') {
    (new TaskController())->delete();
    exit;
}

// 3.4. Trung tâm thông báo & Nhắc nhở thông minh
if ($page === 'api-notifications') {
    (new NotificationController())->apiList();
    exit;
}
if ($page === 'api-notification-read') {
    (new NotificationController())->markRead();
    exit;
}
if ($page === 'api-notifications-read-all') {
    (new NotificationController())->markAllRead();
    exit;
}
if ($page === 'api-notification-delete') {
    (new NotificationController())->delete();
    exit;
}
if ($page === 'api-notifications-delete-all') {
    (new NotificationController())->deleteAll();
    exit;
}
if ($page === 'api-reminders-check') {
    (new NotificationController())->checkReminders();
    exit;
}
if ($page === 'api-notifications-test') {
    (new NotificationController())->sendTest();
    exit;
}

// 3.5. Cài đặt hệ thống
if ($page === 'api-settings-save') {
    (new EventController())->apiSettingsSave();
    exit;
}

// 4. Danh sách các trang hợp lệ trong hệ thống
$allowed = [
    'dashboard', 'calendar', 'week', 'month',
    'tasks', 'categories', 'statistics', 'share',
    'pomodoro', 'settings'
];

if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}

// 5. Chuẩn bị dữ liệu cho View theo từng Controller
$data = [];
$eventController = new EventController();
$taskController = new TaskController();

if ($page === 'dashboard') {
    $controller = new DashboardController();
    $data = $controller->index();
} elseif (in_array($page, ['calendar', 'week', 'month'], true)) {
    $data = $eventController->index();
} elseif ($page === 'tasks') {
    $data = $taskController->index();
} elseif ($page === 'categories') {
    $data = $eventController->categoriesData();
} elseif ($page === 'statistics') {
    $data = $eventController->statisticsData();
} elseif ($page === 'settings') {
    $data = $eventController->settingsData();
} elseif ($page === 'share') {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT * FROM shares WHERE user_id = :userId ORDER BY id DESC");
    $stmt->execute(['userId' => Auth::id() ?? 1]);
    $data = [
        'csrf_token' => Auth::csrfToken(),
        'shares'     => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
} elseif ($page === 'pomodoro') {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id = :userId AND DATE(completed_at) = CURDATE()");
    $stmt->execute(['userId' => Auth::id() ?? 1]);
    $cycles = (int)$stmt->fetchColumn();
    $data = [
        'csrf_token' => Auth::csrfToken(),
        'pomoCycles' => $cycles
    ];
}

$pageTitles = [
    'dashboard'  => 'Dashboard',
    'calendar'   => 'Lịch của tôi',
    'week'       => 'Lịch tuần',
    'month'      => 'Lịch tháng',
    'tasks'      => 'Quản lý công việc',
    'categories' => 'Danh mục',
    'statistics' => 'Thống kê',
    'share'      => 'Chia sẻ lịch',
    'pomodoro'   => 'Pomodoro',
    'settings'   => 'Cài đặt'
];

$pageTitle = $pageTitles[$page] ?? 'Personal Calendar';

// 6. Hiển thị Layout và Trang tương ứng
require __DIR__ . '/app/Views/layouts/header.php';
require __DIR__ . '/app/Views/pages/' . $page . '.php';
require __DIR__ . '/app/Views/layouts/footer.php';
