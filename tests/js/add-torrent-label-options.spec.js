import { readFileSync } from "fs";

// The Add-torrent dialog fills its label dropdown from the labels of the
// torrents already loaded, so a label is only as trustworthy as whatever set
// it -- an RSS filter's ${N} capture, an autotools {TRACKER} expansion, or a
// plain request to php/addtorrent.php all write one.
//
// jsdom cannot show the consequence: it neither fetches an <img> nor fires its
// onerror. What it can show is the cause -- that building the list creates
// elements out of the label, and that the value the dialog would submit is no
// longer the label. The browser run is what proves execution.

window.$ = window.jQuery = require("jquery");

// Pull the shipped handlers out of js/content.js rather than restating them,
// so this spec cannot drift from the code that ships.
function extractBlock(src, needle) {
  const start = src.indexOf(needle);
  if (start < 0) throw new Error("not found in js/content.js: " + needle);
  let i = src.indexOf("{", start);
  let depth = 0;
  for (; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}" && --depth === 0) break;
  }
  return src.slice(start, src.indexOf(";", i) + 1);
}

const contentSrc = readFileSync("../js/content.js", { encoding: "utf-8" });
const builder = extractBlock(
  contentSrc,
  "theDialogManager.setHandler('tadd', 'beforeShow'"
);
// The change handler is what copies the chosen option's value into
// #tadd_label, the field the submit path sends as label=.
const onChange = extractBlock(
  contentSrc,
  "$(\"#tadd_label_select\").on('change'"
);

function buildWith(labels) {
  document.body.innerHTML = `
    <div id="tadd" style="display:none">
      <input type="text" id="tadd_label">
      <select id="tadd_label_select"></select>
      <span id="tadd-return-select"></span>
      <input type="file" id="torrent_file">
      <button id="add_button"></button>
    </div>`;

  const theUILang = { No_label: "No label", newLabel: "New label..." };
  const $$ = (id) => document.getElementById(id);
  const theWebUI = {
    settings: {},
    categoryList: {
      torrentLabelTree: {
        torrentLabels: new Map(labels.map((l) => [l, {}])),
      },
    },
  };
  const box = {};
  const theDialogManager = { setHandler: (dlg, ev, fn) => (box.fn = fn) };

  // eslint-disable-next-line no-new-func
  new Function(
    "$", "theUILang", "$$", "theWebUI", "theDialogManager",
    `${onChange}\n${builder}`
  )(window.$, theUILang, $$, theWebUI, theDialogManager);

  box.fn();
  return document.getElementById("tadd_label_select");
}

// Pick option i and run the real change handler, then read what would be sent.
function submittedLabelAt(sel, i) {
  sel.selectedIndex = i;
  window.$(sel).trigger("change");
  return document.getElementById("tadd_label").value;
}

describe("Add-torrent dialog label dropdown", () => {
  it("keeps the two fixed entries and adds one option per label", () => {
    const sel = buildWith(["alpha", "beta"]);
    expect(sel.options.length).toBe(4);
    expect(sel.options[0].text).toBe("No label");
    expect(sel.options[1].text).toBe("New label...");
    expect(sel.options[2].text).toBe("alpha");
    expect(sel.options[3].text).toBe("beta");
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
});
