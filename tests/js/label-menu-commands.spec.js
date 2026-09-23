import { readFileSync } from "fs";

window.$ = require("jquery");

// A panel label's context menu is built by the category list and handed to
// theContextMenu, which runs an entry's command when the entry is picked. The
// plugins that own a panel wrap categoryList.contextMenuEntries to contribute
// their own entries, so this spec drives the whole path -- the plugin's
// wrapper, theWebUI.labelContextMenu, the real menu builder -- and picks the
// entries, to check that each one does what it is named after.

function run(code) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

function load(src) {
  run(readFileSync(src, { encoding: "utf-8" }));
}

// lang/en.js before common.js: common.js reads theUILang at load time.
// common.js before objects.js: the menu builder calls $type() on every entry.
for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js", "../js/stable.js"]) {
  load(src);
}

// What js/webui.js expects to find already defined.
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

// What the two plugins reach for at load time and this spec does not exercise.
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

// The plugin loader runs a plugin's init.js inside a closure holding `plugin`.
// The stub answers any method the plugin calls on itself with a no-op, except
// the permission checks the menu builders consult.
window.__makePlugin = (path) =>
  new Proxy(
    {
      path,
      name: path.replace(/^\.\.\/plugins\/|\/$/g, ""),
      enabled: true,
      allStuffLoaded: true,
      canChangeMenu: () => true,
      canChangeOptions: () => false,
      loadLang: () => {},
      loadMainCSS: () => {},
    },
    {
      has: () => true,
      get: (target, prop) => (prop in target ? target[prop] : () => {}),
    }
  );

function loadPlugin(path) {
  // The loader fetches a plugin's language file before its init.js; the menu
  // entries are named from it.
  load(path + "lang/en.js");
  const code = readFileSync(path + "init.js", { encoding: "utf-8" });
  run(
    "(function(){ var plugin = window.__makePlugin('" +
      path +
      "');\ntry {\n" +
      code +
      "\n} catch(e) { window.__pluginLoadError = e; }\n})();"
  );
}

// A panel label's selection, as the category list keeps it.
let selected = {};

function fakeTable() {
  return {
    clearSelection: () => {},
    fillSelection: () => {},
    // No torrent row is selected, so labelContextMenu builds the label menu
    // on its own rather than appending it to a torrent menu.
    getFirstSelected: () => "",
    clearRows: () => {},
    updateRows: () => {},
    setRowById: () => {},
    removeRow: () => {},
    setLazy: () => {},
    refreshSelection: () => {},
    scrollTo: () => {},
    resize: () => {},
    hideRow: () => {},
    unhideRow: () => {},
    refreshRows: () => {},
    getFirstVisible: () => "",
    setBackground: () => {},
    rowSel: {},
    selCount: 0,
  };
}

function installCategoryList() {
  theWebUI.categoryList = {
    theUILang: window.theUILang,
    selection: {
      ids: (panelId) => selected[panelId] ?? [],
      count: (panelId) => (selected[panelId] ?? []).length,
      select: () => {},
    },
    switchLabel: () => {},
    selectionActive: () => false,
    isLabelIdSelected: () => false,
    panelLabelAttribs: { prss: new Map(), psearch: new Map() },
    refreshPanel: { prss: () => [], psearch: () => [] },
    statistic: { empty: () => ({ scan: () => {} }), viewIdToIndex: () => 0 },
    syncAfterScan: () => {},
    refresh: () => {},
    refreshAndSyncPanel: () => {},
    syncFn: () => {},
    // The base implementation each plugin wraps.
    contextMenuEntries: () => [],
    removeActiveTextSearches: () => {},
    removeAllTextSearches: () => {},
  };
}

// Pick the entry by the text the user reads, the way a user picks it.
function entryNamed(label) {
  return theContextMenu.obj
    .find("a.menu-cmd")
    .filter((ndx, a) => $(a).text() === label);
}

function openLabelMenu(panelId, labelId) {
  theWebUI.labelContextMenu({
    panelId,
    labelId,
    rightClick: true,
    metaKey: false,
    shiftKey: false,
    clientX: 10,
    clientY: 10,
  });
}

