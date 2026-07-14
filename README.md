# Auto FB Content Planner

## Mục tiêu dự án

Xây dựng web app bằng **Laravel + Inertia React + Tailwind** để:

1. Tìm ảnh/video từ Pexels bằng API chính thức.
2. Tạo caption theo preset/ngôn ngữ.
3. Lưu bài vào hàng đợi đăng Facebook Page.
4. Duyệt bài trước khi đăng.
5. Đăng lên Facebook Page qua Meta Graph API (Phase 2).
6. Đăng video lên Facebook Page qua Meta Graph Video API (Phase 2.1).
7. Về sau tích hợp Gemini để chấm điểm bài viết, phân tích Page, gợi ý tối ưu nội dung.

## Stack

- **Backend:** Laravel 12 + Inertia.js
- **Frontend:** React + Tailwind CSS
- **Database:** SQLite (local) / Workspace default
- **HTTP Client:** Laravel HTTP Client
- **Scheduler:** Laravel Console Commands
- **Facebook API:** Meta Graph API v25.0

## Hướng dẫn cài đặt (Windows + Laragon)

### Yêu cầu
- Laragon (PHP 8.2+, Composer)
- Node.js (v18+)
- PHP SQLite extension enabled (`php.ini`: bỏ comment `extension=pdo_sqlite` và `extension=sqlite3`)

### Cài đặt
```bash
composer install
cp .env.example .env
php artisan key:generate
# Tạo database/database.sqlite nếu chưa có
php artisan migrate
npm install
npm run build
php artisan serve
```

## Facebook Publishing (Phase 2 & Phase 2.1)

### Cấu hình

Có 2 cách cấu hình:

**Cách 1: Qua .env**
```
META_GRAPH_VERSION=v25.0
FACEBOOK_PAGE_ID=your_page_id
FACEBOOK_PAGE_ACCESS_TOKEN=your_page_access_token
FACEBOOK_PUBLISH_MODE=fake
FACEBOOK_VIDEO_UPLOAD_MODE=remote_url
FACEBOOK_VIDEO_MAX_MB=100
```

**Cách 2: Qua Settings UI**
1. Mở http://127.0.0.1:8000/settings
2. Nhập Page ID, Access Token, Graph Version
3. Chọn Publish Mode (fake/real)
4. Chọn Video Upload Mode (remote_url / local_download)
5. Thiết lập Video Max MB (mặc định 100)
6. Bấm "Save All Settings"

### Fake Mode vs Real Mode

| | Fake Mode | Real Mode |
|---|---|---|
| **Env** | `FACEBOOK_PUBLISH_MODE=fake` | `FACEBOOK_PUBLISH_MODE=real` |
| **Hành vi** | Đổi status thành `published_fake` và tạo fake facebook_post_id | Gọi Facebook Graph API thật |
| **API call** | Không | Có |
| **Yêu cầu token** | Không | Có |
| **An toàn** | Hoàn toàn an toàn | Bài sẽ đăng thật lên Page |

### Hướng dẫn sử dụng

#### 1. Bắt đầu với Fake Mode (khuyến nghị)
```
FACEBOOK_PUBLISH_MODE=fake
```
Fake mode cho phép test toàn bộ workflow đăng text, photo, video mà không cần Facebook token thật.

#### 2. Khi muốn đăng thật
1. Lấy Page Access Token từ Meta Developer Portal
2. Nhập vào Settings hoặc .env
3. Đổi `FACEBOOK_PUBLISH_MODE=real`
4. Bấm "Validate Facebook Config" trong Settings để kiểm tra thông tin Page
5. Approve các bài muốn đăng trong Queue
6. Bấm "Publish Now" hoặc chạy command để tự động đăng lên Page

#### 3. Validate Config
Bấm "🔍 Validate Facebook Config" trong Settings sẽ:
- Gọi GET /{page_id}?fields=id,name,link
- Hiện tên Page và link nếu thành công
- Hiện lỗi rõ ràng nếu token sai/hết hạn

### Publish Methods

| Media Type | API Endpoint | Phase |
|---|---|---|
| Text only | POST /{page_id}/feed | ✅ Phase 2 |
| Photo | POST /{page_id}/photos | ✅ Phase 2 |
| Video | POST /{page_id}/videos | ✅ Phase 2.1 |

*Lưu ý:* `remote_url` upload mode yêu cầu Facebook server phải kéo được video trực tiếp từ Pexels URL. Chức năng `local_download` multipart upload là placeholder cho Phase 2.2.

### Console Commands

```bash
# Fake/Real publish approved posts đến giờ (phân loại text/photo/video trên console output)
php artisan posts:publish-due

# Tạo draft posts tự động cho các topics active
php artisan posts:generate-daily
```

### Publish Logs

Mọi lần publish (fake/real) đều được log vào bảng `post_publish_logs`:
- mode (fake/real)
- action (publish_text/publish_photo/publish_video/validate_config)
- status (success/failed)
- error_message
- request_summary (không chứa token)
- response_json (không chứa token)

## Security

- ❌ Không log access token
- ❌ Không trả token về frontend sau khi lưu (masked với •)
- ❌ Không commit token vào git
- ✅ Secret settings có `is_secret=true`
- ✅ Publish logs không chứa token

## Chạy Test

```bash
php artisan test
```

## Phase hiện tại: Phase 2.1

- ✅ Dashboard với thống kê (draft/approved/published/published_fake/failed count)
- ✅ Topics CRUD + toggle active
- ✅ Pexels Search (tối ưu hóa chọn video MP4 dưới 1080p, có duration badge, thumbnail cho video)
- ✅ Queue management (approve/unapprove/edit/delete)
- ✅ **Publish Now** cho bài approved (hỗ trợ xác nhận modal chi tiết cho video)
- ✅ **Fake/Real publish mode**
- ✅ **Facebook Graph API**: text post + photo post + **video post**
- ✅ **Validate Facebook Config**
- ✅ **Publish logs** (post_publish_logs)
- ✅ Settings page với đầy đủ Facebook Publishing và Video configs
- ✅ Console commands (generate-daily, publish-due)
- ⏳ Video local multipart upload (Phase 2.2 placeholder)
- ⏳ Reels publishing skeleton (FacebookReelsService)
- ❌ Gemini AI (Phase 4)
- ❌ Browser automation (không bao giờ)
