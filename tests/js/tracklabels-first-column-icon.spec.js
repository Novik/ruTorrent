import { readFileSync } from "fs";

window.$ = require("jquery");

// The real stable.js and the real tracklabels plugin: what is under test is
// the DOM of a row once the plugin has written a tracker into it.
window.TYPE_STRING = "string";
window.TYPE_NUMBER = "number";
window.TYPE_PROGRESS = "progress";
window.TYPE_STRING_LABEL = "label";
window.theUILang = { Tracker: "Tracker" };
window.theRequestManager = { addRequest: () => 0 };
window.thePlugins = { isInstalled: () => false };
window.plugin = {
  loadLang() {},
  canChangeColumns: () => true,
  canChangeMenu: () => true,
  allStuffLoaded: true,
  enabled: true,
};
const tables = {};
window.theWebUI = {
  resource: {},
  torrents: {},
  trackers: {},
  tables: { trt: { columns: [] } },
  config() {},
  addTrackers() {},
  getTable: (id) => tables[id],
  categoryList: {
    refreshPanel: { plabel: () => [] },
    contextMenuEntries: () => [],
    rescan() {},
    refreshAndSyncPanel() {},
  },
};

for (const src of ["../lang/en.js", "../js/common.js", "../js/stable.js", "../plugins/tracklabels/init.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

const HASH = "E47249CE09FEE3AD99A38C6AEC5666E2FF134C64";
const COLUMNS = [
  { text: "Name", width: 200, id: "name", type: window.TYPE_STRING, enabled: true },
  { text: "Ratio", width: 60, id: "ratio", type: window.TYPE_STRING, enabled: true },
  { text: "Tracker", width: 100, id: "tracker", type: window.TYPE_STRING, enabled: true },
];

// A real dxSTable minus create(): the getters it reads (cols, tBody, ...) are
// served by this markup. Every sync is run by hand, so the throttled
// syncDOMAsync is a no-op.
function torrentTable(firstId) {
  const dCont = window.$(
    "<div id='trt' class='stable'>" +
      "<table><colgroup></colgroup><thead><tr></tr></thead><tbody></tbody></table>" +
    "</div>"
  );
  const ids = COLUMNS.map((c) => c.id);
  const first = ids.indexOf(firstId);
  const colOrder = [first, ...ids.map((_, i) => i).filter((i) => i !== first)];
  for (let i = 0; i < COLUMNS.length; i++) {
    dCont.find("colgroup").append(window.$("<col>"));
    dCont.find("thead tr").append(window.$("<td>").css("text-align", "left"));
  }
  document.body.replaceChildren(dCont[0]);

  const table = Object.create(window.dxSTable.prototype);
  table.created = true;
  table.dCont = dCont;
  table.colOrder = colOrder;
  table.colsdata = colOrder.map((i) => COLUMNS[i]);
  table.ids = ids;
  table.pendingSync = {};
  table.syncDOMAsync = () => {};
  table.format = (_, arr) => arr;
  table.noSort = true;
  const data = ["Some.Release.2026", "0.000", ""];
  table.rowdata = {
    [HASH]: { data, icon: "Status_Up", attr: {}, enabled: true, fmtdata: data.slice() },
  };
  const tr = table.createRow(data, HASH, "Status_Up", {});
  table.dCont.find("tbody").append(tr);
  tables.trt = table;
  return { table, tr };
}

const firstCell = (tr) => ({
  icons: [...tr.cells[0].querySelectorAll(".stable-icon")].map((el) => el.className),
  text: tr.cells[0].textContent,
});

describe("status icon in the first column", () => {
  beforeEach(() => {
    window.theWebUI.torrents = { [HASH]: {} };
    window.theWebUI.trackers = { [HASH]: [{ group: 0, name: "http://tracker.example.org:2710/announce" }] };
  });

  it("survives tracklabels filling in the Tracker column when Tracker is first", () => {
    const { table, tr } = torrentTable("tracker");
    expect(firstCell(tr).icons).toEqual(["stable-icon Status_Up"]);

    window.theWebUI.rebuildTrackersLabels();
    table.syncDOM();

    expect(firstCell(tr)).toEqual({ icons: ["stable-icon Status_Up"], text: "example.org" });
  });

  it("survives an in-place update of whichever column is first", () => {
    const { table, tr } = torrentTable("ratio");
    table.setValue(HASH, table.getColById("ratio"), "1.250");
    table.syncDOM();
    expect(firstCell(tr)).toEqual({ icons: ["stable-icon Status_Up"], text: "1.250" });

    // A later state change still swaps the icon, and only that one.
    table.setIcon(HASH, "Status_Down");
    table.setValue(HASH, table.getColById("ratio"), "1.500");
    table.syncDOM();
    expect(firstCell(tr)).toEqual({ icons: ["stable-icon Status_Down"], text: "1.500" });
  });

  it("puts no icon into a cell that is not first", () => {
    const { table, tr } = torrentTable("name");
    window.theWebUI.rebuildTrackersLabels();
    table.syncDOM();

    expect(firstCell(tr)).toEqual({ icons: ["stable-icon Status_Up"], text: "Some.Release.2026" });
    const trackerCell = tr.cells[table.getColOrder(table.getColById("tracker"))];
    expect(trackerCell.querySelectorAll(".stable-icon").length).toBe(0);
    expect(trackerCell.textContent).toBe("example.org");
  });
});
