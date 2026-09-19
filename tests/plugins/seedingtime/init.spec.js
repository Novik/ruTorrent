import { readFileSync } from "fs";

window.$ = require("jquery");

// objects.js supplies the real theDialogManager, so the tests see how its
// setHandler/addHandler treat a handler another plugin registered first.
for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// Minimal scaffolding for plugins/seedingtime/init.js: capture the request
// callbacks it registers instead of running a real request manager.
window.TYPE_NUMBER = "number";
window.thePlugins = { isInstalled: () => false };
// The plugin wraps dxSTable.prototype.setRowById; keep the original call
// observable so the tests can assert on the attributes it receives.
window.dxSTable = function () {};
window.dxSTable.prototype.setRowById = function (ids, sId, icon, attr) {
  this.lastRow = { ids, sId, icon, attr };
  return this.lastRow;
};
const requestCallbacks = {};
window.theRequestManager = {
  map: (cmd) => cmd,
  addRequest: (_table, cmd, callback) => {
    requestCallbacks[cmd] = callback;
    return cmd;
  },
  removeRequest: () => {},
};
window.theWebUI = {
  deltaTime: 0,
  settings: {},
  config: function () {},
  setSettings: jest.fn(), // the core save, wrapped by onLangLoaded
  getTable: () => ({ renameColumnById: () => {}, removeColumnById: () => {} }),
  tables: { trt: { columns: [], format: (_table, arr) => arr } },
};

