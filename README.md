# InternTrack

InternTrack là ứng dụng quản lý kỳ thực tập dành cho sinh viên, doanh nghiệp, giảng viên và quản trị viên. Giao diện tiếng Việt; ứng dụng sử dụng PHP 8.2, MySQL/MariaDB, PDO và chạy trên Apache (đã cấu hình sẵn cho XAMPP).

## Yêu cầu

- Windows với XAMPP (Apache, MySQL/MariaDB và PHP 8.2 trở lên).
- PHP extensions: `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`.
- Composer 2 để cài PHPMailer.
- Trình duyệt hiện đại. Google Fonts chỉ được tải khi máy có kết nối Internet; font hệ thống là phương án dự phòng.
- Để gửi OTP: tài khoản Gmail đã bật xác minh 2 bước, Google App Password và cấu hình SMTP của XAMPP Sendmail.

## Cài đặt trên XAMPP

1. Đặt mã nguồn tại `C:\xampp\htdocs\InternTrack`.
2. Mở XAMPP Control Panel và khởi động Apache, MySQL.
3. Mở `http://localhost/phpmyadmin`, tạo database tên `interntrack` với collation `utf8mb4_unicode_ci`.
4. Chọn database vừa tạo, import `database/interntrack.sql`. Schema dùng `CREATE TABLE IF NOT EXISTS` và không xóa dữ liệu hiện có.
5. Tại thư mục dự án, chạy `composer install` để cài PHPMailer.
6. Với database đã cài từ phiên bản trước: chạy `database/migrations/20261001_password_reset_otp.sql` nếu chưa có bảng OTP, sau đó chạy `database/migrations/20261002_password_reset_verified_at.sql`. Với database mới, hai trường đã có trong schema chính.
7. Chỉ import `database/seed.sql` nếu cần dữ liệu mẫu để xem các màn hình và luồng nghiệp vụ. Không dùng dữ liệu seed làm tài khoản production; trang đăng nhập không còn hiển thị tài khoản hoặc mật khẩu demo.
8. Mở `http://localhost/InternTrack/`.

### Tài khoản demo mặc định (dùng cho môi trường local)

Sau khi import `database/seed.sql`, có thể đăng nhập bằng các tài khoản sau. Đây là tài khoản mẫu chỉ dành cho local/test và không dùng cho production.

| Vai trò       | Username   | Email                        | Mật khẩu       |
| ------------- | ---------- | ---------------------------- | -------------- |
| Sinh viên     | `student`  | `student@interntrack.local`  | `Student@123`  |
| Doanh nghiệp  | `company`  | `company@interntrack.local`  | `Company@123`  |
| Giảng viên    | `lecturer` | `lecturer@interntrack.local` | `Lecturer@123` |
| Quản trị viên | `admin`    | `admin@interntrack.local`    | `Bi@06112005`  |

> Nếu chưa có dữ liệu seed hoặc muốn tạo tài khoản mới, hãy tạo user trong bảng `users` với role tương ứng (`student`, `company`, `lecturer`, `admin`) và thêm bản ghi liên quan trong bảng `students`, `companies`, `lecturers` hoặc cấp quyền admin trong module quản trị.

