<?php

class RuTrackerCheckImpl
{
    static private function looksLikeHtmlError($content)
    {
        if (!is_string($content) || trim($content) === '') return true;

        // A valid metainfo dictionary starts with "d". Only classify leading
        // text/markup as an HTTP error; arbitrary binary fields may themselves
        // contain words such as "Error" or fragments that look like HTML.
        $leading = ltrim($content, "\xEF\xBB\xBF \t\r\n");
        return isset($leading[0]) && ($leading[0] === '<'
            || preg_match('/^(?:Error:|attachment data not found\b)/i', $leading));
    }

    static private function normalizeHash($value)
    {
        if (!is_string($value)) return null;
        $value = strtoupper(trim($value));
        return preg_match('/^[0-9A-F]{40}$/', $value) ? $value : null;
    }

    static private function extractTopicId($url)
    {
        if (!is_string($url) || $url === '') return null;
        $parts = @parse_url(trim($url));
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'], $parts['path'])) return null;
        if (!preg_match('/^https?$/i', $parts['scheme'])) return null;
        if (!preg_match('/^rutracker\.(?:org|cr|net|nl)$/i', $parts['host'])) return null;
        if (strcasecmp($parts['path'], '/forum/viewtopic.php') !== 0) return null;

        $query = array();
        parse_str(isset($parts['query']) ? $parts['query'] : '', $query);
        if (!isset($query['t']) || !is_scalar($query['t']) || !ctype_digit((string) $query['t'])) return null;
        return (int) $query['t'];
    }

    // Decode CP1251 HTML to UTF-8 for reliable text search.
    static private function decodePage($content)
    {
        if (!is_string($content) || $content === '') return '';

        $decoded = false;
        if (function_exists('iconv')) {
            $decoded = @iconv('CP1251', 'UTF-8//IGNORE', $content);
        }
        if (($decoded === false) && function_exists('mb_convert_encoding')) {
            $decoded = @mb_convert_encoding($content, 'UTF-8', 'CP1251');
        }
        return ($decoded === false || is_null($decoded)) ? $content : $decoded;
    }

    static private function extractLastPageHtml($client, $topicId)
    {
        $topicUrl = 'https://rutracker.org/forum/viewtopic.php?t=' . $topicId;
        $client->setcookies();
        $client->fetchComplex($topicUrl);

        if ($client->status != 200 || empty($client->results)) return null;

        $html = self::decodePage($client->results);
        $lastStart = 0;
        if (preg_match_all('/<a\b[^>]*>/i', $html, $anchors)) {
            foreach ($anchors[0] as $anchor) {
                if (!preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/is', $anchor, $classMatch)
                    || !preg_match('/(?:^|\s)pg(?:\s|$)/i', $classMatch[2])
                    || !preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/is', $anchor, $hrefMatch)) {
                    continue;
                }

                $href = html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $parts = @parse_url($href);
                if (!is_array($parts) || !isset($parts['path'], $parts['query'])
                    || strcasecmp(basename($parts['path']), 'viewtopic.php') !== 0
                    || (isset($parts['host']) && !preg_match('/^rutracker\.(?:org|cr|net|nl)$/i', $parts['host']))) {
                    continue;
                }
                $query = array();
                parse_str($parts['query'], $query);
                if (!isset($query['t'], $query['start'])
                    || !is_scalar($query['t']) || !is_scalar($query['start'])
                    || (int) $query['t'] !== (int) $topicId
                    || !ctype_digit((string) $query['start'])) {
                    continue;
                }
                $lastStart = max($lastStart, (int) $query['start']);
            }
        }

        if ($lastStart > 0) {
            $client->setcookies();
            $client->fetchComplex($topicUrl . '&start=' . $lastStart);
            if ($client->status != 200 || empty($client->results)) return null;
            $html = self::decodePage($client->results);
        }

        return $html;
    }

    static private function isModeratorPost($postHtml)
    {
        if (!preg_match_all('/<img\b[^>]*>/i', $postHtml, $images)) return false;

        foreach ($images[0] as $image) {
            if (!preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/is', $image, $classMatch)) continue;
            if (!preg_match('/(?:^|\s)user-rank(?:\s|$)/i', $classMatch[2])) continue;
            if (!preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $image, $altMatch)) continue;
            if (preg_match('/\bmoderator\b|модератор/iu', $altMatch[2])) return true;
        }
        return false;
    }

