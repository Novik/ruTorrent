<?php

/**
 * NNMClub Torrent Checker — Cloudflare-Resilient Override
 *
 * This file overrides the upstream trackers/nnmclub.php to work around
 * Cloudflare Turnstile CAPTCHA protection on NNMClub's website.
 *
 * ## Background
 *
 * NNMClub (nnmclub.to) is a BitTorrent tracker forum based on phpBB.
 * The upstream checker fetches the topic page (viewtopic.php) and looks for
 * a magnet `btih:` hash in the HTML to detect torrent updates. However,
 * NNMClub is now behind Cloudflare Turnstile, which blocks automated logins
 * via the `loginmgr` plugin. Without authentication, the topic page is
 * served as a "guest" page (~16 KB) that does NOT contain the btih hash.
 * This causes the upstream code to always return STE_DELETED (false positive).
 *
 * ## Key Discoveries
 *
 * 1. The guest page DOES contain a `download.php?id=...` link.
 * 2. The `download.php` endpoint is NOT behind Cloudflare and returns
 *    a valid .torrent file (~521 KB) even for unauthenticated requests.
 * 3. Guest-downloaded torrents contain a dummy passkey (`ffffffff...`)
 *    in the announce URL instead of the user's real passkey.
 * 4. The BitTorrent tracker (bt02.nnm-club.cc:2710) is NOT behind
 *    Cloudflare and responds to scrape requests directly.
 * 5. NNMClub passkeys are per-user (not per-torrent): any passkey from
 *    the same user account works for scraping any torrent.
 *
 * ## Two-Phase Algorithm
 *
 * Phase 1 — Tracker Scrape (fast, ~67 bytes response):
 *   Scrapes bt02.nnm-club.cc directly with the user's passkey extracted
 *   from an existing NNMClub torrent. If the current info_hash is found
 *   in the scrape response, the torrent is up-to-date. This handles
 *   ~99% of checks with minimal bandwidth.
 *   Note: Dummy/guest passkeys (all-f's) are filtered out and never used.
 *
 * Phase 2 — Guest Torrent Download (only if scrape says "not found"):
 *   Downloads the guest .torrent from download.php, compares its info_hash
 *   with the current hash. If they differ, the torrent was updated on NNMClub.
 *   The dummy passkey in the downloaded torrent is patched with the real one
 *   before handing it to createTorrent() for replacement.
 *
 * ## Session Patching
 *
 * If a torrent in the rtorrent session lacks a passkey in its announce URL
 * (e.g., previously downloaded without authentication), and a donor passkey
 * is found from another NNMClub torrent, the session .torrent file is patched
 * using the Torrent class.  libtorrent_resume and rtorrent metadata are
 * preserved so fast-resume works correctly after restart.
 *
 * @see https://nnmclub.to  NNMClub tracker forum
 */

class NNMClubCheckImpl
{
    private const DEFAULT_USER_AGENT =
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
      . "AppleWebKit/537.36 (KHTML, like Gecko) "
      . "Chrome/120.0.0.0 Safari/537.36";

    /**
     * Tracker hostnames for scrape requests (NOT behind Cloudflare).
     * If the first host fails, the next is tried.
     */
    private const TRACKER_HOSTS = ['bt02.nnm-club.cc', 'bt02.nnm-club.info'];
    private const TRACKER_PORT  = 2710;

    /**
     * Default site domain for viewtopic/download requests.
     * Used as fallback when the domain from comment URL is not available.
     */
    private const SITE_DOMAIN = 'nnmclub.to';

    /**
     * Explicit allowlist for topic hosts to avoid requesting arbitrary domains.
     */
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
     * Regex to match dummy/guest passkeys that must be ignored.
     * Guest downloads produce all-f's; all-zeros is another degenerate case.
     */
    private const DUMMY_PASSKEY_RE = '/^(?:f{32}|0{32})$/i';

    /**
     * Regex fragment for NNMClub hostnames in announce URLs.
     * Port is optional to support URLs with implicit default ports.
     */
    private const ANNOUNCE_HOST_RE = '(?:nnm-club|nnmclub)\.\w+(?::\d+)?';

    /**
     * Regex to extract a 32-character hex passkey from an NNMClub announce URL.
     * Supports both domain formats: nnm-club.* and nnmclub.*.
     */
    private const TOKEN_RE = '`' . self::ANNOUNCE_HOST_RE . '/([0-9a-f]{32})/announce`i';