Cấu hình database mặc định: host `127.0.0.1`, port `3306`, database `interntrack`, user `root`, mật khẩu rỗng. Có thể ghi đè bằng biến môi trường `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. Không dùng cấu hình mặc định này trên máy chủ thật.

### Tạo tài khoản quản trị ban đầu

Không có mật khẩu mặc định công khai. Với database chưa có tài khoản, tạo một password hash bằng PHP CLI:

```powershell
C:\xampp\php\php.exe -r "echo password_hash('THAY_BANG_MAT_KHAU_MẠNH', PASSWORD_DEFAULT), PHP_EOL;"
```

Thay giá trị được in ra vào câu lệnh SQL sau trong phpMyAdmin; thay email, username và họ tên theo nhu cầu:

```sql
INSERT INTO users (username, email, password_hash, full_name, role, status)
VALUES ('admin', 'admin@example.com', 'HASH_VUA_TAO', 'Quản trị viên', 'admin', 'active');
```

Sau đó đăng nhập bằng email hoặc username vừa tạo. Tài khoản sinh viên, doanh nghiệp và giảng viên cần được tạo trong bảng `users` với role tương ứng (`student`, `company`, `lecturer`) và bản ghi hồ sơ liên quan trong bảng `students`, `companies` hoặc `lecturers`. Trong môi trường production, nên dùng quy trình cấp tài khoản riêng, mật khẩu duy nhất và quyền truy cập database tối thiểu.

## Cấu hình gửi OTP bằng Gmail

Ứng dụng dùng PHPMailer gửi SMTP trực tiếp qua Gmail với STARTTLS. OTP chỉ được chấp nhận sau khi Gmail báo gửi thành công; mã được lưu dưới dạng SHA-256 hash, hết hạn sau 10 phút, chỉ xác minh một lần, tối đa 5 lần nhập sai và giới hạn gửi lại 60 giây. Form tạo mật khẩu chỉ xuất hiện sau khi máy chủ xác minh OTP hợp lệ.

1. Bật xác minh 2 bước cho tài khoản Gmail, sau đó tạo Google App Password. Không dùng mật khẩu Gmail thông thường.
2. Cấu hình các biến môi trường cho tiến trình Apache; không đặt bí mật trực tiếp trong mã nguồn:

```text
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-account@gmail.com
MAIL_PASSWORD=your-16-character-google-app-password
MAIL_FROM=your-account@gmail.com
MAIL_FROM_NAME=InternTrack
```

`MAIL_USERNAME` thường là địa chỉ Gmail gửi; `MAIL_FROM` nên là cùng địa chỉ đó. Trên Windows, tạo các biến môi trường User/System từ Settings hoặc System Properties, sau đó thoát và mở lại XAMPP Control Panel rồi restart Apache để Apache nhận giá trị. Không commit App Password vào `.env`, README, SQL hoặc Git.

3. Đảm bảo máy chủ cho phép kết nối outbound tới `smtp.gmail.com:587`, rồi thử Quên mật khẩu với tài khoản có email truy cập được. Kiểm tra Inbox và Spam. Nếu gửi lỗi, xem PHP/Apache error log; log ứng dụng không chứa App Password.

Không thể gửi Gmail thật nếu thiếu Google App Password hợp lệ hoặc mạng bị chặn. Đừng chia sẻ App Password trong chat hoặc repository.

Không đưa App Password, database password hay biến môi trường bí mật vào README, SQL seed, mã nguồn, ảnh chụp hoặc Git. Máy chủ thật nên dùng mail transport có giám sát và chính sách gửi thư phù hợp.

## Tính năng

### Sinh viên

- Xem cơ hội thực tập đang mở và xếp hạng gợi ý dựa trên chuyên ngành, phần giới thiệu, tiêu đề, mô tả và yêu cầu của vị trí.
- Tải CV PDF, DOC hoặc DOCX (tối đa 5 MB) lên hồ sơ. CV là điều kiện bắt buộc khi nộp đơn; bản CV tại thời điểm ứng tuyển được đính kèm hồ sơ.
- Cập nhật ảnh đại diện JPG, PNG hoặc WebP (tối đa 3 MB), thông tin liên hệ và giới thiệu.
- Theo dõi trạng thái đơn ứng tuyển, nhật ký, nhiệm vụ, báo cáo, đánh giá và tiến độ thực tập.

### Doanh nghiệp

- Cập nhật hồ sơ, đăng và quản lý vị trí tuyển dụng, xem CV ứng viên, duyệt hồ sơ, giao nhiệm vụ và đánh giá thực tập sinh.

### Giảng viên và quản trị viên

- Giảng viên theo dõi sinh viên, tiến độ, nhật ký, báo cáo và đánh giá.
- Quản trị viên quản lý tài khoản, doanh nghiệp, vị trí, kỳ thực tập và phân công giảng viên.

### Tài khoản và bảo vệ dữ liệu

- Đăng nhập theo vai trò, đăng xuất, CSRF token, mật khẩu băm bằng `password_hash()` và đặt lại mật khẩu bằng OTP email.
- Tệp CV, báo cáo và ảnh đại diện nằm trong `uploads/`; Apache chặn truy cập trực tiếp. CV/báo cáo được tải qua endpoint kiểm tra quyền; ảnh đại diện chỉ được xem bởi chính chủ.
- Tệp tải lên được kiểm tra MIME thực tế và giới hạn dung lượng; không dựa riêng vào phần mở rộng do trình duyệt gửi lên.

## Gợi ý cơ hội và AI

Màn hình cơ hội sắp xếp vị trí theo mức trùng từ khóa hồ sơ sinh viên với tiêu đề, mô tả và yêu cầu tuyển dụng. Đây là bộ gợi ý cục bộ, chạy không cần API key và không gửi CV/hồ sơ đến dịch vụ bên ngoài. Nó giúp ưu tiên cơ hội gần ngành học nhưng chưa phải mô hình học máy hoặc chatbot sinh nội dung. Muốn tích hợp LLM/embedding thực sự cần chọn nhà cung cấp, cấu hình khóa bí mật ngoài web root, chính sách đồng ý xử lý dữ liệu và đánh giá độ phù hợp trước khi triển khai.

## Cấu trúc chính

- `index.php`: định tuyến, session, xác thực CSRF, xử lý đăng nhập và gửi hồ sơ.
- `controllers/`: logic xác thực, dữ liệu màn hình và hành động nghiệp vụ.
- `models/`: các mô hình dữ liệu.
- `views/`: giao diện theo vai trò và layout dùng chung.
- `config/`: cấu hình kết nối database và tiện ích ứng dụng.
- `database/interntrack.sql`: schema; `database/migrations/`: thay đổi bổ sung cho database đã cài.
- `assets/`: CSS và JavaScript.
- `uploads/`: tệp người dùng tải lên; giữ quyền ghi cho PHP và không mở quyền truy cập web trực tiếp.

## UTF-8 và giao diện

HTML khai báo UTF-8; MySQL/PDO dùng `utf8mb4`. Font Be Vietnam Pro được tải từ Google Fonts khi có Internet, với fallback hệ thống. Bố cục thích ứng desktop, tablet và điện thoại; giảm chuyển động theo cài đặt hệ điều hành.

## Triển khai production

- Dùng HTTPS, mật khẩu database riêng, cập nhật PHP/MySQL và tắt hiển thị lỗi ra trình duyệt.
- Không import seed data; tạo tài khoản có mật khẩu riêng, không tái sử dụng mật khẩu máy local.
- Cấu hình `DB_*`, `MAIL_FROM` và mail transport trong môi trường server, không lưu secret vào repository.
- Sao lưu database và thư mục `uploads/`; kiểm tra quyền filesystem và khả năng gửi email.
- Chạy thử đặt lại mật khẩu, nộp CV, tải tệp theo quyền của từng vai trò và các luồng nghiệp vụ trước khi mở truy cập công khai.
