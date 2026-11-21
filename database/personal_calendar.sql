-- ========================================================
-- Personal Calendar - Full Database Schema & Real Data
-- Generated: 2026-11-21 08:12:45
-- Database: personal_calendar
-- ========================================================

CREATE DATABASE IF NOT EXISTS `personal_calendar` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `personal_calendar`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Cấu trúc bảng `assignments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_assignments_user` (`user_id`),
  CONSTRAINT `fk_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Cấu trúc bảng `categories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#64748b',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa-folder',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_category` (`user_id`,`name`),
  CONSTRAINT `fk_categories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu thực tế cho bảng `categories`
INSERT INTO `categories` (`id`, `user_id`, `name`, `color`, `icon`, `created_at`) VALUES
('1', '1', 'Họp & Khách hàng', '#5b52e0', 'fa-handshake', '2026-11-21 08:10:36'),
('2', '1', 'Công việc & Dự án', '#38bdf8', 'fa-briefcase', '2026-11-21 08:10:36'),
('3', '1', 'Thiết kế & Sáng tạo', '#f43f5e', 'fa-palette', '2026-11-21 08:10:36'),
('4', '1', 'Cá nhân & Gia đình', '#10b981', 'fa-house-user', '2026-11-21 08:10:36'),
('5', '1', 'Học tập & Kỹ năng', '#f59e0b', 'fa-graduation-cap', '2026-11-21 08:10:36'),
('6', '1', 'Sức khỏe & Thể thao', '#06b6d4', 'fa-heart-pulse', '2026-11-21 08:10:36'),
('7', '1', 'Tài chính & Đầu tư', '#8b5cf6', 'fa-wallet', '2026-11-21 08:10:36');

-- --------------------------------------------------------
-- Cấu trúc bảng `events`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` enum('pending','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reminder_at` datetime DEFAULT NULL,
  `reminder_minutes` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_events_category` (`category_id`),
  KEY `idx_events_user_start` (`user_id`,`start_time`),
  KEY `idx_events_user_status` (`user_id`,`status`),
  KEY `idx_events_start_end` (`start_time`,`end_time`),
  CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu thực tế cho bảng `events`
INSERT INTO `events` (`id`, `user_id`, `category_id`, `title`, `description`, `start_time`, `end_time`, `location`, `priority`, `status`, `reminder_at`, `reminder_minutes`, `created_at`, `updated_at`) VALUES
('21', '1', '6', 'Chạy bộ rèn luyện sức khỏe buổi sáng', 'Chạy 5km nhẹ nhàng quanh công viên để khởi động cuối tuần năng động.', '2026-11-21 06:30:00', '2026-11-21 07:30:00', 'Công viên Cầu Giấy, Hà Nội', 'low', 'completed', NULL, NULL, '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('22', '1', '1', 'Gặp mặt trao đổi tiến độ hợp tác với đối tác', 'Thảo luận về kế hoạch triển khai giải pháp số hóa lịch trình cho quý tới.', '2026-11-21 09:30:00', '2026-11-21 11:00:00', 'Highlands Coffee - 32 Tràng Tiền, Hoàn Kiếm', 'high', 'pending', NULL, '30', '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('23', '1', '5', 'Khóa học trực tuyến: Quản trị dự án Agile & Scrum', 'Tham gia buổi đào tạo chuyên sâu về tối ưu hóa quy trình làm việc nhóm.', '2026-11-21 14:30:00', '2026-11-21 16:00:00', 'Zoom Meeting (Trực tuyến)', 'medium', 'pending', NULL, '15', '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('24', '1', '4', 'Ăn tối cuối tuần ấm cúng cùng gia đình', 'Dành thời gian sum họp gia đình cuối tuần, thư giãn sau tuần làm việc.', '2026-11-21 18:30:00', '2026-11-21 20:30:00', 'Nhà hàng Sen Tây Hồ, Hà Nội', 'medium', 'pending', NULL, '60', '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('25', '1', '4', 'Bảo dưỡng và chăm sóc xe định kỳ', 'Kiểm tra dầu máy, hệ thống phanh và rửa xe chuẩn bị cho tuần công tác mới.', '2026-11-22 08:30:00', '2026-11-22 10:30:00', 'Trung tâm dịch vụ ô tô Toyota Mỹ Đình', 'low', 'pending', NULL, NULL, '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('26', '1', '5', 'Đọc sách và lập kế hoạch mục tiêu tuần mới', 'Tổng hợp mục tiêu trọng tâm cần đạt được và sắp xếp thời gian biểu tuần 48.', '2026-11-22 15:00:00', '2026-11-22 16:30:00', 'Phòng làm việc tại nhà', 'low', 'pending', NULL, NULL, '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('27', '1', '1', 'Họp giao ban đầu tuần với Ban Giám đốc', 'Báo cáo nhanh kết quả công việc tuần qua và triển khai kế hoạch chiến lược mới.', '2026-11-23 08:30:00', '2026-11-23 09:30:00', 'Phòng họp VIP - Tầng 3 Trụ sở chính', 'high', 'pending', NULL, '15', '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('28', '1', '3', 'Review giao diện Dashboard & Thiết kế hệ thống', 'Đánh giá bản mẫu giao diện người dùng mới cùng đội ngũ UI/UX Designer.', '2026-11-24 10:00:00', '2026-11-24 11:30:00', 'Phòng họp kỹ thuật B2', 'high', 'pending', NULL, '15', '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('29', '1', '7', 'Làm việc với bộ phận Kế toán & Kiểm toán nội bộ', 'Đối soát chi phí dự án và rà soát hồ sơ thanh toán hợp đồng tháng 11.', '2026-11-25 14:00:00', '2026-11-25 15:30:00', 'Văn phòng Kế toán - Tầng 2', 'medium', 'pending', NULL, '30', '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('30', '1', '2', 'Phỏng vấn ứng viên Senior Developer', 'Đánh giá năng lực chuyên môn và tư duy kiến trúc hệ thống của ứng viên.', '2026-11-26 09:30:00', '2026-11-26 11:00:00', 'Phòng phỏng vấn số 1', 'high', 'pending', NULL, '15', '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('31', '1', '2', 'Tổng kết nghiệm thu Sprint công nghệ 44', 'Trình diễn tính năng mới (Live Search, Toast Notification, Quản lý Tasks) trước hội đồng.', '2026-11-27 16:00:00', '2026-11-27 17:30:00', 'Google Meet (Online)', 'medium', 'pending', NULL, '30', '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('32', '1', '6', 'Đạp xe thể thao dã ngoại cùng câu lạc bộ', 'Tham gia buổi đạp xe 20km ngắm cảnh và rèn luyện thể lực cuối tuần.', '2026-11-28 06:00:00', '2026-11-28 08:00:00', 'Cung đường Hồ Tây, Hà Nội', 'low', 'pending', NULL, NULL, '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('33', '1', '1', 'Họp triển khai kế hoạch kinh doanh tháng 11', 'Giao chỉ tiêu kinh doanh và phân công nhiệm vụ cho các trưởng bộ phận.', '2026-11-16 09:00:00', '2026-11-16 10:30:00', 'Phòng họp chính', 'high', 'completed', NULL, NULL, '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('34', '1', '5', 'Hội thảo trực tuyến: Ứng dụng AI trong quản trị hiện đại', 'Chia sẻ kinh nghiệm tích hợp trợ lý AI vào quy trình làm việc doanh nghiệp.', '2026-11-17 14:00:00', '2026-11-17 15:30:00', 'Microsoft Teams', 'medium', 'completed', NULL, NULL, '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('35', '1', '2', 'Khảo sát thực địa địa điểm mở rộng văn phòng', 'Đánh giá cơ sở hạ tầng, vị trí và tiện ích của tòa nhà văn phòng mới.', '2026-11-18 10:00:00', '2026-11-18 11:30:00', 'Tòa nhà Keangnam Landmark 72', 'medium', 'completed', NULL, NULL, '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('36', '1', '1', 'Lễ ký kết biên bản ghi nhớ hợp tác chiến lược', 'Chính thức ký kết hợp tác phát triển nền tảng quản trị thông minh.', '2026-11-19 15:00:00', '2026-11-19 16:30:00', 'Khách sạn Melia Hà Nội', 'high', 'completed', NULL, NULL, '2026-11-21 08:10:36', '2026-11-21 08:10:36'),
('37', '1', '4', 'Gặp mặt tri ân thầy cô nhân ngày Nhà giáo Việt Nam', 'Thăm và chúc mừng các thầy cô giáo cũ nhân ngày 20/11.', '2026-11-20 16:30:00', '2026-11-20 18:00:00', 'Đại học Bách Khoa Hà Nội', 'low', 'completed', NULL, NULL, '2026-11-21 08:10:36', '2026-11-21 08:10:36');

-- --------------------------------------------------------
-- Cấu trúc bảng `notifications`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'event',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `action_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` int unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `time_ago` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2 hours ago',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notifications_user` (`user_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu thực tế cho bảng `notifications`
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `action_url`, `reference_type`, `reference_id`, `is_read`, `time_ago`, `created_at`) VALUES
('18', '1', 'event', 'Lịch trình sắp diễn ra hôm nay', 'Bạn có cuộc hẹn \"Gặp mặt trao đổi tiến độ hợp tác với đối tác\" lúc 09:30 tại Highlands Coffee.', 'index.php?page=calendar', 'event', NULL, '0', '10 phút trước', '2026-11-21 08:00:36'),
('19', '1', 'task', 'Công việc cần xử lý gấp', 'Công việc \"Hoàn thiện báo cáo tài chính quý 4 gửi Ban Giám đốc\" đã quá hạn 2 ngày. Vui lòng kiểm tra!', 'index.php?page=tasks', 'task', NULL, '0', '1 giờ trước', '2026-11-21 07:10:36'),
('20', '1', 'event', 'Lịch trình đầu tuần mới đã sẵn sàng', 'Cuộc họp \"Họp giao ban đầu tuần với Ban Giám đốc\" được lên lịch vào 08:30 Thứ 2 (23/11).', 'index.php?page=week', 'event', NULL, '0', '3 giờ trước', '2026-11-21 05:10:36'),
('21', '1', 'system', 'Đồng bộ hệ thống thành công', 'Toàn bộ dữ liệu lịch trình, nhiệm vụ và danh mục của bạn đã được cập nhật chuẩn xác.', 'index.php?page=dashboard', 'system', NULL, '1', 'Hôm qua', '2026-11-20 08:10:36'),
('22', '1', 'task', 'Công việc đã hoàn thành', 'Bạn đã hoàn tất công việc \"Gia hạn thẻ tập gym và gói chăm sóc thể lực cá nhân\".', 'index.php?page=tasks', 'task', NULL, '1', '3 ngày trước', '2026-11-18 08:10:36'),
('23', '1', 'task', 'Công việc cần xử lý: Kiểm tra và ký duyệt hợp đồng cung cấp dịch vụ phần mềm', 'Công việc \"Kiểm tra và ký duyệt hợp đồng cung cấp dịch vụ phần mềm\" đã quá hạn. Vui lòng kiểm tra và hoàn thành sớm!', 'index.php?page=tasks&task_id=15', 'task', '15', '0', 'Vừa xong', '2026-11-21 08:11:19'),
('24', '1', 'task', 'Công việc cần xử lý: Hoàn thiện báo cáo tài chính quý 4 gửi Ban Giám đốc', 'Công việc \"Hoàn thiện báo cáo tài chính quý 4 gửi Ban Giám đốc\" đã quá hạn. Vui lòng kiểm tra và hoàn thành sớm!', 'index.php?page=tasks&task_id=14', 'task', '14', '0', 'Vừa xong', '2026-11-21 08:11:19');

-- --------------------------------------------------------
-- Cấu trúc bảng `pomodoro_sessions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pomodoro_sessions`;
CREATE TABLE `pomodoro_sessions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `duration_minutes` int unsigned NOT NULL DEFAULT '25',
  `mode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'focus',
  `completed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pomo_user` (`user_id`),
  CONSTRAINT `fk_pomo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu thực tế cho bảng `pomodoro_sessions`
INSERT INTO `pomodoro_sessions` (`id`, `user_id`, `duration_minutes`, `mode`, `completed_at`) VALUES
('1', '1', '25', 'focus', '2026-11-21 08:30:00'),
('2', '1', '25', 'focus', '2026-11-21 09:15:00'),
('3', '1', '25', 'focus', '2026-11-21 14:00:00');

-- --------------------------------------------------------
-- Cấu trúc bảng `reminders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `reminders`;
CREATE TABLE `reminders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `event_id` int unsigned NOT NULL,
  `remind_at` datetime NOT NULL,
  `is_sent` tinyint(1) NOT NULL DEFAULT '0',
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_reminders_event` (`event_id`),
  KEY `idx_reminders_due` (`remind_at`,`is_sent`),
  CONSTRAINT `fk_reminders_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Cấu trúc bảng `shares`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `shares`;
CREATE TABLE `shares` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `shared_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('view','edit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'view',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shares_user` (`user_id`),
  CONSTRAINT `fk_shares_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu thực tế cho bảng `shares`
INSERT INTO `shares` (`id`, `user_id`, `shared_email`, `role`, `created_at`) VALUES
('1', '1', 'hoang.nguyen@techcorp.vn', 'edit', '2026-11-21 08:10:36'),
('2', '1', 'phuong.designer@studio.com', 'view', '2026-11-21 08:10:36'),
('3', '1', 'lan.kehoach@doitac.vn', 'view', '2026-11-21 08:10:36');

-- --------------------------------------------------------
-- Cấu trúc bảng `tasks`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` enum('pending','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_completed` tinyint(1) NOT NULL DEFAULT '0',
  `progress` int unsigned NOT NULL DEFAULT '0',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_tasks_user` (`user_id`),
  CONSTRAINT `fk_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu thực tế cho bảng `tasks`
INSERT INTO `tasks` (`id`, `user_id`, `category_id`, `title`, `description`, `priority`, `status`, `is_completed`, `progress`, `due_date`, `created_at`, `updated_at`) VALUES
('14', '1', '7', 'Hoàn thiện báo cáo tài chính quý 4 gửi Ban Giám đốc', 'Tổng hợp chi tiết doanh thu, chi phí vận hành và dự phóng ngân sách quý 1 năm sau.', 'urgent', 'pending', '0', '70', '2026-11-19', '2026-11-15 08:30:00', '2026-11-21 08:10:36'),
('15', '1', '1', 'Kiểm tra và ký duyệt hợp đồng cung cấp dịch vụ phần mềm', 'Rà soát các điều khoản cam kết SLA, chính sách hỗ trợ kỹ thuật và tiến độ giải ngân.', 'high', 'pending', '0', '50', '2026-11-20', '2026-11-16 09:00:00', '2026-11-21 08:10:36'),
('16', '1', '3', 'Lập kế hoạch nội dung truyền thông cho chiến dịch Tết 2027', 'Phối hợp với nhóm Marketing lên ý tưởng kịch bản video và bộ nhận diện hình ảnh.', 'high', 'pending', '0', '30', '2026-11-25', '2026-11-18 10:15:00', '2026-11-21 08:10:36'),
('17', '1', '7', 'Thanh toán hóa đơn điện nước và cước viễn thông văn phòng', 'Trích xuất sao kê ngân hàng và thanh toán các hóa đơn dịch vụ định kỳ đúng hạn.', 'medium', 'pending', '0', '20', '2026-11-23', '2026-11-19 14:00:00', '2026-11-21 08:10:36'),
('18', '1', '3', 'Review thiết kế giao diện Mobile App phiên bản 2.5', 'Đánh giá trải nghiệm người dùng, bảng màu SaaS tím xanh và tính tương thích trên màn hình nhỏ.', 'high', 'pending', '0', '60', '2026-11-26', '2026-11-19 15:30:00', '2026-11-21 08:10:36'),
('19', '1', '5', 'Chuẩn bị tài liệu đào tạo nội bộ về quy trình bảo mật thông tin', 'Cập nhật tài liệu hướng dẫn tuân thủ an toàn thông tin theo chuẩn ISO 27001.', 'medium', 'pending', '0', '40', '2026-11-28', '2026-11-20 11:00:00', '2026-11-21 08:10:36'),
('20', '1', '6', 'Gia hạn thẻ tập gym và gói chăm sóc thể lực cá nhân', 'Đăng ký gói hội viên rèn luyện sức khỏe định kỳ cho quý 1 năm sau.', 'low', 'completed', '1', '100', '2026-11-18', '2026-11-10 08:00:00', '2026-11-21 08:10:36'),
('21', '1', '1', 'Cập nhật danh bạ khách hàng VIP và gửi thư cảm ơn', 'Gửi email tri ân tới các đối tác chiến lược nhân dịp hoàn thành các dự án lớn.', 'medium', 'completed', '1', '100', '2026-11-15', '2026-11-11 09:30:00', '2026-11-21 08:10:36'),
('22', '1', '5', 'Đọc và tóm tắt cuốn sách Atomic Habits - Thay đổi tí hon', 'Ghi chép các nguyên tắc xây dựng thói quen tốt và loại bỏ thói quen trì hoãn.', 'low', 'completed', '1', '100', '2026-11-12', '2026-11-05 10:00:00', '2026-11-21 08:10:36'),
('23', '1', '6', 'Đặt lịch khám sức khỏe tổng quát cuối năm tại bệnh viện', 'Chọn gói khám kiểm tra sức khỏe toàn diện và đặt lịch khám vào sáng thứ 7.', 'medium', 'pending', '0', '10', '2026-11-27', '2026-11-20 16:00:00', '2026-11-21 08:10:36');

-- --------------------------------------------------------
-- Cấu trúc bảng `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu thực tế cho bảng `users` (tài khoản mặc định: admin / admin)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `avatar`, `created_at`) VALUES
('1', 'Nguyễn Quang Tú', 'admin', '$2y$10$Vb9yK3ovIVB4CNuSd2WZt.7tCTdM32RfzlpWpojKoCML1x8JuiCua', NULL, '2026-11-09 20:17:32');

-- --------------------------------------------------------
-- Cấu trúc bảng `habits` & `habit_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `habit_logs`;
DROP TABLE IF EXISTS `habits`;

CREATE TABLE `habits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency` enum('daily','weekly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
  `target_per_week` tinyint unsigned NOT NULL DEFAULT '7',
  `color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#c2410c',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_habits_user` (`user_id`,`is_archived`),
  CONSTRAINT `fk_habits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `habit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `habit_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `log_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_habit_date` (`habit_id`,`log_date`),
  KEY `idx_habit_logs_user_date` (`user_id`,`log_date`),
  CONSTRAINT `fk_habit_logs_habit` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_habit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `habits` (`id`, `user_id`, `name`, `description`, `frequency`, `target_per_week`, `color`) VALUES
(1, 1, 'Tập thể dục', '30 phút vận động buổi sáng', 'daily', 7, '#15803d'),
(2, 1, 'Đọc sách', 'Ít nhất 20 phút trước khi ngủ', 'daily', 7, '#c2410c'),
(3, 1, 'Uống đủ 2L nước', 'Chia đều trong ngày', 'daily', 7, '#2563eb'),
(4, 1, 'Học ngoại ngữ', 'Ôn từ vựng và nghe 30 phút', 'weekly', 5, '#b45309');

SET FOREIGN_KEY_CHECKS = 1;
