# SmashClub — Quản lý sân cầu lông

Ứng dụng tiếng Việt dùng **Node.js, Express, JavaScript và MySQL**. Giao diện khách hàng và khu vận hành dùng chung một máy chủ ở cổng `3000`. Laragon cung cấp dịch vụ MySQL; ứng dụng được chạy bằng Node.js.

## Chạy trên Windows với Laragon

Cần **Node.js 22 trở lên**, npm và **MySQL 8.0 trở lên**. Bật MySQL trong Laragon trước khi chạy setup. Tài khoản MySQL dùng cho setup phải có quyền tạo database và bảng.

Mở PowerShell tại thư mục dự án:

```powershell
Set-Location 'C:\laragon\www\quan ly san cau long'
npm install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
```

Mở `.env`, kiểm tra thông tin MySQL. Mặc định phù hợp với Laragon dùng tài khoản `root` chưa đặt mật khẩu:

```dotenv
NODE_ENV=development
HOST=127.0.0.1
PORT=3000
APP_ORIGIN=http://127.0.0.1:3000
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quan_ly_san_cau_long
DB_USERNAME=root
DB_PASSWORD=
SESSION_SECRET=replace-with-a-random-secret-at-least-32-characters
TRUST_PROXY=false
```

Tạo chuỗi bí mật, sau đó sao chép kết quả vào `SESSION_SECRET` trong `.env`:

```powershell
node -e "console.log(require('node:crypto').randomBytes(48).toString('hex'))"
```

Khởi tạo database và chạy ứng dụng:

```powershell
npm run setup
npm start
```

Giữ cửa sổ Node đang chạy và mở **http://127.0.0.1:3000**. `http://localhost:3000` cũng dùng được; nên giữ cùng một hostname trong suốt phiên đăng nhập. Không cần bật Apache hoặc mở đường dẫn thư mục qua máy chủ web Laragon.

Nếu dùng Git Bash, chuyển thư mục bằng `cd '/c/laragon/www/quan ly san cau long'`; các lệnh `npm install`, `npm run setup`, `npm start` giống PowerShell. Sao chép `.env.example` thành `.env` chỉ khi chưa có `.env`.

| Trang | URL mặc định |
| --- | --- |
| Trang chủ | http://127.0.0.1:3000/ |
| Đặt sân | http://127.0.0.1:3000/?page=book |
| Đăng nhập | http://127.0.0.1:3000/?page=login |
| Đăng ký | http://127.0.0.1:3000/?page=register |
| Lịch của tôi | http://127.0.0.1:3000/?page=history |
| Khu quản lý | http://127.0.0.1:3000/?page=admin |

Muốn tự tải lại máy chủ khi sửa mã, dùng `npm run dev`. Dừng máy chủ bằng `Ctrl+C`.

## Tài khoản demo

Các tài khoản sau được tạo **chỉ khi database chưa có người dùng**. Mật khẩu lưu trong MySQL là bcrypt hash.

| Vai trò | Email | Mật khẩu demo |
| --- | --- | --- |
| Quản trị viên | `admin@smashclub.vn` | `Admin@12345` |
| Quản lý | `manager@smashclub.vn` | `Manager@12345` |
| Nhân viên | `staff@smashclub.vn` | `Staff@12345` |
| Khách hàng | `customer@smashclub.vn` | `Customer@12345` |

Đây là thông tin đăng nhập công khai dành cho bản demo. **Đổi toàn bộ mật khẩu demo trước khi sử dụng thực tế**, cấu hình bí mật phiên riêng và chỉ cấp các quyền cần thiết. Các khách hàng minh họa khác chỉ có hồ sơ khách hàng, không có tài khoản đăng nhập.

## Setup và dữ liệu mẫu

`npm run setup` đọc `.env`, tạo database nếu chưa tồn tại với `utf8mb4`/`utf8mb4_unicode_ci`, rồi chạy `database/schema.sql`. Tên database chỉ nhận chữ cái ASCII, chữ số và dấu gạch dưới, dài tối đa 64 ký tự.

Script dùng khóa MySQL để tránh hai lần setup chạy đồng thời và transaction cho toàn bộ dữ liệu demo. Khi một bước seed lỗi, các bản ghi của lần seed đó được rollback; các bảng vừa tạo có thể vẫn còn để chạy lại. Setup không có lệnh xóa database, xóa bảng hoặc reset dữ liệu.

