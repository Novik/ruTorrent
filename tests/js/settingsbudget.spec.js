import { readFileSync } from "fs";
window.$ = window.jQuery = require("jquery");

// webui.js needs most of the UI stack, in the order index.html loads it.
for (const src of ["../lang/en.js","../js/sanitize.js","../js/sanitize.config.js",
  "../js/browser.js","../js/common.js","../js/objects.js","../js/options.js",
  "../js/content.js","../js/stable.js","../js/graph.js","../js/plugins.js",
  "../js/rtorrent.js","../js/webui.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

describe("theWebUI.socketAllocBudget", () => {
  afterEach(() => { delete theWebUI.systemInfo; });

  // loadSettings() runs on startup too, before getplugins.php has replied.
  it("reports no budget when systemInfo is not populated yet", () => {
    delete theWebUI.systemInfo;
    expect(theWebUI.socketAllocBudget()).toBe(0);
    theWebUI.systemInfo = {};
    expect(theWebUI.socketAllocBudget()).toBe(0);
  });

  it("reports no budget on an rtorrent that does not report one", () => {
    theWebUI.systemInfo = { rTorrent: { started: true, iVersion: 0x1013 } };
    expect(theWebUI.socketAllocBudget()).toBe(0);
  });

  it("reports the budget when rtorrent supplies it", () => {
    theWebUI.systemInfo = { rTorrent: { socketAllocBudget: 63064 } };
    expect(theWebUI.socketAllocBudget()).toBe(63064);
  });

  it("showSocketAllocBudget() does not throw before systemInfo exists", () => {
    delete theWebUI.systemInfo;
    expect(() => theWebUI.showSocketAllocBudget()).not.toThrow();
  });
});

describe("theWebUI.socketAllocLimit", () => {
  afterEach(() => { delete theWebUI.systemInfo; });

  it("reports no limit before systemInfo is populated", () => {
    delete theWebUI.systemInfo;
    expect(theWebUI.socketAllocLimit("socketFilesAllocMax")).toBe(0);
  });

  it("reports no limit on an rtorrent that does not supply one", () => {
    theWebUI.systemInfo = { rTorrent: { started: true } };
    expect(theWebUI.socketAllocLimit("socketFilesAllocMax")).toBe(0);
    expect(theWebUI.socketAllocLimit("socketFilesAllocMin")).toBe(0);
  });

  it("reports each limit rtorrent supplies", () => {
    theWebUI.systemInfo = { rTorrent: { socketFilesAllocMin: 4, socketFilesAllocMax: 65536, socketHttpAllocMax: 4096 } };
    expect(theWebUI.socketAllocLimit("socketFilesAllocMin")).toBe(4);
    expect(theWebUI.socketAllocLimit("socketFilesAllocMax")).toBe(65536);
    expect(theWebUI.socketAllocLimit("socketHttpAllocMax")).toBe(4096);
  });
});

describe("theWebUI.socketAllocationAccepted", () => {
  const limits = { started: true, socketAllocBudget: 63064,
    socketFilesAllocMin: 4, socketFilesAllocMax: 65536, socketHttpAllocMax: 4096 };

  beforeEach(() => {
    theWebUI.systemInfo = { rTorrent: { ...limits } };
    global.noty = jest.fn();
  });
  afterEach(() => { delete theWebUI.systemInfo; $("#alloc_fixture").remove(); });

  const withFields = (files, http) => {
    $("<div>").attr("id", "alloc_fixture").append(
      $("<input>").attr({ type: "number", id: "max_open_files" }).val(files),
      $("<input>").attr({ type: "number", id: "max_open_http" }).val(http),
    ).appendTo(document.body);
  };

  // The Connection page is removed outright for users without the permission,
  // so the fields the check reads are simply not in the document.
  it("accepts when the Connection page is not present", () => {
    expect(theWebUI.socketAllocationAccepted()).toBe(true);
    expect(global.noty).not.toHaveBeenCalled();
  });

  it("accepts a pair inside every limit", () => {
    withFields(60000, 3000);
    expect(theWebUI.socketAllocationAccepted()).toBe(true);
    expect(global.noty).not.toHaveBeenCalled();
  });

  it("rejects open files below the minimum", () => {
    withFields(2, 100);
    expect(theWebUI.socketAllocationAccepted()).toBe(false);
    expect(global.noty).toHaveBeenCalledWith(expect.stringContaining("4"), "error");
  });

  it("rejects open files above the maximum", () => {
    withFields(70000, 100);
    expect(theWebUI.socketAllocationAccepted()).toBe(false);
    expect(global.noty).toHaveBeenCalledWith(expect.stringContaining("65536"), "error");
  });

  it("rejects HTTP connections above the maximum", () => {
    withFields(1000, 5000);
    expect(theWebUI.socketAllocationAccepted()).toBe(false);
    expect(global.noty).toHaveBeenCalledWith(expect.stringContaining("4096"), "error");
  });

  it("rejects a pair that does not fit the shared budget", () => {
    withFields(63000, 500);
    expect(theWebUI.socketAllocationAccepted()).toBe(false);
    expect(global.noty).toHaveBeenCalledWith(expect.stringContaining("63064"), "error");
  });
});

describe("theWebUI.setSettings socket allocation", () => {
  let requested, saved;

  beforeEach(() => {
    theWebUI.systemInfo = { rTorrent: { started: true, socketAllocBudget: 63064,
      socketFilesAllocMin: 4, socketFilesAllocMax: 65536, socketHttpAllocMax: 4096 } };
    global.noty = jest.fn();
    requested = jest.fn(); saved = jest.fn();
    theWebUI.request = requested; theWebUI.save = saved;
    $("<div>").attr("id", "alloc_fixture").append(
      $("<input>").attr({ type: "number", id: "max_open_files" }).val(70000),
      $("<input>").attr({ type: "number", id: "max_open_http" }).val(100),
      $("<input>").attr({ type: "text", id: "webui.test_setting" }).val("changed"),
    ).appendTo(document.body);
    theWebUI.settings = { "max_open_files": 1024, "max_open_http": 100,
      "webui.test_setting": "original" };
  });
  afterEach(() => { delete theWebUI.systemInfo; $("#alloc_fixture").remove(); });

  it("does not send an out-of-range allocation to rtorrent", () => {
    theWebUI.setSettings();
    expect(requested).not.toHaveBeenCalled();
  });

  // Rejecting the rtorrent write must not throw away the user's other changes.
  it("still saves webui settings when the allocation is rejected", () => {
    theWebUI.setSettings();
    expect(saved).toHaveBeenCalled();
  });
});
