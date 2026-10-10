<?php
declare(strict_types=1);
/**
 * MediaKitSources — ToS-compliant media import for Media Kit.
 *
 * Allowed paths ONLY:
 *  1) Official platform APIs (Meta Graph, YouTube Data, LinkedIn, X) with tenant tokens
 *  2) oEmbed for a single public post URL
 *  3) RSS/Atom feed URL supplied by the tenant
 *  4) Manual entry (caller stores the row)
 *
 * NO profile-page HTML scraping, NO proxy rotation, NO anti-bot evasion.
 *
 * Required scopes (document for tenant admins):
 *  - Meta Graph: pages_read_engagement, instagram_basic, instagram_content_publish (read)
 *    or Instagram Graph: instagram_business_basic / pages_show_list
 *  - YouTube Data API v3: API key (public channel read) or OAuth for private
 *  - LinkedIn: r_organization_social for org posts (admin token)
 *  - X API v2: bearer token with tweet.read + users.read
 */
final class MediaKitSources
{
    public const CACHE_TTL = 900; // 15 minutes

    /** @return array<string,mixed> */
    public static function loadConnections(): array
    {
        $path = self::connectionsPath();
        if ($path === '' || !is_file($path)) {
            return self::emptyConnections();
        }
        $j = json_decode((string)@file_get_contents($path), true);
        if (!is_array($j)) {
            return self::emptyConnections();
        }
        return array_merge(self::emptyConnections(), $j);
    }

