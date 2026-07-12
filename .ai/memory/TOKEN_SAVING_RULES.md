# Token Saving Rules

## Mục tiêu

Giúp AI agent không phải đọc lại toàn bộ dự án hoặc toàn bộ lịch sử chat.

## Quy tắc 1 — Single Source of Context

Mỗi session mới chỉ cần đọc 4 file:

1. `.ai/memory/PROJECT_MEMORY.md`
2. `.ai/memory/PHASE_STATUS.md`
3. `docs/phases/PHASE_xx_*.md`
4. `.ai/prompts/PHASE_xx_PROMPT.md`

## Quy tắc 2 — Không paste toàn bộ code khi không cần

Khi hỏi AI sửa lỗi:

- Chỉ gửi file lỗi.
- Gửi log lỗi đầy đủ.
- Gửi cấu trúc thư mục liên quan.
- Không gửi toàn bộ project.

## Quy tắc 3 — Sau mỗi phase phải cập nhật memory

Cập nhật:

- Phase đã hoàn thành.
- File đã tạo/sửa.
- Test đã chạy.
- Bug còn lại.
- Quyết định mới.

## Quy tắc 4 — Prompt phase phải giới hạn scope

Mỗi prompt phải có:

- Mục tiêu phase.
- Không làm gì.
- File được phép sửa.
- Acceptance criteria.
- Test cần chạy.
