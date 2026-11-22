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
        <!-- Đổi mật khẩu & Bảo mật -->
        <div class="panel mb-4">
            <div class="panel-heading">
                <div>
                    <h2>Đổi mật khẩu & Bảo mật</h2>
                    <p>Cập nhật mật khẩu để bảo vệ tài khoản cá nhân của bạn</p>
                </div>
            </div>
            <form id="changePasswordForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="mb-3">
                    <label for="currentPassword" class="form-label fw-bold">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="currentPassword" name="current_password" required placeholder="Nhập mật khẩu hiện tại" autocomplete="current-password">
                        <button class="btn btn-outline-secondary btn-toggle-pwd" type="button" data-target="currentPassword" title="Ẩn/Hiện mật khẩu">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label for="newPassword" class="form-label fw-bold">Mật khẩu mới <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="6" placeholder="Tối thiểu 6 ký tự" autocomplete="new-password">
                            <button class="btn btn-outline-secondary btn-toggle-pwd" type="button" data-target="newPassword" title="Ẩn/Hiện mật khẩu">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label for="confirmPassword" class="form-label fw-bold">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required minlength="6" placeholder="Nhập lại mật khẩu mới" autocomplete="new-password">
                            <button class="btn btn-outline-secondary btn-toggle-pwd" type="button" data-target="confirmPassword" title="Ẩn/Hiện mật khẩu">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary-soft" id="btnChangePassword">
                        <i class="fa-solid fa-key me-2"></i>Cập nhật mật khẩu
                    </button>
                    <small class="text-muted" style="font-size: 11px;">
                        <i class="fa-solid fa-shield-halved me-1 text-primary"></i>Tối thiểu 6 ký tự
                    </small>
                </div>
            </form>
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
