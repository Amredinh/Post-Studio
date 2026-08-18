<?php
/**
 * Platform metadata + display helpers.
 */

function platform_meta(string $platform): array {
    $map = [
        'twitter'        => ['name' => 'X (Twitter)',      'short' => 'X',   'color' => '#1D9BF0', 'dark' => false],
        'instagram'      => ['name' => 'Instagram',        'short' => 'IG',  'color' => '#E4405F', 'dark' => false],
        'facebook'       => ['name' => 'Facebook',         'short' => 'FB',  'color' => '#1877F2', 'dark' => false],
        'linkedin'       => ['name' => 'LinkedIn',         'short' => 'in',  'color' => '#0A66C2', 'dark' => false],
        'tiktok'         => ['name' => 'TikTok',           'short' => 'TT',  'color' => '#111827', 'dark' => true],
        'youtube'        => ['name' => 'YouTube',          'short' => 'YT',  'color' => '#FF0000', 'dark' => false],
        'pinterest'      => ['name' => 'Pinterest',        'short' => 'Pin', 'color' => '#E60023', 'dark' => false],
        'reddit'         => ['name' => 'Reddit',           'short' => 'rd',  'color' => '#FF4500', 'dark' => false],
        'bluesky'        => ['name' => 'Bluesky',          'short' => 'BS',  'color' => '#0085FF', 'dark' => false],
        'threads'        => ['name' => 'Threads',          'short' => 'TH',  'color' => '#4a4a5a', 'dark' => true],
        'googlebusiness' => ['name' => 'Google Business',  'short' => 'G',   'color' => '#4285F4', 'dark' => false],
        'telegram'       => ['name' => 'Telegram',         'short' => 'TG',  'color' => '#229ED9', 'dark' => false],
        'snapchat'       => ['name' => 'Snapchat',         'short' => 'SC',  'color' => '#FFFC00', 'dark' => true],
        'whatsapp'       => ['name' => 'WhatsApp',         'short' => 'WA',  'color' => '#25D366', 'dark' => false],
        'discord'        => ['name' => 'Discord',          'short' => 'DC',  'color' => '#5865F2', 'dark' => false],
        'slack'          => ['name' => 'Slack',            'short' => 'SL',  'color' => '#4A154B', 'dark' => false],
    ];
    return $map[$platform] ?? [
        'name' => ucfirst(str_replace('_', ' ', $platform)),
        'short' => strtoupper(substr($platform, 0, 2)),
        'color' => '#64748b',
        'dark' => false,
    ];
}

/** Render a platform badge (colored circle + label). */
function platform_badge(string $platform): string {
    $m = platform_meta($platform);
    $text = $m['dark'] ? 'text-slate-900' : 'text-white';
    return '<span class="inline-flex items-center gap-2">
        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold ' . $text . '" style="background:' . htmlspecialchars($m['color']) . '">' . htmlspecialchars($m['short']) . '</span>
        <span>' . htmlspecialchars($m['name']) . '</span>
    </span>';
}

/** Render a small status badge for a post status. */
function status_badge(string $status): string {
    $colors = [
        'scheduled'  => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
        'draft'      => 'bg-slate-500/15 text-slate-300 border-slate-500/30',
        'publishing' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'processing' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'published'  => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'partial'    => 'bg-orange-500/15 text-orange-300 border-orange-500/30',
        'failed'     => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        'cancelled'  => 'bg-slate-600/15 text-slate-400 border-slate-600/30',
        'pending'    => 'bg-slate-500/15 text-slate-300 border-slate-500/30',
    ];
    $c = $colors[$status] ?? $colors['draft'];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ' . $c . '">' . htmlspecialchars(ucfirst($status)) . '</span>';
}

/** Common list of platforms for dropdowns/filters. */
function platform_list(): array {
    return [
        'twitter', 'instagram', 'facebook', 'linkedin', 'tiktok', 'youtube',
        'pinterest', 'reddit', 'bluesky', 'threads', 'googlebusiness',
        'telegram', 'snapchat', 'whatsapp', 'discord', 'slack',
    ];
}