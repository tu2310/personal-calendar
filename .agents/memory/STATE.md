# STATE — Personal Calendar

## Việc vừa hoàn tất (2026-11-22)
- Triển khai trọn bộ kế hoạch UI/UX `docs/GHI_CHU_SUA_UI_LICH_CA_NHAN.md` (mobile-first 360px, Unslop):
  - Màn mặc định `Hôm nay` (`dashboard.php` / alias `today`), rút gọn sidebar còn 4 mục (`Hôm nay`, `Lịch`, `Tasks`, `Sắp tới`), gom bộ chọn `Ngày | Tuần | Tháng` trên `Lịch`, thêm trang `Sắp tới` (`upcoming.php`), thiết kế lại form chọn 1 `Ngày thực hiện` + `Khung giờ` + `Lặp lại` + thu gọn `+ Kéo dài nhiều ngày / Hạn chót` trên `#taskModal` và `#eventModal`, hiển thị `allDay: true` cho việc kéo dài nhiều ngày.
  - Bổ sung hiệu ứng vật lý 4 nhịp gỡ trái → gỡ phải → rơi xuống đáy → biến mất & thu gọn mượt khi tích hoàn thành trên `Hôm nay` (`#todayTimelineList`), đồng thời lọc bỏ việc đã xong khi F5 nhưng vẫn giữ chuẩn bộ đếm `Hoàn thành X/Y việc hôm nay` và tự hiện trạng thái `#todayAllDoneState`.
  - Vá sạch 3 điểm QA Veto (không gắn sai `Quá hạn` cho việc nhiều ngày đang diễn ra, đồng bộ `getStats` theo `from_today` + sort mặc định `due_asc` trên `Tasks`, xóa trùng `escapeHtml`).
- Kiểm định chốt đạt **`[QA PASS]`**, **`SECURITY: PASS`**, **97/97 ca kiểm thử PASS** (`npm run lint` 39 file, `node --check`, `test_phase3_suite.php` 30/30, `notification_flows_test.php` 28/28, `live_http_e2e_flows.php` 24/24, integration test DB thật 15/15).

## Việc tồn đọng / Bước tiếp theo
- Chờ người dùng bấm **Ctrl + F5** trên `http://127.0.0.1:8080` để trải nghiệm trực tiếp màn `Hôm nay` (hiệu ứng tích gỡ rơi), `Tasks`, `Sắp tới`, `Lịch`.
- Chưa `git commit` / `git push` (chỉ thực hiện khi người dùng yêu cầu).
