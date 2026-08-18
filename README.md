# Post Studio

A lightweight, cPanel-hostable **PHP/MySQL** dashboard for creating and scheduling social-media posts through **two posting services at once** — [Zernio](https://zernio.com) and [BulkPublish](https://app.bulkpublish.com).

The composer lists all connected channels from **both services**, you tick which platforms to post to, attach media, and Post Studio creates the post on the correct service(s). Mixed selections (Zernio + BulkPublish in one run) automatically create **one post per service**.

## Features

- Single admin login with first-run setup (bcrypt, CSRF-protected)
- Composer for **Zernio** and **BulkPublish** channels in one screen, grouped by platform with ZN/BP badges
- Per-platform options: post format, title, privacy, first comment, custom content
- Schedule posts (publish now, or date/time + timezone)
- Media: browser uploads via Zernio presigned URLs or a server-side proxy to BulkPublish `/api/media`; media-URL input also supported
- Merged post list and detail view across both services (retry / unpublish / publish-now)
- Bulk CSV scheduling with a downloadable template
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
- API keys: Zernio (`sk_...`) and/or BulkPublish (`bp_...`)

### Setup

1. Clone or upload the files to your web root.
2. Edit `config.php` with your database credentials and change `APP_SECRET` to a long random string. Set `APP_DEBUG` to `false` in production.
3. Import `install.sql` in phpMyAdmin (creates `users`, `settings`, `posts`).
4. Open the site → `login.php` creates your admin account on first run.
5. Go to **Settings** → paste your Zernio and/or BulkPublish API keys → confirm the green "connected" badges.
6. Open **Compose Post** — channels from both services appear with ZN/BP badges.

### Deployment to cPanel

> **Note:** if your host's antivirus flags the site as `Sanesecurity.Foxhole.JS_Zip_23.UNOFFICIAL` when you upload a `.zip`, that's a known ClamAV heuristic false positive triggered by any zip containing a `.js` file. Upload the folder directly via File Manager (or FTP) instead of a zip — no code changes needed.

## Documentation

See [`AGENTS.md`](AGENTS.md) for a full self-contained developer guide: database schema, both API client docs, payload shapes, data flow, testing instructions, and the deployment checklist.

## License

Copyright © 2026. All rights reserved.