function pick(label) {
  const entry = entryNamed(label);
  expect(entry.length).toBe(1);
  entry.trigger("click");
}

// An entry with no command is drawn the way setCommand marks one: present,
// named, and doing nothing when it is picked.
function isInert(label) {
  const entry = entryNamed(label);
  expect(entry.length).toBe(1);
  return entry.hasClass("dis");
}

beforeEach(() => {
  selected = {};
  delete window.__pluginLoadError;
  installCategoryList();
  theWebUI.getTable = fakeTable;
  theWebUI.clearDetails = () => {};
  theWebUI.createMenu = () => {};
  theContextMenu.init();
  theContextMenu.clear();
  jest.spyOn(theDialogManager, "toggle").mockImplementation(() => {});
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe("the feed panel menu the rss plugin builds", () => {
  beforeEach(() => {
    Object.assign(theWebUI, {
      rssLabels: { feed1: { enabled: 1, name: "a feed" } },
      rssItems: {},
      rssGroups: {},
      settings: {},
      request: () => {},
      requestWithTimeout: () => {},
      save: () => {},
      setDisableControls: () => {},
      resizeTop: () => {},
      resize: () => {},
    });
    loadPlugin("../plugins/rss/");
    expect(window.__pluginLoadError).toBeUndefined();
  });

  // Right-clicking the panel with no feed selected offers the four entries
  // that are not about one feed.
  describe("with no feed selected", () => {
    beforeEach(() => {
      selected = { prss: [] };
      openLabelMenu("prss", null);
    });

    it("clears the feed history", () => {
      const ran = jest
        .spyOn(theWebUI, "RSSClearHistory")
        .mockImplementation(() => {});
      pick(theUILang.rssMenuClearHistory);
      expect(ran).toHaveBeenCalled();
    });

    it("opens the add feed dialog", () => {
      pick(theUILang.addRSS);
      expect(theDialogManager.toggle).toHaveBeenCalledWith("dlgAddRSS");
    });

    it("opens the add group dialog", () => {
      const ran = jest
        .spyOn(theWebUI, "RSSAddGroup")
        .mockImplementation(() => {});
      pick(theUILang.addRSSGroup);
      expect(ran).toHaveBeenCalled();
    });

    it("opens the feed manager", () => {
      const ran = jest
        .spyOn(theWebUI, "RSSManager")
        .mockImplementation(() => {});
      pick(theUILang.rssMenuManager);
      expect(ran).toHaveBeenCalled();
    });
  });

  // With a feed selected the same four entries are there, above the entries
  // that act on that feed.
  describe("with a feed selected", () => {
    beforeEach(() => {
      selected = { prss: ["feed1"] };
      openLabelMenu("prss", "feed1");
    });

    it("still clears the feed history", () => {
      const ran = jest
        .spyOn(theWebUI, "RSSClearHistory")
        .mockImplementation(() => {});
      pick(theUILang.rssMenuClearHistory);
      expect(ran).toHaveBeenCalled();
    });

    it("updates the selected feed", () => {
      const ran = jest
        .spyOn(theWebUI, "RSSRefresh")
        .mockImplementation(() => {});
      pick(theUILang.rssMenuRefresh);
      expect(ran).toHaveBeenCalled();
    });

    it("disables, edits and deletes the selected feed", () => {
      for (const [label, method] of [
        [theUILang.rssMenuDisable, "RSSToggleStatus"],
        [theUILang.rssMenuEdit, "RSSEdit"],
        [theUILang.rssMenuDelete, "RSSDelete"],
      ]) {
        const ran = jest.spyOn(theWebUI, method).mockImplementation(() => {});
        pick(label);
        expect(ran).toHaveBeenCalled();
      }
    });
  });

  // A disabled feed offers enable instead of disable, and its update entry
  // carries no command: there is nothing to update until it is enabled.
  describe("with a disabled feed selected", () => {
    beforeEach(() => {
      theWebUI.rssLabels = { feed1: { enabled: 0, name: "a feed" } };
      selected = { prss: ["feed1"] };
      openLabelMenu("prss", "feed1");
    });

    it("enables the selected feed", () => {
      const ran = jest
        .spyOn(theWebUI, "RSSToggleStatus")
        .mockImplementation(() => {});
      pick(theUILang.rssMenuEnable);
      expect(ran).toHaveBeenCalled();
    });

    it("draws the update entry with nothing to run", () => {
      expect(isInert(theUILang.rssMenuRefresh)).toBe(true);
    });
  });

  // A group is told apart from a feed by the id being one of rssGroups, and
  // offers the group entries rather than the feed ones.
  describe("with a group selected", () => {
    beforeEach(() => {
      theWebUI.rssGroups = { group1: { enabled: 1, cnt: 2, name: "a group" } };
      selected = { prss: ["group1"] };
      openLabelMenu("prss", "group1");
    });

    it("disables and updates the selected group", () => {
      const status = jest
        .spyOn(theWebUI, "RSSGroupSetStatus")
        .mockImplementation(() => {});
      pick(theUILang.rssMenuGroupDisable);
      expect(status).toHaveBeenCalledWith(0);
      const refresh = jest
        .spyOn(theWebUI, "RSSGroupRefresh")
        .mockImplementation(() => {});
      pick(theUILang.rssMenuGroupRefresh);
      expect(refresh).toHaveBeenCalled();
    });

    it("edits, deletes and empties the selected group", () => {
      for (const [label, method] of [
        [theUILang.rssMenuGroupEdit, "RSSEditGroup"],
        [theUILang.rssMenuGroupDelete, "RSSGroupDelete"],
        [theUILang.rssMenuGroupContentsDelete, "RSSGroupDeleteContents"],
      ]) {
        const ran = jest.spyOn(theWebUI, method).mockImplementation(() => {});
        pick(label);
        expect(ran).toHaveBeenCalled();
      }
    });

    it("offers the feed entries to neither a group nor the panel", () => {
      expect(entryNamed(theUILang.rssMenuDisable).length).toBe(0);
      expect(entryNamed(theUILang.rssMenuEdit).length).toBe(0);
    });
  });

  // A disabled group can be enabled only when it holds something; an empty
  // one is drawn with the entry present and no command.
  describe("with a disabled group selected", () => {
    it("enables a group that holds feeds", () => {
      theWebUI.rssGroups = { group1: { enabled: 0, cnt: 2, name: "a group" } };
      selected = { prss: ["group1"] };
      openLabelMenu("prss", "group1");
      const ran = jest
        .spyOn(theWebUI, "RSSGroupSetStatus")
        .mockImplementation(() => {});
      pick(theUILang.rssMenuGroupEnable);
      expect(ran).toHaveBeenCalledWith(1);
    });

    it("draws an empty group's enable entry with nothing to run", () => {
      theWebUI.rssGroups = { group1: { enabled: 0, cnt: 0, name: "a group" } };
      selected = { prss: ["group1"] };
      openLabelMenu("prss", "group1");
      expect(isInert(theUILang.rssMenuGroupEnable)).toBe(true);
      expect(isInert(theUILang.rssMenuGroupRefresh)).toBe(true);
    });
  });
});

describe("the saved search menu the extsearch plugin builds", () => {
  beforeEach(() => {
    Object.assign(theWebUI, {
      settings: {},
      request: () => {},
      save: () => {},
    });
    window.theSearchEngines = {
      sites: {},
      current: -1,
      set: () => {},
      show: () => {},
      checkForIncorrectCurrent: () => {},
    };
    loadPlugin("../plugins/extsearch/");
    expect(window.__pluginLoadError).toBeUndefined();
    selected = { psearch: ["extteg_1"] };
    openLabelMenu("psearch", "extteg_1");
  });

  it("refreshes the saved search", () => {
    const ran = jest
      .spyOn(theWebUI, "tegRefresh")
      .mockImplementation(() => {});
    pick(theUILang.tegRefresh);
    expect(ran).toHaveBeenCalled();
  });

  it("deletes the saved search", () => {
    const ran = jest
      .spyOn(theWebUI, "extTegDelete")
      .mockImplementation(() => {});
    pick(theUILang.tegMenuDelete);
    expect(ran).toHaveBeenCalled();
  });
});
