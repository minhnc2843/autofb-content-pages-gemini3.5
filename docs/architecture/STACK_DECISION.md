# Stack Decision

## Chọn: Laravel + Inertia React

### Lý do

- Laravel mạnh về backend, scheduler, queue, migration.
- React cho trải nghiệm dashboard tốt.
- Inertia giúp dùng React mà không cần tách API frontend/backend quá sớm.
- Phù hợp Windows + Laragon.
- Dễ bảo mật API key/token ở backend.

## Không chọn Blade thuần

Blade dễ làm nhưng trải nghiệm UI không mượt bằng React.

## Không chọn browser extension làm bản chính

Extension dễ lộ token/API key và không phù hợp chạy lịch đăng ổn định.