- Nếu `users` đã có ít nhất một tài khoản, setup **bỏ qua toàn bộ seed** và giữ nguyên tài khoản, mật khẩu, cấu hình, lịch sân và giao dịch. Chạy lại không làm mới ngày của dữ liệu demo.
- Nếu `users` rỗng nhưng bảng nghiệp vụ đã có dữ liệu, setup dừng để tránh trộn dữ liệu. Script cũng giữ nguyên các cấu hình đã có; nếu chính sách giá trị số khác chính sách demo, hãy dùng database mới để thử demo.
- `database/seed.sql` chỉ chứa các cấu hình mặc định với `ON DUPLICATE KEY UPDATE` giữ nguyên giá trị đã có. `scripts/setup.js` tạo người dùng, bcrypt hash, các ngày tương đối và quan hệ nghiệp vụ. Không cần nhập `seed.sql` thủ công.
- `CREATE TABLE IF NOT EXISTS` không thay thế hệ thống migration: setup không tự sửa cột của một schema cũ không tương thích.
- Muốn có một bản demo mới ở thời điểm khác, chọn **tên `DB_DATABASE` mới**, chạy setup và khởi động lại Node. Database cũ được giữ nguyên.

Ngày, giờ và phiên MySQL sử dụng **Asia/Ho_Chi_Minh (UTC+07:00)**. Dữ liệu mẫu gồm:

- Một cơ sở SmashClub Quận 7, TP. Hồ Chí Minh; địa chỉ, điện thoại và tên khách hàng là thông tin minh họa.
- Sáu sân trong nhà: bốn sân tiêu chuẩn, hai sân Premium; mở cửa **05:00–23:00 tất cả các ngày**, mỗi ô lịch 30 phút.
- Giá cơ bản **100.000đ/giờ** cho sân tiêu chuẩn, **140.000đ/giờ** cho Premium. Quy tắc toàn bộ sân: cao điểm **17:00–22:00, 160.000đ/giờ, ưu tiên 10**; thứ Bảy/Chủ nhật **130.000đ/giờ, ưu tiên 5**. Quy tắc ưu tiên cao hơn thay thế giá cơ bản trong khoảng áp dụng, nên cuối tuần ngoài giờ cao điểm có giá 130.000đ cho cả hai loại sân.
- Mã `WELCOME10` giảm 10%, `SMASH50` giảm 50.000đ và `EARLYBIRD` giảm 15%, kèm mức tối thiểu, trần giảm và giới hạn lượt dùng. Thời hạn tính từ ba tháng trước đến ba tháng sau ngày seed. `EARLYBIRD` là tên chiến dịch, không có điều kiện tự giới hạn giờ sáng.
- Bốn tài khoản theo vai trò, 11 hồ sơ khách hàng, sáu mặt hàng: nước suối, nước điện giải, cầu lông, thuê vợt, quấn cán và thuê giày.
- 42 ngày lịch sử, mỗi ngày ba lượt hoàn tất; lịch hôm nay phù hợp với giờ chạy setup; lịch đã xác nhận trong bảy ngày tiếp theo. Ngoài giờ mở cửa không tạo lượt “đang chơi”; gần giờ đóng cửa có thể không còn lượt sắp tới trong ngày.
- Hóa đơn, dòng tiền sân/sản phẩm, thanh toán tiền mặt/chuyển khoản, biến động kho, chi phí, danh sách chờ và thông báo. Một ca nhân viên mới mở có tiền đầu ca bằng 0; các giao dịch lịch sử không thuộc ca mới này.

Dòng hóa đơn `Tiền sân …` luôn giữ số tiền trước giảm giá; ưu đãi nằm ở `invoices.discount` để không trừ hai lần. Điểm demo bằng tổng `floor(tiền sân sau giảm giá / 10.000)` của các lượt đã hoàn tất. Các lịch đã xác nhận, đang chơi và hoàn tất có đầy đủ ô lịch 30 phút; khóa duy nhất theo sân/ngày/giờ ngăn trùng ô. Seed kiểm tra tổng dòng hóa đơn, tổng thanh toán và số ô lịch trước khi commit.

## Thử các luồng chính

**Khách hàng:** đăng nhập tài khoản khách, mở Đặt sân, chọn ngày/sân/giờ/thời lượng, xem báo giá và áp dụng voucher nếu đủ điều kiện. Có thể đặt lặp theo tuần, tối đa 12 tuần trong phạm vi 180 ngày. Khi đặt online, hệ thống giữ chỗ 15 phút và yêu cầu cọc 30% theo cấu hình. Khách xem mã đặt/QR vé và trạng thái tại Lịch của tôi, đổi hoặc hủy lịch theo chính sách được lưu tại thời điểm đặt. Khi sân đã kín, có thể đăng ký danh sách chờ.

