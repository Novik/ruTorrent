import { readFileSync } from "fs";

window.$ = require("jquery");

// js/objects.js builds a context menu entry from a two-element array. When the
// second element is a string it runs it through eval on click (js/objects.js:400);
// when it is a function it calls it (js/objects.js:403). This stands in for that
// switch, so the spec exercises the sink the menu really uses.
function activate(entry) {
  const action = entry[1];
  if (typeof action === "string") {
    // eslint-disable-next-line no-eval
    return eval(action);
  }
  return action();
}

// A label that ends the argument, runs a statement of its own and comments out
// the rest of the emitted call -- the shape a string action is vulnerable to.
const PAYLOAD = "x');window.__ran = 'YES';//";

function stubGlobals() {
  window.theUILang = new Proxy({}, { get: (_t, prop) => prop });
  window.theDialogManager = {
    show: () => {},
    hide: () => {},
    make: () => {},
    setHandler: () => {},
    setLayer: () => {},
  };
  window.thePlugins = { get: () => ({}), isInstalled: () => false };
  window.theContextMenu = { add: () => {}, get: () => null };
  window.injectCustomElementCSS = () => {};
  window.escapeHTML = (v) => String(v ?? "");
  window.$$ = () => null;
  // js/common.js:13
  window.$type = (obj) =>
    obj == undefined ? false : obj.constructor == Array ? "array" : typeof obj;
}

// The plugin body runs inside a closure holding `plugin`, the way the loader
// runs it. canChangeMenu has to answer true: it is what gates the entry.
function loadTracklabels() {
  const path = "../plugins/tracklabels/init.js";
  const code = readFileSync(path, { encoding: "utf-8" });
  const el = document.createElement("script");
  el.textContent =
    "(function(){ var plugin = new Proxy({ name: 'tracklabels', enabled: true," +
    " dialogId: 'dlg', canChangeMenu: () => true, canChangeColumns: () => false }," +
    " { has: () => true, get: (t, p) => (p in t ? t[p] : () => {})," +
    "   set: (t, p, v) => { t[p] = v; return true; } });" +
    "\ntry {\n" + code + "\n} catch(e) { window.__loadError = e; }\n})();";
  document.body.appendChild(el);
}

describe("tracklabels context menu entry", () => {
  let dialogCalls;

  beforeEach(() => {
    document.body.innerHTML = "";
    delete window.__ran;
    delete window.__loadError;
    dialogCalls = [];
    stubGlobals();
    window.theWebUI = {
      categoryList: {
        refreshPanel: { plabel: () => [] },
        contextMenuEntries: () => [],
        updatedStatisticEntry: () => [],
      },
      config: () => {},
      addTrackers: () => {},
      getTable: () => ({}),
      request: () => {},
    };
    loadTracklabels();
    expect(window.__loadError).toBeUndefined();
    // The entry's job is to open the dialog on this label and nothing else.
    window.theWebUI.showTracklabelsDialog = (lbl) => dialogCalls.push(lbl);
  });

  function editEntry(panelId, labelId) {
    const entries = theWebUI.categoryList.contextMenuEntries(panelId, labelId);
    expect(entries.length).toBe(1);
    return entries[0];
  }

  it("does not run a tracker name as code when the entry is activated", () => {
    activate(editEntry("ptrackers", "i" + PAYLOAD));
    expect(window.__ran).toBeUndefined();
    expect(dialogCalls).toEqual([PAYLOAD]);
  });

  it("does not run a torrent label as code when the entry is activated", () => {
    activate(editEntry("plabel", "clabel__" + PAYLOAD));
    expect(window.__ran).toBeUndefined();
    expect(dialogCalls).toEqual([PAYLOAD]);
  });

  it("passes a label through whole, whatever it contains", () => {
    const awkward = "a'b\"c\\d\ne</script>f";
    activate(editEntry("plabel", "clabel__" + awkward));
    expect(dialogCalls).toEqual([awkward]);
  });

  it("still opens the dialog on an ordinary label", () => {
    activate(editEntry("ptrackers", "iexample.com"));
    expect(dialogCalls).toEqual(["example.com"]);
    activate(editEntry("plabel", "plabel_other"));
    expect(dialogCalls).toEqual(["example.com", "nlb"]);
  });

  it("adds no entry for the all-labels row", () => {
    expect(theWebUI.categoryList.contextMenuEntries("plabel", "plabel_all")).toEqual([]);
    expect(theWebUI.categoryList.contextMenuEntries("ptrackers", "ptrackers_all")).toEqual([]);
  });
});
