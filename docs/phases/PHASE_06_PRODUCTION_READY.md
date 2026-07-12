# Phase 6 — Production Ready

## Mục tiêu

- Chuẩn hóa env.
- Scheduler production.
- Log/backup.
- Token renewal reminder.
- Security review.
- Deployment notes.

## Không làm trong phase này

- Không mở rộng scope ngoài danh sách mục tiêu.
- Không làm phần đăng thật nếu chưa đến Phase 3.
- Không gọi Gemini thật nếu chưa đến Phase 4.
- Không dùng browser automation.

## Acceptance Criteria

- [ ] Deploy được lên VPS/hosting phù hợp.
- [ ] Scheduler chạy ổn.
- [ ] Log lỗi đọc được.
- [ ] Token/API key không lộ.
- [ ] Backup DB có hướng dẫn.

## Test cần có

- Unit test cho service chính của phase.
- Feature test cho route/controller chính.
- Manual test checklist được cập nhật trong `docs/testing/MANUAL_TEST_CHECKLIST.md`.

## Kết thúc phase cần cập nhật

- `DEVELOPMENT_HISTORY.md`
- `.ai/memory/PROJECT_MEMORY.md`
- `.ai/memory/PHASE_STATUS.md`
- `CHANGELOG.md`
