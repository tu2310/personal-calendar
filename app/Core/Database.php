<?php
/**
 * Quản lý kết nối MySQL qua PDO (hỗ trợ đọc cấu hình từ .env hoặc biến môi trường)
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');

class Database
{
    private static ?PDO $instance = null;
    private static ?array $envCache = null;

    public static function loadEnv(): array
    {
        if (self::$envCache !== null) {
            return self::$envCache;
        }
        $vars = [];
        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }
                $pos = strpos($trimmed, '=');
                if ($pos === false) {
                    continue;
                }
                $key = trim(substr($trimmed, 0, $pos));
                $val = trim(substr($trimmed, $pos + 1));
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                if ($key !== '') {
                    $vars[$key] = $val;
                }
            }
        }
        self::$envCache = $vars;
        return $vars;
    }

    private static function env(string $key, string $default = ''): string
    {
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string)$fromEnv;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }
        $fileVars = self::loadEnv();
        return array_key_exists($key, $fileVars) ? $fileVars[$key] : $default;
    }

    public static function config(): array
    {
        return [
            'host'     => self::env('DB_HOST', '127.0.0.1'),
            'port'     => (int)self::env('DB_PORT', '3306') ?: 3306,
            'dbname'   => self::env('DB_NAME', 'personal_calendar'),
            'username' => self::env('DB_USER', 'root'),
            'password' => self::env('DB_PASS', ''),
        ];
    }

    public function connect(): PDO
    {
        if (self::$instance === null) {
            $cfg = self::config();
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset=utf8mb4";
            self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+07:00'"
            ]);
        }
        return self::$instance;
    }

    public static function getConnection(): PDO
    {
        return (new self())->connect();
    }
}
