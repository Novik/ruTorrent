import { readFileSync } from "fs";

window.$ = require("jquery");

// create() builds the real header cells and runs init(), so it needs the
// column type constants webui.js defines at module level.
window.TYPE_STRING = "string";
window.TYPE_NUMBER = "number";
window.TYPE_PROGRESS = "progress";
window.theWebUI = { resource: {} };

// objects.js for DnD, which create() attaches to the header row.
for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js", "../js/stable.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

const COLUMNS = [
  { text: "Name", width: 200, id: "name", type: window.TYPE_STRING, enabled: true },
  { text: "Size", width: 100, id: "size", type: window.TYPE_NUMBER, enabled: true },
];

// A table built by create() itself: the header cells carrying the handler
// under test exist only because create() made them.
function createdTable() {
  const host = $("<div>");
  $(document.body).append(host);
  const table = new window.dxSTable();
  table.bindKeys = jest.fn();
  table.create(host[0], COLUMNS.map((column) => Object.assign({}, column)), "touch");
  return table;
}

// assignEvents() reads dCont and the header row, and binds to dCont; the
// markup is what create() leaves behind.
function tableWithEvents() {
  const cont = $(
    '<div class="stable"><div class="stable-body"><table>' +
      "<colgroup><col></colgroup><thead><tr><td></td></tr></thead><tbody></tbody>" +
      "</table></div></div>"
  );
  $(document.body).append(cont);
  const table = Object.assign(Object.create(window.dxSTable.prototype), {
    dCont: cont,
    bindKeys: jest.fn(),
  });
  window.dxSTable.prototype.assignEvents.call(table);
  return table;
}

// triggerHandler rather than trigger: a mousedown on a header cell bubbles to
// the container, and create() and assignEvents() each bind one there, so a
// bubbling event cannot say which of the two answered it.
const press = (element) => element.triggerHandler($.Event("mousedown", { which: 1 }));

describe("the s-table keyboard shortcuts bound on a mouse press", () => {
  beforeEach(() => {
    document.querySelectorAll(".stable").forEach((node) => node.remove());
    $.support.touchable = false;
    $.support.touchPrimary = false;
  });

  describe("on a header cell, bound by create()", () => {
    it("are bound for a mouse and keyboard desktop", () => {
      const table = createdTable();
      press(table.tHeadRow.find("td").first());
      expect(table.bindKeys.mock.calls.length).toBe(1);
    });

    // The reason the two capability flags are separate: a laptop with a
    // touchscreen is touchable and still has the keyboard these are for.
    it("are bound on a touchscreen laptop", () => {
      $.support.touchable = true;
      const table = createdTable();
      press(table.tHeadRow.find("td").first());
      expect(table.bindKeys.mock.calls.length).toBe(1);
    });

    it("are not bound where touch is the primary pointer", () => {
      $.support.touchable = true;
      $.support.touchPrimary = true;
      const table = createdTable();
      press(table.tHeadRow.find("td").first());
      expect(table.bindKeys.mock.calls.length).toBe(0);
    });
  });

  describe("on the table container, bound by assignEvents()", () => {
    it("are bound for a mouse and keyboard desktop", () => {
      const table = tableWithEvents();
      press(table.dCont);
      expect(table.bindKeys.mock.calls.length).toBe(1);
    });

    it("are bound on a touchscreen laptop", () => {
      $.support.touchable = true;
      const table = tableWithEvents();
      press(table.dCont);
      expect(table.bindKeys.mock.calls.length).toBe(1);
    });

    it("are not bound where touch is the primary pointer", () => {
      $.support.touchable = true;
      $.support.touchPrimary = true;
      const table = tableWithEvents();
      press(table.dCont);
      expect(table.bindKeys.mock.calls.length).toBe(0);
    });
  });
});
