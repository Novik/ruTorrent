import { readFileSync } from "fs";

window.$ = require("jquery");

const OUTAGE = "Could not reach rTorrent over XMLRPC. Is rTorrent running?";

function h(char) {
  return Array.from({ length: 40 }, () => char).join("");
}

// A jqXHR as jQuery hands one to a fail handler, answering header questions
// case-insensitively the way a real one does.
function jqXHRLike(status, responseText, headers) {
  const sent = headers || {};
  return {
    status: status,
    responseText: responseText,
    getResponseHeader: (name) => {
      const wanted = String(name).toLowerCase();
      for (const key of Object.keys(sent))
        if (key.toLowerCase() === wanted) return sent[key];
      return null;
    },
  };
}

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

// The production files, loaded as the <script>s they are in the browser.
// webui.js's $(document).ready block is dropped: it is the page's own startup.
function loadStack() {
  window.TYPE_STRING = "string";
  window.TYPE_STRING_LABEL = "stringlabel";
  window.TYPE_NUMBER = "number";
  window.TYPE_PROGRESS = "progress";
  window.TYPE_PEERS = "peers";
  window.TYPE_SEEDS = "seeds";
  window.ALIGN_RIGHT = "right";
  window.theFormatter = {};
  window.dxSTable = function () {};
  window.dxSTable.prototype.create = jest.fn();
  window.dxSTable.prototype.getIdByCol = jest.fn(() => "name");
  window.dxSTable.prototype.setLazy = jest.fn();
  window.dxSTable.prototype.addRowById = jest.fn();
  window.dxSTable.prototype.setRowById = jest.fn();
  window.dxSTable.prototype.removeRow = jest.fn();
  window.rSpeedGraph = function () {};
  window.rSpeedGraph.prototype.addData = jest.fn();
  window.rSpeedGraph.prototype.create = jest.fn();
  window.Timer = function () {};
  window.Timer.prototype.start = jest.fn();
  window.Timer.prototype.stop = jest.fn();

  for (const src of ["../lang/en.js", "../js/common.js", "../js/content.js", "../js/rtorrent.js"]) {
    const el = document.createElement("script");
    el.textContent = readFileSync(src, { encoding: "utf-8" });
    document.body.appendChild(el);
  }
  let code = readFileSync("../js/webui.js", { encoding: "utf-8" });
  code = code.replace(/\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/, "");
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);

  window.noty = jest.fn();
  window.requestAnimationFrame = (callback) => {
    callback();
    return 1;
  };
  window.cancelAnimationFrame = jest.fn();
}

