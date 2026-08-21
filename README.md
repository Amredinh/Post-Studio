# Post Studio

A lightweight, cPanel-hostable **PHP/MySQL** dashboard for creating and scheduling social-media posts through **three posting services at once** — [Zernio](https://zernio.com), [BulkPublish](https://app.bulkpublish.com) and [Buffer](https://buffer.com).

The composer lists all connected channels from **all services**, you tick which platforms to post to, attach media, and Post Studio creates the post on the correct service(s). Mixed selections (Zernio + BulkPublish + Buffer channels in one run) automatically create **one post per service**. Buffer applies one shared caption/settings set to all its selected profiles.

## Features

- Single admin login with first-run setup (bcrypt, CSRF-protected)
- Composer for **Zernio**, **BulkPublish** and **Buffer** channels in one screen, grouped by platform with ZN/BP/BF badges
- Per-platform options: post format (reel/story/carousel/thread…), title, privacy, first comment, custom content — applied where the target service supports them
- Schedule posts (publish now, or date/time + timezone)
- Media: browser uploads via Zernio presigned URLs or a server-side proxy to BulkPublish `/api/media`; media-URL input also supported
- Merged post list and detail view across all three services (retry / unpublish / publish-now / remove-from-queue)
- Bulk CSV scheduling with a downloadable template — rows are routed per service via a `service` column (`zernio` | `bulkpublish` | `buffer`)
- Analytics dashboard: reach/likes/comments across all services, cached locally (30-min TTL, 60-s refresh throttle) to protect free-tier API quotas; tracked post views on detail pages
- **Tools & Platforms reference page**: capability matrix (which service supports which platform) plus per-platform free-plan limits, formats, composer options and tips
- **Telegram bot integration** — post from Telegram, check status/analytics, get help
- Dark, modern dashboard UI (Tailwind CSS via CDN — no build step)

## Tech stack

- **PHP 7.4+** (plain cURL + PDO, no framework)
- **MySQL / MariaDB** (cPanel / phpMyAdmin)
- **JavaScript** (vanilla, single file)
- **Tailwind CSS** via CDN + one hand-written stylesheet

## Getting started

### Requirements

- PHP 7.4+ with `curl`, `pdo_mysql`, `mbstring`
- MySQL/MariaDB database
- API credentials:
  - Zernio API key (`sk_...`)
  - BulkPublish API key (`bp_...`) — from app.bulkpublish.com → Settings → API Keys
  - Buffer access token (classic API v1) — from buffer.com → Settings (optional)
- Telegram bot token (optional, for the Telegram integration)

### Setup

1. Clone or upload the files to your web root.
2. **Copy `config.example.php` to `config.php`** and fill in your database credentials.
   Change `APP_SECRET` to a long random string. Set `APP_DEBUG` to `false` in production.
   `config.php` is git-ignored, so your real credentials stay on the server and never
   conflict with future git pulls.
3. Import `install.sql` in phpMyAdmin (creates `users`, `settings`, `posts`, `posts_engagement`).
4. Open the site → `login.php` creates your admin account on first run.
5. Go to **Settings** → paste your Zernio and/or BulkPublish and/or Buffer keys → confirm
   the green "connected" badges (each card has a connection test).
6. (Optional) Enter your Telegram bot token in Settings → use /help, /post, /status commands.
7. Open **Compose Post** — channels from all connected services appear with ZN/BP/BF badges.
8. Open **Posts** — merged list from every service; use "Refresh" to reload data without a
   full page load.
9. Check **Tools & Platforms** for each platform's free-plan limits and which service
   supports what before composing.

### Deployment to cPanel

> **Note:** if your host's antivirus flags the site as `Sanesecurity.Foxhole.JS_Zip_23.UNOFFICIAL` when you upload a `.zip`, that's a known ClamAV heuristic false positive triggered by any zip containing a `.js` file. Upload the folder directly via File Manager (or FTP) instead of a zip — no code changes needed.

> **Git updates:** because `config.php` is untracked, cPanel's Git Version Control
> ("Update from Remote") can never overwrite or conflict with your live credentials.
> Upgrading from an older deployment where `config.php` was tracked? Back up the file,
> run `git stash`, pull, then restore it — see AGENTS.md Section 13.

## Documentation

See [`AGENTS.md`](AGENTS.md) for a full self-contained developer guide: database schema, all three API client docs (including Buffer's classic-v1 quirks — form-urlencoded requests, unix timestamps, `bf` ID prefixes), payload shapes, data flow, testing instructions, and the deployment checklist. Also see `gitnote.md` for git workflow notes.

## License

Copyright © 2026. All rights reserved.
