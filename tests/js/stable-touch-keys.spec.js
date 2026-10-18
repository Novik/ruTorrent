import { readFileSync } from "fs";

window.$ = require("jquery");

for (const src of ["../lang/en.js", "../js/common.js", "../js/stable.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

window.theDialogManager = { isModalState: () => false };

function makeTable() {
  const t = Object.create(window.dxSTable.prototype);
  return Object.assign(t, {
    rowIDs: ["a", "b"],
    rowdata: { a: { enabled: true }, b: { enabled: true } },
    rowSel: { a: false, b: false },
    selCount: 0,
    stSel: [],
    pendingSync: {},
    syncDOMAsync: () => {},
    refreshSelection: () => {},
    bindKeyCalls: 0,
  });
}

const boundKeyHandlers = () => (($._data(document, "events") || {}).keydown || []).length;

describe("the s-table keyboard shortcuts", () => {
  beforeEach(() => {
    $(document).off("keydown");
    $.support.touchable = false;
    $.support.touchPrimary = false;
  });

  afterEach(() => {
    $(document).off("keydown");
  });

  const select = (t) =>
    window.dxSTable.prototype.selectRow.call(t, { which: 1, metaKey: false, shiftKey: false }, { id: "a" });

  it("are bound for a mouse and keyboard desktop", () => {
    select(makeTable());
    expect(boundKeyHandlers()).toBe(1);
  });

  // A phone or a tablet has nothing to press them with, and the handler sits on
  // the document for as long as the table lives.
  it("are not bound where touch is the primary pointer", () => {
    $.support.touchable = true;
    $.support.touchPrimary = true;
    select(makeTable());
    expect(boundKeyHandlers()).toBe(0);
  });

  // The regression the two flags exist to prevent: a laptop with a touchscreen
  // is touchable and still has the keyboard these shortcuts are for.
  it("are bound on a touchscreen laptop", () => {
    $.support.touchable = true;
    $.support.touchPrimary = false;
    select(makeTable());
    expect(boundKeyHandlers()).toBe(1);
  });

  it("runs the select-all shortcut once they are bound", () => {
    const t = makeTable();
    let filled = 0;
    t.fillSelection = () => { filled++; };
    select(t);

    const e = $.Event("keydown", { which: 65, keyCode: 65, ctrlKey: true });
    $(document).trigger(e);

    expect(filled).toBe(1);
  });

  it("does not run it where touch is the primary pointer", () => {
    $.support.touchable = true;
    $.support.touchPrimary = true;
    const t = makeTable();
    let filled = 0;
    t.fillSelection = () => { filled++; };
    select(t);

    $(document).trigger($.Event("keydown", { which: 65, keyCode: 65, ctrlKey: true }));

    expect(filled).toBe(0);
  });
});
