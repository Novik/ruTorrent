import { readFileSync } from "fs";
import * as bbcodeModule from "../../../plugins/rss/bbcode";

window.$ = require("jquery");
window.GetActiveLanguage = function () {
  return "en";
};
window.rsstestingbbcodeModule = bbcodeModule;

// The category panel is not on the path this exercises; it only has to be
// there for plugins/rss/init.js to attach itself to at load time.
const categoryList = {
  selection: { ids: () => [] },
  panelLabelAttribs: { prss: new Map() },
  refreshPanel: {},
  switchLabel() {},
  contextMenuEntries: () => [],
  isLabelIdSelected: () => false,
  refresh() {},
  syncWithPrunedSelection() {},
  refreshAndSyncPanel() {},
  syncFn() {},
};

window.theWebUI = {
  version: "0.0.0",
  settings: { "webui.needmessage": true },
  showFlags: 0xffff,
  systemInfo: {
    rTorrent: { apiVersion: 10, iVersion: 0x908, started: true },
  },
  categoryList,
  resizeTop: () => {},
};

document.body.append(
  ...["category-list", "panel-label"].map((elementTag) =>
    Object.assign(document.createElement("template"), {
      id: `${elementTag}-template`,
      innerHTML: '<link rel="stylesheet" />',
    })
  )
);

function run(code) {
  const scriptEl = document.createElement("script");
  scriptEl.setAttribute("type", "text/javascript");
  scriptEl.textContent = code;
  document.head.appendChild(scriptEl);
}

function load(src) {
  run(readFileSync(src, { encoding: "utf-8" }));
}

for (const src of [
  "../js/sanitize.js",
  "../js/sanitize.config.js",
  "../js/custom-elements.js",
  "../js/category-list-elements.js",
  "../lang/en.js",
  "../js/common.js",
  "../js/objects.js",
  "../js/content.js",
  "../js/rtorrent.js",
  "../js/plugins.js",
]) {
  load(src);
}

// Both plugins are registered before their language files run, because each
// language file ends by telling its plugin the language arrived. The init
// files come last, the way plugins.js loads them.
const PLUGINS = ["rss", "rssurlrewrite"];
run(
  "window.__plugins = {" +
    PLUGINS.map(
      (name) =>
        `${name}: Object.assign(new rPlugin('${name}', 4.0, 'a', 'b', 'c', 'd'), {path: '../plugins/${name}/'})`
    ).join(",") +
    "};"
);
for (const name of PLUGINS) {
  load(`../plugins/${name}/lang/en.js`);
}
for (const name of PLUGINS) {
  let code = readFileSync(`../plugins/${name}/init.js`, { encoding: "utf-8" });
  if (name === "rss") {
    // jsdom runs no dynamic import, so the bbcode module is handed over.
    code = code.replace(
      "var bbcode = null;",
      "var bbcode = window.rsstestingbbcodeModule;"
    );
  }
  run(`(function () { var plugin = window.__plugins.${name}; ${code} })();`);
}
correctContent();

// An address a feed may carry and the panel will open: an ordinary scheme and
// an ordinary host, so neither the feed reader nor the openable-address test
// drops the item, with the quote in the query.
const INJECTED_HREF =
  "http://example.org/get?id=1'-(window.__menuCommandRan=1)-'";

function rssTableStub() {
  const rows = {};
  return {
    rows,
    rowSel: {},
    selCount: 0,
    ids: [],
    getValues: () => [],
    getIcon: () => "",
    setRowById(values, id) {
      rows[id] = values;
      if (!(id in this.rowSel)) this.rowSel[id] = false;
    },
    removeRow(id) {
      delete rows[id];
      delete this.rowSel[id];
    },
  };
}

describe("the URL info entry of an rss item", () => {
  let table;

  beforeEach(() => {
    delete window.__menuCommandRan;
    table = rssTableStub();
    theWebUI.torrents = {};
    theWebUI.rssItems = {};
    theWebUI.rssLabels = {};
    theWebUI.getTable = () => table;
    theContextMenu.init();
    theContextMenu.clear();
  });

  function ingest(href) {
    theWebUI.addRSSItems({
      list: [
        {
          hash: "0123456789abcdef",
          label: "A feed",
          enabled: 1,
          url: "http://example.org/feed.xml",
          items: [
            {
              href,
              guid: href,
              title: "An item",
              time: 0,
              hash: "",
              errcount: 0,
            },
          ],
        },
      ],
      groups: {},
      errors: [],
    });
  }

  function selectAndOpenMenu(href) {
    table.rowSel[href] = true;
    table.selCount = 1;
    theWebUI.createMenu({ which: 3 }, href);
  }

  function urlInfoEntry() {
    return theContextMenu.obj
      .find("a.menu-cmd")
      .filter((ndx, a) => $(a).text() === theUILang.rssURLInfo);
  }

  it("keeps the whole address as the item's id", () => {
    ingest(INJECTED_HREF);

    expect(Object.keys(theWebUI.rssItems)).toEqual([INJECTED_HREF]);
    expect(theWebUI.rssItems[INJECTED_HREF].href).toBe(INJECTED_HREF);
  });

  it("runs nothing the address carries when the entry is picked", () => {
    ingest(INJECTED_HREF);
    selectAndOpenMenu(INJECTED_HREF);

    const entry = urlInfoEntry();
    expect(entry.length).toBe(1);
    entry.trigger("click");

    expect(window.__menuCommandRan).toBeUndefined();
  });

  it("reports the address the item carries", () => {
    const seen = [];
    theWebUI.showURLInfo = (id) => seen.push(id);

    ingest(INJECTED_HREF);
    selectAndOpenMenu(INJECTED_HREF);
    urlInfoEntry().trigger("click");

    expect(seen).toEqual([INJECTED_HREF]);
  });
});
