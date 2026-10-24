import { readFileSync } from "fs";

window.$ = window.jQuery = require("jquery");

function load(file) {
  const script = document.createElement("script");
  script.textContent = readFileSync(`../${file}`, "utf8");
  document.body.appendChild(script);
}

beforeAll(() => {
  for (const file of ["lang/en.js", "js/sanitize.js", "js/sanitize.config.js",
    "js/browser.js", "js/common.js", "js/objects.js", "js/options.js", "js/content.js",
    "js/stable.js", "js/graph.js", "js/plugins.js", "js/rtorrent.js", "js/webui.js"]) load(file);
});

const legacy = { name: "/data/\\xCF\\xF0", pathKey: "%2Fdata%2F%CF%F0" };
const originalRequest = {};

beforeEach(() => {
  load("js/rtorrent.js");
  document.body.innerHTML = '<input id="directory"><input type="number" id="max_peers">';
  theWebUI.systemInfo = { rTorrent: { started: true, apiVersion: 26, iVersion: 0x101a } };
  theWebUI.settings = { directory: legacy, max_peers: "100" };
  theWebUI.settingsSavePending = false;
  originalRequest.value = theWebUI.request;
  theWebUI.request = jest.fn();
  theWebUI.loadSettings();
});

afterEach(() => { theWebUI.request = originalRequest.value; });

function httprpc(enabled) {
  if (enabled === null) return;
  const script = document.createElement("script");
  script.textContent = `(function(){ const plugin={enabled:${enabled}};\n`
    + readFileSync("../plugins/httprpc/init.js", "utf8") + "\n})();";
  document.body.appendChild(script);
}

test("settings read uses byte-aware server values with httprpc", () => {
  httprpc(true);
  const request = new rTorrentStub("?action=getsettings");
  expect(request.mountPoint).toBe("plugins/httprpc/action.php");
  expect(request.dataType).toBe("json");
  const values = Array(theRequestManager.stg.count + 1).fill("0");
  values[4] = legacy;
  const result = request.getResponse(values);
  expect(result.directory).toEqual(legacy);
});

test("read-back shows a byte name and unrelated changes leave its identity untouched", () => {
  expect($("#directory").val()).toBe("/data/\\xCF\\xF0");
  $("#max_peers").val("101");
  theWebUI.setSettings();
  expect(theWebUI.request).toHaveBeenCalledTimes(1);
  const request = theWebUI.request.mock.calls[0][0];
  expect(request.ss).toEqual(["nmax_peers"]);
  expect(theWebUI.settings.directory).toEqual(legacy);
});

test.each([null, false])("without httprpc (%s) a selected byte directory reaches XML-RPC as base64", enabled => {
  httprpc(enabled);
  setPathInput("#directory", { name: "/other/\\xFF", pathKey: "%2Fother%2F%FF" });
  theWebUI.setSettings();
  const request = theWebUI.request.mock.calls[0][0];
  expect(request.commands[0].params.at(-1)).toEqual({ type: "base64", value: btoa("/other/\xFF") });
});

test("without httprpc an edited UTF-8 directory reaches XML-RPC as escaped text", () => {
  $("#directory").val("/映画/a&b").trigger("input");
  theWebUI.setSettings();
  const request = theWebUI.request.mock.calls[0][0];
  expect(request.commands[0].params.at(-1)).toEqual({ type: "string", value: "/映画/a&amp;b" });
});

test("a selected directory keeps raw bytes on Apply with httprpc", () => {
  httprpc(true);
  setPathInput("#directory", { name: "/other/\\xFF", pathKey: "%2Fother%2F%FF" });
  theWebUI.setSettings();
  const request = theWebUI.request.mock.calls[0][0];
  expect(request.mountPoint).toBe("plugins/httprpc/action.php");
  expect(request.content).toBe("mode=setsettings&v=%2Fother%2F%FF&s=sdirectory");
  expect(theWebUI.settings.directory).toEqual({ name: "/other/\\xFF", pathKey: "%2Fother%2F%FF" });
});

test.each(["/映画/🙂", "/literal%CF/%FF", "/a&b=c+d", "/raw\\xFF"])("editing the directory submits literal text: %s", text => {
  httprpc(true);
  $("#directory").val(text).trigger("input");
  theWebUI.setSettings();
  const request = theWebUI.request.mock.calls[0][0];
  expect(request.mountPoint).toBe("plugins/httprpc/action.php");
  expect(new URLSearchParams(request.content).get("v")).toBe(text);
  expect(theWebUI.settings.directory).toBe(text);
});

test("a reload retains a serialized path envelope without inventing an edit", () => {
  theWebUI.settings = JSON.parse(JSON.stringify({ directory: legacy }));
  theWebUI.loadSettings();
  theWebUI.setSettings();
  expect($("#directory").val()).toBe("/data/\\xCF\\xF0");
  expect(theWebUI.request).not.toHaveBeenCalled();
});

test("selecting the same raw directory with a different label does not save a new path", () => {
  setPathInput("#directory", { name: "Another display label", pathKey: legacy.pathKey });
  theWebUI.setSettings();
  expect(theWebUI.request).not.toHaveBeenCalled();
});

test("equal display labels still select distinct raw directories", () => {
  httprpc(true);
  setPathInput("#directory", { name: legacy.name, pathKey: "%2Fdata%2F%CF%F1" });
  theWebUI.setSettings();
  const request = theWebUI.request.mock.calls[0][0];
  expect(request.content).toBe("mode=setsettings&v=%2Fdata%2F%CF%F1&s=sdirectory");
});
