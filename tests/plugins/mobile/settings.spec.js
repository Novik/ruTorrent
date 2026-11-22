import { readFileSync } from "fs";

window.$ = window.jQuery = require("jquery");

window.theUILang = {
  Name: "Name", Status: "Status", Size: "Size", Uploaded: "Uploaded",
  Downloaded: "Downloaded", Done: "Done", ETA: "ETA", Ul_speed: "UL",
  Down_speed: "DL", Ratio: "Ratio", addTime: "Added", seedingTime: "Seeding",
};

// What the server holds; the plugin re-reads it before every save
let storedOnServer = {};
let saveCount = 0;

window.theWebUI = {
  settings: {},
  requestWithoutTimeout: function (url, callback) {
    expect(url).toBe("?action=getuisettings");
    callback(JSON.parse(JSON.stringify(storedOnServer)));
  },
  save: function () {
    saveCount++;
    storedOnServer = JSON.parse(JSON.stringify(window.theWebUI.settings));
  },
};

// $type and friends come from the core, as they do in the browser
const coreEl = document.createElement("script");
coreEl.textContent = readFileSync("../js/common.js", { encoding: "utf-8" });
document.body.appendChild(coreEl);

let code = readFileSync("../plugins/mobile/init.js", { encoding: "utf-8" });
// The takeover call at the end of the file needs the whole desktop UI
// (theWebUI, thePlugins, the page markup); this spec exercises the
// settings helpers, which are plain assignments above it
code = code.replace(/plugin\.disableOthers\(\);\s*$/, "");
const scriptEl = document.createElement("script");
scriptEl.textContent = `(function () { var plugin = {}; plugin.path = "../plugins/mobile/"; ${code} })();`;
document.body.appendChild(scriptEl);

const plugin = window.mobile;

