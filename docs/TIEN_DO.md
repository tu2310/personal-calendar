# Tiến Độ Kiểm Thử Toàn Diện — Personal Calendar

## 1. Bản đồ hệ thống (Pha 0)
- **Stack**: PHP 8.x MVC thuần (PDO) + MySQL 8.0.30 (`localhost`, DB `personal_calendar` — **ĐÃ XÁC NHẬN LOCAL**) + Vanilla JS + CDN (Bootstrap 5.3, FontAwesome 6.5, FullCalendar 6.1, Chart.js 4.4, SweetAlert2). Thư mục phụ: `lich-todo-ui/` (prototype tĩnh).
- **Chạy/Kiểm**: Laragon Apache (`http://localhost/Lich_ca_nhan/`) hoặc `php -S 127.0.0.1:8080`. Lint: `php -l`. Test sẵn có: `tests/test_phase3_suite.php`.
- **Vai trò**: `Guest` (chưa đăng nhập), `User A` (chủ sở hữu), `User B` (người khác — test IDOR). Không có role Admin riêng.
- **Tác vụ nền / Upload / Tích hợp**: Không có cron/queue/upload/export server-side; nhắc nhở bằng JS polling `api-reminders-check` + Browser Notification API.

## 2. Ma trận phủ (Chức năng × Vai trò × Loại kiểm thử)
Ký hiệu: `[-]` Chưa chạy | `[PASS]` Đạt | `[FAIL]` Có lỗi | `[N/A]` Không kiểm được.
Loại kiểm: **1**=Happy path, **2**=Validate, **3**=State/Flow, **4**=Auth/IDOR, **5**=Concurrency, **6**=Security, **7**=Data integrity, **8**=UI/UX.

| Nhóm | Module & Route/API | Vai trò | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | Trạng thái |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Pha 1 | Static lint (`php -l`), `test_phase3_suite.php`, quét bảo mật tĩnh | N/A | PASS | - | - | - | - | FAIL | - | - | Có lỗi (DB tắt) |
| N1 | Auth: `login`, `login-post`, `register`, `register-post`, `logout` | Guest, A | - | - | - | - | - | - | - | - | Chưa chạy |
| N2 | Dashboard & Statistics: `dashboard`, `statistics`, chart tuần | Guest, A, B | - | - | - | - | - | - | - | - | Chưa chạy |
| N3 | Events & Calendar: `calendar/week/month`, `api-events`, `api-event-detail/save/time/delete`, `api-search` | Guest, A, B | - | - | - | - | - | - | - | - | Chưa chạy |
| N4 | Tasks: `tasks`, `api-tasks-list`, `api-task-detail/save/status/delete` | Guest, A, B | - | - | - | - | - | - | - | - | Chưa chạy |
| N5 | Categories: `categories`, `api-category-save`, `api-category-delete` | Guest, A, B | - | - | - | - | - | - | - | - | Chưa chạy |
| N6 | Notifications: `api-notifications`, `read`, `read-all`, `delete`, `delete-all`, `reminders-check`, `test` | Guest, A, B | - | - | - | - | - | - | - | - | Chưa chạy |
| N7 | Settings, Share, Pomodoro: `settings`, `api-settings-save`, `share`, `pomodoro` | Guest, A, B | - | - | - | - | - | - | - | - | Chưa chạy |
| Cuối A | Polling `api-reminders-check` lặp/chồng, tích hợp Browser Notification & CDN | A | - | - | - | - | - | - | - | - | Chưa chạy |
| Cuối B | Luồng chéo End-to-End (User A + B xuyên module, đối chiếu số liệu) | A, B | - | - | - | - | - | - | - | - | Chưa chạy |
| Cuối C | Hiệu năng & độ bền (tải lớn `[TEST]`, N+1, index, mất DB) | A | - | - | - | - | - | - | - | - | Chưa chạy |

## 3. Nhật ký tiến độ
- **2026-11-21**: Hoàn thành Pha 0 (lập bản đồ & ma trận phủ).
- **2026-11-21**: Hoàn thành Pha 1 — `php -l` sạch (0 lỗi); `test_phase3_suite.php` chưa chạy được do MySQL Laragon đang tắt; phát hiện 5 lỗi tĩnh (2 Cao, 2 TB, 1 Thấp). Kế tiếp: **Nhóm 1 (Auth)** (cần bật MySQL Laragon trước).
