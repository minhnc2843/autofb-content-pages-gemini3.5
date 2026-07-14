# Development History

## Phase 0 — Project Foundation (2026-07-11)

- Tạo blueprint tài liệu.
- Tạo .ai/memory, .ai/prompts, docs structure.
- Tạo stub files cho services và commands.
- Tạo .env.example.

## Phase 1 — Core Laravel + Inertia React (2026-07-11)

### Mục tiêu
Build Laravel + Inertia React app chạy được local với đầy đủ chức năng Phase 1.

### Kết quả
- ✅ Laravel 12 initialized.
- ✅ Inertia.js + React + Tailwind CSS configured.
- ✅ 5 database migrations created and run successfully.
- ✅ 5 Eloquent models with relationships.
- ✅ PexelsService, CaptionService, FacebookPageService (stub), GeminiService (stub).
- ✅ 5 Controllers, 2 Console Commands.
- ✅ 7 React pages + 6 React components.
- ✅ 22 tests passing (100%).

## Phase 2 — Facebook Publishing (2026-07-12)

### Mục tiêu
Tích hợp Facebook Pages API để đăng thật lên Facebook Page với cơ chế an toàn.

### Kết quả
- ✅ **FacebookPageService** hoàn thiện:
  - `getGraphBaseUrl()`: Meta Graph API URL (configurable version).
  - `getPageId()` / `getPageAccessToken()`: DB → env fallback.
  - `validateConfig()`: GET /{page_id}?fields=id,name,link.
  - `publishTextPost()`: POST /{page_id}/feed.
  - `publishPhotoPost()`: POST /{page_id}/photos.
  - `publishVideoPost()`: Phase 2 stub (clear error).
  - `publishPost()`: fake/real mode routing.
- ✅ **PostPublishLog** model + migration: log mỗi lần publish.
- ✅ **PostQueue** updated: publishLogs relationship, published scope.
- ✅ **PublishDuePostsCommand** updated: dùng FacebookPageService, chi tiết CLI output.
- ✅ **QueueController** updated: publishNow method.
- ✅ **SettingController** updated: validateFacebook, meta_graph_version, publish_mode, token masking.
- ✅ **Queue UI** updated: Publish Now button, confirmation modal, publish mode indicator, facebook_post_id, error_message.
- ✅ **Settings UI** updated: Facebook Publishing section, validate button, mode selector.
- ✅ **StatusBadge** updated: published status.
- ✅ **Dashboard** updated: published count.
- ✅ **Routes**: POST /queue/{post}/publish-now, POST /settings/facebook/validate.
- ✅ **.env.example** updated.
- ✅ **49 tests passing** (130 assertions).
- ✅ **npm run build** passing.

### Tests thêm mới (27 tests)
- FacebookPageServiceTest: 14 tests
- FacebookSettingsTest: 4 tests
- PublishDuePostsCommandTest: 9 tests

### Security
- Access token không bao giờ log.
- Secret settings masked ở frontend.
- Token không trả về plain text sau save.

### Files tạo mới / sửa
- database/migrations/2024_01_02_000001_create_post_publish_logs_table.php (NEW)
- app/Models/PostPublishLog.php (NEW)
- app/Models/PostQueue.php (UPDATED)
- app/Services/FacebookPageService.php (REWRITTEN)
- app/Console/Commands/PublishDuePostsCommand.php (REWRITTEN)
- app/Http/Controllers/QueueController.php (UPDATED)
- app/Http/Controllers/SettingController.php (UPDATED)
- app/Http/Controllers/DashboardController.php (UPDATED)
- routes/web.php (UPDATED)
- resources/js/Pages/Queue/Index.jsx (REWRITTEN)
- resources/js/Pages/Settings/Index.jsx (REWRITTEN)
- resources/js/Components/StatusBadge.jsx (UPDATED)
- tests/Unit/FacebookPageServiceTest.php (NEW)
- tests/Feature/FacebookSettingsTest.php (NEW)
- tests/Feature/PublishDuePostsCommandTest.php (NEW)
- .env.example (UPDATED)
- README.md (UPDATED)
- CHANGELOG.md (UPDATED)

### Không làm
- Không đăng video thật (Phase 2.1).
- Không gọi Gemini thật (Phase 4).
- Không dùng browser automation.

## Phase 2.1 — Facebook Video Publishing (2026-07-13)

### Mục tiêu
Hoàn thiện chức năng đăng VIDEO lên Facebook Page qua Meta Graph Video API.

### Kết quả
- ✅ **FacebookPageService** được mở rộng hỗ trợ video:
  - `publishVideoPost(PostQueue $post)`: Thực hiện gọi Graph API đăng video qua remote_url.
  - Hỗ trợ custom limits qua `FACEBOOK_VIDEO_MAX_MB`.
  - Tự động kiểm tra file size trước khi upload bằng HTTP HEAD.
  - Xử lý các loại ID trả về của Facebook để tìm post_id phù hợp.
  - Placeholder cho `local_download` mode (ném ra ngoại lệ Phase 2.2).
- ✅ **FacebookReelsService** skeleton: Chuẩn bị cấu trúc cho Reels publishing ở Phase tiếp theo.
- ✅ **Pexels Video Parsing Optimization**: Tự động lọc video MP4 chất lượng cao <= 1080p từ Pexels.
- ✅ **Queue UI**: Badge video, play link, modal confirmation tùy biến cho video.
- ✅ **Settings UI**: Bổ sung thiết lập Video Upload Mode và Max MB.
- ✅ **Database migration**: Cập nhật `posts_queue` với các cột `publish_started_at`, `published_at`, và `publish_attempts`.
- ✅ **PublishDuePostsCommand**: Log thống kê bài đăng (Text, Photo, Video) chi tiết và ghi nhận thời điểm bắt đầu/hoàn thành đăng bài.
- ✅ **65 tests passing** (172 assertions).
- ✅ **npm run build** passing.

