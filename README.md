# ruTorrent

ruTorrent is a front-end for the popular Bittorrent client [rtorrent](http://rakshasa.github.io/rtorrent).

This project is released under the GPLv3 license, for more details, take a look at the LICENSE.md file in the source.

## Main features

* Lightweight server side, so it can be installed on old and low-end servers and even on some SOHO routers
* Extensible - there are several plugins and everybody can create their own one
* Nice look ;) 

## Screenshots

[![](https://github.com/Novik/ruTorrent/wiki/images/scr1_small.jpg)](https://github.com/Novik/ruTorrent/wiki/images/scr1_big.jpg)
[![](https://github.com/Novik/ruTorrent/wiki/images/scr2_small.jpg)](https://github.com/Novik/ruTorrent/wiki/images/scr2_big.jpg)
[![](https://github.com/Novik/ruTorrent/wiki/images/scr3_small.jpg)](https://github.com/Novik/ruTorrent/wiki/images/scr3_big.jpg)

## Download

 * [Stable version](https://github.com/Novik/ruTorrent/releases/latest) — the latest tagged release
 * [Current master](https://github.com/Novik/ruTorrent/tarball/master) — what the next release is cut from

## Getting started

  * There's no installation routine or compilation necessary. The sources are cloned/unpacked into a directory which is setup as document root of a web server of your choice (for detailed instructions see the [webserver wiki article](https://github.com/Novik/ruTorrent/wiki/WebSERVER)).
  * After setting up the webserver `ruTorrent` itself needs to be configured. Instructions can be found in various articles in the [wiki](https://github.com/Novik/ruTorrent/wiki).
  * Keep `share/`, `conf/` and `tests/` out of what the web server will serve. `share/` holds the profile data — settings, stored torrents, and the tracker credentials the `loginmgr` plugin keeps — and it sits under the document root by default. Point `$profilePath` in `conf/config.php` at a directory outside the document root, such as `/var/lib/rutorrent`: that is the one measure nothing else has to be right for. Apache denies the three directories from the `.htaccess` file already in each of them; nginx and lighttpd do not read `.htaccess` and need the rules in [`nginx-example.conf`](nginx-example.conf) — written for the prefix the install is served under, and placed before the `.php` handler.

## Public feed links behind a reverse proxy

When a reverse proxy changes the application host or path prefix, set the full public application URL in `conf/config.local.php` so the `feeds` plugin generates working channel, image and download links:

```php
<?php
$publicURL = 'https://example.org/torrents/';
```

A nonempty `RU_PUBLIC_URL` environment variable overrides this setting; an empty or unset variable permits the local value. Use an absolute HTTP(S) URL without credentials, query or fragment. Without an explicit setting, feed links use the request's host, scheme and actual script path. A proxy that rewrites that path needs the explicit public prefix; `X-Forwarded-Host` does not select it.

## Contributing

Pull requests target **`master`**. There is no `develop` branch — it was retired,
and a release is simply a tag on `master`.

Using AI tooling to write a patch is fine. Opening what it produced without
reading it is not. Whatever wrote the code, the pull request is yours, and that
means three things before you open it:

1. **Check that it is sane.** Read the whole diff yourself. A PR that reformats
   files it had no reason to touch, or that contains changes you cannot explain
   in your own words, will be closed rather than reviewed.
2. **Vouch for it.** Run it on a real ruTorrent instance that you actually use —
   your own, with your torrents and plugins, left running long enough to see it
   behave. A container spun up to prove the diff applies is not a test. Say in
   the PR what you ran it against and what you saw.
3. **Answer reviews personally.** Review comments are addressed to you. If the
   replies are generated and unread, the PR stops there.

Keep the diff scoped to the change. Whitespace churn, unrelated reformatting and
rewritten vendored files make a patch unreviewable, and a patch nobody can
review does not get merged.

## Requirements

* **PHP 7.4 or newer**, with the `json` and `pcre` extensions. The `simplexml`,
  `curl`, `mbstring` and `zlib` extensions are recommended — some plugins and the
  XMLRPC proxy need them — as are the `php`, `curl` and `gzip` command-line
  programs.
* **rtorrent**: the `0.9.8` baseline and the `0.16.x` series are supported.
  Other versions may or may not work.
* A web server with ruTorrent's directory as its document root (see the
  [webserver wiki article](https://github.com/Novik/ruTorrent/wiki/WebSERVER)).

### Checking your environment

A checker is bundled with ruTorrent. Run it from the command line, from inside
your ruTorrent directory:

```
php env_check.php
```

It verifies the PHP version and extensions, the external programs plugins rely
on, common `conf/config.php` mistakes, and connects to rtorrent to report and
classify its version and to check that ruTorrent's event handlers are registered
with it. It exits `0` when everything required passes and `1` otherwise, so it
can be used in scripts. For safety it runs on the command line only and refuses
to run under a web server.

### SCGI transport limits

`$rpcTimeOut` bounds connecting to rTorrent; a missing or unusable value means 30 seconds. `$rpcTransferTimeOut` bounds the complete request write and response read together. Its default `null`, and any value that is not a finite positive number, takes the greater of the connection timeout and PHP's finite positive `default_socket_timeout` (or 60 seconds otherwise). Byte-by-byte progress cannot extend this total budget, and a request that runs out of it is logged with the budget and the setting to raise. For a large response over a slow connection, set a finite positive `$rpcTransferTimeOut`, for example `180` for a transfer expected to take 80 seconds, and allow enough time in PHP and the web server too. Both timeouts are capped at 2147483 seconds; there is no unlimited transfer setting. `php env_check.php` reports unusable values of both settings. While it waits, the client watches the socket and reads as soon as rTorrent answers, waking at least every 32 milliseconds to check the budget. Where `stream_select()` is disabled or cannot watch the socket, it sleeps instead: 50 microseconds at first and twice as long each time up to 32 milliseconds. The previous `rpc2.php` reader handled a delayed response inconsistently: on PHP 8 it could return HTTP 200 with an empty body, while PHP 7.4 could wait without a bound.

`$rpcMaxResponseBytes` limits the declared response body. Its default is 100 MiB, the largest response rTorrent itself sends; a smaller positive integer refuses larger responses. A larger or unusable value means 100 MiB; `php env_check.php` reports this configuration mistake. A response above 1 MiB is collected in the configured `$tempDirectory`. When it is unset, the WebUI uses ruTorrent's existing temporary-directory detection and `rpc2.php` uses PHP's system temporary directory. A world-writable directory without the sticky bit is not used; the WebUI reports such a temporary directory as unsafe. Only a new, empty file in the directory is used, and it is unlinked before any response byte is written, so terminating the worker leaves no response file behind. Accounts that may write to the directory through its group can still substitute a file of their own. If no such file can be used, the response is collected in memory, where one near 100 MiB needs about twice its size. These settings apply to both the WebUI and the filtered `rpc2.php` endpoint, including persisted configurations that omit them. Incomplete or malformed SCGI frames fail the request rather than publishing a partial result. This does not change which XMLRPC calls the proxy permits.
