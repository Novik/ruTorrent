const path = require("path");
const { scan, sourceFiles } = require("./context-menu-scan");

// Every entry the tree hands to theContextMenu is read by the scanner in
// context-menu-scan.js, which accepts a command only when the source proves
// it is a function or nothing at all, and reports every other expression --
// a string, a concatenation, a value it cannot resolve. Grep does not find
// these: an entry can sit in a nested CMENU_CHILD list, in an array a loop
// pushes onto, or in the return value of a category list method. The
// scanner's own tests are in context-menu-scan.spec.js.

const TREE = path.resolve(__dirname, "..", "..");

describe("every context menu entry in the tree", () => {
  const sources = sourceFiles(TREE);
  const result = scan(sources);

  it("is read from more than a handful of files", () => {
    // Guards against the walk quietly finding nothing and passing.
    expect(sources.length).toBeGreaterThan(50);
  });

  it("is read from every call that hands the menu an entry", () => {
    // The dispatcher is found once, in js/objects.js. The calls it makes on
    // itself are the only ones not read, and there are two of them, both in
    // its own add: an exemption that grew would leave entries unchecked. The
    // sink count is a floor: an edit that stops the scan seeing the calls
    // fails here rather than passing above.
    expect(result.dispatchers).toBe(1);
    expect(result.recursion).toBe(2);
    expect(result.sinks).toBeGreaterThan(100);
    // The functions whose return value builds a panel label's menu.
    expect(result.producers).toEqual(["contextMenuEntries", "createRSSMenuPrim"]);
  });

  it("carries a function, or nothing, as its command; anything else is reported", () => {
    expect(result.findings).toEqual([]);
  });
});