    static private function extractPostBody($postHtml)
    {
        if (!preg_match_all('/<div\b[^>]*>/i', $postHtml, $divs, PREG_OFFSET_CAPTURE)) return null;

        foreach ($divs[0] as $div) {
            $tag = $div[0];
            if (!preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/is', $tag, $classMatch)) continue;
            if (!preg_match('/(?:^|\s)post_body(?:\s|$)/i', $classMatch[2])) continue;

            $start = $div[1] + strlen($tag);
            if (!preg_match('/<\/div>\s*<!--\/post_body-->/i', $postHtml, $end, PREG_OFFSET_CAPTURE, $start)) {
                return null;
            }
            return substr($postHtml, $start, $end[0][1] - $start);
        }
        return null;
    }

    // Accept only a final absorption marker written in a moderator post.
    static private function detectAbsorbedTopic($client, $topicId)
    {
        $html = self::extractLastPageHtml($client, $topicId);
        if (empty($html)) return null;

        if (!preg_match_all(
            '~<tbody\b[^>]*\bid=["\']post_\d+["\'][^>]*>.*?</tbody>~is',
            $html,
            $posts
        )) return null;

        foreach (array_reverse($posts[0]) as $post) {
            if (!self::isModeratorPost($post)) continue;
            $body = self::extractPostBody($post);
            if ($body === null) continue;

            $plain = html_entity_decode(
                preg_replace('/<[^>]+>/', ' ', $body),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $plain = preg_replace('/\s+/u', ' ', trim($plain));
            if (!preg_match('/(?:^|\s)(?:Поглощено|Объединено)\.?$/iu', $plain)) continue;

            $decodedBody = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!preg_match_all(
                '~href=["\'](?:(?:https?://rutracker\.(?:org|cr|net|nl)/forum/)|/forum/|\./)?viewtopic\.php\?[^"\']*\bt=(\d+)[^"\']*["\']~i',
                $decodedBody,
                $links
            )) continue;

            $candidates = array();
            foreach ($links[1] as $candidate) {
                $candidate = (int) $candidate;
                if ($candidate && $candidate !== (int) $topicId) $candidates[$candidate] = true;
            }
            if (count($candidates) === 1) return (int) key($candidates);
        }
        return null;
    }

    static public function download_torrent($url, $hash, $oldTorrent)
    {
        $topicId = self::extractTopicId($url);
        if ($topicId === null && is_object($oldTorrent)) {
            $topicId = self::extractTopicId($oldTorrent->comment());
        }
        if ($topicId === null) return ruTrackerChecker::STE_NOT_NEED;

        $localHash = self::normalizeHash($hash);
        $remoteHash = null;
        $apiDeleted = false;

        $apiUrl = 'https://api.rutracker.cc/v1/get_tor_hash?by=topic_id&val=' . $topicId;
        $client = ruTrackerChecker::makeClient($apiUrl);
        if ($client->status == 200) {
            $response = @json_decode($client->results, true);
            if (is_array($response) && isset($response['result']) && is_array($response['result'])
                && array_key_exists($topicId, $response['result'])) {
                $apiValue = $response['result'][$topicId];
                if (is_array($apiValue)) {
                    $remoteHash = self::normalizeHash(isset($apiValue['hash']) ? $apiValue['hash'] : null);
                    $apiDeleted = ($remoteHash === null && isset($apiValue['error_code'])
                        && (int) $apiValue['error_code'] === 1);
                } else {
                    $remoteHash = self::normalizeHash($apiValue);
                }

                if ($remoteHash !== null && $remoteHash === $localHash) {
                    return ruTrackerChecker::STE_UPTODATE;
                }
            }
        }

        $client->setcookies();
        $client->fetchComplex('https://rutracker.org/forum/dl.php?t=' . $topicId);
        $directStatus = $client->status;
        $directBody = $client->results;
        $directParseError = false;
        if ($directStatus == 200 && !self::looksLikeHtmlError($directBody)) {
            $downloadedTorrent = @new Torrent($directBody);
            if (!$downloadedTorrent->errors()
                && self::normalizeHash($downloadedTorrent->hash_info()) !== null) {
                // A valid payload reached the local replacement transaction.
                // Its error must not trigger a second, potentially conflicting replacement.
                return ruTrackerChecker::createTorrent($directBody, $hash);
            }
            $directParseError = true;
        }

        $absorbedTopicId = self::detectAbsorbedTopic($client, $topicId);
        if ($absorbedTopicId !== null) {
            $client->setcookies();
            $client->fetchComplex('https://rutracker.org/forum/dl.php?t=' . $absorbedTopicId);
            if ($client->status == 200 && !self::looksLikeHtmlError($client->results)) {
                // createTorrent treats unparseable payloads as a deleted topic
                // (legacy handler contract); for a replacement download that
                // would be wrong, so validate the payload here.
                $replacement = @new Torrent($client->results);
                if ($replacement->errors()
                    || self::normalizeHash($replacement->hash_info()) === null) {
                    return ruTrackerChecker::STE_ERROR;
                }
                return ruTrackerChecker::createTorrent($client->results, $hash);
            }
            return ruTrackerChecker::STE_CANT_REACH_TRACKER;
        }

        // Only a topic-specific API deletion is authoritative. Transport,
        // login and unexpected payload failures must remain retryable.
        if ($apiDeleted) return ruTrackerChecker::STE_DELETED;
        if ($directParseError) return ruTrackerChecker::STE_ERROR;
        return ruTrackerChecker::STE_CANT_REACH_TRACKER;
    }
}

ruTrackerChecker::registerTracker("/rutracker\./", "/rutracker\.|t-ru\.org/", "RuTrackerCheckImpl::download_torrent");
