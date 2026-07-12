# Phase 4 — Gemini AI

## Mục tiêu

- Tạo GeminiService thật.
- AI caption generator.
- Post scoring.
- Media analysis.
- AI result cache.
- Queue page có nút AI Score.

## Không làm trong phase này

- Không mở rộng scope ngoài danh sách mục tiêu.
- Không làm phần đăng thật nếu chưa đến Phase 3.
- Không gọi Gemini thật nếu chưa đến Phase 4.
- Không dùng browser automation.

## Acceptance Criteria

- [ ] Không gọi Gemini khi GEMINI_API_KEY trống.
- [ ] AI Score trả JSON chuẩn.
- [ ] Caption AI có preset.
- [ ] Result được lưu ai_analyses.
- [ ] Không gọi Gemini lặp nếu đã có cache hợp lệ.

## Test cần có

- Unit test cho service chính của phase.
- Feature test cho route/controller chính.
- Manual test checklist được cập nhật trong `docs/testing/MANUAL_TEST_CHECKLIST.md`.

## Kết thúc phase cần cập nhật

- `DEVELOPMENT_HISTORY.md`
- `.ai/memory/PROJECT_MEMORY.md`
- `.ai/memory/PHASE_STATUS.md`
- `CHANGELOG.md`
