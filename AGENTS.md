# AGENTS.md — Post Studio

Self-contained developer guide. Everything you need to understand, run, extend, and
deploy this project is documented here — you should not need to read the source files
to understand how the app works, but file paths are listed throughout so you can jump
into the code when you need details.

---

## 1. What this project is

**Post Studio** is a lightweight, cPanel-hostable **PHP/MySQL** web app that lets a single
admin create and schedule social-media posts through **two different posting services at
the same time**:

- **Zernio** (`https://zernio.com/api/v1`) — auth via `Bearer sk_...` API key.
- **BulkPublish** (`https://app.bulkpublish.com`) — auth via `Bearer bp_...` API key.

The key feature: the composer lists **all connected channels from both services**, the
user ticks which platforms to post to, picks media/upload position, and the app creates
posts on the correct service(s). Mixed selections (Zernio + BulkPublish channels in one
composer run) create **one post per service** automatically because the two APIs are
incompatible.

Tech constraints decided with the client:
- **PHP 7.4+** compatibility (no PHP 8-only functions). Code uses plain cURL + PDO.
- **No build step** — Tailwind CSS via CDN, one hand-written `assets/css/style.css`.
- **Single admin** with a **simple password login** (bcrypt, first-run setup when the
  `users` table is empty). CSRF-protected forms + JSON endpoints.
- **Media**: browser uploads via **Zernio presigned URLs** OR via a server-side proxy to
  BulkPublish `/api/media`; media-URL input is also supported.
- Dark modern dashboard UI.

---

## 2. Environment

- Working directory: `C:\Users\user\Desktop\main projects\poster`
- Target host: **cPanel** (shared hosting), database via phpMyAdmin/MariaDB.
- Local tooling used during development (NOT part of the project):
  - Portable PHP: `C:\Users\user\AppData\Local\Temp\opencode\php\bin\php.exe`
    (php.ini already enables curl, pdo_mysql, mbstring).
  - Mock API servers + test scripts: `C:\Users\user\AppData\Local\Temp\opencode\mock\`
    (Zernio mock `router.php`, BP mock `bp_router.php`, client/composer tests).

---

## 3. Database credentials (production)

From `config.php`:

| Setting | Value |
|---|---|
| DB_HOST | `localhost` |
| DB_NAME | `db_name` |
| DB_USER | `db_user_name` |
| DB_PASS | `db_password` |

These are cPanel MySQL credentials. Do not commit real secrets anywhere else.

---

## 4. Database schema (`install.sql`)

Run/import `install.sql` in phpMyAdmin on the target DB. Three tables:

### `users`
| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| username | VARCHAR(100) UNIQUE | |
| password_hash | VARCHAR(255) | bcrypt |
| created_at | TIMESTAMP | default now |

First-run: `login.php` creates an admin if the table is empty (see Section 8).

### `settings`
Key/value store (`key` VARCHAR(100) PK, `value` TEXT). Important keys:

| Key | Meaning |
|---|---|
| `zernio_api_key` | Zernio API key (`sk_...`) |
| `bulkpublish_api_key` | BulkPublish API key (`bp_...`) |

Add new keys with `set_setting()` / read with `get_setting()` (in `includes/db.php`).
The schema does not need to change for new settings.

### `posts`
Local mirror of every created post (record-keeping only; the source of truth is each
service's API).

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| zernio_post_id | VARCHAR(64) | Zernio post `_id` (24-hex) or BulkPublish post `id` (integer as string) |
| service | VARCHAR(20) | `zernio` or `bulkpublish` |
| content | TEXT | caption |
| media_type | VARCHAR(20) | `caption` / `image` / `video` |
| media_json | TEXT | JSON of media items |
| platforms_json | TEXT | JSON of the selected platform entries |
| scheduled_for | DATETIME | local scheduled time |
| timezone | VARCHAR(50) | default `UTC` |
| status | VARCHAR(20) | from the API |
| source | VARCHAR(20) | `composer` or `bulk` |
| created_at / updated_at | TIMESTAMP | |

**Migration note:** `install.sql` ends with guarded `ALTER TABLE ... ADD COLUMN IF NOT EXISTS
service ...` and `ADD INDEX IF NOT EXISTS idx_service` so old databases can be upgraded by
re-importing the file.

---

## 5. File structure (what each file does)

```
poster/
├── config.php                 DB creds, ZERNIO_BASE_URL, BULKPUBLISH_BASE_URL, APP_NAME, APP_SECRET, SESSION_NAME, APP_DEBUG
├── install.sql                schema (see Section 4)
├── .htaccess                  (provided for cPanel)
├── login.php                  login + first-run admin setup
├── logout.php                 destroys session
├── index.php                  dashboard: channel counts + connected channels grouped by platform
├── composer.php               compose-post page (form skeleton; logic is in app.js)
├── bulk.php                   bulk CSV upload page
├── posts.php                  merged post list from both services (filters + pagination)
├── post_view.php              post detail (normalizes both services into one $view shape)
├── settings.php               manage both API keys + connection tests
├── includes/
│   ├── auth.php               sessions, CSRF, require_login / require_login_ajax, is_logged_in, user_count
│   ├── db.php                 PDO helper: db(), get_setting(), set_setting()
│   ├── zernio.php             Zernio API client class + zernio_client() factory
│   ├── bulkpublish.php        BulkPublish API client class + bulkpublish_client() + bp_map_platform()
│   ├── platforms.php          platform_meta(), platform_badge(), status_badge(), platform_list()
│   ├── header.php             sidebar + topbar; nav; connection badges
│   └── footer.php             closing tags + app.js include
├── assets/
│   ├── css/style.css          hand-written dark theme layered over Tailwind
│   ├── js/app.js              ALL front-end logic (composer, posts filters, post view actions, bulk)
│   └── csv_template.csv       bulk-upload template (includes the `service` column)
└── ajax/
    ├── list_accounts.php      returns channels from BOTH services, tagged with service
    ├── presign.php            Zernio presign upload URL (browser → Zernio storage directly)
    ├── bp_upload.php          browser → this server → BulkPublish /api/media (multipart proxy)
    ├── create_post.php        dispatches post creation per service; mirrors to local posts table
    ├── action.php             retry / unpublish (Zernio), retry / publish-now (BulkPublish)
    └── bulk_upload.php        parses CSV, routes each row by its `service` column
