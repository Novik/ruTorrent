import { readFileSync } from "fs";

window.$ = window.jQuery = require("jquery");
const binary = { name: "raw\\xFF", labelKey: "raw%FF" };
const binaryId = "blabel__raw%FF";
const hash = "A".repeat(40);
const otherHash = "B".repeat(40);

function load(file, prefix = "", suffix = "") {
  const script = document.createElement("script");
  script.textContent = prefix + readFileSync(`../${file}`, "utf8") + suffix;
  document.body.appendChild(script);
}

beforeAll(() => {
  load("lang/en.js");
  load("js/common.js");
});

function labelTree() {
  return { torrentLabels: new Map([
    [binaryId, { value: binary, name: binary.name, path: [binary.name], panelId: binaryId }],
    [binary.name, { value: binary.name, name: binary.name, path: [binary.name], panelId: "clabel__" + binary.name }],
  ]) };
}

function torrent(value, overrides = {}) {
  return Object.assign({ hash, name: "Release", size: 100, label: typeof value === "object" ? value.name : value,
    label_value: value, state: 1, done: 500, ul: 0, dl: 0, eta: -1, ratio: 0, msg: "", partially_done: 0 }, overrides);
}

test("desktop add selects and submits binary labels separately from their literal display spelling", () => {
  window.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} };
  window.theWebUI = { settings: {}, version: "test", categoryList: { torrentLabelTree: labelTree() },
    save() {}, upRateMenu() {}, downRateMenu() {} };
  window.theOptionsWindow = { init() {} };
  document.body.innerHTML = '<div id="dialog-container"></div>';
  load("js/objects.js"); load("js/content.js");
  makeContent();
  theDialogManager.items.tadd.beforeShow();
  expect($("#tadd_label_select option").slice(2).map((_, el) => $(el).text()).get()).toEqual([binary.name, binary.name]);
  $("#tadd_label_select").prop("selectedIndex", 2).trigger("change");
  $("#addtorrenturl").triggerHandler("submit");
  expect($("#addtorrenturl").attr("action")).toContain("label=raw%FF");
  $("#tadd_label_select").prop("selectedIndex", 3).trigger("change");
  $("#addtorrenturl").triggerHandler("submit");
  expect($("#addtorrenturl").attr("action")).toContain("label=raw%5CxFF");
  $("#tadd_label_select").prop("selectedIndex", 1).trigger("change");
  $("#tadd_label").val("raw%FF").trigger("input");
  $("#addtorrenturl").triggerHandler("submit");
  expect($("#addtorrenturl").attr("action")).toContain("label=raw%25FF");
  $("#tadd-return-select").trigger("click");
  $("#addtorrenturl").triggerHandler("submit");
  expect($("#addtorrenturl").attr("action")).not.toContain("label=");
});