    private const SCRAPE_RESULT_UPTODATE = 1;
    private const SCRAPE_RESULT_NOT_FOUND = 2;
    private const SCRAPE_RESULT_FAILED = 3;

    /** @var string|null Cached rtorrent session directory path */
    private static $sessionDirCache = null;

    /** @var bool True once session dir lookup has been attempted */
    private static $sessionDirCacheLoaded = false;

    /** @var bool True once donor passkey lookup has been attempted */
    private static $donorPasskeyCacheLoaded = false;

    /** @var string|null Cached donor passkey (null means not found) */
    private static $donorPasskeyCache = null;

    // ====================================================================
    // Logging
    // ====================================================================

    /**
     * Log a message through ruTrackerChecker::logDebug().
     * @param string $message  Message (auto-prefixed with [NNMClub])
     */
    private static function log($message)
    {
        ruTrackerChecker::logDebug('[NNMClub] ' . $message);
    }

    /**
     * Build a plain Snoopy client for "guest mode" requests.
     * Intentionally uses fetch() (not fetchComplex()) to bypass loginmgr logic.
     *
     * @return Snoopy
     */
    private static function makeGuestClient()
    {
        $client = new Snoopy();
        $client->read_timeout = 5;
        $client->_fp_timeout = 5;
        $client->agent = self::DEFAULT_USER_AGENT;
        return $client;
    }

    /**
     * Execute a single guest HTTP request with logging for transport failures.
     *
     * @param  Snoopy $client
     * @param  string $url
     * @param  string $method
     * @param  string $contentType
     * @param  string $body
     * @return void
     */
    private static function guestFetch($client, $url, $method = "GET", $contentType = "", $body = "")
    {
        @$client->fetch($url, $method, $contentType, $body);
        if ($client->status < 0) {
            self::log("Guest fetch failed: url={$url} status={$client->status}");
        }
    }

    /**
     * Detect common anti-bot/challenge pages where download link is absent.
     *
     * @param  string $html
     * @return bool
     */
    private static function looksLikeChallengePage($html)
    {
        return is_string($html)
            && $html !== ''
            && (bool) preg_match('/cf-chl|turnstile|captcha|cloudflare|just a moment|challenge-platform/i', $html);
    }

    /**
     * Parse and normalize an NNMClub viewtopic URL.
     * Supports both ?p=... and ?t=... topic references.
     *
     * @param  string $url
     * @return array|null ['host' => string, 'query' => string, 'id' => string]
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
            if (array_key_exists($param, $query) && ctype_digit((string) $query[$param])) {
                return [
                    'host' => $host,
                    'query' => $param . '=' . $query[$param],
                    'id' => (string) $query[$param],
                ];
            }
        }

        return null;
    }

    /**
     * Validate topic host against a strict allowlist.
     *
     * @param  string $host
     * @return bool
     */
    private static function isAllowedTopicHost($host)
    {
        return in_array($host, self::TOPIC_HOSTS, true);
    }

    // ====================================================================
    // Passkey Discovery
    // ====================================================================

    /**
     * Extract a real 32-hex passkey from an announce URL or announce-list.
     * Dummy/guest passkeys (all-f's, all-zeros) are filtered out.
     *
     * @param  string|array|null $announce  Single URL, announce-list array, or null
     * @return string|null  Lowercase 32-hex passkey, or null if not found / dummy
     */
    private static function extractPasskey($announce)
    {
        if ($announce === null) return null;

        if (is_array($announce)) {
            foreach ($announce as $tier) {
                $urls = is_array($tier) ? $tier : [$tier];
                foreach ($urls as $url) {
                    $pk = self::extractPasskey($url);
                    if ($pk !== null) return $pk;
                }
            }
            return null;
        }

        // Accept any string (URL or raw bencode data from findDonorPasskey)
        if (!is_string($announce)) return null;

        if (preg_match(self::TOKEN_RE, $announce, $m)) {
            $pk = strtolower($m[1]);
            if (preg_match(self::DUMMY_PASSKEY_RE, $pk)) {
                return null;  // Reject dummy passkey
            }
            return $pk;
        }
        return null;
    }

