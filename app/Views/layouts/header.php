<?php
$currentUser = Auth::user();
$currentUserName = $currentUser['name'] ?? 'Người dùng';
$initial = mb_strtoupper(mb_substr($currentUserName, 0, 1, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(Auth::csrfToken()) ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Personal Calendar') ?> | Personal Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <?php
    $cssToastVer = @file_exists('public/assets/css/toast.css') ? filemtime('public/assets/css/toast.css') : time();
    $cssStyleVer = @file_exists('public/assets/css/style.css') ? filemtime('public/assets/css/style.css') : time();
    ?>
    <link href="public/assets/css/toast.css?v=<?= $cssToastVer ?>" rel="stylesheet">
    <link href="public/assets/css/style.css?v=<?= $cssStyleVer ?>" rel="stylesheet">
    <style>
        html.dark, html.dark body {
            background: #141415;
            color: #ededed;
        }
        .fc-theme-standard .fc-popover,
        .fc .fc-popover,
        div.fc-popover {
            background: var(--surface, #ffffff) !important;
            border: 1px solid var(--line, rgba(24, 24, 27, 0.1)) !important;
            border-radius: 12px !important;
            box-shadow: var(--shadow-float, 0 12px 32px rgba(24, 24, 27, 0.08)) !important;
            z-index: 99999 !important;
            opacity: 1 !important;
        }
        .fc-theme-standard .fc-popover-header,
        .fc .fc-popover-header {
            background: var(--surface-2, #f1f0ec) !important;
            border-bottom: 1px solid var(--line, rgba(24, 24, 27, 0.1)) !important;
        }
        .fc-theme-standard .fc-popover-body,
        .fc .fc-popover-body {
            background: var(--surface, #ffffff) !important;
        }
    </style>
    <script>
        if (localStorage.getItem('personalCalendarTheme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body>
<script>
    if (document.documentElement.classList.contains('dark')) {
        document.body.classList.add('dark');
    }
</script>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php">
            <span class="brand-icon"><i class="fa-solid fa-calendar-check"></i></span>
            <span>Personal <b>Calendar</b></span>
        </a>
        <div class="profile-mini">
            <div class="avatar"><?= htmlspecialchars($initial) ?></div>
            <div style="min-width:0;">
                <strong><?= htmlspecialchars($currentUserName) ?></strong>
                <small><span class="online-dot"></span>Đang hoạt động</small>
            </div>
        </div>
        <div class="nav-caption">Không gian làm việc</div>
        <nav class="side-nav">
            <?php
            $menus = [
                ['dashboard', 'fa-house', 'Tổng quan'],
                ['calendar', 'fa-calendar-days', 'Lịch của tôi'],
                ['week', 'fa-calendar-week', 'Lịch tuần'],
                ['month', 'fa-calendar', 'Lịch tháng'],
                ['tasks', 'fa-list-check', 'Công việc'],
                ['habits', 'fa-chart-column', 'Thói quen & Thống kê'],
                ['agenda', 'fa-rectangle-list', 'Danh sách lịch']
            ];
            foreach ($menus as [$key, $icon, $label]):
            ?>
            <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>">
                <i class="fa-solid <?= $icon ?>"></i><span><?= $label ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="nav-caption extra-caption">Cá nhân</div>
        <nav class="side-nav">
            <a class="nav-link <?= $page === 'trash' ? 'active' : '' ?>" href="index.php?page=trash"><i class="fa-solid fa-trash-can"></i><span>Thùng rác</span></a>
            <a class="nav-link <?= $page === 'settings' ? 'active' : '' ?>" href="index.php?page=settings"><i class="fa-solid fa-gear"></i><span>Cài đặt</span></a>
        </nav>
        <div class="sidebar-bottom">
            <a class="nav-link logout-link" href="index.php?page=logout" id="sidebarLogout"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Đăng xuất</span></a>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <button class="icon-btn mobile-menu" id="mobileMenu" aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
            <div class="search-box position-relative" id="topbarSearchBox">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="topbarSearchInput" placeholder="Tìm lịch trình, công việc" aria-label="Tìm kiếm" autocomplete="off" spellcheck="false">
                <button type="button" class="btn-clear-search d-none" id="btnClearSearch" title="Xóa tìm kiếm (Esc)" aria-label="Xóa từ khóa">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <kbd id="searchKbdShortcut">Ctrl K</kbd>

                <!-- DROPDOWN KẾT QUẢ LIVE SEARCH -->
                <div class="live-search-dropdown d-none" id="liveSearchDropdown" role="listbox" aria-label="Kết quả tìm kiếm">
                    <div class="live-search-header d-flex justify-content-between align-items-center">
                        <span class="live-search-header-title">Kết quả tìm kiếm</span>
                        <span class="live-search-badge" id="liveSearchCount">0 kết quả</span>
                    </div>
                    <div class="live-search-list" id="liveSearchList">
                        <!-- Render danh sách kết quả qua JS -->
                    </div>
                    <div class="live-search-footer d-flex justify-content-between align-items-center">
                        <span><kbd>Esc</kbd> để đóng</span>
                        <span>Bấm để xem chi tiết</span>
                    </div>
                </div>
            </div>
            <div class="topbar-actions position-relative">
                <button class="icon-btn theme-toggle" id="themeToggle" title="Đổi giao diện"><i class="fa-regular fa-moon"></i></button>
                
                <!-- Notification Bell & Center -->
                <div class="notification-wrapper position-relative">
                    <button class="icon-btn notification-btn" id="topbarNotifBtn" title="Thông báo" aria-expanded="false" aria-haspopup="true">
                        <i class="fa-regular fa-bell"></i>
                        <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
                    </button>

                    <!-- Notification Dropdown Menu -->
                    <div class="notification-dropdown shadow-lg" id="notificationDropdown" style="display: none;">
                        <div class="notif-header d-flex align-items-center justify-content-between p-3 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold fs-6">Trung tâm thông báo</span>
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-2" id="notifHeaderCount">0</span>
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-light text-primary py-1 px-2" id="btnMarkAllRead" title="Đánh dấu tất cả đã đọc" style="font-size: 11px;">
                                    <i class="fa-solid fa-check-double me-1"></i>Đã đọc tất cả
                                </button>
                                <button type="button" class="btn btn-sm btn-light text-danger py-1 px-2" id="btnClearAllNotifs" title="Xóa tất cả thông báo" style="font-size: 11px;">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="notif-filter-tabs d-flex px-3 pt-2 pb-2 border-bottom gap-1">
                            <button type="button" class="btn btn-sm notif-tab active" data-type="all">Tất cả</button>
                            <button type="button" class="btn btn-sm notif-tab" data-type="event">Nhắc lịch</button>
                            <button type="button" class="btn btn-sm notif-tab" data-type="task">Công việc</button>
                            <button type="button" class="btn btn-sm notif-tab" data-type="system">Hệ thống</button>
                        </div>

                        <div class="notif-body" id="notificationListContainer">
                            <div class="p-4 text-center text-muted" id="notifLoading">
                                <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                                <div style="font-size: 12px;">Đang kiểm tra thông báo...</div>
                            </div>
                        </div>

                        <div class="notif-footer text-center p-2 border-top">
                            <small class="text-muted" style="font-size: 11px;">
                                <i class="fa-solid fa-shield-halved text-success me-1"></i>Chỉ nhắc nhở sự kiện thuộc tài khoản của bạn
                            </small>
                        </div>
                    </div>
                </div>

                <div class="top-date"><i class="fa-regular fa-calendar"></i><span id="todayLabel">Hôm nay</span></div>
                <div class="top-avatar" title="<?= htmlspecialchars($currentUserName) ?>"><?= htmlspecialchars($initial) ?></div>
            </div>
        </header>
        <div class="page-content">