```

---

## 6. The two posting services

### 6.1 Zernio (`includes/zernio.php`)

- Base URL: `https://zernio.com/api/v1` (constant `ZERNIO_BASE_URL`).
- Auth: `Authorization: Bearer <sk_...>`.
- Client class `Zernio` methods:
  - `listAccounts(?profileId, status)` → `GET /v1/accounts`
  - `listProfiles()` → `GET /v1/profiles`
  - `createPost(array)` → `POST /v1/posts` (sends `x-request-id` header)
  - `listPosts(array $filters)` → `GET /v1/posts`
  - `getPost(id)` → `GET /v1/posts/{id}`
  - `retryPost(id)` → `POST /v1/posts/{id}/retry`
  - `unpublishPost(id)` → `POST /v1/posts/{id}/unpublish`
  - `presignMedia(filename, contentType)` → `POST /v1/media/presign`
  - `uuid4()` static
- Factory: `zernio_client()` returns `null` when no key stored.
- **Post ID**: 24-hex string in `_id`. Account IDs are also strings (`_id`).
- Zernio statuses: `draft, scheduled, publishing, published, partial, failed, cancelled, pending`.
- Composer payload shape (unchanged from original design, tested):
  ```
  {
    content,                       // caption string (optional if media present)
    platforms: [{
      platform,                    // e.g. "twitter", "instagram"
      accountId,                   // Zernio account _id
      platformSpecificData: {...}, // per-platform options (see app.js PLATFORM_OPTS)
      customContent                // optional per-platform caption override
    }],
    mediaItems: [{ url, type }],   // type = "image" | "video"
    publishNow: true               // OR:
    scheduledFor: "YYYY-MM-DDTHH:MM:SS", timezone: "America/New_York",
    tags: [],                      // optional
    tiktokSettings: { ... }        // only when a Zernio TikTok channel is selected
  }
  ```

### 6.2 BulkPublish (`includes/bulkpublish.php`)