    /**
     * Scan rtorrent session directory for any NNMClub torrent with a real passkey.
     *
     * Since NNMClub passkeys are per-user (all interchangeable), we can
     * borrow a passkey from ANY NNMClub torrent belonging to the same user.
     *
     * Optimization: reads only the first 4 KB for quick rejection and
     * passkey extraction before falling back to full file read.
     *
     * @return string|null  32-hex passkey from a donor torrent, or null
     */
    private static function findDonorPasskey()
    {
        if (self::$donorPasskeyCacheLoaded) {
            self::log("Using cached donor passkey lookup result");
            return self::$donorPasskeyCache;
        }

        $sessionDir = self::getSessionDir();
        if ($sessionDir === null) return self::cacheDonorPasskey(null);

        $sessionDir = rtrim($sessionDir, '/');
        if ($sessionDir === '') {
            self::log("Invalid session dir from get_session, skipping donor passkey lookup");
            return self::cacheDonorPasskey(null);
        }

        $files = @glob($sessionDir . '/*.torrent');
        if (!$files) {
            self::log("No session torrents found for donor passkey lookup");
            return self::cacheDonorPasskey(null);
        }

        foreach ($files as $path) {
            // Quick rejection: check first 4 KB for NNMClub signature
            $head = @file_get_contents($path, false, null, 0, 4096);
            if ($head === false
                || (stripos($head, 'nnm-club') === false
                    && stripos($head, 'nnmclub') === false)) {
                continue;
            }

            // Passkey is usually near the beginning of the bencoded data.
            // Try head first, then full file only when needed.
            $pk = self::extractPasskey($head);
            if ($pk !== null) {
                return self::cacheDonorPasskey($pk);
            }

            if (strlen($head) >= 4096) {
                $full = @file_get_contents($path);
                if ($full !== false) {
                    $pk = self::extractPasskey($full);
                    if ($pk !== null) {
                        return self::cacheDonorPasskey($pk);
                    }
                }
            }
        }
        self::log("Donor passkey not found in session torrents");
        return self::cacheDonorPasskey(null);
    }

    /**
     * Save donor passkey lookup result in cache.
     *
     * @param  string|null $passkey  Found passkey or null if absent
     * @return string|null
     */
    private static function cacheDonorPasskey($passkey)
    {
        self::$donorPasskeyCacheLoaded = true;
        self::$donorPasskeyCache = $passkey;
        return $passkey;
    }

    /**
     * Get rtorrent session directory via XMLRPC (result is cached).
     * @return string|null  Session directory path, or null on failure
     */
    private static function getSessionDir()
    {
        if (self::$sessionDirCacheLoaded) {
            return self::$sessionDirCache;
        }

        self::$sessionDirCacheLoaded = true;

        $req = new rXMLRPCRequest(new rXMLRPCCommand("get_session"));
        if ($req->run() && !$req->fault && !empty($req->val[0])) {
            self::$sessionDirCache = $req->val[0];
            return self::$sessionDirCache;
        }

        self::$sessionDirCache = null;
        self::log("Failed to resolve rtorrent session directory via get_session");
        return null;
    }

    // ====================================================================
    // Session Torrent Patching
    // ====================================================================

    /**
     * Patch announce URLs in the session .torrent file to include passkey.
     *
     * Uses the Torrent class for clean bencode parsing and serialization.
     * Preserves libtorrent_resume and rtorrent metadata so that
     * fast-resume works correctly after rtorrent restart.
     *
     * @param  string $hash     Info hash (hex, uppercase)
     * @param  string $passkey  32-hex passkey to inject
     * @return bool   True if the file was patched and saved
     */
    private static function patchSessionTorrent($hash, $passkey)
    {
        $sessionDir = self::getSessionDir();
        if ($sessionDir === null) {
            self::log("patchSessionTorrent skipped: no session dir for {$hash}");
            return false;
        }

        $path = rtrim($sessionDir, '/') . '/' . $hash . '.torrent';
        if (!is_writable($path)) {
            self::log("patchSessionTorrent skipped: not writable {$path}");
            return false;
        }

        $torrent = new Torrent($path);
        if ($torrent->errors()) {
            self::log("patchSessionTorrent failed: unreadable torrent {$path}");
            return false;
        }

        $changed = self::patchPasskeyInTorrent($torrent, $passkey);
        if (!$changed) {
            self::log("patchSessionTorrent no-op for {$hash}: no announce URLs to patch");
            return false;
        }

        // NOTE: We intentionally preserve libtorrent_resume and rtorrent
        // metadata — they are needed for fast-resume after rtorrent restart.
        // Only the announce URLs are modified (outside the info dict),
        // so the info_hash and resume data remain valid.

        $saved = (bool) $torrent->save($path);
        self::log("patchSessionTorrent " . ($saved ? "saved" : "failed to save") . " for {$hash}");
        return $saved;
    }