**Nhân viên:** vào khu quản lý, xem lịch ngày, tạo lịch tại quầy cho khách, ghi nhận thanh toán, nhận sân, bán nước/cầu, cho thuê dụng cụ, nhận trả dụng cụ và hoàn tất lượt chơi. Cần thanh toán đủ hóa đơn và trả hết đồ thuê trước khi hoàn tất. Tạo/mở ca và đối chiếu tiền mặt khi đóng ca; ca demo đã được mở sẵn cho tài khoản nhân viên.

**Quản lý:** quản lý cơ sở, sân, giờ hoạt động, thời gian bảo trì, bảng giá, voucher, khách hàng, tồn kho và chi phí. Xử lý hoàn tiền theo quyền, đối soát giao dịch chuyển khoản được nhập thủ công, xem báo cáo và xuất CSV. Việc hạn chế khách, thay đổi điểm/hạng và sửa thông tin quản trị được kiểm tra theo vai trò ở máy chủ.

**Quản trị viên:** có quyền quản lý tài khoản nhân viên, cập nhật thông tin thương hiệu/chính sách/ngân hàng, xem nhật ký và xuất bản sao lưu SQL. Bản sao lưu chứa thông tin riêng tư và hash mật khẩu; lưu tại nơi phù hợp. Khôi phục thủ công bằng MySQL vào một database trống, rồi chỉnh `.env` tới database đó. Không có thao tác tự phục hồi hoặc tự ghi đè database đang chạy.

Báo cáo tách doanh số hóa đơn theo ngày tạo hóa đơn, tiền thực thu, hoàn tiền, chi phí và công nợ. Vì có đặt trước, tiền cọc và thu bổ sung, những chỉ số này có thể khác nhau hợp lý. Báo cáo sử dụng sân được tính theo thời gian hoạt động thực tế, có xét lịch đóng/bảo trì.

## Chuyển khoản, VietQR và thông báo

Bản demo cố ý để trống `bank_name`, `bank_bin`, `bank_account`, `bank_owner`. Để tạo QR chuyển khoản, quản trị viên nhập ngân hàng, BIN ngân hàng sáu chữ số, số tài khoản và tên chủ tài khoản trong Cài đặt. Mã VietQR được tạo tại máy chủ từ cấu hình và số tiền của hóa đơn; khi chưa cấu hình, khách được hướng dẫn thanh toán tại quầy.

**Nhân viên phải kiểm tra tiền thực nhận rồi ghi nhận thanh toán hoặc đối soát giao dịch thủ công.** Việc quét mã hay khách báo đã chuyển tiền không tự xác nhận thanh toán. Hệ thống không kết nối cổng thanh toán, không tự tải sao kê và không có webhook ngân hàng. Các giao dịch demo mang tham chiếu `DEMO-…`, không thực hiện chuyển tiền.

Thông báo và thông báo danh sách chờ nằm trong ứng dụng. Không gửi email, SMS, Zalo hoặc push; các thông tin liên hệ demo không được dùng để gửi tin. QR vé chứa mã đặt sân để đối chiếu; không tích hợp máy quét/cổng kiểm soát ra vào. Ứng dụng có thể chạy trong mạng nội bộ sau khi cài dependencies; không cần dịch vụ QR bên ngoài.

## Kiểm tra

```powershell
npm run check
npm test
```

- `npm run check` chạy `node --check` đệ quy cho JavaScript trong `server`, `scripts`, `tests`, `public`, `database/migrations`, bỏ qua dependencies và thư mục công cụ. Lệnh chỉ in lỗi và cũng từ chối tệp PHP; thành công trả exit code 0.
- `npm test` chạy Node test với MySQL local. Harness kiểm tra cả `.env` và biến môi trường, chỉ chấp nhận `localhost` hoặc `127.0.0.1`; không chọn database ứng dụng làm đích.
- Migration mới `database/migrations/20261115_001_test_workspaces.js` tạo có điều kiện hai DB riêng `smashclub_test_http` và `smashclub_test_booking`. Mỗi DB có dấu sở hữu workspace và khóa độc quyền. Không sử dụng DROP/TRUNCATE; cuối lượt test dọn fixture `[TEST]` theo khóa ngoại bằng DELETE trong transaction, giữ nguyên schema.
- Nếu DB thử không có dấu sở hữu, còn dữ liệu cũ, hoặc một lượt khác đang chạy, harness dừng và giữ nguyên dữ liệu để kiểm tra. Không tự dọn lượt test bị gián đoạn.
- Bộ test trực tiếp đặt sân được bật bằng `BOOKING_DB_TEST=1`. Bật biến này khi chạy toàn bộ bộ test để không bỏ qua nhóm MySQL. Mật khẩu fixture được sinh ngẫu nhiên trong bộ nhớ.

PowerShell, chạy thêm kiểm thử đặt sân:

