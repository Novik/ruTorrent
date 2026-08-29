import { readFileSync } from "fs";

window.$ = require("jquery");
window.FileReader = window.FileReader || function () {};
// plugins/filedrop/lang/en.js calls back into thePlugins when loaded directly,
// as it would via the real plugin loader.
window.thePlugins = { get: () => ({ langLoaded: () => {} }) };

for (const src of [
  "../lang/en.js",
  "../js/common.js",
  "../plugins/filedrop/lang/en.js", // provides theUILang.tooManyFiles etc; normally merged in by plugin.loadLang()
]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

window.theWebUI = { settings: {} };
window.injectScript = () => {}; // jquery.filedrop.js / #maincont drop wiring isn't under test here

let plugin;
{
  const code = readFileSync("../plugins/filedrop/init.js", { encoding: "utf-8" });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "window.__filedropPlugin = (function () { var plugin = { path: '../plugins/filedrop/', " +
    "loadLang: function () {}, disable: function () {} }; " +
    code +
    "\nreturn plugin; })();";
  document.body.appendChild(scriptEl);
  plugin = window.__filedropPlugin;
}

// jsdom does not implement HTMLElement.isContentEditable (the "editing host"
// algorithm), so setting the contenteditable attribute has no effect on it.
// Tests stub the property directly on the element instead.
function makeContentEditableDiv() {
  const div = document.createElement("div");
  div.isContentEditable = true;
  document.body.appendChild(div);
  return div;
}

// handlePaste fires addUrls without awaiting it; spy on the real
// implementation so tests can await the exact promise it returns.
function callPasteAndWait(ev) {
  const spy = jest.spyOn(plugin, "addUrls");
  plugin.handlePaste(ev);
  const result = spy.mock.results[0]?.value;
  spy.mockRestore();
  return result ?? Promise.resolve();
}

const MAGNET = "magnet:?xt=urn:btih:08ada5a7a6183aae1e09d831df6748d566095a10&dn=Sintel";

function pasteEvent(target, text) {
  const cd = { getData: (type) => (type === "text/plain" ? text : "") };
  const ev = new Event("paste", { bubbles: true, cancelable: true });
  ev.clipboardData = cd;
  Object.defineProperty(ev, "target", { value: target });
  return ev;
}

describe("filedrop: paste-to-add", () => {
  let ajaxMock;

  beforeEach(() => {
    ajaxMock = jest.fn(() => Promise.resolve({ result: "Success" }));
    window.$.ajax = ajaxMock;
    jest.spyOn(window, "noty").mockImplementation(() => {});
    plugin.queuefiles = null;
    plugin.maxfiles = null;
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it("ignores a paste into an editable field", () => {
    const input = document.createElement("input");
    document.body.appendChild(input);

    const handled = plugin.handlePaste(pasteEvent(input, MAGNET));

    expect(ajaxMock).not.toHaveBeenCalled();
    input.remove();
  });

  it("ignores a paste into a contenteditable element", () => {
    const div = makeContentEditableDiv();

    plugin.handlePaste(pasteEvent(div, MAGNET));

    expect(ajaxMock).not.toHaveBeenCalled();
    div.remove();
  });

  it("ignores a paste with no magnet or torrent URL in it", () => {
    plugin.handlePaste(pasteEvent(document.body, "just some ordinary text"));

    expect(ajaxMock).not.toHaveBeenCalled();
  });

  it("adds a magnet link pasted outside a text field", async () => {
    const ev = pasteEvent(document.body, MAGNET);
    const preventDefault = jest.spyOn(ev, "preventDefault");

    await callPasteAndWait(ev);

    expect(preventDefault).toHaveBeenCalled();
    expect(ajaxMock).toHaveBeenCalledWith(
      expect.objectContaining({
        url: "../plugins/filedrop/../../php/addtorrent.php",
        method: "POST",
        data: { url: MAGNET, json: 1 },
      })
    );
    expect(window.noty).toHaveBeenCalledWith(
      expect.stringContaining(MAGNET),
      "success"
    );
  });

  it("extracts every magnet/URL from a multi-line paste", async () => {
    const magnetB =
      "magnet:?xt=urn:btih:2222222222222222222222222222222222222222&dn=B";
    const text = `${MAGNET}\n${magnetB}`;

    await callPasteAndWait(pasteEvent(document.body, text));

    expect(ajaxMock).toHaveBeenCalledTimes(2);
    expect(ajaxMock.mock.calls.map((c) => c[0].data.url).sort()).toEqual(
      [MAGNET, magnetB].sort()
    );
  });

  it("ignores a paste that mixes a URL with ordinary prose", () => {
    const text = `look at this ${MAGNET} and check it out`;

    plugin.handlePaste(pasteEvent(document.body, text));

    expect(ajaxMock).not.toHaveBeenCalled();
  });

  it("adds a lone non-torrent URL pasted outside a text field", async () => {
    // Trackers link to torrent files with URLs that don't end in
    // ".torrent" (e.g. RUTracker's /forum/dl.php?t=, Kinozal's
    // /download.php?id=), so a whole-paste URL is added regardless of
    // its path/extension -- this is a deliberate tradeoff, not an
    // oversight.
    const url = "https://example.com/news/article-about-cats";

    await callPasteAndWait(pasteEvent(document.body, url));

    expect(ajaxMock).toHaveBeenCalledWith(
      expect.objectContaining({ data: { url, json: 1 } })
    );
  });

  it("splits a comma-separated list of magnet links", async () => {
    const magnetB =
      "magnet:?xt=urn:btih:2222222222222222222222222222222222222222&dn=B";
    const text = `${MAGNET},${magnetB}`;

    await callPasteAndWait(pasteEvent(document.body, text));

    expect(ajaxMock).toHaveBeenCalledTimes(2);
    expect(ajaxMock.mock.calls.map((c) => c[0].data.url).sort()).toEqual(
      [MAGNET, magnetB].sort()
    );
  });

  it("splits a semicolon-separated list of magnet links with spacing", async () => {
    const magnetB =
      "magnet:?xt=urn:btih:2222222222222222222222222222222222222222&dn=B";
    const text = `${MAGNET}; ${magnetB}`;

    await callPasteAndWait(pasteEvent(document.body, text));

    expect(ajaxMock).toHaveBeenCalledTimes(2);
    expect(ajaxMock.mock.calls.map((c) => c[0].data.url).sort()).toEqual(
      [MAGNET, magnetB].sort()
    );
  });

  it("does not split a comma-joined tracker list inside one magnet link", async () => {
    // Multi-tracker magnets commonly comma-join announce URLs within a
    // single tr= parameter; that comma must not be mistaken for a
    // separator between two pasted links.
    const magnetWithTrackers =
      "magnet:?xt=urn:btih:3333333333333333333333333333333333333333&dn=C" +
      "&tr=http://t1.example/announce,http://t2.example/announce";

    await callPasteAndWait(pasteEvent(document.body, magnetWithTrackers));

    expect(ajaxMock).toHaveBeenCalledTimes(1);
    expect(ajaxMock).toHaveBeenCalledWith(
      expect.objectContaining({
        data: { url: magnetWithTrackers, json: 1 },
      })
    );
  });

  it("reports a failed add", async () => {
    ajaxMock.mockImplementation(() => Promise.resolve({ result: "Failed" }));

    await callPasteAndWait(pasteEvent(document.body, MAGNET));

    expect(window.noty).toHaveBeenCalledWith(
      expect.stringContaining(MAGNET),
      "error"
    );
  });
});

describe("filedrop: addUrls chunking (shared by paste and drop)", () => {
  beforeEach(() => {
    window.$.ajax = jest.fn(() => Promise.resolve({ result: "Success" }));
    jest.spyOn(window, "noty").mockImplementation(() => {});
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  it("sends every URL in one batch when queuefiles is unset", async () => {
    plugin.queuefiles = null;
    plugin.maxfiles = null;

    await plugin.addUrls(["magnet:?xt=urn:btih:" + "1".repeat(40), "magnet:?xt=urn:btih:" + "2".repeat(40)]);

    expect(window.$.ajax).toHaveBeenCalledTimes(2);
  });

  it("rejects the whole batch when it exceeds maxfiles with no queuefiles set", async () => {
    plugin.queuefiles = null;
    plugin.maxfiles = 1;

    await plugin.addUrls(["magnet:?xt=urn:btih:" + "1".repeat(40), "magnet:?xt=urn:btih:" + "2".repeat(40)]);

    expect(window.$.ajax).not.toHaveBeenCalled();
    expect(window.noty).toHaveBeenCalledWith(
      expect.stringContaining(String(1)),
      "error"
    );
  });

  it("sends URLs in queuefiles-sized chunks with a delay between them", async () => {
    plugin.queuefiles = 1;
    plugin.maxfiles = null;

    const promise = plugin.addUrls([
      "magnet:?xt=urn:btih:" + "1".repeat(40),
      "magnet:?xt=urn:btih:" + "2".repeat(40),
    ]);

    await Promise.resolve(); // let the first chunk's ajax call fire
    expect(window.$.ajax).toHaveBeenCalledTimes(1);

    await jest.advanceTimersByTimeAsync(200);
    await promise;

    expect(window.$.ajax).toHaveBeenCalledTimes(2);
  });
});
