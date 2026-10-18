import { readFileSync } from "fs";

window.$ = require("jquery");

// The real stable.js and common.js: the subject is the cell createRow builds
// for the torrent name when webui.normalize_torrent_name is on, the one
// column type (TYPE_STRING_LABEL) whose text goes through .html() so the
// normalizer's trailing `label` can be shown as code.
window.TYPE_STRING = "string";
window.TYPE_NUMBER = "number";
window.TYPE_PROGRESS = "progress";
window.TYPE_STRING_LABEL = "label";
window.theWebUI = { resource: {} };

for (const src of ["../lang/en.js", "../js/common.js", "../js/stable.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

function nameCell(name) {
  const columns = [{ text: "Name", width: 200, id: "name", type: window.TYPE_STRING_LABEL, enabled: true }];
  const dCont = window.$(
    "<div id='trt' class='stable'>" +
      "<table><colgroup><col></colgroup><thead><tr><td></td></tr></thead><tbody></tbody></table>" +
    "</div>"
  );
  document.body.appendChild(dCont[0]);
  const table = Object.create(window.dxSTable.prototype);
  table.created = true;
  table.dCont = dCont;
  table.colOrder = [0];
  table.colsdata = columns;
  table.ids = ["name"];
  table.rowdata = { ROW1: { fmtdata: [window.theNormalizer.normalize(name, false)] } };
  table.createIcon = () => window.$("<span>");
  const row = table.createRow([name], "ROW1");
  dCont.remove();
  return window.$(row).find("td").first().children("div").first();
}

// Elements in the cell other than the row icon createIcon() puts first.
function markup(div) {
  return div.find("*").filter((_, el) => el.tagName !== "SPAN");
}

describe("normalized torrent name cell", () => {
  it("shows a name carrying markup as text, never as elements", () => {
    const div = nameCell("Movie.2024 <img src=x onerror=alert(document.domain)>");
    expect(div.find("img").length).toBe(0);
    expect(markup(div).length).toBe(0);
    expect(div.text()).toBe("Movie.2024 <img src=x onerror=alert(document.domain)>");
  });

  it("still marks up the normalizer's trailing label as code, with its text escaped", () => {
    const div = nameCell("[Gr<b>oup] Some.Show.S01");
    const code = div.find("code");
    expect(code.length).toBe(1);
    expect(code.find("*").length).toBe(0);
    expect(code.text()).toBe("Gr<b>oup");
    expect(div.text()).toBe("Some.Show.S01 Gr<b>oup");
  });

  it("keeps quotes, ampersands and backslashes of a raw name byte for byte", () => {
    // The ampersands have to begin entities, or the case cannot tell an
    // escape that handles "&" from one that does not: "&c" is no entity, so
    // it survives .html() either way. "&amp;" and "&lt;" are, and an escape
    // that left "&" alone would hand .html() something to decode -- the cell
    // would read "A & B <i>" instead of the name that was stored.
    const raw = 'aud "q" \\b \'a\' A &amp; B &lt;i&gt;';
    const div = nameCell(raw);
    expect(markup(div).length).toBe(0);
    expect(div.text()).toBe(raw);
  });
});
