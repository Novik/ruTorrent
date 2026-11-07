import { readFileSync } from "fs";

window.$ = require("jquery");

// The feed picker is a <select> the rss plugin fills with one <option> per feed
// and per feed group. The label of a feed defaults to the channel title of the
// remote feed (plugins/rss/rss.php), so these option labels are remote input.
//
// jQuery's .append() runs any <script> in the html it is handed, so a script
// element in a feed title executes the moment the picker is filled. The other
// payload only has to become an element to prove the title reached the parser.
const SCRIPT_PAYLOAD = `<script>window.__xss = "RAN"</` + `script>`;
const TAG_PAYLOAD = `<img src=x onerror="window.__xss = 'RAN'">`;

function stubGlobals() {
  window.theUILang = new Proxy({}, { get: (_t, prop) => prop });
  window.theDialogManager = {
    show: () => {},
    hide: () => {},
    setLayer: () => {},
    init: () => {},
    bindEvent: () => {},
  };
  window.thePlugins = {
    get: () => ({ langLoaded: () => {}, allStuffLoaded: true }),
    isInstalled: () => false,
  };
  window.injectCustomElementCSS = () => {};
  window.injectCustomElementAttribute = () => {};
  window.injectScript = () => {};
  window.rTorrentStub = function () {};
  window.dxSTable = function () {};
  window.Timer = function () {};
  window.theConverter = { bytes: () => "", date: () => "", time: () => "" };
  window.theTabs = { add: () => {}, show: () => {}, resize: () => {} };
  window.theContextMenu = { add: () => {}, show: () => {}, clear: () => {} };
  window.iv = (v) => parseInt(v, 10) || 0;
  window.escapeHTML = (v) => String(v ?? "");
  window.TYPE_STRING = "string";
  window.TYPE_NUMBER = "number";
  window.ALIGN_RIGHT = "right";
}

// Both plugins are classic scripts that run inside a closure holding `plugin`,
// exactly as the plugin loader runs them. The stub answers any method the
// plugin calls on itself with a no-op, so this spec does not have to track the
// whole plugin API; only the option building is under test.
window.__makePluginStub = (path) =>
  new Proxy(
    { path, name: "p", enabled: true },
    {
      has: () => true,
      get: (target, prop) => (prop in target ? target[prop] : () => {}),
    }
  );

function runPluginSource(path, extra) {
  const code = readFileSync(path, { encoding: "utf-8" });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function(){ var plugin = window.__makePluginStub('" +
    path.replace(/init\.js$/, "") +
    "'); " +
    (extra || "") +
    "\ntry {\n" +
    code +
    "\n} catch(e) { window.__pluginLoadError = e; }\n})();";
  document.body.appendChild(scriptEl);
}

function makeCategoryList() {
  return {
    panelLabelAttribs: { prss: new Map() },
    refreshPanel: {},
    isLabelIdSelected: () => false,
    switchLabel: () => {},
    contextMenuEntries: () => [],
    syncAfterScan: () => {},
    statistic: { empty: () => ({ scan: () => {} }) },
  };
}

describe("feed picker options", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    delete window.__xss;
    delete window.__pluginLoadError;
    stubGlobals();
    window.theWebUI = {
      categoryList: makeCategoryList(),
      settings: {},
      rssGroups: {},
      rssLabels: {},
      maxThrottle: 0,
      maxRatio: 0,
      isCorrectThrottle: () => false,
      isCorrectRatio: () => false,
      getTable: () => ({
        clearRows: () => {},
        updateRows: () => {},
        setRowById: () => {},
        removeRow: () => {},
        setLazy: () => {},
      }),
      setDisableControls: () => {},
      resizeTop: () => {},
      resize: () => {},
      request: () => {},
      save: () => {},
    };
  });

  describe("rss plugin", () => {
    function loadFilters(labels) {
      runPluginSource("../plugins/rss/init.js");
      document.body.insertAdjacentHTML(
        "beforeend",
        `<ul id="fltlist"></ul><select id="FLT_rss"></select>`
      );
      Object.assign(theWebUI, labels);
      theWebUI.loadFilters([]);
      return $("#FLT_rss");
    }

    it("loads", () => {
      runPluginSource("../plugins/rss/init.js");
      expect(window.__pluginLoadError).toBeUndefined();
      expect(typeof theWebUI.loadFilters).toBe("function");
    });

    it("does not run a script a feed title carries", () => {
      const sel = loadFilters({ rssLabels: { h1: { name: SCRIPT_PAYLOAD } } });
      expect(window.__xss).toBeUndefined();
      expect(sel.find("script").length).toBe(0);
    });

    it("does not run a script a feed group title carries", () => {
      const sel = loadFilters({ rssGroups: { g1: { name: SCRIPT_PAYLOAD } } });
      expect(window.__xss).toBeUndefined();
      expect(sel.find("script").length).toBe(0);
    });

    it("does not let a feed title become an element", () => {
      const sel = loadFilters({ rssLabels: { h1: { name: TAG_PAYLOAD } } });
      expect(sel.find("img").length).toBe(0);
      expect(sel.find("option").eq(1).children().length).toBe(0);
    });

    it("shows the feed title as its own text, punctuation included", () => {
      const sel = loadFilters({
        rssLabels: { h1: { name: `Bob's "Feed" <b>&raquo; 2024</b>` } },
      });
      const opt = sel.find("option[value='h1']");
      expect(opt.length).toBe(1);
      expect(opt.text()).toBe(`Bob's "Feed" <b>&raquo; 2024</b>`);
    });

    it("keeps the option value and the all-feeds entry", () => {
      const sel = loadFilters({
        rssGroups: { g1: { name: "Group" } },
        rssLabels: { h1: { name: "Feed" } },
      });
      expect(sel.find("option").length).toBe(3);
      expect(sel.find("option").eq(0).val()).toBe("");
      expect(sel.find("option").eq(1).val()).toBe("g1");
      expect(sel.find("option").eq(1).text()).toBe("Group");
      expect(sel.find("option").eq(2).val()).toBe("h1");
      expect(sel.find("option").eq(2).text()).toBe("Feed");
    });
  });

  describe("rssurlrewrite plugin", () => {
    function loadRules(labels) {
      runPluginSource("../plugins/rssurlrewrite/init.js");
      document.body.insertAdjacentHTML(
        "beforeend",
        `<ul id="rlslist"></ul><select id="RLS_rss"></select>`
      );
      Object.assign(theWebUI, labels);
      theWebUI.loadRules([]);
      return $("#RLS_rss");
    }

    it("loads", () => {
      runPluginSource("../plugins/rssurlrewrite/init.js");
      expect(window.__pluginLoadError).toBeUndefined();
      expect(typeof theWebUI.loadRules).toBe("function");
    });

    it("does not run a script a feed title carries", () => {
      const sel = loadRules({ rssLabels: { h1: { name: SCRIPT_PAYLOAD } } });
      expect(window.__xss).toBeUndefined();
      expect(sel.find("script").length).toBe(0);
    });

    it("does not let a feed title become an element", () => {
      const sel = loadRules({ rssLabels: { h1: { name: TAG_PAYLOAD } } });
      expect(sel.find("img").length).toBe(0);
    });

    it("shows the feed title as its own text", () => {
      const sel = loadRules({ rssLabels: { h1: { name: `Bob's & <Feed>` } } });
      const opt = sel.find("option[value='h1']");
      expect(opt.length).toBe(1);
      expect(opt.text()).toBe(`Bob's & <Feed>`);
    });
  });
});
