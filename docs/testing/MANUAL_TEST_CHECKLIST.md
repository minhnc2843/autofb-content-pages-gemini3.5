# Manual Test Checklist

## Setup

- [ ] `composer install`
- [ ] `npm install`
- [ ] `copy .env.example .env`
- [ ] `php artisan key:generate`
- [ ] `php artisan migrate`
- [ ] `npm run dev`
- [ ] `php artisan serve`

## Phase 1

- [ ] Mở được dashboard.
- [ ] Tạo topic mới.
- [ ] Sửa topic.
- [ ] Xóa topic.
- [ ] Lưu settings.
- [ ] Queue page hiển thị.

## Phase 2

- [ ] Nhập PEXELS_API_KEY.
- [ ] Search photo.
- [ ] Search video.
- [ ] Create draft từ media.
- [ ] Sửa caption.
- [ ] Approve post.
- [ ] Chạy `php artisan posts:generate-daily`.
- [ ] Chạy `php artisan posts:publish-due`.
