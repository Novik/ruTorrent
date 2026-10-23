import { readFileSync } from "fs";

// The plugin's first poll can land before its translation does: loadLang() is
// asynchronous and the first check() goes out immediately, and a locale with no
// diskspace translation of its own pays a 404 and an en fallback before
// theUILang gains a diskUsage at all. setValue therefore has to cope with the
// string being absent. It runs from the ajax success handler, so a throw there
// also costs the complete handler that schedules the next poll: the pane would
// keep its first sample for as long as the page is open.
window.$ = require("jquery");

function loadPlugin() {
  document.body.innerHTML =
    '<div id="meter-disk-pane"><div id="meter-disk-value"></div><div id="meter-disk-text"></div></div>';
  window.iv = (v) => parseInt(v, 10) || 0;
  window.theConverter = { bytes: (v) => String(v) };
  // The state the race produces: the language file has not been applied yet.
  window.theUILang = {};
  window.RGBackground = function () {
    this.setGradient = () => this;
    this.getColor = () => "#000000";
  };
  window.plugin = {
    loadLang: () => {},
    loadMainCSS: () => {},
    freeBytesInMeter: false,
    allStuffLoaded: false,
    prgStartColor: "#000000",
    prgEndColor: "#ffffff",
    init: () => {},
    markLoaded: () => {},
    addPaneToStatusbar: () => {},
    removePaneFromStatusbar: () => {},
  };

  let code = readFileSync("../plugins/diskspace/init.js", { encoding: "utf-8" });
  // The file ends by starting its own polling; the subject here is setValue.
  code = code.replace(/\nplugin\.init\(\);\s*$/, "");
  const el = document.createElement("script");
  el.textContent = code;
  document.body.appendChild(el);
}

beforeEach(loadPlugin);

function pane() {
  return document.getElementById("meter-disk-pane");
}

describe("a sample that arrives before the translation", () => {
  it("is taken without throwing, so the next poll is still scheduled", () => {
    expect(window.theUILang.diskUsage).toBeUndefined();
    expect(() => plugin.setValue(400, 60)).not.toThrow();
  });

  it("leaves the pane without a title rather than a half-built one", () => {
    plugin.setValue(400, 60);
    expect(pane().getAttribute("title")).toBeNull();
  });

  it("still fills the meter, which needs no translation", () => {
    plugin.setValue(400, 60);
    expect(document.getElementById("meter-disk-text").textContent).toBe("85%");
  });

  it("gains the title on the first sample after the translation arrives", () => {
    plugin.setValue(400, 60);
    window.theUILang.diskUsage = "%USED%/%TOTAL% (%FREE% free)";
    plugin.setValue(400, 60);
    expect(pane().getAttribute("title")).toBe("340/400 (60, 15% free)");
  });
});
