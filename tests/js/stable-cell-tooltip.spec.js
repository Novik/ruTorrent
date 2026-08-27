import { readFileSync } from "fs";

window.$ = require("jquery");

// stable.js is the real thing here: the point of this file is the cell DOM it
// builds, so a stub would test nothing. Only the module-level constants it
// expects from webui.js are supplied.
window.TYPE_STRING = "string";
window.TYPE_NUMBER = "number";
window.TYPE_PROGRESS = "progress";
window.TYPE_STRING_LABEL = "label";
window.theWebUI = { resource: {} };

// common.js first: stable.js reaches for its $$() helper when it refreshes a
// row in place.
for (const src of ["../lang/en.js", "../js/common.js", "../js/stable.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// A real dxSTable, minus create(). Most of what the widget reads is a GETTER
// over the DOM under dCont (cols, tHeadCols, tBodyCols, tBody are all defined
// that way at the bottom of stable.js), so the harness builds that markup
// rather than stubbing the getters -- which is also what keeps this test
// honest: createRow and syncDOM run as themselves.
function fakeTable(columns, fmtdata) {
  const dCont = window.$(
    "<div id='trt' class='stable'>" +
      "<table><colgroup></colgroup><thead><tr></tr></thead><tbody></tbody></table>" +
    "</div>"
  );
  for (const _ of columns) {
    dCont.find("colgroup").append(window.$("<col>"));
    dCont.find("thead tr").append(window.$("<td>").css("text-align", "left"));
  }
  document.body.appendChild(dCont[0]);

  const table = Object.create(window.dxSTable.prototype);
  table.created = true;
  table.dCont = dCont;
  table.colOrder = columns.map((_, i) => i);
  table.colsdata = columns;
  table.ids = columns.map((c) => c.id);
  table.rowdata = { ROW1: { fmtdata } };
  table.createIcon = () => window.$("<span>");
  return table;
}

// The message column is 200px in a table-layout:fixed, white-space:nowrap,
// overflow:hidden table (css/stable.css), so anything longer is cut off at the
// edge with no ellipsis and no other sign that there is more. rTorrent's
// d.message is routinely longer than that, because it JOINS the message of
// every failing tracker row into one string with ' /// '.
const LONG = "Tracker: [Could not connect to server /// Could not resolve hostname]";

describe("dxSTable cell tooltips", () => {
  it("gives a column that asks for it the untruncated value as the cell tooltip", () => {
    const columns = [
      { text: "Name", width: 200, id: "name", type: window.TYPE_STRING, enabled: true },
      { text: "Tracker Status", width: 200, id: "msg", type: window.TYPE_STRING, enabled: true, titled: true },
    ];
    const table = fakeTable(columns, { 0: "Some.Release.2026", 1: LONG });

    const tr = window.dxSTable.prototype.createRow.call(table, ["Some.Release.2026", LONG], "ROW1", null);

    expect(tr.cells[1].getAttribute("title")).toBe(LONG);
    // The row's own tooltip carries the torrent name; a column that did not
    // ask for a tooltip must not shadow it with one of its own.
    expect(tr.cells[0].hasAttribute("title")).toBe(false);
    expect(tr.getAttribute("title")).toBe("Some.Release.2026");
  });

  it("does not put an empty tooltip on a cell with nothing in it", () => {
    // title="" on the cell would suppress the row tooltip for that cell while
    // showing nothing in its place, which is worse than no tooltip at all.
    const columns = [
      { text: "Name", width: 200, id: "name", type: window.TYPE_STRING, enabled: true },
      { text: "Tracker Status", width: 200, id: "msg", type: window.TYPE_STRING, enabled: true, titled: true },
    ];
    const table = fakeTable(columns, { 0: "Some.Release.2026", 1: "" });

    const tr = window.dxSTable.prototype.createRow.call(table, ["Some.Release.2026", ""], "ROW1", null);

    expect(tr.cells[1].hasAttribute("title")).toBe(false);
  });

  it("keeps the tooltip current when the row is updated in place", () => {
    // Rows are built once and refreshed from syncDOM on every poll, so a
    // tooltip written only by createRow would freeze at whatever the message
    // was when the torrent first appeared in the list.
    const columns = [
      { text: "Name", width: 200, id: "name", type: window.TYPE_STRING, enabled: true },
      { text: "Tracker Status", width: 200, id: "msg", type: window.TYPE_STRING, enabled: true, titled: true },
    ];
    const table = fakeTable(columns, { 0: "Some.Release.2026", 1: "Tracker: [Timed out]" });
    const tr = window.dxSTable.prototype.createRow.call(table, ["Some.Release.2026", "Tracker: [Timed out]"], "ROW1", null);
    table.dCont.find("tbody").append(tr);
    expect(tr.cells[1].getAttribute("title")).toBe("Tracker: [Timed out]");

    table.rowdata.ROW1.fmtdata[1] = LONG;
    table.rowdata.ROW1.data = { 1: LONG };
    table.pendingSync = { dirty: { ROW1: { col: { 1: true } } } };
    window.dxSTable.prototype.syncDOM.call(table);
    expect(tr.cells[1].getAttribute("title")).toBe(LONG);

    // And it is taken away again when the message clears, rather than being
    // left behind as a tooltip for a state the torrent is no longer in.
    table.rowdata.ROW1.fmtdata[1] = "";
    table.pendingSync = { dirty: { ROW1: { col: { 1: true } } } };
    window.dxSTable.prototype.syncDOM.call(table);
    expect(tr.cells[1].hasAttribute("title")).toBe(false);
  });
});
