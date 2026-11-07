import { readFileSync } from "fs";

window.$ = require("jquery");

function h(char) {
  return Array.from({ length: 40 }, () => char).join("");
}

function loadWebUI() {
  window.theUILang = new Proxy(
    {},
    {
      get: (_target, prop) => prop,
    }
  );
  window.theFormatter = {};
  window.TYPE_STRING = "string";
  window.TYPE_NUMBER = "number";
  window.TYPE_PROGRESS = "progress";
  window.TYPE_PEERS = "peers";
  window.TYPE_SEEDS = "seeds";
  window.ALIGN_RIGHT = "right";
  window.dxSTable = function () {};
  window.rSpeedGraph = function () {};
  window.rSpeedGraph.prototype.addData = jest.fn();
  window.Timer = function () {};

  let code = readFileSync("../js/webui.js", { encoding: "utf-8" });
  code = code.replace(/\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/, "");
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

function makeTaskQueue() {
  const queue = {
    reset: jest.fn(() => queue),
    map: jest.fn((items, callback) => {
      items.forEach(callback);
      return queue;
    }),
    enqueueFunc: jest.fn((callback) => {
      callback();
      return queue;
    }),
    run: jest.fn(() => Promise.resolve()),
  };
  return queue;
}

describe("webui stale details", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    loadWebUI();
    window.requestAnimationFrame = (callback) => {
      callback();
      return 1;
    };
    window.cancelAnimationFrame = jest.fn();
  });

  it("clears selected details when the selected torrent disappears from list updates", () => {
    const oldHash = h("A");
    const newHash = h("B");
    const table = {
      setLazy: jest.fn(),
      setRowById: jest.fn(),
      removeRow: jest.fn(),
    };
    const statistic = {
      scan: jest.fn(),
      upload: 0,
      download: 0,
    };

    Object.assign(theWebUI, {
      systemInfo: { rTorrent: { started: true } },
      dID: oldHash,
      activeView: "TrackerList",
      torrents: {
        [oldHash]: { name: "old", downloaded: 1 },
      },
      files: { [oldHash]: ["file"] },
      dirs: { [oldHash]: ["dir"] },
      peers: { [oldHash]: ["peer"] },
      trackers: { [oldHash]: ["tracker"] },
      taskAddTorrents: makeTaskQueue(),
      categoryList: {
        statistic: {
          empty: () => statistic,
        },
        syncAfterScan: jest.fn(),
      },
      getTable: jest.fn(() => table),
      getStatusIcon: jest.fn(() => ["icon", "status"]),
      filterByLabel: jest.fn(),
      getAllTrackers: jest.fn(),
      getTotal: jest.fn(),
      getOpenStatus: jest.fn(),
      setInterval: jest.fn(),
      updateViewRows: jest.fn(),
      loadTorrents: jest.fn(),
      updateTrackers: jest.fn(),
      clearDetails: jest.fn(),
    });

    theWebUI.addTorrents({
      torrents: {
        [newHash]: { name: "new", downloaded: 2 },
      },
    });

    expect(theWebUI.dID).toBe("");
    expect(theWebUI.clearDetails).toHaveBeenCalledTimes(1);
    expect(theWebUI.updateTrackers).not.toHaveBeenCalledWith(oldHash);
    expect(theWebUI.files[oldHash]).toBeUndefined();
    expect(theWebUI.dirs[oldHash]).toBeUndefined();
    expect(theWebUI.peers[oldHash]).toBeUndefined();
    expect(theWebUI.trackers[oldHash]).toBeUndefined();
  });

  it("discards peer response and preserves clean cache when torrent is not in torrents list", () => {
    const oldHash = h("A");
    const currentHash = h("B");
    const table = {
      updateRows: jest.fn(),
      clearRows: jest.fn(),
    };

    Object.assign(theWebUI, {
      torrents: {
        [currentHash]: { name: "current" },
      },
      dID: currentHash,
      peers: {},
      getTable: jest.fn(() => table),
    });

    theWebUI.addPeers(
      {
        p1: { name: "1.2.3.4", port: 80, ip: "1.2.3.4", attr: {} },
      },
      oldHash
    );

    expect(theWebUI.peers[oldHash]).toBeUndefined();
    expect(table.clearRows).not.toHaveBeenCalled();
    expect(table.updateRows).not.toHaveBeenCalled();
  });

  it("updates cache and table when peer response is for currently selected active torrent", () => {
    const currentHash = h("B");
    const table = {
      updateRows: jest.fn(),
      clearRows: jest.fn(),
    };

    Object.assign(theWebUI, {
      torrents: {
        [currentHash]: { name: "current" },
      },
      dID: currentHash,
      peers: {},
      getTable: jest.fn(() => table),
    });

    theWebUI.addPeers(
      {
        p1: { name: "1.2.3.4", port: 80, ip: "1.2.3.4", attr: {} },
      },
      currentHash
    );

    expect(theWebUI.peers[currentHash]).toBeDefined();
    expect(theWebUI.peers[currentHash].p1.name).toBe("1.2.3.4:80");
    expect(table.updateRows).toHaveBeenCalledWith(theWebUI.peers[currentHash]);
  });

  it("updates cache but not table when peer response is for active unselected torrent", () => {
    const currentHash = h("B");
    const unselectedHash = h("C");
    const table = {
      updateRows: jest.fn(),
      clearRows: jest.fn(),
    };

    Object.assign(theWebUI, {
      torrents: {
        [currentHash]: { name: "current" },
        [unselectedHash]: { name: "unselected" },
      },
      dID: currentHash,
      peers: {},
      getTable: jest.fn(() => table),
    });

    theWebUI.addPeers(
      {
        p1: { name: "5.6.7.8", port: 51413, ip: "5.6.7.8", attr: {} },
      },
      unselectedHash
    );

    expect(theWebUI.peers[unselectedHash]).toBeDefined();
    expect(theWebUI.peers[unselectedHash].p1.name).toBe("5.6.7.8:51413");
    expect(table.updateRows).not.toHaveBeenCalled();
    expect(table.clearRows).not.toHaveBeenCalled();
  });
});

