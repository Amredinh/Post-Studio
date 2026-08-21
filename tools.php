<?php
$page_title = 'Tools & Platforms';
$active = 'tools';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/platforms.php';

$znKeySet = (bool)get_setting('zernio_api_key', '');
$bpKeySet = (bool)get_setting('bulkpublish_api_key', '');
$bfKeySet = (bool)get_setting('buffer_api_key', '');

$platforms = [
    ['slug' => 'twitter', 'name' => 'X (Twitter)', 'zn' => true, 'bp' => true, 'bf' => true,
     'types' => 'Text · Images (≤4) · Video · Threads',
     'limits' => '280 characters on the free plan. Up to 4 images (5 MB each) or one video (2 min 20 s, 512 MB).',
     'tips' => 'Longer text can be split into a thread — BulkPublish does this automatically when Post format is set to Thread. Reply visibility is configurable per post.',
     'opts' => 'Reply settings (who can reply) · first comment (BulkPublish) · thread mode'],
    ['slug' => 'facebook', 'name' => 'Facebook', 'zn' => true, 'bp' => true, 'bf' => true,
     'types' => 'Feed post · Photo/Carousel · Video · Reel · Story',
     'limits' => 'Posts up to ~63k characters, but the feed truncates after a few lines. Reels perform best under 90 s. Stories expire after 24 h.',
     'tips' => 'Links in the caption usually get less reach — put them in the first comment instead. Reels need a short vertical video.',
     'opts' => 'Post format (Feed/Reel/Story) · reel title · share to story · first comment'],
    ['slug' => 'instagram', 'name' => 'Instagram', 'zn' => true, 'bp' => true, 'bf' => true,
     'types' => 'Feed photo · Feed video · Carousel (≤10) · Reel · Story',
     'limits' => 'Captions max 2,200 characters with max 30 hashtags. Single videos publish as Reels automatically. Trial Reels let you test content on non-followers.',
     'tips' => 'Captions do not contain clickable links — use the first-comment field for URLs. Stories expire after 24 h and are best used for time-sensitive posts.',
     'opts' => 'Post format (auto/reel/story) · collaborators · share-to-story · trial reel · first comment'],
    ['slug' => 'linkedin', 'name' => 'LinkedIn', 'zn' => true, 'bp' => true, 'bf' => true,
     'types' => 'Text post · Multi-image · PDF carousel · Article',
     'limits' => '3,000 characters per post (~first 210 shown before “see more”). PDF carousels accept up to 100 MB documents.',
     'tips' => 'External links can cut reach significantly — put them in the first comment. Articles require a URL plus title. Document carousels get strong engagement for B2B.',
     'opts' => 'First comment · article url/title/description · carousel title'],
    ['slug' => 'tiktok', 'name' => 'TikTok', 'zn' => true, 'bp' => true, 'bf' => true,
     'types' => 'Video (required) · Photo slideshow',
     'limits' => 'Video is mandatory for normal posts; photo slideshows are supported via post-type overrides. Captions up to 2,200 characters.',
     'tips' => 'TikTok legally requires consent flags — this app sends content_preview_confirmed and express_consent_given automatically. Mark AI-generated content when applicable.',
     'opts' => 'Privacy level · allow comments/duets/stitches · cover timestamp · AIGC label · brand-content toggles'],
    ['slug' => 'youtube', 'name' => 'YouTube', 'zn' => true, 'bp' => true, 'bf' => true,
     'types' => 'Video · Short',
     'limits' => 'Title max 100 characters (required). Description up to 5,000 characters. Shorts are auto-detected: vertical 9:16 video of 3 minutes or less.',
     'tips' => 'Upload a vertical MP4 under 3 minutes and it publishes as a Short with no extra flag. Set visibility per post; category IDs map to YouTube categories (22 = People & Blogs).',
     'opts' => 'Title (required) · visibility · category ID · made-for-kids · playlist · thumbnail'],
    ['slug' => 'pinterest', 'name' => 'Pinterest', 'zn' => true, 'bp' => true, 'bf' => true,
     'types' => 'Standard pin · Video pin · Carousel',
     'limits' => 'Title max 100 characters (required), description max 500. A destination link makes pins clickable. Boards are required by the API.',
     'tips' => 'Board selection still needs to be configured on the service side for BulkPublish — the app surfaces the client method but not the UI picker yet. Vertical 2:3 images perform best.',
     'opts' => 'Title/description/link · board (via API client) · dominant colour · cover image'],
    ['slug' => 'threads', 'name' => 'Threads', 'zn' => true, 'bp' => true, 'bf' => false,
     'types' => 'Text · Image · Video · Carousel',
     'limits' => '500 characters per post. Carousels up to 20 items. Not available through Buffer’s API yet.',
     'tips' => 'Threads rewards conversational text posts; hashtags behave more like topics than Instagram tags.',
     'opts' => 'Post type override (text/image/video/carousel)'],
    ['slug' => 'bluesky', 'name' => 'Bluesky', 'zn' => true, 'bp' => true, 'bf' => true,
     'types' => 'Text post · Thread',
     'limits' => '300 characters per post on the free plan.',
     'tips' => 'Use thread mode for longer content — BulkPublish splits it into chained posts automatically.',
     'opts' => 'Thread mode (postFormat)'],
    ['slug' => 'mastodon', 'name' => 'Mastodon', 'zn' => false, 'bp' => true, 'bf' => true,
     'types' => 'Text post · Thread',
     'limits' => 'Default instance limit is 500 characters (instances may differ).',
     'tips' => 'Thread mode works the same as Bluesky/X. Instance-specific character limits are enforced by the connected server.',
     'opts' => 'Thread mode (postFormat)'],
    ['slug' => 'tumblr', 'name' => 'Tumblr', 'zn' => false, 'bp' => true, 'bf' => true,
     'types' => 'Text · Image · Video',
     'limits' => 'No strict character cap; blog selection is handled through channel options.',
     'tips' => 'Best for evergreen visual content — reblog culture favours image-heavy posts.',
     'opts' => 'Blog selection via channel options API'],
    ['slug' => 'reddit', 'name' => 'Reddit', 'zn' => false, 'bp' => true, 'bf' => true,
     'types' => 'Text post · Link · Image',
     'limits' => 'Titles max 300 characters. Subreddit and flair rules are enforced by each community.',
     'tips' => 'Subreddit/flair selection uses the channel-options API (client supported, UI picker pending). Self-promotion is heavily moderated — follow each subreddit’s rules.',
     'opts' => 'Subreddit + flair via channel options API'],
    ['slug' => 'googlebusiness', 'name' => 'Google Business', 'zn' => true, 'bp' => true, 'bf' => true,
     'types' => 'Standard update · Event · Offer',
     'limits' => 'Updates up to 1,500 characters; images up to 5 MB. Events/offers carry start/end dates.',
     'tips' => 'Great for local SEO freshness signals. Offers should always include a clear call-to-action and expiry date.',
     'opts' => 'Update type (standard/event/offer)'],
    ['slug' => 'telegram', 'name' => 'Telegram', 'zn' => true, 'bp' => true, 'bf' => false,
     'types' => 'Channel message · Media group',
     'limits' => 'Messages up to 4,096 characters; media albums up to 10 items. Also manageable directly through this app’s built-in Telegram bot page.',
     'tips' => 'The in-app Telegram Bot page can post and pull analytics independently of the scheduling services.',
     'opts' => 'Managed via Zernio/BulkPublish scheduling or the built-in bot'],
    ['slug' => 'discord', 'name' => 'Discord', 'zn' => false, 'bp' => true, 'bf' => false,
     'types' => 'Channel message',
     'limits' => 'Message length 2,000 characters (4,000 with server boosts). Channel targeting requires the channel-options lookup.',
     'tips' => 'First comments are not supported on Discord by BulkPublish — include everything in the main message.',
     'opts' => 'Target channel via channel options API'],
    ['slug' => 'snapchat', 'name' => 'Snapchat', 'zn' => true, 'bp' => false, 'bf' => false,
     'types' => 'Story media',
     'limits' => 'Vertical 9:16 video/images; stories expire after 24 h. Availability depends on your connected Zernio account.',
     'tips' => 'Keep clips short and front-load hooks — completion rate drives Story placement.',
     'opts' => 'Generic media pipeline (no deep options exposed)'],
    ['slug' => 'whatsapp', 'name' => 'WhatsApp', 'zn' => true, 'bp' => false, 'bf' => false,
     'types' => 'Status / broadcast media',
     'limits' => 'Status updates expire after 24 h. Availability depends on your connected Zernio account.',
     'tips' => 'Broadcasts require opted-in recipients; statuses behave like stories.',
     'opts' => 'Generic media pipeline (no deep options exposed)'],
];
?>
<div class="space-y-6">

  <div class="card card-pad">
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex-1 min-w-[240px]">
        <h2 class="font-semibold">Platform capability reference</h2>
        <p class="text-sm text-slate-400 mt-1">What each connected posting service supports, per platform — free-plan limits, post formats, and the options this app exposes in the composer.</p>
      </div>
      <div class="flex gap-2 text-xs">
        <span class="px-2 py-1 rounded bg-violet-500/15 text-violet-300 border border-violet-500/25 font-semibold">ZN · Zernio</span>
        <span class="px-2 py-1 rounded bg-fuchsia-500/15 text-fuchsia-300 border border-fuchsia-500/25 font-semibold">BP · BulkPublish</span>
        <span class="px-2 py-1 rounded bg-sky-500/15 text-sky-300 border border-sky-500/25 font-semibold">BF · Buffer</span>
      </div>
    </div>
    <?php if (!$znKeySet || !$bpKeySet || !$bfKeySet): ?>
      <div class="mt-4 text-xs text-slate-500">
        Connected keys:
        <?= $znKeySet ? '<span class="text-violet-300">Zernio</span>' : '<span class="text-slate-600 line-through">Zernio</span>' ?> ·
        <?= $bpKeySet ? '<span class="text-fuchsia-300">BulkPublish</span>' : '<span class="text-slate-600 line-through">BulkPublish</span>' ?> ·
        <?= $bfKeySet ? '<span class="text-sky-300">Buffer</span>' : '<span class="text-slate-600 line-through">Buffer</span>' ?>
        — add missing keys in <a href="settings.php" class="text-violet-400 hover:text-violet-300">Settings</a>.
      </div>
    <?php endif; ?>
  </div>

  <!-- Capability matrix -->
  <div class="card overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-xs uppercase tracking-wider text-slate-500 border-b border-slate-800">
          <th class="px-4 py-3">Platform</th>
          <th class="px-4 py-3 text-center">ZN</th>
          <th class="px-4 py-3 text-center">BP</th>
          <th class="px-4 py-3 text-center">BF</th>
          <th class="px-4 py-3 hidden md:table-cell">Post types</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($platforms as $p): ?>
          <tr class="border-b border-slate-800/60 last:border-0 hover:bg-slate-800/30">
            <td class="px-4 py-2.5 font-medium"><?= htmlspecialchars($p['name']) ?></td>
            <td class="px-4 py-2.5 text-center"><?= $p['zn'] ? '<span class="text-emerald-400">✓</span>' : '<span class="text-slate-700">—</span>' ?></td>
            <td class="px-4 py-2.5 text-center"><?= $p['bp'] ? '<span class="text-emerald-400">✓</span>' : '<span class="text-slate-700">—</span>' ?></td>
            <td class="px-4 py-2.5 text-center"><?= $p['bf'] ? '<span class="text-emerald-400">✓</span>' : '<span class="text-slate-700">—</span>' ?></td>
            <td class="px-4 py-2.5 text-slate-400 hidden md:table-cell"><?= htmlspecialchars($p['types']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Detail cards -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <?php foreach ($platforms as $p): ?>
      <?php $meta = platform_meta($p['slug']); ?>
      <div class="card card-pad">
        <div class="flex items-center gap-2.5 mb-3">
          <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                style="background:<?= htmlspecialchars($meta['color']) ?>"><?= htmlspecialchars(strtoupper(substr($p['name'], 0, 2))) ?></span>
          <h3 class="font-semibold"><?= htmlspecialchars($p['name']) ?></h3>
          <span class="ml-auto flex gap-1">
            <?php if ($p['zn']): ?><span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-violet-500/15 text-violet-300 border border-violet-500/25">ZN</span><?php endif; ?>
            <?php if ($p['bp']): ?><span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-fuchsia-500/15 text-fuchsia-300 border border-fuchsia-500/25">BP</span><?php endif; ?>
            <?php if ($p['bf']): ?><span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-sky-500/15 text-sky-300 border border-sky-500/25">BF</span><?php endif; ?>
          </span>
        </div>
        <div class="space-y-2 text-sm">
          <div><span class="text-slate-500 text-xs uppercase tracking-wide block mb-0.5">Formats</span><?= htmlspecialchars($p['types']) ?></div>
          <div><span class="text-slate-500 text-xs uppercase tracking-wide block mb-0.5">Free-plan limits</span><?= htmlspecialchars($p['limits']) ?></div>
          <div><span class="text-slate-500 text-xs uppercase tracking-wide block mb-0.5">Composer options</span><?= htmlspecialchars($p['opts']) ?></div>
          <div><span class="text-slate-500 text-xs uppercase tracking-wide block mb-0.5">Tips</span><span class="text-slate-400"><?= htmlspecialchars($p['tips']) ?></span></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- How posting works here -->
  <div class="card card-pad">
    <h3 class="font-semibold mb-2">How multi-service posting works</h3>
    <ul class="text-sm text-slate-400 space-y-1.5 list-disc pl-5">
      <li>The composer lists every connected channel from all three services, tagged <span class="text-violet-300 font-semibold">ZN</span>, <span class="text-fuchsia-300 font-semibold">BP</span> or <span class="text-sky-300 font-semibold">BF</span>.</li>
      <li>Ticking channels from different services creates one post per service automatically — the APIs are incompatible behind the scenes.</li>
      <li>Per-platform options apply where the service supports them; Buffer applies one shared caption/settings set to all its selected profiles.</li>
      <li>If one service fails, the others still publish and any errors are reported per service.</li>
    </ul>
  </div>

</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
