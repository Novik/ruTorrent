import { readFileSync } from "fs";

window.$ = require("jquery");

function loadScript(path, owner) {
  const script = document.createElement("script");
  const source = readFileSync(`../${path}`, "utf8");
  script.textContent = owner ? `(function(plugin) {\n${source}\n})(window.${owner});` : source;
  document.body.appendChild(script);
}

beforeEach(() => {
  jest.useFakeTimers();
  document.body.innerHTML = '<div id="tskConsole-header"></div><div id="tskcmdlog"></div><div id="tskcmderrors"></div><div id="tasks"></div>';
  loadScript("lang/en.js");
  loadScript("js/common.js");
  window.taskTable = { addRowById: jest.fn(), setValuesById: jest.fn(() => true), setIcon: jest.fn(), refreshRows: jest.fn() };
  window.theWebUI = { requestWithoutTimeout: jest.fn(), requestWithTimeout: jest.fn(),
    settings: { "webui.update_interval": 1000000 }, getTable: () => taskTable,
    tables: { tasks: { container: "#tasks" } }, deltaTime: 0, activeView: "" };
  window.theDialogManager = { show: jest.fn(), hide: jest.fn(), clearModalState: jest.fn(), setModalState: jest.fn() };
  window.rTorrentStub = function() {};
  window.dxSTable = function() {};
  window.theTabs = { onShow() {} };
  window.requesterPlugin = null;
  window.thePlugins = { get: () => requesterPlugin };
  window.plugin = { loadLang() {}, loadMainCSS() {}, canChangeTabs: () => false };
  loadScript("plugins/_task/init.js");
  plugin.foreground = { no: "task-1", pid: 123, status: -1, options: { noclose: true }, requester: "fixture" };
});

afterEach(() => {
  jest.clearAllTimers();
  jest.useRealTimers();
});

test("console display preserves raw callback arguments and exposes a separate projected line", () => {
  const seen = [];
  window.requesterPlugin = { onTaskShowLog(task, line, id, index, displayLine) {
    seen.push({ task, line, id, index, displayLine });
    return escapeHTML(displayLine === undefined ? line : displayLine) + "<br>";
  } };
  const state = { no: "task-1", pid: 123, status: -1, params: { arg: "raw�", hash: "A".repeat(40), no: "83" },
    log: ["Фильм �", "-=*=-", "0"], errors: ["<b>�</b>"],
    display: { arg: "raw\\xFF", log: ["Фильм \\xFF", "-=*=-", "0"], errors: ["<b>\\xFE</b>"] } };
  plugin.check(state);
  expect($("#tskcmdlog").text()).toBe("Фильм \\xFF-=*=-0");
  expect($("#tskcmderrors").text()).toBe("<b>\\xFE</b>");
  expect($("#tskcmderrors b")).toHaveLength(0);
  expect(seen.map(({ line, displayLine }) => [line, displayLine])).toEqual([
    ["Фильм �", "Фильм \\xFF"], ["-=*=-", "-=*=-"], ["0", "0"], ["<b>�</b>", "<b>\\xFE</b>"],
  ]);
  expect(plugin.foreground.params).toBe(state.params);
  expect(state.log[0]).toBe("Фильм �");
  const firstNode = $("#tskcmdlog")[0].firstChild;
  plugin.check(state);
  expect($("#tskcmdlog")[0].firstChild).toBe(firstNode);
  expect(seen).toHaveLength(8);
});

test("default console rendering uses Unicode projection and drops it when the next poll is valid", () => {
  const state = { no: "task-1", pid: 123, status: -1, params: {}, log: ["raw�"], errors: [],
    display: { log: ["raw\\xFF"] } };
  plugin.check(state);
  expect($("#tskcmdlog").text()).toBe("raw\\xFF");
  plugin.check({ ...state, log: ["映画 🎬"], display: undefined });
  expect($("#tskcmdlog").text()).toBe("映画 🎬");
});

