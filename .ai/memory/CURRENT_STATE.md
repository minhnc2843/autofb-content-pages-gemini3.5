# Current State — Auto FB Content Planner

## Phase Hiện Tại

Dự án đã **HOÀN THÀNH TOÀN BỘ CÁC PHASE THEO BẢN THIẾT KẾ BAN ĐẦU VÀ PHASE 7 (MULTI-PAGE & AI ASSISTANT FOUNDATION)** (2026-07-15).

## Trạng thái kiểm tra

- **php artisan test**: ✅ 158 tests passed, 0 failed (100% assertions).
- **npm run build**: ✅ Compilation succeeded (all React/Vite assets built cleanly).
- **Môi trường**: Laragon, PHP 8.2+, SQLite.

## Chức năng đã xong (Phase 7 - Multi-Page & AI Assistant)

- **Quản lý nhiều Page**: Thêm, sửa, cấu hình chi tiết platform, publish mode (fake/real), access token riêng biệt cho từng page, thay thế cấu hình settings toàn cục (nhưng vẫn hỗ trợ fallback an toàn).
- **Hồ sơ nội dung (Niche Profiles)**: Từng page có hồ sơ riêng biệt cấu hình mô tả, đối tượng mục tiêu, mix ratio (photo/video/text), avoid topics, hashtag policy, posting slot hours, và max posts/day.
- **Preset mẫu có sẵn**: Tích hợp các preset tối ưu hoá cao gồm:
  - *Nature Healing*: calm, peaceful, healing; slots `['07:30', '12:30', '20:30']`; video 70%, photo 30%.
  - *Buddhist Teaching*: respectful, peaceful, reflective; slots `['06:00', '12:30', '20:00']`; photo 60%, video 30%, text 10%.
  - *Animals*: cute, fun, heartwarming; slots `['09:00', '15:00', '21:00']`; video 80%, photo 20%.
- **AI Assistant Chatbot**: Giao diện hội thoại tương tác tự nhiên hỗ trợ người dùng ra lệnh tạo kế hoạch, phân tích page, chẩn đoán config, hoặc thay đổi token.
- **Bảo mật và Che giấu Token (Secret Redaction)**: Tự động dùng regex phát hiện access token trong tin nhắn chat của user, mã hoá lưu vào bảng `pending_secrets`, che giấu token bằng tag `[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]` trước khi lưu chat history và gửi prompt cho Gemini API.
- **Panel Duyệt Việc (Task Confirmation Workflow)**: AI không bao giờ tự ý thực thi các thay đổi cấu hình hoặc xuất bản thực tế. Mọi yêu cầu được tạo dưới dạng Task ở trạng thái `awaiting_confirmation`. Người dùng review kế hoạch ở sidebar bên phải và click **Confirm** để thực thi hoặc **Cancel** để huỷ.
- **Phân tách Publishing & Scheduling**: Nâng cấp `DuePostPublisherService` và command `posts:publish-due` để lọc xuất bản theo `--page=`.

## Các chức năng đã có từ trước (Phases 1-6)

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
