# Project Memory — Auto FB Content Planner

## Mục tiêu hiện tại

Xây dựng web app Laravel + Inertia React để tìm ảnh/video từ Pexels, tạo caption, lưu queue, duyệt bài, đăng Facebook Page qua API chính thức, và sau này dùng Gemini để tối ưu nội dung.

## Stack đã quyết định

- Laravel 12 backend
- Inertia React frontend
- Tailwind CSS 4
- SQLite local / MySQL production
- Laravel Scheduler
- Pexels API
- Facebook Pages API / Meta Graph API v25.0
- Gemini API (Phase 4+)

## Quyết định quan trọng

1. Không dùng browser automation.
2. Không thao tác trên giao diện facebook.com.
3. Fake/Real publish mode qua FACEBOOK_PUBLISH_MODE env.
4. Token không bao giờ log hoặc trả về frontend.
5. Secret settings masked với • ở frontend.
6. Video publishing chưa hỗ trợ ở Phase 2 (Phase 2.1).
7. PHP SQLite extension cần enable thủ công trong php.ini (Laragon).
8. Vite plugin-react dùng version ^5 cho Vite 7.

## Phase hiện tại

Phase 2 — Facebook Publishing — **HOÀN THÀNH** (2026-07-12).

## Phase tiếp theo

Phase 2.1 — Video Publishing hoặc Phase 3.

## Những gì đã hoàn thành

### Phase 1
- 5 database tables: topics, media_items, posts_queue, settings, ai_analyses.
- 5 models, 5 services, 5 controllers, 2 commands.
- 7 React pages, 6 components.
- 22 tests passing.

### Phase 2
- PostPublishLog table + model.
- FacebookPageService full implementation.
- Fake/Real publish mode.
- Publish Now button + confirmation modal.
- Validate Facebook Config.
- Token masking in Settings.
- 49 tests passing (27 mới).
