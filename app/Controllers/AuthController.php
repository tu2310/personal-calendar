<?php
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Notification.php';

class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function showLogin(): void
    {
        Auth::requireGuest();
        $error = Auth::getFlash('error');
        $success = Auth::getFlash('success');
        $oldEmail = Auth::getFlash('old_email') ?? '';
        $csrfToken = Auth::csrfToken();
        $pageTitle = 'Đăng nhập';
        require __DIR__ . '/../Views/auth/login.php';
    }

    public function login(): void
    {
        Auth::requireGuest();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=login');
            exit;
        }

        $csrf = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if (!Auth::verifyCsrf($csrf)) {
            Auth::setFlash('error', 'Mã xác thực CSRF không hợp lệ. Vui lòng thử lại.');
            Auth::setFlash('toast', ['type' => 'error', 'title' => 'Lỗi bảo mật', 'message' => 'Mã xác thực CSRF không hợp lệ.']);
            header('Location: index.php?page=login');
            exit;
        }

        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        Auth::setFlash('old_email', $email);

        if (empty($email) || empty($password)) {
            $msg = 'Vui lòng điền đầy đủ tài khoản và mật khẩu.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'warning', 'title' => 'Thiếu thông tin', 'message' => $msg]);
            header('Location: index.php?page=login');
            exit;
        }

        if ($email !== 'admin' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Địa chỉ email không đúng định dạng.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'warning', 'title' => 'Email không hợp lệ', 'message' => $msg]);
            header('Location: index.php?page=login');
            exit;
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user || !password_verify($password, $user['password'])) {
            $msg = 'Email hoặc mật khẩu không chính xác.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'error', 'title' => 'Đăng nhập thất bại', 'message' => $msg]);
            header('Location: index.php?page=login');
            exit;
        }

        // Đăng nhập thành công
        Auth::login($user);
        Auth::setFlash('toast', [
            'type' => 'success',
            'title' => 'Đăng nhập thành công!',
            'message' => 'Chào mừng ' . $user['name'] . ' quay trở lại với Personal Calendar.'
        ]);
        header('Location: index.php?page=dashboard');
        exit;
    }

    public function showRegister(): void
    {
        Auth::requireGuest();
        $error = Auth::getFlash('error');
        $success = Auth::getFlash('success');
        $oldData = Auth::getFlash('old_data') ?? [];
        $csrfToken = Auth::csrfToken();
        $pageTitle = 'Đăng ký tài khoản';
        require __DIR__ . '/../Views/auth/register.php';
    }

    public function register(): void
    {
        Auth::requireGuest();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?page=register');
            exit;
        }

        $csrf = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if (!Auth::verifyCsrf($csrf)) {
            Auth::setFlash('error', 'Mã bảo mật CSRF không hợp lệ.');
            Auth::setFlash('toast', ['type' => 'error', 'title' => 'Lỗi bảo mật', 'message' => 'Mã bảo mật CSRF không hợp lệ.']);
            header('Location: index.php?page=register');
            exit;
        }

        $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $passwordConfirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

        Auth::setFlash('old_data', ['name' => $name, 'email' => $email]);

        if (empty($name) || empty($email) || empty($password)) {
            $msg = 'Vui lòng điền đầy đủ tất cả các trường.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'warning', 'title' => 'Thiếu thông tin', 'message' => $msg]);
            header('Location: index.php?page=register');
            exit;
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $msg = 'Họ tên phải từ 2 đến 100 ký tự.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'warning', 'title' => 'Họ tên không hợp lệ', 'message' => $msg]);
            header('Location: index.php?page=register');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Địa chỉ email không hợp lệ.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'warning', 'title' => 'Email không hợp lệ', 'message' => $msg]);
            header('Location: index.php?page=register');
            exit;
        }

        if ($this->userModel->emailExists($email)) {
            $msg = 'Email này đã được sử dụng. Vui lòng chọn email khác hoặc đăng nhập.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'error', 'title' => 'Email đã tồn tại', 'message' => $msg]);
            header('Location: index.php?page=register');
            exit;
        }

        if (strlen($password) < 6) {
            $msg = 'Mật khẩu phải có ít nhất 6 ký tự.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'warning', 'title' => 'Mật khẩu quá ngắn', 'message' => $msg]);
            header('Location: index.php?page=register');
            exit;
        }

        if ($password !== $passwordConfirm) {
            $msg = 'Mật khẩu xác nhận không khớp.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'warning', 'title' => 'Lỗi xác nhận', 'message' => $msg]);
            header('Location: index.php?page=register');
            exit;
        }

        // Tạo tài khoản mới
        $userId = $this->userModel->create($name, $email, $password);
        if ($userId) {
            (new Notification())->create(
                (int)$userId,
                'Chào mừng đến với Personal Calendar!',
                "Xin chào {$name}! Tài khoản của bạn đã sẵn sàng. Hãy bắt đầu lên lịch trình và công việc ngay hôm nay!",
                'system',
                'index.php?page=dashboard',
                'welcome',
                (int)$userId
            );

            $user = $this->userModel->findById($userId);
            Auth::login($user);
            Auth::setFlash('toast', [
                'type' => 'success',
                'title' => 'Đăng ký thành công!',
                'message' => 'Chào mừng bạn đến với Personal Calendar. Hãy bắt đầu lên kế hoạch ngay!'
            ]);
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $msg = 'Đã có lỗi xảy ra trong quá trình tạo tài khoản. Vui lòng thử lại.';
            Auth::setFlash('error', $msg);
            Auth::setFlash('toast', ['type' => 'error', 'title' => 'Lỗi hệ thống', 'message' => $msg]);
            header('Location: index.php?page=register');
            exit;
        }
    }

    public function logout(): void
    {
        Auth::logout();
        Auth::setFlash('toast', [
            'type' => 'info',
            'title' => 'Đăng xuất thành công',
            'message' => 'Bạn đã đăng xuất tài khoản an toàn. Hẹn gặp lại!'
        ]);
        header('Location: index.php?page=login');
        exit;
    }

    public function apiChangePassword(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = Auth::id();
        $input = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            $input = is_array($data) ? $data : [];
        } else {
            $input = $_POST;
        }

        $csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!Auth::verifyCsrf($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Mã xác thực CSRF không hợp lệ.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $currentPassword = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
        $newPassword = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';
        $confirmPassword = is_string($input['confirm_password'] ?? null) ? $input['confirm_password'] : '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ các trường mật khẩu.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (strlen($newPassword) < 6) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có tối thiểu 6 ký tự.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (strlen($newPassword) > 128) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Mật khẩu mới không được vượt quá 128 ký tự.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Mật khẩu xác nhận không khớp với mật khẩu mới.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($currentPassword === $newPassword) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Mật khẩu mới không được trùng với mật khẩu hiện tại.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $user = $this->userModel->findWithPassword($userId);
        if (!$user || empty($user['password']) || !password_verify($currentPassword, $user['password'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Mật khẩu hiện tại không chính xác.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $updated = $this->userModel->updatePassword($userId, $newPassword);
        if (!$updated) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Đã có lỗi xảy ra khi cập nhật mật khẩu.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'unread_count' => Notification::countUnread($userId),
            'message' => 'Đổi mật khẩu thành công! Hãy ghi nhớ mật khẩu mới của bạn.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
