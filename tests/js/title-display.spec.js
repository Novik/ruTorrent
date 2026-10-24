import { readFileSync } from "fs";

window.$ = require("jquery");
const hash = "A".repeat(40);
const other = "B".repeat(40);

function loadScript(file, prefix = "", suffix = "") {
  const script = document.createElement("script");
  script.textContent = prefix + readFileSync(`../${file}`, "utf8") + suffix;
  document.body.appendChild(script);
}

beforeAll(() => {
  for (const file of ["lang/en.js", "js/common.js", "js/content.js"]) loadScript(file);
});

// Exercise jQuery's real converters and Deferred completion for empty HTTP bodies.
$.ajaxTransport("+json +xml", () => {
  if (!window.__titleWire) return;
  return {
    send(_headers, complete) {
      const wire = window.__titleWire;
      complete(wire.status, wire.status === 204 ? "No Content" : "OK", { text: wire.text }, "");
    },
    abort() {},
  };
});

function exchange(stub, wire) {
  const complete = jest.fn();
  const error = jest.fn();
  window.__titleWire = wire;
  try { Ajax(stub, true, complete, jest.fn(), error); }
  finally { delete window.__titleWire; }
  return { complete, error };
}

function loadUI(enabled = true) {
  window.theWebUI = {
    settings: { "webui.needmessage": true }, showFlags: 0xffff,
    systemInfo: { rTorrent: { apiVersion: 26, iVersion: 0x101a, started: true } },
  };
  loadScript("js/rtorrent.js");
  window.noty = jest.fn();
  correctContent();
  loadScript("plugins/datadir/init.js",
    "(function () { var plugin = { loadMainCSS() {}, loadLang() {}, canChangeMenu() { return false; } };\n", "\n})();");
  if (enabled !== null) loadScript("plugins/httprpc/init.js",
    `(function () { var plugin = { enabled: ${enabled} };\n`, "\n})();");
}

function row(name, path = `/downloads/${name}`) {
  const values = Array(36).fill("0");
  values[4] = name; values[7] = "2"; values[25] = path; values[29] = "";
  values[35] = "plugin-value";
  return values;
}

test.each([null, false])("without httprpc (%s) the core list, files and settings stay XML-RPC", enabled => {
  loadUI(enabled);
  for (const uri of ["?list=1", `?action=getfiles&hash=${hash}`, "?action=getsettings"]) {
    const stub = new rTorrentStub(uri);
    expect(stub.dataType).toBe("xml");
    expect(stub.commands.length).toBeGreaterThan(0);
  }
});

test("list routes through the name resolver with httprpc", () => {
  loadUI(true);
  theRequestManager.cid = 123;
  theRequestManager.addRequest("trt", "d.get_custom3=", () => {});
  const stub = new rTorrentStub("?list=1");
  expect(stub.mountPoint).toBe("plugins/httprpc/action.php");
  expect(stub.dataType).toBe("json");
  expect(stub.content).toBe("mode=list&cid=123&cmd=d.custom3%3D");
  expect(stub.commands).toHaveLength(0);
});

test("title aliases keep the real save-path route and basename", () => {
  loadUI();
  let extension;
  theRequestManager.addRequest("trt", "d.get_custom3=", (id, torrent, value) => { extension = { id, name: torrent.name, value }; });
  const values = row("����");
  const stub = new rTorrentStub("?list=1");
  const model = stub.getResponse({ t: { [hash]: values }, n: { [hash]: "Серия" }, cid: 1, full: 1 }).torrents[hash];
  expect(model.name).toBe("Серия");
  expect(model.path_name).toBe("����");
  expect(model.save_path).toBe("/downloads");
  expect(extension).toEqual({ id: hash, name: "Серия", value: "plugin-value" });
  expect(values[4]).toBe("����");
  theWebUI.torrents = { [hash]: model };
  const path = new rTorrentStub(`?action=getsavepath&hash=${hash}`);
  expect(path.dataType).toBe("json");
  expect(path.mountPoint).toBe("plugins/httprpc/action.php");
  expect(path.content).toBe(`mode=getsavepath&hash=${hash}`);
  expect(path.commands).toHaveLength(0);
  expect(path.getResponse(["0", "/downloads/����", "0"])).toEqual({ hash, savepath: "/downloads" });
  expect(path.getResponse(["0", "/downloads/Серия", "0"]).savepath).toBe("/downloads/Серия");
});

test("rate-only deltas retain aliases; each emitted raw name replaces its alias", () => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { [hash]: row("�") }, n: { [hash]: "Display" }, cid: 1, full: 1 });
  let model = stub.getResponse({ t: { [hash]: { 11: "1234" } }, cid: 2 }).torrents[hash];
  expect(model.name).toBe("Display");
  expect(model.ul).toBe(1234);
  model = stub.getResponse({ t: { [hash]: { 4: "��", 25: "/downloads/��" } }, n: { [hash]: "Next" }, cid: 3 }).torrents[hash];
  expect(model.name).toBe("Next");
  expect(model.path_name).toBe("��");
  model = stub.getResponse({ t: { [hash]: { 4: "映画 🙂", 25: "/downloads/映画 🙂" } }, cid: 4 }).torrents[hash];
  expect(model.name).toBe("映画 🙂");
  expect(model.path_name).toBe("映画 🙂");
  expect(model.save_path).toBe("/downloads");
});

