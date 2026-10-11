# Post Studio — analytics fix + UI redesign

## Tracking fixes
- `includes/metrics.php` (new): reads likes / comments / **shares** / views from any shape the services return
  (top level, `metrics`, `analytics`, `statistics`, per-platform entries; favorites/reactions, retweets/reposts,
  impressions/reach/plays are all understood).
- `ajax/refresh_analytics.php` (rewritten): pages through up to 300 posts per service, also asks Zernio's
  `/analytics` endpoint, adds per-platform and per-service totals, engagement rate, top posts, a 30-day timeline,
  and stores a daily snapshot (`analytics_snapshots`, created automatically) so growth charts build up over time.
  Partial results are now cached too (a failing service no longer causes repeated API calls).
- `includes/db.php`: `get_engagement_views()` never executed its query (always 0) — fixed.
- `ajax/telegram_bot.php`: Telegram analytics reads numbers through the same extractor.
- `ajax/analytics_debug.php` + "Tracking check" panel on the Analytics page: shows the real fields each service returns.

## New UI
- Light + dark themes (OS default, toggle in the top bar, remembered), responsive shell with mobile drawer and bottom tabs.
- `assets/js/charts.js`: line/area, bar, donut and sparkline charts in plain SVG (no CDN, theme-aware, touch tooltips).
- Dashboard and Analytics pages rebuilt around charts; other pages pick up the new look through the shared styles.

## Deploy
Upload the files (keep your own `config.php`). No SQL import is required; `install.sql` also lists the new table.
