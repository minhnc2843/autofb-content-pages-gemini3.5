# Changelog

## [Acceptance Fix Pack] — 2026-07-14

### Added
- **Settings Security & Encrypted secrets**:
  - Automatically encrypts secret settings (API keys, Access Tokens) in the SQLite database.
  - Prioritizes database setting entries over local env variables inside testing and production runtime configurations.
  - Safe-guard mechanism avoiding overwriting real credentials when frontend transmits masked characters (`••••••••`).
- **Pagination & Filters**:
  - Implemented Eloquent-level database pagination (`paginate(20)`) for queue entries.
  - Rendered pagination navigation links in Inertia React queue view.
  - Restrained select-all checkbox to apply only to records visible on the current page.
  - Optimized status & topic filter drop-downs: selecting "all" now clears the filtering constraints instead of failing.
- **Safe Batch Actions**:
  - Restricted state transitions: approving restricted to `draft`/`failed`, unapproving restricted to `approved` to `draft`, and deleting restricted strictly to `draft` posts.
  - Enabled reason logging column in `post_status_histories` mapping the batch action's name.
- **Calendar & Scheduler Upgrades**:
  - Unified auto-schedule parameters in `ContentCalendarService` matching slots list, topics, media type, and start dates.
  - Leveraged Pexels integration fallback in scheduler if cached elements run low.
  - Corrected month/year fallbacks to reset out-of-bound ranges.
  - Warning checks for missing daily slots (lower than 3 posts) now check today or future dates, completely ignoring past history.
  - Optimized duplicate caption checks (`isCaptionDuplicate`) in `DuplicateProtectionService` to query database candidates using `LENGTH(caption)` bounds instead of loading the entire table.
- **Gemini Gating**:
  - Gated GeminiService calls using `GEMINI_ENABLED` database settings to avoid unprompted page-load API hits.
  - Restructured Strategy Engine: `GET /strategy` now only reads the latest cached outline from database, delegating new generation exclusively to a new manual trigger `POST /strategy/generate` action.

### Tests Added
- `SettingsSecurityTest`: Verifying settings encryption, database priority, and masked token safety.
- `QueuePaginationTest`: Testing paginated collections and parameter persistence.
- `QueueBatchSafetyTest`: Validating safe batch transitions, published posts isolation, and status history logs.
- `CalendarValidationTest`: Testing boundary limits, all-filters, and future-only warnings.
- `GeminiGatingTest`: Verifying AI calls gating, strategy generation redirects when disabled, and strategy generation isolation.
- `GeminiServiceTest`: Expanded unit tests to cover `analyzeMedia`, `auditPage`, and `generateStrategy` methods.

---

## [Phase 2.1] — 2026-07-13

### Added
- **Facebook Video Publishing** support via Meta Graph Video API:
  - `publishVideoPost(PostQueue $post)` in `FacebookPageService`: Supports remote_url uploads, checks video size limits, fallbacks for different API response IDs (`post_id`, `id`, `video_id`), and logs detailed publishing operations securely.
  - Video upload mode settings: `FACEBOOK_VIDEO_UPLOAD_MODE` (remote_url, local_download) and `FACEBOOK_VIDEO_MAX_MB`.
  - Placeholder skeleton for `local_download` upload mode.
- **FacebookReelsService** skeleton: Setup structure for future Reels chunk-upload integration.
- **Pexels Video Parsing Optimization**:
  - Automatically filters Pexels search results to select high-quality MP4 links under 1080p.
  - Graceful resolution fallback in case hd/sd requirements aren't present.
- **Queue Page Updates**:
  - Added video badges (🎬), video play links, and media type indicators.
  - Updated confirmation modal warnings specifically for video types.
- **Migration**: Added `publish_started_at`, `published_at`, and `publish_attempts` columns to `posts_queue` table.
- **Console Command Updates**: Logs detailed metrics (Text vs Photo vs Video) and status updates on publishing attempts.

### Tests Added
- `FacebookPageServiceVideoTest`: 10 video-specific tests (fake publish, real endpoints, ID extraction priority, payload limits, API errors, media type verification, token safety).
- `PublishDuePostsCommandTest`: 5 new command/route feature tests for video publishing.
- `PexelsServiceTest`: Added `test_video_parsing_prefers_mp4_under_1080p` unit test.

---

## [Phase 2] — 2026-07-12

### Added
- **FacebookPageService** full implementation:
  - `getGraphBaseUrl()`: Meta Graph API URL with configurable version.
  - `getPageId()` / `getPageAccessToken()`: Settings → .env fallback with clear errors.
  - `validateConfig()`: Calls GET /{page_id} to verify credentials.
  - `publishTextPost()`: POST /{page_id}/feed with caption.
  - `publishPhotoPost()`: POST /{page_id}/photos with URL + caption.
  - `publishVideoPost()`: Phase 2 stub, throws clear error.
  - `publishPost()`: Routes between fake/real mode.
- **PostPublishLog** model + migration: Logs every publish action with mode, status, sanitized request/response.
- **Publish Now** button on Queue page for approved posts with confirmation modal.
- **Publish mode indicator** banner on Queue page (fake vs real).
- **Settings page** updated: Facebook Publishing section with Page ID, Token, Graph Version, Publish Mode selector, Validate button.
- **StatusBadge** updated: Added `published` status with emerald color.
- **Dashboard** updated: Added `published` count to stats.
- New route: `POST /queue/{post}/publish-now`
- New route: `POST /settings/facebook/validate`
- Updated `posts:publish-due` command: Uses FacebookPageService with fake/real mode, detailed CLI output.
- Updated `.env.example` with Facebook config vars.

### Tests Added (27 new tests)
- `FacebookPageServiceTest`: 14 tests (missing config, fake mode, mock publish, API errors, token security, validate config).
- `FacebookSettingsTest`: 4 tests (validate success/401, token masking, config storage).
- `PublishDuePostsCommandTest`: 9 tests (draft/due filtering, fake/real mode, photo publish, publish-now route).

### Security
- Access tokens never logged in PostPublishLog.
- Secret settings masked with `•` before sending to frontend.
- Masked values skipped on save (prevents overwriting with mask characters).

### Not Included
- Video publishing via Facebook API.
- Real Gemini API calls.
- Browser automation.

---

## [Phase 1] — 2026-07-11

### Added
- Laravel 12 framework initialized with Inertia.js + React + Tailwind CSS.
- Database migrations for: topics, media_items, posts_queue, settings, ai_analyses.
- Eloquent models: Topic, MediaItem, PostQueue, Setting, AiAnalysis.
- Services: PexelsService, CaptionService, FacebookPageService (skeleton), GeminiService (skeleton).
- Controllers: Dashboard, Topic (CRUD + toggle), Pexels (search + create draft), Queue (CRUD + approve/unapprove), Settings.
- Console Commands: posts:generate-daily, posts:publish-due (fake only).
- React Pages: Dashboard, Topics (Index/Form), Pexels Search, Queue (Index/Edit), Settings.
- React Components: AppLayout, Sidebar, Header, MediaCard, PostPreviewCard, StatusBadge.
- 22 tests passing.
