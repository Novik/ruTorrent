<?php

/**
 * NNMClub handler, resilient to the Cloudflare Turnstile that now blocks
 * loginmgr-based authentication (an unauthenticated topic page has no btih
 * hash, which made the upstream checker report every torrent as deleted).
 *
 * Phase 1: scrape the tracker endpoint derived from the torrent's announce
 * URL — download.php and the tracker itself are not behind Cloudflare.
 * Phase 2 (only when scrape says the hash is gone): download the guest
 * .torrent from download.php and compare info hashes. Guest torrents carry a
 * dummy credential; it is replaced with the account passkey before the
 * replacement is loaded.
 *
 * NNMClub issues one passkey per account and writes it into the announce URL
 * of every torrent that account downloads, in either of two forms carrying
 * the same value. The form tracks when the .torrent was downloaded, not
 * which host it announces to: currently served .torrents carry
 * `/PASSKEY/announce` on every official host, legacy-named ones included,
 * while older downloads carry `announce?uk=PASSKEY`. A torrent's own passkey
 * is therefore what its own replacement needs; every form a replacement URL
 * already carries is updated in place, and a URL with no passkey at all gets
 * the query form -- the only form this handler wrote before, and torrents
 * carrying it announce successfully on the live fleet. A passkey is never
 * taken from a different torrent unless that torrent published it in the
 * profile-wide query form (real sessions do carry torrents downloaded from
 * other accounts, whose path-form keys are not this profile's), and never
 * written to a host outside TRACKER_HOSTS.
 */

class NNMClubCheckImpl
{
    /** Default site domain when the topic URL carries no host. */
    private const SITE_DOMAIN = 'nnmclub.to';

    /** Explicit allowlist for topic hosts to avoid requesting arbitrary domains. */
    private const TOPIC_HOSTS = [
        'nnmclub.ru',
        'nnmclub.me',
        'nnmclub.to',
        'nnmclub.name',
        'nnmclub.tv',
        'nnm-club.ru',
        'nnm-club.me',
        'nnm-club.to',
        'nnm-club.name',
        'nnm-club.tv',
    ];

    /**
     * Official tracker hosts that may receive the account passkey.
     * Keep this exact: matching an arbitrary nnmclub-like domain would leak it.
     */
    private const TRACKER_HOSTS = [
        'bt.searchtor.to',
        'ipv6.bt.searchtor.to',
        'nnm-club.ru',
        'bt.nnm-club.ru',
        'bt01.nnm-club.ru',
        'bt02.nnm-club.ru',
        'bt.ipv6.nnm-club.ru',
        'bt01.ipv6.nnm-club.ru',
        'bt02.ipv6.nnm-club.ru',
        'nnm-club.cc',
        'bt.nnm-club.cc',
        'bt01.nnm-club.cc',
        'bt02.nnm-club.cc',
        'bt.ipv6.nnm-club.cc',
        'bt01.ipv6.nnm-club.cc',
        'bt02.ipv6.nnm-club.cc',
        'nnm-club.info',
        'bt.nnm-club.info',
        'bt01.nnm-club.info',
        'bt02.nnm-club.info',
        'bt.ipv6.nnm-club.info',
        'bt01.ipv6.nnm-club.info',
        'bt02.ipv6.nnm-club.info',
        'nnmclub.to',
        'bt.nnmclub.to',
        'bt.ipv6.nnmclub.to',
        'ipv6.nnmclub.to',
    ];

    /** Dummy/guest passkeys that must never be treated as credentials. */
    private const DUMMY_PASSKEY_RE = '/^(?:f{32}|0{32})$/i';

    /** The passkey shape, stated once: 32 alphanumerics. */
    private const PASSKEY_CHARS = '[A-Za-z0-9]{32}';

    private const TOKEN_RE = '/^' . self::PASSKEY_CHARS . '$/D';
    private const TRACKER_URL_RE = '`https?://[A-Za-z0-9.-]+(?::\d+)?/'
        . '(?:' . self::PASSKEY_CHARS . '/)?announce(?:\?uk=' . self::PASSKEY_CHARS . ')?`i';
    /**
     * An announce path in either form. $matches[1] is the path prefix,
     * $matches[2] (when present) the path-form passkey.
     */
    private const ANNOUNCE_PATH_RE = '`^(.*?)/(?:(' . self::PASSKEY_CHARS . ')/)?announce/?$`';

