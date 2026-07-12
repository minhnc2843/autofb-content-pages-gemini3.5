# Tests

## Chạy test

```bat
cd C:\laragon\wwwuto-fb-content-planner
php artisan test
```

## Quy tắc

- Không gọi API thật trong automated tests.
- Dùng fake/mock HTTP.
- Mỗi phase phải có ít nhất test cho phần chính.
