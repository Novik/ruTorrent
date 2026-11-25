import { readFileSync } from "fs";
window.$ = window.jQuery = require("jquery");

// webui.js needs most of the UI stack, in the order index.html loads it.
for (const src of ["../lang/langs.js", "../lang/en.js", "../js/sanitize.js", "../js/sanitize.config.js",
  "../js/browser.js", "../js/common.js", "../js/objects.js", "../js/options.js",
  "../js/content.js", "../js/stable.js", "../js/graph.js", "../js/plugins.js",
  "../js/rtorrent.js", "../js/webui.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

// The category list is built by theWebUI's init, which this spec does not run;
// extsearch only wraps a few of its methods at load.
theWebUI.categoryList = {
  switchLabel() {}, contextMenuEntries: () => [], refreshPanel: { psearch: () => [] },
  setQuickSearch() {}, resetSelection() {},
};

// extsearch the way php/getplugins.php hands it to the browser: init.js inside
// a function with its rPlugin. Its init.php would also list the search sites.
const plugin = document.createElement("script");
plugin.textContent = "(function () { var plugin = new rPlugin('extsearch', 3.0, '', '', 0, '');\n"
  + "plugin.loadMainCSS = plugin.loadLang = function() {};\n"
  + "theSearchEngines.sites = { tracker: { enabled: 1, global: 1, public: 1, cats: ['all'] } };\n"
  + readFileSync("../plugins/extsearch/init.js", { encoding: "utf-8" })
  + "\n})();";
document.body.appendChild(plugin);

// Opening the Settings dialog fetches rtorrent's settings and fills the dialog
// with every setting, the stored UI ones included. Nothing there is a change
// the user made, so nothing may be written back: a tab opened earlier would
// otherwise overwrite settings saved since from another tab with its copy.
describe("opening the Settings dialog", () => {
  let uiSaves;

  beforeEach(() => {
    uiSaves = 0;
    theWebUI.configured = true;
    theWebUI.systemInfo = { rTorrent: { started: true, iVersion: 0x1010 } };
    theWebUI.request = jest.fn((qs, cb) => {
      if (String(qs).includes("action=setuisettings"))
        uiSaves++;
      else if (String(qs).includes("action=getsettings") && cb)
        cb[0].call(cb[1], { max_peers: "100" });
    });
    theDialogManager.show = jest.fn();
    theSearchEngines.current = -1;
    theWebUI.settings["webui.search"] = -1;
  });

  it("is a real dialog open, with extsearch loaded", () => {
    expect(thePlugins.get("extsearch").enabled).toBe(true);
    theWebUI.showSettings();
    expect(theDialogManager.show).toHaveBeenCalledWith("stg");
  });

  it("saves no UI settings when the toolbar search is the torrent list", () => {
    theWebUI.showSettings();
    expect(uiSaves).toBe(0);
  });

  it("saves no UI settings when the search is a site that exists", () => {
    theSearchEngines.current = "tracker";
    theWebUI.settings["webui.search"] = "tracker";
    theWebUI.showSettings();
    expect(uiSaves).toBe(0);
    expect(theSearchEngines.current).toBe("tracker");
  });

  it("does not save when the search menu is opened either", () => {
    for (const fn of ["clear", "add", "show"])
      theContextMenu[fn] = jest.fn();
    if (!$("#search").length)
      $("<div>").attr("id", "search").appendTo(document.body);
    theSearchEngines.show();
    expect(uiSaves).toBe(0);
  });

  it("still saves a search the user picks", () => {
    theSearchEngines.set("tracker");
    expect(theSearchEngines.current).toBe("tracker");
    expect(uiSaves).toBe(1);
  });

  it("still saves when a stored search no longer exists and is reset", () => {
    theWebUI.settings["webui.search"] = "gone";
    theWebUI.showSettings();
    expect(theSearchEngines.current).toBe(-1);
    expect(uiSaves).toBe(1);
  });
});

// Dragging a panel divider stores the new split; letting go where it already
// was is not a change.
describe("the panel dividers", () => {
  let uiSaves;

  beforeEach(() => {
    uiSaves = 0;
    theWebUI.configured = true;
    theWebUI.request = jest.fn((qs) => {
      if (String(qs).includes("action=setuisettings"))
        uiSaves++;
    });
    $("#split_fixture").remove();
    $("<div>").attr("id", "split_fixture").append(
      $("<div>").attr("id", "maincont"), $("<div>").attr("id", "tdetails"),
    ).appendTo(document.body);
    // jsdom lays nothing out: give the two boxes the sizes a drag left.
    jest.spyOn($.fn, "height").mockImplementation(function () { return this.is("#maincont") ? 1000 : 0; });
    jest.spyOn($.fn, "outerHeight").mockImplementation(function () { return this.is("#tdetails") ? 195 : 0; });
  });
  afterEach(() => jest.restoreAllMocks());

  it("saves a split the user moved", () => {
    theWebUI.settings["webui.vsplit"] = 0.5;
    theWebUI.setVSplitter();
    expect(theWebUI.settings["webui.vsplit"]).toBe(0.8);
    expect(uiSaves).toBe(1);
  });

  it("does not save a split that did not move", () => {
    theWebUI.settings["webui.vsplit"] = 0.8;
    theWebUI.setVSplitter();
    expect(uiSaves).toBe(0);
  });
});
