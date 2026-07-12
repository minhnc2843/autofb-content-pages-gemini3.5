# User Flow

## Flow 1 — Tạo bài thủ công

1. Người dùng mở Pexels Search.
2. Nhập keyword.
3. Chọn photo/video/both.
4. App hiển thị media cards.
5. Người dùng chọn media.
6. App tạo caption mẫu.
7. Người dùng sửa caption.
8. Người dùng lưu draft.
9. Người dùng approve bài.
10. Phase sau: app đăng khi đến giờ.

## Flow 2 — Tạo 3 bài/ngày

1. Người dùng tạo topics.
2. Người dùng bật topics active.
3. Command `posts:generate-daily` chạy.
4. App lấy media từ Pexels.
5. App tạo 3 draft theo khung giờ.
6. Người dùng duyệt queue.

## Flow 3 — AI tối ưu sau này

1. App lấy dữ liệu Page/post.
2. Gemini phân tích.
3. App hiển thị score.
4. App đề xuất caption/topic/giờ đăng.
