import { readFileSync } from "fs";
window.$ = window.jQuery = require("jquery");
const OLD="A".repeat(40), BAD="B".repeat(40);
function load(path, prefix = "", suffix = "") {
  const script = document.createElement("script");
  script.textContent = prefix + readFileSync(path, "utf8") + suffix;
  document.body.appendChild(script);
}
function setup() {
  document.body.innerHTML = "";
  window.theWebUI = { settings: {"webui.needmessage": true}, showFlags: 0xffff,
    systemInfo: {rTorrent: {apiVersion: 10, iVersion: 0x908, started: true}} };
  for (const path of ["../lang/en.js", "../js/common.js", "../js/content.js", "../js/rtorrent.js"]) load(path);
  correctContent();
  window.__httprpc = { enabled: true };
  load("../plugins/httprpc/init.js", "(function(){var plugin=window.__httprpc;", "})();");
  window.noty = jest.fn();
  theRequestManager.cid = 7;
  theRequestManager.torrents = {};
}
function row() {
  const values = Array(35).fill("0");
  values[4] = "kept"; values[5] = "1"; values[7] = "1"; values[14] = "";
  values[25] = "/kept"; values[29] = ""; values[30] = "";
  return values;
}
// Transport supplies raw HTTP bytes; jQuery's real JSON converter, Deferred,
// Ajax/getResponse/listResponse and httprpc list wrapper all run unchanged.
$.ajaxTransport("+json +xml", options => {
  if (!window.__w10wire) return;
  return {
    send(_headers, complete) {
      const wire = window.__w10wire;
      wire.options = options;
      complete(wire.status, wire.status===204 ? "No Content" : "OK",
        {text:wire.text}, "Content-Type: application/json\r\n" + (wire.headers || ""));
    },
    abort() {},
  };
});
function exchange(data, status=200, text="success", raw=false) {
  const ok = jest.fn(); const error = jest.fn();
  const wire = {status, text:raw ? data : data===undefined ? "" : JSON.stringify(data)};
  const resolve = () => {
    window.__w10wire = wire;
    try { Ajax("?list=1", true, ok, jest.fn(), error); }
    finally { delete window.__w10wire; }
  };
  return {ok,error,wire,resolve};
}
beforeEach(setup);
describe("valid actual httprpc empty lists", () => {
  test.each([{}, []])("full list t=%p retires a previous cached row", t => {
    theRequestManager.torrents[OLD] = row();
    const x = exchange({t, cid:8, full:1});
    expect(x.resolve).not.toThrow();
    expect(x.wire.options.dataType).toBe("json");
    expect(x.error).not.toHaveBeenCalled();
    expect(x.ok).toHaveBeenCalledTimes(1);
    expect(x.ok.mock.calls[0][0].torrents).toEqual({});
    expect(theRequestManager.torrents).toEqual({});
    expect(theRequestManager.cid).toBe(8);
  });
  test("valid empty incremental delta retains the cached row", () => {
    theRequestManager.torrents[OLD] = row();
    const x = exchange({t:[], cid:8});
    expect(x.resolve).not.toThrow();
    expect(Object.keys(x.ok.mock.calls[0][0].torrents)).toEqual([OLD]);
    expect(x.error).not.toHaveBeenCalled();
  });
});
describe("invalid httprpc lists must reach the caller error path before cache writes", () => {
  test.each([
    ["204 absent body", undefined, 204, "nocontent"],
    ["JSON null", null],
    ["missing t", {}],
    ["null t", {t:null,cid:99}],
    ["nonempty array t", {t:[1],cid:99}],
    ["null row", {t:{[BAD]:null},cid:99}],
    ["short row after deletion", {t:{[BAD]:["0"]},cid:99,d:[OLD]}],
  ])("%s", (_name, body, status=200, text="success") => {
    const kept = row(); theRequestManager.torrents[OLD] = kept;
    const x = exchange(body,status,text);
    expect(x.resolve).not.toThrow();
    expect(x.ok).not.toHaveBeenCalled();
    expect(x.error).toHaveBeenCalledTimes(1);
    expect(noty).not.toHaveBeenCalled();
    expect(theRequestManager.cid).toBe(7);
    expect(theRequestManager.torrents).toEqual({[OLD]:kept});
  });
});

