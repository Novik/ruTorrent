import { readFileSync } from "fs";

window.$ = require("jquery");

for (const src of ["../lang/en.js", "../js/common.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// Minimal scaffolding for plugins/seedingtime/init.js: capture the request
// callbacks it registers instead of running a real request manager.
window.TYPE_NUMBER = "number";
window.thePlugins = { isInstalled: () => false };
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
  getTable: () => ({ renameColumnById: () => {}, removeColumnById: () => {} }),
  tables: { trt: { columns: [], format: (_table, arr) => arr } },
};

{
  // The plugin loader normally provides `plugin`; stub just what init.js uses.
  const code = readFileSync("../plugins/seedingtime/init.js", {
    encoding: "utf-8",
  });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function () { var plugin = { loadLang: function () {}, " +
    "canChangeColumns: function () { return true; }, allStuffLoaded: true }; " +
    code +
    "\n})();";
  document.body.appendChild(scriptEl);
}

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
