<?php
$user = $data['user'] ?? Auth::user();
$csrfToken = $data['csrf_token'] ?? '';
?>
<section class="welcome-row">
    <div>
        <div class="eyebrow">Tùy chỉnh</div>
        <h1>Cài đặt</h1>
        <p class="page-subtitle">Thông tin tài khoản, giao diện và tùy chọn nhắc nhở</p>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-6">
        <!-- Thông tin cá nhân -->
        <div class="panel mb-4">
            <div class="panel-heading">
                <div>
                    <h2>Thông tin tài khoản</h2>
                    <p>Cập nhật tên hiển thị của bạn trên hệ thống</p>
                </div>
            </div>
            <form id="profileForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="mb-3">
                    <label for="profileName" class="form-label fw-bold">Họ và tên</label>
                    <input type="text" class="form-control" id="profileName" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Địa chỉ Email</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled readonly style="opacity: 0.7;">
                    <small class="text-muted" style="font-size: 10px;">Email đăng nhập không thể thay đổi trực tiếp.</small>
                </div>
                <button type="submit" class="btn btn-primary-soft" id="btnSaveProfile">
                    <i class="fa-solid fa-check me-2"></i>Lưu thông tin cá nhân
                </button>
            </form>
        </div>

        <!-- Cài đặt giao diện -->
        <div class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Giao diện người dùng</h2>
                    <p>Chuyển đổi giữa chế độ Sáng và Tối</p>
                </div>
            </div>
            <p>Chọn biểu tượng mặt trăng / mặt trời ở góc trên bên phải hoặc bấm nút bên dưới để chuyển đổi giao diện tức thì. Tùy chọn sẽ được ghi nhớ trên thiết bị này.</p>
            <button class="btn btn-primary-soft" onclick="document.getElementById('themeToggle').click()">
                <i class="fa-solid fa-circle-half-stroke me-2"></i>Chuyển đổi Light / Dark Mode
            </button>
        </div>
    </div>

    <div class="col-lg-6">
        <!-- Thông báo trình duyệt (Browser Notification API) -->
        <div class="panel mb-4">
            <div class="panel-heading">
                <div>
                    <h2>Thông báo Trình duyệt (Browser Notification)</h2>
                    <p>Nhận thông báo nổi từ trình duyệt khi sự kiện hoặc công việc sắp bắt đầu</p>
                </div>
            </div>

            <div class="p-3 mb-3 rounded-3 border" style="background: var(--surface-2); border-color: var(--line) !important;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <strong class="d-block" style="font-size: 12.5px;">Trạng thái cấp quyền:</strong>
                        <span class="badge" id="browserNotifPermissionBadge" style="font-size: 11px;">Đang kiểm tra...</span>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="prefBrowserNotif" style="cursor: pointer; width: 38px; height: 20px;">
                    </div>
                </div>
                <small class="text-muted d-block mt-2" style="font-size: 11px;">
                    <i class="fa-solid fa-circle-info me-1 text-primary"></i>
                    Personal Calendar chỉ xin quyền khi bạn chủ động bấm kích hoạt. Nếu từ chối, ứng dụng vẫn luôn gửi thông báo qua chuông và Toast bên trong website.
                </small>
            </div>

            <!-- Hướng dẫn mở khóa khi trình duyệt chặn quyền -->
            <div id="browserNotifUnblockGuide" class="p-3 mb-3 rounded-3 border border-warning" style="background: rgba(245, 158, 11, 0.08); display: none;">
                <div class="d-flex align-items-start gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-warning fs-5 mt-1"></i>
                    <div>
                        <strong class="text-warning d-block mb-1" style="font-size: 13px;">Trình duyệt đang chặn thông báo trên localhost</strong>
                        <p class="mb-2 text-muted" style="font-size: 11.5px; line-height: 1.5;">
                            Do chính sách bảo mật của trình duyệt, khi đã chọn "Chặn", JavaScript không thể tự động xin lại quyền mà bạn cần mở lại bằng tay:
                        </p>
                        <ol class="mb-2 ps-3 text-muted" style="font-size: 0.8125rem; line-height: 1.6;">
                            <li>Bấm vào biểu tượng <strong>Cài đặt trang web</strong> ở đầu thanh địa chỉ URL bên trên.</li>
                            <li>Tìm mục <strong>Thông báo (Notifications)</strong> và chọn <strong>Cho phép (Allow)</strong>.</li>
                            <li>Tải lại trang (nhấn phím <code>F5</code>).</li>
                        </ol>
                        <div class="badge bg-warning-subtle text-warning" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-shield-halved me-1"></i>Thông báo âm thanh, Toast và chuông trong trang vẫn hoạt động bình thường
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary-soft btn-sm" id="btnEnableBrowserNotif">
                    <i class="fa-solid fa-bell me-1"></i> Bật & Cấp quyền thông báo
                </button>
                <button type="button" class="btn btn-light btn-sm" id="btnTestBrowserNotif">
                    <i class="fa-solid fa-paper-plane me-1 text-primary"></i> Gửi thử thông báo
                </button>
            </div>

            <div class="mt-3 pt-2 border-top">
                <small class="text-muted" style="font-size: 10.5px;">
                    * Lưu ý: Thông báo trình duyệt hoạt động khi tab website hoặc trình duyệt đang mở. Khi đóng hoàn toàn trình duyệt sẽ yêu cầu giải pháp Web Push Server riêng biệt.
                </small>
            </div>
        </div>

        <!-- Tùy chọn thông báo & nhắc nhở in-app -->
        <div class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Tùy chọn Nhắc nhở & Âm thanh</h2>
                    <p>Quản lý các thông báo bên trong website</p>
                </div>
            </div>
            <form id="preferencesForm">
                <div class="mb-3 form-check form-switch ps-0 d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong class="d-block" style="font-size: 12px;">Âm thanh thông báo</strong>
                        <small class="text-muted">Phát chuông ngắn khi có lịch sắp tới hoặc thông báo mới</small>
                    </div>
                    <input class="form-check-input ms-0" type="checkbox" role="switch" id="prefSound" checked style="width: 38px; height: 20px;">
                </div>
                <div class="mb-3 form-check form-switch ps-0 d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong class="d-block" style="font-size: 12px;">Thông báo Toast nổi</strong>
                        <small class="text-muted">Hiển thị thẻ Toast góc trên bên phải khi đến giờ nhắc lịch</small>
                    </div>
                    <input class="form-check-input ms-0" type="checkbox" role="switch" id="prefReminder" checked style="width: 38px; height: 20px;">
                </div>
                <div class="mb-4 form-check form-switch ps-0 d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong class="d-block" style="font-size: 12px;">Đếm ngược tự động Pomodoro</strong>
                        <small class="text-muted">Tự động bắt đầu giờ nghỉ sau khi kết thúc phiên làm việc</small>
                    </div>
                    <input class="form-check-input ms-0" type="checkbox" role="switch" id="prefAutoRest" style="width: 38px; height: 20px;">
                </div>
                <button type="submit" class="btn btn-primary-soft" id="btnSavePreferences">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Lưu tùy chọn cài đặt
                </button>
            </form>
        </div>
    </div>
</div>
