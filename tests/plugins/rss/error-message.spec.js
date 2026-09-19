import { readFileSync } from "fs";

window.$ = require("jquery");

// theWebUI.showErrors renders the rss plugin's error list. Each entry is built
// by plugins/rss/rss.php and travels to the browser as json, so its contents
// are whatever the server put there -- and one of the error texts is assembled
// from a feed server's own http status line (Snoopy.class.inc:526-528 keeps the
// status token from the response's status line, and rss.php:152 concatenates
// it into the message). None of it may be treated as code.
const PAYLOAD = "window.__rssErrorEval = 'RAN'";

function stubGlobals() {
  window.theUILang = new Proxy(
    { cantFetchRSS: "Cannot fetch feed", rssDontExist: "No such feed" },
    { get: (target, prop) => (prop in target ? target[prop] : prop) }
  );
  window.theDialogManager = {
    show: () => {},
    hide: () => {},
    setLayer: () => {},
    init: () => {},
    bindEvent: () => {},
  };
  window.thePlugins = {
    get: () => ({ langLoaded: () => {}, allStuffLoaded: true }),
    isInstalled: () => false,
  };
  window.injectCustomElementCSS = () => {};
  window.injectCustomElementAttribute = () => {};
  window.injectScript = () => {};
  window.rTorrentStub = function () {};
  window.dxSTable = function () {};
  window.Timer = function () {};
  window.theConverter = { bytes: () => "", date: () => "12:00", time: () => "" };
  window.theTabs = { add: () => {}, show: () => {}, resize: () => {} };
  window.theContextMenu = { add: () => {}, show: () => {}, clear: () => {} };
  window.iv = (v) => parseInt(v, 10) || 0;
  window.escapeHTML = (v) => String(v ?? "");
  window.TYPE_STRING = "string";
  window.TYPE_NUMBER = "number";
  window.ALIGN_RIGHT = "right";
  window.notyCalls = [];
  window.noty = (msg) => window.notyCalls.push(msg);
  window.catlist = {
    refreshAndSyncPanel: () => {},
    refreshPanel: () => {},
  };
}

window.__makePluginStub = (path) =>
  new Proxy(
    { path, name: "p", enabled: true },
    {
      has: () => true,
      get: (target, prop) => (prop in target ? target[prop] : () => {}),
    }
  );

function runPluginSource(path) {
  const code = readFileSync(path, { encoding: "utf-8" });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function(){ var plugin = window.__makePluginStub('" +
    path.replace(/init\.js$/, "") +
    "'); \ntry {\n" +
    code +
    "\n} catch(e) { window.__pluginLoadError = e; }\n})();";
  document.body.appendChild(scriptEl);
}

function makeCategoryList() {
  return {
    panelLabelAttribs: { prss: new Map() },
    refreshPanel: {},
    isLabelIdSelected: () => false,
    switchLabel: () => {},
    contextMenuEntries: () => [],
    syncAfterScan: () => {},
    statistic: { empty: () => ({ scan: () => {} }) },
  };
}

function showErrors(errors) {
  document.body.innerHTML = "";
  delete window.__rssErrorEval;
  delete window.__pluginLoadError;
  stubGlobals();
  window.theWebUI = {
    categoryList: makeCategoryList(),
    settings: {},
    rssGroups: {},
    rssLabels: {},
    delayedRSSErrors: {},
    rssShowErrorsDelayed: false,
    maxThrottle: 0,
    maxRatio: 0,
    isCorrectThrottle: () => false,
    isCorrectRatio: () => false,
    getTable: () => ({
      clearRows: () => {},
      updateRows: () => {},
      setRowById: () => {},
      removeRow: () => {},
      setLazy: () => {},
    }),
    setDisableControls: () => {},
    resizeTop: () => {},
    resize: () => {},
    request: () => {},
    save: () => {},
  };
  runPluginSource("../plugins/rss/init.js");
  expect(window.__pluginLoadError).toBeUndefined();
  // The plugin's own load sets this from the stored settings; the spec wants
  // the message shown at once rather than parked for the next panel refresh.
  theWebUI.rssShowErrorsDelayed = false;
  theWebUI.showErrors(errors);
  return window.notyCalls;
}

describe("rss error messages", () => {
  it("does not run an error description the server sent", () => {
    showErrors([{ time: 0, desc: PAYLOAD, prm: "" }]);
    expect(window.__rssErrorEval).toBeUndefined();
  });

  it("does not run code spliced into the feed's http status", () => {
    // What the server produces when a feed answers with a status line whose
    // status token carries a quote: rss.php:152 concatenates the token into
    // the detail text.
    const detail = "[RSS-HTTP-Error] Status: 500' + " + PAYLOAD + " + '";
    showErrors([
      {
        time: 0,
        key: "cantFetchRSS",
        detail,
        prm: "https://feed.example.org/rss",
      },
    ]);
    expect(window.__rssErrorEval).toBeUndefined();
    expect(window.notyCalls.join("\n")).toContain(detail);
  });

  it("renders the lang key's text and the detail as one message", () => {
    const calls = showErrors([
      { time: 0, key: "cantFetchRSS", detail: "boom", prm: "https://x/rss" },
    ]);
    expect(calls.length).toBe(1);
    expect(calls[0]).toContain("Cannot fetch feed");
    expect(calls[0]).toContain("boom");
    expect(calls[0]).toContain("(https://x/rss)");
  });

  it("renders an error that carries no detail", () => {
    const calls = showErrors([{ time: 0, key: "rssDontExist", prm: "" }]);
    expect(calls.length).toBe(1);
    expect(calls[0]).toContain("No such feed");
    expect(calls[0]).not.toContain("undefined");
  });

  it("names the feed a label is known for", () => {
    document.body.innerHTML = "";
    stubGlobals();
    const calls = showErrors([]);
    expect(calls).toEqual([]);
    theWebUI.rssLabels = { h1: { name: "My Feed", url: "https://x/rss" } };
    theWebUI.showErrors([
      { time: 0, key: "cantFetchRSS", detail: "boom", prm: "https://x/rss" },
    ]);
    expect(window.notyCalls[0]).toContain("<My Feed>");
  });
});
