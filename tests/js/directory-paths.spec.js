import { readFileSync } from "fs";

window.$ = require("jquery");
window.jQuery = window.$;

function load(path, prefix = "", suffix = "") {
  const script = document.createElement("script");
  script.textContent = prefix + readFileSync(`../${path}`, "utf8") + suffix;
  document.body.appendChild(script);
}

beforeAll(() => {
  load("lang/en.js");
  load("js/common.js");
});

beforeEach(() => {
  document.body.innerHTML = '<input id="path"><dialog id="frame"><input class="filter-dir"></dialog>';
  window.theWebUI = {};
});

const legacy = { name: "/downloads/\\xFF", pathKey: "%2Fdownloads%2F%FF" };

function pluginScript(name, options = {}, expose = "") {
  window.pathPlugin = { name, enabled: true, allStuffLoaded: true, loadLang() {}, loadMainCSS() {},
    canChangeMenu: () => false, canChangeOptions: () => true, canChangeTabs: () => false, disable() {}, ...options };
  load(`plugins/${name}/init.js`, "(function(plugin){", `${expose}})(window.pathPlugin);`);
  return window.pathPlugin;
}

test.each(["/映画/🙂", "/literal%2F/%25", "/back\\slash", "/plus+ space?&="])("manual paths stay literal: %s", path => {
  setPathInput("#path", path);
  expect(pathDisplay(getPathInput("#path"))).toBe(path);
  expect(decodeURIComponent(pathQuery(getPathInput("#path")))).toBe(path);
});

test("selected byte identity survives submit and changes only after an edit", () => {
  setPathInput("#path", legacy);
  expect($("#path").val()).toBe(legacy.name);
  expect(pathQuery(getPathInput("#path"))).toBe(legacy.pathKey);
  $("#path").val("/other%2F\\xFF");
  expect(decodeURIComponent(pathQuery(getPathInput("#path")))).toBe("/other%2F\\xFF");
  setPathInput("#path", legacy);
  $("#path").trigger("input");
  expect(getPathInput("#path")).toBe(legacy.name);
});

test.each([
  "/downloads/line\nnext\rfile",
  { name: "/downloads/line\n\\xFF", pathKey: "%2Fdownloads%2Fline%0A%FF" },
])("input normalization does not alter an untouched selected path", path => {
  setPathInput("#path", path);
  expect($("#path").val()).not.toContain("\n");
  expect(getPathInput("#path")).toEqual(path);
  $("#path").trigger("input");
  expect(getPathInput("#path")).toBe($("#path").val());
});

test("the desktop chooser navigates and selects by byte key when labels collide", () => {
  load("plugins/_getdir/init.js", "(function(){const plugin={loadLang(){},loadMainCSS(){}};", "})();");
  const browser = Object.create(theWebUI.rDirBrowser.prototype);
  browser.edit = $("#path"); browser.frame = $("#frame"); browser.withFiles = true;
  const previous = $.ajax;
  $.ajax = jest.fn((_url, options) => options.success({
    path: legacy,
    directories: [{ name: "same", pathKey: "%FE" }, { name: "same", pathKey: "%FD" }],
    files: [{ name: "file", pathKey: "%FC" }],
  }));
  try {
    setPathInput("#path", legacy);
    browser.requestDir();
    expect($.ajax.mock.calls[0][0]).toContain(`dir=${legacy.pathKey}`);
    expect(browser.frame.find(".rmenuitem").map((_i, el) => $(el).text()).get()).toEqual(["./", "same/", "same/", "file"]);
    browser.frame.find(".rmenuitem").eq(2).trigger("click");
    expect($("#path").val()).toBe("/downloads/\\xFF/same/");
    expect(pathQuery(getPathInput("#path"))).toBe("%2Fdownloads%2F%FF%2F%FD%2F");
    browser.frame.find(".rmenuitem").eq(3).trigger("click");
    expect(pathQuery(getPathInput("#path"))).toBe("%2Fdownloads%2F%FF%2F%FC");
  } finally { $.ajax = previous; }
});

