# Current Context For Next AI Session

Bạn đang build dự án Auto FB Content Planner.

## Trạng thái hiện tại

- **Phase 1 đã hoàn thành** (2026-07-11).
- **Phase 2 đã hoàn thành** (2026-07-12).
- App chạy được local tại http://127.0.0.1:8000.
- 49 tests pass 100%.
- npm run build pass.

## Stack đã cài đặt

- Laravel 12.63.0
- Inertia.js 3.1.1
- React 19 + @inertiajs/react
- Tailwind CSS 4
- Vite 7.3.6
- PHPUnit 11
- SQLite

## Chức năng đã hoàn thành

### Phase 1
1. Dashboard: thống kê draft/approved/published/published_fake/failed.
2. Topics: CRUD + toggle active.
3. Pexels Search: tìm photo/video, tạo draft post.
4. Queue: xem/sửa/approve/unapprove/xóa bài.
5. Settings: lưu API keys.
6. Commands: posts:generate-daily, posts:publish-due.
7. Services: PexelsService, CaptionService (5 ngôn ngữ).

### Phase 2
8. FacebookPageService: publishTextPost, publishPhotoPost, validateConfig.
9. Fake/Real publish mode (FACEBOOK_PUBLISH_MODE).
10. Publish Now button cho bài approved.
11. PostPublishLog: log mọi lần publish.
12. Settings: Facebook Publishing section, Validate Config button.
13. Token masking: không expose token ra frontend.
14. 27 tests mới cho Facebook features.

## Phase tiếp theo: Phase 2.1 hoặc Phase 3

- Phase 2.1: Video publishing.
- Phase 3: Xem docs/phases/ để biết chi tiết.

## Khi bắt đầu session mới, hãy đọc

1. `.ai/memory/PROJECT_MEMORY.md`
2. `.ai/memory/PHASE_STATUS.md`
3. Docs phases tương ứng
