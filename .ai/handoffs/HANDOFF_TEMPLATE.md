# AI Handoff Template

Dùng mẫu này khi chuyển sang AI agent khác hoặc mở chat mới.

```md
Tôi đang build dự án Auto FB Content Planner.

Stack:
- Laravel + Inertia React + Tailwind
- SQLite local
- Pexels API phase 2
- Facebook Pages API phase 3
- Gemini phase 4

Bạn cần đọc trước:
1. .ai/memory/PROJECT_MEMORY.md
2. .ai/memory/PHASE_STATUS.md
3. docs/phases/PHASE_XX_*.md
4. .ai/prompts/PHASE_XX_PROMPT.md

Phase hiện tại:
- Phase XX: ...

Yêu cầu:
- Chỉ làm đúng phase này.
- Không mở rộng scope.
- Không gọi API thật nếu phase chưa cho phép.
- Sau khi xong, cập nhật DEVELOPMENT_HISTORY.md và .ai/memory/PROJECT_MEMORY.md.
- Chạy test và báo kết quả.

Môi trường:
- Windows + Laragon
- Chạy lệnh trong CMD tại C:\laragon\wwwuto-fb-content-planner
```