- Base URL: `https://app.bulkpublish.com` (constant `BULKPUBLISH_BASE_URL`).
- Auth: `Authorization: Bearer <bp_...>`. Key from app.bulkpublish.com → Settings → API Keys.
- Client class `BulkPublish` methods:
  - `listChannels(?bool $active)` → `GET /api/channels`
  - `getChannelOptions(id)` → `GET /api/channels/{id}/options` (Pinterest boards, Reddit
    subreddits/flairs, Discord channels, Tumblr blogs)
  - `listPlatforms()` → `GET /api/platforms` (availability + post types per platform)
  - `createPost(array)` → `POST /api/posts`
  - `listPosts(array $filters)` → `GET /api/posts` (filters: page, limit max 500, status,
    channelId, search, from, to, scheduledFrom, scheduledTo)
  - `getPost(id)` → `GET /api/posts/{id}`
  - `publishPost(id)` → `POST /api/posts/{id}/publish`
  - `retryPost(id)` → `POST /api/posts/{id}/retry`
  - `storyPost(id)` → `POST /api/posts/{id}/story`
  - `uploadMedia(filePath, filename, mime)` → `POST /api/media` (multipart, max 100 MB single)
  - `uploadMediaFromUrl(url)` → downloads a public URL then uploads it (server-side)
  - `deleteMedia(id)` → `DELETE /api/media/{id}`
- Factory: `bulkpublish_client()` returns `null` when no key stored.
- **IDs are integers** (channel `id`, post `id`). Post statuses:
  `draft, scheduled, publishing, published, processing, failed, partial`.
- **Channel shape** (from `GET /api/channels`):
  `{ id (int), platform, accountName, accountId, accountType, profileImage, isActive,
     tokenStatus (valid|expiring_soon|expired), tokenExpiresAt, metadata, createdAt, updatedAt }`
- **Create-post payload**:
  ```
  {
    content,                      // caption
    channels: [{ channelId: int, platform: "x" }],   // platform slugs differ! see below
    mediaFiles: [ int, ... ],     // media file IDs (from /api/media)
    status: "draft" | "scheduled",
    scheduledAt: "2026-04-10T12:00:00Z",  // ISO, UTC when combined with timezone
    timezone: "America/New_York",
    platformSpecific: { "<platform>": {...}, "_firstComment": "..." },  // _firstComment is TOP-LEVEL
    platformContent: { "<platform>": "different caption for that platform" },
    postTypeOverrides: { "<platform>": "reel" },
    postFormat: "thread"          // optional threads for X/Bluesky/Mastodon
  }
  ```
- **Platform slug differences (internal → BulkPublish):**
  | Internal name (this app) | BulkPublish slug |
  |---|---|
  | `twitter` | `x` |
  | `googlebusiness` | `gmb` |
  | everything else | same |
  - Mapping helpers: `bp_map_platform()` (BP → internal) and `bp_to_bp_slug()`
    (internal → BP, defined in `ajax/create_post.php`).
  - `platform_meta()` in `includes/platforms.php` also has entries for `mastodon`/`tumblr`
    via the fallback branch; BP uses those slugs directly.
- **Post types** (via `postTypeOverrides`): Instagram `feed_photo|feed_video|reel|story|carousel`,
  Facebook `post|reel|story`, TikTok `video|photo_slideshow`, YouTube `video|short`,
  LinkedIn `post|multi_image|pdf_carousel|article`, Pinterest `pin|video_pin|carousel`,
  Threads `text|image|video|carousel`, X/Bluesky/Mastodon `post` (use `postFormat:"thread"`
  for threads), Google Business `standard|event|offer`, Tumblr/Reddit/Discord/Telegram `post`.
- **Common `platformSpecific` options:**
  - YouTube: `title` (required), `privacyStatus` (public/unlisted/private), `categoryId`,
    `madeForKids`, `playlistId`, `thumbnailUrl`.
  - TikTok: `privacyLevel` (PUBLIC_TO_EVERYONE/MUTUAL_FOLLOW_FRIENDS/FOLLOWER_OF_CREATOR/
    SELF_ONLY/SEND_TO_USER_INBOX), `disableDuet`, `disableStitch`, `disableComment`,
    `isAigc`, `brandContentToggle`, `brandOrganicToggle`, `thumbnailTimestamp`.
  - X: `replySettings` (everyone/following/mentionedUsers).
  - Instagram: `collaborators`, `shareToStory`, `trialReel`, `graduationStrategy`, `thumbnailTimestamp`.
  - Facebook: `shareToStory`.
  - LinkedIn: `url` (required for `article`), `title`, `description`, `carouselTitle`.
  - Pinterest: keyed by channel ID → `{ boardId }`; also `title` (required), `description`,
    `link`, `dominantColor`, `coverImageUrl`.
  - `_firstComment`: top-level `platformSpecific` key, applies to all channels on the post.
    Not supported on Discord, Pinterest, TikTok, Google Business, Tumblr (recorded as failed,
    main post still publishes).
