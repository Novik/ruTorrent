import { readFileSync } from "fs";

// The Load dialog of an extsearch result fills its label dropdown from the
// labels of the torrents already loaded, so a label is only as trustworthy
// as whatever set it.
//
// jsdom cannot show the consequence: it neither fetches an <img> nor fires
// its onerror. What it can show is the cause -- that building the list
// creates elements out of a label, and that the value the dialog would
// submit is no longer the label. A browser run is what proves execution.

window.$ = window.jQuery = require("jquery");

// Pull the shipped handlers out of plugins/extsearch/init.js rather than
// restating them, so this spec cannot drift from the code that ships.
function extractBlock(src, needle) {
  const start = src.indexOf(needle);
  if (start < 0) throw new Error("not found in plugins/extsearch/init.js: " + needle);
  let i = src.indexOf("{", start);
  let depth = 0;
  for (; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}" && --depth === 0) break;
  }
  return src.slice(start, src.indexOf(";", i) + 1);
}

const initSrc = readFileSync("../plugins/extsearch/init.js", { encoding: "utf-8" });
const builder = extractBlock(
  initSrc,
  'theDialogManager.setHandler("tegLoadTorrents", "beforeShow"'
);
// The change handler is what copies the chosen option's value into
// #teglabel, the field the Load path sends as the label.
const onChange = extractBlock(initSrc, '$("#teglabel_select").on("change"');
const onReturn = extractBlock(initSrc, '$("#teglabel_return_select").on("click"');

// The beforeShow handler the dialog manager is given: calling it again is
// the dialog being opened a second time, on the DOM the first visit left.
let reopenDialog;

const theUILang = { No_label: "No label", newLabel: "New label...", Return_select_label: "<<" };

function buildWith(labels) {
  document.body.innerHTML = `
    <div id="tegLoadTorrents">
      <input type="text" id="teglabel">
      <select id="teglabel_select"></select>
      <button type="button" id="teglabel_return_select"></button>
    </div>`;

  const theWebUI = {
    categoryList: {
      torrentLabelTree: {
        torrentLabels: new Map(labels.map((l) => [l, {}])),
      },
    },
  };
  const theDialogManager = {
    setHandler: (dlg, ev, fn) => {
      reopenDialog = fn;
    },
  };


  new Function(
    "$", "theUILang", "theWebUI", "theDialogManager",
    `${onChange}\n${onReturn}\n${builder}`
  )(window.$, theUILang, theWebUI, theDialogManager);

  reopenDialog();
  return document.getElementById("teglabel_select");
}

const el = (id) => document.getElementById(id);
// jsdom lays nothing out, so :visible answers "hidden" for every element.
// The handlers show and hide with jQuery, which writes style.display.
const shown = (id) => el(id).style.display !== "none";

// Pick option i, run the real change handler, and read what would be sent.
function submittedLabelAt(sel, i) {
  sel.selectedIndex = i;
  window.$(sel).trigger("change");
  return el("teglabel").value;
}

describe("extsearch Load dialog label dropdown", () => {
  it("keeps the two fixed entries and adds one option per label", () => {
    const sel = buildWith(["alpha", "beta"]);
    expect(sel.options.length).toBe(4);
    expect(sel.options[0].text).toBe("No label");
    expect(sel.options[1].text).toBe("New label...");
    expect(sel.options[2].text).toBe("alpha");
    expect(sel.options[3].text).toBe("beta");
  });

  it("opens on the dropdown, with the free-text field put away", () => {
    buildWith(["alpha"]);
    expect(el("teglabel_select").selectedIndex).toBe(0);
    expect(shown("teglabel_select")).toBe(true);
    expect(shown("teglabel")).toBe(false);
    expect(shown("teglabel_return_select")).toBe(false);
    expect(el("teglabel").value).toBe("");
  });

  it("reopens on the dropdown, clearing what the last visit left", () => {
    const sel = buildWith(["alpha", "beta"]);
    submittedLabelAt(sel, 1);
    window.$("#teglabel").val("typed last time");
    reopenDialog();
    expect(el("teglabel_select").selectedIndex).toBe(0);
    expect(el("teglabel").value).toBe("");
    expect(shown("teglabel")).toBe(false);
    expect(shown("teglabel_select")).toBe(true);
    expect(shown("teglabel_return_select")).toBe(false);
  });

  it("does not turn a label into markup", () => {
    const sel = buildWith(['<img src=x onerror="window.ran=1">']);
    expect(sel.querySelectorAll("img").length).toBe(0);
    expect(sel.options.length).toBe(3);
    expect(sel.options[2].textContent).toBe('<img src=x onerror="window.ran=1">');
  });

  it("does not let a label close the option tag", () => {
    const sel = buildWith(["</option><option>injected"]);
    expect(sel.options.length).toBe(3);
    expect(sel.options[2].textContent).toBe("</option><option>injected");
  });

  it("submits the literal label when the label contains markup", () => {
    const label = "<b>evil";
    const sel = buildWith([label]);
    expect(sel.options[2].value).toBe(label);
    expect(submittedLabelAt(sel, 2)).toBe(label);
  });

  it("submits the literal label when it contains repeated whitespace", () => {
    // Without an explicit value attribute option.value falls back to the
    // option's text, which is stripped and collapsed.
    const label = "two  spaces";
    const sel = buildWith([label]);
    expect(sel.options[2].value).toBe(label);
    expect(submittedLabelAt(sel, 2)).toBe(label);
  });

  it("leaves the label empty for the No-label entry", () => {
    const sel = buildWith(["alpha"]);
    expect(submittedLabelAt(sel, 0)).toBe("");
  });

  it("hands over to the free-text field for the New-label entry", () => {
    const sel = buildWith(["alpha"]);
    expect(submittedLabelAt(sel, 1)).toBe("");
    expect(shown("teglabel")).toBe(true);
    expect(shown("teglabel_select")).toBe(false);
    expect(shown("teglabel_return_select")).toBe(true);
  });

  it("goes back to the dropdown, and drops the typed label, on return", () => {
    const sel = buildWith(["alpha"]);
    submittedLabelAt(sel, 1);
    window.$("#teglabel").val("typed by hand");
    window.$("#teglabel_return_select").trigger("click");
    expect(el("teglabel").value).toBe("");
    expect(shown("teglabel")).toBe(false);
    expect(shown("teglabel_select")).toBe(true);
    expect(el("teglabel_select").selectedIndex).toBe(0);
    expect(shown("teglabel_return_select")).toBe(false);
  });
});
