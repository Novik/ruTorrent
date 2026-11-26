import { readFileSync } from "fs";

window.$ = require("jquery");

// The real stable.js and common.js: a row is built by createRow() and then
// updated in place by setValue() + syncDOM(). Whatever the update leaves in a
// cell must be what createRow() would have built for the new value, including
// for TYPE_STRING_LABEL, whose escaped text may end in the normalizer's
// `label` shown as <code>.
window.theWebUI = { resource: {} };

for (const src of ["../lang/en.js", "../js/common.js", "../js/stable.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

const HASH = "E47249CE09FEE3AD99A38C6AEC5666E2FF134C64";
const COLUMNS = [
  { text: "Name", width: 200, id: "name", type: window.TYPE_STRING_LABEL, enabled: true },
  { text: "Ratio", width: 60, id: "ratio", type: window.TYPE_STRING, enabled: true },
  { text: "Done", width: 60, id: "done", type: window.TYPE_PROGRESS, enabled: true },
];

// The name goes through the normalizer, as theFormatter.torrents does when
// webui.normalize_torrent_name is on.
function format(_, arr) {
  for (const i in arr) {
    if (Number(i) === 0 && arr[i] != null) arr[i] = window.theNormalizer.normalize(arr[i], false);
  }
  return arr;
}

// A real dxSTable minus create(): the getters it reads (cols, tBody, ...) are
// served by this markup. Every sync is run by hand.
function torrentTable(firstId, data) {
  const dCont = window.$(
    "<div id='trt' class='stable'>" +
      "<table><colgroup></colgroup><thead><tr></tr></thead><tbody></tbody></table>" +
    "</div>"
  );
  const ids = COLUMNS.map((c) => c.id);
  const first = ids.indexOf(firstId);
  const colOrder = [first, ...ids.map((_, i) => i).filter((i) => i !== first)];
  for (let i = 0; i < COLUMNS.length; i++) {
    dCont.find("colgroup").append(window.$("<col>"));
    dCont.find("thead tr").append(window.$("<td>").css("text-align", "left"));
  }
  document.body.replaceChildren(dCont[0]);

  const table = Object.create(window.dxSTable.prototype);
  table.created = true;
  table.dCont = dCont;
  table.colOrder = colOrder;
  table.colsdata = colOrder.map((i) => COLUMNS[i]);
  table.ids = ids;
  table.pendingSync = {};
  table.syncDOMAsync = () => {};
  table.format = format;
  table.noSort = true;
  table.prgStartColor = new window.RGBackground("#ff0000");
  table.prgEndColor = new window.RGBackground("#00ff00");
  table.rowdata = {
    [HASH]: { data: data.slice(), icon: "Status_Up", attr: {}, enabled: true, fmtdata: format(null, data.slice()) },
  };
  const tr = table.createRow(data, HASH, "Status_Up", {});
  table.dCont.find("tbody").append(tr);
  return { table, tr };
}

const cellsHTML = (tr) => [...tr.cells].map((td) => td.innerHTML);

// Update every column of a built row in place, then build a fresh row from
// the same values: the two must be the same DOM.
function updatedAndFresh(firstId, before, after) {
  const { table, tr } = torrentTable(firstId, before);
  after.forEach((v, col) => table.setValue(HASH, col, v));
  table.syncDOM();
  const fresh = torrentTable(firstId, after).tr;
  return { updated: tr, fresh };
}

const BEFORE = ["[GrpA] Some.Show.S01", "0.000", "10"];
const AFTER = ["[GrpB] Other.Show.S02", "1.250", "55.5"];
const EVIL = '[<img src=x onerror="window.pwned=1">] Movie <img src=x onerror="window.pwned=1">';

describe("in-place update of a cell", () => {
  afterEach(() => {
    delete window.pwned;
  });

  for (const firstId of ["name", "ratio"]) {
    describe(`with ${firstId} as the first column`, () => {
      it("leaves the same DOM as a fresh row for the new values", () => {
        const { updated, fresh } = updatedAndFresh(firstId, BEFORE, AFTER);
        expect(cellsHTML(updated)).toEqual(cellsHTML(fresh));
      });

      it("keeps the normalizer's label as code after the update", () => {
        const { updated } = updatedAndFresh(firstId, BEFORE, AFTER);
        const nameCell = updated.querySelector(".stable-trt-col-0");
        const code = nameCell.querySelectorAll("code");
        expect(code.length).toBe(1);
        expect(code[0].textContent).toBe("GrpB");
        expect(nameCell.textContent).toBe("Other.Show.S02 GrpB");
      });

      it("keeps the status icon only in the first cell", () => {
        const { updated } = updatedAndFresh(firstId, BEFORE, AFTER);
        expect([...updated.cells].map((td) => td.querySelectorAll(".stable-icon").length)).toEqual([1, 0, 0]);
      });

      it("matches a fresh row when a value is cleared", () => {
        const { updated, fresh } = updatedAndFresh(firstId, BEFORE, ["", "", ""]);
        expect(cellsHTML(updated)).toEqual(cellsHTML(fresh));
        // A text cell keeps a blank in it, as createRow() has always put there.
        expect(updated.querySelector(".stable-trt-col-0 div").textContent).toBe(" ");
        expect(updated.querySelector(".stable-trt-col-1 div").textContent).toBe(" ");
      });

      it("shows markup in a name or label as text, after create and after update", () => {
        const created = torrentTable(firstId, [EVIL, "0.000", "0"]).tr;
        const { updated } = updatedAndFresh(firstId, BEFORE, [EVIL, "0.000", "0"]);
        for (const tr of [created, updated]) {
          const nameCell = tr.querySelector(".stable-trt-col-0");
          expect(nameCell.querySelectorAll("img").length).toBe(0);
          expect([...nameCell.querySelectorAll("code")].map((el) => el.textContent))
            .toEqual(['<img src=x onerror="window.pwned=1">']);
          expect(nameCell.querySelectorAll("code *").length).toBe(0);
          expect(nameCell.textContent).toBe('Movie <img src=x onerror="window.pwned=1"> <img src=x onerror="window.pwned=1">');
        }
        expect(window.pwned).toBeUndefined();
      });
    });
  }
});
