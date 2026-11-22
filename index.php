<?php
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/DashboardController.php';
require_once __DIR__ . '/app/Controllers/EventController.php';
require_once __DIR__ . '/app/Controllers/TaskController.php';
require_once __DIR__ . '/app/Controllers/NotificationController.php';
require_once __DIR__ . '/app/Controllers/HabitController.php';

Auth::init();

$rawPage = $_GET['page'] ?? 'dashboard';
$page = is_string($rawPage) ? trim($rawPage) : 'dashboard';

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
if (str_starts_with($page, 'api-') && !Auth::check()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'], JSON_UNESCAPED_UNICODE);
    exit;
}
Auth::requireLogin();
Auth::csrfToken();
if (str_starts_with($page, 'api-') && $page !== 'api-settings-save') {
    session_write_close();
}

// 3. Định tuyến các API JSON (bọc try/catch ẩn SQL & stacktrace khi lỗi bất ngờ)
if (str_starts_with($page, 'api-')) {
    try {
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
        if ($page === 'api-task-restore') {
            (new TaskController())->restore();
            exit;
        }
        if ($page === 'api-task-force-delete') {
            (new TaskController())->forceDelete();
            exit;
        }
        if ($page === 'api-trash-empty') {
            (new TaskController())->emptyTrash();
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
            (new NotificationController())->apiTest();
            exit;
        }

        // 3.5. Thói quen (Habits)
        if ($page === 'api-habit-save') {
            (new HabitController())->save();
            exit;
        }
        if ($page === 'api-habit-toggle') {
            (new HabitController())->toggle();
            exit;
        }
        if ($page === 'api-habit-delete') {
            (new HabitController())->delete();
            exit;
        }

        // 3.6. Cài đặt hệ thống
        if ($page === 'api-settings-save') {
            (new EventController())->apiSettingsSave();
            exit;
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Endpoint API không tồn tại.'], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (\Throwable $e) {
        error_log(sprintf(
            '[API Error] page=%s user=%s: %s in %s:%d',
            $page,
            (string)(Auth::id() ?? 'guest'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Đã xảy ra lỗi hệ thống.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 4. Danh sách các trang hợp lệ trong hệ thống
if ($page === 'today') {
    $page = 'dashboard';
} elseif ($page === 'agenda') {
    $page = 'tasks';
} elseif ($page === 'statistics') {
    $page = 'habits';
}

$allowed = [
    'dashboard', 'calendar', 'week', 'month', 'agenda',
    'tasks', 'upcoming', 'habits', 'share',
    'pomodoro', 'trash', 'settings'
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
} elseif (in_array($page, ['calendar', 'week', 'month', 'agenda'], true)) {
    $data = $eventController->index();
} elseif ($page === 'tasks') {
    $data = $taskController->index();
} elseif ($page === 'upcoming') {
    $data = $taskController->upcomingData();
} elseif ($page === 'trash') {
    $data = $taskController->trashData();
} elseif ($page === 'habits') {
    $data = (new HabitController())->index();
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
    'dashboard'  => 'Hôm nay',
    'calendar'   => 'Lịch',
    'week'       => 'Lịch tuần',
    'month'      => 'Lịch tháng',
    'agenda'     => 'Tasks',
    'tasks'      => 'Tasks',
    'upcoming'   => 'Sắp tới',
    'habits'     => 'Thói quen & Thống kê',
    'share'      => 'Chia sẻ lịch',
    'pomodoro'   => 'Pomodoro',
    'trash'      => 'Thùng rác',
    'settings'   => 'Cài đặt'
];

$pageTitle = $pageTitles[$page] ?? 'Personal Calendar';

// 6. Hiển thị Layout và Trang tương ứng
require __DIR__ . '/app/Views/layouts/header.php';
require __DIR__ . '/app/Views/pages/' . $page . '.php';
require __DIR__ . '/app/Views/layouts/footer.php';