- **Error responses**: BulkPublish returns either
  `{ "error": { "message": "...", "code": "..." } }` or `{ "error": "string" }`. The client
  normalizes both. `BulkPublishException` carries `status` and `payload`.

---

## 7. How the pieces fit together (data flow)

### 7.1 Loading channels (`ajax/list_accounts.php`)
Called by the composer with `GET`. Returns:
```json
{
  "ok": true,
  "accounts": [ ... ],       // unified list, both services
  "services": { "zernio": true, "bulkpublish": true },
  "errors": [ ... ]          // per-service error strings when one API fails but the other worked
}
```
Unified account shape:
- Zernio accounts: original API fields PLUS `service: "zernio"`, `color`, `short`.
- BulkPublish channels: mapped to
  `{ service: "bulkpublish", _id: "b<id>", channelId: <int>, platform, displayName, username,
     avatarUrl, accountId, accountType, tokenStatus, isActive, color, short }`.

**ID collision rule:** Zernio `_id` is a hex string; BulkPublish `_id` is prefixed with `b`
(`b1`, `b2`, ...). The composer keys selections on `_id`, so the two never collide.

If both services have no key configured, the endpoint returns HTTP 400 with an error.

### 7.2 The composer (`composer.php` + `assets/js/app.js`)
Order of sections on the page:
1. **Post type** — caption / image / video (radio cards).
2. **Media** — upload file (Zernio presign if a Zernio key exists, else BP proxy) or paste a URL.
3. **Caption**.
4. **Schedule** — publish now, or date/time + timezone.
5. **Select channels** — grouped by platform, each channel shows a ZN or BP badge, an
   "expired"/"offline" tag when relevant, and a per-platform "Select all" button.
6. **Platform options** — per selected platform: post format, title, privacy, first comment,
   custom content, etc. (driven by `PLATFORM_OPTS` in app.js).

`composer.buildPlatformEntry(acc)` builds the per-channel entry:
- Zernio: `{ service, platform, accountId, platformSpecificData, customContent }`
- BulkPublish: `{ service, platform, channelId, platformSpecific, postTypeOverride,
   customContent }` — where `platformSpecific` uses BP field names (`privacyStatus`,
   `privacyLevel`, `disableComment`, `thumbnailUrl`, etc.) and `_firstComment` is included
   so the server can hoist it.

`composer.buildPayload()` assembles the unified payload (see 6.1 for the Zernio part) and
also:
- copies `fileId` onto media items when the file was uploaded via the BP proxy,
- only adds root `tiktokSettings` when a **Zernio** TikTok channel is selected
  (BP TikTok options travel inside `platformSpecific.tiktok`).

### 7.3 Creating a post (`ajax/create_post.php`)
1. Splits `payload.platforms` into `$zernioEntries` and `$bpEntries` by `service`.
2. **Zernio**: strips `service` from entries and `fileId` from media items, then calls
   `Zernio::createPost($zPayload)`.
3. **BulkPublish**: calls `build_bp_payload()` which:
   - maps internal platform names → BP slugs,
   - builds `channels`, `platformSpecific` (hoists `_firstComment` to top level),
     `platformContent`, `postTypeOverrides`,
   - resolves media: uses an existing `fileId` when present, otherwise downloads the media
     URL via `uploadMediaFromUrl()` to obtain a file ID,
   - schedules: `publishNow` → create as `draft` then `publishPost()`;
     `scheduledFor` → converts to UTC ISO `scheduledAt` + `timezone`.
4. `mirror_post()` inserts each created post into the local `posts` table (non-fatal if it
   fails).
5. Responds with `{ ok: true, posts: [ { service, post, message } ], errors: [] }`.

**Mixed selections** → two HTTP calls, two posts, both mirrored. If one service fails, the
other still succeeds and the error is reported.

### 7.4 Viewing posts (`posts.php` + `post_view.php`)
- `posts.php` fetches both services (page 1, limit 100 each), normalizes into a common
  shape (`_id`, `service`, `content`, `status`, `scheduledFor`, `mediaItems`, `platforms`,
  `_sort`), merges, sorts newest-first, then paginates locally (20/page).
- `post_view.php` takes `?id=...&service=zernio|bulkpublish`. It normalizes the raw API
  response into a single `$view` array used by one template.
- Actions:
  - Zernio: **Retry** (failed) → `retryPost`, **Unpublish** (published/partial) → `unpublishPost`.
  - BulkPublish: **Retry** (failed) → `retryPost`, **Publish now** (draft) → `publishPost`.
  - Both handled in `ajax/action.php` by reading the `service` field.