test("jQuery syntax parsererror reaches the error callback without success/cache writes", () => {
  const kept=row(); theRequestManager.torrents[OLD]=kept;
  const x=exchange('{"t":',200,"success",true);
  expect(x.resolve).not.toThrow();
  expect(x.ok).not.toHaveBeenCalled();
  expect(x.error).toHaveBeenCalledTimes(1);
  expect(x.error.mock.calls[0][0]).toContain("parsererror");
  expect(theRequestManager.cid).toBe(7);
  expect(theRequestManager.torrents).toEqual({[OLD]:kept});
});

test("a legitimate sparse row object merges a changed cell into its cached row", () => {
  const kept=row(); theRequestManager.torrents[OLD]=kept;
  const x=exchange({t:{[OLD]:{12:"42"}},cid:8});
  expect(x.resolve).not.toThrow();
  expect(x.error).not.toHaveBeenCalled();
  expect(x.ok.mock.calls[0][0].torrents[OLD].dl).toBe(42);
  expect(x.ok.mock.calls[0][0].torrents[OLD].name).toBe("kept");
  expect(theRequestManager.torrents[OLD]).toBe(kept);
});


test.each([
  {t:{},cid:8.5}, {t:{},cid:"8"}, {t:{},cid:8,d:{}}, {t:{},cid:8,d:[null]},
  {t:{},cid:8,full:"1"}, {t:{[BAD]:{name:"bad"}},cid:8},
  {t:{[OLD]:{29:null}},cid:8},
  {t:{[OLD]:{name:"bad"}},cid:8},
  {t:{[OLD]:{12:{}}},cid:8},
  {t:{invalid:row()},cid:8},
  {t:{[BAD]:Object.assign(row(),{4:1})},cid:8},
  {t:{[BAD]:Object.assign(row(),{25:1})},cid:8},
  {t:{[BAD]:Object.assign(row(),{29:1})},cid:8},
  {t:{[OLD]:{12:"42"}},cid:8,full:1},
])("invalid document %p preserves the cache", body => {
  const kept=row(); theRequestManager.torrents[OLD]=kept;
  const x=exchange(body);
  expect(x.resolve).not.toThrow();
  expect(x.ok).not.toHaveBeenCalled();
  expect(x.error).toHaveBeenCalledTimes(1);
  expect(noty).not.toHaveBeenCalled();
  expect(theRequestManager.cid).toBe(7);
  expect(theRequestManager.torrents).toEqual({[OLD]:kept});
});

test("a late bad row does not apply an earlier valid update or deletion", () => {
  const kept=row(); theRequestManager.torrents[OLD]=kept;
  const x=exchange({t:{[OLD]:{12:"42"},[BAD]:["0"]},cid:99,d:[OLD]});
  x.resolve();
  expect(x.ok).not.toHaveBeenCalled();
  expect(x.error).toHaveBeenCalledTimes(1);
  expect(theRequestManager.cid).toBe(7);
  expect(theRequestManager.torrents[OLD]).toBe(kept);
  expect(kept[12]).toBe("0");
  expect(theRequestManager.torrents[BAD]).toBeUndefined();
});

test("new numeric-keyed rows and deletions support array completion callbacks", () => {
  theRequestManager.torrents[OLD]=row();
  const values=Object.assign({},row(),{35:"plugin value"});
  const context={}; const complete=jest.fn(); const error=jest.fn();
  window.__w10wire={status:200,text:JSON.stringify({t:{[BAD]:values},cid:8,d:[OLD]})};
  try { Ajax("?list=1",true,[complete,context,"extra"],jest.fn(),error); }
  finally { delete window.__w10wire; }
  expect(error).not.toHaveBeenCalled();
  expect(complete).toHaveBeenCalledTimes(1);
  expect(complete.mock.instances[0]).toBe(context);
  expect(complete.mock.calls[0][1]).toBe("extra");
  expect(Object.keys(complete.mock.calls[0][0].torrents)).toEqual([BAD]);
  expect(theRequestManager.torrents[BAD][35]).toBe("plugin value");
});

test("direct getResponse refuses before changing cid or cached rows", () => {
  const stub=new rTorrentStub("?list=1");
  theRequestManager.torrents[OLD]=row();
  expect(stub.getResponse({t:{[BAD]:["0"]},cid:99,d:[OLD]})).toBeUndefined();
  expect(stub.isError()).toBe(1);
  expect(theRequestManager.cid).toBe(7);
  expect(Object.keys(theRequestManager.torrents)).toEqual([OLD]);
});

