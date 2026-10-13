<?php
/**
 * Metric normalisation helpers.
 *
 * Every service names its engagement counters differently (likes / favorites /
 * reactions, shares / reposts / retweets, views / impressions / reach ...) and
 * nests them in different places (top level, metrics, analytics, statistics ...).
 * ps_extract_metrics() understands all of those shapes and always returns the
 * same six counters, so the rest of the app never needs to care.
 */

/** Candidate key names per normalised counter, in priority order. */
function ps_metric_keys(): array {
    return [
        'views'    => ['views', 'view_count', 'viewCount', 'impressions', 'impression_count', 'impressionCount',
                       'reach', 'plays', 'play_count', 'playCount', 'video_views', 'videoViews'],
        'likes'    => ['likes', 'like_count', 'likeCount', 'favorites', 'favorite_count', 'favoriteCount',
                       'favourites', 'reactions', 'reaction_count', 'reactionCount'],
        'comments' => ['comments', 'comment_count', 'commentCount', 'replies', 'reply_count', 'replyCount', 'mentions'],
        'shares'   => ['shares', 'share_count', 'shareCount', 'reposts', 'repost_count', 'repostCount',
                       'retweets', 'retweet_count', 'retweetCount'],
        'saves'    => ['saves', 'save_count', 'saveCount', 'bookmarks', 'bookmark_count', 'bookmarkCount'],
        'clicks'   => ['clicks', 'click_count', 'clickCount', 'link_clicks', 'linkClicks'],
    ];
}

/** Keys under which a post may nest its metrics. */
function ps_metric_containers(): array {
    return ['metrics', 'analytics', 'statistics', 'stats', 'insights', 'engagement', 'performance'];
}

function ps_empty_metrics(): array {
    return ['views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'clicks' => 0, 'found' => false];
}

/** First numeric value in $src for any of $keys, or null. */
function ps_pick(array $src, array $keys): ?int {
    foreach ($keys as $k) {
        if (array_key_exists($k, $src) && is_numeric($src[$k])) {
            return (int)$src[$k];
        }
    }
    return null;
}

/** Extract counters from a single node (no descent into platform lists). */
function ps_extract_flat(array $node): array {
    $out = ps_empty_metrics();
    $sources = [$node];
    foreach (ps_metric_containers() as $c) {
        if (isset($node[$c]) && is_array($node[$c])) {
            $sources[] = $node[$c];
        }
    }
    foreach (ps_metric_keys() as $metric => $keys) {
        foreach ($sources as $src) {
            $v = ps_pick($src, $keys);
            if ($v !== null) {
                $out[$metric] = $v;
                $out['found'] = true;
                break;
            }
        }
    }
    return $out;
}

/**
 * Extract counters from a post. If the post carries no metrics of its own but
 * its per-platform entries do, those are summed.
 */
function ps_extract_metrics(array $post): array {
    $m = ps_extract_flat($post);
    if ($m['found']) {
        return $m;
    }
    foreach (['platforms', 'postPlatforms'] as $listKey) {
        if (!empty($post[$listKey]) && is_array($post[$listKey])) {
            $sum = ps_empty_metrics();
            foreach ($post[$listKey] as $pl) {
                if (!is_array($pl)) continue;
                $pm = ps_extract_flat($pl);
                if (!$pm['found']) continue;
                $sum['found'] = true;
                foreach (['views', 'likes', 'comments', 'shares', 'saves', 'clicks'] as $k) {
                    $sum[$k] += $pm[$k];
                }
            }
            if ($sum['found']) {
                return $sum;
            }
        }
    }
    return $m;
}

/** Interactions = everything a viewer actively did. */
function ps_engagements(array $m): int {
    return (int)($m['likes'] ?? 0) + (int)($m['comments'] ?? 0) + (int)($m['shares'] ?? 0) + (int)($m['saves'] ?? 0);
}

/** Engagement rate in percent (0 when there are no views). */
function ps_engagement_rate(array $m): float {
    $views = (int)($m['views'] ?? 0);
    if ($views <= 0) return 0.0;
    return round(ps_engagements($m) / $views * 100, 2);
}

/** Pick the best timestamp (unix) for when a post went out. */
function ps_post_time(array $p): int {
    foreach (['publishedAt', 'published_at', 'sent_at', 'scheduledFor', 'scheduledAt', 'scheduled_for', 'due_at', 'createdAt', 'created_at'] as $k) {
        if (empty($p[$k])) continue;
        $v = $p[$k];
        $t = is_numeric($v) ? (int)$v : (strtotime((string)$v) ?: 0);
        if ($t > 0) return $t;
    }
    return 0;
}
