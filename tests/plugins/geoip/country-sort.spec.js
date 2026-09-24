import { readFileSync } from "fs";

window.$ = require("jquery");
window.jQuery = window.$;

// lang/en.js before common.js/stable.js: they read theUILang at load time.
for (const src of ["../lang/en.js", "../js/common.js", "../js/stable.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// The country names the plugin sorts by live in the plugin's own lang, not the
// main one. Three whose localized-name order is the reverse of their code
// order, so a run that sorted by the raw code and one that sorted by the name
// cannot produce the same sequence:
//   by code:  ch, se, za
//   by name:  za (South Africa), se (Sweden), ch (Switzerland)
theUILang.country = { ch: "Switzerland", se: "Sweden", za: "South Africa" };
theUILang.countryName = "Country";
theUILang.commentName = "Comment";

window.rTorrentStub = { prototype: {} };

// A real dxSTable carrying only what getSortFunc/getSorter read: one string
// column "country" at index 0. renameColumnById is a no-op here (it moves DOM
// the plugin's done() calls but this test does not render).
const prsTable = Object.assign(Object.create(window.dxSTable.prototype), {
  colsdata: [{ type: window.TYPE_STRING }],
  getColNoById: (id) => (id === "country" ? 0 : -1),
  renameColumnById: () => {},
});

window.theWebUI = {
  settings: {},
  config: function () {},
  getTable: (id) => (id === "prs" ? prsTable : {}),
  tables: { prs: { columns: [], format: (_table, arr) => arr } },
};

// Load the plugin the way the loader would, with a stubbed `plugin`. Country
// on, comments and the peer menu off, so only the column path runs.
{
  const code = readFileSync("../plugins/geoip/init.js", { encoding: "utf-8" });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function () { var plugin = { loadLang: function () {}, " +
    "loadMainCSS: function () {}, canChangeColumns: function () { return true; }, " +
    "canChangeMenu: function () { return false; }, retrieveCountry: true, " +
    "retrieveComments: false, enabled: true, allStuffLoaded: true }; " +
    code +
    "\n})();";
  document.body.appendChild(scriptEl);
}

// config() adds the column and runs done(), which installs the sort override.
theWebUI.config();

const codes = { r1: "se", r2: "ch", r3: "za" };
const byCode = ["r2", "r1", "r3"]; // ch, se, za
const byName = ["r3", "r1", "r2"]; // South Africa, Sweden, Switzerland

describe("geoip Country column sorting", () => {
  it("installs the sort override on the peers table", () => {
    expect(typeof prsTable.oldGetSortFunc).toBe("function");
  });

  it("sorts the Country column by localized name, not the raw code", () => {
    const sort = prsTable.getSortFunc("country", 0, (k) => codes[k]);
    expect(Object.keys(codes).sort(sort)).toEqual(byName);
  });

  it("reverses on the same localized name order", () => {
    const sort = prsTable.getSortFunc("country", 1, (k) => codes[k]);
    expect(Object.keys(codes).sort(sort)).toEqual([...byName].reverse());
  });

});