    /** @param array<string,mixed> $data */
    public static function saveConnections(array $data): bool
    {
        $path = self::connectionsPath();
        if ($path === '') {
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $clean = self::emptyConnections();
        foreach (['meta', 'youtube', 'linkedin', 'x', 'rss'] as $k) {
            if (isset($data[$k]) && is_array($data[$k])) {
                $clean[$k] = array_merge($clean[$k], $data[$k]);
            }
        }
        // Never write raw secrets into client-visible AppDB; file is under DATA_PATH
        $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json !== false && @file_put_contents($path, $json . "\n", LOCK_EX) !== false;
    }

    /** @return array<string,mixed> */
    public static function emptyConnections(): array
    {
        return [
            'meta' => [
                'access_token' => '',
                'ig_user_id' => '',
                'page_id' => '',
                'enabled' => false,
            ],
            'youtube' => [
                'api_key' => '',
                'channel_id' => '',
                'enabled' => false,
            ],
            'linkedin' => [
                'access_token' => '',
                'org_id' => '',
                'enabled' => false,
            ],
            'x' => [
                'bearer_token' => '',
                'user_id' => '',
                'enabled' => false,
            ],
            'rss' => [
                'feed_url' => '',
                'enabled' => false,
            ],
        ];
    }

    private static function connectionsPath(): string
    {
        if (!defined('DATA_PATH') || DATA_PATH === '') {
            return '';
        }
        return rtrim(str_replace('\\', '/', DATA_PATH), '/') . '/config/mediakit_connections.json';
    }

    /** Public status only — never includes tokens. */
    public static function connectionStatus(): array
    {
        $c = self::loadConnections();
        $out = [];
        $out['meta'] = [
            'connected' => !empty($c['meta']['enabled']) && $c['meta']['access_token'] !== '' && ($c['meta']['ig_user_id'] !== '' || $c['meta']['page_id'] !== ''),
            'label' => 'Meta (Instagram / Facebook)',
            'how' => 'Connect via Meta Graph API: access token + Instagram Business user id (or Page id).',
        ];
        $out['youtube'] = [
            'connected' => !empty($c['youtube']['enabled']) && $c['youtube']['api_key'] !== '' && $c['youtube']['channel_id'] !== '',
            'label' => 'YouTube',
            'how' => 'YouTube Data API v3 key + channel id.',
        ];
        $out['linkedin'] = [
            'connected' => !empty($c['linkedin']['enabled']) && $c['linkedin']['access_token'] !== '' && $c['linkedin']['org_id'] !== '',
            'label' => 'LinkedIn',
            'how' => 'LinkedIn Pages API: org admin token + organization id.',
        ];
        $out['x'] = [
            'connected' => !empty($c['x']['enabled']) && $c['x']['bearer_token'] !== '' && $c['x']['user_id'] !== '',
            'label' => 'X (Twitter)',
            'how' => 'X API v2 bearer token + numeric user id.',
        ];
        $out['rss'] = [
            'connected' => !empty($c['rss']['enabled']) && $c['rss']['feed_url'] !== '',
            'label' => 'RSS / Atom',
            'how' => 'Paste a public feed URL (blog, news, podcast).',
        ];
        return $out;
    }

    /**
     * Fetch recent items from all connected official sources + RSS.
     * @return list<array{platform:string,id:string,caption:string,media_url:string,permalink:string,timestamp:string,title:string}>
     */
    public static function fetchAllConnected(int $limitPerSource = 25): array
    {
        $c = self::loadConnections();
        $items = [];
        if (!empty($c['meta']['enabled']) && $c['meta']['access_token'] !== '') {
            $items = array_merge($items, self::fetchMeta($c['meta'], $limitPerSource));
        }
        if (!empty($c['youtube']['enabled']) && $c['youtube']['api_key'] !== '') {
            $items = array_merge($items, self::fetchYouTube($c['youtube'], $limitPerSource));
        }
        if (!empty($c['linkedin']['enabled']) && $c['linkedin']['access_token'] !== '') {
            $items = array_merge($items, self::fetchLinkedIn($c['linkedin'], $limitPerSource));
        }
        if (!empty($c['x']['enabled']) && $c['x']['bearer_token'] !== '') {
            $items = array_merge($items, self::fetchX($c['x'], $limitPerSource));
        }
        if (!empty($c['rss']['enabled']) && $c['rss']['feed_url'] !== '') {
            $items = array_merge($items, self::fetchRss($c['rss']['feed_url'], $limitPerSource));
        }
        return $items;
    }

    /** @param array<string,mixed> $cfg */
    public static function fetchMeta(array $cfg, int $limit = 25): array
    {
        $token = (string)($cfg['access_token'] ?? '');
        $ig = trim((string)($cfg['ig_user_id'] ?? ''));
        $page = trim((string)($cfg['page_id'] ?? ''));
        $out = [];
        if ($token === '') {
            return $out;
        }
        // Instagram Business media
        if ($ig !== '') {
            $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($ig)
                . '/media?fields=id,caption,media_type,media_url,permalink,timestamp,thumbnail_url'
                . '&limit=' . max(1, min(50, $limit))
                . '&access_token=' . rawurlencode($token);
            $data = self::httpJson($url);
            foreach (($data['data'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $out[] = self::norm(
                    'instagram',
                    (string)($row['id'] ?? ''),
                    (string)($row['caption'] ?? ''),
                    (string)($row['media_url'] ?? $row['thumbnail_url'] ?? ''),
                    (string)($row['permalink'] ?? ''),
                    (string)($row['timestamp'] ?? '')
                );
            }
        }
        // Facebook Page feed (official)
        if ($page !== '') {
            $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($page)
                . '/posts?fields=id,message,full_picture,permalink_url,created_time'
                . '&limit=' . max(1, min(50, $limit))
                . '&access_token=' . rawurlencode($token);
            $data = self::httpJson($url);
            foreach (($data['data'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $out[] = self::norm(
                    'facebook',
                    (string)($row['id'] ?? ''),
                    (string)($row['message'] ?? ''),
                    (string)($row['full_picture'] ?? ''),
                    (string)($row['permalink_url'] ?? ''),
                    (string)($row['created_time'] ?? '')
                );
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $cfg */
    public static function fetchYouTube(array $cfg, int $limit = 25): array
    {
        $key = (string)($cfg['api_key'] ?? '');
        $ch = trim((string)($cfg['channel_id'] ?? ''));
        if ($key === '' || $ch === '') {
            return [];
        }
        // Search recent uploads
        $url = 'https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=' . rawurlencode($ch)
            . '&order=date&type=video&maxResults=' . max(1, min(50, $limit))
            . '&key=' . rawurlencode($key);
        $data = self::httpJson($url);
        $out = [];
        foreach (($data['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $vid = (string)($row['id']['videoId'] ?? '');
            $sn = $row['snippet'] ?? [];
            if (!is_array($sn)) {
                $sn = [];
            }
            $thumb = (string)($sn['thumbnails']['high']['url'] ?? $sn['thumbnails']['default']['url'] ?? '');
            $out[] = self::norm(
                'youtube',
                $vid,
                (string)($sn['description'] ?? ''),
                $thumb,
                $vid !== '' ? ('https://www.youtube.com/watch?v=' . $vid) : '',
                (string)($sn['publishedAt'] ?? ''),
                (string)($sn['title'] ?? '')
            );
        }
        return $out;
    }

    /** @param array<string,mixed> $cfg */
    public static function fetchLinkedIn(array $cfg, int $limit = 25): array
    {
        $token = (string)($cfg['access_token'] ?? '');
        $org = trim((string)($cfg['org_id'] ?? ''));
        if ($token === '' || $org === '') {
            return [];
        }
        // LinkedIn API v2 organization ugc posts (requires r_organization_social)
        $author = str_starts_with($org, 'urn:') ? $org : ('urn:li:organization:' . $org);
        $url = 'https://api.linkedin.com/v2/posts?q=author&author=' . rawurlencode($author)
            . '&count=' . max(1, min(50, $limit)) . '&sortBy=LAST_MODIFIED';
        $data = self::httpJson($url, [
            'Authorization: Bearer ' . $token,
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: 202401',
        ]);
        $out = [];
        foreach (($data['elements'] ?? $data['data'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string)($row['id'] ?? '');
            $commentary = '';
            if (isset($row['commentary']) && is_string($row['commentary'])) {
                $commentary = $row['commentary'];
            } elseif (isset($row['specificContent']['com.linkedin.ugc.ShareContent']['shareCommentary']['text'])) {
                $commentary = (string)$row['specificContent']['com.linkedin.ugc.ShareContent']['shareCommentary']['text'];
            }
            $out[] = self::norm('linkedin', $id, $commentary, '', '', (string)($row['createdAt'] ?? $row['lastModifiedAt'] ?? ''));
        }
        return $out;
    }

    /** @param array<string,mixed> $cfg */
    public static function fetchX(array $cfg, int $limit = 25): array
    {
        $bearer = (string)($cfg['bearer_token'] ?? '');
        $uid = trim((string)($cfg['user_id'] ?? ''));
        if ($bearer === '' || $uid === '') {
            return [];
        }
        $url = 'https://api.twitter.com/2/users/' . rawurlencode($uid)
            . '/tweets?max_results=' . max(5, min(100, $limit))
            . '&tweet.fields=created_at,text,attachments&expansions=attachments.media_keys'
            . '&media.fields=url,preview_image_url';
        $data = self::httpJson($url, ['Authorization: Bearer ' . $bearer]);
        $mediaMap = [];
        foreach (($data['includes']['media'] ?? []) as $m) {
            if (is_array($m) && !empty($m['media_key'])) {
                $mediaMap[(string)$m['media_key']] = (string)($m['url'] ?? $m['preview_image_url'] ?? '');
            }
        }
        $out = [];
        foreach (($data['data'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string)($row['id'] ?? '');
            $mediaUrl = '';
            $keys = $row['attachments']['media_keys'] ?? [];
            if (is_array($keys) && isset($keys[0]) && isset($mediaMap[(string)$keys[0]])) {
                $mediaUrl = $mediaMap[(string)$keys[0]];
            }
            $out[] = self::norm(
                'x',
                $id,
                (string)($row['text'] ?? ''),
                $mediaUrl,
                $id !== '' ? ('https://x.com/i/status/' . $id) : '',
                (string)($row['created_at'] ?? '')
            );
        }
        return $out;
    }

    public static function fetchRss(string $feedUrl, int $limit = 25): array
    {
        $feedUrl = trim($feedUrl);
        if ($feedUrl === '' || !preg_match('~^https?://~i', $feedUrl)) {
            return [];
        }
        $raw = self::httpRaw($feedUrl);
        if ($raw === '') {
            return [];
        }
        $out = [];
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($raw);
        if ($xml === false) {
            return [];
        }
        // RSS 2.0
        if (isset($xml->channel->item)) {
            $i = 0;
            foreach ($xml->channel->item as $item) {
                if ($i++ >= $limit) {
                    break;
                }
                $title = trim((string)($item->title ?? ''));
                $desc = trim(strip_tags((string)($item->description ?? $item->children('content', true)->encoded ?? '')));
                $link = trim((string)($item->link ?? ''));
                $date = trim((string)($item->pubDate ?? ''));
                $guid = trim((string)($item->guid ?? $link));
                $media = '';
                if (isset($item->enclosure['url'])) {
                    $media = (string)$item->enclosure['url'];
                }
                $out[] = self::norm('rss', $guid !== '' ? $guid : sha1($link . $title), $desc, $media, $link, $date, $title);
            }
            return $out;
        }
        // Atom
        if (isset($xml->entry)) {
            $i = 0;
            foreach ($xml->entry as $entry) {
                if ($i++ >= $limit) {
                    break;
                }
                $title = trim((string)($entry->title ?? ''));
                $desc = trim(strip_tags((string)($entry->summary ?? $entry->content ?? '')));
                $link = '';
                if (isset($entry->link['href'])) {
                    $link = (string)$entry->link['href'];
                } elseif (isset($entry->link) && is_string((string)$entry->link)) {
                    $link = (string)$entry->link;
                }
                $date = trim((string)($entry->updated ?? $entry->published ?? ''));
                $id = trim((string)($entry->id ?? $link));
                $out[] = self::norm('rss', $id !== '' ? $id : sha1($link . $title), $desc, '', $link, $date, $title);
            }
        }
        return $out;
    }

    /**
     * oEmbed for a single public post URL (YouTube, official oEmbed endpoints where available).
     * Instagram/Facebook oEmbed require a Meta access token on newer APIs — use connections when present.
     * @return array{ok:bool,title:string,html:string,thumbnail_url:string,provider:string,url:string,message:string}
     */
    public static function oEmbed(string $postUrl): array
    {
        $postUrl = trim($postUrl);
        $empty = ['ok' => false, 'title' => '', 'html' => '', 'thumbnail_url' => '', 'provider' => '', 'url' => $postUrl, 'message' => ''];
        if ($postUrl === '' || !preg_match('~^https?://~i', $postUrl)) {
            $empty['message'] = 'Enter a full https post URL';
            return $empty;
        }
        $low = strtolower($postUrl);
        $endpoint = '';
        $provider = 'web';
        if (str_contains($low, 'youtube.com') || str_contains($low, 'youtu.be')) {
            $endpoint = 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode($postUrl);
            $provider = 'youtube';
        } elseif (str_contains($low, 'twitter.com') || str_contains($low, 'x.com')) {
            $endpoint = 'https://publish.twitter.com/oembed?url=' . rawurlencode($postUrl);
            $provider = 'x';
        } elseif (str_contains($low, 'instagram.com')) {
            // Meta oEmbed requires access token
            $c = self::loadConnections();
            $token = (string)($c['meta']['access_token'] ?? '');
            if ($token === '') {
                $empty['message'] = 'Instagram oEmbed requires a Meta Graph access token (Organisation → Media Kit connections). You can still add the URL manually.';
                $empty['provider'] = 'instagram';
                return $empty;
            }
            $endpoint = 'https://graph.facebook.com/v21.0/instagram_oembed?url=' . rawurlencode($postUrl)
                . '&access_token=' . rawurlencode($token);
            $provider = 'instagram';
        } elseif (str_contains($low, 'facebook.com') || str_contains($low, 'fb.watch')) {
            $c = self::loadConnections();
            $token = (string)($c['meta']['access_token'] ?? '');
            if ($token === '') {
                $empty['message'] = 'Facebook oEmbed requires a Meta Graph access token. Add the post URL manually or connect Meta.';
                $empty['provider'] = 'facebook';
                return $empty;
            }
            $endpoint = 'https://graph.facebook.com/v21.0/oembed_post?url=' . rawurlencode($postUrl)
                . '&access_token=' . rawurlencode($token);
            $provider = 'facebook';
        } elseif (str_contains($low, 'linkedin.com')) {
            $empty['message'] = 'LinkedIn has no public oEmbed. Connect LinkedIn Pages API or add the post manually.';
            $empty['provider'] = 'linkedin';
            return $empty;
        } else {
            $empty['message'] = 'Unsupported URL for oEmbed. Use YouTube, X, or connect Meta for Instagram/Facebook.';
            return $empty;
        }
        $data = self::httpJson($endpoint);
        if ($data === []) {
            $empty['message'] = 'oEmbed request failed or returned empty (private post, or rate limit).';
            $empty['provider'] = $provider;
            return $empty;
        }
        return [
            'ok' => true,
            'title' => (string)($data['title'] ?? $data['author_name'] ?? $provider . ' post'),
            'html' => (string)($data['html'] ?? ''),
            'thumbnail_url' => (string)($data['thumbnail_url'] ?? ''),
            'provider' => $provider,
            'url' => $postUrl,
            'message' => 'ok',
        ];
    }

    /**
     * @return array{platform:string,id:string,caption:string,media_url:string,permalink:string,timestamp:string,title:string}
     */
    private static function norm(
        string $platform,
        string $id,
        string $caption,
        string $mediaUrl,
        string $permalink,
        string $timestamp,
        string $title = ''
    ): array {
        if ($title === '' && $caption !== '') {
            $title = self::s_sub(trim(preg_replace('/\s+/', ' ', $caption) ?? $caption), 0, 80);
        }
        return [
            'platform' => $platform,
            'id' => $id,
            'caption' => $caption,
            'media_url' => $mediaUrl,
            'permalink' => $permalink,
            'timestamp' => $timestamp,
            'title' => $title,
        ];
    }

    private static function s_sub(string $s, int $start, ?int $len = null): string
    {
        if (function_exists('mb_substr')) {
            return $len === null ? (string)mb_substr($s, $start) : (string)mb_substr($s, $start, $len);
        }
        return $len === null ? substr($s, $start) : substr($s, $start, $len);
    }

    /** @param list<string> $headers */
    private static function httpJson(string $url, array $headers = []): array
    {
        $raw = self::httpRaw($url, $headers);
        if ($raw === '') {
            return [];
        }
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }

    /** @param list<string> $headers */
    private static function httpRaw(string $url, array $headers = []): string
    {
        if (!preg_match('~^https?://~i', $url)) {
            return '';
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $hdrs = array_merge([
                'Accept: application/json, application/xml, text/xml, */*',
                'User-Agent: ResourceCentre/1.0 (official-api; +https://arthsathi.com)',
            ], $headers);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPHEADER => $hdrs,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = (string)curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 400) {
                return '';
            }
            return $body;
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 25,
                'header' => implode("\r\n", array_merge(['User-Agent: ResourceCentre/1.0'], $headers)) . "\r\n",
            ],
        ]);
        return (string)@file_get_contents($url, false, $ctx);
    }
}
