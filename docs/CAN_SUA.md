# Danh Sách Cần Sửa (Ghi Chú Rà Soát UI/UX)

> Cập nhật: 2026-11-22  
> Trạng thái: Mới ghi nhận yêu cầu, chưa chỉnh sửa mã nguồn.

## 1. Phần Lịch (`Lịch`)
- **Bỏ các ngày quá khứ**: Lịch phải bắt đầu hiển thị từ **Hôm nay** trở đi, không hiển thị dày đặc các ngày trước hôm nay.
- **Thiết kế lại cho dễ nhìn**: Giao diện hiện tại quá nhiều thông tin/chật chội nên khó phân biệt từng mục → cần tách bạch, gọn gàng và dễ đọc hơn.

## 2. Phần `Tasks`
- Không bỏ hẳn, nhưng **cần thay đổi lại bố cục / cách tổ chức** cho rõ ràng và hợp lý hơn (tự đính chính từ ý định bỏ ban đầu).

## 3. Phần `Sắp tới` (Deadline sắp tới)
- Có thể làm theo hướng lịch nối tiếp bắt đầu từ **Hôm nay**: nếu hôm nay có việc thì hiện ở đầu, rồi nối tiếp các deadline/ngày sắp tới.

## 4. Điểm cần chốt thêm (2 phần bị trùng ý tưởng)
- Hiện đang thấy **bí ý tưởng / trùng vai trò** ở 2 mục trên giao diện (chưa rõ để làm gì khi đã có `Hôm nay` và `Lịch`) → cần chốt phương án gộp gọn hoặc thay bằng chức năng hữu ích hơn.

## 5. Phần `Cài đặt`
- **Thông tin cá nhân / cập nhật tên**: **Chốt giữ nguyên**, đã ổn, không cần sửa thêm.
- **Mục Cấp quyền (Permissions / quyền trình duyệt)**: **Bỏ đi**, không cần hiển thị mục này trong Cài đặt.

## 6. Phần Thông báo
- Thông báo đang **không tập trung ở đúng chỗ** (Trung tâm thông báo) mà lại **hiện lung tung ở nhiều vị trí khác nhau** → cần gom lại cho nhất quán, hiển thị đúng nơi cần báo và tránh báo tràn lan gây rối.

## 7. Form Thêm việc (Modal tạo/sửa)
- **Khung giờ**: Tối ưu lại để **nhập nhanh là xong ngay** (dạng 24h hoặc chọn gọn), tránh bắt chọn `AM/PM` gây rườm rà, mất thao tác.
- **Ngày thực hiện**: **Chốt giữ nguyên**, cách hiển thị hiện tại đã ổn.
- **Mục "Kéo dài nhiều ngày"**: Nhãn/cách hoạt động hiện tại chưa trực quan (người dùng chưa hiểu rõ dùng để làm gì) → cần đặt lại tên hoặc mô tả ngắn cho dễ hiểu.
- **Mức độ ưu tiên**: **Bỏ khỏi form chính** (nếu cần thì dời sang chỗ khác gọn hơn thay vì để trực tiếp ở form thêm việc).
