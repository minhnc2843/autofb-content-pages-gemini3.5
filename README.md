# Auto FB Content Planner

## Mục tiêu dự án

Xây dựng web app bằng **Laravel + Inertia React + Tailwind** để:

1. Tìm ảnh/video từ Pexels bằng API chính thức.
2. Tạo caption theo preset/ngôn ngữ.
3. Lưu bài vào hàng đợi đăng Facebook Page.
4. Duyệt bài trước khi đăng.
5. Đăng lên Facebook Page qua Meta Graph API (Phase 2).
6. Đăng video lên Facebook Page qua Meta Graph Video API (Phase 2.1).
7. tích hợp Gemini để chấm điểm bài viết, phân tích Page, gợi ý tối ưu nội dung.

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
# Nếu dùng SQLite: tạo file database/database.sqlite trước khi migrate
# Nếu dùng MySQL: tạo database trong MySQL trước khi migrate
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

### Draft Workflow

Quy trình xuất bản nháp (Draft Workflow):
1. **Create Draft Post**: Tạo bài viết nháp từ Pexels (Media Search) hoặc tạo mới.
2. **Edit caption/schedule**: Chuyển tới màn hình chỉnh sửa nội dung bài viết và đặt thời gian hẹn giờ.
3. **Click Save & Approve**: Nhấp chọn "Save & Approve" hoặc "Approve Now" để phê duyệt bài viết (chuyển trạng thái sang `approved`).
4. **Run scheduler or Publish Now**: Bài viết đã duyệt sẽ được xuất bản qua scheduler tự động hoặc có thể bấm xuất bản ngay qua nút "Publish Now".
5. **Only approved posts can publish**: Hệ thống chỉ tiến hành xuất bản những bài đăng đã qua kiểm duyệt (`approved`).

**⚠️ Quy tắc xuất bản:**
- Bài viết ở trạng thái `draft` sẽ **không bao giờ** tự động đăng.
- Lịch đăng bài tự động (`approved` posts) chỉ chạy khi scheduler `posts:publish-due` hoạt động.
- Chạy scheduler ở local:
  ```bash
  php artisan schedule:work
  ```
- Kiểm tra & xuất bản thủ công bài đến giờ:
  ```bash
  php artisan posts:publish-due
  ```

### Automatic Publishing Scheduler

Để bài viết đã được duyệt (`approved`) tự động đăng lên Facebook Page khi đến giờ hẹn, Laravel Scheduler phải được cấu hình chạy.

#### 1. Local Development
Trong quá trình dev, chạy lệnh sau ở một terminal riêng biệt để liên tục kiểm tra và chạy scheduler mỗi phút:
```bash
php artisan schedule:work
```

#### 2. Kiểm tra thủ công (Manual Publish Check)
Có thể kích hoạt xuất bản các bài đã đến giờ bằng lệnh:
```bash
php artisan posts:publish-due
```

#### 3. Cấu hình Windows Laragon / Task Scheduler
Để chạy tự động trên môi trường production/Windows mỗi phút, tạo task scheduler chạy command sau:
```cmd
cd C:\laragon\www\auto-fb-content && php artisan schedule:run
```

**⚠️ Lưu ý cực kỳ quan trọng:**
- Phải có lệnh `schedule:work` đang chạy thì bài viết ở trạng thái `approved` đến giờ mới tự động đăng.
- Nếu không chạy scheduler, app chỉ lưu lịch đăng trong database chứ không tự đăng.
- Bài viết có status = `draft` sẽ **không bao giờ** tự động đăng (chỉ bài viết `approved` mới được publish).
- `FACEBOOK_PUBLISH_MODE=fake` chỉ đăng giả lập (không gọi Facebook API).
- `FACEBOOK_PUBLISH_MODE=real` sẽ đăng thật lên trang Facebook cấu hình.

### Publish Logs

Mọi lần publish (fake/real) đều được log vào bảng `post_publish_logs`:
- mode (fake/real)
- action (publish_text/publish_photo/publish_video/validate_config)
- status (success/failed)
- error_message
- request_summary (không chứa token)
- response_json (không chứa token)

### Debug Failed Publish

Khi một bài viết ở trạng thái `approved` chuyển sang `failed`, hệ thống đã thử xuất bản nhưng gặp lỗi từ cấu hình, từ Facebook API, hoặc do URL của file media không hợp lệ.

Để chẩn đoán nguyên nhân lỗi:
1. **Trang Queue/Edit**: Mở bài viết bị lỗi trên giao diện chỉnh sửa để xem thông báo lỗi chi tiết cùng lịch sử 5 bản ghi publish logs gần nhất.
2. **Command chẩn đoán**: Sử dụng Artisan command để kiểm tra trạng thái bài đăng và tính hợp lệ của access token:
   ```bash
   # Chẩn đoán bài đăng số 5
   php artisan posts:debug-publish 5

   # Chẩn đoán bài đăng số 5 kèm theo kiểm tra thông tin Facebook API
   php artisan posts:debug-publish 5 --validate-config

   # Chạy tự động xuất bản
   php artisan posts:publish-due
   ```

*Lưu ý:*
- `FACEBOOK_PUBLISH_MODE=fake` dùng để giả lập toàn bộ quá trình đăng (không gọi API thật).
- `FACEBOOK_PUBLISH_MODE=real` yêu cầu Page Access Token có đủ các quyền đăng bài (`pages_manage_posts`) và media URL phải ở dạng public để Facebook có thể tải về.

## Security

- ❌ Không log access token
- ❌ Không trả token về frontend sau khi lưu (masked với •)
- ❌ Không commit token vào git
- ↳ Secret settings có `is_secret=true`
- ↳ Publish logs không chứa token

## Chạy Test

```bash
php artisan test
```

## Phase hiện tại: Completed MVP (Phase 6 + Acceptance Fix Pack)

- ↳ Dashboard nâng cấp (Coverage score, missing slot indicators, upcoming queue lists, quick actions)
- ↳ Topics CRUD + toggle active
- ↳ Pexels Search (tối ưu hóa chọn video MP4 dưới 1080p, có duration badge, thumbnail cho video)
- ↳ Queue management (approve/unapprove/edit/delete/reschedule) + Phân trang (Pagination)
- ↳ **Publish Now** cho bài approved (hỗ trợ xác nhận modal chi tiết cho video)
- ↳ **Fake/Real publish mode**
- ↳ **Facebook Graph API**: text post + photo post + **video post**
- ↳ **Validate Facebook Config**
- ↳ **Publish logs** (post_publish_logs)
- ↳ Settings page với đầy đủ Facebook Publishing, Video configs và **Mã hóa secrets**
- ↳ Console commands (generate-daily, publish-due, generate-calendar)
- ↳ Lịch đăng bài monthly grid view + **Cảnh báo thiếu slot đăng bài**
- ↳ Chống trùng lặp (Duplicate protection): chống trùng ảnh/video 30 ngày, trùng slot, và trùng caption (tối ưu hóa SQLite/MySQL LENGTH queries)
- ↳ Tích hợp Gemini AI tạo caption variants, chấm điểm nội dung, Page Audit, và Weekly Strategy Engine (hỗ trợ gating `GEMINI_ENABLED` và nút bấm thủ công)
- ↳ **140 tests passed** (100% assertions)
- ❌ Browser automation (không bao giờ)