test("desktop add uses the chosen raw directory in the actual upload form action", () => {
  window.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} };
  window.theWebUI = { settings: {}, version: "test", save() {}, upRateMenu() {}, downRateMenu() {} };
  window.theOptionsWindow = { init() {} };
  document.body.innerHTML = '<div id="dialog-container"></div>';
  load("js/objects.js"); load("js/content.js");
  makeContent();
  setPathInput("#dir_edit", legacy);
  $("#addtorrenturl").triggerHandler("submit");
  expect($("#addtorrenturl").attr("action")).toContain(`dir_edit=${legacy.pathKey}`);
});

test("mobile browse, add and datadir submits preserve the chosen directory bytes", () => {
  document.body.innerHTML += '<div id="getDirList"></div><input id="dir_edit"><input id="datadir_edit"><button id="dataDirOk"></button>';
  window.theWebUI = { getTrackerName() {}, settings: {}, requestWithoutTimeout() {} };
  const plugin = pluginScript("mobile");
  plugin.goBack = jest.fn(); plugin.showPage = jest.fn();
  const previous = $.ajax;
  $.ajax = jest.fn(options => options.success && options.success({ path: legacy, directories: [{ name: "sub", pathKey: "%FE" }] }));
  try {
    plugin.showGetDir();
    $("#getDirList tr").trigger("click");
    expect($.ajax.mock.calls[1][0].data).toContain(`dir=${legacy.pathKey}%2F%FE`);
    $("#getDirList button").first().trigger("click");
    expect(getPathInput("#dir_edit")).toEqual(legacy);
    const form = {};
    plugin.makeAddRequest(form);
    expect(form.action).toContain(`dir_edit=${legacy.pathKey}`);
    setPathInput("#datadir_edit", legacy);
    plugin.torrent = { hash: "A".repeat(40) };
    plugin.dataDirSnapshot = "old";
    $.ajax.mockImplementation(() => {});
    plugin.sendDataDir();
    expect($.ajax.mock.calls.at(-1)[0].data).toContain(`&datadir=${legacy.pathKey}`);
  } finally { $.ajax = previous; }
});

test("mobile datadir submits a different directory with the same displayed spelling", () => {
  document.body.innerHTML += '<input id="datadir_edit"><button id="dataDirOk"></button>';
  theWebUI.getTrackerName = () => "";
  const plugin = pluginScript("mobile");
  plugin.goBack = jest.fn();
  plugin.torrent = { hash: "A".repeat(40) };
  setPathInput("#datadir_edit", legacy);
  plugin.dataDirSnapshot = plugin.dataDirFormState();
  setPathInput("#datadir_edit", legacy.name);
  const previous = $.ajax;
  $.ajax = jest.fn();
  try {
    plugin.sendDataDir();
    expect($.ajax).toHaveBeenCalledTimes(1);
    expect($.ajax.mock.calls[0][0].data).toContain("&datadir=%2Fdownloads%2F%5CxFF");
    expect(plugin.goBack).not.toHaveBeenCalled();
  } finally { $.ajax = previous; }
});

test.each([false, true])("mobile datadir trims a server path like the desktop dialog, closed=%s", closed => {
  document.body.innerHTML += '<div id="torrentDataDir"><input id="datadir_edit"><button id="dataDirOk"></button></div>';
  theWebUI.getTrackerName = () => "";
  const plugin = pluginScript("mobile");
  plugin.showPage = jest.fn();
  const path = "/downloads/trailing \n";
  plugin.torrent = { hash: "A".repeat(40), multi_file: 1, save_path: closed ? "" : path };
  plugin.request = (_request, callback) => callback({ savepath: path });
  plugin.showDataDir();
  expect(getPathInput("#datadir_edit")).toBe("/downloads/trailing");
});