describe("mobile plugin settings", () => {
  beforeEach(() => {
    window.theWebUI.settings = {};
    storedOnServer = {};
    saveCount = 0;
  });

  describe("storedSetting", () => {
    const isTheme = (v) => plugin.isOption(plugin.themes, v);

    it("returns the stored value when it is a known option", () => {
      window.theWebUI.settings["webui.mobile.theme"] = "dark";
      expect(plugin.storedSetting("theme", isTheme, "light")).toBe("dark");
    });

    it("falls back to the default when nothing is stored", () => {
      expect(plugin.storedSetting("theme", isTheme, "system")).toBe("system");
    });

    it("falls back to the default for an unknown value", () => {
      window.theWebUI.settings["webui.mobile.theme"] = "neon";
      expect(plugin.storedSetting("theme", isTheme, "light")).toBe("light");
    });

    it("falls back to the default for a non-string value", () => {
      // The core coerces "true"/"false"/"on"/"auto" settings to 1/0
      window.theWebUI.settings["webui.mobile.theme"] = 1;
      expect(plugin.storedSetting("theme", isTheme, "light")).toBe("light");
    });

    it("accepts every accent color offered by the settings page", () => {
      $.each(plugin.accentColors, (i, c) => {
        window.theWebUI.settings["webui.mobile.accent"] = c[0];
        expect(
          plugin.storedSetting("accent", (v) => plugin.isOption(plugin.accentColors, v), "primary")
        ).toBe(c[0]);
      });
    });
  });

  describe("isKnownSort", () => {
    it("accepts every sort field, ascending and descending", () => {
      $.each(plugin.sortFields, (i, f) => {
        expect(plugin.isKnownSort(f[0])).toBe(true);
        expect(plugin.isKnownSort("-" + f[0])).toBe(true);
      });
    });

    it("rejects an unknown field", () => {
      expect(plugin.isKnownSort("bogus")).toBe(false);
      expect(plugin.isKnownSort("-bogus")).toBe(false);
    });
  });

  describe("storeSetting", () => {
    it("persists the value under its webui.mobile key", () => {
      plugin.storeSetting("sort", "-addtime");
      expect(saveCount).toBe(1);
      expect(storedOnServer["webui.mobile.sort"]).toBe("-addtime");
    });

    it("keeps settings saved elsewhere while this session was open", () => {
      // Snapshot from page load...
      window.theWebUI.settings["webui.speedlistdl"] = "10,100";
      // ...the desktop UI changed it in the meantime
      storedOnServer = { "webui.speedlistdl": "20,200" };

      plugin.storeSetting("theme", "dark");

      expect(storedOnServer["webui.speedlistdl"]).toBe("20,200");
      expect(storedOnServer["webui.mobile.theme"]).toBe("dark");
    });
  });

  describe("showSort", () => {
    beforeEach(() => {
      $("body").append(
        '<div id="sortPage"><select id="sortOption"></select>' +
        '<input type="radio" id="sort_asc"><input type="radio" id="sort_desc"></div>'
      );
      plugin.showPage = function () {};
    });
    afterEach(() => $("#sortPage").remove());

    it("offers the seedingtime fields only when that plugin is loaded", () => {
      plugin.sort = "name";
      plugin.seedingtimeLoaded = false;
      plugin.showSort();
      let values = $("#sortOption option").map((i, o) => o.value).get();
      expect(values).not.toContain("addtime");
      expect(values).toContain("ratio");

      plugin.seedingtimeLoaded = true;
      plugin.showSort();
      values = $("#sortOption option").map((i, o) => o.value).get();
      expect(values).toContain("addtime");
      expect(values).toContain("seedingtime");
    });

    it("preselects the current sort field and direction", () => {
      plugin.seedingtimeLoaded = true;
      plugin.sort = "-addtime";
      plugin.showSort();
      expect($("#sortOption").val()).toBe("addtime");
      expect($("#sort_desc").prop("checked")).toBe(true);
      expect($("#sort_asc").prop("checked")).toBe(false);

      plugin.sort = "size";
      plugin.showSort();
      expect($("#sortOption").val()).toBe("size");
      expect($("#sort_asc").prop("checked")).toBe(true);
    });
  });

  describe("storedFilters", () => {
    it("returns the stored filters when valid JSON is stored", () => {
      window.theWebUI.settings["webui.mobile.filters"] = JSON.stringify({
        status: ["downloading", "completed"],
        label: ["music", ""],
        tracker: ["tracker.example.com"],
      });
      expect(plugin.storedFilters()).toEqual({
        status: ["downloading", "completed"],
        label: ["music", ""],
        tracker: ["tracker.example.com"],
      });
    });

    it("filters out unknown status options and non-string values", () => {
      window.theWebUI.settings["webui.mobile.filters"] = JSON.stringify({
        status: ["downloading", "bogus_status", 123, "completed"],
        label: ["music", 456, null, ""],
        tracker: ["tracker.example.com", false],
      });
      expect(plugin.storedFilters()).toEqual({
        status: ["downloading", "completed"],
        label: ["music", ""],
        tracker: ["tracker.example.com"],
      });
    });

    it("deduplicates values in filter arrays", () => {
      window.theWebUI.settings["webui.mobile.filters"] = JSON.stringify({
        status: ["downloading", "downloading"],
        label: ["music", "music"],
        tracker: ["tracker.example.com", "tracker.example.com"],
      });
      expect(plugin.storedFilters()).toEqual({
        status: ["downloading"],
        label: ["music"],
        tracker: ["tracker.example.com"],
      });
    });

    it("falls back to default empty filters when nothing is stored", () => {
      expect(plugin.storedFilters()).toEqual({
        status: [],
        label: [],
        tracker: [],
      });
    });

    it("falls back to default empty filters for invalid JSON", () => {
      window.theWebUI.settings["webui.mobile.filters"] = "not-a-json-string";
      expect(plugin.storedFilters()).toEqual({
        status: [],
        label: [],
        tracker: [],
      });
    });

    it("falls back to default empty filters for non-string / non-object values", () => {
      window.theWebUI.settings["webui.mobile.filters"] = 42;
      expect(plugin.storedFilters()).toEqual({
        status: [],
        label: [],
        tracker: [],
      });

      window.theWebUI.settings["webui.mobile.filters"] = JSON.stringify(["status", "downloading"]);
      expect(plugin.storedFilters()).toEqual({
        status: [],
        label: [],
        tracker: [],
      });
    });
  });

  describe("filterDone", () => {
    beforeEach(() => {
      plugin.showList = jest.fn();
      plugin.filters = { status: [], label: [], tracker: [] };
      plugin.filtersSnapshot = null;
    });

    it("persists the filters when changed from snapshot", () => {
      plugin.filtersSnapshot = { status: [], label: [], tracker: [] };
      plugin.filters = { status: ["downloading"], label: ["music"], tracker: [] };

      plugin.filterDone();

      expect(saveCount).toBe(1);
      expect(JSON.parse(storedOnServer["webui.mobile.filters"])).toEqual({
        status: ["downloading"],
        label: ["music"],
        tracker: [],
      });
      expect(plugin.filtersSnapshot).toBeNull();
      expect(plugin.showList).toHaveBeenCalled();
    });

    it("does not re-save when filters are unchanged from snapshot", () => {
      plugin.filtersSnapshot = { status: ["downloading"], label: ["music"], tracker: [] };
      plugin.filters = { status: ["downloading"], label: ["music"], tracker: [] };

      plugin.filterDone();

      expect(saveCount).toBe(0);
      expect(plugin.filtersSnapshot).toBeNull();
      expect(plugin.showList).toHaveBeenCalled();
    });
  });

  describe("applyFilters with saved filters", () => {
    it("does not drop saved label and tracker filters before torrents are loaded", () => {
      plugin.torrents = null;
      plugin.labelList = [];
      plugin.trackerList = [];
      plugin.filters = { status: ["downloading"], label: ["music"], tracker: ["tr.example.com"] };

      plugin.applyFilters();

      expect(plugin.filters.label).toEqual(["music"]);
      expect(plugin.filters.tracker).toEqual(["tr.example.com"]);
    });

    it("prunes absent label and tracker filters once torrents are loaded", () => {
      plugin.torrents = { h1: { name: "test", size: 100 } };
      plugin.labelList = [{ name: "other", count: 1 }];
      plugin.trackerList = [{ name: "tr2.example.com", count: 1 }];
      plugin.filters = { status: ["downloading"], label: ["music"], tracker: ["tr.example.com"] };

      plugin.applyFilters();

      expect(plugin.filters.label).toEqual([]);
      expect(plugin.filters.tracker).toEqual([]);
    });
  });

  describe("init", () => {
    const label = 'tv "4k" \\ archive';
    let ajaxSpy;

    beforeEach(() => {
      window.theUILang.All = "All";
      window.theUILang.No_label = "No label";
      window.theUILang.Total = "Total";
      window.theUILang.Stopped = "Stopped";
      window.thePlugins = { get: () => null };
      window.cacheBust = (url) => url;
      // The page markup arrives over ajax; this spec only needs what init
      // restores before that request
      ajaxSpy = jest.spyOn($, "ajax").mockImplementation(() => {});
      $("body").append(
        '<div id="initFilterUi"><span id="filterText"></span><span id="filterCount"></span>' +
        '<span id="filterTotals"></span><div id="filterStatusList"></div>' +
        '<div id="filterLabelsList"></div><div id="filterTrackersList"></div></div>'
      );
      plugin.filters = { status: [], label: [], tracker: [] };
    });
    afterEach(() => {
      ajaxSpy.mockRestore();
      $(window).off("hashchange");
      $("#initFilterUi").remove();
    });

    it("restores the stored filters on load", () => {
      window.theWebUI.settings["webui.mobile.filters"] = JSON.stringify({
        status: ["stopped"],
        label: [label],
        tracker: [],
      });

      plugin.init();

      expect(plugin.filters).toEqual({ status: ["stopped"], label: [label], tracker: [] });

      plugin.torrents = null;
      plugin.labelList = [{ name: "other", count: 1 }, { name: label, count: 1 }];
      plugin.trackerList = [];
      plugin.updateFilterButton($());
      plugin.renderFilterPage();

      expect($("#filterText").text()).toBe("Stopped · " + label);
      expect($("#filterStatusList .active").text()).toBe("0Stopped");
      expect($("#filterLabelsList .active").text()).toBe("1" + label);
      expect($("#filterTrackersList .active").text()).toBe("0All");
    });
  });
});
