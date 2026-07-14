# Current Context For Next AI Session

Bạn đang build dự án Auto FB Content Planner.

## Trạng thái hiện tại

- **Phase 1 đã hoàn thành** (2026-07-11).
- **Phase 2 đã hoàn thành** (2026-07-12).
- **Phase 2.1 đã hoàn thành** (2026-07-13).
- App chạy được local tại http://127.0.0.1:8000.
- 65 tests pass 100%.
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

### Phase 2.1
1. Đăng video thường lên Facebook Page qua Meta Graph Video API (`publishVideoPost`).
2. Tự động kiểm tra dung lượng video trước khi upload bằng HTTP HEAD (`FACEBOOK_VIDEO_MAX_MB`).
3. Lọc chất lượng video MP4 <= 1080p tối ưu từ Pexels.
4. Cập nhật Queue UI với badges video, thời gian video, và play links.
5. Cập nhật Settings UI với Video Upload Mode và Max MB.
6. post_queue bổ sung các cột tracking `publish_started_at`, `published_at`, và `publish_attempts`.
7. FacebookReelsService skeleton cho Reels integration ở Phase tiếp theo.
8. Bổ sung 16 unit và feature tests cho Video publishing.

## Phase tiếp theo: Phase 2.2 hoặc Phase 3

- Phase 2.2: Local video download & multipart upload.
- Phase 3: Advanced features.
- Xem docs/phases/ để biết chi tiết.

## Khi bắt đầu session mới, hãy đọc

1. `.ai/memory/PROJECT_MEMORY.md`
2. `.ai/memory/PHASE_STATUS.md`
3. Docs phases tương ứng
