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

  it("handles trailing escaped quotes, backslashes, and brackets in failure messages", () => {
    const input = 'Tracker: [Could not resolve hostname /// Failure reason \\"Unregistered torrent: Trump: Superior Quality - https://torrent.site/page?id1=12345&id2=67890\\"]';
    const expected = 'Tracker: [Could not resolve hostname /// Failure reason \\&quot;Unregistered torrent: Trump: Superior Quality - <a href="https://torrent.site/page?id1=12345&amp;id2=67890" target="_blank" rel="noopener noreferrer">https://torrent.site/page?id1=12345&amp;id2=67890</a>\\&quot;]';
    expect(window.getClickableTrackerStatus(input)).toBe(expected);
  });

  it("handles torrent failure status with PtP style links and escaped quotes", () => {
    const input = 'https://torrent.site/page?id1=224822&id2=1534647\\"]';
    const expected = '<a href="https://torrent.site/page?id1=224822&amp;id2=1534647" target="_blank" rel="noopener noreferrer">https://torrent.site/page?id1=224822&amp;id2=1534647</a>\\&quot;]';
    expect(window.getClickableTrackerStatus(input)).toBe(expected);
  });
});
