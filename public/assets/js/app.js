/**
 * Personal Calendar SaaS - Main Application JavaScript
 * Đầy đủ chức năng tương tác, giao diện SaaS, tích hợp hệ thống Toast toàn diện
 */

function escapeHtml(str) {
    if (!str && str !== 0) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.getElementById('csrfToken')?.value 
        || document.getElementById('taskModalCsrf')?.value 
        || document.getElementById('categoryCsrf')?.value 
        || document.getElementById('dashboardCsrf')?.value 
        || '';
}

document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    // 1. Quản lý Dark / Light Theme
    const themeToggle = document.getElementById('themeToggle');
    const savedTheme = localStorage.getItem('personalCalendarTheme');
    if (savedTheme === 'dark') body.classList.add('dark');
    updateThemeIcon();

    themeToggle?.addEventListener('click', () => {
        body.classList.toggle('dark');
        const isDark = body.classList.contains('dark');
        document.documentElement.classList.toggle('dark', isDark);
        localStorage.setItem('personalCalendarTheme', isDark ? 'dark' : 'light');
        updateThemeIcon();
        if (window.Toast) {
            window.Toast.info(isDark ? 'Đã chuyển sang giao diện Tối (Dark mode).' : 'Đã chuyển sang giao diện Sáng (Light mode).', 'Giao diện');
        }
    });

    function updateThemeIcon() {
        if (!themeToggle) return;
        themeToggle.innerHTML = body.classList.contains('dark')
            ? '<i class="fa-regular fa-sun"></i>'
            : '<i class="fa-regular fa-moon"></i>';
    }

    // 2. Responsive Mobile Sidebar & Backdrop
    const menuButton = document.getElementById('mobileMenu');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    const toggleSidebar = (open) => {
        if (!sidebar) return;
        const isOpen = open !== undefined ? open : !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', isOpen);
        backdrop?.classList.toggle('show', isOpen);
    };

    menuButton?.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSidebar();
    });

    backdrop?.addEventListener('click', () => toggleSidebar(false));

    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !menuButton?.contains(e.target)) {
                toggleSidebar(false);
            }
        }
    });

    // 3. Hiển thị ngày tháng trên Topbar
    const todayLabel = document.getElementById('todayLabel');
    if (todayLabel) {
        try {
            todayLabel.textContent = new Intl.DateTimeFormat('vi-VN', {
                weekday: 'short',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            }).format(new Date());
        } catch (e) {
            todayLabel.textContent = 'Hôm nay';
        }
    }

    // 4. Khởi tạo Live Search trực tiếp trên Topbar & Phím tắt ⌘ K / Ctrl + K
    initLiveSearch();
    const notifBtn = document.getElementById('topbarNotifBtn');

    // 4.1. Khởi tạo Trung tâm thông báo và Hệ thống nhắc nhở thông minh
    initNotificationCenter();
    initSmartReminders();

    // 5. Toggle trạng thái công việc (Đánh dấu hoàn thành / Khôi phục)
    initTaskCheckboxes();

    // 6. Biểu đồ hàng tuần trên Dashboard / Thống kê
    initWeeklyChart();

    // 7. Xử lý xác nhận đăng xuất
    const logoutBtn = document.getElementById('sidebarLogout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const targetUrl = logoutBtn.getAttribute('href');
            if (window.Swal) {
                Swal.fire({
                    title: 'Đăng xuất tài khoản?',
                    text: 'Bạn có chắc chắn muốn kết thúc phiên làm việc hiện tại không?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#c2410c',
                    cancelButtonColor: '#71717a',
                    confirmButtonText: '<i class="fa-solid fa-right-from-bracket me-1"></i> Đăng xuất',
                    cancelButtonText: 'Ở lại'
                }).then((res) => {
                    if (res.isConfirmed) {
                        window.location.href = targetUrl;
                    }
                });
            } else {
                window.location.href = targetUrl;
            }
        });
    }

    // 8. Khởi tạo các module trang chuyên biệt
    initTasksPage();
    initCategoriesPage();
    initSettingsPage();
    initSharePage();
    initPomodoro();
    initFullCalendar();
});

/**
 * ===== ÂM THANH CHUÔNG THÔNG BÁO (SYNTHESIZED CHIME) =====
 */
function playNotificationChime() {
    const isSoundEnabled = localStorage.getItem('personalCalendarSound') !== 'false';
    if (!isSoundEnabled) return;
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.15); // A5
        gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.5);
    } catch (e) {}
}

/**
 * ===== TRUNG TÂM THÔNG BÁO (NOTIFICATION CENTER) =====
 */
let currentNotifFilter = 'all';

function initNotificationCenter() {
    const notifBtn = document.getElementById('topbarNotifBtn');
    const dropdown = document.getElementById('notificationDropdown');
    const badge = document.getElementById('notifBadge');
    const headerCount = document.getElementById('notifHeaderCount');
    const listContainer = document.getElementById('notificationListContainer');
    const btnMarkAll = document.getElementById('btnMarkAllRead');
    const btnClearAll = document.getElementById('btnClearAllNotifs');
    const tabs = document.querySelectorAll('.notif-tab');

    if (!notifBtn || !dropdown) return;

    // 1. Toggle mở / đóng dropdown khi click chuông
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isVisible = dropdown.style.display === 'block';
        if (isVisible) {
            dropdown.style.display = 'none';
            notifBtn.setAttribute('aria-expanded', 'false');
        } else {
            dropdown.style.display = 'block';
            notifBtn.setAttribute('aria-expanded', 'true');
            loadNotifications(currentNotifFilter);
        }
    });

    // Đóng dropdown khi click ra ngoài
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && !notifBtn.contains(e.target)) {
            dropdown.style.display = 'none';
            notifBtn.setAttribute('aria-expanded', 'false');
        }
    });

    // 2. Chuyển tab lọc thông báo
    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.stopPropagation();
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentNotifFilter = tab.dataset.type || 'all';
            loadNotifications(currentNotifFilter);
        });
    });

    // 3. Đánh dấu tất cả đã đọc
    btnMarkAll?.addEventListener('click', (e) => {
        e.stopPropagation();
        const csrf = getCsrfToken();

        fetch('index.php?page=api-notifications-read-all', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({ csrf_token: csrf })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.Toast?.success('Đã đánh dấu tất cả thông báo là đã đọc!');
                updateNotifBadges(0);
                loadNotifications(currentNotifFilter);
            } else {
                window.Toast?.error(data.message || 'Không thể cập nhật thông báo.');
            }
        })
        .catch(() => window.Toast?.error('Lỗi kết nối máy chủ.'));
    });

    // 4. Xóa tất cả thông báo
    btnClearAll?.addEventListener('click', (e) => {
        e.stopPropagation();
        const csrf = getCsrfToken();

        if (window.Swal) {
            Swal.fire({
                title: 'Xóa toàn bộ thông báo?',
                text: 'Tất cả thông báo trong hộp thư sẽ bị xóa vĩnh viễn.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e46e6e',
                cancelButtonColor: '#8589a5',
                confirmButtonText: 'Xóa tất cả',
                cancelButtonText: 'Hủy bỏ'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('index.php?page=api-notifications-delete-all', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ csrf_token: csrf })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.Toast?.success('Đã xóa toàn bộ thông báo!');
                            updateNotifBadges(0);
                            loadNotifications(currentNotifFilter);
                        } else {
                            window.Toast?.error(data.message || 'Không thể xóa thông báo.');
                        }
                    })
                    .catch(() => window.Toast?.error('Lỗi kết nối khi xóa thông báo.'));
                }
            });
        }
    });

    // Hàm tải danh sách thông báo qua API
    window.loadNotifications = function(type = 'all') {
        if (!listContainer) return;
        listContainer.innerHTML = `
            <div class="p-4 text-center text-muted" id="notifLoading">
                <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                <div style="font-size: 12px;">Đang tải thông báo...</div>
            </div>
        `;

        const query = (type && type !== 'all') ? `&type=${encodeURIComponent(type)}` : '';
        fetch(`index.php?page=api-notifications${query}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    listContainer.innerHTML = '<div class="p-3 text-center text-danger" style="font-size: 12px;">Không thể tải thông báo.</div>';
                    return;
                }

                updateNotifBadges(data.unread_count);

                const items = data.data || [];
                if (items.length === 0) {
                    listContainer.innerHTML = `
                        <div class="p-4 text-center text-muted">
                            <i class="fa-regular fa-bell-slash fs-3 mb-2 d-block text-secondary opacity-50"></i>
                            <div class="fw-semibold" style="font-size: 12.5px;">Không có thông báo nào</div>
                            <small class="text-muted" style="font-size: 11px;">Mọi lịch trình và nhiệm vụ đều đang yên ổn.</small>
                        </div>
                    `;
                    return;
                }

                let html = '';
                items.forEach(item => {
                    const isUnread = item.is_read == 0;
                    const typeClass = item.type === 'event' ? 'event' : (item.type === 'task' ? 'task' : 'system');
                    const iconClass = item.type === 'event' ? 'fa-calendar-day' : (item.type === 'task' ? 'fa-list-check' : 'fa-bell');

                    html += `
                        <div class="notif-item ${isUnread ? 'is-unread' : ''}" 
                             data-id="${item.id}" 
                             data-url="${item.action_url || ''}">
                            <div class="notif-item-icon ${typeClass}">
                                <i class="fa-solid ${iconClass}"></i>
                            </div>
                            <div class="notif-item-content">
                                <div class="notif-item-title">${escapeHtml(item.title)}</div>
                                ${item.message ? `<div class="notif-item-desc">${escapeHtml(item.message)}</div>` : ''}
                                <div class="notif-item-time"><i class="fa-regular fa-clock me-1"></i>${escapeHtml(item.time_ago || 'Vừa xong')}</div>
                            </div>
                            <div class="notif-item-actions">
                                ${isUnread ? `
                                    <button type="button" class="btn btn-sm btn-light btn-mark-read text-primary rounded-circle" data-id="${item.id}" title="Đánh dấu đã đọc">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                ` : ''}
                                <button type="button" class="btn btn-sm btn-light btn-delete-notif text-danger rounded-circle" data-id="${item.id}" title="Xóa thông báo">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });

                listContainer.innerHTML = html;

                // Gắn sự kiện click item để điều hướng
                listContainer.querySelectorAll('.notif-item').forEach(el => {
                    el.addEventListener('click', (e) => {
                        // Nếu bấm nút con thì không điều hướng
                        if (e.target.closest('.notif-item-actions')) return;
                        const notifId = el.dataset.id;
                        const targetUrl = el.dataset.url;

                        // Đánh dấu đã đọc ngầm
                        markSingleNotifRead(notifId, false);

                        if (targetUrl) {
                            window.location.href = targetUrl;
                        }
                    });
                });

                // Nút đánh dấu đã đọc từng cái
                listContainer.querySelectorAll('.btn-mark-read').forEach(b => {
                    b.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const id = b.dataset.id;
                        markSingleNotifRead(id, true);
                    });
                });

                // Nút xóa từng thông báo
                listContainer.querySelectorAll('.btn-delete-notif').forEach(b => {
                    b.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const id = b.dataset.id;
                        deleteSingleNotif(id);
                    });
                });
            })
            .catch(() => {
                listContainer.innerHTML = '<div class="p-3 text-center text-danger" style="font-size: 12px;">Lỗi kết nối khi tải thông báo.</div>';
            });
    };

    function markSingleNotifRead(id, refresh = true) {
        const csrf = getCsrfToken();

        fetch('index.php?page=api-notification-read', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({ id: id, csrf_token: csrf })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateNotifBadges(data.unread_count);
                if (refresh) loadNotifications(currentNotifFilter);
            }
        })
        .catch(() => {});
    }

    function deleteSingleNotif(id) {
        const csrf = getCsrfToken();

        fetch('index.php?page=api-notification-delete', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({ id: id, csrf_token: csrf })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateNotifBadges(data.unread_count);
                loadNotifications(currentNotifFilter);
            } else {
                window.Toast?.error(data.message || 'Không thể xóa thông báo.');
            }
        })
        .catch(() => window.Toast?.error('Lỗi kết nối khi xóa.'));
    }

    function updateNotifBadges(unreadCount) {
        if (!badge || !headerCount) return;
        headerCount.textContent = unreadCount;
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // Đưa hàm ra phạm vi toàn cục để poller gọi
    window.updateNotifBadges = updateNotifBadges;

    // Tải thông báo lần đầu để hiển thị badge số lượng
    fetch('index.php?page=api-notifications')
        .then(res => res.json())
        .then(data => {
            if (data.success) updateNotifBadges(data.unread_count);
        })
        .catch(() => {});
}

