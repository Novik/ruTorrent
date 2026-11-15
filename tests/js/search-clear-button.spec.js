import { readFileSync } from "fs";

// The toolbar search field carries a button that empties it. The button is
// shown only while the field holds text, which includes text that code puts
// in or takes out without an input event: Esc, the button itself, and
// extsearch filling the field again when one of its results is selected.

window.$ = window.jQuery = require("jquery");
window.$$ = (id) => document.getElementById(id);

function loadWebUI() {
  window.theUILang = new Proxy({}, { get: (_target, prop) => prop });
  window.theFormatter = {};
  window.TYPE_STRING = "string";
  window.TYPE_NUMBER = "number";
  window.TYPE_PROGRESS = "progress";
  window.TYPE_PEERS = "peers";
  window.TYPE_SEEDS = "seeds";
  window.ALIGN_RIGHT = "right";
  window.dxSTable = function () {};
  window.rSpeedGraph = function () {};
  window.Timer = function () {};
  window.dStatus = { started: 1, paused: 2, checking: 4, hashing: 8, error: 16 };

  let code = readFileSync("../js/webui.js", { encoding: "utf-8" });
  code = code.replace(
    /\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/,
    ""
  );
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.head.appendChild(scriptEl);
}

// The toolbar's right-hand group, exactly as index.html ships it.
function loadToolbarSearch() {
  const html = readFileSync("../index.html", { encoding: "utf-8" });
  const doc = new DOMParser().parseFromString(html, "text/html");
  document.body.innerHTML = doc.getElementById("rc").outerHTML;
}

// theWebUI.tegRefresh as plugins/extsearch/init.js ships it.
function loadTegRefresh() {
  const src = readFileSync("../plugins/extsearch/init.js", { encoding: "utf-8" });
  const start = src.indexOf("theWebUI.tegRefresh = function");
  let i = src.indexOf("{", start);
  for (let depth = 0; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}" && --depth === 0) break;
  }
  return src.slice(start, i + 1);
}

const field = () => $$("query");
const button = () => $$("query_clear");

// What the field's input listener runs.
function type(text) {
  field().value = text;
  theWebUI.updateQuickSearch();
}

describe("toolbar search clear button", () => {
  beforeEach(() => {
    document.head.innerHTML = "";
    loadWebUI();
    loadToolbarSearch();
    window.theSearchEngines = { current: -1 };
    theWebUI.categoryList = { setQuickSearch: jest.fn() };
  });

  it("is a labelled button next to the field, hidden while it is empty", () => {
    expect(button()).not.toBeNull();
    expect(button().tagName).toBe("BUTTON");
    expect(button().type).toBe("button");
    expect(button().previousElementSibling).toBe(field());
    expect(button().getAttribute("uilangtitle")).toBe("ClearButton");
    expect(readFileSync("../lang/en.js", "utf-8")).toMatch(/^\s*ClearButton\s*:/m);
    expect(button().hidden).toBe(true);
  });

  it("appears while the field holds text and goes when it is emptied", () => {
    type("ubuntu");
    expect(button().hidden).toBe(false);
    type("");
    expect(button().hidden).toBe(true);
  });

  it("appears for text typed while an external search engine is selected", () => {
    theSearchEngines.current = 0;
    type("ubuntu");
    expect(button().hidden).toBe(false);
  });

  it("empties the field, resets the list filter and returns focus when clicked", () => {
    type("ubuntu");
    theWebUI.categoryList.setQuickSearch.mockClear();
    button().focus();

    button().click();

    expect(field().value).toBe("");
    expect(theWebUI.categoryList.setQuickSearch).toHaveBeenLastCalledWith("");
    expect(button().hidden).toBe(true);
    expect(document.activeElement).toBe(field());
  });

  it("goes when the field is emptied by clearSearch, as Esc does", () => {
    type("ubuntu");
    theWebUI.clearSearch();
    expect(field().value).toBe("");
    expect(button().hidden).toBe(true);
  });

  it("hides the clear button while a new external search is pending", () => {
    const plugin = { enabled: true };
    theSearchEngines.current = "all";
    theSearchEngines.checkForIncorrectCurrent = jest.fn();
    theWebUI.requestWithoutTimeout = jest.fn();
    type("ubuntu");
    const src = readFileSync("../plugins/extsearch/init.js", "utf8");
    const start = src.indexOf("theSearchEngines.run = function");
    const end = src.indexOf("\nrTorrentStub.prototype.extsearch", start);
    new Function("theSearchEngines", "theWebUI", "plugin", "$", src.slice(start, end))(theSearchEngines, theWebUI, plugin, window.$);
    theSearchEngines.run();
    expect(field().readOnly).toBe(true);
    expect(button().hidden).toBe(true);
    expect(theWebUI.requestWithoutTimeout).toHaveBeenCalledTimes(1);
  });

  it("does not clear the query while an external search is pending", () => {
    type("ubuntu");
    field().readOnly = true;
    theWebUI.updateSearchClear();
    expect(button().hidden).toBe(true);
    theWebUI.clearSearch();
    expect(field().value).toBe("ubuntu");
  });

  it("keeps a refreshed query until the delayed external result is assigned", () => {
    const plugin = { tegs: { teg_1: { val: "all/all: ubuntu", what: "ubuntu", eng: "all", cat: "all", data: [] } } };
    const catlist = { switchLabel: jest.fn(), refresh: jest.fn() };
    theSearchEngines.getEngName = () => "all";
    window.$type = () => false;
    theWebUI.activeExtTegId = () => "teg_1";
    theWebUI.requestWithoutTimeout = jest.fn();
    const src = readFileSync("../plugins/extsearch/init.js", "utf8");
    const start = src.indexOf("theWebUI.setExtSearchTag = function");
    const end = src.indexOf("\nplugin.getTegByRowId", start);
    new Function("theWebUI", "plugin", "catlist", "$", src.slice(start, end))(theWebUI, plugin, catlist, window.$);
    new Function("theWebUI", "plugin", "$", loadTegRefresh())(theWebUI, plugin, window.$);
    theWebUI.tegRefresh();
    button().click();
    theWebUI.clearSearch();
    const callback = theWebUI.requestWithoutTimeout.mock.calls[0][1];
    callback[0].call(callback[1], { eng: "all", cat: "all", data: ["result"] });
    expect(plugin.tegs.teg_1.what).toBe("ubuntu");
    expect(plugin.tegs.teg_1.data).toEqual(["result"]);
    expect(field().readOnly).toBe(false);
    expect(button().hidden).toBe(false);
  });

  it("stays hidden while extsearch refreshes a readonly query", () => {
    const plugin = { tegs: { teg_1: { what: "ubuntu", eng: "all", cat: "all" } } };
    theWebUI.activeExtTegId = () => "teg_1";
    theWebUI.requestWithoutTimeout = jest.fn();
    new Function("theWebUI", "plugin", "$", loadTegRefresh())(theWebUI, plugin, window.$);

    theWebUI.tegRefresh();

    expect(field().value).toBe("ubuntu");
    expect(button().hidden).toBe(true);
  });
});
