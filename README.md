# InternTrack

**InternTrack** là hệ thống quản lý và theo dõi kỳ thực tập dành cho trường đại học, kết nối **sinh viên**, **doanh nghiệp**, **giảng viên hướng dẫn** và **quản trị viên (khoa)** trên một nền tảng duy nhất. Giao diện hoàn toàn bằng tiếng Việt, kèm các trợ lý AI (Google Gemini) giúp ghép sinh viên với vị trí, đọc nhật ký/báo cáo, cảnh báo sớm sinh viên có nguy cơ và xác nhận kỹ năng thực tế.

Ứng dụng viết bằng PHP thuần (không framework, không bước build), dùng PDO/MySQL và chạy trên Apache/XAMPP.

## Mục lục

1. [Tính năng](#tính-năng)
2. [Công nghệ](#công-nghệ)
3. [Cài đặt nhanh trên XAMPP](#cài-đặt-nhanh-trên-xampp)
4. [Cấu hình (`.env`)](#cấu-hình-env)
5. [Tài khoản đăng nhập](#tài-khoản-đăng-nhập)
6. [Các tính năng AI](#các-tính-năng-ai)
7. [Gửi OTP quên mật khẩu bằng Gmail](#gửi-otp-quên-mật-khẩu-bằng-gmail)
8. [Kiến trúc và cấu trúc thư mục](#kiến-trúc-và-cấu-trúc-thư-mục)
9. [Cơ sở dữ liệu](#cơ-sở-dữ-liệu)
10. [Bảo mật và quyền riêng tư](#bảo-mật-và-quyền-riêng-tư)
11. [Triển khai production](#triển-khai-production)
12. [Xử lý sự cố](#xử-lý-sự-cố)

## Tính năng

### Sinh viên

- Xem **cơ hội thực tập** đang mở, được xếp hạng theo độ phù hợp (từ khóa cục bộ, hoặc điểm AI sau khi chạy gợi ý từ CV).
- Tải CV (PDF/DOC/DOCX, tối đa 5 MB), ảnh đại diện (JPG/PNG/WebP, tối đa 3 MB) và cập nhật hồ sơ; **CV là điều kiện bắt buộc để ứng tuyển**.
- Ứng tuyển, rút hồ sơ, theo dõi trạng thái đơn.
- Ghi **nhật ký thực tập**, nộp **báo cáo** (đề cương, giữa kỳ, cuối kỳ), xem **kế hoạch thực tập** do khoa giao và **đánh giá** nhận được.
- Nhận nhiệm vụ từ doanh nghiệp, báo hoàn thành kèm minh chứng (liên kết Git/Drive và/hoặc tệp tối đa 20 MB).
- **Bong bóng chat AI** ở mọi trang: gợi ý vị trí, hỏi quy định/mốc thời gian/biểu mẫu của khoa, góp ý CV, soạn thư xin thực tập, gợi ý dàn ý báo cáo cuối kỳ từ nhật ký.
- Xem **kỹ năng đã được doanh nghiệp xác nhận** trong hồ sơ.
- Nhận thông báo theo từng mục (chuông và chấm đỏ trên menu).

### Doanh nghiệp

- Quản lý hồ sơ công ty, đăng và chỉnh sửa vị trí tuyển dụng.
- Xem CV ứng viên, duyệt hồ sơ; khi nhận ứng viên, hệ thống tự tạo kỳ thực tập và tự phân công giảng viên đang phụ trách ít sinh viên nhất.
- Giao và theo dõi **nhiệm vụ** dạng bảng kanban, có bộ lọc theo sinh viên, tìm kiếm và lọc việc chờ xác nhận; xem minh chứng rồi xác nhận hoặc yêu cầu làm lại.
- **Đánh giá** thực tập sinh ngay trên từng dòng (lưu nháp, gửi, điều chỉnh sau khi gửi).
- **Xác nhận kỹ năng** AI đề xuất từ nhật ký tuần (xác nhận / sửa / bác bỏ từng dòng).

### Giảng viên

- Theo dõi sinh viên được phân công, kế hoạch thực tập (chỉ xem), tiến độ, nhật ký, báo cáo và đánh giá.
- Duyệt nhật ký, báo cáo kèm phản hồi.
- **AI tóm tắt và chấm sơ bộ** nhật ký, báo cáo; đối chiếu kế hoạch, đánh dấu sơ sài hoặc chép lại tuần trước.
- **Cảnh báo sớm sinh viên có nguy cơ** (trang Tiến độ) kèm gợi ý can thiệp.
- **AI soạn nhận xét cuối kỳ** từ đánh giá của doanh nghiệp và giảng viên.

### Quản trị viên (khoa)

- Quản lý tài khoản (sinh viên, giảng viên, doanh nghiệp, quản trị), xác minh doanh nghiệp, đóng/mở vị trí tuyển dụng.
- Quản lý **kỳ thực tập**: đổi trạng thái, phân công giảng viên, nhập kế hoạch, và **giao kế hoạch chung cho cả một ngành** (có danh sách ngành, tìm kiếm).
- Mã doanh nghiệp (10 chữ số) chỉ nhập lúc tạo, **không chỉnh sửa được** sau đó.
- Quản lý **tài liệu của khoa** (quy định, mốc thời gian, biểu mẫu) làm nguồn cho trợ lý hỏi đáp.
- Bảng điều khiển có thống kê và **thống kê kỹ năng doanh nghiệp đang cần** do AI tổng hợp, kèm gợi ý điều chỉnh chương trình.

## Công nghệ

| Thành phần            | Chi tiết                                                                  |
| --------------------- | ------------------------------------------------------------------------- |
| Ngôn ngữ / máy chủ    | PHP 8.2+, Apache (XAMPP)                                                  |
| Cơ sở dữ liệu         | MySQL 8 / MariaDB 10.5+, PDO với prepared statement, `utf8mb4_unicode_ci` |
| Giao diện             | HTML, CSS và JavaScript thuần (không build), font Be Vietnam Pro          |
| Thư viện PHP          | PHPMailer (qua Composer)                                                  |
| AI                    | Google Gemini API (REST, qua cURL)                                        |
| Yêu cầu PHP extension | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`                    |

Không có framework, không có bước build và hiện chưa có bộ test tự động.

## Cài đặt nhanh trên XAMPP

1. Đặt mã nguồn tại `C:\xampp\htdocs\InternTrack`.
2. Khởi động **Apache** và **MySQL** trong XAMPP Control Panel.
3. Vào `http://localhost/phpmyadmin`, tạo database `interntrack` với collation `utf8mb4_unicode_ci`.
4. Import `database/interntrack.sql` (toàn bộ schema, dùng `CREATE TABLE IF NOT EXISTS` nên chạy lại không xóa dữ liệu).
5. (Tùy chọn) Import `database/seed.sql` để có dữ liệu mẫu và tài khoản demo — **chỉ dùng ở máy local**.
6. Tại thư mục dự án chạy `composer install` để cài PHPMailer.
7. Sao chép `.env.example` thành `.env` và điền cấu hình (xem phần dưới).
8. Mở `http://localhost/InternTrack/`.

Kiểm tra cú pháp một tệp PHP: `C:\xampp\php\php.exe -l <tệp>`.

## Cấu hình (`.env`)

Mọi cấu hình đọc từ biến môi trường hoặc tệp `.env` ở thư mục gốc (`app_env()` trong `config/app.php`). `.env` bị Git bỏ qua và bị `.htaccess` chặn truy cập qua web. **Chỉ điền bí mật vào `.env`, không điền vào `.env.example`** vì tệp mẫu được commit lên Git.

| Biến                                                                                      | Mặc định                                         | Ý nghĩa                                                                         |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------------------- |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`                                 | `127.0.0.1`, `3306`, `interntrack`, `root`, rỗng | Kết nối MySQL                                                                   |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM`, `MAIL_FROM_NAME` | Gmail SMTP, cổng 587                             | Gửi OTP quên mật khẩu                                                           |
| `GEMINI_API_KEY`                                                                          | rỗng                                             | Khóa Gemini; **để trống thì mọi tính năng AI tự ẩn**                            |
| `GEMINI_MODEL`                                                                            | `gemini-3.8-flash`                               | Model Gemini sử dụng (model `gemini-2.5-flash` không còn cấp cho tài khoản mới) |

## Tài khoản đăng nhập

Sau khi import `database/seed.sql`, có thể dùng các tài khoản demo (chỉ cho local/test):

| Vai trò      | Username   | Mật khẩu       |
| ------------ | ---------- | -------------- |
| Sinh viên    | `student`  | `Student@123`  |
| Doanh nghiệp | `company`  | `Company@123`  |
| Giảng viên   | `lecturer` | `Lecturer@123` |

Tài khoản **quản trị viên không có mật khẩu mặc định**. Tạo một password hash rồi chèn vào bảng `users`:

```powershell
C:\xampp\php\php.exe -r "echo password_hash('THAY_BANG_MAT_KHAU_MANH', PASSWORD_DEFAULT), PHP_EOL;"
```

```sql
INSERT INTO users (username, email, password_hash, full_name, role, status)
VALUES ('admin', 'admin@example.com', 'HASH_VUA_TAO', 'Quản trị viên', 'admin', 'active');
```

Quản trị viên sau đó tạo tài khoản sinh viên, giảng viên, doanh nghiệp ngay trong trang **Tài khoản**. Tên công ty, địa chỉ và tin tuyển dụng trong `seed.sql` chỉ là dữ liệu minh họa; tin chỉ hiển thị cho sinh viên khi công ty có địa chỉ và tin có địa điểm làm việc rõ ràng (công việc từ xa ghi "Từ xa").

## Các tính năng AI

Bật bằng cách lấy khóa miễn phí tại <https://aistudio.google.com/apikey> và thêm `GEMINI_API_KEY=...` vào `.env`. Không cần khởi động lại Apache.

| Tính năng                           | Ai dùng       | Vị trí trong giao diện               | Dữ liệu AI đọc                                                         |
| ----------------------------------- | ------------- | ------------------------------------ | ---------------------------------------------------------------------- |
| Gợi ý vị trí từ CV                  | Sinh viên     | Cơ hội thực tập → "Gợi ý bằng AI"    | CV PDF, ngành học, giới thiệu, các vị trí đang mở                      |
| Bong bóng chat                      | Sinh viên     | Góc dưới phải mọi trang              | CV, hồ sơ, nhật ký, nhiệm vụ, vị trí đang mở, tài liệu khoa            |
| Tóm tắt, chấm sơ bộ nhật ký/báo cáo | Giảng viên    | Nhật ký, Báo cáo → "AI tóm tắt"      | Nội dung, kế hoạch thực tập, các mục trước                             |
| Cảnh báo rủi ro                     | Giảng viên    | Tiến độ → "Phân tích rủi ro bằng AI" | Số liệu nộp trễ, vắng nhật ký, nhiệm vụ, điểm và nhận xét doanh nghiệp |
| Nhận xét cuối kỳ                    | Giảng viên    | Đánh giá → "Nhận xét AI"             | Đánh giá đã gửi, phản hồi trên nhật ký                                 |
| Đề xuất kỹ năng từ nhật ký          | Doanh nghiệp  | Kỹ năng thực tập                     | Nhật ký tuần, ngành học, vị trí, kỹ năng các tuần trước                |
| Thống kê kỹ năng cần thiết          | Quản trị viên | Tổng quan                            | Yêu cầu và mô tả các tin tuyển dụng                                    |

**Nguyên tắc thiết kế**

- AI chỉ **đề xuất**; con người quyết định. Kỹ năng chỉ vào hồ sơ sau khi doanh nghiệp xác nhận; điểm AI chấm là "sơ bộ".
- Kết quả được **kiểm tra lại ở phía server**: mã vị trí phải có thật, điểm bị chặn 0–100, và bằng chứng kỹ năng phải là đoạn trích **nguyên văn** từ nhật ký (nếu không sẽ bị loại).
- Nội dung do người dùng viết (CV, nhật ký, nhận xét) luôn được báo cho AI là **dữ liệu**, không phải chỉ dẫn.
- Kết quả được lưu trong DB (`ai_matches`, `ai_insights`, `skill_suggestions`) để xem lại mà không tốn thêm lượt gọi.
- Giới hạn tần suất theo phiên (30 lượt/10 phút cho phân tích, 20 câu/10 phút cho chat; gợi ý ghép vị trí cách nhau 10 phút) và tự thử lại khi Gemini báo quá tải (503).
- AI chỉ đọc được tệp **PDF** (tối đa 4 MB); báo cáo hoặc CV Word cần có nội dung văn bản hoặc được đổi sang PDF.

**Tài liệu cho trợ lý hỏi đáp:** quản trị viên vào **Tài liệu khoa** để dán nội dung hoặc tải tệp `.txt`/`.md` (UTF-8, tối đa 300 KB). Bong bóng chat trả lời quy định, mốc thời gian, biểu mẫu **chỉ dựa trên các tài liệu này** (AI đọc tối đa 30.000 ký tự); nếu không có thông tin sẽ khuyên liên hệ giáo vụ.

**Quyền riêng tư:** CV, nhật ký, báo cáo và nhận xét được gửi tới Google để phân tích. Với gói miễn phí, Google có thể dùng dữ liệu này để cải thiện sản phẩm. Hãy thông báo cho người dùng hoặc chuyển sang gói trả phí trước khi triển khai thật.

## Gửi OTP quên mật khẩu bằng Gmail

OTP gồm 6 chữ số, lưu dạng **SHA-256 hash**, hết hạn sau **60 giây**, chỉ xác minh một lần, tối đa **5 lần nhập sai**, và chỉ được gửi lại sau **60 giây**. Form đặt mật khẩu chỉ hiện sau khi máy chủ xác minh OTP hợp lệ; OTP chỉ được chấp nhận khi Gmail báo gửi thành công.

1. Bật xác minh 2 bước cho tài khoản Gmail rồi tạo **Google App Password** (không dùng mật khẩu Gmail thường).
2. Điền các biến `MAIL_*` trong `.env`:

   ```text
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-account@gmail.com
   MAIL_PASSWORD=your-16-character-google-app-password
   MAIL_FROM=your-account@gmail.com
   MAIL_FROM_NAME=InternTrack
   ```

3. Bảo đảm máy chủ kết nối ra được `smtp.gmail.com:587`, rồi thử "Quên mật khẩu"; kiểm tra cả thư mục Spam. Nếu gửi lỗi, xem log PHP/Apache (log không chứa App Password).

Không đưa App Password, mật khẩu database hay khóa API vào README, SQL, mã nguồn, ảnh chụp hoặc Git.

## Kiến trúc và cấu trúc thư mục

Mọi request đi qua **front controller** `index.php`.

```text
InternTrack/
├── index.php                      # Định tuyến, session, CSRF, đăng nhập/OTP, endpoint JSON cho chat AI
├── config/
│   ├── app.php                    # Session, flash, CSRF, app_env(), gửi mail, xử lý lỗi toàn cục
│   └── database.php               # database() (PDO), database_transaction()
├── controllers/
│   ├── AuthController.php         # Đăng nhập, phiên, OTP đặt lại mật khẩu
│   ├── ActionController.php       # Mọi thao tác POST nghiệp vụ + tải tệp có kiểm tra quyền
│   ├── PageController.php         # Nạp dữ liệu cho từng màn hình
│   ├── NotificationController.php # Thông báo theo mục
│   ├── AiMatchController.php      # Gemini: gợi ý ghép vị trí, bong bóng chat, lõi gọi API
│   └── AiInsightController.php    # Gemini: tóm tắt nhật ký/báo cáo, rủi ro, nhận xét cuối kỳ, kỹ năng
├── views/
│   ├── <vai_trò>/<màn_hình>.php   # Mỗi tệp chỉ khai báo $screen rồi gọi layout chung
│   └── layouts/                   # screen, header, sidebar, content, forms, footer, page-data, ai-insight
├── assets/                        # css/style.css, js/app.js, images/ (logo, favicon SVG)
├── database/                      # interntrack.sql (schema đầy đủ), seed.sql (dữ liệu mẫu)
├── uploads/                       # Tệp người dùng tải lên (Apache chặn truy cập trực tiếp)
├── .env.example · composer.json · .htaccess
```

**Luồng xử lý**

- **Định tuyến:** `?page=<vai_trò>/<màn_hình>` phải nằm trong danh sách `$availablePages` ở `index.php`; tiền tố vai trò phải khớp vai trò người dùng (sai thì 403); chỉ `auth/*` là công khai. Thêm một màn hình = thêm vào danh sách này, tạo view trong `views/<vai_trò>/` và khai báo trong `views/layouts/page-data.php`.
- **POST:** `index.php` kiểm tra CSRF, xử lý đăng nhập/OTP, còn lại giao cho `handle_workspace_action()` (kiểm tra quyền, validate, ghi DB, trả về trang chuyển hướng). Lỗi nghiệp vụ ném `DomainException` và hiển thị bằng flash; lỗi khác được ghi log và thay bằng thông báo chung. Mô hình Post/Redirect/Get với `app_set_flash`/`app_take_flash`.
- **GET:** `?download=<loại>&id=<n>` do `serve_workspace_download()` phục vụ sau khi kiểm tra quyền; còn lại view được `require`.
- **Giao diện hướng dữ liệu:** view của mỗi vai trò gọi `layouts/screen.php`, nạp cấu hình màn hình từ `page-data.php`, bổ sung dữ liệu thật qua `load_screen_data()` rồi hiển thị bằng `header`/`content`/`footer`.
- **Phạm vi dữ liệu:** quyền truy cập kỳ thực tập theo vai trò đi qua `action_internship()`; hãy dùng lại hàm này thay vì tự kiểm tra sở hữu.
- **Quy ước:** `declare(strict_types=1)`, chuỗi giao diện và chú thích bằng tiếng Việt, escape đầu ra bằng `screen_escape()`/`ai_e()`, luôn dùng prepared statement, nhóm thao tác nhiều bước trong `database_transaction()`.

## Cơ sở dữ liệu

`database/interntrack.sql` là schema đầy đủ (MySQL 8+), gồm:

| Nhóm       | Bảng                                                                                          |
| ---------- | --------------------------------------------------------------------------------------------- |
| Tài khoản  | `users`, `students`, `companies`, `lecturers`, `password_reset_otps`, `password_reset_tokens` |
| Tuyển dụng | `positions`, `applications`, `application_status_history`                                     |
| Thực tập   | `internships`, `internship_status_history`, `tasks`, `diaries`, `reports`, `evaluations`      |
| Thông báo  | `notifications`                                                                               |
| AI         | `ai_matches`, `ai_insights`, `skill_suggestions`, `kb_documents`                              |
| View       | `v_student_internships`, `v_application_list`, `v_task_list`, `v_internship_progress`         |

Schema đã gộp mọi migration cũ. Với database cài từ phiên bản trước, import lại `interntrack.sql` sẽ thêm các bảng còn thiếu; cột mới trên bảng cũ (ví dụ `training_plan`, `verified_at`, các cột minh chứng nhiệm vụ) cần thêm bằng `ALTER TABLE` nếu thiếu. Mã doanh nghiệp là chuỗi **10 chữ số**, duy nhất.

## Bảo mật và quyền riêng tư

- Mật khẩu băm bằng `password_hash()`; CSRF token cho mọi POST; PDO prepared statement; escape đầu ra.
- `.htaccess` chặn truy cập trực tiếp tới `.env`, `*.sql`, `*.md`, `composer.*`, `config/`, `controllers/`, `views/`, `database/` và `vendor/`; tắt liệt kê thư mục.
- Tệp trong `uploads/` bị chặn truy cập trực tiếp, chỉ được phục vụ qua endpoint tải có kiểm tra quyền (ảnh đại diện hiển thị cho mọi người dùng đã đăng nhập). Tệp tải lên được kiểm tra MIME thực và dung lượng, không chỉ dựa vào phần mở rộng.
- Kết quả AI luôn được escape trước khi hiển thị; liên kết trong khung chat chỉ được tạo từ mã vị trí dạng số.
- Mỗi giảng viên chỉ thấy và phân tích sinh viên mình phụ trách; doanh nghiệp chỉ thao tác trên thực tập sinh của mình.
- Khóa API và mật khẩu chỉ nằm trong `.env`.

## Triển khai production

- Dùng **HTTPS**, mật khẩu database riêng, tài khoản DB quyền tối thiểu, cập nhật PHP/MySQL và tắt hiển thị lỗi ra trình duyệt.
- **Không import `seed.sql`**; tạo tài khoản với mật khẩu riêng, không tái sử dụng mật khẩu local.
- Cấu hình `DB_*`, `MAIL_*`, `GEMINI_API_KEY` trong môi trường máy chủ, không lưu bí mật trong repository.
- Cân nhắc thông báo/đồng ý xử lý dữ liệu khi dùng AI và gói trả phí của Gemini nếu cần cam kết riêng tư.
- Sao lưu database và thư mục `uploads/`; kiểm tra quyền ghi của PHP trên `uploads/` và khả năng gửi email.
- Chạy thử đặt lại mật khẩu, nộp CV, tải tệp theo từng vai trò và các luồng nghiệp vụ trước khi mở công khai.

## Xử lý sự cố

| Hiện tượng                            | Nguyên nhân thường gặp                                                                               |
| ------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Không thấy bong bóng chat hoặc nút AI | Chưa có `GEMINI_API_KEY` trong `.env`, hoặc đang đăng nhập sai vai trò (bong bóng chỉ cho sinh viên) |
| AI báo "chưa phản hồi được"           | Sai khóa/model (xem log lỗi PHP), hoặc hết hạn mức miễn phí; thử lại sau ít phút                     |
| "AI hiện chỉ đọc được CV dạng PDF"    | CV đang là Word; tải lại bản PDF                                                                     |
| Không gửi được OTP                    | Sai App Password, chưa bật xác minh 2 bước, hoặc mạng chặn cổng 587                                  |
| Lỗi kết nối cơ sở dữ liệu             | MySQL chưa chạy hoặc `DB_*` sai; xem log lỗi PHP                                                     |
| Sinh viên không thấy vị trí           | Công ty chưa được xác minh, thiếu địa chỉ, hoặc tin thiếu địa điểm làm việc / đã quá hạn             |
| Tải lên bị từ chối                    | Sai định dạng hoặc vượt dung lượng cho phép (CV/báo cáo 5 MB, ảnh 3 MB, minh chứng 20 MB)            |
