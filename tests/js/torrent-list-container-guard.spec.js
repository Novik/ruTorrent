import { readFileSync } from "fs";

window.$ = require("jquery");

function h(char) {
  return Array.from({ length: 40 }, () => char).join("");
}

const HASH = h("A");

function makeTaskQueue() {
  const queue = {
    reset: jest.fn(() => queue),
    map: jest.fn((items, callback) => {
      items.forEach(callback);
      return queue;
    }),
    enqueueFunc: jest.fn((callback) => {
      callback();
      return queue;
    }),
    run: jest.fn(() => Promise.resolve()),
  };
  return queue;
}

// php/getplugins.php emits each plugin's init.js inside
// (function () { var plugin = new rPlugin(...); <init.js> })(), so plugin is
// local to the file and so is everything the file declares. Loading one any
// other way puts those declarations in the page's own scope, where a second
// plugin would read the wrong plugin object. Names in `exports` are handed back
// from inside that scope, which is the only way a test can reach one.
function loadPlugin(name, pluginObject, exports) {
  window.__pluginUnderLoad = pluginObject;
  window.__pluginExports = {};
  const handback = (exports || [])
    .map((id) => "window.__pluginExports[" + JSON.stringify(id) + "] = " + id + ";")
    .join("\n");
  const el = document.createElement("script");
  el.textContent =
    "(function () { var plugin = window.__pluginUnderLoad;\n" +
    readFileSync("../plugins/" + name + "/init.js", { encoding: "utf-8" }) +
    "\n" + handback + "\n})();";
  document.body.appendChild(el);
  return window.__pluginExports;
}

