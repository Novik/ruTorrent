import { readFileSync } from "fs";

// The extsearch results (#TegList) sit beside the torrent list (#List) in
// #list-table, whose size the layout sets. A pixel size written on #TegList
// outlives the next resize: rotate a phone or widen the window and the
// results keep their old width, leaving blank space beside them.
//
// jsdom lays nothing out, so it cannot show the blank space. What it can show
// is the cause -- a size pinned on #TegList. A browser run is what proves the
// results then follow the layout.

window.$ = window.jQuery = require("jquery");

// Pull the shipped code out of plugins/extsearch/init.js rather than
// restating it, so this spec cannot drift from the code that ships.
function extractFunction(src, needle) {
  const start = src.indexOf(needle);
  if (start < 0) throw new Error("not found in plugins/extsearch/init.js: " + needle);
  let i = src.indexOf("{", start);
  let depth = 0;
  for (; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}" && --depth === 0) break;
  }
  return src.slice(start, i + 1) + ";";
}

const initSrc = readFileSync("../plugins/extsearch/init.js", { encoding: "utf-8" });
const resizeTopSrc = extractFunction(initSrc, "theWebUI.resizeTop = function(");
const switchLabelSrc = extractFunction(initSrc, "catlist.switchLabel = function(");

const tegTable = {
  resize: jest.fn(),
  clearSelection: jest.fn(),
  scrollTo: jest.fn(),
  resizeColumn: jest.fn(),
};
let theWebUI;
let plugin;
let catlist;

beforeEach(() => {
  document.body.innerHTML = `
    <div id="list-table">
      <div id="List"></div>
      <div id="TegList" style="display: none"></div>
    </div>`;
  Object.values(tegTable).forEach((fn) => fn.mockClear());

  theWebUI = {
    configured: true,
    resizeTop: jest.fn(),
    getTable: () => tegTable,
    clearDetails: jest.fn(),
  };
  plugin = {
    enabled: true,
    tegs: { extteg_0: {} },
    reloadData: jest.fn(),
    switchLabel: jest.fn(() => true),
  };
  plugin.resizeTop = theWebUI.resizeTop;
  catlist = {};

  new Function(
    "$", "theWebUI", "plugin", "catlist",
    `${resizeTopSrc}\n${switchLabelSrc}`
  )(window.$, theWebUI, plugin, catlist);
});

const pinnedSize = () => {
  const style = document.getElementById("TegList").style;
  return { width: style.width, height: style.height };
};

describe("extsearch results size", () => {
  it("does not pin a size on the results when they open", () => {
    catlist.switchLabel("psearch", "extteg_0");
    expect(pinnedSize()).toEqual({ width: "", height: "" });
    expect(document.getElementById("TegList").style.display).not.toBe("none");
  });

  it("does not pin a size on the results on a resize", () => {
    theWebUI.resizeTop(null, 300);
    expect(pinnedSize()).toEqual({ width: "", height: "" });
  });

  it("still lets the core lay out, then redraws the result rows", () => {
    theWebUI.resizeTop(null, 300);
    expect(plugin.resizeTop).toHaveBeenCalledWith(null, 300);
    expect(tegTable.resize).toHaveBeenCalledWith(null, 300);
  });

  it("leaves the result rows alone before the interface is configured", () => {
    theWebUI.configured = false;
    theWebUI.resizeTop(null, 300);
    expect(tegTable.resize).not.toHaveBeenCalled();
  });
});
