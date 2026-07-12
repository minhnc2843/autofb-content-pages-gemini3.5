# Phase 2 — Pexels + Queue

## Mục tiêu

- Tạo PexelsService.
- Tìm photo/video/both.
- Cache media_items.
- Tạo draft post từ media.
- CaptionService template local.
- Queue edit/approve/unapprove.
- Command posts:generate-daily.
- Command posts:publish-due fake.

## Không làm trong phase này

- Không mở rộng scope ngoài danh sách mục tiêu.
- Không làm phần đăng thật nếu chưa đến Phase 3.
- Không gọi Gemini thật nếu chưa đến Phase 4.
- Không dùng browser automation.

## Acceptance Criteria

- [ ] Tìm được media khi có PEXELS_API_KEY.
- [ ] Lưu được media_items.
- [ ] Tạo draft post được.
- [ ] Sửa caption được.
- [ ] Approve/unapprove được.
- [ ] posts:generate-daily tạo tối đa 3 draft/ngày.
- [ ] posts:publish-due chỉ đổi status published_fake.
- [ ] Không gọi Facebook thật.

## Test cần có

- Unit test cho service chính của phase.
- Feature test cho route/controller chính.
- Manual test checklist được cập nhật trong `docs/testing/MANUAL_TEST_CHECKLIST.md`.

## Kết thúc phase cần cập nhật

- `DEVELOPMENT_HISTORY.md`
- `.ai/memory/PROJECT_MEMORY.md`
- `.ai/memory/PHASE_STATUS.md`
- `CHANGELOG.md`
