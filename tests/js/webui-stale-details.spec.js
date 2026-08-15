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
});
