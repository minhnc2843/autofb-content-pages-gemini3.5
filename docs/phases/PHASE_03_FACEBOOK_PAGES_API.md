# Phase 3 — Facebook Pages API

## Mục tiêu

- Tạo FacebookPageService thật.
- Validate page token.
- Publish text/photo/video post.
- Xử lý lỗi token hết hạn.
- Command publish-due đăng thật khi bật flag.
- Log lỗi rõ ràng.

## Không làm trong phase này

- Không mở rộng scope ngoài danh sách mục tiêu.
- Không làm phần đăng thật nếu chưa đến Phase 3.
- Không gọi Gemini thật nếu chưa đến Phase 4.
- Không dùng browser automation.

## Acceptance Criteria

- [ ] Không đăng thật nếu PHASE_FACEBOOK_PUBLISHING_ENABLED=false.
- [ ] Đăng test được lên Page khi flag=true.
- [ ] Lưu facebook_post_id.
- [ ] Lỗi API được lưu vào error_message.
- [ ] Không dùng browser automation.

## Test cần có

- Unit test cho service chính của phase.
- Feature test cho route/controller chính.
- Manual test checklist được cập nhật trong `docs/testing/MANUAL_TEST_CHECKLIST.md`.

## Kết thúc phase cần cập nhật

- `DEVELOPMENT_HISTORY.md`
- `.ai/memory/PROJECT_MEMORY.md`
- `.ai/memory/PHASE_STATUS.md`
- `CHANGELOG.md`