    /**
     * Patch NNMClub announce URLs inside a Torrent object.
     *
     * @param  Torrent $torrent
     * @param  string  $passkey
     * @return bool  True when at least one URL was changed
     */
    private static function patchPasskeyInTorrent($torrent, $passkey)
    {
        $announceChanged = false;
        $listChanged = false;

        $announce = $torrent->announce();
        if (is_string($announce) && $announce !== '') {
            $patched = self::injectPasskeyIntoUrl($announce, $passkey);
            if ($patched !== $announce) {
                $torrent->announce($patched);
                $announceChanged = true;
            }
        }

        $list = $torrent->announce_list();
        if (is_array($list)) {
            $newList = [];
            foreach ($list as $tier) {
                $newTier = [];
                $urls = is_array($tier) ? $tier : [$tier];
                foreach ($urls as $url) {
                    $patchedUrl = is_string($url)
                        ? self::injectPasskeyIntoUrl($url, $passkey)
                        : $url;
                    if ($patchedUrl !== $url) {
                        $listChanged = true;
                    }
                    $newTier[] = $patchedUrl;
                }
                $newList[] = $newTier;
            }
            if ($listChanged) {
                $torrent->announce_list($newList);
            }
        }

        return $announceChanged || $listChanged;
    }

    /**
     * Inject a passkey into an NNMClub announce URL.
     * Supports both nnm-club.* and nnmclub.* domain formats.
     * Non-NNMClub URLs are returned unchanged.
     *
     * @param  string $url      Announce URL
     * @param  string $passkey  32-hex passkey to inject
     * @return string  Modified URL (or original if not NNMClub)
     */
    private static function injectPasskeyIntoUrl($url, $passkey)
    {
        $hostGroup = '(' . self::ANNOUNCE_HOST_RE . ')';
        $result = preg_replace(
            '`' . $hostGroup . '/(?:[0-9a-f]{32}/)?announce`i',
            '$1/' . $passkey . '/announce',
            $url,
            1,
            $count
        );
        if ($count > 0 && $result !== null) {
            return $result;
        }

        return $url;
    }

    // ====================================================================
    // Tracker Scrape
    // ====================================================================

    /**
     * Decode one bencoded value from $data starting at $offset.
     *
     * @param  string $data
     * @param  int    $offset
     * @return mixed
     * @throws Exception
     */
    private static function decodeBencodeValue($data, &$offset)
    {
        if (!isset($data[$offset])) {
            throw new Exception("Unexpected EOF at offset {$offset}");
        }

        $token = $data[$offset];
        if ($token >= '0' && $token <= '9') {
            return self::decodeBencodeString($data, $offset);
        }

        if ($token === 'i') {
            $offset++;
            $end = strpos($data, 'e', $offset);
            if ($end === false) {
                throw new Exception("Invalid integer at offset {$offset}");
            }
            $num = substr($data, $offset, $end - $offset);
            if ($num === '' || !preg_match('/^-?\d+$/', $num)) {
                throw new Exception("Invalid integer value at offset {$offset}");
            }
            $offset = $end + 1;
            return (int) $num;
        }

        if ($token === 'l') {
            $offset++;
            $list = [];
            while (isset($data[$offset]) && $data[$offset] !== 'e') {
                $list[] = self::decodeBencodeValue($data, $offset);
            }
            if (!isset($data[$offset])) {
                throw new Exception("Unterminated list at offset {$offset}");
            }
            $offset++;
            return $list;
        }

        if ($token === 'd') {
            $offset++;
            $dict = [];
            while (isset($data[$offset]) && $data[$offset] !== 'e') {
                $key = self::decodeBencodeString($data, $offset);
                $dict[$key] = self::decodeBencodeValue($data, $offset);
            }
            if (!isset($data[$offset])) {
                throw new Exception("Unterminated dict at offset {$offset}");
            }
            $offset++;
            return $dict;
        }

        throw new Exception("Unknown token '{$token}' at offset {$offset}");
    }

