# Decisions

## D001 — Chọn Laravel + Inertia React

Lý do:
- Backend mạnh.
- React UI tốt.
- Ít phức tạp hơn tách API + SPA ngay từ đầu.
- Phù hợp Laragon/Windows.

## D002 — Không dùng extension làm bản chính

Lý do:
- Dễ lộ API key/token.
- Khó chạy lịch đăng ổn định.
- Dễ lệch sang automation giao diện Facebook.

## D003 — Facebook đăng qua Pages API

Lý do:
- Chính thức hơn.
- Ổn định hơn thao tác giao diện.
- Dễ quản lý log/error/id bài đăng.

## D004 — Gemini để phase sau

Lý do:
- Tránh phình scope.
- Giảm bug.
- Giảm chi phí API/token.
- Lõi queue/phần đăng phải ổn trước.
