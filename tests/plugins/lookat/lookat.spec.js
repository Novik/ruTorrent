import { readFileSync } from "fs";

window.$ = require("jquery");

// js/common.js is what the plugin leans on for the check under test, and the
// plugin is loaded the way the plugin loader loads it: a classic script inside
// a closure that holds `plugin`.
function loadCommon() {
  for (const src of ["../lang/en.js", "../js/common.js"]) {
    const el = document.createElement("script");
    el.textContent = readFileSync(src, { encoding: "utf-8" });
    document.body.appendChild(el);
  }
}

function loadLookat(lookData) {
  const code = readFileSync("../plugins/lookat/init.js", { encoding: "utf-8" });
  const el = document.createElement("script");
  el.textContent =
    "(function(){ var plugin = new Proxy({ name: 'lookat', enabled: true," +
    " allStuffLoaded: true, lookData: " + JSON.stringify(lookData) + "," +
    " partsToRemove: 'nothing-at-all', canChangeMenu: () => true," +
    " canChangeOptions: () => false }," +
    " { has: () => true, get: (t, p) => (p in t ? t[p] : () => {})," +
    "   set: (t, p, v) => { t[p] = v; return true; } });" +
    "\ntry {\n" + code + "\n} catch(e) { window.__loadError = e; }\n})();";
  document.body.appendChild(el);
}

describe("lookat opens a search target", () => {
  let opened;

  beforeEach(() => {
    document.body.innerHTML = "";
    delete window.__ran;
    delete window.__loadError;
    opened = [];
    window.$type = (o) =>
      o == undefined ? false : o.constructor == Array ? "array" : typeof o;
    window.theUILang = new Proxy({}, { get: (_t, p) => p });
    window.theDialogManager = { make: () => {}, show: () => {}, hide: () => {}, setHandler: () => {} };
    window.theContextMenu = { add: () => {}, get: () => null };
    window.rTorrentStub = function () {};
    window.theWebUI = {
      torrents: { HASH: { name: "Some Release" } },
      getTable: () => ({ getFirstSelected: () => "HASH", selCount: 1 }),
      createMenu: () => {},
      addAndShowSettings: () => {},
      setSettings: () => {},
      isTorrentCommandEnabled: () => true,
      attachPageToOptions: () => {},
      request: () => {},
    };
    window.open = (...args) => {
      opened.push(args);
      return null;
    };
    loadCommon();
  });

  it("opens an http target", () => {
    loadLookat({ Search: "https://example.com/find?q={title}" });
    expect(window.__loadError).toBeUndefined();
    theWebUI.lookAt("Search");
    expect(opened.length).toBe(1);
    expect(opened[0][0]).toBe("https://example.com/find?q=Some+Release");
  });

  it("opens nothing for a javascript: target", () => {
    loadLookat({ Bad: "javascript:window.__ran='YES';//{title}" });
    expect(window.__loadError).toBeUndefined();
    theWebUI.lookAt("Bad");
    expect(opened).toEqual([]);
  });

  it("opens nothing for a data: target", () => {
    loadLookat({ Bad: "data:text/html,<img src=x onerror=1>{title}" });
    expect(window.__loadError).toBeUndefined();
    theWebUI.lookAt("Bad");
    expect(opened).toEqual([]);
  });
});
