<?php
/**
 * Lớp Auth quản lý Session, Đăng nhập, CSRF và Phân quyền
 */
class Auth
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (!self::$initialized && session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            session_start();
        }
        self::$initialized = true;
    }

    public static function login(array $user): void
    {
        self::init();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'avatar' => $user['avatar'] ?? null
        ];
    }

    public static function logout(): void
    {
        self::init();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::init();
        return !empty($_SESSION['user']['id']);
    }

    public static function user(): ?array
    {
        self::init();
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        self::init();
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            self::setFlash('error', 'Vui lòng đăng nhập để tiếp tục.');
            header('Location: index.php?page=login');
            exit;
        }
    }

    public static function requireGuest(): void
    {
        if (self::check()) {
            header('Location: index.php?page=dashboard');
            exit;
        }
    }

    public static function csrfToken(): string
    {
        self::init();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::init();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function setFlash(string $key, $message): void
    {
        self::init();
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlash(string $key)
    {
        self::init();
        if (isset($_SESSION['flash'][$key])) {
            $val = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $val;
        }
        return null;
    }

    public static function hasFlash(string $key): bool
    {
        self::init();
        return isset($_SESSION['flash'][$key]);
    }
}