describe("webui list request errors", () => {
  const oldHash = h("A");
  let table;
  let rows;

  beforeEach(() => {
    jest.useFakeTimers();
    document.body.innerHTML = '<div id="cover"></div>';
    Object.defineProperty(document.getElementById("cover"), "offsetWidth", { value: 1 });
    loadWebUI();
    window.iv = Number;
    window.noty = jest.fn();
    window.requestAnimationFrame = (callback) => {
      callback();
      return 1;
    };
    window.cancelAnimationFrame = jest.fn();
    rows = { [oldHash]: { name: "existing" } };
    table = {
      setLazy: jest.fn(),
      setRowById: jest.fn((torrent, hash) => { rows[hash] = torrent; }),
      removeRow: jest.fn((hash) => { delete rows[hash]; }),
    };
    const statistic = { scan: jest.fn(), upload: 0, download: 0 };
    Object.assign(theWebUI, {
      systemInfo: { rTorrent: { started: true } },
      settings: { "webui.retry_on_error": 4, "webui.update_interval": 2000 },
      timer: { start: jest.fn(), stop: jest.fn(), interval: 25 },
      interval: -1,
      updTimer: 0,
      firstLoad: true,
      torrents: { [oldHash]: { name: "existing" } },
      dID: oldHash,
      files: { [oldHash]: ["file"] },
      dirs: { [oldHash]: ["dir"] },
      peers: { [oldHash]: ["peer"] },
      trackers: { [oldHash]: ["tracker"] },
      taskAddTorrents: makeTaskQueue(),
      categoryList: { statistic: { empty: () => statistic }, syncAfterScan: jest.fn() },
      requestWithTimeout: jest.fn(),
      getTable: jest.fn(() => table),
      getStatusIcon: jest.fn(() => ["icon", "status"]),
      setSpeedValues: jest.fn(),
      filterByLabel: jest.fn(),
      getAllTrackers: jest.fn(),
      getTotal: jest.fn(),
      updateViewRows: jest.fn(),
      loadTorrents: jest.fn(),
      clearDetails: jest.fn(),
      update: jest.fn(),
      resize: jest.fn(),
    });
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
  });

  function respond(response) {
    const [callback, scope] = theWebUI.requestWithTimeout.mock.calls[0][1];
    return callback.call(scope, response);
  }

  function expectErrorRetry() {
    expect(document.getElementById("cover").style.display).toBe("none");
    jest.advanceTimersByTime(0);
    expect(theWebUI.resize).toHaveBeenCalledTimes(1);
    jest.advanceTimersByTime(3999);
    expect(theWebUI.update).not.toHaveBeenCalled();
    jest.advanceTimersByTime(1);
    expect(theWebUI.update).toHaveBeenCalledTimes(1);
  }

  it.each([
    ["list=1", "403 [error,list]", "Forbidden", true],
    ["checktorrent&hash=" + h("B") + "&list=1", "503 [error,checktorrent]", "Service unavailable", true],
    ["list=1", "503 [error,list]", "Service unavailable", false],
  ])("preserves confirmed daemon state after an HTTP refusal from %s", (query, status, body, started) => {
    theWebUI.systemInfo.rTorrent.started = started;
    theWebUI.updTimer = window.setTimeout(theWebUI.update, 100);
    theWebUI.getTorrents(query);
    const [url, , , onError] = theWebUI.requestWithTimeout.mock.calls[0];
    expect(url).toBe("?" + (query === "list=1" ? query : "action=" + query) + "&getmsg=1");
    onError(status, body);

    expect(theWebUI.systemInfo.rTorrent.started).toBe(started);
    expect(window.noty).toHaveBeenCalledTimes(1);
    expect(window.noty).toHaveBeenCalledWith("Bad response from server: (" + status + ") " + body, "error");
    expectErrorRetry();
  });

  it("reports HTTP errors without scheduling an update when error retry is disabled", () => {
    theWebUI.settings["webui.retry_on_error"] = 0;
    theWebUI.getTorrents("list=1");
    theWebUI.requestWithTimeout.mock.calls[0][3]("403 [error,list]", "Forbidden");

    expect(theWebUI.systemInfo.rTorrent.started).toBe(true);
    expect(window.noty).toHaveBeenCalledWith("Bad response from server: (403 [error,list]) Forbidden", "error");
    jest.runAllTimers();
    expect(theWebUI.update).not.toHaveBeenCalled();
  });

  it("keeps the timeout notification and normal polling interval", () => {
    theWebUI.getTorrents("list=1");
    theWebUI.requestWithTimeout.mock.calls[0][2]();

    expect(theWebUI.systemInfo.rTorrent.started).toBe(true);
    expect(window.noty).toHaveBeenCalledWith("Request_timed_out", "alert");
    expect(document.getElementById("cover").style.display).toBe("none");
    jest.advanceTimersByTime(2099);
    expect(theWebUI.update).not.toHaveBeenCalled();
    jest.advanceTimersByTime(1);
    expect(theWebUI.update).toHaveBeenCalledTimes(1);
  });

  it.each([
    ["bodyless 204", ""],
    ["undefined", undefined],
    ["null", null],
    ["false", false],
    ["number", 1],
    ["text", "unexpected body"],
    ["array", []],
    ["missing torrents", {}],
    ["null torrents", { torrents: null }],
    ["array torrents", { torrents: [] }],
    ["boolean torrents", { torrents: true }],
    ["number torrents", { torrents: 1 }],
    ["text torrents", { torrents: "invalid" }],
  ])("rejects %s without restoring the connection or changing confirmed rows", (_name, response) => {
    theWebUI.systemInfo.rTorrent.started = false;
    const savedTorrents = theWebUI.torrents;
    theWebUI.getTorrents("list=1");
    expect(() => respond(response)).not.toThrow();

    expect(theWebUI.systemInfo.rTorrent.started).toBe(false);
    expect(theWebUI.torrents).toBe(savedTorrents);
    expect(rows).toEqual({ [oldHash]: { name: "existing" } });
    expect(theWebUI.dID).toBe(oldHash);
    expect(theWebUI.files[oldHash]).toEqual(["file"]);
    expect(theWebUI.getTable).not.toHaveBeenCalled();
    expect(theWebUI.taskAddTorrents.reset).not.toHaveBeenCalled();
    expect(window.noty).toHaveBeenCalledTimes(1);
    expect(window.noty).toHaveBeenCalledWith("Bad response from server: (Invalid torrent list response) Missing torrents", "error");
    expectErrorRetry();
  });

  it("does not retry malformed responses when error retry is disabled", () => {
    theWebUI.settings["webui.retry_on_error"] = 0;
    theWebUI.getTorrents("list=1");
    expect(() => respond({})).not.toThrow();

    expect(window.noty).toHaveBeenCalledWith("Bad response from server: (Invalid torrent list response) Missing torrents", "error");
    jest.runAllTimers();
    expect(theWebUI.update).not.toHaveBeenCalled();
  });

  it.each([true, false])("accepts a valid empty torrent map with previous daemon state %s", async (started) => {
    theWebUI.systemInfo.rTorrent.started = started;
    theWebUI.getTorrents("list=1");
    respond({ torrents: {} });
    await Promise.resolve();

    expect(theWebUI.systemInfo.rTorrent.started).toBe(true);
    expect(theWebUI.torrents).toEqual({});
    expect(rows).toEqual({});
    expect(theWebUI.dID).toBe("");
    expect(theWebUI.files[oldHash]).toBeUndefined();
    expect(table.setLazy.mock.calls).toEqual([[true], [false]]);
    if (started)
      expect(window.noty).not.toHaveBeenCalled();
    else
      expect(window.noty).toHaveBeenCalledWith("linkTorTorrentRestored", "success");
    jest.advanceTimersByTime(2099);
    expect(theWebUI.update).not.toHaveBeenCalled();
    jest.advanceTimersByTime(1);
    expect(theWebUI.update).toHaveBeenCalledTimes(1);
  });
});
