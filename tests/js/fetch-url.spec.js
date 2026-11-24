/**
 * @jest-environment-options {"url": "http://user:pass@localhost/rutorrent/"}
 */
import { readFileSync, readdirSync, statSync } from "fs";
import { join } from "path";

// A page opened as http://user:pass@host/ (HTTP Basic credentials in the
// address) carries them in its own address, so a relative address resolves
// to one that carries them too, and fetch() refuses any such address: it
// throws a TypeError before anything is sent. Every fetch() goes through
// fetchURL() from js/common.js, which leaves them out; the browser still
// sends the credentials it was given for the page. XMLHttpRequest ($.ajax)
// accepts such addresses and is not concerned.

window.$ = window.jQuery = require("jquery");
window.theWebUI = { settings: {} };
for (const src of ["../lang/en.js", "../js/common.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

const pending = () => new Promise(() => {});

describe("fetchURL()", () => {
  it("runs on a page whose address carries credentials", () => {
    expect(document.baseURI).toBe("http://user:pass@localhost/rutorrent/");
  });

  it.each([
    ["a relative address", "plugins/log_history/action.php", "http://localhost/rutorrent/plugins/log_history/action.php"],
    ["a root-relative address with a query", "/php/addtorrent.php?label=a%20b&x=1", "http://localhost/php/addtorrent.php?label=a%20b&x=1"],
    ["an absolute address with credentials", "https://u:p@example.com:8443/a?b#c", "https://example.com:8443/a?b#c"],
    ["a user name alone", "http://u@localhost/x", "http://localhost/x"],
    ["a URL object", new URL("http://user:pass@localhost/php/x.php"), "http://localhost/php/x.php"],
  ])("leaves the credentials out of %s", (_label, url, expected) => {
    expect(fetchURL(url)).toBe(expected);
  });

  it("returns an address it cannot parse as it is, for fetch() to report", () => {
    expect(fetchURL("http://[")).toBe("http://[");
  });
});

describe("the add-torrent upload on such a page", () => {
  afterEach(() => {
    document.querySelectorAll("form").forEach((f) => f.remove());
    delete window.fetch;
  });

  it("is sent to the form's action without the credentials, and with the page's", () => {
    window.fetch = jest.fn(pending);
    document.body.insertAdjacentHTML("beforeend", '<form action="php/addtorrent.php?label=x" method="post"></form>');
    const form = document.body.lastElementChild;
    expect(form.action).toBe("http://user:pass@localhost/rutorrent/php/addtorrent.php?label=x");
    uploadTorrentForm(form, () => {});
    const [url, init] = window.fetch.mock.calls[0];
    expect(url).toBe("http://localhost/rutorrent/php/addtorrent.php?label=x");
    expect(init.credentials).toBe("same-origin");
  });
});

describe("log_history on such a page", () => {
  beforeAll(() => {
    window.fetch = jest.fn(() => Promise.resolve({ status: 204, ok: true }));
    window.plugin = { markLoaded: jest.fn() };
    window.noty = jest.fn();
    document.body.insertAdjacentHTML("beforeend", '<div id="lcont"></div><button id="clear_log"></button>');
    const el = document.createElement("script");
    el.textContent = readFileSync("../plugins/log_history/init.js", { encoding: "utf-8" });
    document.body.appendChild(el);
  });

  beforeEach(() => window.fetch.mockClear());

  it("reads the saved log without the credentials", () => {
    window.fetchLogLines();
    expect(window.fetch.mock.calls[0][0]).toBe("http://localhost/rutorrent/plugins/log_history/action.php");
  });

  it("saves each message without the credentials", () => {
    window.noty("a.torrent - added", "success");
    const [url, init] = window.fetch.mock.calls[0];
    expect(url).toBe("http://localhost/rutorrent/plugins/log_history/log_history.php");
    expect(init.method).toBe("POST");
  });

  it("clears the saved log without the credentials", () => {
    $("#clear_log").trigger("click");
    expect(window.fetch.mock.calls[0][0]).toBe("http://localhost/rutorrent/plugins/log_history/log_history.php?clear=1");
  });
});

// Every fetch() and new Request() in the interface and its plugins takes
// its address from fetchURL(). Vendored libraries, which eslint skips too,
// are left out.
describe("every fetch() in js/ and plugins/", () => {
  const vendored = (p) => /(\.min\.js$|\/jquery[^/]*\.js$|\/flotr|\/bootstrap|\/codemirror|\/vendor\/|\/node_modules\/|^\.\.\/js\/jquery\.flot\.js$)/.test(p);
  const walk = (dir) => readdirSync(dir).flatMap((n) => {
    const p = join(dir, n);
    return statSync(p).isDirectory() ? walk(p) : (p.endsWith(".js") && !vendored(p) ? [p] : []);
  });
  const calls = [];
  for (const file of [...walk("../js"), ...walk("../plugins")]) {
    readFileSync(file, { encoding: "utf-8" }).split("\n").forEach((line, i) => {
      const code = line.replace(/\/\/.*$/, "");
      for (const m of code.matchAll(/(?<![\w$.])(fetch|new\s+Request)\s*\(\s*(.{0,20})/g))
        calls.push({ where: `${file}:${i + 1}`, api: m[1], arg: m[2] });
    });
  }

  it("is found by this scan", () => {
    expect(calls.map((c) => c.where.replace(/:\d+$/, ""))).toEqual(expect.arrayContaining([
      "../js/common.js", "../plugins/log_history/init.js",
    ]));
  });

  it("takes its address from fetchURL()", () => {
    expect(calls.filter((c) => !c.arg.startsWith("fetchURL(")).map((c) => `${c.where} ${c.api}(${c.arg}`)).toEqual([]);
  });
});
