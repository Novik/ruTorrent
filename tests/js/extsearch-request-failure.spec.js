import { readFileSync } from "fs";

// An external search makes the toolbar query read-only until its answer
// arrives, and that hides the clear button. A search whose request fails has
// to give the field back, or it stays read-only and without its button until
// the page is reloaded, and the failure has to be reported.
//
// The real Ajax() and theWebUI are loaded; only the transport is replaced, by
// a jQuery Deferred the test settles as the server would.

window.$ = window.jQuery = require("jquery");
window.$$ = (id) => document.getElementById(id);

function addScript(code) {
  const el = document.createElement("script");
  el.textContent = code;
  document.head.appendChild(el);
}

// A top-level statement of plugins/extsearch/init.js, from its first line to
// the closing brace of its body.
function extract(src, needle) {
  const start = src.indexOf(needle);
  if (start < 0) throw new Error("not found in plugins/extsearch/init.js: " + needle);
  let i = src.indexOf("{", start);
  for (let depth = 0; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}" && --depth === 0) break;
  }
  return src.slice(start, i + 1);
}

function load() {
  document.head.innerHTML = "";
  window.theUILang = new Proxy({}, { get: (_target, prop) => prop });
  window.theFormatter = {};
  window.TYPE_STRING = "string";
  window.TYPE_NUMBER = "number";
  window.TYPE_PROGRESS = "progress";
  window.TYPE_PEERS = "peers";
  window.TYPE_SEEDS = "seeds";
  window.ALIGN_RIGHT = "right";
  window.dxSTable = function () {};
  window.rSpeedGraph = function () {};
  window.Timer = function () {};
  window.dStatus = { started: 1, paused: 2, checking: 4, hashing: 8, error: 16 };
  for (const src of ["../js/common.js", "../js/rtorrent.js"]) {
    addScript(readFileSync(src, { encoding: "utf-8" }));
  }
  addScript(
    readFileSync("../js/webui.js", { encoding: "utf-8" }).replace(
      /\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/,
      ""
    )
  );
  theWebUI.systemInfo = { rTorrent: { started: true } };
  theWebUI.categoryList = { setQuickSearch: jest.fn(), refresh: jest.fn() };

  const html = readFileSync("../index.html", { encoding: "utf-8" });
  const doc = new DOMParser().parseFromString(html, "text/html");
  document.body.innerHTML = doc.getElementById("rc").outerHTML
    + '<select id="exscategory"><option value="all">all</option></select>';

  const init = readFileSync("../plugins/extsearch/init.js", { encoding: "utf-8" });
  window.plugin = { enabled: true, tegs: {}, lastTeg: 0 };
  window.catlist = { switchLabel: jest.fn(), refresh: jest.fn() };
  window.theSearchEngines = {
    current: 0,
    checkForIncorrectCurrent: jest.fn(),
    getEngName: (eng) => eng,
  };
  // From where run() is replaced to the end of the request stub it sends.
  const runStart = init.indexOf("theSearchEngines.run = function");
  const stubStart = init.indexOf("rTorrentStub.prototype.extsearch = function");
  const stubEnd = init.indexOf("\nrTorrentStub.prototype.loadtegtorrents", stubStart);
  addScript(init.slice(runStart, stubEnd));
  for (const needle of [
    "theWebUI.extSearchFailed = function",
    "theWebUI.setExtSearchTag = function",
    "theWebUI.tegRefresh = function",
  ]) {
    if (init.indexOf(needle) >= 0) addScript(extract(init, needle));
  }
  window.noty = jest.fn();
}

const field = () => $$("query");
const button = () => $$("query_clear");

// Replace the transport for the next request and return its Deferred.
function nextRequest() {
  const deferred = $.Deferred();
  const realAjax = $.ajax;
  const sent = [];
  $.ajax = (options) => {
    sent.push(options);
    $.ajax = realAjax;
    return deferred.promise();
  };
  return { deferred, sent };
}

function failWith(deferred, status, responseText) {
  deferred.reject(
    { status, responseText, getResponseHeader: () => null },
    "error",
    "Internal Server Error"
  );
}

function search(text) {
  field().value = text;
  theWebUI.updateQuickSearch();
  theSearchEngines.run();
}

describe("extsearch request failure", () => {
  beforeEach(load);

  it("sends the search and holds the query read-only until it is answered", () => {
    const { sent } = nextRequest();
    search("ubuntu");
    expect(sent.length).toBe(1);
    expect(sent[0].url).toBe("plugins/extsearch/action.php");
    expect(sent[0].data).toContain("what=ubuntu");
    expect(field().readOnly).toBe(true);
    expect(button().hidden).toBe(true);
  });

  it("gives the query and its clear button back when the search fails", () => {
    const { deferred } = nextRequest();
    search("ubuntu");
    failWith(deferred, 500, "engine exploded");
    expect(field().readOnly).toBe(false);
    expect(field().value).toBe("ubuntu");
    expect(button().hidden).toBe(false);
  });

  it("reports the failure as any other failed request is reported", () => {
    const { deferred } = nextRequest();
    search("ubuntu");
    failWith(deferred, 500, "engine exploded");
    expect(noty).toHaveBeenCalledTimes(1);
    expect(noty.mock.calls[0][0]).toBe("Bad response from server: (500 [error,extsearch]) engine exploded");
    expect(noty.mock.calls[0][1]).toBe("error");
  });

  it("lets the query be typed into and cleared again after a failure", () => {
    const { deferred } = nextRequest();
    search("ubuntu");
    failWith(deferred, 502, "");
    theWebUI.clearSearch();
    expect(field().value).toBe("");
    expect(button().hidden).toBe(true);
  });

  it("gives the query back when a refreshed result fails", () => {
    plugin.tegs.teg_1 = { what: "debian", eng: "all", cat: "all", data: [] };
    theWebUI.activeExtTegId = () => "teg_1";
    const { deferred } = nextRequest();
    theWebUI.tegRefresh();
    expect(field().readOnly).toBe(true);
    failWith(deferred, 500, "engine exploded");
    expect(field().readOnly).toBe(false);
    expect(field().value).toBe("debian");
    expect(button().hidden).toBe(false);
    expect(noty).toHaveBeenCalledTimes(1);
  });

  it("still opens the result when the search succeeds", () => {
    const { deferred } = nextRequest();
    search("ubuntu");
    deferred.resolve({ eng: "all", cat: "all", data: ["r"] }, "success", { getResponseHeader: () => null });
    expect(field().readOnly).toBe(false);
    expect(button().hidden).toBe(false);
    expect(catlist.switchLabel).toHaveBeenCalledWith("psearch", "extteg_0");
    expect(plugin.tegs.extteg_0.data).toEqual(["r"]);
    expect(noty).not.toHaveBeenCalled();
  });
});
