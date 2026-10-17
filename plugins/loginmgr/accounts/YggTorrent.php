<?php

class YggTorrentAccount extends commonAccount
{
    // The tracker moves between domains, so its origin is not written here:
    // it is $yggTorrentOrigin in plugins/loginmgr/conf.php, and this is that
    // origin as "https://host[:port]", or '' while none is configured. The
    // host used to be recognised by its shape instead, ygg*.<tld>, which also
    // recognised any such domain anyone registers -- and login() posted the
    // credentials to the host of whichever URL had selected the account.
    public $url = '';

    // Why $yggTorrentOrigin is not usable, for the log line, or '' when it is.
    private $originError = '';

    public function __construct()
    {
        global $yggTorrentOrigin;
        $this->url = self::origin(isset($yggTorrentOrigin) ? $yggTorrentOrigin : '', $this->originError);
    }

    // $value as "https://host[:port]", or '' with the reason in $error. An
    // origin is a scheme, an ASCII host and at most a port: no userinfo,
    // path, query or fragment, since those would go into every URL built on
    // it. A trailing slash is allowed; case and a trailing dot in the host are
    // folded. parse_url() alone would pass "https://ygg example" and
    // "https://ygg..example" as hosts, hence the host pattern.
    static protected function origin($value, &$error)
    {
        if (!is_string($value)) {
            $error = 'is not a string';
            return '';
        }
        if ($value === '') {
            $error = 'is not set';
            return '';
        }
        $error = 'is not an https origin ("https://host" with an ASCII host, optionally a port, and nothing else)';
        $parts = @parse_url($value);
        if (!is_array($parts) || empty($parts["scheme"]) || empty($parts["host"]) ||
            strtolower($parts["scheme"]) !== 'https' ||
            isset($parts["user"]) || isset($parts["pass"]) || isset($parts["query"]) || isset($parts["fragment"]) ||
            (isset($parts["path"]) && $parts["path"] !== '' && $parts["path"] !== '/')) {
            return '';
        }
        $host = rtrim(strtolower($parts["host"]), '.');
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $host)) {
            return '';
        }
        if (isset($parts["port"]) && ($parts["port"] < 1 || $parts["port"] > 65535)) {
            return '';
        }
        $error = '';
        return 'https://' . $host . (isset($parts["port"]) ? ':' . $parts["port"] : '');
    }

    protected function unconfigured()
    {
        if ($this->url !== '') {
            return '';
        }
        return '$yggTorrentOrigin ' . $this->originError
            . '; set it to the tracker\'s origin in plugins/loginmgr/conf.local.php'
            . ' or conf/users/<user>/plugins/loginmgr/conf.php';
    }

    // True when $url is on the configured origin: https, the same host (case
    // and a trailing dot folded) and the same port.
    protected function onOrigin($url)
    {
        if ($this->url === '') {
            return false;
        }
        $parts = @parse_url((string) $url);
        if (!is_array($parts) || empty($parts["host"]) ||
            strtolower(isset($parts["scheme"]) ? $parts["scheme"] : '') !== 'https') {
            return false;
        }
        $site = parse_url($this->url);
        return rtrim(strtolower($parts["host"]), '.') === $site["host"] &&
            (isset($parts["port"]) ? (int) $parts["port"] : 443) === (isset($site["port"]) ? (int) $site["port"] : 443);
    }

    protected function isOK($client)
    {
        return (
            $client->status == 200
            && $client->results !== 'Vous devez vous connecter pour télécharger un torrent'
            && strpos($client->results, "S'identifier</a>") === false
        );
    }

    public function test($url)
    {
        $parts = @parse_url((string) $url);
        if (!is_array($parts) ||
            strcasecmp(isset($parts["path"]) ? $parts["path"] : '', '/engine/download_torrent') !== 0 ||
            preg_match('/(^|&)id=/', isset($parts["query"]) ? $parts["query"] : '') !== 1) {
            return false;
        }
        // A download link of the tracker's shape with no origin to compare it
        // with is declined, and the log says why: the download is then made
        // as a guest.
        if ($this->refuseUnconfigured()) {
            return false;
        }
        return $this->onOrigin($url);
    }

    protected function login($client, $login, $password, &$url, &$method, &$content_type, &$body, &$is_result_fetched)
    {
        $is_result_fetched = false;
        // check() arrives with $this->url and no test() before it; a caller's
        // URL went through test(). Either way the credentials go to the
        // configured origin, never to the host of the URL, and only once that
        // origin has answered the landing request with 2xx: an error status
        // is not sent the password.
        if (!$this->onOrigin($url) || !$client->fetch($url) ||
            (int) $client->status < 200 || (int) $client->status >= 300) {
            return false;
        }
        $client->setcookies();
        $client->referer = $url;
        if ($client->fetch($this->url . "/user/login", "POST", "application/x-www-form-urlencoded",
            "id=" . rawurlencode($login) . "&pass=" . rawurlencode($password) . '&submit=')) {
            $client->setcookies();
            return true;
        }
        return false;
    }
}
