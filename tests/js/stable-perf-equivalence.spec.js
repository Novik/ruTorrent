import { readFileSync } from "fs";
import { PanelLabelSelection, CategoryListStatistic } from "../../js/panel";

window.$ = require("jquery");
window.theWebUI = { resource: {} };

// lang/en.js before common.js, common.js before stable.js (same as the other
// stable specs).
for (const src of ["../lang/en.js", "../js/common.js", "../js/stable.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

// These tests pin that the faster code paths give exactly the answers the
// straightforward ones did: the same row order, the same changed/unchanged
// result, the same visible window, the same selection.

function bareTable(type) {
  const t = Object.create(window.dxSTable.prototype);
  Object.defineProperty(t, "tHeadCols", {
    value: [0, 1].map((i) => {
      const e = document.createElement("td");
      e.setAttribute("index", i);
      return e;
    }),
  });
  Object.defineProperty(t, "cols", { value: 2 });
  Object.assign(t, {
    created: true,
    ids: ["x", "name"],
    colOrder: [0, 1],
    colsdata: [{ type }, { type: window.TYPE_STRING_LABEL }],
    currIndex: -1,
    sortId: "x",
    sortId2: "name",
    reverse: 0,
    secRev: 0,
    rowdata: {},
    rowIDs: [],
    refreshRows: () => {},
  });
  return t;
}

// Deterministic pseudo random numbers, so a failure is reproducible.
function rng(seed) {
  return () => (seed = (seed * 1664525 + 1013904223) % 4294967296) / 4294967296;
}

const names = [
  "The.Matrix_1999", "[grp] the-thing", "{x} Alpha", "(y) alpha", "alpha",
  "Alpha", "ÄPFEL", "apfel", "Zebra", "", null, "10 items", "9 items", "the", "The ",
];

const makeValue = {
  [window.TYPE_STRING]: (r) => names[Math.floor(r() * names.length)],
  [window.TYPE_STRING_LABEL]: (r) => names[Math.floor(r() * names.length)],
  [window.TYPE_NUMBER]: (r) => [0, "0", "3.5", 7, "12abc", "", null, "-4", "1e2"][Math.floor(r() * 9)],
  [window.TYPE_PROGRESS]: (r) => String(Math.floor(r() * 5) * 250),
  [window.TYPE_PEERS]: (r) => `${Math.floor(r() * 4)} (${Math.floor(r() * 4)})`,
  [window.TYPE_SEEDS]: (r) => `${Math.floor(r() * 4)} (${Math.floor(r() * 4)})`,
};

describe("sort with precomputed keys", () => {
  for (const [typeName, type] of Object.entries({
    STRING: window.TYPE_STRING,
    LABEL: window.TYPE_STRING_LABEL,
    NUMBER: window.TYPE_NUMBER,
    PROGRESS: window.TYPE_PROGRESS,
    PEERS: window.TYPE_PEERS,
    SEEDS: window.TYPE_SEEDS,
  })) {
    for (const reverse of [0, 1]) {
      it(`orders ${typeName} columns like the per-comparison sorters (reverse=${reverse})`, () => {
        const r = rng(42 + type);
        const t = bareTable(type);
        for (let i = 0; i < 300; i++) {
          const id = "H" + String((i * 7919) % 1009).padStart(4, "0");
          t.rowdata[id] = { data: [makeValue[type](r), names[Math.floor(r() * names.length)]], enabled: true };
          t.rowIDs.push(id);
        }
        t.reverse = reverse;
        t.secRev = reverse ? 0 : 1;
        const expected = t.rowIDs.slice();
        // the original comparator chain, built from the unchanged getSortFunc
        const pv = Object.fromEntries(Object.entries(t.rowdata).map(([k, v]) => [k, v.data[0]]));
        const sv = Object.fromEntries(Object.entries(t.rowdata).map(([k, v]) => [k, v.data[1]]));
        const p = t.getSortFunc("x", t.reverse, (x) => pv[x]);
        const s = t.getSortFunc("name", t.secRev, (x) => sv[x]);
        expected.sort((x, y) => p(x, y) || s(x, y) || window.theSort.Default(x, y));

        t.Sort(null);
        expect(t.rowIDs).toEqual(expected);
      });
    }
  }

  it("leaves the order alone for a column with no sorter", () => {
    const t = bareTable(99);
    for (const id of ["b", "a", "c"]) {
      t.rowdata[id] = { data: [1, "same"], enabled: true };
      t.rowIDs.push(id);
    }
    t.sortId2 = "x";
    t.Sort(null);
    expect(t.rowIDs).toEqual(["a", "b", "c"]);
  });
});

describe("setValuesByIds", () => {
  function table() {
    const t = Object.create(window.dxSTable.prototype);
    Object.defineProperty(t, "cols", { value: 3 });
    Object.assign(t, {
      ids: ["a", "b", "c"],
      format: (_, r) => r,
      rowdata: {},
      rowSel: {},
      rowIDs: [],
      viewRows: 0,
      markViewRowsChange: () => {},
      markRowDirty: jest.fn(),
    });
    t.addRowById({ a: 1, b: 2, c: 3 }, "h", null, {});
    return t;
  }

  it("reports no change for identical values and does not mark the row", () => {
    const t = table();
    expect(t.setValuesByIds("h", { a: 1, b: 2, c: 3 })).toBe(false);
    expect(t.markRowDirty).not.toHaveBeenCalled();
  });

  it("applies every changed column even when the first one differs", () => {
    const t = table();
    expect(t.setValuesByIds("h", { a: 9, b: 8, c: 3 })).toBe(true);
    expect(t.rowdata.h.data).toEqual([9, 8, 3]);
    expect(t.markRowDirty).toHaveBeenCalledTimes(2);
  });

  it("ignores columns the object does not carry", () => {
    const t = table();
    expect(t.setValuesByIds("h", { b: 5 })).toBe(true);
    expect(t.rowdata.h.data).toEqual([1, 5, 3]);
  });
});

describe("refreshRows window", () => {
  function table(enabledFn, count) {
    const t = Object.create(window.dxSTable.prototype);
    const body = { scrollTop: 0, getBoundingClientRect: () => ({ height: 200 }) };
    const drawn = [];
    Object.defineProperty(t, "dBody", { value: body });
    Object.defineProperty(t, "TR_HEIGHT", { value: 20 });
    Object.defineProperty(t, "tpad", { value: { height: () => {} } });
    Object.defineProperty(t, "bpad", { value: { height: () => {} } });
    Object.defineProperty(t, "tBody", { value: [{ replaceChildren: (...rows) => drawn.splice(0, drawn.length, ...rows) }] });
    Object.assign(t, {
      created: true, isScrolling: false, noDelayingDraw: 0, rowdata: {}, rowIDs: [],
      createRow: (_c, id) => ({ id }), refreshSelection: () => {}, resizeColumn: () => {},
    });
    let enabled = 0;
    for (let i = 0; i < count; i++) {
      const on = enabledFn(i);
      t.rowdata["R" + i] = { data: [], enabled: on };
      t.rowIDs.push("R" + i);
      enabled += on ? 1 : 0;
    }
    t.viewRows = enabled;
    return { t, body, drawn };
  }

  it("draws the same rows as filtering the enabled list by index", () => {
    const { t, body, drawn } = table((i) => i % 3 !== 0, 400);
    for (const top of [0, 37, 400, 1900, 5000, 9999]) {
      body.scrollTop = top;
      t.mni = t.mxi = -1;
      t.refreshRows();
      const enabledIds = t.rowIDs.filter((id) => t.rowdata[id].enabled);
      const expected = enabledIds.filter((_, idx) => idx >= t.mni && idx <= t.mxi);
      expect(drawn.map((r) => r.id)).toEqual(expected);
    }
  });

  it("draws nothing for an empty list", () => {
    const { t, drawn } = table(() => false, 10);
    t.refreshRows();
    expect(drawn).toEqual([]);
  });
});

describe("selectedFn", () => {
  const panelIds = ["pstate", "plabel"];
  const stat = CategoryListStatistic.from("pview", [], {
    pstate: [(h) => (h < "h5" ? ["s_a"] : ["s_b"]), {}],
    plabel: [(h) => (h < "h3" ? ["l_x"] : []), {}],
  });
  const scanned = stat.empty();
  for (let i = 0; i < 8; i++) scanned.scan("h" + i, { size: 1, ul: 0, dl: 0 });

  it("matches selected() for every hash and selection", () => {
    for (const cfg of [{}, { pstate: ["s_a"] }, { plabel: ["l_x"] }, { pstate: ["s_b"], plabel: ["l_x"] }, { pstate: ["s_a", "s_b"] }]) {
      const sel = PanelLabelSelection.fromConfig(cfg, panelIds.concat(["pview"]));
      const fn = scanned.selectedFn(sel);
      for (let i = 0; i < 9; i++)
        expect(fn("h" + i)).toBe(scanned.selected("h" + i, sel));
    }
  });
});

describe("createRow", () => {
  // Expected markup was captured from the jQuery-built rows before the cells
  // were switched to the DOM API; the nodes have to stay identical.
  const expected = ["<tr id=\"h\" title=\"Name\"><td class=\"stable-trt-col-0\" style=\"text-align: left;\"><div><span class=\"stable-icon Status_Up\"></span>Name <code>x</code></div></td><td class=\"stable-trt-col-1\" style=\"display: none; text-align: right;\"><div>5 MB</div></td><td class=\"stable-trt-col-2\" rawvalue=\"500\" style=\"text-align: center;\"><span class=\"meter-text\" style=\"overflow: visible;\">50%</span><div class=\"meter-value\" style=\"width: 50%; visibility: visible;\"></div></td><td class=\"stable-trt-col-3\" title=\"msg\" style=\"text-align: left;\"><div>msg</div></td><td class=\"stable-trt-col-4\" style=\"text-align: left;\"><div>c</div></td></tr>", "<tr id=\"h\" title=\"\" class=\"k\"><td class=\"stable-trt-col-0\" style=\"text-align: left;\"><div> </div></td><td class=\"stable-trt-col-1\" style=\"display: none; text-align: right;\"><div> </div></td><td class=\"stable-trt-col-2\" rawvalue=\"\" style=\"text-align: center;\"><span class=\"meter-text\" style=\"overflow: visible;\"></span><div class=\"meter-value\" style=\"width: 50%; visibility: visible;\"></div></td><td class=\"stable-trt-col-3\" style=\"text-align: left;\"><div> </div></td><td class=\"stable-trt-col-4\" style=\"text-align: left;\"><div> </div></td></tr>", "<tr id=\"h\" title=\"a\"><td class=\"stable-trt-col-0\" style=\"text-align: left;\"><div><span class=\"stable-icon\" style=\"background-image: url(&quot;a.png&quot;); background-size: contain;\"></span>a<b></b></div></td><td class=\"stable-trt-col-1\" style=\"display: none; text-align: right;\"><div>7</div></td><td class=\"stable-trt-col-2\" rawvalue=\"0\" style=\"text-align: center;\"><span class=\"meter-text\" style=\"overflow: visible;\">0%</span><div class=\"meter-value\" style=\"width: 50%; visibility: visible;\"></div></td><td class=\"stable-trt-col-3\" title=\"t&amp;&quot;q\" style=\"text-align: left;\"><div>t&amp;\"q</div></td><td class=\"stable-trt-col-4\" style=\"text-align: left;\"><div>z</div></td></tr>"];

  function table() {
    document.body.innerHTML = '<div id="trt"></div>';
    const t = Object.create(window.dxSTable.prototype);
    Object.defineProperty(t, "dCont", { value: $("#trt") });
    Object.defineProperty(t, "cols", { value: 5 });
    Object.defineProperty(t, "tBodyCols", { value: Array.from({ length: 5 }, () => ({})) });
    Object.defineProperty(t, "tHeadCols", {
      value: ["left", "right", "center", "left", "left"].map((a) => ({ style: { textAlign: a } })),
    });
    Object.assign(t, {
      colOrder: [0, 1, 2, 3, 4],
      colsdata: [
        { type: window.TYPE_STRING_LABEL, enabled: true },
        { type: window.TYPE_NUMBER, enabled: false },
        { type: window.TYPE_PROGRESS, enabled: true },
        { type: window.TYPE_STRING, enabled: true, titled: true },
        { type: window.TYPE_STRING, enabled: true },
      ],
      progressStyle: () => ({ width: "50%", visibility: "visible" }),
    });
    return t;
  }

  const sameNode = (row, html) => {
    // a <tr> only parses inside a table body
    const want = document.createElement("tbody");
    want.innerHTML = html;
    expect(want.firstChild.nodeName).toBe("TR");
    expect(row.isEqualNode(want.firstChild)).toBe(true);
  };

  it("builds a row with icon, label code tag, hidden column, progress meter and tooltip", () => {
    const t = table();
    t.rowdata = { h: { fmtdata: ["Name `x`", "5 MB", "50%", "msg", "c"] } };
    sameNode(t.createRow(["Name", "5", 500, "msg", "c"], "h", "Status_Up", {}), expected[0]);
  });

  it("builds a row of empty values with a single space and no tooltip", () => {
    const t = table();
    t.rowdata = { h: { fmtdata: ["", "", "", "", ""] } };
    sameNode(t.createRow(["", null, 0, "", ""], "h", null, { class: "k" }), expected[1]);
  });

  it("escapes markup in plain cells and builds an image icon", () => {
    const t = table();
    t.rowdata = { h: { fmtdata: ["a<b>", 7, "0%", 't&"q', "z"] } };
    sameNode(t.createRow(["a", 7, "0", "t", "z"], "h", { src: "a.png" }, undefined), expected[2]);
  });
});
