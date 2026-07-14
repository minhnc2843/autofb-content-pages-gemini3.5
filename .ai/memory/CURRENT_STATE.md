# Current State — Auto FB Content Planner

## Phase Hiện Tại

Dự án đã **HOÀN THÀNH TOÀN BỘ CÁC PHASE THEO BẢN THIẾT KẾ BAN ĐẦU** (2026-07-14).

## Trạng thái kiểm tra

- **php artisan test**: ✅ 142 tests passed, 0 failed (100% assertions).
- **npm run build**: ✅ Compilation succeeded in 5.42s (all assets built cleanly).
- **Môi trường**: Laragon, PHP 8.2+, SQLite.

## Chức năng đã xong

- Tự động nén và tối ưu hóa URL ảnh từ Pexels (`w=1600` cho ảnh chính, `w=600` cho ảnh thumbnail) để tránh lỗi dung lượng lớn (Code 1) trên Facebook API.
- Cơ chế auto-retry nén ảnh bổ sung (`w=1200`) nếu lần publish đầu tiên thất bại với mã lỗi 1.
- Command tối ưu hóa URL ảnh hàng loạt `media:optimize-pexels-urls` cho toàn bộ media hiện tại trong database.
- Bổ sung lệnh chẩn đoán chi tiết một post queue cụ thể `posts:debug-publish {postId}` hỗ trợ kiểm tra tính khả dụng của media URL và validate credentials.
- Hiển thị lỗi Facebook API chi tiết (message, code, subcode, type) trên giao diện edit của post cùng timeline 5 logs publish gần nhất.

- Sửa lỗi trắng trang ở Queue bằng cách làm sạch pagination/posts check, loại bỏ loop filter useEffect và bổ sung note cảnh báo trạng thái Draft/Approved.
- Tự động chuyển hướng sau khi tạo Draft Post từ Pexels sang trang edit để preview (có hỗ trợ HTML5 video player và direct links) và điều chỉnh caption/lịch hẹn.
- Nâng cấp trang edit với đầy đủ các nút bấm hành động (Save Changes, Save & Approve, Approve Now, Unapprove, Publish Now) dựa trên trạng thái của bài viết, đồng thời sửa logic redirection giữ người dùng lại trang edit sau khi cập nhật.
- Đăng ký tự động chạy scheduler cho lệnh `posts:publish-due` mỗi phút trong `routes/console.php`.
- Đăng video lên Facebook Page qua cả 2 chế độ: `remote_url` và `local_download`.
- Hỗ trợ đăng Reels trên Facebook Page qua Reels API.
- Bộ lọc nâng cao (filters) trong Queue và Lịch sử thay đổi trạng thái (status history).
- Thao tác hàng loạt (batch actions) trên Queue: Approve, Unapprove, Reschedule (hỗ trợ modal chọn ngày/giờ), Delete, Retry, Draft.
- Giao diện Lịch đăng bài (Calendar view) hàng tháng hiển thị các bài đăng và cảnh báo ngày thiếu slot đăng bài (kèm theo note cảnh báo Draft/Approved).
- Tự động sinh lịch đăng bài (Auto schedule generator) hỗ trợ 7/14/30 ngày và Cơ chế chống trùng (Duplicate protection: chống trùng ảnh/video trong 30 ngày, trùng slot, trùng caption).
- Nâng cấp Dashboard hiển thị thống kê nâng cao (Coverage score, upcoming posts, failed posts alerts, quick actions).
- Tích hợp Gemini AI tạo 3 caption variants cho bài viết, chấm điểm bài đăng, phân tích Media và Page Audit.
- Lập chiến lược nội dung tự động (Content strategy engine) theo danh mục (educational, spiritual, funny, v.v.).
- Mã hóa thông tin nhạy cảm (secrets) như Page Access Token, Gemini API Key trong database.
