# MEMORY — Personal Calendar (`Lich_ca_nhan`)

## Kiến trúc & Quy ước
- **Stack**: PHP 8.3 thuần MVC (`index.php` router, `app/Core`, `app/Controllers`, `app/Models`, `app/Views`) + MySQL (`personal_calendar`, utf8mb4) + Vanilla JS (`public/assets/js/app.js`, `toast.js`) + FullCalendar.
- **Lưu ý thư mục**: `lich-todo-ui/` là bản prototype UI tĩnh độc lập (mock data cổng 8081), KHÔNG sửa nhầm khi làm ứng dụng chính chạy ở `http://127.0.0.1:8080`.
- **File lớn**: `public/assets/js/app.js` > 2.800 dòng — bắt buộc chia ≥ 3 khoảng dòng khi phân tích, chỉ đọc đúng khoảng dòng cần sửa.
- **Hệ thống thông báo**:
  - Bảng `notifications` (`type` allowlist: `event`, `task`, `system`; `reference_type VARCHAR(50)`).
  - Bảng `reminders` có `UNIQUE KEY uniq_reminders_user_event (user_id, event_id)` chống bắn đúp khi nhiều tab poll `api-reminders-check`.
  - Đồng bộ nhắc hẹn qua `NotificationController::syncUserReminders($userId)` (dùng `Notification::createUniqueToday` chống trùng theo ngày cho `event_reminder`, `event_today`, `task_overdue`, `task_due_today`, `task_urgent`, `habit_checkin`).
  - Mọi API ghi CRUD trả kèm `unread_count: int`; Frontend đồng bộ qua `window.refreshNotifications(data.unread_count)`.
- **Bảo mật**:
  - `scripts/bootstrap-db.php` chỉ cho phép chạy CLI (`PHP_SAPI === 'cli'`), trả `403` qua HTTP.
  - Mọi endpoint ghi bắt buộc `POST` + CSRF (`csrf_token` hoặc `X-CSRF-TOKEN`).
  - `category_id` phải xác thực thuộc `user_id` hiện tại; mọi `LEFT JOIN categories` phải kèm `AND c.user_id = e.user_id` (`t.user_id`).
  - `Notification::sanitizeActionUrl` chỉ cho phép đường dẫn nội bộ bắt đầu bằng `index.php?`.

## Bài học rút ra
- Không bắn Toast cảnh báo tách rời qua `sessionStorage` mà không ghi DB `notifications` vì sẽ làm lệch số badge và rỗng Trung tâm thông báo.
- `Notification::hasNotificationFor` nếu không lọc theo ngày (`CURDATE()`) và `reference_type` chi tiết sẽ chặn vĩnh viễn cảnh báo quá hạn của các ngày tiếp theo.
- Khi chuyển trang từ mục thông báo (`window.location.href`), `fetch` đánh dấu đã đọc bắt buộc có `keepalive: true` để trình duyệt không hủy request.

## Lệnh chuẩn
- Kiểm tra cú pháp PHP: `php -l <file.php>`
- Kiểm tra cú pháp JS: `node --check public/assets/js/app.js`
- Khởi tạo/migrate DB an toàn: `php scripts/bootstrap-db.php`
- Chạy test tích hợp luồng thông báo: `php tests/notification_flows_test.php`
- Chạy test E2E HTTP live (`127.0.0.1:8080`): `php tests/live_http_e2e_flows.php`
- Chạy test hồi quy Phase 3: `php tests/test_phase3_suite.php`
