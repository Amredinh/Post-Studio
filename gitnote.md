# Git Notes — Post Studio

## Branch Strategy

- `main` — production-ready code only
- `dev` — development branch, merged to main on release
- Feature branches: `feature/<name>` — create from `dev`, merge back to `dev`

## Commit Messages

- Use present tense: "fix success message", "add analytics", not "fixed" or "added"
- Prefix with area when helpful: `feat:`, `fix:`, `docs:`, `chore:`
- Example: `fix: success message not showing for mixed service posts`

## Pull Request Process

1. Create feature branch from `dev`
2. Implement changes, test locally
3. Push branch and open PR against `dev`
4. At least one approval required before merge
5. After merge, delete feature branch

## Pre-commit

- Run `php -l` on all modified PHP files
- Ensure no new `TODO` or `FIXME` left without tracking in AGENTS.md
- Verify `.gitignore` excludes `note.md` and OS junk

## Known Git Ignores (already in .gitignore)

- `note.md` — local notes, never commit
- OS junk: `.DS_Store`, `Thumbs.db`
- `config.php` — real credentials, lives only on the server (copy from `config.example.php`)

## After Pull/Push

- Run `php -l` on all PHP files to catch syntax errors
- Check that `AGENTS.md` is updated with any new features or changes
- Verify `config.php` stays untracked (`git status` must never list it)

---

## Pending Commit Message (config.php untracked round)

> **Before committing** (wherever the repo actually lives — GitHub/cPanel):
> `git rm --cached config.php` — required once; `.gitignore` alone does NOT
> untrack an already-committed file.

```
fix: stop tracking config.php so server credentials never conflict with pulls

- cPanel "Update from Remote" aborted (XID merge error): the live
  config.php with real DB credentials conflicted with the tracked
  placeholder version
- remove config.php from git tracking and add it to .gitignore;
  real credentials now live only on the server
- add config.example.php template with placeholders and copy instructions
- add .cpanel.yml: automated rsync deploy to docroot after every
  Update from Remote; excludes config.php/*.md/*.sql/.git* so the live
  credentials are never touched; no --delete (nothing on the server is
  ever removed by a deploy); edit USERNAME in DEPLOYPATH before pushing
- docs: AGENTS.md Sections 3/9/13 + progress log updated for the new
  flow, including one-time upgrade steps for existing deployments
  (backup config.php -> git stash -> pull -> restore)
- README.md rewritten: three-service architecture (Buffer), analytics
  caching, Tools page, bulk CSV service column, updated setup steps
```

> **cPanel "Deploy" requirements** (both must hold or the button stays hidden):
> 1. A valid `.cpanel.yml` exists at the repo root.
> 2. The checked-out branch of cPanel's clone has NO uncommitted changes
>    (`git status` clean there). If a hotfix was ever edited live, run
>    `git stash` inside the clone first.

---

## Pending Commit Message (dashboard/analytics hardening round)

```
fix: harden dashboard and analytics caching, partial failures, engagement tracking

- analytics cache: add cached_at timestamp, 30-min TTL for page loads,
  60-s throttle on forced refreshes to protect free-tier API quotas
- trim cached post content to 300 chars and guard cache writes so a
  failed write can never 500 the endpoint
- raise analytics post limit 50 -> 100 per service; posts with missing
  createdAt now sort oldest instead of newest
- analytics UI: render real per-service warnings instead of hardcoded
  "rate limited" guess; handle throttled flag and session expiry;
  show error banner only (no empty-state double-up); format numbers
  via toLocaleString(); add "Updated X min ago" freshness badge
- analytics header: show total tracked post views from get_engagement_views()
- dashboard (index.php): keep rendering when one service fails
  ("(unavailable)" markers); add token issues / offline group badges
  and expiring-soon / offline channel tags; guard platform grouping
- includes/db.php: fix invalid SQL in increment_engagement() (missing
  UPDATE keyword made every call fail silently)
- install.sql: upgrade settings.value TEXT -> MEDIUMTEXT; de-dupe
  posts_engagement and replace plain index with UNIQUE KEY
  uk_service_post (guarded MariaDB migration)
- docs: update AGENTS.md schema section and progress log
```

---

## Pending Commit Message (Buffer integration + Tools page round)

Single-commit option:

```
feat: add Buffer as third posting service and platform reference page

- includes/buffer.php: new classic-v1 Buffer API client (Bearer token,
  form-urlencoded POSTs, .json endpoints); Buffer class + BufferException
  + buffer_client(); buffer_map_platform() (google/gmb -> googlebusiness)
  and buffer_map_status() (sent/buffered/failed mapping); bf ID prefix
  convention; unix-timestamp normalization at every call site; video
  posts rejected by design (classic v1 limitation)
- config.php: BUFFER_BASE_URL constant
- settings.php: Buffer access-token card with getUser() connection test,
  save/clear handlers
- composer: channels from all three services with ZN/BP/BF badges;
  buildPlatformEntry sends accountId = buffer profile id and skips
  per-platform options for Buffer; tiktok root-settings condition now
  checks service === 'zernio' explicitly
- ajax/create_post.php: create_buffer_update() dispatches ONE update for
  all selected Buffer profiles (caption required, video rejected, single
  image via photoUrl, publish-now or scheduledAt ISO-UTC)
- ajax/action.php: retry = share now, unpublish = destroy update
  (Remove from queue button on post view for scheduled updates)
- posts.php / post_view.php: merged list pulls pending+sent queues per
  Buffer profile (50 each) via normalize_buffer_post(); detail view via
  normalize_buffer_view(); BF service tag
- ajax/bulk_upload.php: CSV rows route to Buffer via buffer_row()
  (matches formatted_username/service_username, rejects video rows,
  requires caption)
- ajax/list_accounts.php / index.php: unified account list includes
  Buffer profiles (bf prefix prevents _id collisions); dashboard gets
  a Buffer tile (5-column stat grid) and partial-failure markers
- ajax/refresh_analytics.php: Buffer analytics from statistics.reach/
  favorites/mentions across all sent queues
- tools.php (new): static reference page — capability matrix with
  ZN/BP/BF support columns plus per-platform cards covering post
  formats, free-plan limits, composer options and usage tips
- docs: AGENTS.md updated (three-service architecture, Buffer client
  section, data-flow notes, progress log)
```

Split-commit alternative:

```
feat: add classic-v1 Buffer API client and settings card
```

```
feat: wire Buffer into composer, posts, bulk upload and analytics
```

```
feat: add tools.php platform capability reference page
```