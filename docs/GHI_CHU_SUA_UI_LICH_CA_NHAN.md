# Ghi Chú Chỉnh Sửa UI/UX — Dự Án Lịch Cá Nhân (`Lich_ca_nhan`)

> **Trạng thái:** Ghi nhận từ phiên trao đổi & rà soát trực tiếp ngày `2026-11-22` (chỉ lưu kế hoạch trong dự án `Lich_ca_nhan`, chưa can thiệp code cho tới khi chốt sửa; hoàn toàn tách biệt với dự án `Quan-tri-hanh-chinh-nhan-su`).

---

## 1. Thay màn hình `Tổng quan` bằng trang `Hôm nay` (Mobile-First)
- **Hiện trạng:** Mở web lên vào trang *Tổng quan* nhiều khối thông tin, không giải quyết ngay nhu cầu xem gấp trên điện thoại.
- **Hướng chỉnh sửa:**
  - Đưa màn **`Hôm nay`** lên làm trang mặc định đầu tiên ngay khi mở ứng dụng.
  - Ưu tiên hiển thị **Task cần làm hôm nay + lúc mấy giờ** (tự động bám theo mốc giờ hiện tại).
  - Thiết kế tối giản, gọn nhẹ cho màn hình điện thoại: mỗi Task có **nút dấu tích hình tròn ở bên trái** để chạm 1 lần là hoàn thành ngay tại chỗ.
  - Chỉ giữ một chỉ số tiến độ siêu gọn ở đầu trang (ví dụ: `Hoàn thành 3/5 việc hôm nay`) thay cho các biểu đồ nặng nề.

## 2. Đổi `Danh sách lịch` thành `Tasks` (Tính từ Ngày Hôm Nay)
- **Hiện trạng:** Nhãn *Danh sách lịch* chưa thể hiện đúng bản chất các đầu việc cần xử lý.
- **Hướng chỉnh sửa:**
  - Đổi tên mục *Danh sách lịch* thành **`Tasks`**.
  - Dữ liệu trong `Tasks` **bắt đầu tính từ ngày hôm nay (ngày hiện tại)** trở đi, đồng thời gom các việc quá hạn chưa tích xong lên đầu để không bị trôi mất.

## 3. Bỏ `Thói quen` & `Thống kê` → Đổi thành `Sắp tới`
- **Hiện trạng:** Thanh điều hướng đang dàn trải gồm *Lịch ngày, Lịch tuần, Lịch tháng, Danh sách lịch, Thói quen, Thống kê* — trong đó *Thói quen* và *Thống kê* không thực sự cần thiết cho nhu cầu lịch cá nhân.
- **Hướng chỉnh sửa:**
  - Lược bỏ hai mục **`Thói quen`** và **`Thống kê`** (việc lặp lại hằng ngày/hằng tuần chỉ cần là tùy chọn *Lặp lại* ngay trong thẻ Task).
  - Thay thế bằng mục **`Sắp tới`** để xem nhanh lộ trình các đầu việc của những ngày kế tiếp.
  - Gom gọn bộ chuyển chế độ xem **`Ngày | Tuần | Tháng`** trên thanh công cụ thay vì tách rời nhiều dòng menu.

## 4. Thiết kế trường Ngày & Giờ khi tạo Task mới
- **Mặc định (90% việc làm trong ngày):**
  - Chỉ hiển thị **1 ô `Ngày thực hiện`** (mặc định chọn sẵn *Hôm nay*) + **`Khung giờ`** (*Giờ bắt đầu – Giờ kết thúc*, hoặc để *Chưa gắn giờ*), không bắt chọn *Ngày kết thúc* gây thừa thao tác.
- **Tùy chọn mở rộng (việc kéo dài nhiều ngày / có Deadline):**
  - Ẩn ô *Ngày kết thúc* mặc định; chỉ mở thêm khi người dùng bấm nút phụ **`+ Kéo dài nhiều ngày / Hạn chót`**.
  - Các việc kéo dài nhiều ngày hiển thị dạng thanh ghim gọn ở đầu ngày thay vì lấp kín toàn bộ khung giờ lẻ bên dưới.
