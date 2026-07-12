# Test Strategy

## Nguyên tắc

- Mỗi phase phải có test tương ứng.
- Test không phụ thuộc API ngoài nếu không cần.
- API ngoài phải mock/fake trong test.
- Chỉ gọi API thật khi manual test.

## Test layers

### Unit Tests

Dùng cho:
- CaptionService
- Pexels response mapping
- PostSchedulerService
- Gemini prompt builder
- Facebook payload builder

### Feature Tests

Dùng cho:
- Topics CRUD
- Settings page
- Pexels search request
- Create draft post
- Queue approve/unapprove
- Commands generate/publish

## Commands

```bat
cd C:\laragon\wwwuto-fb-content-planner
php artisan test
npm run build
```
