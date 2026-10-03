import { readFileSync } from "fs";

// theContextMenu.show() with no coordinates falls back to the last position a
// mousemove reported. A touch device sends no mousemove, so every selection
// handler hands the menu the coordinates of the event that opened it, as the
// torrent list already did.
window.$ = require("jquery");

function loadWebUI() {
  window.theUILang = new Proxy({}, { get: (_t, prop) => prop });
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

loadWebUI();

const AT = { clientX: 320, clientY: 240, which: 3 };

describe("the context menu of a selection handler", () => {
  let opened;

  beforeEach(() => {
    opened = [];
    window.theContextMenu = {
      clear: () => {},
      add: () => {},
      setNoHide: () => {},
      show: (x, y) => opened.push([x, y]),
    };
    window.thePlugins = {
      get: () => ({ enabled: true, launched: false, help: "", canShutdown: () => true, canBeLaunched: () => true }),
    };
    window.theWebUI.getTable = () => ({ selCount: 1, getSelected: () => [] });
    window.theWebUI.createFileMenu = () => true;
    window.theWebUI.createPeerMenu = () => true;
    window.theWebUI.createTrackerMenu = () => true;
    window.theWebUI.settings = { "webui.fls.view": 1 };
    window.theWebUI.dID = "HASH";
    window.theWebUI.files = { HASH: [{ name: "a file" }] };
  });

  it("opens the file menu where the event is", () => {
    window.theWebUI.flsSelect(AT, "HASH_f_0");
    expect(opened).toEqual([[320, 240]]);
  });

  it("opens the peer menu where the event is", () => {
    window.theWebUI.prsSelect(AT, "peer1");
    expect(opened).toEqual([[320, 240]]);
  });

  it("opens the tracker menu where the event is", () => {
    window.theWebUI.trkSelect(AT, "HASH_t_0");
    expect(opened).toEqual([[320, 240]]);
  });

  it("opens the plugin menu where the event is", () => {
    window.theWebUI.plgSelect(AT, "_plg_trafic");
    expect(opened).toEqual([[320, 240]]);
  });
});