// The plugin loader normally provides `plugin`; stub just what init.js uses.
const pluginCode = readFileSync("../plugins/seedingtime/init.js", {
  encoding: "utf-8",
});
function loadPlugin() {
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function () { var plugin = { enabled: true, loadLang: function () {}, loadMainCSS: function () {}, " +
    "canChangeColumns: function () { return true; }, canChangeOptions: function () { return true; }, allStuffLoaded: true }; " +
    pluginCode +
    "\nwindow.__plugin = plugin;\n})();";
  document.body.appendChild(scriptEl);
  return window.__plugin;
}

const originalSetRowById = window.dxSTable.prototype.setRowById;
const plugin = loadPlugin();
// What init.js left in the settings model, before any test touches it.
const seededSetting = theWebUI.settings["webui.seedingtime.highlight"];

theWebUI.config(); // registers the columns, the format wrapper and both requests

const seedingtimeCallback = requestCallbacks["d.get_custom=seedingtime"];
const addtimeCallback = requestCallbacks["d.get_custom=addtime"];

// Pinned clock and a torrent added three days before it. The consumers
// (trt/rss/teg columns, mobile details) rely on this exact contract:
// seedingtime is a duration in seconds, addtime is a raw epoch, -1 means
// the custom field is absent.
const NOW = 1787000000000;
const EPOCH = NOW / 1000 - 3 * 86400;
const BIG_DELTA = 36 * 3600 * 1000; // a large browser-vs-server clock skew, ms

describe("seedingtime custom-field requests", () => {
  beforeEach(() => {
    jest.useFakeTimers();
    jest.setSystemTime(NOW);
  });

  afterEach(() => {
    jest.useRealTimers();
    theWebUI.deltaTime = 0;
  });

  it("stores seedingtime as a duration that compensates for clock skew", () => {
    const torrent = {};
    seedingtimeCallback("HASH", torrent, String(EPOCH));
    expect(torrent.seedingtime).toBe(3 * 86400);

    // The duration compares a server timestamp with the browser's "now",
    // so a skewed browser clock must be corrected by deltaTime.
    theWebUI.deltaTime = BIG_DELTA;
    seedingtimeCallback("HASH", torrent, String(EPOCH));
    expect(torrent.seedingtime).toBe(3 * 86400 - BIG_DELTA / 1000);
  });

  it("clamps seedingtime to zero when clock skew places the epoch in the future", () => {
    const torrent = {};
    seedingtimeCallback("HASH", torrent, String(NOW / 1000 + 10));
    expect(torrent.seedingtime).toBe(0);
  });

  it("stores addtime as the raw epoch no matter how skewed the clock is", () => {
    const torrent = {};
    theWebUI.deltaTime = BIG_DELTA;
    addtimeCallback("HASH", torrent, String(EPOCH));
    expect(torrent.addtime).toBe(EPOCH);
  });

  it("flags an absent custom field with -1", () => {
    const torrent = {};
    seedingtimeCallback("HASH", torrent, "");
    addtimeCallback("HASH", torrent, "");
    expect(torrent.seedingtime).toBe(-1);
    expect(torrent.addtime).toBe(-1);
  });

  it("renders the columns as a duration and a calendar date", () => {
    const table = { getIdByCol: (i) => ["seedingtime", "addtime"][i] };
    const rendered = theWebUI.tables.trt.format(table, [3 * 86400, EPOCH]);
    expect(rendered).toEqual([
      theConverter.time(3 * 86400, true),
      theConverter.date(EPOCH),
    ]);
    expect(theWebUI.tables.trt.format(table, [-1, -1])).toEqual(["", ""]);
  });
});

describe("seedingtime finished-duration highlight", () => {
  beforeEach(() => {
    theWebUI.settings[plugin.highlightSetting] = "";
  });

  it("parses a duration with a unit, and a bare number as days", () => {
    expect(plugin.parseDuration("")).toBe(0);
    expect(plugin.parseDuration("nonsense")).toBe(0);
    expect(plugin.parseDuration("45s")).toBe(45);
    expect(plugin.parseDuration("30m")).toBe(30 * 60);
    expect(plugin.parseDuration("12h")).toBe(12 * 3600);
    expect(plugin.parseDuration("1d")).toBe(86400);
    expect(plugin.parseDuration("2w")).toBe(2 * 604800);
    expect(plugin.parseDuration("2W")).toBe(2 * 604800);
    expect(plugin.parseDuration(" 3 d ")).toBe(3 * 86400);
    expect(plugin.parseDuration("3")).toBe(3 * 86400);
  });

  it("highlights a torrent once its finished duration reaches the limit", () => {
    theWebUI.settings[plugin.highlightSetting] = "1d";
    expect(plugin.torrentRowAttr({ seedingtime: 86400 - 1 })).toEqual({
      "data-seedingtime-highlight": "0",
    });
    expect(plugin.torrentRowAttr({ seedingtime: 86400 })).toEqual({
      "data-seedingtime-highlight": "1",
    });
    expect(plugin.torrentRowAttr({ seedingtime: 10 * 86400 })).toEqual({
      "data-seedingtime-highlight": "1",
    });
  });

  it("does not highlight a torrent without a finished duration", () => {
    theWebUI.settings[plugin.highlightSetting] = "1d";
    expect(plugin.torrentRowAttr({ seedingtime: -1 })).toEqual({
      "data-seedingtime-highlight": "0",
    });
    expect(plugin.torrentRowAttr(undefined)).toEqual({
      "data-seedingtime-highlight": "0",
    });
  });

  it("disables the highlight for an empty or unparsable limit", () => {
    expect(plugin.torrentRowAttr({ seedingtime: 365 * 86400 })).toEqual({
      "data-seedingtime-highlight": "0",
    });
    theWebUI.settings[plugin.highlightSetting] = "oops";
    expect(plugin.torrentRowAttr({ seedingtime: 365 * 86400 })).toEqual({
      "data-seedingtime-highlight": "0",
    });
  });

  it("adds the attribute when the torrent list hands the row to the table", () => {
    theWebUI.settings[plugin.highlightSetting] = "2w";
    const table = new dxSTable();
    table.prefix = "trt";
    table.setRowById({ seedingtime: 2 * 604800 }, "HASH", "icon", {});
    expect(table.lastRow.attr).toEqual({ "data-seedingtime-highlight": "1" });
  });

  it("leaves the attributes of a non-torrent table untouched", () => {
    theWebUI.settings[plugin.highlightSetting] = "2w";
    const table = new dxSTable();
    table.prefix = "rss";
    table.setRowById({ seedingtime: 2 * 604800 }, "HASH", "icon", { link: "x" });
    expect(table.lastRow.attr).toEqual({ link: "x" });
  });
});

describe("seedingtime highlight settings key", () => {
  // setSettings only collects a key that is in theWebUI.settings, so without
  // the seed the field would never be saved, nor restored by loadSettings.
  it("seeds an empty limit into the settings model at load", () => {
    expect(seededSetting).toBe("");
  });

  it("keeps a limit the user already saved", () => {
    theWebUI.settings[plugin.highlightSetting] = "3d";
    const installed = dxSTable.prototype.setRowById;
    loadPlugin();
    dxSTable.prototype.setRowById = installed; // undo the second wrap
    expect(theWebUI.settings[plugin.highlightSetting]).toBe("3d");
  });
});

describe("seedingtime applyHighlight", () => {
  it("re-applies the highlight to every torrent row of the trt table", () => {
    const setAttr = jest.fn();
    const getTable = theWebUI.getTable;
    theWebUI.getTable = (prefix) => (prefix === "trt" ? { setAttr } : null);
    theWebUI.torrents = { OLD: { seedingtime: 2 * 86400 }, NEW: { seedingtime: 60 } };
    theWebUI.settings[plugin.highlightSetting] = "1d";
    try {
      plugin.applyHighlight();
    } finally {
      theWebUI.getTable = getTable;
      delete theWebUI.torrents;
    }
    expect(setAttr).toHaveBeenCalledTimes(2);
    expect(setAttr).toHaveBeenCalledWith("OLD", { "data-seedingtime-highlight": "1" });
    expect(setAttr).toHaveBeenCalledWith("NEW", { "data-seedingtime-highlight": "0" });
  });
});

describe("seedingtime settings field", () => {
  const id = "webui.seedingtime.highlight";

  beforeEach(() => {
    $("#st_gl").remove();
    // Register the dialog the way theDialogManager.add() does, without the
    // ResizeObserver jsdom lacks; setHandler/addHandler are the real ones.
    theDialogManager.items.stg = { beforeShow: null, afterShow: null, beforeHide: null, afterHide: null };
    theWebUI.settings[id] = "";
  });

  const makeGeneralPage = () =>
    $("<div>").attr({ id: "st_gl" }).append(
      $("<fieldset>").append($("<div>").addClass("row"))
    ).appendTo(document.body);

  it("puts the label and the input in one cell", () => {
    makeGeneralPage();
    plugin.addHighlightField();
    const input = $(document.getElementById(id));
    expect(input.length).toBe(1);
    const cell = input.parent();
    expect(cell.hasClass("col-md-6")).toBe(true);
    expect(cell.children("label").attr("for")).toBe(id);
    expect($("#st_gl .row").children().length).toBe(1);
  });

  it("waits for the General page rather than leaving the field out", () => {
    jest.useFakeTimers();
    try {
      plugin.addHighlightField();
      expect(document.getElementById(id)).toBeNull();
      makeGeneralPage();
      jest.advanceTimersByTime(1000);
      expect(document.getElementById(id)).not.toBeNull();
    } finally {
      jest.useRealTimers();
    }
  });

  // The theme plugin (runlevel 5) loads before this one (11.4) and registers
  // its own stg/beforeShow handler; replacing it would let a cancelled theme
  // choice be applied on the next OK.
  it("keeps the beforeShow handler another plugin registered", () => {
    const themeHandler = jest.fn();
    theDialogManager.setHandler("stg", "beforeShow", themeHandler);
    makeGeneralPage();
    plugin.addHighlightField();
    theWebUI.settings[id] = "2w";
    document.getElementById(id).value = "cancelled edit";
    theDialogManager.items.stg.beforeShow("stg");
    expect(themeHandler).toHaveBeenCalled();
    expect(document.getElementById(id).value).toBe("2w");
  });
});

describe("seedingtime onRemove", () => {
  const installed = dxSTable.prototype.setRowById;
  beforeEach(() => {
    dxSTable.prototype.setRowById = originalSetRowById;
  });
  afterEach(() => {
    dxSTable.prototype.setRowById = installed;
  });

  it("puts the original dxSTable.prototype.setRowById back", () => {
    const fresh = loadPlugin();
    expect(dxSTable.prototype.setRowById).toBe(fresh.wrappedSetRowById);
    fresh.onRemove();
    expect(dxSTable.prototype.setRowById).toBe(originalSetRowById);
    // A removed plugin must not keep tagging the rows of the torrent list.
    const table = new dxSTable();
    table.prefix = "trt";
    table.setRowById({ seedingtime: 1 }, "HASH", "icon", {});
    expect(table.lastRow.attr).toEqual({});
  });

  it("leaves a later patch of setRowById alone", () => {
    const fresh = loadPlugin();
    const laterPatch = function () {};
    dxSTable.prototype.setRowById = laterPatch;
    fresh.onRemove();
    expect(dxSTable.prototype.setRowById).toBe(laterPatch);
  });
});