    private const SCRAPE_RESULT_UPTODATE = 1;
    private const SCRAPE_RESULT_NOT_FOUND = 2;
    private const SCRAPE_RESULT_FAILED = 3;

    /** @var false|null|array false = not looked up, null = absent, array = found */
    private static $donor = false;

    private static function log($message)
    {
        ruTrackerChecker::logDebug('[NNMClub] ' . $message);
    }

    // fetch() (not fetchComplex()) deliberately bypasses loginmgr: this handler
    // works as a guest, and a Cloudflare-blocked login would poison the check.
    private static function makeGuestClient()
    {
        $client = new Snoopy();
        $client->read_timeout = 5;
        $client->_fp_timeout = 5;
        $client->agent = ruTrackerChecker::USER_AGENT;
        return $client;
    }

    private static function guestFetch($client, $url, $method = "GET", $contentType = "", $body = "")
    {
        @$client->fetch($url, $method, $contentType, $body);
        // Socket errors are negative; the https path stores curl's exit code,
        // which is below any real HTTP status.
        if ($client->status < 100) {
            self::log("Guest fetch failed: url={$url} status={$client->status}");
        }
    }

    private static function looksLikeChallengePage($html)
    {
        return is_string($html)
            && $html !== ''
            && (bool) preg_match('/cf-chl|turnstile|captcha|cloudflare|just a moment|challenge-platform/i', $html);
    }

    /**
     * Parse and normalize an NNMClub viewtopic URL (?p=... or ?t=...).
     *
     * @return array|null ['host' => string, 'query' => string]
     */
    private static function parseTopicRef($url)
    {
        if (!is_string($url) || $url === '') return null;

        $parts = @parse_url(trim($url));
        if (!is_array($parts)) return null;

        if (isset($parts['scheme']) && !preg_match('`^https?$`i', $parts['scheme'])) {
            return null;
        }

        $hostOnly = isset($parts['host']) ? strtolower($parts['host']) : self::SITE_DOMAIN;
        if (!self::isAllowedTopicHost($hostOnly)) {
            return null;
        }
        $host = $hostOnly;

        if (isset($parts['port'])) {
            $port = (int) $parts['port'];
            if ($port < 1 || $port > 65535) return null;
            $host .= ':' . $port;
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
        if (!preg_match('`^/forum/viewtopic\.php/?$`i', $path)) {
            return null;
        }

        $query = [];
        parse_str(isset($parts['query']) ? $parts['query'] : '', $query);

        foreach (['p', 't'] as $param) {
            if (array_key_exists($param, $query)
                && is_scalar($query[$param])
                && ctype_digit((string) $query[$param])) {
                return [
                    'host' => $host,
                    'query' => $param . '=' . $query[$param],
                ];
            }
        }

        return null;
    }

    private static function isAllowedTopicHost($host)
    {
        $normalized = preg_replace('/^www\./i', '', (string) $host);
        return in_array($normalized, self::TOPIC_HOSTS, true);
    }

    private static function isAllowedTrackerHost($host)
    {
        return is_string($host)
            && in_array(strtolower($host), self::TRACKER_HOSTS, true);
    }

    /**
     * Parse one announce URL into a typed credential descriptor. `mode` names
     * the URL form the passkey was found in: `path` (/PASSKEY/announce, the
     * form in currently served .torrents) or `query` (announce?uk=PASSKEY,
     * the form in older downloads). Both forms carry the same account
     * passkey; when a URL carries both, the query value wins.
     *
     * @return array|null ['mode' => 'path'|'query', 'token' => string, 'announceUrl' => string]
     */
    private static function parseAuthUrl($url)
    {
        if (!is_string($url) || $url === '') return null;

        $url = trim($url);
        $parts = @parse_url($url);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !preg_match('/^https?$/i', $parts['scheme'])
            || !self::isAllowedTrackerHost(strtolower($parts['host']))) {
            return null;
        }
        $path = isset($parts['path']) ? $parts['path'] : '';
        if (!preg_match(self::ANNOUNCE_PATH_RE, $path, $match)) return null;

        $query = array();
        parse_str(isset($parts['query']) ? $parts['query'] : '', $query);
        if (isset($query['uk'])
            && is_scalar($query['uk'])
            && preg_match(self::TOKEN_RE, (string) $query['uk'])
            && !preg_match(self::DUMMY_PASSKEY_RE, (string) $query['uk'])) {
            return array(
                'mode' => 'query',
                'token' => (string) $query['uk'],
                'announceUrl' => $url,
            );
        }

        if (isset($match[2]) && $match[2] !== ''
            && !preg_match(self::DUMMY_PASSKEY_RE, $match[2])) {
            return array(
                'mode' => 'path',
                'token' => $match[2],
                'announceUrl' => $url,
            );
        }
        return null;
    }

