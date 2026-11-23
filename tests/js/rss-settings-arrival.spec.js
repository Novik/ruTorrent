import { readFileSync } from "fs";

window.$ = require("jquery");

// The rss plugin asks for its settings some time after the page has loaded,
// and the Settings dialog fills its page from what it has when it opens. This
// spec loads the real js/webui.js and plugins/rss/init.js, opens the dialog
// before the settings arrive, lets them arrive, and checks what the page then
// shows and what Save sends.

function run(code) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

function load(src) {
  run(readFileSync(src, { encoding: "utf-8" }));
}

for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js", "../js/stable.js"]) {
  load(src);
}

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

for (const [name, stub] of Object.entries({
  injectCustomElementCSS: () => {},
  injectCustomElementAttribute: () => {},
  injectScript: () => {},
  rTorrentStub: function () {},
  dxSTable: function () {},
  thePlugins: { get: () => ({ langLoaded: () => {} }), isInstalled: () => false },
  noty: () => {},
})) {
  if (window[name] === undefined) window[name] = stub;
}

window.__makePlugin = (path) =>
  new Proxy(
    {
      path,
      name: "rss",
      enabled: true,
      allStuffLoaded: false,
      canChangeMenu: () => true,
      canChangeOptions: () => true,
      loadLang: () => {},
      loadMainCSS: () => {},
    },
    {
      has: () => true,
      get: (target, prop) => (prop in target ? target[prop] : () => {}),
    }
  );

// The dialog itself and the core Save are not under test: the plugin wraps
// both, so they are replaced before it loads and it wraps the stand-ins.
theWebUI.addAndShowSettings = () => {};
theWebUI.setSettings = () => {};
theWebUI.categoryList = {
  switchLabel: () => {},
  contextMenuEntries: () => [],
  panelLabelAttribs: { prss: new Map() },
  refreshPanel: {},
};

load("../plugins/rss/lang/en.js");
run(
  "(function(){ var plugin = window.__makePlugin('../plugins/rss/');\ntry {\n" +
    readFileSync("../plugins/rss/init.js", { encoding: "utf-8" }) +
    "\n} catch(e) { window.__pluginLoadError = e; }\n})();"
);

// What the plugin's settings page holds, as plugin.onLangLoaded builds it.
function settingsPage() {
  $("#st_rss").remove();
  $("body").append(
    $("<div>").attr("id", "st_rss").append(
      $("<input>").attr({ type: "text", id: "rss_interval", maxlength: 4 }),
      $("<input>").attr({ type: "checkbox", id: "rss_show_errors_delayed" })
    )
  );
}

// The state theWebUI.config leaves before getrsssettings has answered.
function beforeSettingsArrive() {
  theWebUI.updateRSSInterval = 5 * 60 * 1000;
  theWebUI.rssShowErrorsDelayed = true;
}

function settingsArrive(interval, delayerrui) {
  theWebUI.getRSSSettings({ interval, updatedAt: Math.floor(Date.now() / 1000), delayerrui });
}

let sent;

beforeEach(() => {
  jest.useFakeTimers();
  settingsPage();
  beforeSettingsArrive();
  theWebUI.loadRSS = () => {};
  theWebUI.showDelayedRSSErrros = () => {};
  sent = [];
  jest.spyOn(theWebUI, "RSSSetSettings").mockImplementation((interval, delayed) => sent.push([String(interval), delayed]));
});

afterEach(() => {
  jest.useRealTimers();
  jest.restoreAllMocks();
});

it("loads", () => {
  expect(window.__pluginLoadError).toBeUndefined();
});

describe("the Settings dialog opened before the rss settings arrive", () => {
  beforeEach(() => {
    theWebUI.addAndShowSettings();
  });

  it("shows the default until they arrive", () => {
    expect($("#rss_interval").val()).toBe("5");
  });

  it("shows the saved interval once they arrive", () => {
    settingsArrive("37", true);
    expect($("#rss_interval").val()).toBe("37");
  });

  it("shows the saved error display once it arrives", () => {
    settingsArrive("37", false);
    expect($("#rss_show_errors_delayed").prop("checked")).toBe(false);
  });

  it("does not write the default back over the saved interval on Save", () => {
    settingsArrive("37", false);
    theWebUI.setSettings();
    expect(sent).toEqual([]);
  });

  it("keeps an interval typed before they arrive, and Save sends it", () => {
    $("#rss_interval").val("12");
    settingsArrive("37", true);
    expect($("#rss_interval").val()).toBe("12");
    theWebUI.setSettings();
    expect(sent).toEqual([["12", true]]);
  });

  it("keeps an error display changed before they arrive, and Save sends it", () => {
    $("#rss_show_errors_delayed").prop("checked", false);
    settingsArrive("37", true);
    expect($("#rss_show_errors_delayed").prop("checked")).toBe(false);
    theWebUI.setSettings();
    expect(sent).toEqual([["37", false]]);
  });
});

describe("rss settings arriving with the dialog filled after them", () => {
  it("fill the dialog when it opens", () => {
    settingsArrive("37", false);
    theWebUI.addAndShowSettings();
    expect($("#rss_interval").val()).toBe("37");
    expect($("#rss_show_errors_delayed").prop("checked")).toBe(false);
    theWebUI.setSettings();
    expect(sent).toEqual([]);
  });

  it("after a Save, show what was saved", () => {
    settingsArrive("37", true);
    theWebUI.addAndShowSettings();
    $("#rss_interval").val("12");
    theWebUI.setSettings();
    expect(sent).toEqual([["12", true]]);
    settingsArrive("12", true);
    expect($("#rss_interval").val()).toBe("12");
  });
});
