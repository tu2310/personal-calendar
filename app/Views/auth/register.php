<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Đăng ký') ?> | Personal Calendar</title>
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
            <h2 class="auth-title">Tạo tài khoản mới</h2>
            <p class="auth-subtitle text-muted">Bắt đầu quản lý thời gian hiệu quả cùng chúng tôi</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form action="index.php?page=register-post" method="POST" class="auth-form" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Họ và tên</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                    <input type="text" class="form-control" id="name" name="name" 
                           placeholder="Nguyễn Văn A" value="<?= htmlspecialchars($oldData['name'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">Địa chỉ Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="name@example.com" value="<?= htmlspecialchars($oldData['email'] ?? '') ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">Mật khẩu</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Tối thiểu 6 ký tự" minlength="6" required>
                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label for="password_confirm" class="form-label fw-semibold">Xác nhận mật khẩu</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-shield-halved"></i></span>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                           placeholder="Nhập lại mật khẩu" minlength="6" required>
                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-soft w-100 py-2 mt-2">
                <i class="fa-solid fa-user-plus me-2"></i>Đăng ký tài khoản
            </button>
        </form>

        <div class="auth-footer text-center mt-4 pt-3 border-top">
            <p class="mb-0 text-muted">Đã có tài khoản? 
                <a href="index.php?page=login" class="fw-semibold text-primary-accent text-decoration-none">Đăng nhập ngay</a>
            </p>
        </div>
    </div>
</div>

<script src="public/assets/js/toast.js"></script>
<script>
    if (localStorage.getItem('personalCalendarTheme') === 'dark') {
        document.body.classList.add('dark');
    }
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

    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const p1 = document.getElementById('password').value;
        const p2 = document.getElementById('password_confirm').value;
        if (p1 !== p2) {
            e.preventDefault();
            window.Toast?.warning('Mật khẩu xác nhận không khớp! Vui lòng kiểm tra lại.', 'Lưu ý!');
            return false;
        }
    });

    <?php
    $flashToast = Auth::getFlash('toast');
    if ($flashToast): ?>
        window.Toast?.show(<?= json_encode($flashToast, JSON_UNESCAPED_UNICODE) ?>);
    <?php elseif (!empty($error)): ?>
        window.Toast?.error(<?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>);
    <?php endif; ?>
</script>
</body>
</html>