    /**
     * Extract a typed credential from announce metadata or raw session bencode.
     *
     * @param  string|array|null $announce
     * @param  string|null       $requiredMode Optional `path` or `query` filter
     * @return array|null
     */
    private static function extractAuth($announce, $requiredMode = null)
    {
        if ($announce === null) return null;

        if (is_array($announce)) {
            foreach ($announce as $value) {
                $auth = self::extractAuth($value, $requiredMode);
                if ($auth !== null) return $auth;
            }
            return null;
        }
        if (!is_string($announce)) return null;

        $auth = self::parseAuthUrl($announce);
        if ($auth !== null && ($requiredMode === null || $auth['mode'] === $requiredMode)) {
            return $auth;
        }

        if (preg_match_all(self::TRACKER_URL_RE, $announce, $matches)) {
            foreach ($matches[0] as $url) {
                $auth = self::parseAuthUrl($url);
                if ($auth !== null && ($requiredMode === null || $auth['mode'] === $requiredMode)) {
                    return $auth;
                }
            }
        }
        return null;
    }

    /**
     * Scan the rTorrent session directory for a donor passkey, consulted only
     * when the torrent being checked carries no usable key of its own. Only a
     * key another torrent published in the query (`uk=`) form is accepted:
     * that form has always been profile-wide, whereas a path-form key inside
     * a foreign torrent belongs to whoever downloaded that file, and real
     * sessions do carry torrents fetched from other accounts. The result (or
     * its absence) is cached per process.
     */
    private static function findDonorAuth()
    {
        if (self::$donor !== false) {
            return self::$donor;
        }
        self::$donor = null;

        $sessionDir = rtrim((string) rTorrentSettings::get()->session, '/');
        if ($sessionDir === '') {
            self::log("No session directory, skipping reusable credential lookup");
            return null;
        }

        $files = @glob($sessionDir . '/*.torrent');
        if (!$files) {
            self::log("No session torrents found for reusable credential lookup");
            return null;
        }

        foreach ($files as $path) {
            // announce keys precede the info dict in bencode order, so a
            // bounded head read finds any credential the file has.
            $head = @file_get_contents($path, false, null, 0, 65536);
            if ($head === false || !preg_match('/nnm-?club|searchtor/i', $head)) {
                continue;
            }
            $auth = self::extractAuth($head, 'query');
            if ($auth !== null) {
                return (self::$donor = $auth);
            }
        }
        self::log("Reusable profile credential not found in session torrents");
        return null;
    }