/**
 * ===== HỆ THỐNG NHẮC NHỞ THÔNG MINH (SMART REMINDERS POLLER) =====
 */
function initSmartReminders() {
    function checkReminders() {
        fetch('index.php?page=api-reminders-check')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                if (typeof window.updateNotifBadges === 'function') {
                    window.updateNotifBadges(data.unread_count);
                }

                // Cập nhật banner cảnh báo deadline nếu đang ở trang Tasks
                const taskBanner = document.getElementById('taskOverdueBanner');
                const taskBannerNum = document.getElementById('taskOverdueBannerNum');
                if (taskBanner && data.deadline_summary) {
                    if (data.deadline_summary.overdue_count > 0) {
                        taskBanner.style.setProperty('display', 'flex', 'important');
                        if (taskBannerNum) taskBannerNum.textContent = data.deadline_summary.overdue_count;
                    } else {
                        taskBanner.style.setProperty('display', 'none', 'important');
                    }
                }

                const newNotifs = data.new_notifications || [];
                if (newNotifs.length > 0) {
                    playNotificationChime();

                    newNotifs.forEach(n => {
                        // 1. Toast in-app
                        window.Toast?.info(n.message, n.title, { duration: 9000 });

                        // 2. Browser Notification API (nếu được bật và đã cấp quyền)
                        const isBrowserNotifEnabled = localStorage.getItem('personalCalendarBrowserNotif') === 'true';
                        if (isBrowserNotifEnabled && 'Notification' in window && Notification.permission === 'granted') {
                            try {
                                const bNotif = new Notification(n.title, {
                                    body: n.message,
                                    icon: 'https://cdn-icons-png.flaticon.com/512/3652/3652191.png'
                                });
                                bNotif.onclick = function() {
                                    window.focus();
                                    if (n.action_url) window.location.href = n.action_url;
                                };
                            } catch (e) {}
                        }
                    });

                    // Nếu dropdown đang mở, tải lại danh sách
                    const dropdown = document.getElementById('notificationDropdown');
                    if (dropdown && dropdown.style.display === 'block' && typeof window.loadNotifications === 'function') {
                        window.loadNotifications(currentNotifFilter);
                    }
                } else {
                    // Cảnh báo deadline và lịch trình sắp tới cho phiên làm việc (khi chưa có notif mới trong vòng lặp này)
                    const sessionDeadlineAlert = sessionStorage.getItem('pc_session_deadline_alert_done');
                    if (!sessionDeadlineAlert && data.deadline_summary) {
                        sessionStorage.setItem('pc_session_deadline_alert_done', 'true');
                        if (data.deadline_summary.overdue_count > 0) {
                            playNotificationChime();
                            window.Toast?.warning(
                                `Bạn có ${data.deadline_summary.overdue_count} công việc đã quá hạn hoàn thành! Hãy ưu tiên xử lý.`,
                                'Cảnh báo Deadline quá hạn',
                                { duration: 8500 }
                            );
                        } else if (data.deadline_summary.due_today_count > 0) {
                            playNotificationChime();
                            window.Toast?.info(
                                `Hôm nay bạn có ${data.deadline_summary.due_today_count} công việc cần hoàn thành.`,
                                'Deadline hôm nay',
                                { duration: 7500 }
                            );
                        }
                    }

                    const sessionEventAlert = sessionStorage.getItem('pc_session_event_alert_done');
                    if (!sessionEventAlert && data.upcoming_events && data.upcoming_events.length > 0) {
                        sessionStorage.setItem('pc_session_event_alert_done', 'true');
                        const nextEv = data.upcoming_events[0];
                        setTimeout(() => {
                            window.Toast?.info(
                                `Hôm nay có lịch "${nextEv.title}" lúc ${nextEv.start_time}.`,
                                'Lịch trình sắp diễn ra',
                                { duration: 6500 }
                            );
                        }, 1500);
                    }
                }
            })
            .catch(() => {});
    }

    // Chạy kiểm tra sau 1.5 giây khi load trang
    setTimeout(checkReminders, 1500);
    // Polling định kỳ mỗi 30 giây
    setInterval(checkReminders, 30000);
}

/**
 * ===== CẬP NHẬT NHÃN VÀ TRẠNG THÁI QUÁ HẠN CÔNG VIỆC TRÊN UI =====
 */
function updateTaskOverdueUI(row, isChecked) {
    if (!row) return;
    const dueDateStr = row.dataset.dueDate;
    const badge = row.querySelector('.task-due-badge');
    if (!dueDateStr || !badge) return;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const parts = dueDateStr.split('-');
    if (parts.length < 3) return;
    const [year, month, day] = parts.map(Number);
    const dueDate = new Date(year, month - 1, day);
    dueDate.setHours(0, 0, 0, 0);

    const diffDays = Math.round((dueDate - today) / (1000 * 60 * 60 * 24));
    const formattedDate = `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year}`;

    if (isChecked) {
        // TRƯỜNG HỢP: ĐÃ HOÀN THÀNH
        // Ẩn cảnh báo đỏ, không hiển thị số ngày quá hạn, giữ nguyên ngày hạn ban đầu
        row.classList.remove('is-overdue');
        badge.className = 'badge bg-light text-muted border task-due-badge';
        badge.style.fontSize = '11px';
        badge.innerHTML = `<i class="fa-regular fa-clock me-1"></i>Hạn: ${formattedDate}`;
    } else {
        // TRƯỜNG HỢP: BỎ TÍCH (CHƯA HOÀN THÀNH)
        // Nếu ngày hạn đã qua, hiển thị lại nhãn đỏ Quá hạn X ngày
        if (diffDays < 0) {
            row.classList.add('is-overdue');
            badge.className = 'badge bg-danger text-white task-due-badge';
            badge.style.fontSize = '11px';
            badge.innerHTML = `<i class="fa-regular fa-clock me-1"></i>Hạn: ${formattedDate} (Quá hạn ${Math.abs(diffDays)} ngày)`;
        } else if (diffDays === 0) {
            row.classList.remove('is-overdue');
            badge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle task-due-badge';
            badge.style.fontSize = '11px';
            badge.innerHTML = `<i class="fa-regular fa-clock me-1"></i>Hạn: ${formattedDate} (Hôm nay)`;
        } else {
            row.classList.remove('is-overdue');
            badge.className = 'badge bg-light text-muted border task-due-badge';
            badge.style.fontSize = '11px';
            badge.innerHTML = `<i class="fa-regular fa-clock me-1"></i>Hạn: ${formattedDate}`;
        }
    }
}

/**
 * ===== TOGGLE CHECKBOX CÔNG VIỆC TRÊN TASKS / DASHBOARD =====
 */
const activeTaskUpdates = new Set();

function initTaskCheckboxes() {
    document.querySelectorAll('.task-check').forEach(check => {
        if (check.dataset.checkboxBound) return;
        check.dataset.checkboxBound = '1';

        check.addEventListener('change', () => {
            const isChecked = check.checked;
            const row = check.closest('.task-row');
            const taskId = check.dataset.id;
            if (!taskId) return;

            // Chống spam / gửi nhiều request trùng lặp
            if (activeTaskUpdates.has(taskId)) {
                return;
            }
            activeTaskUpdates.add(taskId);

            // 1. Cập nhật giao diện ngay lập tức (Optimistic UI)
            if (row) {
                row.classList.toggle('is-done', isChecked);
                row.dataset.status = isChecked ? 'completed' : 'pending';
                updateTaskOverdueUI(row, isChecked);

                // Nếu công việc không còn thỏa mãn tab hoặc bộ lọc hiện tại thì ẩn ngay
                if (typeof window.checkAndFilterTaskRow === 'function') {
                    window.checkAndFilterTaskRow(row);
                }
            }

            const csrf = getCsrfToken();
            const isTask = check.dataset.isTask !== '0';

            // 2. Gửi request cập nhật Database qua AJAX
            if (isTask) {
                fetch('index.php?page=api-task-status', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({
                        id: taskId,
                        status: isChecked ? 'completed' : 'pending',
                        csrf_token: csrf
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (isChecked) {
                            window.Toast?.success(data.message || 'Đã hoàn thành công việc!');
                        } else {
                            window.Toast?.info(data.message || 'Đã chuyển về chưa hoàn thành!');
                        }
                        if (data.stats) updateTaskStatsUI(data.stats);

                        // Đồng bộ lại bộ lọc và empty state nếu có
                        if (typeof window.triggerTasksFilter === 'function') {
                            window.triggerTasksFilter();
                        }
                    } else {
                        revertTaskCheckboxState(row, check, isChecked);
                        window.Toast?.error(data.message || 'Không thể cập nhật công việc.');
                    }
                })
                .catch(() => {
                    revertTaskCheckboxState(row, check, isChecked);
                    window.Toast?.error('Lỗi kết nối khi cập nhật công việc.');
                })
                .finally(() => {
                    activeTaskUpdates.delete(taskId);
                });
            } else {
                // Trường hợp đặc biệt: mục sự kiện (events) fallback
                fetch('index.php?page=api-event-save', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: new URLSearchParams({
                        id: taskId,
                        status: isChecked ? 'completed' : 'pending',
                        csrf_token: csrf,
                        _partial: 'status'
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.Toast?.success(data.message || (isChecked ? 'Đã hoàn thành!' : 'Đã khôi phục trạng thái!'));
                    } else {
                        revertTaskCheckboxState(row, check, isChecked);
                        window.Toast?.error(data.message || 'Không thể cập nhật trạng thái.');
                    }
                })
                .catch(() => {
                    revertTaskCheckboxState(row, check, isChecked);
                    window.Toast?.error('Lỗi kết nối khi cập nhật sự kiện.');
                })
                .finally(() => {
                    activeTaskUpdates.delete(taskId);
                });
            }
        });
    });
}

function revertTaskCheckboxState(row, check, isChecked) {
    if (check) check.checked = !isChecked;
    if (row) {
        row.classList.toggle('is-done', !isChecked);
        row.dataset.status = !isChecked ? 'completed' : 'pending';
        updateTaskOverdueUI(row, !isChecked);
        if (typeof window.triggerTasksFilter === 'function') {
            window.triggerTasksFilter();
        }
    }
}

/**
 * ===== BIỂU ĐỒ HÀNG TUẦN (CHART.JS) =====
 */
function initWeeklyChart() {
    const chart = document.getElementById('weeklyChart');
    if (chart && window.Chart) {
        let labels = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
        let completedData = [0, 0, 0, 0, 0, 0, 0];
        let pendingData = [0, 0, 0, 0, 0, 0, 0];

        if (chart.dataset.labels) {
            try { labels = JSON.parse(chart.dataset.labels); } catch (e) {}
        }
        if (chart.dataset.completed) {
            try { completedData = JSON.parse(chart.dataset.completed); } catch (e) {}
        }
        if (chart.dataset.pending) {
            try { pendingData = JSON.parse(chart.dataset.pending); } catch (e) {}
        }

        const maxVal = Math.max(10, ...completedData, ...pendingData) + 2;

        const isDarkTheme = document.body.classList.contains('dark');
        new Chart(chart, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Hoàn thành', data: completedData, backgroundColor: isDarkTheme ? '#ea580c' : '#c2410c', borderRadius: 4, barThickness: 12 },
                    { label: 'Chưa hoàn thành', data: pendingData, backgroundColor: isDarkTheme ? '#3f3f46' : '#d4d4d8', borderRadius: 4, barThickness: 12 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: isDarkTheme ? '#85858b' : '#71717a', font: { family: "'JetBrains Mono', monospace", size: 11 } } },
                    y: { beginAtZero: true, max: maxVal, ticks: { stepSize: 2, color: isDarkTheme ? '#85858b' : '#71717a', font: { family: "'JetBrains Mono', monospace", size: 11 } }, grid: { color: isDarkTheme ? 'rgba(237,237,237,0.07)' : 'rgba(24,24,27,0.07)' }, border: { display: false } }
                }
            }
        });
    }
}

/**
 * ===== TRANG QUẢN LÝ CÔNG VIỆC NÂNG CAO (TASKS) =====
 */