    /**
     * Decode one bencoded byte string from $data starting at $offset.
     *
     * @param  string $data
     * @param  int    $offset
     * @return string
     * @throws Exception
     */
    private static function decodeBencodeString($data, &$offset)
    {
        $colon = strpos($data, ':', $offset);
        if ($colon === false) {
            throw new Exception("Invalid string length at offset {$offset}");
        }

        $lenRaw = substr($data, $offset, $colon - $offset);
        if ($lenRaw === '' || preg_match('/\D/', $lenRaw)) {
            throw new Exception("Invalid string size '{$lenRaw}' at offset {$offset}");
        }

        $len = (int) $lenRaw;
        $offset = $colon + 1;
        if (($offset + $len) > strlen($data)) {
            throw new Exception("String out of bounds at offset {$offset}");
        }

        $value = substr($data, $offset, $len);
        $offset += $len;
        return $value;
    }

    /**
     * Parse scrape bencode and check whether "files" contains $binaryHash key.
     *
     * @param  string $payload
     * @param  string $binaryHash  Raw 20-byte hash
     * @return bool|null  true: found, false: not found, null: parse error
     */
    private static function scrapeContainsHash($payload, $binaryHash)
    {
        if (!is_string($payload) || !is_string($binaryHash) || strlen($binaryHash) !== 20) {
            return false;
        }

        try {
            $offset = 0;
            $root = self::decodeBencodeValue($payload, $offset);
        } catch (Exception $e) {
            self::log("Scrape parse failed: " . $e->getMessage());
            return null;
        }

        return is_array($root)
            && isset($root['files'])
            && is_array($root['files'])
            && array_key_exists($binaryHash, $root['files']);
    }