function loadWebUI() {
  window.theUILang = new Proxy({}, { get: (_target, prop) => prop });
  window.theFormatter = {};
  window.theConverter = { bytes: (v) => v, time: (v) => v, round: (v) => v };
  window.TYPE_STRING = "string";
  window.TYPE_STRING_LABEL = "stringlabel";
  window.TYPE_NUMBER = "number";
  window.TYPE_PROGRESS = "progress";
  window.TYPE_PEERS = "peers";
  window.TYPE_SEEDS = "seeds";
  window.ALIGN_RIGHT = "right";
  window.dxSTable = function () {};
  window.rSpeedGraph = function () {};
  window.rSpeedGraph.prototype.addData = jest.fn();
  window.Timer = function () {};
  window.iv = Number;
  window.$type = (v) => (v === undefined || v === null ? false : typeof v);
  window.rGraph = function () {};
  window.rTorrentStub = function () {};
  window.thePlugins = { isInstalled: () => false };
  window.noty = jest.fn();
  window.requestAnimationFrame = (callback) => {
    callback();
    return 1;
  };
  window.cancelAnimationFrame = jest.fn();

  let code = readFileSync("../js/webui.js", { encoding: "utf-8" });
  code = code.replace(/\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/, "");
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

/**
 * Installs the real wrappers two shipped plugins put over addTorrents, in the
 * order a page loads them, and a sentinel on top of both.
 *
 * plugins/mobile installs a third wrapper in the same slot. It is not loaded
 * here -- it installs from the success handler of the request that fetches
 * mobile.html -- and it is the one wrapper that never dereferenced the
 * container first: it delegates, then asks whether listData.torrents is there.
 * The sentinel stands in its place, so what the cases below say about the
 * outermost wrapper is what they say about any wrapper in this slot.
 */
function columnPlugin(name) {
  return {
    loadLang: jest.fn(),
    loadMainCSS: jest.fn(),
    loadCSS: jest.fn(),
    canChangeColumns: () => true,
    canChangeTabs: () => false,
    canChangeOptions: () => false,
    canChangeMenu: () => false,
    collectStatForTorrents: true,
    allStuffLoaded: false,
    updateInterval: 60,
    name: name,
  };
}

function menuPlugin(name) {
  return Object.assign(columnPlugin(name), {
    canChangeColumns: () => false,
    canChangeMenu: () => true,
  });
}

function installWrappers(entered) {
  theWebUI.config = jest.fn();
  theWebUI.request = jest.fn();

  loadPlugin("uploadeta", columnPlugin("uploadeta"));
  // uploadeta installs its hook when the interface is configured.
  theWebUI.config();
  const afterUploadeta = theWebUI.addTorrents;

  loadPlugin("trafic", columnPlugin("trafic"));
  const afterTrafic = theWebUI.addTorrents;

  // These two wrap nothing here; they are loaded for the list each of their
  // writes asks for on the side.
  for (const name of ["ratio", "throttle"])
    loadPlugin(name, menuPlugin(name));

  const inner = theWebUI.addTorrents;
  theWebUI.addTorrents = function (data) {
    entered.push("outermost");
    return inner.call(this, data);
  };

  return {
    uploadetaWrapped: afterUploadeta !== undefined,
    traficWrapped: afterTrafic !== afterUploadeta,
  };
}

describe("a torrent list container that is not one", () => {
  let table;
  let rows;
  // trafic declares a class in the page's own scope, which a second load of the
  // file cannot redeclare, so the page and its plugins are loaded once here and
  // only the state each case needs is renewed below.
  const entered = [];
  let installed;

  beforeAll(() => {
    document.body.innerHTML = "";
    loadWebUI();
    installed = installWrappers(entered);
  });

  beforeEach(() => {
    jest.useFakeTimers();
    document.body.innerHTML = '<div id="cover"></div>';
    Object.defineProperty(document.getElementById("cover"), "offsetWidth", { value: 1 });

    rows = { [HASH]: { name: "existing" } };
    table = {
      setLazy: jest.fn(),
      setRowById: jest.fn((torrent, hash) => {
        rows[hash] = torrent;
      }),
      removeRow: jest.fn((hash) => {
        delete rows[hash];
      }),
      rowSel: { [HASH]: true },
    };
    const statistic = { scan: jest.fn(), upload: 0, download: 0, panelIds: [] };
    statistic.empty = () => statistic;
    Object.assign(theWebUI, {
      url: "",
      systemInfo: { rTorrent: { started: true } },
      settings: { "webui.retry_on_error": 4, "webui.update_interval": 2000 },
      timer: { start: jest.fn(), stop: jest.fn(), interval: 25 },
      interval: -1,
      updTimer: 0,
      firstLoad: true,
      uploadtarget: 100,
      ratiosStat: { [HASH]: [1, 2, 3] },
      torrents: { [HASH]: { name: "existing", label: "old" } },
      dID: HASH,
      files: { [HASH]: ["file"] },
      dirs: { [HASH]: ["dir"] },
      peers: { [HASH]: ["peer"] },
      trackers: { [HASH]: ["tracker"] },
      taskAddTorrents: makeTaskQueue(),
      categoryList: {
        statistic: statistic,
        syncAfterScan: jest.fn(),
      },
      requestWithTimeout: jest.fn(),
      getTable: jest.fn(() => table),
      getStatusIcon: jest.fn(() => ["icon", "status"]),
      setSpeedValues: jest.fn(),
      filterByLabel: jest.fn(),
      getAllTrackers: jest.fn(),
      getTotal: jest.fn(),
      updateViewRows: jest.fn(),
      loadTorrents: jest.fn(),
      clearDetails: jest.fn(),
      update: jest.fn(),
      resize: jest.fn(),
      show: jest.fn(),
    });

    entered.length = 0;
    theWebUI.requestWithTimeout = jest.fn();
    theWebUI.request = jest.fn();
    noty.mockClear();
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
  });

  // The list poll's own door: whatever getTorrents hands the transport is the
  // head of the chain, which is the thing the server's answer reaches first.
  function answerThePoll(response) {
    theWebUI.getTorrents("list=1");
    const [callback, scope] = theWebUI.requestWithTimeout.mock.calls[0][1];
    return () => callback.call(scope, response);
  }

  it("has both shipped wrappers in the chain", () => {
    expect(installed.uploadetaWrapped).toBe(true);
    expect(installed.traficWrapped).toBe(true);
  });

  it("lets a list the wrappers can annotate through to the table", async () => {
    answerThePoll({ torrents: { [HASH]: { name: "one", size: 400, uploaded: 100, ul: 10 } } })();
    await Promise.resolve();

    expect(entered).toEqual(["outermost"]);
    // uploadeta's columns and trafic's, computed by the real wrappers.
    expect(rows[HASH].upload_target).toBe(400);
    expect(rows[HASH].upload_remaining).toBe(300);
    expect(rows[HASH].upload_eta).toBe(30);
    expect(rows[HASH].ratioday).toBe(1 / 400);
    expect(rows[HASH].ratiomonth).toBe(3 / 400);
    expect(noty).not.toHaveBeenCalled();
  });

  it.each([
    ["bodyless 204", ""],
    ["undefined", undefined],
    ["null", null],
    ["false", false],
    ["number", 1],
    ["text", "unexpected body"],
    ["array", []],
    ["missing torrents", {}],
    ["null torrents", { torrents: null }],
    ["array torrents", { torrents: [] }],
    ["boolean torrents", { torrents: true }],
    ["number torrents", { torrents: 1 }],
    ["text torrents", { torrents: "invalid" }],
  ])("refuses %s before any wrapper reads it", (_name, response) => {
    const savedTorrents = theWebUI.torrents;

    expect(answerThePoll(response)).not.toThrow();

    expect(entered).toEqual([]);
    expect(noty).toHaveBeenCalledTimes(1);
    expect(noty).toHaveBeenCalledWith(
      "Bad response from server: (Invalid torrent list response) Missing torrents",
      "error"
    );
    expect(theWebUI.torrents).toBe(savedTorrents);
    expect(rows).toEqual({ [HASH]: { name: "existing" } });
    expect(theWebUI.dID).toBe(HASH);
    expect(theWebUI.files[HASH]).toEqual(["file"]);
  });

  // Everything else in the tree that asks for a list on the side of a write:
  // the two label writes here, and setratio and setthrottle in their plugins.
  it.each([
    ["setLabel", () => theWebUI.setLabel("new")],
    ["removeLabel", () => theWebUI.removeLabel()],
    ["setRatio", () => theWebUI.setRatio("-1")],
    ["setThrottle", () => theWebUI.setThrottle("-1")],
  ])("refuses the same container on the list %s asks for", (_name, write) => {
    write();
    expect(theWebUI.request.mock.calls.length).toBe(1);
    const [callback, scope] = theWebUI.request.mock.calls[0][1];

    expect(() => callback.call(scope, undefined)).not.toThrow();
    expect(entered).toEqual([]);
    expect(noty).toHaveBeenCalledWith(
      "Bad response from server: (Invalid torrent list response) Missing torrents",
      "error"
    );
  });

  it("still refuses a container handed straight to the unwrapped function", () => {
    // The wrappers are the plugins' own; the base keeps its floor so a caller
    // that reaches past them is answered rather than throwing.
    expect(() => theWebUI.webui_addTorrents(undefined)).not.toThrow();
    expect(noty).toHaveBeenCalledWith(
      "Bad response from server: (Invalid torrent list response) Missing torrents",
      "error"
    );
  });
});
