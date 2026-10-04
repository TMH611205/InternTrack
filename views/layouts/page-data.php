<?php
// Danh sách cấu hình dữ liệu hiển thị cho từng màn hình của ứng dụng.
// Mỗi key tương ứng với một route và định nghĩa role, loại layout, tiêu đề, mô tả, metric và dữ liệu bảng/card.
// Cấu hình MẪU của từng màn hình, theo khóa "<vai_trò>/<màn_hình>". Khi chạy thật, PageController::load_screen_data() ghi đè bằng dữ liệu từ database,
// nên các số liệu/dòng dữ liệu ở đây chỉ là dự phòng. Các khóa quan trọng: role, active (mục menu đang sáng), kind (kiểu giao diện trong content.php),
// eyebrow/title/description (tiêu đề), metrics (3 thẻ số liệu), columns/rows (bảng), cards, boards, members...
// Thêm màn hình mới: thêm một khóa ở đây, một `case` trong PageController.php và đường dẫn trong index.php.
return [

    // [Sinh viên] Tổng quan: tiến độ nhiệm vụ, nhật ký, hoạt động gần đây.
    'student/dashboard' => [
        'role' => 'student',
        'active' => 'dashboard',
        'kind' => 'dashboard',
        'eyebrow' => 'Không gian sinh viên',
        'title' => 'Chào buổi sáng, Minh Anh',
        'description' => 'Kỳ thực tập của bạn đang đi đúng nhịp. Đây là những việc đáng chú ý hôm nay.',
        'metrics' => [['label' => 'Tiến độ thực tập', 'value' => '68%', 'note' => '+8% trong tháng'], ['label' => 'Nhật ký đã duyệt', 'value' => '18/24', 'note' => 'Cập nhật hôm qua'], ['label' => 'Nhiệm vụ đang mở', 'value' => '04', 'note' => '1 việc đến hạn hôm nay']],
        'progress' => ['label' => 'Kỳ thực tập tại Northstar Studio', 'value' => '68%', 'note' => 'Còn 24 ngày · kết thúc 25/11/2026'],
        'bars' => [35, 52, 46, 71, 63, 82, 68],
        'activities' => [['time' => '09:20', 'title' => 'Gửi nhật ký tuần 7', 'description' => 'Đang chờ giảng viên Nguyễn Hà duyệt.', 'status' => 'Chờ duyệt'], ['time' => 'Hôm nay', 'title' => 'Hoàn thiện luồng onboarding', 'description' => 'Hạn nộp trước 17:00 · Northstar Studio.', 'status' => 'Đang làm'], ['time' => 'Thứ Hai', 'title' => 'Nhận phản hồi giữa kỳ', 'description' => 'Điểm đánh giá hiện tại: 86/100.', 'status' => 'Hoàn tất']],
    ],

    // [Sinh viên] Đơn ứng tuyển và trạng thái.
    'student/applications' => [
        'role' => 'student',
        'active' => 'applications',
        'kind' => 'table',
        'eyebrow' => 'Cơ hội nghề nghiệp',
        'title' => 'Đơn ứng tuyển',
        'description' => 'Theo dõi trạng thái và phản hồi từ những doanh nghiệp bạn đã kết nối.',
        'metrics' => [['label' => 'Tổng đơn', 'value' => '06', 'note' => 'Trong học kỳ này'], ['label' => 'Đang xem xét', 'value' => '02', 'note' => 'Phản hồi thường trong 3 ngày'], ['label' => 'Được nhận', 'value' => '01', 'note' => 'Northstar Studio']],
        'columns' => ['Vị trí', 'Doanh nghiệp', 'Ngày nộp', 'Trạng thái'],
        'rows' => [['Product Design Intern', 'Northstar Studio', '18/08/2026', 'Được nhận'], ['UX Research Intern', 'Mộc Lab', '21/08/2026', 'Đang xem'], ['Content Intern', 'Lá House', '24/08/2026', 'Đã nộp'], ['Visual Designer', 'Mây Creative', '10/08/2026', 'Chưa phù hợp']],
    ],

    // [Sinh viên] Nhật ký thực tập (dạng dòng thời gian).
    'student/diary' => [
        'role' => 'student',
        'active' => 'diary',
        'kind' => 'timeline',
        'eyebrow' => 'Nhịp làm việc',
        'title' => 'Nhật ký thực tập',
        'description' => 'Ghi lại điều bạn đã làm, thời gian tập trung và những điều muốn hỏi người hướng dẫn.',
        'metrics' => [['label' => 'Tuần hiện tại', 'value' => '07', 'note' => '24/08 – 30/08'], ['label' => 'Giờ đã ghi', 'value' => '32h', 'note' => 'Mục tiêu 40 giờ/tuần'], ['label' => 'Chờ duyệt', 'value' => '01', 'note' => 'Gửi ngày 28/08']],
        'activities' => [['time' => 'Thứ Sáu · 28/08', 'title' => 'Rà soát prototype onboarding', 'description' => 'Kiểm tra luồng tạo tài khoản trên mobile, ghi nhận 3 điểm gây nhầm lẫn và cập nhật prototype Figma. · 7.5 giờ', 'status' => 'Chờ duyệt'], ['time' => 'Thứ Năm · 27/08', 'title' => 'Phỏng vấn người dùng nội bộ', 'description' => 'Thực hiện 2 buổi phỏng vấn với nhóm hỗ trợ khách hàng, tổng hợp insight vào tài liệu nghiên cứu. · 8 giờ', 'status' => 'Đã duyệt'], ['time' => 'Thứ Tư · 26/08', 'title' => 'Workshop cùng nhóm sản phẩm', 'description' => 'Đồng xây dựng journey map và thống nhất phạm vi cho bản thử nghiệm tiếp theo. · 8 giờ']],
    ],

    // [Sinh viên] Đánh giá nhận được từ doanh nghiệp và giảng viên.
    'student/evaluation' => [
        'role' => 'student',
        'active' => 'evaluation',
        'kind' => 'evaluation',
        'eyebrow' => 'Phản hồi phát triển',
        'title' => 'Đánh giá thực tập',
        'description' => 'Góc nhìn từ doanh nghiệp và giảng viên, tập trung vào năng lực bạn đang xây dựng.',
        'metrics' => [['label' => 'Điểm tổng hợp', 'value' => '86/100', 'note' => 'Tốt · cập nhật 20/08'], ['label' => 'Đánh giá đã gửi', 'value' => '02', 'note' => 'Doanh nghiệp và giảng viên'], ['label' => 'Còn lại', 'value' => '01', 'note' => 'Đánh giá cuối kỳ']],
        'criteria' => [['name' => 'Chuyên môn', 'score' => '88'], ['name' => 'Tinh thần chủ động', 'score' => '91'], ['name' => 'Giao tiếp', 'score' => '82'], ['name' => 'Kỷ luật', 'score' => '85']],
        'quote' => 'Minh Anh tiếp nhận phản hồi nhanh và biết chuyển insight thành quyết định thiết kế rõ ràng. Hãy tiếp tục rèn cách trình bày phương án với các bên liên quan.',
    ],

    // [Sinh viên] Chi tiết một vị trí tuyển dụng và form ứng tuyển.
    'student/internship-detail' => [
        'role' => 'student',
        'active' => 'internships',
        'kind' => 'detail',
        'eyebrow' => 'Cơ hội thực tập',
        'title' => 'Product Design Intern',
        'description' => 'Northstar Studio · Nhóm Product Experience · TP. Hồ Chí Minh',
        'metrics' => [['label' => 'Mức độ phù hợp', 'value' => '94%', 'note' => 'Theo hồ sơ hiện tại'], ['label' => 'Số lượng', 'value' => '02', 'note' => 'Vị trí đang tuyển'], ['label' => 'Hạn ứng tuyển', 'value' => '15/09', 'note' => 'Còn 16 ngày']],
        'fields' => [['label' => 'Bạn sẽ làm gì', 'value' => 'Thiết kế luồng onboarding cho nền tảng quản lý bán lẻ; phối hợp với product và engineering để thử nghiệm giải pháp.'], ['label' => 'Yêu cầu', 'value' => 'Sinh viên năm 3–4 ngành thiết kế hoặc công nghệ; có portfolio và tinh thần chủ động học hỏi.'], ['label' => 'Địa điểm', 'value' => 'Hybrid · Quận 3, TP. Hồ Chí Minh'], ['label' => 'Quyền lợi', 'value' => 'Có mentor đồng hành, hỗ trợ chi phí thực tập và cơ hội tham gia sản phẩm đang vận hành.']],
    ],

    // [Sinh viên] Cơ hội thực tập đang mở, xếp theo độ phù hợp.
    'student/internships' => [
        'role' => 'student',
        'active' => 'internships',
        'kind' => 'cards',
        'eyebrow' => 'Cơ hội nghề nghiệp',
        'title' => 'Cơ hội thực tập',
        'description' => 'Khám phá cơ hội thực tập tại TP. Vinh, Nghệ An. Mỗi tin hiển thị rõ công ty, địa chỉ và nơi làm việc.',
        'metrics' => [['label' => 'Vị trí đang mở', 'value' => '42', 'note' => 'Tại 28 doanh nghiệp'], ['label' => 'Phù hợp hồ sơ', 'value' => '12', 'note' => 'Theo ngành Thiết kế UX'], ['label' => 'Sắp hết hạn', 'value' => '05', 'note' => 'Trong 7 ngày tới']],
        'cards' => [['title' => 'Product Design Intern', 'meta' => 'Northstar Studio · TP. Hồ Chí Minh · Hybrid', 'description' => 'Thiết kế trải nghiệm cho nền tảng quản lý bán lẻ. Hạn nhận hồ sơ 15/09.', 'tag' => 'Phù hợp 94%'], ['title' => 'UX Research Intern', 'meta' => 'Mộc Lab · TP. Hồ Chí Minh · Tại văn phòng', 'description' => 'Tham gia nghiên cứu hành vi và kiểm thử sản phẩm giáo dục. Hạn 18/09.', 'tag' => 'Phù hợp 89%'], ['title' => 'Frontend Developer Intern', 'meta' => 'Lá House · Remote · 2 vị trí', 'description' => 'Xây dựng giao diện web cùng nhóm sản phẩm. Hạn nhận hồ sơ 23/09.', 'tag' => 'Đang tuyển']],
    ],

    // [Sinh viên] Hồ sơ cá nhân.
    'student/profile' => [
        'role' => 'student',
        'active' => 'profile',
        'kind' => 'profile',
        'eyebrow' => 'Hồ sơ cá nhân',
        'title' => 'Minh Anh Nguyễn',
        'description' => 'Sinh viên Thiết kế trải nghiệm người dùng · Đại học Kiến trúc TP. Hồ Chí Minh',
        'fields' => [['label' => 'Mã sinh viên', 'value' => 'UX22-0418'], ['label' => 'Email', 'value' => 'minhanh.nguyen@sv.uah.edu.vn'], ['label' => 'Lớp / Khoa', 'value' => 'UXD22A · Khoa Mỹ thuật công nghiệp'], ['label' => 'Số điện thoại', 'value' => '+84 903 248 681'], ['label' => 'Địa chỉ', 'value' => 'Quận Bình Thạnh, TP. Hồ Chí Minh'], ['label' => 'Giới thiệu', 'value' => 'Mình quan tâm đến những sản phẩm số dễ hiểu, có ích và tôn trọng thời gian của người dùng.']],
    ],

    // [Sinh viên] Báo cáo thực tập.
    'student/reports' => [
        'role' => 'student',
        'active' => 'reports',
        'kind' => 'reports',
        'eyebrow' => 'Tài liệu học tập',
        'title' => 'Báo cáo thực tập',
        'description' => 'Các mốc nộp bài và phản hồi của giảng viên trong suốt kỳ thực tập.',
        'cards' => [['title' => 'Đề cương thực tập', 'meta' => 'Đã nộp · 04/09/2026', 'description' => 'Đã duyệt bởi Nguyễn Hà · 08/09/2026', 'tag' => 'Đã duyệt'], ['title' => 'Báo cáo giữa kỳ', 'meta' => 'Đã nộp · 16/10/2026', 'description' => 'Giảng viên đang xem xét.', 'tag' => 'Đang xem'], ['title' => 'Báo cáo cuối kỳ', 'meta' => 'Hạn nộp · 30/11/2026', 'description' => 'Chưa nộp · còn 31 ngày.', 'tag' => 'Sắp đến hạn']],
    ],

    // [Sinh viên] Bảng nhiệm vụ được giao.
    'student/tasks' => [
        'role' => 'student',
        'active' => 'tasks',
        'kind' => 'kanban',
        'eyebrow' => 'Việc cần làm',
        'title' => 'Nhiệm vụ thực tập',
        'description' => 'Một danh sách gọn để biết việc nào cần tập trung, việc nào có thể chờ.',
        'boards' => [['title' => 'Cần làm', 'items' => [['title' => 'Tổng hợp insight phỏng vấn', 'meta' => 'Hạn 02/09 · Ưu tiên cao'], ['title' => 'Chuẩn bị review tuần 8', 'meta' => 'Hạn 04/09 · Ưu tiên vừa']]], ['title' => 'Đang thực hiện', 'items' => [['title' => 'Hoàn thiện luồng onboarding', 'meta' => 'Hạn hôm nay · Northstar Studio'], ['title' => 'Cập nhật component library', 'meta' => 'Hạn 05/09 · Ưu tiên vừa']]], ['title' => 'Hoàn tất', 'items' => [['title' => 'Audit trải nghiệm mobile', 'meta' => 'Hoàn tất 27/08'], ['title' => 'Gửi nhật ký tuần 6', 'meta' => 'Đã duyệt 25/08']]]],
    ],

    // [Doanh nghiệp] Tổng quan.
    'company/dashboard' => [
        'role' => 'company',
        'active' => 'dashboard',
        'kind' => 'dashboard',
        'eyebrow' => 'Northstar Studio',
        'title' => 'Chào ngày mới, Linh',
        'description' => 'Bảng điều khiển tuyển dụng và hướng dẫn thực tập sinh của doanh nghiệp.',
        'metrics' => [['label' => 'Vị trí đang tuyển', 'value' => '04', 'note' => '2 vị trí sắp hết hạn'], ['label' => 'Hồ sơ mới', 'value' => '18', 'note' => '+6 trong 7 ngày'], ['label' => 'Thực tập sinh', 'value' => '12', 'note' => '3 cần đánh giá tuần này']],
        'progress' => ['label' => 'Hoàn tất đánh giá giữa kỳ', 'value' => '75%', 'note' => '9/12 thực tập sinh đã được đánh giá'],
        'bars' => [28, 43, 52, 66, 58, 81, 75],
        'activities' => [['time' => '10 phút trước', 'title' => 'Ứng viên mới cho Product Design', 'description' => 'Trần Gia Hân · UXD22A · hồ sơ phù hợp 92%.', 'status' => 'Hồ sơ mới'], ['time' => 'Hôm nay', 'title' => 'Đánh giá của Quang Minh đến hạn', 'description' => 'Kỳ thực tập kết thúc sau 12 ngày.', 'status' => 'Cần chú ý'], ['time' => 'Hôm qua', 'title' => 'Vị trí Content Intern được đăng', 'description' => 'Tin tuyển dụng hiện có 8 hồ sơ.', 'status' => 'Đã đăng']],
    ],

    // [Doanh nghiệp] Hồ sơ ứng tuyển cần xử lý.
    'company/applications' => [
        'role' => 'company',
        'active' => 'applications',
        'kind' => 'table',
        'eyebrow' => 'Tuyển dụng',
        'title' => 'Hồ sơ ứng tuyển',
        'description' => 'Sàng lọc hồ sơ, xem nhanh mức phù hợp và chuyển ứng viên sang vòng tiếp theo.',
        'metrics' => [['label' => 'Chưa xem', 'value' => '08', 'note' => 'Cần phản hồi trong tuần'], ['label' => 'Đang xem xét', 'value' => '06', 'note' => '3 lịch phỏng vấn'], ['label' => 'Đã nhận', 'value' => '04', 'note' => 'Trong đợt tuyển này']],
        'columns' => ['Ứng viên', 'Vị trí', 'Ngày ứng tuyển', 'Trạng thái'],
        'rows' => [['Trần Gia Hân', 'Product Design Intern', '29/08/2026', 'Mới'], ['Phạm Quốc Bảo', 'Frontend Intern', '28/08/2026', 'Đang xem'], ['Lê Ngọc Vy', 'Product Design Intern', '27/08/2026', 'Phỏng vấn'], ['Đỗ Hoàng Long', 'Content Intern', '24/08/2026', 'Đã nhận']],
    ],

    // [Doanh nghiệp] Đánh giá thực tập sinh.
    'company/evaluations' => [
        'role' => 'company',
        'active' => 'evaluations',
        'kind' => 'table',
        'eyebrow' => 'Đồng hành cùng thực tập sinh',
        'title' => 'Đánh giá năng lực',
        'description' => 'Gửi phản hồi cụ thể, công bằng và có ích cho bước phát triển tiếp theo.',
        'metrics' => [['label' => 'Chờ đánh giá', 'value' => '03', 'note' => 'Hoàn thành trước 05/09'], ['label' => 'Đã gửi', 'value' => '09', 'note' => 'Trong học kỳ này'], ['label' => 'Đánh giá cuối kỳ', 'value' => '02', 'note' => 'Sắp đến hạn']],
        'columns' => ['Thực tập sinh', 'Vị trí', 'Mốc đánh giá', 'Trạng thái'],
        'rows' => [['Quang Minh', 'Frontend Intern', 'Giữa kỳ', 'Cần hoàn thành'], ['Minh Anh Nguyễn', 'Product Design Intern', 'Giữa kỳ', 'Đã gửi'], ['Thảo Nguyên', 'Content Intern', 'Cuối kỳ', 'Sắp đến hạn']],
    ],

    // [Doanh nghiệp] Danh sách thực tập sinh.
    'company/inters' => [
        'role' => 'company',
        'active' => 'interns',
        'kind' => 'table',
        'eyebrow' => 'Đội ngũ của bạn',
        'title' => 'Thực tập sinh',
        'description' => 'Theo dõi tiến độ, người hướng dẫn và tình trạng của từng kỳ thực tập.',
        'metrics' => [['label' => 'Đang thực tập', 'value' => '12', 'note' => 'Ở 4 nhóm chuyên môn'], ['label' => 'Kết thúc tháng này', 'value' => '03', 'note' => 'Cần chuẩn bị đánh giá'], ['label' => 'Tiến độ ổn định', 'value' => '10', 'note' => '83% tổng số thực tập sinh']],
        'columns' => ['Thực tập sinh', 'Vị trí', 'Người hướng dẫn', 'Tiến độ'],
        'rows' => [['Minh Anh Nguyễn', 'Product Design', 'Linh Trần', '68%'], ['Quang Minh', 'Frontend', 'Tuấn Phạm', '74%'], ['Thảo Nguyên', 'Content', 'Mai Lê', '91%'], ['Gia Hân', 'Product Design', 'Linh Trần', '12%']],
    ],

    // [Doanh nghiệp] Xác nhận kỹ năng AI đề xuất từ nhật ký tuần.
    'company/skills' => [
        'role' => 'company',
        'active' => 'skills',
        'kind' => 'table',
        'eyebrow' => 'Xác nhận kỹ năng',
        'title' => 'Kỹ năng từ nhật ký tuần',
        'description' => 'AI đọc nhật ký của thực tập sinh và đề xuất kỹ năng đã thực sự dùng. Bạn xác nhận, sửa hoặc bác bỏ từng dòng trước khi vào hồ sơ.',
        'metrics' => [['label' => 'Chờ xác nhận', 'value' => '0', 'note' => 'Kỹ năng AI đề xuất'], ['label' => 'Đã xác nhận', 'value' => '0', 'note' => 'Đã vào hồ sơ sinh viên'], ['label' => 'Nhật ký chưa phân tích', 'value' => '0', 'note' => 'Sẵn sàng cho AI']],
        'columns' => ['Thực tập sinh', 'Tuần', 'Ngày nhật ký', 'Kỹ năng đề xuất'],
        'rows' => [],
    ],

    // [Doanh nghiệp] Quản lý vị trí tuyển dụng.
    'company/positions' => [
        'role' => 'company',
        'active' => 'positions',
        'kind' => 'cards',
        'eyebrow' => 'Tuyển đúng người',
        'title' => 'Vị trí thực tập',
        'description' => 'Quản lý tin tuyển dụng và theo dõi chất lượng hồ sơ theo từng vị trí.',
        'metrics' => [['label' => 'Đang mở', 'value' => '04', 'note' => '18 hồ sơ đang chờ'], ['label' => 'Bản nháp', 'value' => '02', 'note' => 'Chưa hiển thị với sinh viên'], ['label' => 'Đã đóng', 'value' => '07', 'note' => 'Từ đầu năm học']],
        'cards' => [['title' => 'Product Design Intern', 'meta' => 'TP. Hồ Chí Minh · Hybrid · 2 vị trí', 'description' => '18 hồ sơ · Hạn nhận 15/09/2026', 'tag' => 'Đang tuyển'], ['title' => 'Frontend Developer Intern', 'meta' => 'TP. Hồ Chí Minh · Tại văn phòng · 1 vị trí', 'description' => '11 hồ sơ · Hạn nhận 20/09/2026', 'tag' => 'Đang tuyển'], ['title' => 'Content Intern', 'meta' => 'Remote · 1 vị trí', 'description' => '8 hồ sơ · Hạn nhận 30/09/2026', 'tag' => 'Đang tuyển']],
    ],

    // [Giảng viên] Hồ sơ cá nhân.
    'lecturer/profile' => [
        'role' => 'lecturer',
        'active' => 'profile',
        'kind' => 'profile',
        'eyebrow' => 'Hồ sơ cá nhân',
        'title' => 'Giảng viên',
        'description' => '',
        'fields' => [],
    ],

    // [Quản trị] Hồ sơ cá nhân.
    'admin/profile' => [
        'role' => 'admin',
        'active' => 'profile',
        'kind' => 'profile',
        'eyebrow' => 'Hồ sơ cá nhân',
        'title' => 'Quản trị viên',
        'description' => '',
        'fields' => [],
    ],

    // [Doanh nghiệp] Hồ sơ doanh nghiệp.
    'company/profile' => [
        'role' => 'company',
        'active' => 'profile',
        'kind' => 'profile',
        'eyebrow' => 'Thông tin doanh nghiệp',
        'title' => 'Northstar Studio',
        'description' => 'Thiết kế sản phẩm số lấy con người làm trung tâm · Hồ sơ doanh nghiệp đã xác minh.',
        'fields' => [['label' => 'Mã doanh nghiệp', 'value' => '0100000001'], ['label' => 'Website', 'value' => 'northstar.studio'], ['label' => 'Email liên hệ', 'value' => 'people@northstar.studio'], ['label' => 'Điện thoại', 'value' => '+84 28 3822 0188'], ['label' => 'Địa chỉ', 'value' => '18 Nguyễn Thị Minh Khai, Quận 1, TP. Hồ Chí Minh'], ['label' => 'Giới thiệu', 'value' => 'Studio sản phẩm độc lập, đồng hành cùng doanh nghiệp xây dựng trải nghiệm số có ý nghĩa.']],
    ],

    // [Doanh nghiệp] Bảng nhiệm vụ đã giao cho thực tập sinh.
    'company/tasks' => [
        'role' => 'company',
        'active' => 'tasks',
        'kind' => 'kanban',
        'eyebrow' => 'Hướng dẫn công việc',
        'title' => 'Nhiệm vụ thực tập',
        'description' => 'Giao việc rõ mục tiêu, người phụ trách và hạn hoàn thành cho thực tập sinh.',
        'boards' => [['title' => 'Chưa bắt đầu', 'items' => [['title' => 'Chuẩn bị tài liệu design QA', 'meta' => 'Minh Anh · Hạn 05/09'], ['title' => 'Viết nội dung empty state', 'meta' => 'Thảo Nguyên · Hạn 07/09']]], ['title' => 'Đang thực hiện', 'items' => [['title' => 'Luồng onboarding phiên bản 2', 'meta' => 'Minh Anh · Hạn 02/09'], ['title' => 'Tối ưu trang danh mục', 'meta' => 'Quang Minh · Hạn 04/09']]], ['title' => 'Chờ phản hồi', 'items' => [['title' => 'Audit accessibility', 'meta' => 'Gia Hân · Gửi hôm qua']]]],
    ],

    // [Giảng viên] Tổng quan.
    'lecturer/dashboard' => [
        'role' => 'lecturer',
        'active' => 'dashboard',
        'kind' => 'dashboard',
        'eyebrow' => 'Cố vấn học tập',
        'title' => 'Xin chào, cô Hà',
        'description' => 'Một góc nhìn nhanh về tiến độ và những sinh viên đang cần được hỗ trợ.',
        'metrics' => [['label' => 'Sinh viên phụ trách', 'value' => '32', 'note' => '3 lớp chuyên ngành'], ['label' => 'Nhật ký chờ duyệt', 'value' => '11', 'note' => '5 mục đã quá 2 ngày'], ['label' => 'Báo cáo cần đọc', 'value' => '04', 'note' => 'Có 1 báo cáo cuối kỳ']],
        'progress' => ['label' => 'Sinh viên cập nhật tuần này', 'value' => '81%', 'note' => '26/32 sinh viên đã gửi nhật ký'],
        'bars' => [38, 48, 63, 55, 76, 82, 81],
        'activities' => [['time' => 'Cần xử lý', 'title' => 'Nhật ký của Trần Gia Hân', 'description' => 'Đã chờ duyệt 3 ngày · kỳ thực tập tại Mây Creative.', 'status' => 'Ưu tiên'], ['time' => 'Hôm nay', 'title' => 'Báo cáo giữa kỳ từ Minh Anh', 'description' => 'Northstar Studio · nộp lúc 08:42.', 'status' => 'Mới nộp'], ['time' => 'Ngày mai', 'title' => 'Lịch trao đổi với nhóm UXD22A', 'description' => '14:00 · Phòng B.302.', 'status' => 'Lịch hẹn']],
    ],

    // [Giảng viên] Nhật ký cần duyệt.
    'lecturer/diaries' => [
        'role' => 'lecturer',
        'active' => 'diaries',
        'kind' => 'table',
        'eyebrow' => 'Theo sát quá trình',
        'title' => 'Duyệt nhật ký thực tập',
        'description' => 'Nhật ký mới nhất được ưu tiên để phản hồi kịp thời và giữ nhịp học tập.',
        'metrics' => [['label' => 'Chờ duyệt', 'value' => '11', 'note' => '5 mục đã quá 2 ngày'], ['label' => 'Đã duyệt tuần này', 'value' => '48', 'note' => 'Từ 32 sinh viên'], ['label' => 'Cần trao đổi', 'value' => '02', 'note' => 'Có ghi chú cần hỗ trợ']],
        'columns' => ['Sinh viên', 'Nội dung', 'Ngày ghi', 'Trạng thái'],
        'rows' => [['Trần Gia Hân', 'Tổng hợp kiểm thử usability', '29/08/2026', 'Chờ duyệt'], ['Minh Anh Nguyễn', 'Rà soát prototype onboarding', '28/08/2026', 'Chờ duyệt'], ['Phạm Quốc Bảo', 'Tối ưu component form', '28/08/2026', 'Đã duyệt'], ['Lê Ngọc Vy', 'Phân tích hành trình người dùng', '27/08/2026', 'Cần trao đổi']],
    ],

    // [Giảng viên] Đánh giá sinh viên.
    'lecturer/evaluations' => [
        'role' => 'lecturer',
        'active' => 'evaluations',
        'kind' => 'table',
        'eyebrow' => 'Ghi nhận sự tiến bộ',
        'title' => 'Đánh giá sinh viên',
        'description' => 'Tổng hợp đánh giá định kỳ và hoàn thiện nhận xét học thuật cho từng kỳ thực tập.',
        'metrics' => [['label' => 'Chưa hoàn tất', 'value' => '07', 'note' => 'Hạn trong 14 ngày'], ['label' => 'Đã hoàn tất', 'value' => '19', 'note' => 'Học kỳ hiện tại'], ['label' => 'Điểm trung bình', 'value' => '84.6', 'note' => 'Trên thang điểm 100']],
        'columns' => ['Sinh viên', 'Doanh nghiệp', 'Mốc', 'Trạng thái'],
        'rows' => [['Quang Minh', 'Northstar Studio', 'Giữa kỳ', 'Chưa đánh giá'], ['Minh Anh Nguyễn', 'Northstar Studio', 'Giữa kỳ', 'Đã gửi'], ['Thảo Nguyên', 'Lá House', 'Cuối kỳ', 'Cần hoàn tất'], ['Gia Hân', 'Mây Creative', 'Giữa kỳ', 'Đã gửi']],
    ],

    // [Giảng viên] Tiến độ và cảnh báo rủi ro từng sinh viên.
    'lecturer/progress' => [
        'role' => 'lecturer',
        'active' => 'progress',
        'kind' => 'progress',
        'eyebrow' => 'Tín hiệu học tập',
        'title' => 'Tiến độ thực tập',
        'description' => 'Nhận diện sớm trường hợp chậm nhật ký, trễ báo cáo hoặc cần thêm hỗ trợ.',
        'members' => [['name' => 'Minh Anh Nguyễn', 'detail' => 'Northstar Studio · Cập nhật hôm qua', 'progress' => 68, 'status' => 'Đúng tiến độ'], ['name' => 'Quang Minh', 'detail' => 'Northstar Studio · Cập nhật 4 ngày trước', 'progress' => 74, 'status' => 'Cần theo dõi'], ['name' => 'Thảo Nguyên', 'detail' => 'Lá House · Cập nhật hôm nay', 'progress' => 91, 'status' => 'Đúng tiến độ'], ['name' => 'Trần Gia Hân', 'detail' => 'Mây Creative · Chưa gửi nhật ký tuần', 'progress' => 38, 'status' => 'Cần hỗ trợ']],
    ],

    // [Giảng viên] Báo cáo của sinh viên.
    'lecturer/reports' => [
        'role' => 'lecturer',
        'active' => 'reports',
        'kind' => 'reports',
        'eyebrow' => 'Học thuật',
        'title' => 'Báo cáo sinh viên',
        'description' => 'Đọc, phản hồi và quản lý các mốc đề cương, giữa kỳ, cuối kỳ.',
        'cards' => [['title' => 'Báo cáo giữa kỳ · Minh Anh Nguyễn', 'meta' => 'Northstar Studio · Nộp 29/08/2026', 'description' => 'Tệp PDF · 2.4 MB · Chưa có nhận xét', 'tag' => 'Cần xem'], ['title' => 'Đề cương · Trần Gia Hân', 'meta' => 'Mây Creative · Nộp 28/08/2026', 'description' => 'Đã duyệt · 30/08/2026', 'tag' => 'Đã duyệt'], ['title' => 'Báo cáo giữa kỳ · Phạm Quốc Bảo', 'meta' => 'Mộc Lab · Nộp 25/08/2026', 'description' => 'Có phản hồi mới từ doanh nghiệp.', 'tag' => 'Đang xem']],
    ],

    // [Giảng viên] Sinh viên phụ trách.
    'lecturer/students' => [
        'role' => 'lecturer',
        'active' => 'students',
        'kind' => 'table',
        'eyebrow' => 'Lớp cố vấn',
        'title' => 'Sinh viên của tôi',
        'description' => 'Tìm hồ sơ, doanh nghiệp tiếp nhận và tình trạng thực tập của sinh viên.',
        'metrics' => [['label' => 'Tổng sinh viên', 'value' => '32', 'note' => '3 lớp chuyên ngành'], ['label' => 'Đang thực tập', 'value' => '27', 'note' => '5 chưa bắt đầu'], ['label' => 'Cần hỗ trợ', 'value' => '02', 'note' => 'Theo tín hiệu tuần']],
        'columns' => ['Sinh viên', 'Lớp', 'Doanh nghiệp', 'Tiến độ'],
        'rows' => [['Minh Anh Nguyễn', 'UXD22A', 'Northstar Studio', '68%'], ['Quang Minh', 'UXD22A', 'Northstar Studio', '74%'], ['Thảo Nguyên', 'UXD22B', 'Lá House', '91%'], ['Trần Gia Hân', 'UXD22C', 'Mây Creative', '38%']],
    ],

    // [Quản trị] Tổng quan hệ thống.
    'admin/dashboard' => [
        'role' => 'admin',
        'active' => 'dashboard',
        'kind' => 'dashboard',
        'eyebrow' => 'Điều hành InternTrack',
        'title' => 'Tổng quan hệ thống',
        'description' => 'Theo dõi quy mô, hoạt động và các mục cần xử lý trên toàn bộ chương trình thực tập.',
        'metrics' => [['label' => 'Sinh viên hoạt động', 'value' => '1,248', 'note' => '+12% so với học kỳ trước'], ['label' => 'Doanh nghiệp đối tác', 'value' => '86', 'note' => '7 hồ sơ chờ xác minh'], ['label' => 'Kỳ thực tập đang chạy', 'value' => '412', 'note' => 'Tại 64 doanh nghiệp']],
        'progress' => ['label' => 'Hoàn thành cập nhật tuần', 'value' => '78%', 'note' => '974/1.248 sinh viên có nhật ký'],
        'bars' => [42, 51, 59, 62, 74, 69, 78],
        'activities' => [['time' => 'Cần xử lý', 'title' => '7 doanh nghiệp chờ xác minh', 'description' => 'Hồ sơ đăng ký mới trong 5 ngày gần nhất.', 'status' => 'Quản trị'], ['time' => 'Hôm nay', 'title' => '42 vị trí thực tập đang mở', 'description' => '18 vị trí sẽ đóng nhận hồ sơ trong tháng 9.', 'status' => 'Tuyển dụng'], ['time' => 'Hôm nay', 'title' => 'Tỷ lệ cập nhật nhật ký đạt 78%', 'description' => 'Tăng 6 điểm phần trăm so với tuần trước.', 'status' => 'Hoạt động']],
    ],

    // [Quản trị] Doanh nghiệp và trạng thái xác minh.
    'admin/companies' => [
        'role' => 'admin',
        'active' => 'companies',
        'kind' => 'table',
        'eyebrow' => 'Đối tác thực tập',
        'title' => 'Doanh nghiệp',
        'description' => 'Xác minh thông tin đối tác và quản lý trạng thái tham gia chương trình.',
        'metrics' => [['label' => 'Đang hoạt động', 'value' => '86', 'note' => '64 có thực tập sinh'], ['label' => 'Chờ xác minh', 'value' => '07', 'note' => 'Hồ sơ mới nhất 2 giờ trước'], ['label' => 'Tạm ngưng', 'value' => '03', 'note' => 'Cần rà soát lại']],
        'columns' => ['Doanh nghiệp', 'Mã số', 'Vị trí mở', 'Trạng thái'],
        'rows' => [['Northstar Studio', '0100000001', '04', 'Hoạt động'], ['Mộc Lab', '0100000002', '02', 'Hoạt động'], ['Mây Creative', '0100000003', '03', 'Chờ xác minh'], ['Lá House', '0100000004', '01', 'Hoạt động']],
    ],

    // [Quản trị] Kỳ thực tập của mọi sinh viên và kế hoạch chung theo ngành.
    'admin/internship' => [
        'role' => 'admin',
        'active' => 'internships',
        'kind' => 'table',
        'eyebrow' => 'Toàn trường',
        'title' => 'Kỳ thực tập',
        'description' => 'Giám sát kỳ thực tập, tình trạng phân công giảng viên và các mốc quan trọng.',
        'metrics' => [['label' => 'Đang diễn ra', 'value' => '412', 'note' => '64 doanh nghiệp'], ['label' => 'Sắp bắt đầu', 'value' => '128', 'note' => 'Trong 30 ngày tới'], ['label' => 'Cần phân công', 'value' => '09', 'note' => 'Chưa có giảng viên']],
        'columns' => ['Sinh viên', 'Ngành', 'Vị trí', 'Giảng viên', 'Trạng thái'],
        'rows' => [['Minh Anh Nguyễn', 'Thiết kế', 'Product Design Intern', 'Nguyễn Hà', 'Đang diễn ra'], ['Quang Minh', 'Công nghệ thông tin', 'Frontend Intern', 'Trần Duy', 'Đang diễn ra'], ['Thảo Nguyên', 'Truyền thông', 'Content Intern', 'Chưa phân công', 'Sắp bắt đầu'], ['Gia Hân', 'Thiết kế', 'UX Research Intern', 'Lê Phương', 'Đang diễn ra']],
    ],

    // [Quản trị] Vị trí thực tập của mọi doanh nghiệp.
    'admin/positions' => [
        'role' => 'admin',
        'active' => 'positions',
        'kind' => 'table',
        'eyebrow' => 'Cơ hội nghề nghiệp',
        'title' => 'Vị trí thực tập',
        'description' => 'Kiểm duyệt tin tuyển dụng và giữ thông tin cơ hội luôn chính xác, phù hợp.',
        'metrics' => [['label' => 'Đang mở', 'value' => '42', 'note' => 'Tại 28 doanh nghiệp'], ['label' => 'Chờ duyệt', 'value' => '06', 'note' => 'Có 2 tin mới hôm nay'], ['label' => 'Đã đóng', 'value' => '118', 'note' => 'Trong năm học 2025–26']],
        'columns' => ['Vị trí', 'Doanh nghiệp', 'Số lượng', 'Trạng thái'],
        'rows' => [['Product Design Intern', 'Northstar Studio', '02', 'Đang mở'], ['Frontend Developer Intern', 'Northstar Studio', '01', 'Đang mở'], ['UX Research Intern', 'Mây Creative', '01', 'Chờ duyệt'], ['Content Intern', 'Lá House', '01', 'Đang mở']],
    ],

    // [Quản trị] Danh sách sinh viên.
    'admin/students' => [
        'role' => 'admin',
        'active' => 'students',
        'kind' => 'table',
        'eyebrow' => 'Cộng đồng học tập',
        'title' => 'Sinh viên',
        'description' => 'Quản lý tài khoản, tình trạng học tập và thông tin thực tập trên toàn trường.',
        'metrics' => [['label' => 'Tài khoản hoạt động', 'value' => '1,248', 'note' => 'Tăng 134 trong năm nay'], ['label' => 'Đang thực tập', 'value' => '412', 'note' => '33% tổng số sinh viên'], ['label' => 'Chưa có nơi thực tập', 'value' => '57', 'note' => 'Cần hỗ trợ kết nối']],
        'columns' => ['Sinh viên', 'Mã sinh viên', 'Ngành học', 'Trạng thái'],
        'rows' => [['Minh Anh Nguyễn', 'UX22-0418', 'Thiết kế UX', 'Đang thực tập'], ['Quang Minh', 'FE22-0132', 'Kỹ thuật phần mềm', 'Đang thực tập'], ['Thảo Nguyên', 'CT22-0079', 'Truyền thông số', 'Đang thực tập'], ['Gia Hân', 'UX22-0431', 'Thiết kế UX', 'Chờ thực tập']],
    ],

    // [Quản trị] Tài liệu của khoa cho trợ lý hỏi đáp.
    'admin/documents' => [
        'role' => 'admin',
        'active' => 'documents',
        'kind' => 'table',
        'eyebrow' => 'Trợ lý hỏi đáp',
        'title' => 'Tài liệu của khoa',
        'description' => 'Quy định, mốc thời gian và biểu mẫu thực tập. Trợ lý AI dùng các tài liệu này để trả lời sinh viên.',
        'metrics' => [['label' => 'Tài liệu', 'value' => '0', 'note' => 'Đang dùng cho trợ lý'], ['label' => 'Tổng độ dài', 'value' => '0', 'note' => 'Ký tự'], ['label' => 'Trợ lý AI', 'value' => '—', 'note' => '']],
        'columns' => ['Tài liệu', 'Độ dài', 'Cập nhật', 'Trạng thái'],
        'rows' => [],
    ],

    // [Quản trị] Tài khoản hệ thống chia theo nhóm vai trò.
    'admin/users' => [
        'role' => 'admin',
        'active' => 'users',
        'kind' => 'table',
        'eyebrow' => 'Quản trị truy cập',
        'title' => 'Tài khoản hệ thống',
        'description' => 'Quản lý quyền truy cập theo vai trò và đảm bảo tài khoản luôn được cập nhật.',
        'metrics' => [['label' => 'Tổng tài khoản', 'value' => '1,426', 'note' => '4 nhóm vai trò'], ['label' => 'Đang hoạt động', 'value' => '1,408', 'note' => '98.7% tài khoản'], ['label' => 'Bị khóa', 'value' => '03', 'note' => 'Lần đăng nhập bất thường']],
        'columns' => ['Họ tên', 'Email', 'Vai trò', 'Trạng thái'],
        'rows' => [['Minh Anh Nguyễn', 'minhanh.nguyen@sv.uah.edu.vn', 'Sinh viên', 'Hoạt động'], ['Nguyễn Hà', 'ha.nguyen@uah.edu.vn', 'Giảng viên', 'Hoạt động'], ['Linh Trần', 'linh@northstar.studio', 'Doanh nghiệp', 'Hoạt động'], ['Hoàng Nam', 'nam.hoang@uah.edu.vn', 'Quản trị viên', 'Hoạt động']],
    ],
];
