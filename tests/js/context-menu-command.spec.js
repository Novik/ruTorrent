import { readFileSync } from "fs";

window.$ = require("jquery");

function run(code) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

function load(src) {
  run(readFileSync(src, { encoding: "utf-8" }));
}

// lang/en.js before common.js: common.js reads theUILang at load time.
// common.js before objects.js: the menu builder calls $type() on every entry.
for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js", "../js/stable.js"]) {
  load(src);
}

// What js/webui.js expects to find already defined, reduced to what the two
// rate menus reach.
Object.assign(window, {
  theFormatter: {},
  TYPE_STRING: "string",
  TYPE_NUMBER: "number",
  TYPE_PROGRESS: "progress",
  TYPE_PEERS: "peers",
  TYPE_SEEDS: "seeds",
  ALIGN_RIGHT: "right",
  rSpeedGraph: function () {},
  Timer: function () {},
  dStatus: { started: 1, paused: 2, checking: 4, hashing: 8, error: 16 },
});
window.rSpeedGraph.prototype.addData = () => {};
run(
  readFileSync("../js/webui.js", { encoding: "utf-8" }).replace(
    /\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/,
    ""
  )
);
const theRealWebUI = window.theWebUI;

function entries() {
  return theContextMenu.obj.find("a.menu-cmd");
}

function entryNamed(label) {
  return entries().filter((ndx, a) => $(a).text() === label);
}

beforeEach(() => {
  theContextMenu.init();
  theContextMenu.clear();
});

describe("a menu command that is not a function", () => {
  beforeEach(() => {
    delete window.__ranAsCode;
  });

  it("is not run when the entry is picked", () => {
    theContextMenu.add(["Plain", "window.__ranAsCode = 1"]);
    theContextMenu.add([CMENU_SEL, "Selected", "window.__ranAsCode = 1"]);

    entries().trigger("click");

    expect(window.__ranAsCode).toBeUndefined();
  });

  it("leaves the entry marked the way a command-less entry is marked", () => {
    theContextMenu.add(["Plain", "noop()"]);
    theContextMenu.add([CMENU_SEL, "Selected", "noop()"]);

    expect(entryNamed("Plain").hasClass("dis")).toBe(true);
    expect(entryNamed("Selected").hasClass("dis")).toBe(true);
  });

  it("names the command it would not run", () => {
    const warn = jest.spyOn(console, "warn").mockImplementation(() => {});
    try {
      theContextMenu.add(["Plain", "theWebUI.start()"]);
      expect(warn).toHaveBeenCalledWith(
        expect.stringContaining("theWebUI.start()")
      );
    } finally {
      warn.mockRestore();
    }
  });
});

// Each of these menus is built in a loop, so every entry has to carry the
// value of the iteration it was built in and not the last one.

describe("the search engine menu", () => {
  beforeEach(() => {
    $(document.body).append($("<div>").attr("id", "search"));
  });

  afterEach(() => {
    $("#search").remove();
  });

  it("gives every engine its own entry", () => {
    const chosen = [];
    theSearchEngines.sites = [
      { name: "First" },
      { name: "Second" },
      { name: "Third" },
    ];
    theSearchEngines.current = 1;
    theSearchEngines.set = (ndx) => chosen.push(ndx);

    theSearchEngines.show();
    for (const name of ["First", "Second", "Third"]) {
      entryNamed(name).trigger("click");
    }

    expect(chosen).toEqual([0, 1, 2]);
  });
});

describe("the column menu of a table", () => {
  afterEach(() => {
    window.theWebUI = theRealWebUI;
  });

  it("gives every column its own entry", () => {
    const toggled = [];
    window.theWebUI = {
      getTable: (prefix) => ({
        toggleColumn: (col) => toggled.push([prefix, col]),
      }),
    };
    const table = Object.assign(Object.create(window.dxSTable.prototype), {
      isMoving: false,
      prefix: "trt",
      colOrder: [1, 1, 1],
      colsdata: [
        { text: "Name", enabled: true },
        { text: "Size", enabled: false },
        { text: "Done", enabled: false },
      ],
    });

    table.onRightClick({ which: 3, clientX: 0, clientY: 0 });
    for (const name of ["Name", "Size", "Done"]) {
      entryNamed(name).trigger("click");
    }

    expect(toggled).toEqual([
      ["trt", 0],
      ["trt", 1],
      ["trt", 2],
    ]);
  });
});

describe("the download rate menu", () => {
  it("gives every listed speed its own entry", () => {
    const asked = [];
    theRealWebUI.settings = { "webui.speedlistdl": "50,100,200" };
    theRealWebUI.total = { rateDL: 100 * 1024 };
    theRealWebUI.setDLRate = (rate) => asked.push(rate);

    theRealWebUI.downRateMenu({ which: 3, clientX: 0, clientY: 0 });
    for (const speed of [50, 100, 200]) {
      entryNamed(theConverter.speed(speed * 1024)).trigger("click");
    }

    expect(asked).toEqual([50 * 1024, 100 * 1024, 200 * 1024]);
  });
});