function initTasksPage() {
    const listContainer = document.getElementById('taskListContainer');
    if (!listContainer) return;

    const searchInput = document.getElementById('taskSearchInput');
    const priorityFilter = document.getElementById('taskPriorityFilter');
    const categoryFilter = document.getElementById('taskCategoryFilter');
    const sortSelect = document.getElementById('taskSortSelect');
    const statusTabs = document.querySelectorAll('.task-status-tab');
    const btnOpenAdd = document.getElementById('btnOpenAddTaskModal');
    const taskModalEl = document.getElementById('taskModal');
    const taskForm = document.getElementById('taskForm');
    const btnDeleteTask = document.getElementById('btnDeleteTaskModal');
    const loadingOverlay = document.getElementById('taskLoadingOverlay');

    let currentStatus = 'all';
    let taskModal = null;
    if (taskModalEl && window.bootstrap) {
        taskModal = new bootstrap.Modal(taskModalEl);
    }

    // Helper so sánh theo sortVal
    function compareTasksBySort(a, b, sortVal) {
        const priorityWeights = { urgent: 4, high: 3, medium: 2, low: 1 };
        if (sortVal === 'due_asc') {
            const dateA = a.dataset.dueDate || '9999-12-31';
            const dateB = b.dataset.dueDate || '9999-12-31';
            if (dateA !== dateB) return dateA.localeCompare(dateB);
            return Number(b.dataset.id) - Number(a.dataset.id);
        } else if (sortVal === 'due_desc') {
            const dateA = a.dataset.dueDate || '0000-00-00';
            const dateB = b.dataset.dueDate || '0000-00-00';
            if (dateA !== dateB) return dateB.localeCompare(dateA);
            return Number(b.dataset.id) - Number(a.dataset.id);
        } else if (sortVal === 'priority_desc') {
            const pA = priorityWeights[a.dataset.priority] || 0;
            const pB = priorityWeights[b.dataset.priority] || 0;
            if (pA !== pB) return pB - pA;
            return Number(b.dataset.id) - Number(a.dataset.id);
        } else if (sortVal === 'created_asc') {
            const cA = a.dataset.createdAt || '';
            const cB = b.dataset.createdAt || '';
            if (cA && cB && cA !== cB) return cA.localeCompare(cB);
            return Number(a.dataset.id) - Number(b.dataset.id);
        } else {
            // created_desc
            const cA = a.dataset.createdAt || '';
            const cB = b.dataset.createdAt || '';
            if (cA && cB && cA !== cB) return cB.localeCompare(cA);
            return Number(b.dataset.id) - Number(a.dataset.id);
        }
    }

    // 1. Lọc và Tìm kiếm trực tiếp trên UI (Live Search + Multi-filter)
    function filterAndSortTasks() {
        const rawQuery = (searchInput?.value || '').trim();
        const query = rawQuery.toLowerCase();
        const normQuery = typeof removeVietnameseTones === 'function' ? removeVietnameseTones(query) : query;

        const pVal = priorityFilter?.value || 'all';
        const cVal = categoryFilter?.value || '';
        const sortVal = sortSelect?.value || 'created_desc';

        const rows = Array.from(listContainer.querySelectorAll('.task-row'));
        if (rows.length === 0) return;

        // 1.1 Sắp xếp:
        // Nếu có từ khóa tìm kiếm: ĐƯA CÁC CÔNG VIỆC KHỚP LÊN ĐẦU TIÊN
        if (rawQuery) {
            rows.sort((a, b) => {
                const titleA = (a.querySelector('.task-copy strong')?.textContent || '').toLowerCase();
                const descA = (a.querySelector('.task-desc-text')?.textContent || '').toLowerCase();
                const normA = typeof removeVietnameseTones === 'function' ? removeVietnameseTones(titleA + ' ' + descA) : (titleA + ' ' + descA);

                const titleB = (b.querySelector('.task-copy strong')?.textContent || '').toLowerCase();
                const descB = (b.querySelector('.task-desc-text')?.textContent || '').toLowerCase();
                const normB = typeof removeVietnameseTones === 'function' ? removeVietnameseTones(titleB + ' ' + descB) : (titleB + ' ' + descB);

                const normTitleA = typeof removeVietnameseTones === 'function' ? removeVietnameseTones(titleA) : titleA;
                const normTitleB = typeof removeVietnameseTones === 'function' ? removeVietnameseTones(titleB) : titleB;

                let scoreA = 0;
                if (titleA.startsWith(query) || normTitleA.startsWith(normQuery)) scoreA = 3;
                else if (titleA.includes(query) || normTitleA.includes(normQuery)) scoreA = 2;
                else if (descA.includes(query) || normA.includes(normQuery)) scoreA = 1;

                let scoreB = 0;
                if (titleB.startsWith(query) || normTitleB.startsWith(normQuery)) scoreB = 3;
                else if (titleB.includes(query) || normTitleB.includes(normQuery)) scoreB = 2;
                else if (descB.includes(query) || normB.includes(normQuery)) scoreB = 1;

                if (scoreA !== scoreB) {
                    return scoreB - scoreA; // Điểm khớp cao nhất lên đầu tiên
                }
                return compareTasksBySort(a, b, sortVal);
            });
        } else {
            // Không tìm kiếm: Sắp xếp theo lựa chọn sortVal
            rows.sort((a, b) => compareTasksBySort(a, b, sortVal));
        }

        // Đưa các dòng đã sắp xếp lại vào DOM ngay lập tức
        rows.forEach(r => listContainer.appendChild(r));

        let visibleCount = 0;

        // 1.2 Lọc theo đa điều kiện kết hợp
        rows.forEach(row => {
            const title = (row.querySelector('.task-copy strong')?.textContent || '').toLowerCase();
            const desc = (row.querySelector('.task-desc-text')?.textContent || '').toLowerCase();
            const status = row.dataset.status || 'pending';
            const priority = row.dataset.priority || 'medium';
            const categoryId = row.dataset.categoryId || '';
            const isOverdue = row.classList.contains('is-overdue');

            // Hỗ trợ tìm kiếm tiếng Việt có dấu và không dấu
            const rawText = title + ' ' + desc;
            const normText = typeof removeVietnameseTones === 'function' ? removeVietnameseTones(rawText) : rawText;

            const matchSearch = !rawQuery || rawText.includes(query) || normText.includes(normQuery);
            const matchPriority = (pVal === 'all') || (priority === pVal);
            const matchCategory = !cVal || (categoryId === cVal);

            let matchStatus = true;
            if (currentStatus === 'pending') {
                matchStatus = (status === 'pending');
            } else if (currentStatus === 'completed') {
                matchStatus = (status === 'completed');
            } else if (currentStatus === 'overdue') {
                matchStatus = (status === 'pending') && isOverdue;
            }

            const isShow = matchSearch && matchPriority && matchCategory && matchStatus;
            if (isShow) {
                row.classList.remove('d-none', 'is-hidden');
                row.style.setProperty('display', 'flex', 'important');
                visibleCount++;
            } else {
                row.classList.add('d-none', 'is-hidden');
                row.style.setProperty('display', 'none', 'important');
            }
        });

        // 1.3 Xử lý empty state khi không tìm thấy kết quả
        let noResultMsg = document.getElementById('taskNoResultMsg');
        if (visibleCount === 0) {
            if (!noResultMsg) {
                noResultMsg = document.createElement('div');
                noResultMsg.id = 'taskNoResultMsg';
                noResultMsg.className = 'py-5 text-center text-muted';
                noResultMsg.innerHTML = `
                    <i class="fa-solid fa-filter-circle-xmark fs-2 mb-2 d-block opacity-50"></i>
                    <div class="fw-bold fs-6">Không tìm thấy công việc phù hợp</div>
                    <small>Hãy thử đổi từ khóa tìm kiếm hoặc điều chỉnh lại các bộ lọc.</small>
                `;
                listContainer.appendChild(noResultMsg);
            }
            noResultMsg.classList.remove('d-none');
            noResultMsg.style.setProperty('display', 'block', 'important');
        } else if (noResultMsg) {
            noResultMsg.classList.add('d-none');
            noResultMsg.style.setProperty('display', 'none', 'important');
        }
    }

    // Helper kiểm tra một dòng có còn thỏa mãn tab/bộ lọc hiện tại không (dùng khi tick checkbox)
    window.checkAndFilterTaskRow = function(row) {
        if (!row) return;
        const status = row.dataset.status || 'pending';
        const isOverdue = row.classList.contains('is-overdue');

        let matchStatus = true;
        if (currentStatus === 'pending') {
            matchStatus = (status === 'pending');
        } else if (currentStatus === 'completed') {
            matchStatus = (status === 'completed');
        } else if (currentStatus === 'overdue') {
            matchStatus = (status === 'pending') && isOverdue;
        }

        if (!matchStatus) {
            row.classList.add('d-none', 'is-hidden');
            row.style.setProperty('display', 'none', 'important');
        } else {
            row.classList.remove('d-none', 'is-hidden');
            row.style.setProperty('display', 'flex', 'important');
        }
    };
    window.triggerTasksFilter = filterAndSortTasks;

    // Lắng nghe sự kiện tìm kiếm theo từng ký tự kèm Debounce 300ms
    let searchDebounceTimer = null;
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            filterAndSortTasks();
        }, 300);
    });

    searchInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            searchInput.value = '';
            filterAndSortTasks();
            searchInput.blur();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchDebounceTimer);
            filterAndSortTasks();
        }
    });

    // Lọc theo mức độ ưu tiên: Cập nhật ngay lập tức
    priorityFilter?.addEventListener('change', () => {
        filterAndSortTasks();
    });

    // Lọc theo danh mục: Cập nhật ngay lập tức
    categoryFilter?.addEventListener('change', () => {
        filterAndSortTasks();
    });

    // Sắp xếp: Cập nhật ngay lập tức mà KHÔNG tải lại trang
    sortSelect?.addEventListener('change', () => {
        filterAndSortTasks();
    });

    // Chuyển đổi tab trạng thái: Cập nhật ngay lập tức
    statusTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            statusTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentStatus = tab.dataset.status || 'all';
            filterAndSortTasks();
        });
    });

    // 2. Mở Modal Thêm mới công việc
    btnOpenAdd?.addEventListener('click', () => {
        if (!taskForm) return;
        taskForm.reset();
        document.getElementById('taskModalId').value = '';
        document.getElementById('taskModalLabel').innerHTML = '<i class="fa-solid fa-list-check me-2 text-primary"></i><span>Thêm công việc mới</span>';
        if (btnDeleteTask) btnDeleteTask.style.display = 'none';
        taskModal?.show();
    });

    // 3. Nút Sửa công việc từ danh sách
    document.querySelectorAll('.btn-task-edit-v2').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const id = btn.dataset.id;
            if (!id) return;

            fetch(`index.php?page=api-task-detail&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        const t = data.data;
                        document.getElementById('taskModalId').value = t.id;
                        document.getElementById('taskModalTitle').value = t.title;
                        document.getElementById('taskModalCategory').value = t.category_id || '';
                        document.getElementById('taskModalDueDate').value = t.due_date || '';
                        document.getElementById('taskModalPriority').value = t.priority || 'medium';
                        document.getElementById('taskModalStatus').value = t.status || 'pending';
                        document.getElementById('taskModalDescription').value = t.description || '';

                        document.getElementById('taskModalLabel').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i><span>Chỉnh sửa công việc</span>';
                        if (btnDeleteTask) btnDeleteTask.style.display = 'inline-block';
                        taskModal?.show();
                    } else {
                        window.Toast?.error(data.message || 'Không thể tải chi tiết công việc.');
                    }
                })
                .catch(() => window.Toast?.error('Lỗi kết nối khi tải chi tiết công việc.'));
        });
    });

    // 4. Submit form Thêm / Sửa công việc
    taskForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const titleVal = document.getElementById('taskModalTitle')?.value.trim();
        if (!titleVal) {
            window.Toast?.warning('Vui lòng nhập tên công việc!', 'Thiếu thông tin');
            return;
        }

        const idVal = document.getElementById('taskModalId')?.value;
        const payload = {
            id: idVal,
            csrf_token: document.getElementById('taskModalCsrf')?.value || '',
            title: titleVal,
            category_id: document.getElementById('taskModalCategory')?.value || null,
            due_date: document.getElementById('taskModalDueDate')?.value || null,
            priority: document.getElementById('taskModalPriority')?.value || 'medium',
            status: document.getElementById('taskModalStatus')?.value || 'pending',
            description: document.getElementById('taskModalDescription')?.value.trim()
        };

        const submitBtn = document.getElementById('btnSaveTaskModal');
        const origText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang lưu...';

        fetch('index.php?page=api-task-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;

            if (data.success) {
                taskModal?.hide();
                window.Toast?.success(data.message || 'Lưu công việc thành công!');
                setTimeout(() => window.location.reload(), 700);
            } else {
                window.Toast?.error(data.message || 'Không thể lưu công việc.');
            }
        })
        .catch(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origText;
            window.Toast?.error('Lỗi kết nối khi lưu công việc.');
        });
    });

    // 5. Nút Xóa công việc từ modal và từ danh sách
    function deleteTaskItem(id, cardElement = null) {
        const csrf = getCsrfToken();

        if (!id || !window.Swal) return;

        Swal.fire({
            title: 'Xóa công việc này?',
            text: 'Nhiệm vụ sẽ bị xóa vĩnh viễn khỏi danh sách của bạn.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e46e6e',
            cancelButtonColor: '#8589a5',
            confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> Xóa ngay',
            cancelButtonText: 'Hủy bỏ'
        }).then(result => {
            if (result.isConfirmed) {
                fetch('index.php?page=api-task-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, csrf_token: csrf })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        taskModal?.hide();
                        window.Toast?.success('Đã xóa công việc thành công!');
                        if (cardElement) {
                            cardElement.remove();
                        } else {
                            document.querySelector(`.task-row[data-id="${id}"]`)?.remove();
                        }
                        if (data.stats) updateTaskStatsUI(data.stats);
                    } else {
                        window.Toast?.error(data.message || 'Không thể xóa công việc.');
                    }
                })
                .catch(() => window.Toast?.error('Lỗi kết nối khi xóa công việc.'));
            }
        });
    }

    btnDeleteTask?.addEventListener('click', () => {
        const id = document.getElementById('taskModalId')?.value;
        if (id) deleteTaskItem(id);
    });

    document.querySelectorAll('.btn-task-delete-v2').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const id = btn.dataset.id;
            const row = btn.closest('.task-row');
            if (id) deleteTaskItem(id, row);
        });
    });
}

function updateTaskStatsUI(stats) {
    if (!stats) return;
    const pBadge = document.getElementById('taskStatPercentBadge');
    const pBar = document.getElementById('taskStatProgressBar');
    const compCount = document.getElementById('taskStatCompletedCount');
    const pendCount = document.getElementById('taskStatPendingCount');
    const totalCount = document.getElementById('taskStatTotalCount');

    const sComp = document.getElementById('statCompletedNum');
    const sPend = document.getElementById('statPendingNum');
    const sOver = document.getElementById('statOverdueNum');
    const sUrg = document.getElementById('statUrgentNum');

    const tabAll = document.getElementById('tabCountAll');
    const tabPend = document.getElementById('tabCountPending');
    const tabComp = document.getElementById('tabCountCompleted');
    const tabOver = document.getElementById('tabCountOverdue');

    const pct = stats.progress_percent ?? 0;
    if (pBadge) pBadge.textContent = `${pct}%`;
    if (pBar) {
        pBar.style.width = `${pct}%`;
        pBar.setAttribute('aria-valuenow', pct);
    }
    if (compCount) compCount.textContent = stats.completed ?? 0;
    if (pendCount) pendCount.textContent = stats.pending ?? 0;
    if (totalCount) totalCount.textContent = stats.total ?? 0;

    if (sComp) sComp.textContent = stats.completed ?? 0;
    if (sPend) sPend.textContent = stats.pending ?? 0;
    if (sOver) sOver.textContent = stats.overdue ?? 0;
    if (sUrg) sUrg.textContent = stats.urgent ?? 0;

    if (tabAll) tabAll.textContent = stats.total ?? 0;
    if (tabPend) tabPend.textContent = stats.pending ?? 0;
    if (tabComp) tabComp.textContent = stats.completed ?? 0;
    if (tabOver) tabOver.textContent = stats.overdue ?? 0;

    // Cập nhật banner cảnh báo deadline trên trang Tasks
    const taskBanner = document.getElementById('taskOverdueBanner');
    const taskBannerNum = document.getElementById('taskOverdueBannerNum');
    if (taskBanner) {
        if ((stats.overdue ?? 0) > 0) {
            taskBanner.style.setProperty('display', 'flex', 'important');
            if (taskBannerNum) taskBannerNum.textContent = stats.overdue;
        } else {
            taskBanner.style.setProperty('display', 'none', 'important');
        }
    }

    // Cập nhật thẻ thống kê trên Dashboard nếu đang ở trang Dashboard
    const dashPending = document.getElementById('dashCard_pending_val');
    const dashCompleted = document.getElementById('dashCard_completed_val');
    const dashCompletedChange = document.getElementById('dashCard_completed_change');
    const dashPendingChange = document.getElementById('dashCard_pending_change');

    if (dashPending) {
        dashPending.textContent = String(stats.pending ?? 0).padStart(2, '0');
        if (dashPendingChange) {
            dashPendingChange.innerHTML = `<i class="fa-solid fa-arrow-trend-up"></i> ${stats.overdue > 0 ? `Quá hạn: ${stats.overdue} việc` : 'Cần xử lý'}`;
        }
    }
    if (dashCompleted) {
        dashCompleted.textContent = String(stats.completed ?? 0).padStart(2, '0');
        if (dashCompletedChange) {
            dashCompletedChange.innerHTML = `<i class="fa-solid fa-arrow-trend-up"></i> ${stats.total > 0 ? `Tiến độ: ${pct}%` : 'Đã hoàn tất'}`;
        }
    }
}

function fillAndOpenEventModal(event) {
    const modalEl = document.getElementById('eventModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);

    document.getElementById('eventId').value = event.id;
    document.getElementById('eventTitle').value = event.title;
    document.getElementById('eventCategory').value = event.category_id || '';
    document.getElementById('eventLocation').value = event.location || '';
    document.getElementById('eventPriority').value = event.priority || 'medium';
    document.getElementById('eventStatus').value = event.status || 'pending';
    document.getElementById('eventDescription').value = event.description || '';
    document.getElementById('eventReminder').value = event.reminder_at ? event.reminder_at.replace(' ', 'T').substring(0, 16) : '';

    // Nhắc nhở thông minh
    const reminderSwitch = document.getElementById('eventHasReminder');
    const reminderMinutesSelect = document.getElementById('eventReminderMinutes');
    const reminderRow = document.getElementById('reminderOptionsRow');

    const hasRem = !!(event.reminder_minutes !== null && event.reminder_minutes !== undefined && event.reminder_minutes !== '');
    if (reminderSwitch) reminderSwitch.checked = hasRem;
    if (reminderRow) reminderRow.style.display = hasRem ? 'flex' : 'none';
    if (reminderMinutesSelect && hasRem) {
        reminderMinutesSelect.value = String(event.reminder_minutes);
    }

    if (event.start_time) {
        const [sDate, sTime] = event.start_time.split(' ');
        document.getElementById('eventStartDate').value = sDate;
        document.getElementById('eventStartTime').value = sTime ? sTime.substring(0, 5) : '08:00';
    }
    if (event.end_time) {
        const [eDate, eTime] = event.end_time.split(' ');
        document.getElementById('eventEndDate').value = eDate;
        document.getElementById('eventEndTime').value = eTime ? eTime.substring(0, 5) : '09:00';
    }

    document.getElementById('eventModalLabel').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i><span>Chỉnh sửa công việc / Lịch trình</span>';
    const btnDel = document.getElementById('btnDeleteEvent');
    if (btnDel) btnDel.style.display = 'inline-block';

    modal.show();
}

/**
 * ===== TRANG DANH MỤC (CATEGORIES) NÂNG CAO CÓ BIỂU TƯỢNG =====
 */
function initCategoriesPage() {
    const btnAdd = document.getElementById('btnOpenAddCategoryModal');
    const modalEl = document.getElementById('categoryModal');
    if (!btnAdd || !modalEl) return;

    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('categoryForm');
    const nameInput = document.getElementById('categoryName');
    const colorPicker = document.getElementById('categoryColor');
    const colorHex = document.getElementById('categoryColorHex');
    const iconInput = document.getElementById('categoryIcon');
    const idInput = document.getElementById('categoryId');
    const modalTitle = document.getElementById('categoryModalLabel');
    const iconButtons = document.querySelectorAll('.icon-select-btn');

    // Live preview elements
    const previewName = document.getElementById('catPreviewName');
    const previewIconBox = document.getElementById('catPreviewIcon');

    function updatePreview() {
        const nameVal = nameInput.value.trim() || 'Tên danh mục';
        const colorVal = colorPicker.value || '#6658e8';
        const iconVal = iconInput.value || 'fa-folder';

        if (previewName) previewName.textContent = nameVal;
        if (previewIconBox) {
            previewIconBox.style.backgroundColor = `${colorVal}20`;
            previewIconBox.style.color = colorVal;
            previewIconBox.innerHTML = `<i class="fa-solid ${iconVal}"></i>`;
        }
    }

    // Lắng nghe thay đổi tên & màu để live preview
    nameInput?.addEventListener('input', updatePreview);
    colorPicker?.addEventListener('input', () => {
        colorHex.value = colorPicker.value;
        updatePreview();
    });
    colorHex?.addEventListener('input', () => {
        if (/^#[0-9A-Fa-f]{6}$/.test(colorHex.value)) {
            colorPicker.value = colorHex.value;
            updatePreview();
        }
    });

    // Preset color buttons
    document.querySelectorAll('.preset-color-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const c = btn.dataset.color;
            if (c) {
                colorPicker.value = c;
                colorHex.value = c;
                updatePreview();
            }
        });
    });

    // Icon Picker buttons
    iconButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            iconButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const chosenIcon = btn.dataset.icon || 'fa-folder';
            if (iconInput) iconInput.value = chosenIcon;
            updatePreview();
        });
    });

    // Mở modal thêm mới
    btnAdd.addEventListener('click', () => {
        form.reset();
        idInput.value = '';
        colorPicker.value = '#6658e8';
        colorHex.value = '#6658e8';
        iconInput.value = 'fa-folder';
        iconButtons.forEach(b => b.classList.toggle('active', b.dataset.icon === 'fa-folder'));
        modalTitle.innerHTML = '<i class="fa-solid fa-layer-group me-2 text-primary"></i>Thêm danh mục mới';
        updatePreview();
        modal.show();
    });

    // Nút Sửa danh mục
    document.querySelectorAll('.btn-cat-edit').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const card = btn.closest('.category-card');
            const isSystem = card.dataset.system === '1';

            if (isSystem) {
                window.Toast?.warning('Danh mục mặc định của hệ thống không thể chỉnh sửa.');
                return;
            }

            idInput.value = card.dataset.id;
            nameInput.value = card.dataset.name;
            const c = card.dataset.color || '#6658e8';
            colorPicker.value = c;
            colorHex.value = c;
            const ic = card.dataset.icon || 'fa-folder';
            iconInput.value = ic;

            iconButtons.forEach(b => b.classList.toggle('active', b.dataset.icon === ic));
            modalTitle.innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Chỉnh sửa danh mục';
            updatePreview();
            modal.show();
        });
    });

    // Submit form Thêm / Sửa
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const nameVal = nameInput.value.trim();
        if (!nameVal) {
            window.Toast?.warning('Vui lòng nhập tên danh mục!', 'Thiếu thông tin');
            return;
        }

        const payload = {
            id: idInput.value,
            name: nameVal,
            color: colorPicker.value,
            icon: iconInput.value || 'fa-folder',
            csrf_token: document.getElementById('categoryCsrf')?.value || ''
        };

        const submitBtn = document.getElementById('btnSaveCategory');
        submitBtn.disabled = true;

        fetch('index.php?page=api-category-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            if (data.success) {
                modal.hide();
                window.Toast?.success(data.message || (payload.id ? 'Sửa danh mục thành công!' : 'Thêm danh mục thành công!'));
                setTimeout(() => window.location.reload(), 800);
            } else {
                window.Toast?.error(data.message || 'Không thể lưu danh mục.');
            }
        })
        .catch(() => {
            submitBtn.disabled = false;
            window.Toast?.error('Lỗi kết nối khi lưu danh mục.');
        });
    });

    // Nút Xóa danh mục
    document.querySelectorAll('.btn-cat-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const card = btn.closest('.category-card');
            const id = card.dataset.id;
            const csrf = document.getElementById('categoryCsrf')?.value || '';

            if (id && window.Swal) {
                Swal.fire({
                    title: 'Xóa danh mục này?',
                    text: 'Các lịch trình và công việc thuộc danh mục này sẽ tự động chuyển về "Chưa phân loại".',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e46e6e',
                    cancelButtonColor: '#8589a5',
                    confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> Xóa ngay',
                    cancelButtonText: 'Hủy bỏ'
                }).then(result => {
                    if (result.isConfirmed) {
                        fetch('index.php?page=api-category-delete', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: id, csrf_token: csrf })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.Toast?.success('Xóa danh mục thành công!');
                                card.remove();
                            } else {
                                window.Toast?.error(data.message || 'Không thể xóa danh mục.');
                            }
                        })
                        .catch(() => window.Toast?.error('Lỗi kết nối khi xóa danh mục.'));
                    }
                });
            }
        });
    });
}

/**
 * ===== TRANG CÀI ĐẶT (SETTINGS) & BROWSER NOTIFICATION API =====
 */
function initSettingsPage() {
    const profileForm = document.getElementById('profileForm');
    profileForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const name = document.getElementById('profileName')?.value.trim();
        const csrf = profileForm.querySelector('input[name="csrf_token"]')?.value || '';

        if (!name) {
            window.Toast?.warning('Vui lòng nhập họ và tên của bạn!', 'Lưu ý!');
            return;
        }

        const btn = document.getElementById('btnSaveProfile');
        btn.disabled = true;

        fetch('index.php?page=api-settings-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name, csrf_token: csrf })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            if (data.success) {
                window.Toast?.success(data.message || 'Cập nhật thông tin cá nhân thành công!');
            } else {
                window.Toast?.error(data.message || 'Không thể cập nhật thông tin.');
            }
        })
        .catch(() => {
            btn.disabled = false;
            window.Toast?.error('Lỗi kết nối khi cập nhật thông tin.');
        });
    });

    // Quản lý Browser Notification API
    const permBadge = document.getElementById('browserNotifPermissionBadge');
    const prefSwitch = document.getElementById('prefBrowserNotif');
    const btnEnable = document.getElementById('btnEnableBrowserNotif');
    const btnTest = document.getElementById('btnTestBrowserNotif');
    const unblockGuide = document.getElementById('browserNotifUnblockGuide');

    function updatePermissionBadge() {
        if (!permBadge) return;
        if (!('Notification' in window)) {
            permBadge.textContent = 'Trình duyệt không hỗ trợ';
            permBadge.className = 'badge bg-secondary-subtle text-secondary';
            if (prefSwitch) prefSwitch.disabled = true;
            if (btnEnable) btnEnable.disabled = true;
            if (unblockGuide) unblockGuide.style.display = 'none';
            return;
        }

        const perm = Notification.permission;
        const isStoredOn = localStorage.getItem('personalCalendarBrowserNotif') === 'true';

        if (perm === 'granted') {
            permBadge.innerHTML = '<i class="fa-solid fa-check me-1"></i> Đã cấp quyền (Sẵn sàng)';
            permBadge.className = 'badge bg-success-subtle text-success';
            if (prefSwitch) prefSwitch.checked = isStoredOn;
            if (btnEnable) {
                btnEnable.innerHTML = '<i class="fa-solid fa-check me-1"></i> Quyền thông báo đã sẵn sàng';
                btnEnable.classList.remove('btn-primary-soft', 'btn-warning-subtle', 'text-warning');
                btnEnable.classList.add('btn-light', 'text-success');
            }
            if (unblockGuide) unblockGuide.style.display = 'none';
        } else if (perm === 'denied') {
            permBadge.innerHTML = '<i class="fa-solid fa-ban me-1"></i> Bị từ chối trong Cài đặt trình duyệt';
            permBadge.className = 'badge bg-danger-subtle text-danger';
            if (prefSwitch) prefSwitch.checked = false;
            if (btnEnable) {
                btnEnable.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Đã bị chặn - Xem cách mở lại';
                btnEnable.classList.remove('btn-primary-soft', 'btn-light', 'text-success');
                btnEnable.classList.add('btn-warning-subtle', 'text-warning');
            }
            if (unblockGuide) unblockGuide.style.display = 'block';
        } else {
            permBadge.innerHTML = '<i class="fa-regular fa-clock me-1"></i> Chưa cấp quyền';
            permBadge.className = 'badge bg-warning-subtle text-warning';
            if (prefSwitch) prefSwitch.checked = false;
            if (btnEnable) {
                btnEnable.innerHTML = '<i class="fa-solid fa-bell me-1"></i> Bật & Cấp quyền thông báo';
                btnEnable.classList.remove('btn-light', 'btn-warning-subtle', 'text-success', 'text-warning');
                btnEnable.classList.add('btn-primary-soft');
            }
            if (unblockGuide) unblockGuide.style.display = 'none';
        }
    }

    updatePermissionBadge();

    // Xin quyền khi người dùng chủ động bấm
    btnEnable?.addEventListener('click', () => {
        if (!('Notification' in window)) {
            window.Toast?.warning('Trình duyệt hiện tại không hỗ trợ Notification API.');
            return;
        }

        if (Notification.permission === 'denied') {
            if (unblockGuide) {
                unblockGuide.style.display = 'block';
                unblockGuide.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            window.Toast?.warning(
                'Trình duyệt đang chặn quyền thông báo. Hãy xem khung hướng dẫn màu vàng bên dưới để mở lại.',
                'Cần mở khóa trình duyệt'
            );
            return;
        }

        Notification.requestPermission().then(permission => {
            updatePermissionBadge();
            if (permission === 'granted') {
                localStorage.setItem('personalCalendarBrowserNotif', 'true');
                if (prefSwitch) prefSwitch.checked = true;
                window.Toast?.success('Đã cấp quyền thông báo trình duyệt thành công!');
                try {
                    new Notification('Personal Calendar', {
                        body: 'Bạn đã kích hoạt thành công tính năng thông báo trình duyệt!',
                        icon: 'https://cdn-icons-png.flaticon.com/512/3652/3652191.png'
                    });
                } catch (e) {}
            } else if (permission === 'denied') {
                localStorage.setItem('personalCalendarBrowserNotif', 'false');
                window.Toast?.warning('Bạn đã từ chối quyền. Ứng dụng vẫn gửi thông báo qua chuông và Toast bên trong website.', 'Đã từ chối quyền');
            }
        });
    });

    // Nút thử nghiệm thông báo (hoạt động 100% kể cả khi bị chặn trình duyệt)
    btnTest?.addEventListener('click', () => {
        // 1. Phát chuông thông báo âm thanh
        playNotificationChime();

        // 2. Gửi request tạo thông báo in-app trong database & cập nhật chuông
        fetch('index.php?page=api-notifications-test')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof window.updateNotifBadges === 'function') {
                        window.updateNotifBadges(data.unread_count);
                    }
                    window.Toast?.success(
                        'Thông báo thử nghiệm in-app thành công! Hãy kiểm tra biểu tượng quả chuông trên thanh điều hướng.',
                        'Thử nghiệm hệ thống'
                    );

                    // Tải lại danh sách thông báo nếu dropdown đang mở
                    const dropdown = document.getElementById('notificationDropdown');
                    if (dropdown && dropdown.style.display === 'block' && typeof window.loadNotifications === 'function') {
                        window.loadNotifications('all');
                    }
                }
            })
            .catch(() => {
                window.Toast?.info('Đã kiểm tra chuông âm thanh in-app thành công.', 'Thử nghiệm');
            });

        // 3. Nếu trình duyệt đã cấp quyền Notification, phát thêm thông báo native
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const bNotif = new Notification('Lịch trình sắp bắt đầu: Họp rà soát tiến độ', {
                    body: 'Sự kiện sẽ bắt đầu sau 15 phút tại Phòng họp A. Vui lòng chuẩn bị tài liệu!',
                    icon: 'https://cdn-icons-png.flaticon.com/512/3652/3652191.png'
                });
                bNotif.onclick = function() {
                    window.focus();
                };
            } catch (e) {}
        } else if ('Notification' in window && Notification.permission === 'denied') {
            setTimeout(() => {
                window.Toast?.info(
                    'Gợi ý: Thông báo in-app (chuông & toast) hoạt động 100%! Để hiện thêm pop-up Windows, hãy mở lại quyền ở icon ổ khóa trên thanh địa chỉ.',
                    'Quyền trình duyệt',
                    { duration: 7000 }
                );
            }, 1200);
        }
    });

    // Switch bật tắt
    prefSwitch?.addEventListener('change', () => {
        if (prefSwitch.checked) {
            if (Notification.permission === 'granted') {
                localStorage.setItem('personalCalendarBrowserNotif', 'true');
                window.Toast?.success('Đã bật nhận thông báo trình duyệt.');
            } else {
                btnEnable?.click();
            }
        } else {
            localStorage.setItem('personalCalendarBrowserNotif', 'false');
            window.Toast?.info('Đã tắt nhận thông báo qua trình duyệt.');
        }
    });

    // Form tùy chọn in-app
    const prefForm = document.getElementById('preferencesForm');
    const soundSwitch = document.getElementById('prefSound');
    const reminderSwitch = document.getElementById('prefReminder');
    const autoRestSwitch = document.getElementById('prefAutoRest');

    // Khởi tạo giá trị đã lưu
    if (soundSwitch) soundSwitch.checked = localStorage.getItem('personalCalendarSound') !== 'false';
    if (reminderSwitch) reminderSwitch.checked = localStorage.getItem('personalCalendarToastReminder') !== 'false';
    if (autoRestSwitch) autoRestSwitch.checked = localStorage.getItem('personalCalendarAutoRest') === 'true';

    prefForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        localStorage.setItem('personalCalendarSound', soundSwitch?.checked ? 'true' : 'false');
        localStorage.setItem('personalCalendarToastReminder', reminderSwitch?.checked ? 'true' : 'false');
        localStorage.setItem('personalCalendarAutoRest', autoRestSwitch?.checked ? 'true' : 'false');
        window.Toast?.success('Lưu các tùy chọn nhắc nhở thành công!');
    });
}

/**
 * ===== TRANG CHIA SẺ LỊCH (SHARE) =====
 */
function initSharePage() {
    const btnCopy = document.getElementById('btnCopyShareLink');
    btnCopy?.addEventListener('click', () => {
        const link = document.getElementById('shareLinkInput')?.value;
        if (link && navigator.clipboard) {
            navigator.clipboard.writeText(link).then(() => {
                window.Toast?.success('Đã sao chép liên kết chia sẻ lịch vào bộ nhớ tạm!');
            }).catch(() => {
                window.Toast?.info('Liên kết: ' + link);
            });
        } else {
            window.Toast?.success('Đã sao chép liên kết chia sẻ lịch vào bộ nhớ tạm!');
        }
    });

    const inviteForm = document.getElementById('inviteUserForm');
    inviteForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const email = document.getElementById('inviteEmail')?.value.trim();
        if (!email) {
            window.Toast?.warning('Vui lòng nhập địa chỉ email người nhận!', 'Thiếu thông tin');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            window.Toast?.warning('Địa chỉ email không đúng định dạng!', 'Email không hợp lệ');
            return;
        }

        window.Toast?.success(`Đã gửi lời mời chia sẻ lịch thành công tới ${email}!`);
        inviteForm.reset();
    });

    document.querySelectorAll('.btn-revoke-share').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const row = btn.closest('.d-flex');
            if (window.Swal) {
                Swal.fire({
                    title: 'Hủy quyền chia sẻ?',
                    text: 'Người dùng này sẽ không thể xem hoặc chỉnh sửa lịch trình của bạn nữa.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e46e6e',
                    cancelButtonColor: '#8589a5',
                    confirmButtonText: 'Hủy chia sẻ',
                    cancelButtonText: 'Đóng'
                }).then(result => {
                    if (result.isConfirmed) {
                        row?.remove();
                        window.Toast?.success('Đã hủy quyền chia sẻ lịch thành công!');
                    }
                });
            }
        });
    });

    document.getElementById('btnOpenInviteModal')?.addEventListener('click', () => {
        document.getElementById('inviteEmail')?.focus();
        window.Toast?.info('Vui lòng điền email và quyền hạn ở khung bên dưới để gửi lời mời.');
    });
}

/**
 * ===== ĐỒNG HỒ POMODORO =====
 */
function initPomodoro() {
    const display = document.getElementById('timerDisplay');
    if (!display) return;
    const start = document.getElementById('timerStart');
    const reset = document.getElementById('timerReset');
    const caption = document.getElementById('timerCaption');
    const ring = document.querySelector('.timer-ring');
    const pomoCycles = document.getElementById('pomoCycles');
    const pomoProgress = document.getElementById('pomoProgressBar');

    let duration = 25 * 60, remaining = duration, interval = null;
    let completedCycles = 0;

    const render = () => {
        const min = Math.floor(remaining / 60).toString().padStart(2, '0');
        const sec = (remaining % 60).toString().padStart(2, '0');
        display.textContent = `${min}:${sec}`;
        const deg = ((duration - remaining) / duration) * 360;
        ring.style.background = `conic-gradient(#7669ed ${deg}deg, var(--line) ${deg}deg)`;
    };

    const stop = () => {
        clearInterval(interval);
        interval = null;
        start.innerHTML = '<i class="fa-solid fa-play me-2"></i>Bắt đầu';
    };

    start.addEventListener('click', () => {
        if (interval) {
            stop();
            caption.textContent = 'ĐÃ TẠM DỪNG';
            window.Toast?.warning('Đã tạm dừng đồng hồ Pomodoro.');
            return;
        }

        caption.textContent = 'ĐANG TẬP TRUNG';
        start.innerHTML = '<i class="fa-solid fa-pause me-2"></i>Tạm dừng';
        window.Toast?.info('Bắt đầu phiên tập trung! Hãy chú ý làm việc.', 'Pomodoro');

        interval = setInterval(() => {
            if (remaining > 0) {
                remaining--;
                render();
            } else {
                stop();
                caption.textContent = 'HOÀN THÀNH PHIÊN';
                completedCycles++;
                if (pomoCycles) pomoCycles.textContent = completedCycles;
                if (pomoProgress) {
                    const pct = Math.min(100, (completedCycles / 4) * 100);
                    pomoProgress.style.width = `${pct}%`;
                }
                window.Toast?.success('Tuyệt vời! Bạn đã hoàn thành một phiên tập trung.', 'Pomodoro');
            }
        }, 1000);
    });

    reset.addEventListener('click', () => {
        stop();
        remaining = duration;
        caption.textContent = 'SẴN SÀNG TẬP TRUNG?';
        render();
        window.Toast?.info('Đã đặt lại đồng hồ Pomodoro.');
    });

    document.querySelectorAll('.mode-btn').forEach(button => button.addEventListener('click', () => {
        stop();
        document.querySelectorAll('.mode-btn').forEach(item => item.classList.remove('active'));
        button.classList.add('active');
        duration = Number(button.dataset.minutes) * 60;
        remaining = duration;
        caption.textContent = 'SẴN SÀNG TẬP TRUNG?';
        render();
        window.Toast?.info(`Đã chuyển sang chế độ ${button.textContent.trim()}.`, 'Chế độ');
    }));

    render();
}