    private static function rebuildTrackerUrl($parts, $path, $query)
    {
        $url = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
        if (isset($parts['port'])) $url .= ':' . (int) $parts['port'];
        $url .= $path;
        if (count($query)) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    /**
     * Patch every NNMClub announce URL in a torrent with the account passkey.
     *
     * @return bool True when the torrent carries at least one NNMClub
     *              announce URL; each such URL now holds the passkey, whether
     *              or not its text needed changing.
     */
    private static function patchAuthInTorrent($torrent, $auth)
    {
        if (!is_array($auth)
            || !isset($auth['token'])
            || !preg_match(self::TOKEN_RE, (string) $auth['token'])) {
            return false;
        }
        $token = (string) $auth['token'];
        $found = false;

        $announce = $torrent->announce();
        if (is_string($announce) && $announce !== '') {
            $patched = self::injectAuthIntoUrl($announce, $token);
            if ($patched !== null) {
                $found = true;
                if ($patched !== $announce) {
                    $torrent->announce($patched);
                }
            }
        }

        $list = $torrent->announce_list();
        if (is_array($list)) {
            $newList = [];
            $listChanged = false;
            foreach ($list as $tier) {
                $newTier = [];
                $urls = is_array($tier) ? $tier : [$tier];
                foreach ($urls as $url) {
                    $patchedUrl = is_string($url)
                        ? self::injectAuthIntoUrl($url, $token)
                        : null;
                    if ($patchedUrl !== null) {
                        $found = true;
                        if ($patchedUrl !== $url) {
                            $listChanged = true;
                            $url = $patchedUrl;
                        }
                    }
                    $newTier[] = $url;
                }
                $newList[] = $newTier;
            }
            if ($listChanged) {
                $torrent->announce_list($newList);
            }
        }

        return $found;
    }

    /**
     * Write the account passkey into an NNMClub announce URL. Every form the
     * URL already carries is updated in place -- the tracker serves both forms
     * and they hold the same value -- and a URL with no passkey at all gets
     * the query form: the only form this handler wrote before, and torrents
     * carrying it announce successfully on the live fleet.
     *
     * @param  string $token Passkey already validated against TOKEN_RE
     * @return string|null Patched URL (possibly identical to the input), or
     *                     null when the URL is not an NNMClub announce URL
     */
    private static function injectAuthIntoUrl($url, $token)
    {
        if (!is_string($url)) return null;
        $parts = @parse_url($url);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'], $parts['path'])
            || !preg_match('/^https?$/i', $parts['scheme'])
            || !self::isAllowedTrackerHost(strtolower($parts['host']))
            || !preg_match(self::ANNOUNCE_PATH_RE, $parts['path'], $match)) {
            return null;
        }

        $query = array();
        parse_str(isset($parts['query']) ? $parts['query'] : '', $query);

        if (isset($match[2]) && $match[2] !== '') {
            $path = $match[1] . '/' . $token . '/announce';
            if (isset($query['uk'])) {
                $query['uk'] = $token;
            }
        } else {
            $path = $match[1] . '/announce';
            $query['uk'] = $token;
        }
        return self::rebuildTrackerUrl($parts, $path, $query);
    }

    /**
     * Check whether a scrape response lists the given hash. Scrape bodies are
     * tiny bencoded dicts keyed by raw 20-byte hashes, so finding the bencoded
     * key is enough; phase 2 (guest download + hash comparison) independently
     * re-verifies before any replacement happens.
     *
     * @param  string $binaryHash Raw 20-byte hash
     */
    private static function scrapeContainsHash($payload, $binaryHash)
    {
        return is_string($payload)
            && is_string($binaryHash) && strlen($binaryHash) === 20
            && isset($payload[0]) && $payload[0] === 'd'
            && strpos($payload, '20:' . $binaryHash) !== false;
    }

    /** Derive a scrape URL without changing the credential's meaning or case. */
    private static function buildScrapeUrl($auth, $binaryHash)
    {
        if (!is_array($auth) || !isset($auth['mode'], $auth['token'], $auth['announceUrl'])) {
            return null;
        }
        $parts = @parse_url($auth['announceUrl']);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'], $parts['path'])
            || !self::isAllowedTrackerHost(strtolower($parts['host']))) {
            return null;
        }

        $path = preg_replace('`/announce/?$`', '/scrape', $parts['path'], 1, $count);
        if ($count !== 1 || $path === null) return null;

        $query = array();
        parse_str(isset($parts['query']) ? $parts['query'] : '', $query);
        unset($query['info_hash']);
        if ($auth['mode'] === 'query') {
            $query['uk'] = $auth['token'];
        } elseif ($auth['mode'] !== 'path') {
            return null;
        }

