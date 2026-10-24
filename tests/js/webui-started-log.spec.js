import { readFileSync } from "fs";

// theWebUI.init() writes the first line of the Log tab. index.html loads
// webui.js only once the language file has loaded, so that line comes from
// theUILang like the rest of the interface.
window.$ = require("jquery");

function loadScript(src) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

function loadWebUI() {
  window.theFormatter = {};
  window.TYPE_STRING = "string";
  window.TYPE_NUMBER = "number";
  window.TYPE_PROGRESS = "progress";
  window.TYPE_PEERS = "peers";
  window.TYPE_SEEDS = "seeds";
  window.ALIGN_RIGHT = "right";
  window.CMENU_CHILD = "child";
  window.CMENU_SEP = "sep";
  window.dxSTable = function () {};
  window.rSpeedGraph = function () {};
  window.rSpeedGraph.prototype.addData = () => {};
  window.Timer = function () {};
  window.$type = (o) => (o == undefined ? false : typeof o);
  window.iv = (v) => parseInt(v, 10) || 0;

  let code = readFileSync("../js/webui.js", { encoding: "utf-8" });
  code = code.replace(/\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/, "");
  const el = document.createElement("script");
  el.textContent = code;
  document.body.appendChild(el);
}

loadScript("../lang/en.js");
loadWebUI();

function firstLogLine() {
  const lines = [];
  window.log = (text) => lines.push(text);
  // The old-browser branch stops init() before it sends any request.
  window.browser = { isOldIE: true };
  theWebUI.setStatusUpdate = () => {};
  theWebUI.msg = () => {};
  theWebUI.init();
  return lines[0];
}

describe("the first line of the Log tab", () => {
  test.each([
    ["en", "WebUI started."],
    ["fr", "Interface web démarrée."],
  ])("is in the interface language (%s)", (lang, expected) => {
    loadScript(`../lang/${lang}.js`);
    expect(firstLogLine()).toBe(expected);
  });
});
