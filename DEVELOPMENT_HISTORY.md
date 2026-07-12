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
