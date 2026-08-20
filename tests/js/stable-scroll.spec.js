import { readFileSync } from "fs";

window.$ = require("jquery");

function loadStable() {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync("../js/stable.js", { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// dBody, tBody and TR_HEIGHT are getters over dCont, so the table needs a real
// container; everything else is stripped to what the scroll handler and syncDOM
// touch.
function makeTable(props) {
  const cont = $(
    '<div class="stable">' +
      '<div class="stable-body"><table>' +
        "<colgroup><col></colgroup>" +
        "<thead><tr><td></td></tr></thead>" +
        '<tbody class="stable-virtpad"><tr></tr></tbody>' +
        "<tbody><tr><td></td></tr></tbody>" +
        '<tbody class="stable-virtpad"><tr></tr></tbody>' +
      "</table></div>" +
      '<div class="stable-scrollpos"></div>' +
    "</div>"
  );
  $(document.body).append(cont);
  return Object.assign(Object.create(window.dxSTable.prototype), {
    created: true,
    dCont: cont,
    pendingSync: {},
    viewRows: 500,
    noDelayingDraw: 0,
    getMaxRows: () => 20,
    getColById: () => -1,
    syncDOMAsync: jest.fn(),
    refreshRows: jest.fn(),
    refreshSelection: jest.fn(),
    noSort: true,
    sortId: "",
    sortId2: "",
    rowdata: {},
    ...props,
  });
}

describe("s-table scrolling", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    loadStable();
  });

  it("delays drawing once a scroll passes a few rows", () => {
    const table = makeTable();
    dxSTable.prototype.assignEvents.call(table);
    expect(table.isScrolling).toBe(false);

    table.dBody.scrollTop = table.TR_HEIGHT * 10;
    $(table.dBody).trigger("scroll");

    expect(table.isScrolling).toBe(true);
  });

  it("keeps drawing immediately when the option is on", () => {
    const table = makeTable({ noDelayingDraw: 1 });
    dxSTable.prototype.assignEvents.call(table);

    table.dBody.scrollTop = table.TR_HEIGHT * 10;
    $(table.dBody).trigger("scroll");

    expect(table.isScrolling).toBe(false);
  });

  it("leaves rows added or removed mid-scroll queued for the scroll to end", () => {
    const table = makeTable({ isScrolling: true, pendingSync: { prows: { A: 0 } } });

    dxSTable.prototype.syncDOM.call(table);

    expect(table.refreshRows).not.toHaveBeenCalled();
    expect(table.pendingSync.prows).toEqual({ A: 0 });
  });

  it("draws those rows as soon as the scroll ends", () => {
    const table = makeTable({ isScrolling: true, pendingSync: { prows: { A: 0 } } });
    dxSTable.prototype.syncDOM.call(table);

    table.isScrolling = false;
    dxSTable.prototype.syncDOM.call(table);

    expect(table.refreshRows).toHaveBeenCalled();
  });
});
