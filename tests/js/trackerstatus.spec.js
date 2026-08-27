import { readFileSync } from "fs";

window.$ = require("jquery");

for (const src of ["../lang/en.js", "../js/common.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

describe("getClickableTrackerStatus", () => {
  it("returns empty string for null, undefined, or empty input", () => {
    expect(window.getClickableTrackerStatus(null)).toBe("");
    expect(window.getClickableTrackerStatus(undefined)).toBe("");
    expect(window.getClickableTrackerStatus("")).toBe("");
  });

  it("converts http and https URLs to clickable links", () => {
    const input = "Tracker status: http://example.com/torrent/123";
    const expected = 'Tracker status: <a href="http://example.com/torrent/123" target="_blank" rel="noopener noreferrer">http://example.com/torrent/123</a>';
    expect(window.getClickableTrackerStatus(input)).toBe(expected);
  });

  it("handles embedded URLs with surrounding text and trailing period", () => {
    const input = "text text text https://tracker.foo/torrent.php?id=12345. text text text.";
    const expected = 'text text text <a href="https://tracker.foo/torrent.php?id=12345" target="_blank" rel="noopener noreferrer">https://tracker.foo/torrent.php?id=12345</a>. text text text.';
    expect(window.getClickableTrackerStatus(input)).toBe(expected);
  });

  it("excludes trailing punctuation like '.', ')', or ',' from link", () => {
    const input = "(see https://example.com/foo, or http://example.com/bar).";
    const expected = '(see <a href="https://example.com/foo" target="_blank" rel="noopener noreferrer">https://example.com/foo</a>, or <a href="http://example.com/bar" target="_blank" rel="noopener noreferrer">http://example.com/bar</a>).';
    expect(window.getClickableTrackerStatus(input)).toBe(expected);
  });

  it("sanitizes HTML and script tags to prevent XSS", () => {
    const input = "<script>alert('xss')</script> http://example.com/item?a=1&b=2";
    const expected = '&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt; <a href="http://example.com/item?a=1&amp;b=2" target="_blank" rel="noopener noreferrer">http://example.com/item?a=1&amp;b=2</a>';
    expect(window.getClickableTrackerStatus(input)).toBe(expected);
  });

  it("does not convert non-http/https links to hyperlinks", () => {
    const input = "ftp://files.example.com/torrent or javascript:alert(1)";
    const expected = "ftp://files.example.com/torrent or javascript:alert(1)";
    expect(window.getClickableTrackerStatus(input)).toBe(expected);
  });

  // libtorrent announces to one address family at a time; when both fail it
  // joins the two reasons with ' /// ' (TrackerHttp::receive_failed). The two
  // parts are IPv4 and IPv6 for one tracker. Live sample from a
  // 400-torrent fleet, 2026-08-21:
  //   Tracker: [Could not connect to server /// Could not resolve hostname]
  // on a torrent whose failing rows were a retracker and an IPv6 mirror.
  it("puts each joined tracker row's message on its own line", () => {
    const input = "Tracker: [Could not connect to server /// Could not resolve hostname]";
    expect(window.getClickableTrackerStatus(input)).toBe(
      "Tracker: [Could not connect to server<br>Could not resolve hostname]");
  });

  it("splits the join without touching a URL that contains slashes", () => {
    const input = "Tracker: [Failure reason /// see https://tracker.foo/a///b for details]";
    const expected = 'Tracker: [Failure reason<br>see <a href="https://tracker.foo/a///b" target="_blank" '
      + 'rel="noopener noreferrer">https://tracker.foo/a///b</a> for details]';
    expect(window.getClickableTrackerStatus(input)).toBe(expected);
  });

  it("leaves a bare /// with no surrounding spaces alone", () => {
    expect(window.getClickableTrackerStatus("a///b")).toBe("a///b");
  });

  it("handles torrent failure status with PtP style links and escaped quotes", () => {
    const input = 'https://torrent.site/page?id1=224822&id2=1534647\\"]';
    const expected = '<a href="https://torrent.site/page?id1=224822&amp;id2=1534647" target="_blank" rel="noopener noreferrer">https://torrent.site/page?id1=224822&amp;id2=1534647</a>\\&quot;]';
    expect(window.getClickableTrackerStatus(input)).toBe(expected);
  });
});