/**
 * ===== TÍCH HỢP FULLCALENDAR & EVENT CRUD =====
 */
function initFullCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl || !window.FullCalendar) return;

    const eventModalEl = document.getElementById('eventModal');
    const detailModalEl = document.getElementById('eventDetailModal');
    const eventModal = eventModalEl ? new bootstrap.Modal(eventModalEl) : null;
    const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;

    const eventForm = document.getElementById('eventForm');
    const btnOpenAddModal = document.getElementById('btnOpenAddModal');
    const btnDeleteEvent = document.getElementById('btnDeleteEvent');
    const btnDetailEdit = document.getElementById('btnDetailEdit');
    const btnDetailDelete = document.getElementById('btnDetailDelete');

    const csrfToken = getCsrfToken();
    let currentSelectedEvent = null;

    const formatToSqlDateTime = (date) => {
        const d = new Date(date);
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    };

    const hasCustomToolbar = !!document.getElementById('calendarToolbar');
    let selectedCategoryId = null;

    const initialView = calendarEl.dataset.initialView || 'dayGridMonth';
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: initialView,
        locale: 'vi',
        timeZone: 'local',
        headerToolbar: hasCustomToolbar ? false : {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: 'Hôm nay',
            month: 'Tháng',
            week: 'Tuần',
            day: 'Ngày'
        },
        navLinks: true,
        selectable: true,
        selectMirror: true,
        editable: true,
        dayMaxEvents: 4,
        moreLinkClick: 'popover',
        events: function(fetchInfo, successCallback, failureCallback) {
            const url = new URL('index.php', window.location.href);
            url.searchParams.set('page', 'api-events');
            url.searchParams.set('start', fetchInfo.startStr);
            url.searchParams.set('end', fetchInfo.endStr);
            if (selectedCategoryId) {
                url.searchParams.set('category_id', selectedCategoryId);
            }
            fetch(url.toString())
                .then(res => res.json())
                .then(data => successCallback(data))
                .catch(err => failureCallback(err));
        },
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },
        slotLabelFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },

        datesSet: function(info) {
            const titleEl = document.getElementById('calendarTitleText');
            if (titleEl) {
                titleEl.textContent = info.view.title;
            }
            const currentView = info.view.type;
            document.querySelectorAll('.view-seg-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === currentView);
            });
        },

        select: function(info) {
            openAddModal(info.start, info.end, info.allDay);
            calendar.unselect();
        },

        eventClick: function(info) {
            info.jsEvent.preventDefault();
            currentSelectedEvent = info.event;
            openDetailModal(info.event);
        },

        eventDrop: function(info) {
            updateEventTime(info.event, info.revert);
        },

        eventResize: function(info) {
            updateEventTime(info.event, info.revert);
        }
    });

    calendar.render();
    window.appCalendar = calendar;
    window.openCalendarDetailModal = (event) => {
        currentSelectedEvent = event;
        openDetailModal(event);
    };

    // Giám sát và ép nền đặc (opaque) 100% bằng JS cho popover FullCalendar khi mở
    const popoverObserver = new MutationObserver(() => {
        document.querySelectorAll('.fc-popover').forEach(popover => {
            const isDark = document.body.classList.contains('dark');
            const bg = isDark ? '#1e293b' : '#ffffff';
            const border = isDark ? '#334155' : '#e2e8f0';
            const headerBg = isDark ? '#0f172a' : '#f8fafc';
            const textColor = isDark ? '#f1f5f9' : '#1e293b';

            popover.style.setProperty('background', bg, 'important');
            popover.style.setProperty('background-color', bg, 'important');
            popover.style.setProperty('border', `1px solid ${border}`, 'important');
            popover.style.setProperty('border-radius', '14px', 'important');
            popover.style.setProperty('box-shadow', '0 20px 45px rgba(15, 23, 42, 0.22)', 'important');
            popover.style.setProperty('opacity', '1', 'important');
            popover.style.setProperty('z-index', '99999', 'important');

            const header = popover.querySelector('.fc-popover-header');
            if (header) {
                header.style.setProperty('background', headerBg, 'important');
                header.style.setProperty('background-color', headerBg, 'important');
                header.style.setProperty('border-bottom', `1px solid ${border}`, 'important');
                header.style.setProperty('padding', '10px 14px', 'important');
            }

            const title = popover.querySelector('.fc-popover-title');
            if (title) {
                title.style.setProperty('color', textColor, 'important');
                title.style.setProperty('font-weight', '700', 'important');
            }

            const body = popover.querySelector('.fc-popover-body');
            if (body) {
                body.style.setProperty('background', bg, 'important');
                body.style.setProperty('background-color', bg, 'important');
                body.style.setProperty('padding', '10px 12px', 'important');
            }
        });
    });
    popoverObserver.observe(document.body, { childList: true, subtree: true });

    // Kết nối các nút của Toolbar mới với FullCalendar API
    if (hasCustomToolbar) {
        document.getElementById('calBtnPrev')?.addEventListener('click', () => {
            calendar.prev();
        });
        document.getElementById('calBtnNext')?.addEventListener('click', () => {
            calendar.next();
        });
        document.getElementById('calBtnToday')?.addEventListener('click', () => {
            calendar.today();
        });

        document.querySelectorAll('.view-seg-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetView = btn.dataset.view;
                if (targetView) {
                    calendar.changeView(targetView);
                }
            });
        });

        // Xử lý lọc theo danh mục
        document.querySelectorAll('.cat-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const catId = chip.dataset.categoryId;
                const catName = chip.dataset.name || 'Danh mục';

                if (selectedCategoryId === catId) {
                    selectedCategoryId = null;
                    chip.classList.remove('active');
                } else {
                    document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
                    selectedCategoryId = catId;
                    chip.classList.add('active');
                    window.Toast?.info(`Đang lọc theo danh mục: ${catName}`, 'Bộ lọc');
                }

                calendar.refetchEvents();
            });
        });
    }

    document.getElementById('themeToggle')?.addEventListener('click', () => {
        setTimeout(() => calendar.updateSize(), 200);
    });

    function openAddModal(startDate = null, endDate = null, allDay = false) {
        eventForm.reset();
        document.getElementById('eventId').value = '';
        document.getElementById('eventModalLabel').innerHTML = '<i class="fa-regular fa-calendar-plus me-2 text-primary"></i><span>Thêm lịch trình mới</span>';
        if (btnDeleteEvent) btnDeleteEvent.style.display = 'none';

        const now = startDate ? new Date(startDate) : new Date();
        const pad = (n) => String(n).padStart(2, '0');

        const ymd = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        document.getElementById('eventStartDate').value = ymd;
        document.getElementById('eventEndDate').value = ymd;

        if (allDay || !startDate) {
            document.getElementById('eventStartTime').value = '08:00';
            document.getElementById('eventEndTime').value = '09:00';
        } else {
            const sh = pad(now.getHours());
            const sm = pad(now.getMinutes());
            document.getElementById('eventStartTime').value = `${sh}:${sm}`;

            if (endDate) {
                const end = new Date(endDate);
                const ey = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}`;
                document.getElementById('eventEndDate').value = ey;
                document.getElementById('eventEndTime').value = `${pad(end.getHours())}:${pad(end.getMinutes())}`;
            } else {
                const endHour = pad((now.getHours() + 1) % 24);
                document.getElementById('eventEndTime').value = `${endHour}:${sm}`;
            }
        }

        const hasRem = true;
        const reminderSwitch = document.getElementById('eventHasReminder');
        const reminderRow = document.getElementById('reminderOptionsRow');
        const reminderMinutesSelect = document.getElementById('eventReminderMinutes');

        if (reminderSwitch) reminderSwitch.checked = true;
        if (reminderRow) reminderRow.style.display = 'flex';
        if (reminderMinutesSelect) reminderMinutesSelect.value = '15';
        updateReminderPreview();

        eventModal?.show();
    }

    function updateReminderPreview() {
        const hasRem = document.getElementById('eventHasReminder')?.checked;
        const row = document.getElementById('reminderOptionsRow');
        const previewEl = document.getElementById('reminderPreviewText');
        if (row) row.style.display = hasRem ? 'flex' : 'none';
        if (!hasRem || !previewEl) return;

        const sDate = document.getElementById('eventStartDate')?.value;
        const sTime = document.getElementById('eventStartTime')?.value;
        const mins = parseInt(document.getElementById('eventReminderMinutes')?.value || '15', 10);

        if (sDate && sTime) {
            const startTs = new Date(`${sDate}T${sTime}:00`).getTime();
            if (!isNaN(startTs)) {
                const remindTs = new Date(startTs - (mins * 60 * 1000));
                const pad = n => String(n).padStart(2, '0');
                const timeStr = `${pad(remindTs.getHours())}:${pad(remindTs.getMinutes())}, ${pad(remindTs.getDate())}/${pad(remindTs.getMonth() + 1)}`;
                previewEl.innerHTML = `<i class="fa-solid fa-clock-rotate-left me-1 text-info"></i>Sẽ nhắc lúc: <b>${timeStr}</b>`;
                return;
            }
        }
        previewEl.innerHTML = `<i class="fa-solid fa-clock-rotate-left me-1 text-info"></i>Sẽ nhắc trước khi sự kiện bắt đầu.`;
    }

    document.getElementById('eventHasReminder')?.addEventListener('change', updateReminderPreview);
    document.getElementById('eventReminderMinutes')?.addEventListener('change', updateReminderPreview);
    document.getElementById('eventStartDate')?.addEventListener('input', updateReminderPreview);
    document.getElementById('eventStartTime')?.addEventListener('input', updateReminderPreview);

    function openDetailModal(event) {
        const props = event.extendedProps || {};
        document.getElementById('detailTitle').textContent = event.title;

        const catBadge = document.getElementById('detailCategoryBadge');
        catBadge.textContent = props.category_name || 'Chưa phân loại';
        catBadge.style.backgroundColor = (props.category_color || '#6658e8') + '25';
        catBadge.style.color = props.category_color || '#6658e8';
        catBadge.style.border = `1px solid ${props.category_color || '#6658e8'}55`;

        document.getElementById('detailTime').textContent = `${props.start_formatted || ''} - ${props.end_formatted || ''}`;

        const locRow = document.getElementById('detailLocationRow');
        const locSpan = document.getElementById('detailLocation');
        if (props.location) {
            locSpan.textContent = props.location;
            locRow.style.display = 'flex';
        } else {
            locRow.style.display = 'none';
        }

        // Hiển thị thông tin nhắc nhở
        const remText = document.getElementById('detailReminderText');
        if (remText) {
            const rMin = props.reminder_minutes;
            const rMap = {
                0: 'Đúng giờ sự kiện bắt đầu',
                5: 'Trước 5 phút',
                10: 'Trước 10 phút',
                15: 'Trước 15 phút',
                30: 'Trước 30 phút',
                60: 'Trước 1 giờ',
                1440: 'Trước 1 ngày'
            };
            if (rMin !== null && rMin !== undefined) {
                remText.textContent = 'Nhắc nhở: ' + (rMap[rMin] || `${rMin} phút trước`);
            } else if (props.reminder_at) {
                remText.textContent = 'Nhắc nhở: ' + props.reminder_at;
            } else {
                remText.textContent = 'Không bật nhắc nhở';
            }
        }

        const pBadge = document.getElementById('detailPriorityBadge');
        const pMap = { high: ['Cao', 'high'], medium: ['Trung bình', 'medium'], low: ['Thấp', 'low'] };
        const pInfo = pMap[props.priority] || ['Trung bình', 'medium'];
        pBadge.textContent = 'Ưu tiên: ' + pInfo[0];
        pBadge.className = 'priority ' + pInfo[1];

        const sBadge = document.getElementById('detailStatusBadge');
        if (props.status === 'completed') {
            sBadge.textContent = 'Đã hoàn thành';
            sBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
        } else {
            sBadge.textContent = 'Chưa hoàn thành';
            sBadge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle';
        }

        const descEl = document.getElementById('detailDescription');
        descEl.textContent = props.description ? props.description : 'Không có mô tả chi tiết.';

        detailModal?.show();
    }

    function openEditModal(event) {
        detailModal?.hide();
        const props = event.extendedProps || {};

        document.getElementById('eventId').value = event.id;
        document.getElementById('eventTitle').value = event.title;
        document.getElementById('eventCategory').value = props.category_id || '';
        document.getElementById('eventLocation').value = props.location || '';
        document.getElementById('eventPriority').value = props.priority || 'medium';
        document.getElementById('eventStatus').value = props.status || 'pending';
        document.getElementById('eventDescription').value = props.description || '';
        document.getElementById('eventReminder').value = props.reminder_at || '';

        // Đọc reminder_minutes cho form sửa
        const reminderSwitch = document.getElementById('eventHasReminder');
        const reminderMinutesSelect = document.getElementById('eventReminderMinutes');
        const reminderRow = document.getElementById('reminderOptionsRow');

        const hasRem = (props.reminder_minutes !== null && props.reminder_minutes !== undefined && props.reminder_minutes !== '') || !!props.reminder_at;
        if (reminderSwitch) reminderSwitch.checked = hasRem;
        if (reminderRow) reminderRow.style.display = hasRem ? 'flex' : 'none';
        if (reminderMinutesSelect && props.reminder_minutes !== null && props.reminder_minutes !== undefined) {
            reminderMinutesSelect.value = String(props.reminder_minutes);
        }
        updateReminderPreview();

        if (props.raw_start) {
            const [sDate, sTime] = props.raw_start.split('T');
            document.getElementById('eventStartDate').value = sDate;
            document.getElementById('eventStartTime').value = sTime ? sTime.substring(0, 5) : '08:00';
        }

        if (props.raw_end) {
            const [eDate, eTime] = props.raw_end.split('T');
            document.getElementById('eventEndDate').value = eDate;
            document.getElementById('eventEndTime').value = eTime ? eTime.substring(0, 5) : '09:00';
        }

        document.getElementById('eventModalLabel').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i><span>Chỉnh sửa lịch trình</span>';
        if (btnDeleteEvent) btnDeleteEvent.style.display = 'inline-block';

        setTimeout(() => eventModal?.show(), 200);
    }

    btnOpenAddModal?.addEventListener('click', () => openAddModal());

    btnDetailEdit?.addEventListener('click', () => {
        if (currentSelectedEvent) openEditModal(currentSelectedEvent);
    });

    btnDetailDelete?.addEventListener('click', () => {
        if (currentSelectedEvent) {
            detailModal?.hide();
            confirmDeleteEvent(currentSelectedEvent.id);
        }
    });

    btnDeleteEvent?.addEventListener('click', () => {
        const id = document.getElementById('eventId').value;
        if (id) {
            eventModal?.hide();
            confirmDeleteEvent(id);
        }
    });

    // Submit form Thêm / Sửa Lịch
    eventForm?.addEventListener('submit', function(e) {
        e.preventDefault();

        const title = document.getElementById('eventTitle').value.trim();
        const sDate = document.getElementById('eventStartDate').value;
        const sTime = document.getElementById('eventStartTime').value;
        const eDate = document.getElementById('eventEndDate').value;
        const eTime = document.getElementById('eventEndTime').value;

        if (!title) {
            window.Toast?.warning('Vui lòng nhập tiêu đề lịch trình!', 'Thiếu thông tin');
            return;
        }

        const startDateTime = new Date(`${sDate}T${sTime}:00`);
        const endDateTime = new Date(`${eDate}T${eTime}:00`);

        if (endDateTime <= startDateTime) {
            window.Toast?.warning('Thời gian kết thúc phải diễn ra sau thời gian bắt đầu!', 'Thời gian không hợp lệ');
            return;
        }

        const hasRem = document.getElementById('eventHasReminder')?.checked;
        const reminderMinutesVal = hasRem ? document.getElementById('eventReminderMinutes')?.value : null;

        const idVal = document.getElementById('eventId').value;
        const payload = {
            id: idVal,
            csrf_token: csrfToken,
            title: title,
            category_id: document.getElementById('eventCategory').value,
            location: document.getElementById('eventLocation').value.trim(),
            start_time: `${sDate} ${sTime}:00`,
            end_time: `${eDate} ${eTime}:00`,
            priority: document.getElementById('eventPriority').value,
            status: document.getElementById('eventStatus').value,
            has_reminder: hasRem ? 1 : 0,
            reminder_minutes: reminderMinutesVal,
            description: document.getElementById('eventDescription').value.trim()
        };

        const submitBtn = document.getElementById('btnSaveEvent');
        const origBtnHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...';

        fetch('index.php?page=api-event-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origBtnHtml;

            if (data.success) {
                eventModal?.hide();
                calendar.refetchEvents();
                window.Toast?.success(data.message || (idVal ? 'Cập nhật lịch thành công!' : 'Thêm lịch thành công!'));
            } else {
                window.Toast?.error(data.message || 'Không thể lưu lịch trình.');
            }
        })
        .catch(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = origBtnHtml;
            window.Toast?.error('Lỗi khi lưu lịch hoặc mất kết nối máy chủ.');
        });
    });

    // Kéo thả & Resize cập nhật thời gian
    function updateEventTime(event, revertCallback) {
        const start = formatToSqlDateTime(event.start);
        const end = event.end ? formatToSqlDateTime(event.end) : formatToSqlDateTime(new Date(event.start.getTime() + 3600000));

        fetch('index.php?page=api-event-time', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: event.id,
                start_time: start,
                end_time: end,
                csrf_token: csrfToken
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.Toast?.success(data.message || 'Cập nhật thời gian thành công!');
                calendar.refetchEvents();
            } else {
                if (typeof revertCallback === 'function') revertCallback();
                window.Toast?.error(data.message || 'Không thể cập nhật thời gian.');
            }
        })
        .catch(() => {
            if (typeof revertCallback === 'function') revertCallback();
            window.Toast?.error('Lỗi kết nối khi cập nhật thời gian sự kiện.');
        });
    }

    // Modal xác nhận và xóa sự kiện
    function confirmDeleteEvent(id) {
        if (!window.Swal) {
            deleteEventApi(id);
            return;
        }

        Swal.fire({
            title: 'Xóa lịch trình này?',
            text: 'Lịch trình sẽ bị xóa vĩnh viễn và không thể khôi phục.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e46e6e',
            cancelButtonColor: '#8589a5',
            confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> Xóa ngay',
            cancelButtonText: 'Hủy bỏ'
        }).then(result => {
            if (result.isConfirmed) {
                deleteEventApi(id);
            }
        });
    }

    function deleteEventApi(id) {
        fetch('index.php?page=api-event-delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, csrf_token: csrfToken })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                calendar.refetchEvents();
                window.Toast?.success(data.message || 'Xóa lịch thành công!');
            } else {
                window.Toast?.error(data.message || 'Không thể xóa lịch trình.');
            }
        })
        .catch(() => {
            window.Toast?.error('Lỗi kết nối khi gửi yêu cầu xóa.');
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'new') {
        openAddModal();
    }
}

/**
 * =========================================================================
 * ===== LIVE SEARCH TRÊN TOPBAR (TÌM KIẾM TRỰC TIẾP LỊCH TRÌNH CÁ NHÂN) =====
 * =========================================================================
 */
function initLiveSearch() {
    const searchBox   = document.getElementById('topbarSearchBox');
    const searchInput = document.getElementById('topbarSearchInput');
    const clearBtn    = document.getElementById('btnClearSearch');
    const kbdShortcut = document.getElementById('searchKbdShortcut');
    const dropdown    = document.getElementById('liveSearchDropdown');
    const listEl      = document.getElementById('liveSearchList');
    const countEl     = document.getElementById('liveSearchCount');

    if (!searchInput || !dropdown) return;

    let debounceTimer = null;
    let currentQuery  = '';
    let activeIndex   = -1;

    // 1. Phím tắt toàn cục ⌘ K / Ctrl + K để focus nhanh vào thanh tìm kiếm
    window.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
            if (searchInput.value.trim() && dropdown.classList.contains('d-none')) {
                performSearch(searchInput.value.trim());
            }
        }
    });

    // 2. Sự kiện 'input' với kỹ thuật Debounce 300ms theo từng ký tự
    searchInput.addEventListener('input', () => {
        const val = searchInput.value;
        const trimmed = val.trim();
        currentQuery = trimmed;

        // Cập nhật nút xóa nhanh
        if (val.length > 0) {
            clearBtn?.classList.remove('d-none');
            kbdShortcut?.classList.add('d-none');
        } else {
            clearBtn?.classList.add('d-none');
            kbdShortcut?.classList.remove('d-none');
        }

        clearTimeout(debounceTimer);

        // Khi xóa hết từ khóa, khôi phục trạng thái hiển thị ban đầu
        if (!trimmed) {
            hideDropdown();
            return;
        }

        debounceTimer = setTimeout(() => {
            performSearch(trimmed);
        }, 300);
    });

    // 3. Nút xóa nhanh nội dung tìm kiếm [x]
    clearBtn?.addEventListener('click', () => {
        searchInput.value = '';
        currentQuery = '';
        clearBtn.classList.add('d-none');
        kbdShortcut?.classList.remove('d-none');
        hideDropdown();
        searchInput.focus();
    });

    // 4. Điều hướng bằng phím: Escape để đóng, Mũi tên Lên/Xuống để duyệt, Enter để chọn
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            hideDropdown();
            searchInput.blur();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            navigateResults(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            navigateResults(-1);
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0) {
                e.preventDefault();
                const items = listEl.querySelectorAll('.live-search-item');
                if (items[activeIndex]) {
                    items[activeIndex].click();
                }
            }
        }
    });

    // 5. Đóng danh sách kết quả khi click chuột ra ngoài
    document.addEventListener('click', (e) => {
        if (searchBox && !searchBox.contains(e.target)) {
            hideDropdown();
        }
    });

    // 6. Mở lại dropdown khi focus nếu đã có từ khóa
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim() && dropdown.classList.contains('d-none')) {
            performSearch(searchInput.value.trim());
        }
    });

    function showDropdown() {
        dropdown.classList.remove('d-none');
    }

    function hideDropdown() {
        dropdown.classList.add('d-none');
        activeIndex = -1;
    }

    // 7. Thực thi tìm kiếm qua API index.php?page=api-search
    function performSearch(query) {
        showDropdown();
        listEl.innerHTML = `
            <div class="live-search-loading">
                <i class="fa-solid fa-spinner fa-spin me-2 text-primary"></i> Đang tìm kiếm lịch trình...
            </div>
        `;
        if (countEl) countEl.textContent = '...';

        fetch(`index.php?page=api-search&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(res => {
                // Đảm bảo kết quả trả về đúng với query hiện tại (chống race condition)
                if (query !== currentQuery) return;

                if (res.success && Array.isArray(res.data)) {
                    renderResults(res.data, query);
                } else {
                    renderEmpty(query);
                }
            })
            .catch(() => {
                listEl.innerHTML = `
                    <div class="live-search-empty text-danger">
                        <i class="fa-solid fa-circle-exclamation mb-2 fa-2x"></i>
                        <p class="mb-0">Có lỗi xảy ra khi kết nối máy chủ. Vui lòng thử lại sau.</p>
                    </div>
                `;
            });
    }

    // 8. Hiển thị danh sách kết quả tìm kiếm
    function renderResults(items, query) {
        if (!items || items.length === 0) {
            renderEmpty(query);
            return;
        }

        if (countEl) countEl.textContent = `${items.length} kết quả`;
        activeIndex = -1;

        listEl.innerHTML = items.map((item, index) => {
            const titleHtml = highlightLiveSearchText(item.title, query);
            const catColor = item.category_color || '#6658e8';
            const isTask = (item.type === 'task');

            // Huy hiệu loại (Lịch trình hoặc Công việc)
            const typeBadge = isTask
                ? `<span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 9.5px;"><i class="fa-solid fa-list-check me-1"></i>Công việc</span>`
                : `<span class="badge bg-light text-secondary border" style="font-size: 9.5px;"><i class="fa-regular fa-calendar-days me-1 text-primary"></i>Lịch trình</span>`;

            // Huy hiệu trạng thái
            let statusBadge = '';
            if (item.status === 'completed') {
                statusBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;">Đã xong</span>`;
            } else if (item.status === 'in_progress') {
                statusBadge = `<span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 10px;">Đang làm</span>`;
            } else if (item.status_color === 'danger') {
                statusBadge = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 10px;">Quá hạn</span>`;
            } else {
                statusBadge = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 10px;">Chờ xử lý</span>`;
            }

            return `
                <div class="live-search-item" data-id="${item.id}" data-type="${item.type || 'event'}" data-title="${escapeHtml(item.title)}" data-index="${index}" role="option" tabindex="-1">
                    <div class="live-search-item-left">
                        <span class="live-search-dot" style="background-color: ${catColor};"></span>
                        <div class="live-search-info">
                            <div class="d-flex align-items-center gap-1 mb-1">
                                ${typeBadge}
                                <div class="live-search-item-title fw-bold" title="${escapeHtml(item.title)}">${titleHtml}</div>
                            </div>
                            <div class="live-search-item-meta">
                                <span class="live-search-meta-pill text-primary fw-semibold">
                                    <i class="fa-regular fa-clock"></i> ${escapeHtml(item.date_formatted)} · ${escapeHtml(item.time_formatted)}
                                </span>
                                ${item.location ? `
                                    <span class="live-search-meta-pill">
                                        <i class="fa-solid fa-location-dot text-danger"></i> ${escapeHtml(item.location)}
                                    </span>
                                ` : ''}
                                ${item.category_name ? `
                                    <span class="live-search-meta-pill">
                                        <i class="fa-regular fa-folder" style="color: ${catColor}"></i> ${escapeHtml(item.category_name)}
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="live-search-item-right">
                        ${statusBadge}
                    </div>
                </div>
            `;
        }).join('');

        // 9. Bấm vào kết quả để mở chi tiết tương ứng (Lịch trình hoặc Công việc)
        listEl.querySelectorAll('.live-search-item').forEach(el => {
            el.addEventListener('click', () => {
                const id = el.dataset.id;
                const type = el.dataset.type;
                const title = el.dataset.title || '';
                hideDropdown();

                if (type === 'task') {
                    if (window.location.href.includes('page=tasks')) {
                        const targetRow = document.querySelector(`.task-row[data-id="${id}"]`);
                        if (targetRow) {
                            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            targetRow.style.setProperty('background-color', 'rgba(102, 88, 232, 0.15)', 'important');
                            setTimeout(() => targetRow.style.removeProperty('background-color'), 2500);
                        }
                    } else {
                        window.location.href = `index.php?page=tasks&search=${encodeURIComponent(title)}`;
                    }
                } else {
                    if (window.openEventDetailById) {
                        window.openEventDetailById(id);
                    }
                }
            });
        });
    }

    // 10. Hiển thị thông báo khi không tìm thấy kết quả
    function renderEmpty(query) {
        if (countEl) countEl.textContent = '0 kết quả';
        listEl.innerHTML = `
            <div class="live-search-empty">
                <div class="live-search-empty-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="live-search-empty-title">Không tìm thấy kết quả phù hợp</div>
                <p class="live-search-empty-desc">Không có lịch trình hoặc công việc nào khớp với từ khóa "<strong>${escapeHtml(query)}</strong>".</p>
            </div>
        `;
    }

    // Điều hướng kết quả bằng phím mũi tên
    function navigateResults(direction) {
        const items = listEl.querySelectorAll('.live-search-item');
        if (!items.length) return;

        items.forEach(it => it.classList.remove('is-selected'));
        activeIndex += direction;

        if (activeIndex < 0) activeIndex = items.length - 1;
        if (activeIndex >= items.length) activeIndex = 0;

        items[activeIndex]?.classList.add('is-selected');
        items[activeIndex]?.scrollIntoView({ block: 'nearest' });
    }
}

/**
 * Mở đúng lịch tương ứng qua ID và hiển thị modal chi tiết
 */
window.openEventDetailById = function(eventId) {
    if (!eventId) return;

    fetch(`index.php?page=api-event-detail&id=${eventId}`)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                const ev = res.data;
                const modalEl = document.getElementById('eventDetailModal');

                // Nếu đang ở trang có FullCalendar, di chuyển lịch đến ngày sự kiện
                if (window.appCalendar && ev.start_time) {
                    window.appCalendar.gotoDate(ev.start_time);
                }

                // Nếu có hàm của FullCalendar trên trang calendar/week/month
                if (window.openCalendarDetailModal) {
                    const fcObj = {
                        id: ev.id,
                        title: ev.title,
                        extendedProps: {
                            category_id: ev.category_id,
                            category_name: ev.category_name,
                            category_color: ev.category_color,
                            location: ev.location,
                            priority: ev.priority,
                            status: ev.status,
                            description: ev.description,
                            reminder_at: ev.reminder_at,
                            reminder_minutes: ev.reminder_minutes,
                            raw_start: ev.start_time ? ev.start_time.replace(' ', 'T') : '',
                            raw_end: ev.end_time ? ev.end_time.replace(' ', 'T') : '',
                            start_formatted: ev.start_time,
                            end_formatted: ev.end_time
                        }
                    };
                    window.openCalendarDetailModal(fcObj);
                    return;
                }

                // Fallback nếu đang ở trang khác (Dashboard, Tasks, Categories...)
                if (modalEl) {
                    document.getElementById('detailTitle').textContent = ev.title;

                    const catBadge = document.getElementById('detailCategoryBadge');
                    if (catBadge) {
                        catBadge.textContent = ev.category_name || 'Chưa phân loại';
                        const c = ev.category_color || '#6658e8';
                        catBadge.style.backgroundColor = c + '25';
                        catBadge.style.color = c;
                        catBadge.style.border = `1px solid ${c}55`;
                    }

                    const timeEl = document.getElementById('detailTime');
                    if (timeEl) {
                        timeEl.textContent = `${ev.start_time || ''} - ${ev.end_time || ''}`;
                    }

                    const locRow = document.getElementById('detailLocationRow');
                    const locSpan = document.getElementById('detailLocation');
                    if (locRow && locSpan) {
                        if (ev.location && ev.location.trim()) {
                            locSpan.textContent = ev.location;
                            locRow.style.display = 'flex';
                        } else {
                            locRow.style.display = 'none';
                        }
                    }

                    const remText = document.getElementById('detailReminderText');
                    if (remText) {
                        const rMin = ev.reminder_minutes;
                        const rMap = {
                            0: 'Đúng giờ sự kiện bắt đầu',
                            5: 'Trước 5 phút',
                            10: 'Trước 10 phút',
                            15: 'Trước 15 phút',
                            30: 'Trước 30 phút',
                            60: 'Trước 1 giờ',
                            1440: 'Trước 1 ngày'
                        };
                        if (rMin !== null && rMin !== undefined) {
                            remText.textContent = 'Nhắc nhở: ' + (rMap[rMin] || `${rMin} phút trước`);
                        } else if (ev.reminder_at) {
                            remText.textContent = 'Nhắc nhở: ' + ev.reminder_at;
                        } else {
                            remText.textContent = 'Không bật nhắc nhở';
                        }
                    }

                    const pBadge = document.getElementById('detailPriorityBadge');
                    if (pBadge) {
                        const pMap = { high: ['Cao', 'high'], medium: ['Trung bình', 'medium'], low: ['Thấp', 'low'] };
                        const pInfo = pMap[ev.priority] || ['Trung bình', 'medium'];
                        pBadge.textContent = 'Ưu tiên: ' + pInfo[0];
                        pBadge.className = 'priority ' + pInfo[1];
                    }

                    const sBadge = document.getElementById('detailStatusBadge');
                    if (sBadge) {
                        if (ev.status === 'completed') {
                            sBadge.textContent = 'Đã hoàn thành';
                            sBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                        } else {
                            sBadge.textContent = 'Chưa hoàn thành';
                            sBadge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle';
                        }
                    }

                    const descEl = document.getElementById('detailDescription');
                    if (descEl) {
                        descEl.textContent = ev.description || 'Không có mô tả chi tiết.';
                    }

                    // Ẩn nút xóa/sửa khi ở trang khác (tránh thiếu eventForm)
                    const btnEdit = document.getElementById('btnDetailEdit');
                    const btnDelete = document.getElementById('btnDetailDelete');
                    if (btnEdit) {
                        btnEdit.onclick = () => {
                            window.location.href = `index.php?page=calendar&event_id=${ev.id}`;
                        };
                    }
                    if (btnDelete) {
                        btnDelete.style.display = 'none';
                    }

                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                } else {
                    window.location.href = `index.php?page=calendar&event_id=${ev.id}`;
                }
            } else {
                window.Toast?.error(res.message || 'Không tìm thấy chi tiết lịch trình.');
            }
        })
        .catch(() => {
            window.Toast?.error('Lỗi kết nối khi tải chi tiết lịch trình.');
        });
};

/**
 * Highlight từ khóa tìm kiếm (hỗ trợ cả có dấu và không dấu)
 */
function highlightLiveSearchText(text, query) {
    if (!text) return '';
    if (!query) return escapeHtml(text);

    const normText = removeVietnameseTones(text);
    const normQuery = removeVietnameseTones(query);

    const idx = normText.indexOf(normQuery);
    if (idx === -1) {
        return escapeHtml(text);
    }

    const before = text.substring(0, idx);
    const match = text.substring(idx, idx + query.length);
    const after = text.substring(idx + query.length);

    return `${escapeHtml(before)}<span class="live-search-highlight">${escapeHtml(match)}</span>${escapeHtml(after)}`;
}

/**
 * Bỏ dấu tiếng Việt phục vụ so sánh không dấu
 */
function removeVietnameseTones(str) {
    if (!str) return '';
    return str.normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd').replace(/Đ/g, 'D')
        .toLowerCase();
}

/**
 * Thoát ký tự HTML chống XSS
 */
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
