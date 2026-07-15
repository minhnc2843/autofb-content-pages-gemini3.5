# Changelog

## [Phase 7] — 2026-07-15

### Added
- **Multi-Page Management (Quản lý nhiều Page)**:
  - Created pages, profiles, and topics tables to store page-specific Facebook credentials, timezone, niche profile metadata, mix ratios, slot configurations, and active topics.
  - Automatically seeded a "Default Facebook Page" using legacy global settings to ensure backward compatibility.
  - Page CRUD controller (`PageController`) supporting presets filling, default page selection, configuration validation, and active toggling.
  - Front-end views `Pages/Index.jsx` and `Pages/Form.jsx` supporting presets setup, timezone selects, mix ratio controls, and page validation.
  - Added page filter inputs to Dashboard, Calendar, and Queue screens, and displayed current page context inside table rows and statistics.
- **AI Chatbot Assistant (AI Copilot Hub)**:
  - Created chat sessions, messages, and tasks tables to host chat logs and pending background operations.
  - Giao diện `Assistant/Index.jsx` offering session sidebars, messaging logs, and task confirmation panels.
  - Chatbot intent parser and controller (`AssistantController` and `AIAssistantService`) supporting `create_content_plan`, `generate_drafts`, `analyze_page`, `optimize_schedule`, `update_page_token`, and `validate_page_token` intents.
- **Secure Secrets Redaction (Che giấu token)**:
  - Created `SecretRedactionService` to detect Facebook access tokens in raw message strings, encrypt and store them in `pending_secrets`, and redact them with `[FACEBOOK_PAGE_ACCESS_TOKEN_REDACTED]` prior to saving chat history or forwarding to Gemini API.
- **Review and Execute Plan Workflow (Panel Duyệt Việc)**:
  - Created tasks in status `awaiting_confirmation` for AI actions.
  - Added task confirmation and cancellation endpoints, verifying page credentials, plan details, and updating status to `completed` or `failed` post-execution.
- **Separated Command Scope**:
  - Upgraded `DuePostPublisherService` and `PublishDuePostsCommand` (`posts:publish-due`) to support optional `--page=` parameters for running target publishing tasks.

### Tests Added
- `Phase7FoundationTest.php`:
  - `test_secret_redaction_service_redacts_fb_tokens`: Verifies short and long token detection, redaction tag mapping, and pending secrets storage.
  - `test_assistant_does_not_store_raw_tokens_in_chat_history`: Verifies user messages in database do not contain raw tokens.
  - `test_update_page_token_task_confirm_decrypts_and_saves_token`: Verifies that confirming update token task decrypts pending secrets and updates page credentials.
  - `test_validate_page_token_task_confirm_executes_successfully`: Verifies Graph API validate task execution.
  - `test_tokens_are_never_sent_to_gemini_prompts`: Verifies Gemini prompts do not receive raw credentials.
  - `test_pending_secrets_cleanup_expires_old_secrets`: Verifies purging of expired pending secrets.
  - `test_page_crud_and_presets`: Verifies page creation, presets, and profile relations.
  - `test_page_profile_validation`: Verifies profile relationships and validation.
  - `test_queue_index_filters_posts_by_page_id`: Verifies page scoping in Queue index.
  - `test_publish_due_command_publishes_scoped_posts_for_specific_page`: Verifies page scope in scheduling commands.

---

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
  - Fixed Queue index view blank screen crash by applying safety checks for null or undefined posts data, and removing the auto-filtering useEffect loop.
  - Added inline status warning badges/texts for Draft and Approved posts in the Queue list.
- **Safe Batch Actions**:
  - Restricted state transitions: approving restricted to `draft`/`failed`, unapproving restricted to `approved` to `draft`, and deleting restricted strictly to `draft` posts.
  - Enabled reason logging column in `post_status_histories` mapping the batch action's name.
- **Calendar & Scheduler Upgrades**:
  - Unified auto-schedule parameters in `ContentCalendarService` matching slots list, topics, media type, and start dates.
  - Leveraged Pexels integration fallback in scheduler if cached elements run low.
  - Corrected month/year fallbacks to reset out-of-bound ranges.
  - Warning checks for missing daily slots (lower than 3 posts) now check today or future dates, completely ignoring past history.
  - Optimized duplicate caption checks (`isCaptionDuplicate`) in `DuplicateProtectionService` to query database candidates using `LENGTH(caption)` bounds instead of loading the entire table.
  - Redirected Pexels `createDraft` action to the edit screen of the newly created post, offering full media preview (with HTML5 video player and direct links) and immediate caption/schedule customization.
  - Registered `posts:publish-due` command to run every minute in Laravel Scheduler via `routes/console.php`.
  - Added global status warning note at the top of the monthly Calendar view.
  - Integrated contextual action buttons (Save Changes, Save & Approve, Approve Now, Unapprove, Publish Now) in the Edit view, conditionally rendered based on post status.
  - Restructured redirection logic: status update, unapprove, approve, and manual publish actions now redirect back to the Edit view when triggered from there using referer-based checks, rather than redirecting to Queue index.
  - Tối ưu hóa URL ảnh tự động từ Pexels (`w=1600` cho ảnh chính, `w=600` cho ảnh thumbnail) nhằm giải quyết triệt để lỗi Graph API Code 1.
  - Xây dựng cơ chế auto-retry nén thêm ảnh về kích thước `w=1200` nếu lần publish đầu tiên gặp mã lỗi 1 hoặc cảnh báo "reduce the amount of data".
  - Thêm lệnh batch-optimize `media:optimize-pexels-urls` để chuyển đổi URL ảnh cũ trong database.
- **Gemini Gating**:
  - Gated GeminiService calls using `GEMINI_ENABLED` database settings to avoid unprompted page-load API hits.
  - Restructured Strategy Engine: `GET /strategy` now only reads the latest cached outline from database, delegating new generation exclusively to a new manual trigger `POST /strategy/generate` action.

### Tests Added
- `PhotoPublishRetryTest`: Testing retry fallback with heavy compression and media optimize batch command.
- `PublishFailureDiagnosticsTest`: Testing failures alerts, 5 logs timeline, and retry status transitions.
- `AcceptanceFixPackTest`: Verifying Pexels redirect on draft creation, Queue page safety when empty or non-empty, approved status requirement to auto-publish, Console Scheduler registration, edit page loading, post updating redirects, Save & Approve transitions, and manual publish limits.
- `SettingsSecurityTest`: Verifying settings encryption, database priority, and masked token safety.
- `QueuePaginationTest`: Testing paginated collections and parameter persistence.
- `QueueBatchSafetyTest`: Validating safe batch transitions, published posts isolation, and status history logs.
- `CalendarValidationTest`: Testing boundary limits, all-filters, and future-only warnings.
- `GeminiGatingTest`: Verifying AI calls gating, strategy generation redirects when disabled, and strategy generation isolation.
- `GeminiServiceTest` (Expanded): Added unit tests for `analyzeMedia()`, `auditPage()`, and `generateStrategy()` methods.

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