test("background task rows show projected arguments while activation keeps original parameters", () => {
  plugin.foreground.no = 0;
  const item = { no: "task-1", pid: 123, status: 0, name: "mediainfo", requester: "mediainfo",
    start: 1, finish: 2, params: { arg: "raw�", hash: "A".repeat(40), no: "83" }, display: { arg: "raw\\xFF" }, log: [], errors: [] };
  plugin.onGetTasks({ "task-1": item });
  expect(taskTable.addRowById.mock.calls[0][0].arg).toBe("raw\\xFF");
  plugin.onGetTasks({ "task-1": item });
  expect(taskTable.setValuesById.mock.calls[0][1].arg).toBe("raw\\xFF");
  expect(plugin.background["task-1"].params).toBe(item.params);
  plugin.fromBackground("task-1");
  expect(plugin.foreground.params).toEqual(item.params);
  expect(plugin.foreground.no).toBe("task-1");
  expect(theWebUI.requestWithoutTimeout).not.toHaveBeenCalled();
  plugin.start();
  const request = new URLSearchParams(theWebUI.requestWithoutTimeout.mock.calls[0][0]);
  expect(request.getAll("v")).toEqual(["arg", "hash", "no"]);
  expect(request.getAll("s")).toEqual(["raw�", "A".repeat(40), "83"]);
});

test("structured log errors keep their original callback shape", () => {
  const error = { reason: "dumpMissing", file: "original", user: "owner" };
  const callback = jest.fn((_task, line) => escapeHTML(line.reason) + "<br>");
  window.requesterPlugin = { onTaskShowLog: callback };
  plugin.check({ no: "task-1", pid: 123, status: -1, params: {}, log: [], errors: [error] });
  expect(callback.mock.calls[0][1]).toBe(error);
  expect($("#tskcmderrors").text()).toBe("dumpMissing");
});

function loadRequester(name) {
  window.requesterPlugin = { name, loadLang() {}, loadMainCSS() {}, canChangeMenu: () => true, canChangeOptions: () => false };
  thePlugins.isInstalled = () => false;
  loadScript(`plugins/${name}/init.js`, "requesterPlugin");
  return requesterPlugin;
}

test("screenshots keep raw frame identifiers while rendering projected errors", () => {
  const requester = loadRequester("screenshots");
  document.body.insertAdjacentHTML("beforeend", '<input id="scimgfile" value="映画">');
  expect(requester.onTaskShowLog({ no: "task-1" }, "7", "tskcmdlog", 0, "display alias")).toBe("");
  const url = new URL($("#scframe0 img")[0].src);
  expect(url.searchParams.get("fno")).toBe("7");
  expect(url.searchParams.get("file")).toBe("映画");
  expect(requester.onTaskShowLog({}, "<b>�</b>", "tskcmderrors", 0, "<b>\\xFF</b>")).toBe("&lt;b&gt;\\xFF&lt;/b&gt;<br>");
});

test("spectrogram markers use the raw line while ordinary text uses the display line", () => {
  const requester = loadRequester("spectrogram");
  expect(requester.onTaskShowLog({}, "-=*=-", "tskcmdlog", 0, "display alias")).toBe("");
  expect(requester.gotImage).toBe(true);
  requester.gotImage = false;
  expect(requester.onTaskShowLog({}, "raw�", "tskcmdlog", 0, "-=*=-")).toBe("-=*=-<br>");
  expect(requester.gotImage).toBe(false);
  expect(requester.onTaskShowLog({}, "<b>�</b>", "tskcmderrors", 0, "<b>\\xFE</b>")).toBe("&lt;b&gt;\\xFE&lt;/b&gt;<br>");
});

test("dump keeps structured localized errors while rendering projected plain output", () => {
  const requester = loadRequester("dump");
  theUILang.dumpFixture = "Missing {file} for {user}";
  const error = { reason: "dumpFixture", file: "<file>", user: "owner" };
  expect(requester.onTaskShowLog({}, error, "tskcmderrors", 0, "must not replace object")).toBe("Missing &lt;file&gt; for owner<br>");
  expect(error).toEqual({ reason: "dumpFixture", file: "<file>", user: "owner" });
  expect(requester.onTaskShowLog({}, "<b>�</b>", "tskcmdlog", 0, "<b>\\xFD</b>")).toBe("&lt;b&gt;\\xFD&lt;/b&gt;<br>");
});
