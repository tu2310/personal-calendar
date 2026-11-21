# Tiến Độ Dự Án: Giao Diện Lịch Cá Nhân + To-do List (UI/UX)

## 1. Tổng quan
- Ứng dụng: Lịch cá nhân kết hợp To-do list (thuần giao diện client-side, in-memory mock data).
- Công nghệ: HTML5, CSS3 (tokens, mobile-first, dark/light theme), JavaScript ES Modules.
- Thư mục: `lich-todo-ui/`.

## 2. Danh sách 13 màn hình hoàn thành
1. **Hôm nay (`#/hom-nay`)**: Lời chào cá nhân hóa, ngày tháng định dạng tiếng Việt, thanh tiến độ công việc trong ngày, timeline dòng thời gian kết hợp sự kiện và việc có giờ, khối công việc quá hạn cảnh báo đỏ, danh sách việc không gắn giờ, nút FAB (+) thêm nhanh.
2. **Lịch (`#/lich`)**: Chuyển đổi 4 chế độ xem Tháng / Tuần / Ngày / Danh sách; hiển thị chấm màu trạng thái theo danh mục công việc; chạm ngày mở danh sách việc/sự kiện chi tiết; lưới giờ 24h với đường kẻ thời gian thực; kéo thả đổi giờ; nút quay lại Hôm nay; lọc theo danh mục.
3. **Danh sách việc (`#/viec`)**: Phân nhóm trực quan (Quá hạn · Hôm nay · Sắp tới · Không hạn · Đã hoàn thành dạng thu gọn); thanh tìm kiếm nhanh, lọc theo danh mục, sắp xếp theo ngày/ưu tiên/tên; thao tác tick hoàn thành tức thì, dời ngày mai, sửa, xóa với Toast Hoàn tác (Undo).
4. **Thêm/sửa việc**: Modal trên Desktop, Bottom sheet trên Mobile; tiêu đề bắt buộc có kiểm tra hợp lệ; chọn hạn nhanh (Hôm nay / Ngày mai / Tuần sau); giờ, danh mục, độ ưu tiên; trường phụ thu gọn (ghi chú, việc con).
5. **Chi tiết việc**: Hiển thị đầy đủ thông tin, danh sách việc con tương tác tick chọn trực tiếp, ghi chú, lịch sử thay đổi mẫu, nhân bản việc, xóa việc.
6. **Thêm/sửa sự kiện**: Đặt tên, thời gian bắt đầu - kết thúc, tùy chọn cả ngày, địa điểm, danh mục, màu sắc, cảnh báo trực tiếp khi trùng khung giờ với sự kiện khác.
7. **Danh mục & nhãn (`#/danh-muc`)**: Danh sách danh mục màu sắc, đếm số việc chưa xong trong từng danh mục, thêm mới, sửa tên và xóa danh mục.
8. **Thông báo (`#/thong-bao`)**: Trung tâm thông báo nhắc việc, việc quá hạn, cảnh báo trùng lịch; các nút hoãn nhanh (+10 phút, +1 giờ, sang ngày mai) và bỏ qua.
9. **Thống kê (`#/thong-ke`)**: Thẻ chỉ số tổng quan (số việc hoàn thành, tỷ lệ đúng hạn, chuỗi streak liên tiếp, giờ tập trung); biểu đồ cột hoàn thành các ngày trong tuần vẽ bằng SVG nội bộ; biểu đồ phân bổ công việc theo danh mục.
10. **Tìm kiếm toàn cục (`#/tim-kiem`)**: Tìm kiếm tức thì theo từ khóa trên cả danh sách việc và sự kiện; bộ lọc nhanh Tất cả / Công việc / Sự kiện.
11. **Cài đặt (`#/cai-dat`)**: Hồ sơ demo; chuyển đổi giao diện Sáng / Tối / Tự động; tùy chọn ngày đầu tuần, định dạng ngày/giờ; xuất file JSON dự phòng; bộ chuyển 4 trạng thái demo (Bình thường / Loading / Rỗng / Lỗi).
12. **Đăng nhập / Đăng ký / Quên mật khẩu (`#/auth`)**: Form mẫu 3 tab; kiểm tra hợp lệ phía client; đăng nhập chuyển thẳng vào ứng dụng với dữ liệu mẫu.
13. **Thùng rác (`#/thung-rac`)**: Quản lý danh sách việc đã xóa, khôi phục về danh sách chính, nút dọn sạch thùng rác.

## 3. Đáp ứng yêu cầu kỹ thuật & Truy cập (Accessibility)
- **4 trạng thái bắt buộc**: Đầy đủ Skeleton Loading shimmer, Empty state với minh họa SVG + nút hành động, Error state kèm nút thử lại, và Success toast.
- **Không backend, không DB, không thư viện ngoài, không CDN, không localStorage**: Chạy 100% in-memory, tải lại trang về trạng thái dữ liệu mẫu chuẩn ban đầu.
- **Hệ thống Design Token**: Biến CSS chuẩn (`tokens.css`), độ tương phản WCAG AA, kích thước ô chạm tối thiểu 44px, hỗ trợ prefers-color-scheme và prefers-reduced-motion.
- **Responsive**: Kiểm tra tối ưu không vỡ layout tại 360px (mobile), 768px (tablet), 1280px (desktop); không xuất hiện thanh cuộn ngang trang.
