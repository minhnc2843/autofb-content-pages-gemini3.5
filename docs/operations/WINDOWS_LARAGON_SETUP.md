# Windows + Laragon Setup

## Bước 1 — Mở CMD

Nhấn `Win + R`, nhập:

```bat
cmd
```

Bấm Enter.

## Bước 2 — Vào thư mục web của Laragon

```bat
cd C:\laragon\www
```

## Bước 3 — Vào project

```bat
cd auto-fb-content
```

## Bước 4 — Cài dependency

```bat
composer install
npm install
```

## Bước 5 — Env

```bat
copy .env.example .env
php artisan key:generate
```

## Bước 6 — Database SQLite

```bat
type nul > database\database.sqlite
php artisan migrate
```

## Bước 7 — Chạy app

CMD thứ nhất:

```bat
php artisan serve
```

CMD thứ hai:

```bat
npm run dev
```

Mở trình duyệt:

```txt
http://127.0.0.1:8000
```
