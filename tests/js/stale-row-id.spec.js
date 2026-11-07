import { readFileSync } from "fs";

// A row id outlives the listing it came from: clearRows() drops the data at
// once and the rows leave the DOM only when the deferred sync runs, and a
// selection can survive a directory change. Both layers have to answer for an
// id they no longer know instead of throwing.
window.$ = require("jquery");
window.TYPE_STRING = "string";
window.TYPE_NUMBER = "number";
window.theWebUI = { resource: {} };

for (const src of ["../lang/en.js", "../js/common.js", "../js/stable.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

describe("an event on a row the table has dropped", () => {
  let table;
  let reached;

  beforeEach(() => {
    document.body.innerHTML =
      "<div id='fls' class='stable'><table><tbody>" +
        "<tr id='dropped'><td>a file</td></tr>" +
        "<tr id='current'><td>another file</td></tr>" +
      "</tbody></table></div>";
    reached = [];
    table = Object.create(window.dxSTable.prototype);
    table.rowdata = { current: { data: [] } };
    table.ondblclick = (row) => reached.push(["dblclick", row && row.id]);
    table.selectRow = (e, row) => reached.push(["select", row && row.id]);
  });

  const click = (type, id) =>
    window.dxSTable.prototype.handleClick.call(table, {
      type: type,
      which: 1,
      target: document.querySelector("#" + id + " td"),
    });

  it("is not handed to the selection handler", () => {
    click("mousedown", "dropped");
    expect(reached).toEqual([]);
  });

  it("is not handed to the double-click handler", () => {
    click("dblclick", "dropped");
    expect(reached).toEqual([]);
  });

  it("still delivers an event on a row the table holds", () => {
    click("mousedown", "current");
    click("dblclick", "current");
    expect(reached).toEqual([["select", "current"], ["dblclick", "current"]]);
  });
});

describe("a directory asked about an id it does not hold", () => {
  let dir;

  beforeEach(() => {
    dir = new window.rDirectory();
    dir.dirs[""]["known"] = { data: { name: "a file" }, link: null };
  });

  it("reports no entry rather than throwing", () => {
    expect(() => dir.getEntry("gone")).not.toThrow();
    expect(dir.getEntry("gone")).toBeNull();
  });

  it("reports that it is not a directory rather than throwing", () => {
    expect(() => dir.isDirectory("gone")).not.toThrow();
    expect(dir.isDirectory("gone")).toBe(false);
  });

  it("still answers for what it holds", () => {
    expect(dir.getEntry("known")).toEqual({ name: "a file" });
    expect(dir.isDirectory("known")).toBe(false);
  });
});