describe("mobile label identity", () => {
  let mobile;
  beforeEach(() => {
    document.body.innerHTML = '<div id="torrentDetails"><table><tr id="label"><td></td><td></td></tr></table></div>'
      + '<div id="torrentsList"><div id="list"><table><tbody></tbody></table></div></div>'
      + '<div id="filterStatusList"></div><div id="filterLabelsList"></div><div id="filterTrackersList"></div><span id="filterText"></span>'
      + '<select id="tadd_label_select"></select><input id="tadd_label"><input id="dir_edit"><input id="url">';
    window.theWebUI = { settings: {}, categoryList: { torrentLabelTree: labelTree(), panelLabelAttribs: { plabel: new Map([
      [binaryId, { count: 1 }], ["clabel__" + binary.name, { count: 1 }],
    ]) } }, getStatusIcon: () => ["", "Stopped"], getTrackerName: () => "", requestWithoutTimeout: (_q, cb) => cb({}), save: jest.fn() };
    window.thePlugins = { get: () => null };
    window.theConverter = { bytes: String, speed: String, round: String, time: String };
    window.dStatus = { started: 1, paused: 2, checking: 4, hashing: 8, error: 16 };
    window.rTorrentStub = function() { this.content = "transport"; this.commands = []; };
    window.__labelMobile = { disable: jest.fn() };
    load("plugins/mobile/init.js", "(function(){ const plugin=window.__labelMobile;", "})();");
    mobile = window.__labelMobile;
    mobile.torrent = torrent("");
    mobile.torrents = { [hash]: mobile.torrent };
    mobile.request = jest.fn();
    mobile.showPage = jest.fn();
    mobile.showAlert = jest.fn();
    mobile.statusFilterOptions = [];
  });

  test("setting a binary label sends its original bytes and updates display separately", () => {
    mobile.setTorrentLabel(binary);
    expect(mobile.request).toHaveBeenCalledWith(`?action=setlabel&hash=${hash}&s=label&v=raw%FF`);
    expect(mobile.torrent.label).toBe(binary.name);
    expect(mobile.torrent.label_value).toEqual(binary);
    expect(mobile.torrent.label_id).toBe(binaryId);
    expect($("#label td:last").text()).toContain(binary.name);
  });

  test("the literal spelling of a binary label selects a different label", () => {
    mobile.torrent = torrent(binary);
    mobile.torrents[hash] = mobile.torrent;
    mobile.setTorrentLabel(binary.name);
    expect(mobile.request).toHaveBeenCalledWith(`?action=setlabel&hash=${hash}&s=label&v=raw%5CxFF`);
  });

  test("the existing label menu carries the selected value instead of its display text", () => {
    mobile.editLabel();
    $("#label select").prop("selectedIndex", 2).trigger("change");
    expect(mobile.request).toHaveBeenCalledWith(`?action=setlabel&hash=${hash}&s=label&v=raw%FF`);
  });

  test("dismissing the binary label menu does not overwrite it", () => {
    mobile.torrent = torrent(binary);
    mobile.editLabel();
    expect($("#label select").prop("selectedIndex")).toBe(2);
    $("#label select").trigger("blur");
    expect(mobile.request).not.toHaveBeenCalled();
  });

  test("accepting unchanged editor text preserves the selected binary label", () => {
    mobile.torrent = torrent(binary);
    mobile.editLabelText();
    $("#labelEditOk").trigger("mousedown");
    expect(mobile.request).not.toHaveBeenCalled();
  });

  test("editing a selected label submits literal percent-looking text", () => {
    mobile.torrent = torrent(binary);
    mobile.editLabelText();
    $("#labelEdit").val("raw%FF").trigger("input");
    $("#labelEditOk").trigger("mousedown");
    expect(mobile.request).toHaveBeenCalledWith(`?action=setlabel&hash=${hash}&s=label&v=raw%25FF`);
  });

  test("row snapshots notice a raw label change whose display stays the same", () => {
    expect(mobile.rowSnapshot(torrent(binary))).not.toEqual(mobile.rowSnapshot(torrent(binary.name)));
  });

  test("equal visible labels have separate filters, counts and icon targets", () => {
    mobile.request = (_q, cb) => cb({ [hash]: [], [otherHash]: [] });
    mobile.processTorrents({ [hash]: torrent(binary), [otherHash]: torrent(binary.name, { hash: otherHash }) }, true);
    mobile.setFilter("label", binary);
    expect($("#" + hash).css("display")).not.toBe("none");
    expect($("#" + otherHash).css("display")).toBe("none");
    expect($("#filterText").text()).toContain(binary.name);
    expect(mobile.labelIds[binaryId]).not.toBe(mobile.labelIds["clabel__" + binary.name]);
    window.thePlugins.get = () => ({});
    mobile.renderFilterPage();
    const icons = $("#filterLabelsList img").map((_, img) => img.getAttribute("src")).get();
    expect(icons).toContain("plugins/tracklabels/action.php?label=raw%FF");
    expect(icons).toContain("plugins/tracklabels/action.php?label=raw%5CxFF");
  });

  test("the add dialog displays existing label values rather than internal keys", () => {
    mobile.addTorrent();
    expect($("#tadd_label_select option").slice(2).map((_, el) => $(el).text()).get()).toEqual([binary.name, binary.name]);
  });

  test("adding with a selected raw label submits its bytes once", () => {
    window.theURLs = { AddTorrentURL: "php/addtorrent.php" };
    setLabelInput("#tadd_label", binary);
    const form = {};
    expect(mobile.makeAddRequest(form)).toBe(true);
    expect(form.action).toBe("php/addtorrent.php?label=raw%FF");
    $("#tadd_label").val("raw%FF").trigger("input");
    mobile.makeAddRequest(form);
    expect(form.action).toBe("php/addtorrent.php?label=raw%25FF");
  });

  test("a valid label spelled like a binary ID retains its own filter", () => {
    theWebUI.categoryList.panelLabelAttribs.plabel.set("clabel__" + binaryId, { count: 1 });
    mobile.request = (_q, cb) => cb({ [hash]: [], [otherHash]: [] });
    mobile.processTorrents({ [hash]: torrent(binary), [otherHash]: torrent(binaryId, { hash: otherHash }) }, true);
    mobile.setFilter("label", binaryId);
    expect($("#" + hash).css("display")).toBe("none");
    expect($("#" + otherHash).css("display")).not.toBe("none");
  });
});

