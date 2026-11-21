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
        /* Khóa cứng màu nền đặc cho Popover FullCalendar - Chống triệt để lỗi cache và trong suốt */
        .fc {
            --fc-page-bg-color: var(--surface, #ffffff) !important;
            --fc-neutral-bg-color: var(--surface-2, #f8fafc) !important;
        }
        .fc-theme-standard .fc-popover,
        .fc .fc-popover,
        div.fc-popover {
            background: #ffffff !important;
            background-color: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 14px !important;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.22), 0 6px 16px rgba(15, 23, 42, 0.08) !important;
            z-index: 99999 !important;
            overflow: hidden !important;
            min-width: 260px !important;
            max-width: 330px !important;
            opacity: 1 !important;
        }
        .fc-theme-standard .fc-popover-header,
        .fc .fc-popover-header {
            background: #f8fafc !important;
            background-color: #f8fafc !important;
            padding: 10px 14px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .fc .fc-popover-title {
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #1e293b !important;
        }
        .fc .fc-popover-close {
            cursor: pointer !important;
            font-size: 15px !important;
            color: #64748b !important;
            padding: 3px 6px !important;
            border-radius: 6px !important;
            opacity: 0.8 !important;
            line-height: 1 !important;
        }
        .fc .fc-popover-close:hover {
            opacity: 1 !important;
            color: #0f172a !important;
            background: rgba(0, 0, 0, 0.08) !important;
        }
        .fc-theme-standard .fc-popover-body,
        .fc .fc-popover-body {
            background: #ffffff !important;
            background-color: #ffffff !important;
            padding: 10px 12px !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 6px !important;
            max-height: 280px !important;
            overflow-y: auto !important;
        }
        .fc .fc-popover-body .fc-daygrid-event {
            margin: 0 !important;
            padding: 6px 10px !important;
            border-radius: 8px !important;
        }
        .fc .fc-daygrid-more-link {
            font-size: 11px !important;
            font-weight: 700 !important;
            color: #6658e8 !important;
            background: rgba(102, 88, 232, 0.09) !important;
            padding: 3px 9px !important;
            border-radius: 6px !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            margin-top: 3px !important;
            cursor: pointer !important;
        }
        .fc .fc-daygrid-more-link:hover {
            background: #6658e8 !important;
            color: #ffffff !important;
        }
        body.dark .fc {
            --fc-page-bg-color: #1e293b !important;
            --fc-neutral-bg-color: #0f172a !important;
        }
        body.dark .fc-theme-standard .fc-popover,
        body.dark .fc .fc-popover,
        body.dark div.fc-popover {
            background: #1e293b !important;
            background-color: #1e293b !important;
            border-color: #334155 !important;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6) !important;
        }
        body.dark .fc-theme-standard .fc-popover-header,
        body.dark .fc .fc-popover-header {
            background: #0f172a !important;
            background-color: #0f172a !important;
            border-bottom-color: #334155 !important;
        }
        body.dark .fc .fc-popover-title {
            color: #f1f5f9 !important;
        }
        body.dark .fc .fc-popover-close:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
        }
        body.dark .fc-theme-standard .fc-popover-body,
        body.dark .fc .fc-popover-body {
            background: #1e293b !important;
            background-color: #1e293b !important;
        }
        body.dark .fc .fc-daygrid-more-link {
            background: rgba(102, 88, 232, 0.22) !important;
            color: #c4b5fd !important;
        }
        body.dark .fc .fc-daygrid-more-link:hover {
            background: #6658e8 !important;
            color: #ffffff !important;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php">
            <span class="brand-icon"><i class="fa-solid fa-calendar-check"></i></span>
            <span>Personal <b>Calendar</b></span>
        </a>
        <div class="profile-mini">
            <div class="avatar"><?= htmlspecialchars($initial) ?></div>
            <div>
                <strong>Xin chào, <?= htmlspecialchars($currentUserName) ?>!</strong>
                <small><span class="online-dot"></span> Đang hoạt động</small>
            </div>
        </div>
        <div class="nav-caption">KHÔNG GIAN LÀM VIỆC</div>
        <nav class="side-nav">
            <?php
            $menus = [
                ['dashboard', 'fa-house', 'Dashboard'],
                ['calendar', 'fa-calendar-days', 'Lịch của tôi'],
                ['week', 'fa-calendar-week', 'Lịch tuần'],
                ['month', 'fa-calendar', 'Lịch tháng'],
                ['tasks', 'fa-list-check', 'Công việc'],
                ['categories', 'fa-layer-group', 'Danh mục'],
                ['statistics', 'fa-chart-column', 'Thống kê'],
                ['share', 'fa-share-nodes', 'Chia sẻ lịch']
            ];
            foreach ($menus as [$key, $icon, $label]):
            ?>
            <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>">
                <i class="fa-solid <?= $icon ?>"></i><span><?= $label ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="nav-caption extra-caption">CÁ NHÂN</div>
        <nav class="side-nav">
            <a class="nav-link <?= $page === 'pomodoro' ? 'active' : '' ?>" href="index.php?page=pomodoro"><i class="fa-solid fa-stopwatch"></i><span>Pomodoro</span><span class="nav-new">NEW</span></a>
            <a class="nav-link <?= $page === 'settings' ? 'active' : '' ?>" href="index.php?page=settings"><i class="fa-solid fa-gear"></i><span>Cài đặt</span></a>
        </nav>
        <div class="sidebar-bottom">
            <div class="upgrade-card">
                <div class="upgrade-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                <strong>Làm chủ thời gian</strong>
                <p>Lên kế hoạch nhỏ, tạo nên kết quả lớn.</p>
                <a href="index.php?page=calendar">Xem lịch của bạn <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <a class="nav-link logout-link" href="index.php?page=logout" id="sidebarLogout"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Đăng xuất</span></a>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <button class="icon-btn mobile-menu" id="mobileMenu" aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
            <div class="search-box position-relative" id="topbarSearchBox">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="topbarSearchInput" placeholder="Tìm kiếm lịch trình, công việc..." aria-label="Tìm kiếm" autocomplete="off" spellcheck="false">
                <button type="button" class="btn-clear-search d-none" id="btnClearSearch" title="Xóa tìm kiếm (Esc)" aria-label="Xóa từ khóa">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <kbd id="searchKbdShortcut">⌘ K</kbd>

                <!-- DROPDOWN KẾT QUẢ LIVE SEARCH -->
                <div class="live-search-dropdown shadow-lg d-none" id="liveSearchDropdown" role="listbox" aria-label="Kết quả tìm kiếm">
                    <div class="live-search-header d-flex justify-content-between align-items-center">
                        <span class="live-search-header-title"><i class="fa-solid fa-bolt me-1 text-primary"></i> Kết quả tìm kiếm</span>
                        <span class="live-search-badge" id="liveSearchCount">0 kết quả</span>
                    </div>
                    <div class="live-search-list" id="liveSearchList">
                        <!-- Render danh sách kết quả qua JS -->
                    </div>
                    <div class="live-search-footer d-flex justify-content-between align-items-center">
                        <span><kbd>Esc</kbd> để đóng</span>
                        <span><i class="fa-regular fa-hand-pointer me-1"></i> Bấm để xem chi tiết</span>
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
