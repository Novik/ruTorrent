import { readFileSync } from "fs";

window.$ = window.jQuery = require("jquery");

window.theUILang = {
  traf: "Traffic", perDay: "Per day", perMonth: "Per month", perYear: "Per year",
  allTrackers: "All trackers", ClearButton: "Clear", selectedTorrent: "Selected",
  ratioDay: "Ratio/day", ratioWeek: "Ratio/week", ratioMonth: "Ratio/month",
};
window.TYPE_NUMBER = "number";
window.theWebUI = { settings: {}, torrents: {} };
window.theTabs = { onShow: function () {} };
window.thePlugins = { isInstalled: () => false };
window.rTorrentStub = function () {};
window.rGraph = class {};

// The getratios response is a <script> the browser runs: it assigns
// theWebUI.ratiosStat and the callback marks it fresh.
let ratiosCallback = null;

window.theWebUI.request = function (url, cb) {
  expect(url).toBe("?action=getratios");
  ratiosCallback = cb; // [handler, context], as the core request() takes it
};
window.theWebUI.addTorrents = function () {};

const load = (src) => {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
};

load("../js/common.js");   // $type
load("../js/stable.js");   // the real dxSTable, for the row half of the story

// The plugin file as the browser gets it, with the tab half switched off: the
// ratio columns live under canChangeColumns(), which is the part under test.
const code = readFileSync("../plugins/trafic/init.js", { encoding: "utf-8" });
const el = document.createElement("script");
el.textContent =
  "var plugin = { canChangeTabs: () => false, canChangeColumns: () => true," +
  " collectStatForTorrents: true, updateInterval: 15, allStuffLoaded: false," +
  " loadLang: () => {} };" +
  "window.traficPlugin = plugin;" + code;
document.body.appendChild(el);

const plugin = window.traficPlugin;

// A getratios round trip: the response script sets ratiosStat, then the
// callback runs.
function ratiosArrive(stat) {
  window.theWebUI.ratiosStat = stat;
  const [handler, context] = ratiosCallback;
  handler.call(context, stat);
}

const HASH = "613BC75564F589B579D7040CF8E82525E81EF80C";
const SIZE = 1000;
const listResponse = () => ({ torrents: { [HASH]: { size: SIZE, name: "x" } } });

describe("trafic ratio columns", () => {
  beforeEach(() => {
    jest.useFakeTimers();
    window.theWebUI.ratiosStat = {};
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
  });

  it("fills the columns on the update that follows a getratios answer", () => {
    ratiosArrive({ [HASH]: [100, 200, 300] });

    const data = listResponse();
    window.theWebUI.addTorrents(data);

    expect(data.torrents[HASH].ratioday).toBe(0.1);
    expect(data.torrents[HASH].ratioweek).toBe(0.2);
    expect(data.torrents[HASH].ratiomonth).toBe(0.3);
  });

  it("fills them on every later update too", () => {
    ratiosArrive({ [HASH]: [100, 200, 300] });
    window.theWebUI.addTorrents(listResponse());

    // rtorrent.js builds a fresh torrent object per poll, so each update has
    // to be given the ratios again.
    const next = listResponse();
    window.theWebUI.addTorrents(next);

    expect(next.torrents[HASH].ratioday).toBe(0.1);
    expect(next.torrents[HASH].ratioweek).toBe(0.2);
    expect(next.torrents[HASH].ratiomonth).toBe(0.3);
  });

  it("has nothing to add before the first answer arrives", () => {
    const data = listResponse();
    window.theWebUI.addTorrents(data);

    expect("ratioday" in data.torrents[HASH]).toBe(false);
  });
});

describe("what the table does with an update that omits the ratios", () => {
  const IDS = ["name", "ratioday", "ratioweek", "ratiomonth"];

  it("keeps the cells of an existing row as they were", () => {
    const written = [];
    const table = {
      ids: IDS,
      setValue: (row, col, val) => {
        written.push([IDS[col], val]);
        return true;
      },
    };

    window.dxSTable.prototype.setValuesByIds.call(table, "ROW1", { name: "x" });

    expect(written).toEqual([["name", "x"]]);
  });

  it("writes null into a new row, which is a blank cell", () => {
    let cols = null;
    const table = { ids: IDS, addRow: (c) => { cols = c; return true; } };

    window.dxSTable.prototype.addRowById.call(table, { name: "x" }, "ROW1", null, {});

    expect(cols).toEqual(["x", null, null, null]);
  });
});
