<?php
require_once __DIR__ . '/../Core/Database.php';

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => trim($email)]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, name, email, avatar, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findWithPassword(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, name, email, password, avatar, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :id");
        return $stmt->execute(['password' => $hash, 'id' => $id]);
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => trim($email)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(string $name, string $email, string $password, ?string $avatar = null): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, avatar, created_at)
            VALUES (:name, :email, :password, :avatar, NOW())
        ");
        $stmt->execute([
            'name' => trim($name),
            'email' => trim(strtolower($email)),
            'password' => $hash,
            'avatar' => $avatar
        ]);
        return (int)$this->db->lastInsertId();
    }
}
