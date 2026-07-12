# Changelog

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

### Not Included (Phase 2.1 / Phase 3+)
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
