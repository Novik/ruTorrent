import { readFileSync } from "fs";

// The disk meter shows the used percentage; its tooltip gives the free space in
// bytes. The percentage of free space goes into the same %FREE% substitution,
// so no translation has to change, and it is rounded down so it never claims
// more free space than there is.
window.$ = require("jquery");

const DISK_USAGE = "%USED%/%TOTAL% (%FREE% free)";

function loadPlugin() {
  document.body.innerHTML =
    '<div id="meter-disk-pane"><div id="meter-disk-value"></div><div id="meter-disk-text"></div></div>';
  window.iv = (v) => parseInt(v, 10) || 0;
  window.theConverter = { bytes: (v) => String(v) };
  window.theUILang = { diskUsage: DISK_USAGE, diskNotification: "full" };
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

loadPlugin();

function title(total, free) {
  plugin.setValue(total, free);
  return document.getElementById("meter-disk-pane").getAttribute("title");
}

describe("the disk space tooltip", () => {
  it("gives the free space as bytes and as a percentage", () => {
    expect(title(400, 60)).toBe("340/400 (60, 15% free)");
  });

  it("rounds the percentage down, never overstating free space", () => {
    // 15.9% free reads as 15, not 16.
    expect(title(1000, 159)).toBe("841/1000 (159, 15% free)");
  });

  it("reads zero rather than dividing by a zero total", () => {
    expect(title(0, 0)).toBe("0/0 (0, 0% free)");
  });

  it("leaves the wording to the translated string", () => {
    window.theUILang.diskUsage = "libre : %FREE% sur %TOTAL%";
    expect(title(1000, 250)).toBe("libre : 250, 25% sur 1000");
    window.theUILang.diskUsage = DISK_USAGE;
  });

  it("still shows the used percentage in the meter itself", () => {
    plugin.setValue(400, 60);
    expect(document.getElementById("meter-disk-text").textContent).toBe("85%");
  });
});