test("a rejected list reaches the error callback with the arguments of a failed request", () => {
  const headers="X-rTorrent-State: some-state\r\n";
  const failed=exchange('{"t":',200,"success",true); failed.wire.headers=headers;
  const rejected=exchange({t:null,cid:99}); rejected.wire.headers=headers;
  failed.resolve(); rejected.resolve();
  expect(failed.error).toHaveBeenCalledTimes(1);
  expect(rejected.error).toHaveBeenCalledTimes(1);
  const [failure]=failed.error.mock.calls; const [rejection]=rejected.error.mock.calls;
  expect(rejection).toHaveLength(failure.length);
  expect(rejection).toEqual([failure[0], "Invalid list response", failure[2]]);
  expect(failure).toEqual(["200 [parsererror,list]", '{"t":', "some-state"]);
});

test("without an error callback a rejected list is still shown once", () => {
  const ok=jest.fn();
  window.__w10wire={status:200,text:JSON.stringify({t:null,cid:99})};
  try { Ajax("?list=1",true,ok,jest.fn()); }
  finally { delete window.__w10wire; }
  expect(ok).not.toHaveBeenCalled();
  expect(noty).toHaveBeenCalledTimes(1);
  expect(noty).toHaveBeenCalledWith("Invalid list response [list]","error");
  expect(theRequestManager.cid).toBe(7);
});

test("the caller error callback releases its loading state once", () => {
  let loading=true; const error=jest.fn(() => {loading=false;});
  window.__w10wire={status:204,text:""};
  try { Ajax("?list=1",true,jest.fn(),jest.fn(),error); }
  finally { delete window.__w10wire; }
  expect(error).toHaveBeenCalledTimes(1);
  expect(loading).toBe(false);
});

test("valid XML and XML faults keep their existing response path", () => {
  window.__httprpc.enabled=false;
  const stub=new rTorrentStub("?list=1");
  expect(stub.getResponse($.parseXML('<methodResponse><params><param><value><array><data/></array></value></param></params></methodResponse>')).torrents).toEqual({});
  const fault=new rTorrentStub("?list=1");
  fault.getResponse($.parseXML('<methodResponse><fault><value><struct><member><name>faultCode</name><value><int>1</int></value></member><member><name>faultString</name><value><string>refused</string></value></member></struct></value></fault></methodResponse>'));
  expect(fault.xmlRPCFault).toBe(true);
  expect(fault.isError()).toBeGreaterThan(0);
  const absent=new rTorrentStub("?list=1");
  expect(absent.getResponse(undefined)).toBe("");
  expect(absent.isError()).toBe(0);
});


test("XML HTTP204 keeps its existing completion behavior", () => {
  window.__httprpc.enabled=false;
  const complete=jest.fn(); const error=jest.fn();
  window.__w10wire={status:204,text:""};
  try { Ajax("?list=1",true,complete,jest.fn(),error); }
  finally { delete window.__w10wire; }
  expect(error).not.toHaveBeenCalled();
  expect(complete).toHaveBeenCalledTimes(1);
  expect(complete).toHaveBeenCalledWith("");
});


test("large deltas do not rescan the deletion array for every updated row", () => {
  const t={}; const d=[];
  for(let i=0; i<1000; i++) {
    const hash=i.toString(16).padStart(40,"0");
    theRequestManager.torrents[hash]=row();
    t[hash]={12:"42"};
    d.push((i+1000).toString(16).padStart(40,"0"));
  }
  const search=jest.spyOn(d,"indexOf");
  const stub=new rTorrentStub("?list=1");
  const result=stub.getResponse({t,d,cid:8});
  expect(stub.isError()).toBe(0);
  expect(Object.keys(result.torrents)).toHaveLength(1000);
  expect(Object.values(result.torrents).every(torrent => torrent.dl===42)).toBe(true);
  expect(search).not.toHaveBeenCalled();
  expect(theRequestManager.cid).toBe(8);
});

test("a deleted cached row cannot supply missing cells to an incoming sparse row", () => {
  const kept=row(); theRequestManager.torrents[OLD]=kept;
  const x=exchange({t:{[OLD]:{12:"42"}},d:[OLD],cid:8});
  expect(x.resolve).not.toThrow();
  expect(x.error).toHaveBeenCalledTimes(1);
  expect(x.ok).not.toHaveBeenCalled();
  expect(theRequestManager.torrents[OLD]).toBe(kept);
  expect(kept[12]).toBe("0");
  expect(theRequestManager.cid).toBe(7);
});