describe("tracklabels byte label target", () => {
  let plugin;
  let requests;
  let originalXHR;
  beforeEach(() => {
    document.body.innerHTML = "";
    requests = [];
    originalXHR = window.XMLHttpRequest;
    window.XMLHttpRequest = class {
      constructor() { requests.push(this); this.status = 200; }
      open(method, url) { this.method = method; this.url = url; }
      setRequestHeader() {}
      send(body) { this.body = body; }
    };
    const handlers = {};
    window.theDialogManager = {
      make: (id, _title, body) => $("body").append($("<div>").attr("id", id).append(body)),
      setHandler: (id, event, fn) => { handlers[id + event] = fn; },
      show: id => handlers[id + "beforeShow"]?.(), hide: jest.fn(),
    };
    window.theWebUI = { categoryList: { torrentLabelTree: labelTree(), refreshPanel: { plabel: attrs => [...attrs] },
      contextMenuEntries: () => [], updatedStatisticEntry: (_p, id, attrs = {}) => [id, attrs], syncFn: jest.fn() },
      config: jest.fn(), addTrackers: jest.fn(), update: jest.fn() };
    window.__labelIcons = { loadLang: jest.fn(), canChangeMenu: () => true, addPaneToCategory: jest.fn() };
    load("plugins/tracklabels/init.js", "(function(){ const plugin=window.__labelIcons;", "})();");
    plugin = window.__labelIcons;
    plugin.onLangLoaded();
  });
  afterEach(() => { window.XMLHttpRequest = originalXHR; });

  test("panel icons use binary bytes separately from equal literal display names", () => {
    const rows = theWebUI.categoryList.refreshPanel.plabel(new Map([
      [binaryId, { text: binary.name }], ["clabel__" + binary.name, { text: binary.name }],
    ]));
    expect(rows.map(row => row[1].icon)).toEqual([
      "url:plugins/tracklabels/action.php?label=raw%FF",
      "url:plugins/tracklabels/action.php?label=raw%5CxFF",
    ]);
  });

  test("context menu delete retains the selected raw label in the request URI", () => {
    theWebUI.categoryList.contextMenuEntries("plabel", binaryId)[0][1]();
    expect($("#tracklabels-dialog-label").val()).toBe(binary.name);
    $("#tracklabels-dialog input[name=delete]").triggerHandler("click");
    expect(requests[0].url).toBe("plugins/tracklabels/action.php?label=raw%FF");
    expect(requests[0].body.has("label")).toBe(false);
    requests[0].onloadend();
    expect(plugin.imageEditSuffix.label[binaryId]).toMatch(/^&t=/);
  });

  test("editing the icon target makes percent-looking text literal", () => {
    theWebUI.showTracklabelsDialog(binary);
    $("#tracklabels-dialog-label").val("raw%FF").trigger("input");
    $("#tracklabels-dialog input[name=delete]").triggerHandler("click");
    expect(requests[0].url).toBe("plugins/tracklabels/action.php?label=raw%25FF");
  });

  test("a binary label containing a dot remains a label icon target", () => {
    theWebUI.showTracklabelsDialog({ name: "raw\\xFF.test", labelKey: "raw%FF.test" });
    $("#tracklabels-dialog input[name=delete]").triggerHandler("click");
    expect(requests[0].url).toBe("plugins/tracklabels/action.php?label=raw%FF.test");
  });
});