### 7.5 Bulk CSV (`bulk.php` + `ajax/bulk_upload.php`)
CSV columns (in this order in the template):
```
service,platform,username,caption,media_url,media_type,scheduled_for,timezone,custom_content
```
- `service` = `zernio` (default) or `bulkpublish`.
- `platform`/`username` must match a connected account. Zernio matches on
  `platform|username` (username is case-insensitive); BulkPublish matches on
  `platform|accountName` or `platform|accountId` (internal platform names, so use `twitter`
  not `x`, `googlebusiness` not `gmb`).
- `media_type` = `caption` (no media), `image`, `video`. `media_url` required for image/video.
- `scheduled_for` = `YYYY-MM-DD HH:MM` in the given `timezone`; empty = publish now.
- For BulkPublish rows, media URLs are uploaded via `uploadMediaFromUrl()` first.
- Dry-run mode validates without creating posts.

### 7.6 Dashboard (`index.php`)
Shows totals (channels, platforms, Zernio accounts, BulkPublish channels) and the connected
channel list grouped by platform with ZN/BP badges and copy-ID checkboxes.

---

## 8. Authentication flow

- `includes/auth.php`:
  - `session_name(SESSION_NAME)`, `session_start()`.
  - `is_logged_in()` → session `user_id` > 0.
  - `require_login()` (page) / `require_login_ajax()` (JSON 401) guards.
  - `csrf_token()` / `verify_csrf()` — pages send it in a hidden input; AJAX sends it as the
    `X-CSRF-Token` header (added automatically by `api()` in app.js).
- `login.php`:
  - If `user_count() === 0`, shows the **first-run setup** form (create username + password,
    bcrypt-hashed, inserted into `users`).
  - Otherwise a normal login form. Sets `$_SESSION['user_id']`.
- `logout.php`: destroys the session, redirects to `login.php`.

---

