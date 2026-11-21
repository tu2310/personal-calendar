<?php
/**
 * Quản lý kết nối MySQL qua PDO
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');

class Database
{
    private string $host = 'localhost';
    private string $dbname = 'personal_calendar';
    private string $username = 'root';
    private string $password = '';
    private static ?PDO $instance = null;

    public function connect(): PDO
    {
        if (self::$instance === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            self::$instance = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
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