    /**
     * Scrape the NNMClub tracker to check if a hash is registered.
     *
     * Uses Snoopy (via makeClient) for consistency with the rest of the
     * plugin (respects proxy settings, bind IP, timeouts).
     * Tries all configured TRACKER_HOSTS with fallback on failure.
     *
     * @param  string $passkey  32-hex passkey for tracker auth
     * @param  string $hash     Info hash (hex, uppercase, 40 chars)
     * @return int  One of SCRAPE_RESULT_* constants
     */
    private static function checkViaScrape($passkey, $hash)
    {
        $binary = @pack('H*', $hash);
        if (strlen($binary) !== 20) {
            self::log("Scrape skipped: invalid info hash format {$hash}");
            return self::SCRAPE_RESULT_FAILED;
        }

        $sawNotFound = false;

        foreach (self::TRACKER_HOSTS as $host) {
            $url = 'http://' . $host . ':' . self::TRACKER_PORT
                 . '/' . $passkey . '/scrape?info_hash=' . rawurlencode($binary);

            $client = ruTrackerChecker::makeClient($url);

            if ($client->status == 200
                && is_string($client->results)
                && $client->results !== '') {
                $hashState = self::scrapeContainsHash($client->results, $binary);
                if ($hashState === true) {
                    return self::SCRAPE_RESULT_UPTODATE;
                }
                if ($hashState === null) {
                    self::log("Scrape response parse error on {$host}");
                    continue;
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

    // ====================================================================
    // Formatting
    // ====================================================================

    /**
     * Convert result values to readable log-safe strings.
     *
     * @param  mixed $value
     * @return string
     */
    private static function stringify($value)
    {
        if ($value === null) return 'null';
        if ($value === true) return 'true';
        if ($value === false) return 'false';
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }
        return gettype($value);
    }

    // ====================================================================
    // Main Entry Point
    // ====================================================================

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
        if ($topicRef === null && is_object($old_torrent)) {
            $topicRef = self::parseTopicRef($old_torrent->comment());
        }
        if ($topicRef === null) {
            self::log("Skip check: unable to parse NNMClub topic reference from URL/comment");
            return ruTrackerChecker::STE_NOT_NEED;
        }
        $siteDomain = $topicRef['host'];
        $topicQuery = $topicRef['query'];
        self::log("Start check for {$hash} using {$siteDomain}/forum/viewtopic.php?{$topicQuery}");

        // =============================================================
        // PASSKEY DISCOVERY
        // =============================================================

        $passkey = self::extractPasskey($old_torrent->announce())
                ?? self::extractPasskey($old_torrent->announce_list());
        if ($passkey !== null) {
            self::log("Using passkey from current torrent metadata");
        }

        if ($passkey === null) {
            $passkey = self::findDonorPasskey();
            if ($passkey !== null) {
                self::log("Found donor passkey, patching session torrent {$hash}");
                if (!self::patchSessionTorrent($hash, $passkey)) {
                    self::log("Session patch attempt finished without file changes for {$hash}");
                }
            } else {
                self::log("No passkey found for {$hash}, skipping scrape");
            }
        }

        // =============================================================
        // PHASE 1: TRACKER SCRAPE (fast path)
        // =============================================================

        if ($passkey !== null) {
            $scrapeResult = self::checkViaScrape($passkey, $hash);
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

        // =============================================================
        // PHASE 2: GUEST TORRENT DOWNLOAD
        // =============================================================

        // --- Step 1: Fetch guest topic page ---
        $client = self::makeGuestClient();
        self::guestFetch($client, "https://{$siteDomain}/forum/viewtopic.php?" . $topicQuery);
        if ($client->status != 200) {
            self::log("viewtopic fetch failed: status={$client->status}");
            return ruTrackerChecker::STE_CANT_REACH_TRACKER;
        }

        // --- Step 2: btih shortcut (for authenticated sessions) ---
        if (preg_match('`btih:(?P<hash>[0-9A-Fa-f]{40})`', $client->results, $btihMatch)) {
            if (strtoupper($btihMatch['hash']) === $hash) {
                return ruTrackerChecker::STE_UPTODATE;
            }
            self::log("Topic btih differs for {$topicQuery}, verifying via downloaded .torrent");
        }

        // --- Step 3: Find download link ---
        if (!preg_match('`(?:/forum/)?download\.php\?id=(?P<dlid>\d+)`i', $client->results, $dlMatch)) {
            if (self::looksLikeChallengePage($client->results)) {
                self::log("No download link for {$topicQuery}: challenge page detected");
                return ruTrackerChecker::STE_CANT_REACH_TRACKER;
            }
            self::log("No download link for {$topicQuery}: unexpected topic page format");
            return ruTrackerChecker::STE_ERROR;
        }
        $downloadId = $dlMatch['dlid'];

        // --- Step 4: Download guest .torrent ---
        $client->setcookies();
        self::guestFetch($client, "https://{$siteDomain}/forum/download.php?id=" . $downloadId);
        if ($client->status != 200 || empty($client->results)) {
            self::log("download.php failed: status={$client->status} id={$downloadId}");
            return ($client->status < 0)
                ? ruTrackerChecker::STE_CANT_REACH_TRACKER
                : ruTrackerChecker::STE_ERROR;
        }

        $guestData = $client->results;

        // --- Step 5: Compare info_hash ---
        $guestTorrent = new Torrent($guestData);
        if ($guestTorrent->errors()) {
            self::log("Failed to parse downloaded torrent for {$topicQuery}");
            return ruTrackerChecker::STE_ERROR;
        }

        $guestHash = strtoupper((string) $guestTorrent->hash_info());
        if ($guestHash === $hash) {
            self::log("Guest torrent hash matches current hash for {$topicQuery}");
            return ruTrackerChecker::STE_UPTODATE;
        }

        // --- Step 6: Hash differs → torrent was updated ---
        self::log("Hash changed for {$topicQuery}: {$hash} -> {$guestHash}");
        if ($passkey === null) {
            self::log("Hash differs but passkey is unavailable; refusing replacement");
            return ruTrackerChecker::STE_ERROR;
        }
        if (!self::patchPasskeyInTorrent($guestTorrent, $passkey)) {
            self::log("Hash differs but passkey patch found no NNMClub announce URLs");
            return ruTrackerChecker::STE_ERROR;
        }

        $replaceResult = ruTrackerChecker::createTorrent((string) $guestTorrent, $hash);
        self::log("createTorrent result for {$hash}: " . self::stringify($replaceResult));
        return $replaceResult;
    }
}

// Register this tracker handler with ruTrackerChecker.
// First regex: matches the torrent's comment URL.
// Second regex: matches announce URLs containing "nnm-club" or "nnmclub".
ruTrackerChecker::registerTracker(
    "/(nnm-club|nnmclub)\./",
    "/(nnm-club|nnmclub)\./",
    "NNMClubCheckImpl::download_torrent"
);
