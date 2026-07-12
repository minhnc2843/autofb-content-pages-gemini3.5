# Scheduler Setup

## Local test

Chạy thủ công:

```bat
php artisan posts:generate-daily
php artisan posts:publish-due
```

## Windows Task Scheduler sau này

Tạo task chạy mỗi phút:

```bat
cd C:\laragon\wwwuto-fb-content-planner && php artisan schedule:run
```
