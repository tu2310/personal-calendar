<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Đăng nhập') ?> | Personal Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="public/assets/css/toast.css" rel="stylesheet">
    <link href="public/assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-wrapper">
    <div class="auth-card panel">
        <div class="auth-header text-center">
            <div class="auth-brand mb-3">
                <span class="brand-icon"><i class="fa-solid fa-calendar-check"></i></span>
                <span class="fs-4 fw-bold">Personal <span class="text-primary-accent">Calendar</span></span>
            </div>
            <h2 class="auth-title">Đăng nhập</h2>
            <p class="auth-subtitle">Truy cập lịch trình và danh sách công việc cá nhân</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success d-flex align-items-center mb-3 py-2 px-3" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
        <?php endif; ?>

        <form action="index.php?page=login-post" method="POST" class="auth-form" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">Tài khoản / Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                    <input type="text" class="form-control" id="email" name="email" 
                           placeholder="admin hoặc name@example.com" value="<?= htmlspecialchars($oldEmail ?? '') ?>" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label fw-semibold mb-0">Mật khẩu</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="••••••••" required>
                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-soft w-100 py-2 mt-2">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Đăng nhập
            </button>
        </form>

        <div class="auth-footer text-center mt-4 pt-3 border-top">
            <p class="mb-0 text-muted">Chưa có tài khoản? 
                <a href="index.php?page=register" class="fw-semibold text-primary-accent text-decoration-none">Đăng ký ngay</a>
            </p>
        </div>
    </div>
</div>

<script src="public/assets/js/toast.js"></script>
<script>
    // Khởi tạo theme từ localStorage
    if (localStorage.getItem('personalCalendarTheme') === 'dark') {
        document.body.classList.add('dark');
    }
    // Toggle hiển thị mật khẩu
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.input-group').querySelector('input');
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    <?php
    $flashToast = Auth::getFlash('toast');
    if ($flashToast): ?>
        window.Toast?.show(<?= json_encode($flashToast, JSON_UNESCAPED_UNICODE) ?>);
    <?php elseif (!empty($error)): ?>
        window.Toast?.error(<?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>);
    <?php elseif (!empty($success)): ?>
        window.Toast?.success(<?= json_encode($success, JSON_UNESCAPED_UNICODE) ?>);
    <?php endif; ?>
</script>
</body>
</html>
