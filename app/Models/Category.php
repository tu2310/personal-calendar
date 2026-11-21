<?php
require_once __DIR__ . '/../Core/Database.php';

class Category
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(?int $userId = null): array
    {
        if ($userId) {
            $stmt = $this->db->prepare("
                SELECT id, user_id, name, color, COALESCE(NULLIF(icon, ''), 'fa-folder') AS icon, created_at 
                FROM categories 
                WHERE user_id = :userId OR user_id IS NULL 
                ORDER BY id ASC
            ");
            $stmt->execute(['userId' => $userId]);
        } else {
            $stmt = $this->db->query("
                SELECT id, user_id, name, color, COALESCE(NULLIF(icon, ''), 'fa-folder') AS icon, created_at 
                FROM categories 
                ORDER BY id ASC
            ");
        }
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, name, color, COALESCE(NULLIF(icon, ''), 'fa-folder') AS icon, created_at 
            FROM categories 
            WHERE id = :id 
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $cat = $stmt->fetch();
        return $cat ?: null;
    }

    public function create(int $userId, string $name, string $color, string $icon = 'fa-folder'): int
    {
        $cleanIcon = $this->sanitizeIcon($icon);
        $stmt = $this->db->prepare("
            INSERT INTO categories (user_id, name, color, icon, created_at)
            VALUES (:user_id, :name, :color, :icon, NOW())
        ");
        $stmt->execute([
            'user_id' => $userId,
            'name'    => trim($name),
            'color'   => $color ?: '#6658e8',
            'icon'    => $cleanIcon
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $userId, string $name, string $color, string $icon = 'fa-folder'): bool
    {
        // Chỉ cho phép người dùng sửa danh mục của chính họ
        $cleanIcon = $this->sanitizeIcon($icon);
        $stmt = $this->db->prepare("
            UPDATE categories 
            SET name = :name, color = :color, icon = :icon 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([
            'id'      => $id,
            'user_id' => $userId,
            'name'    => trim($name),
            'color'   => $color ?: '#6658e8',
            'icon'    => $cleanIcon
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        // Chỉ cho phép người dùng xóa danh mục của chính họ (không xóa danh mục hệ thống user_id IS NULL)
        $stmt = $this->db->prepare("
            DELETE FROM categories 
            WHERE id = :id AND user_id = :user_id
        ");
        return $stmt->execute([
            'id'      => $id,
            'user_id' => $userId
        ]);
    }

    private function sanitizeIcon(string $icon): string
    {
        $icon = trim($icon);
        if (!preg_match('/^fa-[a-z0-9-]+$/i', $icon)) {
            return 'fa-folder';
        }
        return strtolower($icon);
    }
}