test("an emitted name refreshes its alias even when byte substitution leaves the raw cell unchanged", () => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { [hash]: row("�") }, n: { [hash]: "First" }, cid: 1, full: 1 });
  const model = stub.getResponse({ t: { [hash]: { 4: "�" } }, n: { [hash]: "Second" }, cid: 2 }).torrents[hash];
  expect(model.name).toBe("Second");
  expect(model.path_name).toBe("�");
});

test.each(["full", "deleted", "lost-cache"])("%s clears an alias before the hash is reused", reset => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { [hash]: row("�") }, n: { [hash]: "Old alias" }, cid: 1, full: 1 });
  if (reset === "full") stub.getResponse({ t: {}, cid: 2, full: 1 });
  else if (reset === "deleted") stub.getResponse({ t: {}, d: [hash], cid: 2 });
  else theRequestManager.torrents = {};
  if (reset !== "lost-cache") expect(Object.keys(theRequestManager.torrentNames)).toHaveLength(0);
  const model = stub.getResponse({ t: { [hash]: row("raw") }, cid: 3 }).torrents[hash];
  expect(model.name).toBe("raw");
  expect(model.path_name).toBe("raw");
});

test("a full response refreshes the alias for a retained hash", () => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { [hash]: row("�") }, n: { [hash]: "Old alias" }, cid: 1, full: 1 });
  const model = stub.getResponse({ t: { [hash]: row("�") }, cid: 2, full: 1 }).torrents[hash];
  expect(model.name).toBe("�");
});

test("an empty display title remains an explicit alias through rate-only updates", () => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { [hash]: row("�") }, n: { [hash]: "" }, cid: 1, full: 1 });
  expect(stub.getResponse({ t: { [hash]: { 11: "7" } }, cid: 2 }).torrents[hash].name).toBe("");
});

test.each([
  ["null map", null], ["scalar map", "name"], ["array map", []],
  ["non-string name", { [hash]: 7 }], ["missing name", { [hash]: null }],
  ["invalid hash", { invalid: "alias" }], ["unrelated hash", { [other]: "alias" }],
  ["prototype key", JSON.parse('{"__proto__":"alias"}')],
])("malformed title metadata (%s) cannot partially update the list", (_label, n) => {
  loadUI();
  new rTorrentStub("?list=1").getResponse({ t: { [hash]: row("�") }, n: { [hash]: "Original" }, cid: 1, full: 1 });
  const previous = JSON.stringify(theRequestManager.torrents);
  const rejected = new rTorrentStub("?list=1");
  rejected.getResponse({ t: { [hash]: row("new") }, d: [hash], full: 1, n, cid: 99 });
  expect(rejected.isError()).toBeTruthy();
  expect(theRequestManager.cid).toBe(1);
  expect(JSON.stringify(theRequestManager.torrents)).toBe(previous);
  const model = new rTorrentStub("?list=1").getResponse({ t: { [hash]: { 11: "7" } }, cid: 2 }).torrents[hash];
  expect(model.name).toBe("Original");
});

test.each([
  { 11: "7" }, { 4: 7 }, "abcde", Object.assign(Object.create({ 4: "inherited" }), { 11: "7" }),
])("an alias requires its own emitted string name cell", values => {
  loadUI();
  new rTorrentStub("?list=1").getResponse({ t: { [hash]: row("raw") }, cid: 1, full: 1 });
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { [hash]: values }, n: { [hash]: "Alias" }, cid: 2 });
  expect(stub.isError()).toBeTruthy();
  expect(theRequestManager.cid).toBe(1);
});

test("a matching raw row cannot make a malformed alias hash valid", () => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { invalid: row("raw") }, n: { invalid: "Alias" }, cid: 2 });
  expect(stub.isError()).toBeTruthy();
  expect(Object.keys(theRequestManager.torrents)).toHaveLength(0);
  expect(Object.keys(theRequestManager.torrentNames)).toHaveLength(0);
});

test.each([
  ["HTTP 204", { status: 204, text: "" }],
  ["JSON null", { status: 200, text: "null" }],
  ["invalid alias", { status: 200, text: JSON.stringify({ t: {}, n: null, cid: 99, full: 1 }) }],
])("httprpc rejects %s through the caller error path without cache writes", (_label, wire) => {
  loadUI();
  new rTorrentStub("?list=1").getResponse({ t: { [hash]: row("�") }, n: { [hash]: "Original" }, cid: 1, full: 1 });
  const rows = JSON.stringify(theRequestManager.torrents);
  const aliases = JSON.stringify(theRequestManager.torrentNames);
  const result = exchange(new rTorrentStub("?list=1"), wire);
  expect(result.complete).not.toHaveBeenCalled();
  expect(result.error).toHaveBeenCalledTimes(1);
  expect(result.error.mock.calls[0][0]).toContain("parsererror,list");
  expect(result.error.mock.calls[0][1]).toBe("Invalid list response");
  expect(noty).not.toHaveBeenCalled();
  expect(theRequestManager.cid).toBe(1);
  expect(JSON.stringify(theRequestManager.torrents)).toBe(rows);
  expect(JSON.stringify(theRequestManager.torrentNames)).toBe(aliases);
});