test("create and unpack pass structured paths through the existing console task transport", () => {
  document.body.innerHTML += '<input id="path_edit"><textarea id="trackers"></textarea><input id="comment"><input id="source"><input id="edit_unpack">';
  window.theWebUI = { startConsoleTask: jest.fn(), addAndShowSettings() {}, setSettings() {} };
  window.theDialogManager = { hide() {} };
  window.rTorrentStub = function() {};
  pluginScript("create", {}, "window.runPathCreate=checkCreate;");
  setPathInput("#path_edit", legacy);
  runPathCreate();
  expect(theWebUI.startConsoleTask.mock.calls[0][2].path_edit).toEqual(legacy);
  pluginScript("unpack", { canChangeOptions: () => false });
  setPathInput("#edit_unpack", legacy);
  theWebUI.unpack();
  expect(theWebUI.startConsoleTask.mock.calls[1][2].dir).toEqual(legacy);
  window.dxSTable = function() {};
  const task = pluginScript("_task");
  task.clearForeTimeout = () => {};
  theDialogManager.setModalState = () => {};
  theWebUI.requestWithoutTimeout = jest.fn();
  theWebUI.startConsoleTask("create", "create", { path_edit: legacy });
  const request = theWebUI.requestWithoutTimeout.mock.calls[0][0];
  expect(request).toContain("&v=path_edit[pathKey]&s=%252Fdownloads%252F%25FF");
  expect(request).toContain("&v=path_edit[name]&s=%2Fdownloads%2F%5CxFF");
});

test("autotools and unpack reopen persisted paths without treating them as edits", () => {
  const fields = ["enable_label", "label_template", "enable_move", "path_to_finished", "skip_move_for_files", "fileop_type", "enable_watch", "path_to_watch", "watch_start", "automove_filter", "auto_add_label", "auto_add_name", "unpack_enabled", "unpack_label", "unpack_name", "edit_unpack1", "edit_filter"];
  document.body.innerHTML += fields.map(id => `<input id="${id}" type="${id.includes("enable") || id.startsWith("auto_add") || id === "watch_start" || id.startsWith("unpack_") ? "checkbox" : "text"}">`).join("");
  window.rTorrentStub = function() {};
  window.theWebUI = { addAndShowSettings() {}, setSettings() {},
    autotools: { EnableLabel: 0, LabelTemplate: "", EnableMove: 0, PathToFinished: legacy, SkipMoveForFiles: "", FileOpType: "Move", EnableWatch: 0, PathToWatch: legacy, WatchStart: 0, MoveFilter: "", AddLabel: 0, AddName: 0 },
    unpackData: { enabled: 0, addLabel: 0, addName: 0, path: legacy, filter: "/.*/" },
  };
  pluginScript("autotools");
  theWebUI.addAndShowSettings();
  expect(theWebUI.autotoolsWasChanged()).toBe(false);
  const auto = new rTorrentStub(); auto.setautotools();
  expect(auto.content).toContain(`&path_to_finished=${legacy.pathKey}`);
  expect(auto.content).toContain(`&path_to_watch=${legacy.pathKey}`);
  pluginScript("unpack");
  theWebUI.addAndShowSettings();
  expect(theWebUI.unpackWasChanged()).toBe(false);
  const unpack = new rTorrentStub(); unpack.setunpack();
  expect(unpack.content).toContain(`&unpack_path=${legacy.pathKey}`);
});

test("extsearch uses the selected raw directory in its actual request", () => {
  document.body.innerHTML += '<input id="tegdir_edit"><input id="teglabel">';
  window.theSearchEngines = {};
  window.rTorrentStub = function() {};
  theWebUI.categoryList = { switchLabel() {}, contextMenuEntries() {}, refreshPanel: { psearch() {} } };
  const plugin = pluginScript("extsearch", { canChangeOptions: () => false });
  plugin.tegArray = [];
  setPathInput("#tegdir_edit", legacy);
  const request = new rTorrentStub(); request.loadtegtorrents();
  expect(request.content).toContain(`&dir_edit=${legacy.pathKey}`);
});