### Tests thêm mới (16 tests)
- FacebookPageServiceVideoTest: 10 tests
- PublishDuePostsCommandTest (Video cases): 5 tests
- PexelsServiceTest (video resolution validation): 1 test

### Files tạo mới / sửa
- database/migrations/2024_01_02_000002_add_publish_tracking_to_posts_queue.php (NEW)
- app/Services/FacebookReelsService.php (NEW)
- tests/Unit/FacebookPageServiceVideoTest.php (NEW)
- app/Services/FacebookPageService.php (UPDATED)
- app/Services/PexelsService.php (UPDATED)
- app/Models/PostQueue.php (UPDATED)
- app/Console/Commands/PublishDuePostsCommand.php (UPDATED)
- app/Http/Controllers/QueueController.php (UPDATED)
- app/Http/Controllers/SettingController.php (UPDATED)
- resources/js/Pages/Queue/Index.jsx (UPDATED)
- resources/js/Pages/Settings/Index.jsx (UPDATED)
- resources/js/Components/MediaCard.jsx (UPDATED)
- tests/Feature/PublishDuePostsCommandTest.php (UPDATED)
- tests/Unit/PexelsServiceTest.php (UPDATED)
- tests/Unit/FacebookPageServiceTest.php (UPDATED)
- README.md (UPDATED)
- CHANGELOG.md (UPDATED)

### Không làm
- Local multipart upload (lùi lại Phase 2.2).
- Đăng Reels thật (Phase 2.1 chỉ có skeleton).
- Gemini AI thật (Phase 4).
- Browser automation.

## Acceptance Fix Pack (2026-07-14)

### Mục tiêu
Khắc phục các lỗi chưa đạt nghiệm thu và hoàn thiện MVP an toàn, ổn định: dọn dẹp cấu hình mẫu, mã hóa bảo mật, phân trang hàng đợi, tối ưu bộ lọc, siết chặt các action hàng loạt, nâng cấp cơ chế lên lịch tự động và chống trùng, tắt tự động gọi AI khi chỉ mở trang, và bổ sung test suite chứng minh.

### Kết quả
- ✅ **Bảo mật & Cấu hình**:
  - Dọn cấu hình nhạy cảm trong `.env.example`.
  - Ưu tiên đọc cài đặt từ DB thay vì env.
  - Mã hóa tự động các secret settings (API keys, Tokens).
  - Ngăn không cho ghi đè secrets khi frontend gửi chuỗi masked (`••••••••`).
- ✅ **Phân trang & Lọc hàng đợi**:
  - Bổ sung phân trang Backend (`paginate(20)`) và Frontend cho Queue.
  - Batch selection chỉ chọn các item trên trang hiện tại.
  - Cải tiến filters để khi chọn status/topic là "all" thì bỏ qua điều kiện lọc.
- ✅ **Thao tác hàng loạt an toàn (Safe Batch Actions)**:
  - Siết chặt các điều kiện chuyển trạng thái hàng loạt (ví dụ: chỉ cho phép duyệt bài ở trạng thái `draft` hoặc `failed`).
  - Ghi nhận cột `reason` trong bảng lịch sử thay đổi trạng thái bài viết (`post_status_histories`) để theo dõi vết batch action.
- ✅ **Lên lịch tự động & Chống trùng (Calendar & Scheduler)**:
  - Tối ưu `DuplicateProtectionService` truy vấn trực tiếp DB khoảng giờ thay vì load toàn bộ.
  - Chuẩn hóa caption trước khi so sánh trùng lặp.
  - Hỗ trợ custom options (days, posts_per_day, start_date, media_type, topic_ids) trong `ContentCalendarService`.
  - Hỗ trợ fallback từ Pexels API nếu cache media không đủ.
  - Cảnh báo thiếu slot trong tháng chỉ áp dụng cho ngày hôm nay và tương lai, bỏ qua quá khứ.
- ✅ **Kiểm soát Gemini AI (Gemini Gating)**:
  - Tích hợp cờ `GEMINI_ENABLED` và kiểm tra khóa API để tắt hẳn các cuộc gọi AI không mong muốn.
  - Tách hành động lấy chiến lược tuần (`GET /strategy`) thành chỉ đọc cache/DB, tạo route mới (`POST /strategy/generate`) để người dùng tự kích hoạt sinh chiến lược mới bằng nút bấm.
  - Gating cuộc gọi Gemini trong Caption generator, Page Audit, và Queue analyze.
- ✅ **Bổ sung Suite Test mới**:
  - `SettingsSecurityTest`: Kiểm tra ghi đè secret, ưu tiên DB settings.
  - `QueuePaginationTest`: Kiểm tra phân trang và lọc.
  - `QueueBatchSafetyTest`: Kiểm tra an toàn batch action và vết status history.
  - `CalendarValidationTest`: Kiểm tra fallback tháng/năm, lọc "all", và cảnh báo thiếu slot.
  - `GeminiGatingTest`: Kiểm tra tắt gọi AI tự động và gating các endpoint.
- ✅ **Tất cả các tests passed** (100% assertions).
- ✅ **npm run build** biên dịch thành công.