test("an explicit XML HTTP 204 preserves its existing completion behavior", () => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.dataType = "xml";
  const result = exchange(stub, { status: 204, text: "" });
  expect(result.complete).toHaveBeenCalledTimes(1);
  expect(result.complete).toHaveBeenCalledWith("");
  expect(result.error).not.toHaveBeenCalled();
  expect(noty).not.toHaveBeenCalled();
});

const legacyPaths = {
  base: { name: "/\\xFF/\\xFE", pathKey: "%2F%FF%2F%FE" },
  save: { name: "/\\xFF", pathKey: "%2F%FF" },
};

test("raw save paths survive sparse updates, path changes and the datadir response", () => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  let model = stub.getResponse({ t: { [hash]: row("�", "/�/�") }, p: { [hash]: legacyPaths }, cid: 1, full: 1 }).torrents[hash];
  expect(model.base_path).toBe(legacyPaths.base.name);
  expect(model.save_path_value).toEqual(legacyPaths.save);
  model = stub.getResponse({ t: { [hash]: { 11: "5" } }, cid: 2 }).torrents[hash];
  expect(model.save_path_value).toEqual(legacyPaths.save);
  theWebUI.torrents = { [hash]: model };
  const request = new rTorrentStub(`?action=getsavepath&hash=${hash}`);
  expect(request.getResponse(["0", "/�/�", "0", legacyPaths]).savepath).toEqual(legacyPaths.save);
  const changed = { base: legacyPaths.base, save: legacyPaths.base };
  model = stub.getResponse({ t: { [hash]: { 4: "different" } }, p: { [hash]: changed }, cid: 3 }).torrents[hash];
  expect(model.save_path_value).toEqual(legacyPaths.base);
  model = stub.getResponse({ t: { [hash]: { 25: "/new/different" } }, cid: 4 }).torrents[hash];
  expect(model.save_path_value).toBeUndefined();
  expect(model.save_path).toBe("/new");
});

test.each(["full", "delete"])("%s removes the remembered raw path before hash reuse", reset => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { [hash]: row("�", "/�/�") }, p: { [hash]: legacyPaths }, cid: 1, full: 1 });
  stub.getResponse({ t: {}, ...(reset === "full" ? { full: 1 } : { d: [hash] }), cid: 2 });
  expect(Object.keys(theRequestManager.torrentPaths)).toHaveLength(0);
  expect(stub.getResponse({ t: { [hash]: row("new", "/plain/new") }, cid: 3 }).torrents[hash].save_path).toBe("/plain");
});

test.each([
  null, [], { invalid: legacyPaths }, { [hash]: null },
  { [hash]: { base: legacyPaths.base, save: { name: "bad", pathKey: "%FF%2f" } } },
  { [hash]: { base: legacyPaths.base, save: { name: "bad", pathKey: "x&dir=bad" } } },
])("malformed path metadata is rejected before cache mutation", p => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { [hash]: row("�", "/�/�") }, p: { [hash]: legacyPaths }, cid: 1, full: 1 });
  const before = JSON.stringify(theRequestManager.torrents);
  stub.getResponse({ t: { [hash]: row("new", "/new") }, p, d: [hash], full: 1, cid: 2 });
  expect(stub.isError()).toBeTruthy();
  expect(theRequestManager.cid).toBe(1);
  expect(JSON.stringify(theRequestManager.torrents)).toBe(before);
  expect(theRequestManager.torrentPaths[hash]).toEqual(legacyPaths);
});

test("a path projection must accompany its own name or base-path update", () => {
  loadUI();
  const stub = new rTorrentStub("?list=1");
  stub.getResponse({ t: { [hash]: row("raw", "/raw") }, cid: 1, full: 1 });
  stub.getResponse({ t: { [hash]: { 11: "1" } }, p: { [hash]: legacyPaths }, cid: 2 });
  expect(stub.isError()).toBeTruthy();
  expect(theRequestManager.cid).toBe(1);
});

test("rejected path metadata reaches the caller error once without a second notice", () => {
  loadUI();
  window.noty = jest.fn();
  const complete = jest.fn(), error = jest.fn();
  const previous = $.ajax;
  const request = $.Deferred();
  $.ajax = () => request;
  try {
    Ajax("?list=1", true, complete, jest.fn(), error);
    request.resolve({ t: {}, p: null, cid: 99 }, "success", { status: 200, getResponseHeader: () => null });
    expect(error).toHaveBeenCalledTimes(1);
    expect(error.mock.calls[0][0]).toBe("200 [parsererror,list]");
    expect(complete).not.toHaveBeenCalled();
    expect(noty).not.toHaveBeenCalled();
    expect(theRequestManager.cid).toBe(0);
  } finally { $.ajax = previous; }
});