// Everything the page's own startup would have put on theWebUI, and nothing
// about the daemon: that comes from systemInfo below, as getplugins.php seeds it.
function prepareWebUI(started) {
  // addTorrents() replaces categoryList.statistic with what empty() handed it,
  // so the stand-in has to be able to answer empty() again on the next list.
  const statistic = { scan: jest.fn(), upload: 0, download: 0, panelIds: [] };
  statistic.empty = () => statistic;
  Object.assign(theWebUI, {
    url: "",
    showFlags: 0xffff,
    systemInfo: {
      rTorrent: {
        started: started,
        iVersion: 0x1014,
        apiVersion: 11,
        version: "0.16.20",
        libVersion: "0.16.20",
      },
    },
    settings: {
      "webui.retry_on_error": 4,
      "webui.update_interval": 2000,
      "webui.reqtimeout": 1000,
      "webui.show_dets": 1,
      "webui.show_cats": 1,
      "webui.hsplit": 0.5,
      "webui.vsplit": 0.5,
      "webui.fullrows": 0,
      "webui.no_delaying_draw": 0,
      "webui.effects": 0,
      "webui.selected_tab.keep": 0,
      "webui.selected_tab.last": "lcont",
    },
    timer: { start: jest.fn(), stop: jest.fn(), interval: 25 },
    interval: -1,
    updTimer: 0,
    firstLoad: true,
    torrents: {},
    dID: "",
    files: {},
    dirs: {},
    peers: {},
    trackers: {},
    taskAddTorrents: makeTaskQueue(),
    categoryList: {
      statistic: statistic,
      syncAfterScan: jest.fn(),
      config: jest.fn(),
    },
    getStatusIcon: jest.fn(() => ["icon", "status"]),
    setSpeedValues: jest.fn(),
    filterByLabel: jest.fn(),
    getAllTrackers: jest.fn(),
    getTotal: jest.fn(),
    updateViewRows: jest.fn(),
    loadTorrents: jest.fn(),
    clearDetails: jest.fn(),
    resize: jest.fn(),
    show: jest.fn(),
  });
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

// Drives a real "?list=1" poll and lets it fail the way the server failed it:
// getTorrents -> Ajax -> its fail handler -> listRequestFailed -> error.
// Only the transport is replaced.
function pollFails(status, body, headers) {
  const deferred = $.Deferred();
  const realAjax = $.ajax;
  $.ajax = () => deferred.promise();
  try {
    theWebUI.getTorrents("list=1");
  } finally {
    $.ajax = realAjax;
  }
  deferred.reject(jqXHRLike(status, body, headers), "error", "");
}

function pollTimesOut() {
  const deferred = $.Deferred();
  const realAjax = $.ajax;
  $.ajax = () => deferred.promise();
  try {
    theWebUI.getTorrents("list=1");
  } finally {
    $.ajax = realAjax;
  }
  deferred.reject(jqXHRLike(0, "", {}), "timeout", "");
}

// Drives one real request whose error callback is not the list poller's, and
// fails it the way the server failed it. Only the transport is replaced.
function requestFails(start, status, body, headers) {
  const deferred = $.Deferred();
  const realAjax = $.ajax;
  $.ajax = () => deferred.promise();
  try {
    start();
  } finally {
    $.ajax = realAjax;
  }
  deferred.reject(jqXHRLike(status, body, headers), "error", "");
}

describe("whether the daemon state follows the daemon", () => {
  beforeEach(() => {
    jest.useFakeTimers();
    document.body.innerHTML = "";
    loadStack();
    prepareWebUI(true);
    correctContent();
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
  });

  it("stops saying the daemon is up once an answer establishes it is not", () => {
    pollFails(500, OUTAGE, { "X-rTorrent-State": "unreachable" });

    expect(theWebUI.systemInfo.rTorrent.started).toBe(false);
    expect(noty).toHaveBeenCalledWith(
      "Bad response from server: (500 [error,list]) " + OUTAGE,
      "error"
    );
  });

  it.each([
    ["a refusal", 403, "Forbidden"],
    ["a gateway with nothing to say", 503, "Service unavailable"],
    ["a fault the daemon raised", 500, "The daemon raised this fault."],
    ["a page an intermediary substituted", 502, "<html><body>Bad Gateway</body></html>"],
  ])("leaves the state alone after %s", (_name, status, body) => {
    pollFails(status, body, {});

    expect(theWebUI.systemInfo.rTorrent.started).toBe(true);
  });

  it("leaves the state alone after a request that timed out locally", () => {
    pollTimesOut();

    expect(theWebUI.systemInfo.rTorrent.started).toBe(true);
  });

  it("leaves the state alone when the answer named a state it does not know", () => {
    pollFails(500, "Something else entirely", { "X-rTorrent-State": "some-later-state" });

    expect(theWebUI.systemInfo.rTorrent.started).toBe(true);
  });

  it("follows the daemon through any door, not only the list poll", () => {
    theWebUI.finishSettingsSave = jest.fn();
    requestFails(() => theWebUI.refreshSettingsAfterFailedSave(), 500, OUTAGE, {
      "X-rTorrent-State": "unreachable",
    });

    expect(theWebUI.systemInfo.rTorrent.started).toBe(false);
    expect(theWebUI.finishSettingsSave).toHaveBeenCalledTimes(1);
  });

  it("follows the daemon through a settings write that answered terminally", () => {
    window.theSearchEngines = { current: "none" };
    theWebUI.configured = true;
    theWebUI.activeView = "lcont";
    const released = jest.fn();
    requestFails(() => theWebUI.save(null, released), 500, OUTAGE, {
      "X-rTorrent-State": "unreachable",
    });

    expect(theWebUI.systemInfo.rTorrent.started).toBe(false);
    expect(released).toHaveBeenCalledTimes(1);
  });

  it("announces the daemon once when the list comes back, not on every poll", async () => {
    pollFails(500, OUTAGE, { "X-rTorrent-State": "unreachable" });
    expect(theWebUI.systemInfo.rTorrent.started).toBe(false);
    noty.mockClear();

    theWebUI.addTorrents({ torrents: { [h("A")]: { name: "one", size: 1 } } });
    await Promise.resolve();
    expect(theWebUI.systemInfo.rTorrent.started).toBe(true);
    expect(noty).toHaveBeenCalledWith(theUILang.linkTorTorrentRestored, "success");
    expect(noty).toHaveBeenCalledTimes(1);

    theWebUI.addTorrents({ torrents: { [h("A")]: { name: "one", size: 1 } } });
    theWebUI.addTorrents({ torrents: { [h("A")]: { name: "one", size: 1 } } });
    await Promise.resolve();
    expect(noty).toHaveBeenCalledTimes(1);
  });

  it("says nothing about a recovery that did not happen", async () => {
    theWebUI.addTorrents({ torrents: { [h("A")]: { name: "one", size: 1 } } });
    await Promise.resolve();

    expect(theWebUI.systemInfo.rTorrent.started).toBe(true);
    expect(noty).not.toHaveBeenCalled();
  });

  it("goes down and comes back as many times as the daemon does", async () => {
    for (let round = 0; round < 3; round++) {
      pollFails(500, OUTAGE, { "X-rTorrent-State": "unreachable" });
      expect(theWebUI.systemInfo.rTorrent.started).toBe(false);
      noty.mockClear();
      theWebUI.addTorrents({ torrents: {} });
      await Promise.resolve();
      expect(theWebUI.systemInfo.rTorrent.started).toBe(true);
      expect(noty).toHaveBeenCalledWith(theUILang.linkTorTorrentRestored, "success");
      expect(noty).toHaveBeenCalledTimes(1);
      noty.mockClear();
    }
  });
});

describe("the guards the daemon state drives", () => {
  beforeEach(() => {
    jest.useFakeTimers();
    document.body.innerHTML = "";
    loadStack();
    prepareWebUI(true);
    correctContent();
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
  });

  function goDown() {
    pollFails(500, OUTAGE, { "X-rTorrent-State": "unreachable" });
    expect(theWebUI.systemInfo.rTorrent.started).toBe(false);
  }

  // js/webui.js:2522 -- the poll itself, the one guard that runs on a timer.
  it("webui.js update(): stops asking for a list, and asks again on recovery", async () => {
    goDown();
    theWebUI.getTorrents = jest.fn();
    theWebUI.show = jest.fn();
    theWebUI.update();
    expect(theWebUI.getTorrents).not.toHaveBeenCalled();
    expect(theWebUI.show).toHaveBeenCalledTimes(1);

    theWebUI.systemInfo.rTorrent.started = true;
    theWebUI.update();
    expect(theWebUI.getTorrents).toHaveBeenCalledWith("list=1");
  });

  // js/webui.js:1011 -- opening the settings dialog, mid-session every time.
  it("webui.js showSettings(): opens the dialog without asking the daemon", () => {
    goDown();
    document.body.innerHTML += '<div id="stg" style="display:none"></div>';
    theWebUI.request = jest.fn();
    theWebUI.addAndShowSettings = jest.fn();
    theWebUI.showSettings();
    expect(theWebUI.request).not.toHaveBeenCalled();
    expect(theWebUI.addAndShowSettings).toHaveBeenCalledTimes(1);

    theWebUI.systemInfo.rTorrent.started = true;
    theWebUI.showSettings();
    expect(theWebUI.request.mock.calls[0][0]).toBe("?action=getsettings");
  });

  // plugins/create/init.js:83 -- the Create Torrent dialog, mid-session every time.
  it("plugins/create showCreate(): refuses to offer seeding, and offers it again", () => {
    goDown();
    document.body.innerHTML +=
      '<input id="trackers" value="http://tracker/announce">' +
      '<input id="deleteFromRecentTrackers" type="checkbox">' +
      '<input id="start_seeding" type="checkbox">' +
      '<label id="lbl_start_seeding"></label>';
    window.theDialogManager = { show: jest.fn(), make: jest.fn(), hide: jest.fn() };
    const { showCreate } = loadPlugin(
      "create",
      { loadLang: jest.fn(), loadMainCSS: jest.fn(), loadCSS: jest.fn(), name: "create" },
      ["showCreate"]
    );

    showCreate();
    expect($("#start_seeding").prop("disabled")).toBe(true);
    expect($("#lbl_start_seeding").hasClass("disabled")).toBe(true);

    theWebUI.systemInfo.rTorrent.started = true;
    showCreate();
    expect($("#start_seeding").prop("disabled")).toBe(false);
    expect($("#lbl_start_seeding").hasClass("disabled")).toBe(false);
  });

  // js/content.js:459 and :521 -- read when correctContent() runs, which is once,
  // from loadPlugins() at startup. The state it reads there is the state
  // getplugins.php seeded, so these decide the interface a page opened during an
  // outage gets; a state change later in the session cannot reach them.
  it("js/content.js correctContent(): drops what a stopped daemon cannot do", () => {
    window.rPlugin = function () {};
    const removed = {
      pages: [],
      tabs: [],
      buttons: [],
      separators: [],
      options: [],
    };
    rPlugin.prototype.removePageFromTabs = (id) => removed.tabs.push(id);
    rPlugin.prototype.removePageFromOptions = (id) => removed.options.push(id);
    rPlugin.prototype.removeButtonFromToolbar = (id) => removed.buttons.push(id);
    rPlugin.prototype.removeSeparatorFromToolbar = (id) => removed.separators.push(id);

    theWebUI.systemInfo.rTorrent.started = false;
    theWebUI.showFlags = 0xffff;
    correctContent();

    expect(theWebUI.showFlags & 0xffef).toBe(0);
    expect(removed.tabs).toEqual(
      expect.arrayContaining(["TrackerList", "FileList", "PeerList", "Speed"])
    );
    expect(removed.buttons).toEqual(
      expect.arrayContaining(["add", "remove", "start", "pause", "stop"])
    );

    removed.tabs.length = 0;
    removed.buttons.length = 0;
    theWebUI.systemInfo.rTorrent.started = true;
    theWebUI.showFlags = 0xffff;
    correctContent();

    expect(theWebUI.showFlags & 0xffef).toBe(0xffef);
    expect(removed.tabs).toEqual([]);
    expect(removed.buttons).toEqual([]);
  });

  // js/webui.js:454 and :494 -- read when config() runs, which is once, from
  // initFinish() at startup. Same standing as the two above.
  it("webui.js config(): hides the details pane and says there is no list", () => {
    document.body.innerHTML +=
      '<div id="cover"></div><div id="maincont"></div><div id="t"></div>' +
      '<div id="tdetails"></div><div id="VDivider"></div><div id="HDivider"></div>' +
      '<div id="offcanvas-sidepanel"></div><div id="lcont"></div>' +
      '<input id="query"><div id="PeerList"><div class="stable-body"></div></div>' +
      '<div id="trt-scp"></div>';
    window.theTabs = { show: jest.fn() };
    window.theDialogManager = { setEffects: jest.fn(), visible: [] };
    window.thePlugins = { list: {}, isInstalled: () => false };
    theWebUI.tables.trt.obj.scp = "#trt-scp";
    theWebUI.updateQuickSearch = jest.fn();
    theWebUI.createSpeedGraph = jest.fn();
    theWebUI.prsSelect = jest.fn();

    theWebUI.systemInfo.rTorrent.started = false;
    theWebUI.config();

    // jsdom lays nothing out, so :visible answers false for every element;
    // the inline display jQuery writes is what hide() and show() leave behind.
    expect($("#tdetails")[0].style.display).toBe("none");
    expect($("#VDivider")[0].style.display).toBe("none");
    expect($("#trt-scp").text()).toBe(theUILang.noTorrentList);

    $("#tdetails, #VDivider").hide();
    $("#trt-scp").text("");
    theWebUI.systemInfo.rTorrent.started = true;
    theWebUI.config();

    expect($("#tdetails")[0].style.display).not.toBe("none");
    expect($("#VDivider")[0].style.display).not.toBe("none");
    expect($("#trt-scp").text()).toBe("");
  });
});
