-- Personal Calendar - Database Migration Phase 3
-- Giai đoạn 3: Nhắc nhở thông minh, Trung tâm thông báo, Quản lý công việc nâng cao & Biểu tượng danh mục

USE personal_calendar;

-- 1. Bổ sung cột icon cho bảng categories (nếu chưa có)
SET @dbname = DATABASE();
SET @tablename = "categories";
SET @columnname = "icon";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE categories ADD COLUMN icon VARCHAR(50) NOT NULL DEFAULT 'fa-folder' AFTER color"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Bổ sung cột reminder_minutes cho bảng events (nếu chưa có)
SET @tablename = "events";
SET @columnname = "reminder_minutes";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE events ADD COLUMN reminder_minutes INT NULL DEFAULT NULL AFTER reminder_at"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. Nâng cấp bảng tasks: category_id, description, priority, status, updated_at
-- 3.1. category_id
SET @tablename = "tasks";
SET @columnname = "category_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE tasks ADD COLUMN category_id INT UNSIGNED NULL AFTER user_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3.2. description
SET @tablename = "tasks";
SET @columnname = "description";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE tasks ADD COLUMN description TEXT NULL AFTER title"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3.3. priority
SET @tablename = "tasks";
SET @columnname = "priority";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE tasks ADD COLUMN priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium' AFTER description"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3.4. status
SET @tablename = "tasks";
SET @columnname = "status";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE tasks ADD COLUMN status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending' AFTER priority"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3.5. updated_at
SET @tablename = "tasks";
SET @columnname = "updated_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE tasks ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Đồng bộ status theo is_completed cho tasks cũ nếu có
UPDATE tasks SET status = IF(is_completed = 1, 'completed', 'pending') WHERE status IS NULL OR status = '';

-- 4. Nâng cấp bảng notifications: action_url, reference_type, reference_id
-- 4.1. action_url
SET @tablename = "notifications";
SET @columnname = "action_url";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE notifications ADD COLUMN action_url VARCHAR(255) NULL AFTER message"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4.2. reference_type
SET @tablename = "notifications";
SET @columnname = "reference_type";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE notifications ADD COLUMN reference_type VARCHAR(50) NULL AFTER action_url"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4.3. reference_id
SET @tablename = "notifications";
SET @columnname = "reference_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE notifications ADD COLUMN reference_id INT UNSIGNED NULL AFTER reference_type"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 5. Nâng cấp bảng reminders: user_id, sent_at
-- 5.1. user_id
SET @tablename = "reminders";
SET @columnname = "user_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE reminders ADD COLUMN user_id INT UNSIGNED NULL AFTER id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 5.2. sent_at
SET @tablename = "reminders";
SET @columnname = "sent_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE reminders ADD COLUMN sent_at DATETIME NULL AFTER is_sent"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 6. Cập nhật icon phong phú mặc định cho các danh mục mẫu (nếu còn fa-folder)
UPDATE categories SET icon = 'fa-briefcase' WHERE name LIKE '%Công việc%' AND (icon IS NULL OR icon = 'fa-folder');
UPDATE categories SET icon = 'fa-graduation-cap' WHERE name LIKE '%Học tập%' AND (icon IS NULL OR icon = 'fa-folder');
UPDATE categories SET icon = 'fa-user' WHERE name LIKE '%Cá nhân%' AND (icon IS NULL OR icon = 'fa-folder');
UPDATE categories SET icon = 'fa-handshake' WHERE name LIKE '%Họp%' AND (icon IS NULL OR icon = 'fa-folder');
UPDATE categories SET icon = 'fa-heart-pulse' WHERE name LIKE '%Sức khỏe%' AND (icon IS NULL OR icon = 'fa-folder');
UPDATE categories SET icon = 'fa-palette' WHERE name LIKE '%Thiết kế%' AND (icon IS NULL OR icon = 'fa-folder');