        $url = self::rebuildTrackerUrl($parts, $path, $query);
        return $url . (count($query) ? '&' : '?') . 'info_hash=' . rawurlencode($binaryHash);
    }

    /**
     * Scrape the tracker endpoint associated with a typed credential. The
     * passkey is the account's key in either form, so when the credential's
     * own host fails the current official IPv4 endpoint is consulted too, in
     * the same form the credential already uses.
     *
     * @return int One of SCRAPE_RESULT_* constants
     */
    private static function checkViaScrape($auth, $hash)
    {
        if (!is_string($hash) || !preg_match('/^[0-9A-F]{40}$/i', $hash)) {
            self::log("Scrape skipped: invalid info hash format {$hash}");
            return self::SCRAPE_RESULT_FAILED;
        }
        $binary = pack('H*', $hash);

        $urls = array();
        $primary = self::buildScrapeUrl($auth, $binary);
        if ($primary !== null) $urls[] = $primary;
        $mode = isset($auth['mode']) ? $auth['mode'] : null;
        if (isset($auth['token']) && ($mode === 'query' || $mode === 'path')) {
            $fallback = self::buildScrapeUrl(array(
                'mode' => $mode,
                'token' => $auth['token'],
                'announceUrl' => $mode === 'query'
                    ? 'http://bt.searchtor.to/announce?uk=' . rawurlencode($auth['token'])
                    : 'http://bt.searchtor.to/' . rawurlencode($auth['token']) . '/announce',
            ), $binary);
            if ($fallback !== null && !in_array($fallback, $urls, true)) $urls[] = $fallback;
        }
        if (!count($urls)) return self::SCRAPE_RESULT_FAILED;

        $sawNotFound = false;

        foreach ($urls as $url) {
            $host = @parse_url($url, PHP_URL_HOST);
            $host = is_string($host) ? $host : 'unknown';

            $client = ruTrackerChecker::makeClient($url);

            if ($client->status == 200
                && is_string($client->results)
                && $client->results !== '') {
                if (self::scrapeContainsHash($client->results, $binary)) {
                    return self::SCRAPE_RESULT_UPTODATE;
                }
                self::log("Scrape response OK on {$host}, hash {$hash} not found");
                $sawNotFound = true;
                continue;
            }
            self::log("Scrape failed on {$host}: status={$client->status}");
        }

        return $sawNotFound
            ? self::SCRAPE_RESULT_NOT_FOUND
            : self::SCRAPE_RESULT_FAILED;
    }

    /**
     * Check whether an NNMClub torrent needs updating.
     *
     * @param  string  $url         Topic URL (viewtopic.php?p=... or ?t=...)
     * @param  string  $hash        Current info hash (hex, uppercase, 40 chars)
     * @param  Torrent $old_torrent Current torrent object (from session)
     * @return int     One of ruTrackerChecker::STE_* constants
     */
    public static function download_torrent($url, $hash, $old_torrent)
    {
        $hash = strtoupper((string) $hash);

        // Prefer matched URL, but fallback to torrent comment if handler was
        // invoked via announce URL.
        $topicRef = self::parseTopicRef($url);
        if ($topicRef === null && is_object($old_torrent) && method_exists($old_torrent, 'comment')) {
            $topicRef = self::parseTopicRef($old_torrent->comment());
        }
        if ($topicRef !== null) {
            self::log("Start check for {$hash} using {$topicRef['host']}/forum/viewtopic.php?{$topicRef['query']}");
        } else {
            self::log("Start announce-only check for {$hash}; guest replacement will be unavailable");
        }

        $announces = array($url);
        if (is_object($old_torrent)) {
            if (method_exists($old_torrent, 'announce')) $announces[] = $old_torrent->announce();
            if (method_exists($old_torrent, 'announce_list')) $announces[] = $old_torrent->announce_list();
        }
        // The passkey this torrent itself announces with is the account's own
        // key (see the file header), so one credential serves both the scrape
        // and, if it comes to that, the replacement patch. Prefer the current
        // path form when a torrent happens to carry both; a donor from the
        // session is consulted only when the torrent carries no key at all.
        $auth = self::extractAuth($announces, 'path') ?: self::extractAuth($announces, 'query');
        if ($auth !== null) {
            self::log("Using the {$auth['mode']}-form credential from current torrent metadata");
        } else {
            $auth = self::findDonorAuth();
            if ($auth !== null) {
                self::log("Using reusable profile credential from a session torrent");
            } else {
                self::log("No tracker credential found for {$hash}, skipping scrape");
            }
        }

        // Phase 1: tracker scrape (fast path).
        if ($auth !== null) {
            $scrapeResult = self::checkViaScrape($auth, $hash);
            if ($scrapeResult === self::SCRAPE_RESULT_UPTODATE) {
                return ruTrackerChecker::STE_UPTODATE;
            }
            if ($scrapeResult === self::SCRAPE_RESULT_FAILED) {
                self::log("All scrape hosts failed for {$hash}");
            }
            if ($scrapeResult === self::SCRAPE_RESULT_NOT_FOUND) {
                self::log("Scrape did not find hash {$hash}, falling back to guest download");
            }
        }

        if ($topicRef === null) {
            self::log("No topic reference for {$hash}; guest download and replacement are unavailable");
            return ruTrackerChecker::STE_NOT_NEED;
        }
        $siteDomain = $topicRef['host'];
        $topicQuery = $topicRef['query'];

        // Phase 2: guest topic page + torrent download.
        $client = self::makeGuestClient();
        self::guestFetch($client, "https://{$siteDomain}/forum/viewtopic.php?" . $topicQuery);
        if ($client->status != 200) {
            self::log("viewtopic fetch failed: status={$client->status}");
            return ruTrackerChecker::STE_CANT_REACH_TRACKER;
        }

        // btih shortcut (present only for authenticated sessions).
        if (preg_match('`btih:(?P<hash>[0-9A-Fa-f]{40})`', $client->results, $btihMatch)) {
            if (strtoupper($btihMatch['hash']) === $hash) {
                return ruTrackerChecker::STE_UPTODATE;
            }
            self::log("Topic btih differs for {$topicQuery}, verifying via downloaded .torrent");
        }

        if (!preg_match('`(?:/forum/)?download\.php\?id=(?P<dlid>\d+)`i', $client->results, $dlMatch)) {
            if (self::looksLikeChallengePage($client->results)) {
                self::log("No download link for {$topicQuery}: challenge page detected");
                return ruTrackerChecker::STE_CANT_REACH_TRACKER;
            }
            self::log("No download link for {$topicQuery}: unexpected topic page format");
            return ruTrackerChecker::STE_ERROR;
        }
        $downloadId = $dlMatch['dlid'];

        $client->setcookies();
        self::guestFetch($client, "https://{$siteDomain}/forum/download.php?id=" . $downloadId);
        if ($client->status != 200 || empty($client->results)) {
            self::log("download.php failed: status={$client->status} id={$downloadId}");
            return ($client->status < 0)
                ? ruTrackerChecker::STE_CANT_REACH_TRACKER
                : ruTrackerChecker::STE_ERROR;
        }

        $guestData = $client->results;

        // Suppress PHP 7.4's filename-probe warning for binary metainfo.
        $guestTorrent = @new Torrent($guestData);
        if ($guestTorrent->errors()) {
            self::log("Failed to parse downloaded torrent for {$topicQuery}");
            return ruTrackerChecker::STE_ERROR;
        }

        $guestHash = strtoupper((string) $guestTorrent->hash_info());
        if ($guestHash === $hash) {
            self::log("Guest torrent hash matches current hash for {$topicQuery}");
            return ruTrackerChecker::STE_UPTODATE;
        }

        // Hash differs: the torrent was updated on NNMClub.
        self::log("Hash changed for {$topicQuery}: {$hash} -> {$guestHash}");
        if ($auth === null) {
            self::log("Hash differs but no account passkey is available; refusing replacement");
            return ruTrackerChecker::STE_ERROR;
        }
        self::log("Patching the replacement with the {$auth['mode']}-form passkey");
        if (!self::patchAuthInTorrent($guestTorrent, $auth)) {
            self::log("Hash differs but credential patch found no NNMClub announce URLs");
            return ruTrackerChecker::STE_ERROR;
        }

        $replaceResult = ruTrackerChecker::createTorrent((string) $guestTorrent, $hash);
        self::log("createTorrent result for {$hash}: " . var_export($replaceResult, true));
        return $replaceResult;
    }
}

// Register this tracker handler with ruTrackerChecker.
// First regex: matches the torrent's comment URL.
// Second regex also covers the current official searchtor announcers.
ruTrackerChecker::registerTracker(
    "/(nnm-club|nnmclub)\./",
    "/(?:nnm-club|nnmclub)\.|(?:ipv6\.)?bt\.searchtor\.to/i",
    "NNMClubCheckImpl::download_torrent"
);