```powershell
$env:BOOKING_DB_TEST = '1'
node --env-file=.env --test tests/booking.test.js
Remove-Item Env:BOOKING_DB_TEST
```

Git Bash:

```bash
BOOKING_DB_TEST=1 node --env-file=.env --test tests/booking.test.js
```

## Cấu trúc và cấu hình vận hành

| Đường dẫn | Nội dung |
| --- | --- |
| `server/index.js` | Máy chủ Express, API, session và tác vụ hết hạn giữ chỗ |
| `server/core/` | Cấu hình, MySQL, xác thực, validation, thời gian, nhật ký |
| `server/controllers/` | Các API cho khách hàng và khu quản lý |
| `server/services/` | Đặt sân, giá, thanh toán, kho, báo cáo, QR, sao lưu |
| `public/` | HTML, CSS, JavaScript và tài nguyên giao diện |
| `database/schema.sql` | Cấu trúc bảng và khóa ngoại |
| `database/seed.sql` | Cấu hình demo mặc định không ghi đè |
| `scripts/setup.js` | Tạo schema và dữ liệu demo theo ngày hiện tại |
| `scripts/check.js` | Kiểm tra cú pháp JavaScript và tệp PHP còn sót |
| `tests/` | Kiểm thử nghiệp vụ và tích hợp |

Chính sách mặc định: cọc 30%, hủy trước 24 giờ, ô lịch 30 phút, giữ chỗ 15 phút, mỗi 10.000đ tiền sân sau giảm giá được một điểm khi hoàn tất. Lịch đặt lưu snapshot giá và chính sách. Hoàn tiền cần quản lý; hủy sát giờ/vắng mặt áp dụng khoản phí tối đa bằng tiền cọc theo snapshot.

Ứng dụng dùng session lưu MySQL, CSRF token, bcrypt, giới hạn yêu cầu và kiểm tra quyền phía máy chủ. API runtime không bật nhiều câu SQL trong một truy vấn; chỉ kết nối CLI setup bật tùy chọn này để nhập schema.

Khi vận hành thực tế, đặt mật khẩu MySQL và dùng tài khoản ứng dụng riêng, đổi thông tin cơ sở/demo, đặt `SESSION_SECRET` ngẫu nhiên ổn định, sao lưu dữ liệu và cấu hình HTTPS. Với reverse proxy tin cậy, đặt `NODE_ENV=production`, `APP_ORIGIN` thành URL HTTPS thực tế và `TRUST_PROXY=true` theo cấu hình proxy. Cookie production yêu cầu HTTPS; không bật production cho phiên thử HTTP cục bộ. Khi thay đổi `.env`, khởi động lại Node.

## Xử lý lỗi thường gặp

| Hiện tượng | Cách kiểm tra |
| --- | --- |
| `ECONNREFUSED` / không kết nối MySQL | Bật MySQL trong Laragon, kiểm tra `DB_HOST`, `DB_PORT` và dịch vụ đang nghe đúng cổng. |
| `Access denied` | Kiểm tra `DB_USERNAME`, `DB_PASSWORD` và quyền tạo database/bảng của tài khoản chạy setup. |
| Chưa có bảng `users` | Chạy `npm run setup` từ thư mục dự án, rồi chạy lại máy chủ. |
| Setup báo đã có tài khoản | Đây là hành vi giữ nguyên dữ liệu. Dùng tài khoản hiện có; mật khẩu demo chỉ đúng với bản seed chưa được đổi mật khẩu. |
| Setup phát hiện dữ liệu cũ/schema không khớp | Giữ nguyên database cũ. Dùng tên database mới cho demo hoặc thực hiện migration phù hợp trước khi chạy. |
| Cổng `3000` đã được sử dụng | Dừng phiên Node cũ hoặc đổi cả `PORT` và `APP_ORIGIN` trong `.env`, rồi mở URL ở cổng mới. |
| Không đăng nhập được khi bật production trên HTTP | Thử cục bộ với `NODE_ENV=development`, hoặc cấu hình HTTPS đúng cho production. |
| Mã QR thanh toán chưa hiện | Điền đủ cấu hình ngân hàng bằng tài khoản admin; hóa đơn phải còn số tiền cần thu và thuộc trạng thái được phép thanh toán. |
| Lịch giữ chỗ hết hạn | Chọn lại khung giờ và tạo lượt đặt mới; không tự coi giao dịch ngoài hệ thống là đã thanh toán. |
| Lịch demo không còn gần hôm nay | Seed chỉ tạo một lần. Dùng database mới nếu cần dữ liệu demo theo ngày mới, hoặc tạo lịch bằng giao diện. |
