import { readFileSync } from "fs";

window.$ = require("jquery");

// lang/en.js before common.js: common.js references theUILang at load time.
// common.js before stable.js: selectRow reaches for $type() on every call.
for (const src of ["../lang/en.js", "../js/common.js", "../js/stable.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// A bare dxSTable: enough state for selectRow/clearRows to read and write,
// none of the DOM machinery neither one touches.
function makeTable(rowIDs) {
  const rowdata = {};
  const rowSel = {};
  for (const id of rowIDs) {
    rowdata[id] = { enabled: true };
    rowSel[id] = false;
  }
  return Object.assign(Object.create(window.dxSTable.prototype), {
    pendingSync: {},
    syncDOMAsync: jest.fn(),
    rowIDs,
    rowdata,
    rowSel,
    selCount: 0,
    stSel: [],
  });
}

function click(table, id, opts = {}) {
  dxSTable.prototype.selectRow.call(
    table,
    { which: 1, metaKey: false, shiftKey: false, ...opts },
    { id }
  );
}

describe("s-table selection surviving clearRows", () => {
  it("clearRows drops the range anchor along with the rows it pointed into", () => {
    const table = makeTable(["a", "b", "c"]);
    click(table, "b"); // a plain click, so stSel is genuinely set by selectRow
    expect(table.stSel).toEqual(["b"]);

    dxSTable.prototype.clearRows.call(table);

    expect(table.stSel).toEqual([]);
  });

  // The reported defect: a directory is entered (clearRows wipes the old
  // rows), the new rows load, and a Shift-click on a row that is not the
  // last one selects everything from it to the end of the table -- because
  // the anchor left over in stSel names a row from the directory just left,
  // which the walk in selectRow can never reach.
  it("a shift-click right after new rows load selects only the clicked row", () => {
    const table = makeTable(["old-1", "old-2"]);
    click(table, "old-1");
    expect(table.stSel).toEqual(["old-1"]);

    dxSTable.prototype.clearRows.call(table);

    // The next directory's rows -- none of them is "old-1".
    table.rowIDs = ["a", "b", "c", "d"];
    table.rowdata = Object.fromEntries(table.rowIDs.map((id) => [id, { enabled: true }]));
    table.rowSel = Object.fromEntries(table.rowIDs.map((id) => [id, false]));

    click(table, "b", { shiftKey: true });

    expect(table.stSel).toEqual(["b"]);
    expect(table.selCount).toBe(1);
    expect(table.rowSel).toEqual({ a: false, b: true, c: false, d: false });
  });
});