## 9. Config details (`config.php`)

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'quraneat_post');
define('DB_USER', 'quraneat_post');
define('DB_PASS', '.XIDBYCXE~+KrfX]');
define('ZERNIO_BASE_URL', 'https://zernio.com/api/v1');
define('BULKPUBLISH_BASE_URL', 'https://app.bulkpublish.com');
define('APP_NAME', 'Post Studio');
define('APP_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING_1234567890abcdef'); // used for sessions
define('SESSION_NAME', 'poststudio_session');
define('APP_DEBUG', true); // set false in production
```

---

## 10. Testing / validation performed (progress log)

Everything below was verified during development:

1. **PHP lint** — all 22 PHP files pass `php -l` with portable PHP 8.4
   (`C:\Users\user\AppData\Local\Temp\opencode\php\bin\php.exe`). Code is written for
   PHP 7.4+ so it is safe on cPanel.
2. **Zernio client** — 15/15 tests against a mock server (`mock/router.php`).
3. **BulkPublish client** — 19/19 tests against a mock server (`mock/bp_router.php`):
   listChannels, getChannelOptions, listPlatforms, uploadMedia, createPost (scheduled,
   multi-channel, mediaFiles), getPost, publishPost, retryPost, listPosts, uploadMediaFromUrl,
   and the bad-auth error path.
4. **Composer payload logic** — 14/14 original tests (`mock/test_composer.js`) against the
   real `app.js`, plus 11 mixed-service tests (`mock/test_composer_real.js`) and 4
   structural payload tests (`mock/test_composer_mixed.js`). Verified:
   - Zernio entries carry `accountId` + `platformSpecificData`.
   - BulkPublish entries carry `channelId` + `platformSpecific` + `postTypeOverride`
     (e.g. Instagram reel → `postTypeOverride: "reel"`).
   - Zernio YouTube uses `platformSpecificData.visibility`; BP YouTube uses
     `platformSpecific.privacyStatus`.
   - `fileId` is preserved on BP-uploaded media.
   - Root `tiktokSettings` only for Zernio TikTok.
   - Media URL / fileId / schedule passthrough.
5. **Auth redirects** — verified (login redirect, AJAX 401, CSRF).

How to re-run the tests:
- Start the BP mock: `php -S 127.0.0.1:8911 <temp>\opencode\mock\bp_router.php`
- Run `php <temp>\opencode\mock\test_bp_client.php`
- Run `node <temp>\opencode\mock\test_composer.js` and `test_composer_real.js`
  (these need a DOM shim which is already inside each test file).
- Zernio mock: `php -S 127.0.0.1:8910 <temp>\opencode\mock\router.php` + `test_client.php`.

**Note:** the mock routers enforce `Authorization: Bearer` headers; the BP mock serves
`/static/photo.jpg` publicly (no auth) to exercise `uploadMediaFromUrl()`.

---

## 11. Known issues / limitations (accepted "fix later" items)

1. **No local MySQL test** — a full DB-backed integration test was never run locally
   (MariaDB download timed out). Auth/dashboard/DB code paths are linted and logic-tested,
   but not integration-tested against a live database.
2. **Posts pagination** — `posts.php` fetches the first 100 posts per service and paginates
   locally, so results beyond ~100 posts per service are not reachable. Fine for now.
3. **BulkPublish large media** — single-request uploads capped at 100 MB; the chunked
   multipart flow (up to 1 GB) is documented but not implemented.
4. **Mixed-service media** — when both services are selected, media uploaded via Zernio
   presign is re-downloaded server-side for BulkPublish. Double transfer; acceptable.
5. **Per-platform deep options** — Pinterest board, Reddit subreddit/flair, Discord channel,
   and Tumblr blog selection (which need `GET /api/channels/{id}/options`) are exposed in the
   BP client but not yet surfaced in the composer UI. BP validation will still reject
   posts that need them (e.g. Pinterest requires a title + board).
6. **ClamAV false positive on upload** — see Section 12. Not a code defect.
7. **`require_api_key()` in `includes/auth.php`** is a legacy Zernio-only helper; it is not
   used anywhere. Harmless, can be removed.

---

## 12. ClamAV false positive (uploading the site to cPanel)

When the site is zipped and uploaded to cPanel, the host's antivirus may report:

```
Sanesecurity.Foxhole.JS_Zip_23.UNOFFICIAL FOUND
```

This is a **ClamAV heuristic false positive**. The Sanesecurity "Foxhole" signatures match
the pattern *"a JavaScript file exists inside a ZIP archive"* — they are well documented to
flag legitimate zips containing any `.js` file. Post Studio contains exactly one JS file,
`assets/js/app.js`, which is clean (no `eval`, `atob`, `fromCharCode`, base64, etc.).

**Do NOT modify the code to "fix" this** — the trigger is the zip+JS combination, not the
code. Instead use one of:

1. **Recommended — skip the zip.** In cPanel **File Manager → Upload**, drag-and-drop the
   `poster` folder (or upload the files into a directory). No zip = no `JS_Zip` signature.
   FTP/SFTP also works.
2. Re-zip with a different tool/settings (e.g., 7-Zip different compression method/level,
   or WinRAR zip) — changing the compressed bytes can dodge the heuristic. Not guaranteed.
3. Zip everything **except** `assets/js/app.js`, then upload that one file separately.
4. Ask the host to whitelist the file/path in their antivirus configuration.

---

## 13. Deployment checklist (cPanel)

1. Create the database + user in cPanel MySQL. Put those values in `config.php`
   (Section 3 / Section 9).
2. Upload the files (Section 12 — prefer File Manager folder upload over a zip).
3. Import `install.sql` in phpMyAdmin.
4. Set `APP_DEBUG` to `false` in `config.php`, and change `APP_SECRET` to a long random
   string.
5. Visit the site → `login.php` → create the admin account on first run.
6. Go to **Settings** → paste your Zernio (`sk_...`) and/or BulkPublish (`bp_...`) API keys →
   verify the green "connected" badges.
7. Open **Compose Post** — channels from both services should appear with ZN/BP badges.
8. Optional: test bulk CSV on the **Bulk Publish** page with "Validate only" first.

---

## 14. Quick reference — common additions

- **Add a platform option** in the composer: edit the `PLATFORM_OPTS` object in
  `assets/js/app.js`, then map the field in `buildPlatformEntry()` (Zernio → `platformSpecificData`,
  BulkPublish → `platformSpecific`). Remember BP field names differ (e.g. `privacyStatus`,
  `privacyLevel`).
- **Add an API method**: mirror the pattern in `includes/zernio.php` or
  `includes/bulkpublish.php` (one `request()` helper handles everything).
- **Add a setting**: call `set_setting()` / `get_setting()`; no schema change needed.
- **Add a page**: set `$page_title` and `$active`, `require_once includes/header.php`, render
  content, `require_once includes/footer.php`.
- **Front-end endpoint**: use `window.PostStudio.api(url, { method, body })` (JSON) or
  pass a `FormData` body for multipart. CSRF header is added automatically.
