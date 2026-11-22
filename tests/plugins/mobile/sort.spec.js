import { readFileSync } from "fs";

window.$ = require("jquery");
window.jQuery = window.$;

for (const src of ["../lang/en.js", "../js/common.js", "../js/rtorrent.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

window.matchMedia =
  window.matchMedia ||
  (() => ({ matches: false, addEventListener: () => {}, addListener: () => {} }));
window.scrollTo = () => {};

window.theWebUI = {
  deltaTime: 0,
  settings: {},
  torrents: {},
  getTrackerName: (url) => url,
  config: function () {},
  requestWithTimeout: function(url, callback) {
    if (callback) callback({ torrents: {} });
  },
  requestWithoutTimeout: function(url, callback) {
    if (callback) callback({});
  },
  save: function() {},
  sizeDecimalPlaces: () => 2,
  getStatusIcon: () => ["Status_Up", "Seeding"],
  categoryList: {
    panelLabelAttribs: {
      plabel: null
    }
  }
};
window.thePlugins = { isInstalled: () => false, get: () => null };
window.theConverter = {
  speed: (v) => v + " B/s",
  bytes: (v) => v + " B",
  time: (v) => v + "s",
  round: (v, d) => Number(v).toFixed(d)
};
window.theDialogManager = { addHandler: function () {} };
window.rTorrentStub = function() {
  this.content = "";
  this.commands = [];
};

window.__mobile_sort = {
  loadLang: function () {},
  attachPageToMenu: function () {},
  disable: function () {},
  langLoaded: true,
  allStuffLoaded: true,
  path: "../plugins/mobile/",
  request: function(query, callback) {
    if (callback) callback({});
  }
};

{
  let code = readFileSync("../plugins/mobile/init.js", { encoding: "utf-8" });
  code = code.replace(/plugin\.disableOthers\(\);\s*$/, "");
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function () { var plugin = window.__mobile_sort;\n" + code + "\n})();";
  document.body.appendChild(scriptEl);
}

const plugin = window.__mobile_sort;
window.mobile = plugin;

describe("mobile dynamic sorting and DOM reordering", () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="torrentsList" class="mainContainer" style="display: block;">
        <div id="list">
          <table class="table">
            <tbody></tbody>
          </table>
        </div>
        <div id="sortLiveStatus" role="status" class="visually-hidden"></div>
      </div>
    `;
    plugin.rowsPrev = {};
    plugin.rowsPrimed = false;
    plugin.currentPage = 'torrentsList';
  });

  describe("dynamicSort", () => {
    it("sorts upload speed in descending order (-ul) with zeroes handled properly", () => {
      const fn = plugin.dynamicSort("-ul");
      const a = { hash: "1", name: "A", ul: 0 };
      const b = { hash: "2", name: "B", ul: 500 };
      const c = { hash: "3", name: "C", ul: 200 };

      const list = [a, b, c];
      list.sort(fn);
      expect(list.map(t => t.hash)).toEqual(["2", "3", "1"]);
    });

    it("sorts upload speed in ascending order (ul)", () => {
      const fn = plugin.dynamicSort("ul");
      const a = { hash: "1", name: "A", ul: 0 };
      const b = { hash: "2", name: "B", ul: 500 };
      const c = { hash: "3", name: "C", ul: 200 };

      const list = [b, a, c];
      list.sort(fn);
      expect(list.map(t => t.hash)).toEqual(["1", "3", "2"]);
    });

    it("tie-breaks by name and then hash deterministically", () => {
      const fn = plugin.dynamicSort("-ul");
      const a = { hash: "b_hash", name: "Same", ul: 100 };
      const b = { hash: "a_hash", name: "Same", ul: 100 };
      const c = { hash: "c_hash", name: "Another", ul: 100 };

      const list = [a, b, c];
      list.sort(fn);
      // 'Another' before 'Same'; for same names, 'a_hash' before 'b_hash'
      expect(list.map(t => t.hash)).toEqual(["c_hash", "a_hash", "b_hash"]);
    });
  });

  describe("processTorrents DOM reordering on updates", () => {
    const hash1 = "1111111111111111111111111111111111111111";
    const hash2 = "2222222222222222222222222222222222222222";
    const hash3 = "3333333333333333333333333333333333333333";

    it("reevaluates sort order and reorders DOM rows when a torrent speed changes", () => {
      plugin.sort = "-ul";

      // Initial state: Torrent 2 is fastest (500), then Torrent 3 (200), then Torrent 1 (50)
      const initialTorrents = {
        [hash1]: { name: "Torrent 1", size: 1000, state: 1, done: 1000, ul: 50, dl: 0, eta: -1, ratio: 1000, label: "" },
        [hash2]: { name: "Torrent 2", size: 1000, state: 1, done: 1000, ul: 500, dl: 0, eta: -1, ratio: 1000, label: "" },
        [hash3]: { name: "Torrent 3", size: 1000, state: 1, done: 1000, ul: 200, dl: 0, eta: -1, ratio: 1000, label: "" },
      };

      // Initial render with singleUpdate = true
      plugin.processTorrents(initialTorrents, true);

      let domOrder = $("#torrentsList #list table tbody tr").map((_, el) => el.id).get();
      expect(domOrder).toEqual([hash2, hash3, hash1]);

      // Mock reorderRows to spy on whether highlight was requested
      const origReorder = plugin.reorderRows;
      let reorderCalled = false;
      let movedNodesPassed = [];
      plugin.reorderRows = function(container, nodes, movedNodes) {
        reorderCalled = true;
        movedNodesPassed = movedNodes || [];
        origReorder.call(this, container, nodes, movedNodes);
      };

      // Subsequent fetch without singleUpdate: Torrent 1's speed skyrockets to 1000!
      // New expected order: Torrent 1 (1000), Torrent 2 (500), Torrent 3 (200)
      const updatedTorrents = {
        [hash1]: { name: "Torrent 1", size: 1000, state: 1, done: 1000, ul: 1000, dl: 0, eta: -1, ratio: 1000, label: "" },
        [hash2]: { name: "Torrent 2", size: 1000, state: 1, done: 1000, ul: 500, dl: 0, eta: -1, ratio: 1000, label: "" },
        [hash3]: { name: "Torrent 3", size: 1000, state: 1, done: 1000, ul: 200, dl: 0, eta: -1, ratio: 1000, label: "" },
      };

      plugin.processTorrents(updatedTorrents);

      expect(reorderCalled).toBe(true);
      expect(movedNodesPassed.length).toBeGreaterThan(0);

      domOrder = $("#torrentsList #list table tbody tr").map((_, el) => el.id).get();
      expect(domOrder).toEqual([hash1, hash2, hash3]);

      // Highlight applied to cells that changed rank
      const row1Cell = $(`#${hash1}`).children("td");
      expect(row1Cell.hasClass("rank-highlight")).toBe(true);

      // Next fetch where order did NOT change: Torrent 1 speed stays high
      reorderCalled = false;
      const unchangedOrderTorrents = {
        [hash1]: { name: "Torrent 1", size: 1000, state: 1, done: 1000, ul: 1100, dl: 0, eta: -1, ratio: 1000, label: "" },
        [hash2]: { name: "Torrent 2", size: 1000, state: 1, done: 1000, ul: 500, dl: 0, eta: -1, ratio: 1000, label: "" },
        [hash3]: { name: "Torrent 3", size: 1000, state: 1, done: 1000, ul: 200, dl: 0, eta: -1, ratio: 1000, label: "" },
      };
      plugin.processTorrents(unchangedOrderTorrents);
      // Even though row 1's speed changed from 1000 to 1100, its placement in the list did not change
      expect(reorderCalled).toBe(false);

      plugin.reorderRows = origReorder;
    });

    it("does not apply rank-highlight on initial render", () => {
      plugin.sort = "-ul";
      const torrents = {
        [hash1]: { name: "Torrent 1", size: 1000, state: 1, done: 1000, ul: 50, dl: 0, eta: -1, ratio: 1000, label: "" },
        [hash2]: { name: "Torrent 2", size: 1000, state: 1, done: 1000, ul: 500, dl: 0, eta: -1, ratio: 1000, label: "" },
      };
      plugin.processTorrents(torrents, true);
      const cells = $("#torrentsList #list table tbody tr td");
      expect(cells.hasClass("rank-highlight")).toBe(false);
    });

    it("announces sort change in sortLiveStatus on setSort", () => {
      document.body.innerHTML += `
        <div id="torrentSort">
          <select id="sortOption">
            <option value="ul" selected>UL</option>
          </select>
          <input type="radio" id="sort_asc" name="sort_direction">
          <input type="radio" id="sort_desc" name="sort_direction" checked>
        </div>
      `;
      plugin.sort = "name";
      plugin.setSort();

      expect(plugin.sort).toBe("-ul");
      const status = document.getElementById("sortLiveStatus");
      expect(status.textContent).toContain("Upload Speed");
      expect(status.textContent).toContain("Descending");
    });
  });

  describe("reorderRows highlight behaviors", () => {
    beforeEach(() => {
      jest.useFakeTimers();
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    it("reorders rows in DOM and applies rank-highlight to moved nodes", () => {
      const tbody = document.createElement("tbody");
      const r1 = document.createElement("tr");
      r1.id = "r1";
      r1.innerHTML = "<td>Cell 1</td>";
      const r2 = document.createElement("tr");
      r2.id = "r2";
      r2.innerHTML = "<td>Cell 2</td>";
      tbody.appendChild(r1);
      tbody.appendChild(r2);

      // Reorder r2 before r1, passing r2 as moved
      plugin.reorderRows(tbody, [r2, r1], [r2]);

      expect(Array.from(tbody.children).map(c => c.id)).toEqual(["r2", "r1"]);
      expect($(r2).children("td").hasClass("rank-highlight")).toBe(true);
      expect($(r1).children("td").hasClass("rank-highlight")).toBe(false);

      // After 1500ms, rank-highlight should be removed
      jest.advanceTimersByTime(1500);
      expect($(r2).children("td").hasClass("rank-highlight")).toBe(false);
    });

    it("reorders nodes correctly when moveBefore is supported", () => {
      const tbody = document.createElement("tbody");
      const r1 = document.createElement("tr");
      r1.id = "r1";
      r1.innerHTML = "<td>Cell 1</td>";
      const r2 = document.createElement("tr");
      r2.id = "r2";
      r2.innerHTML = "<td>Cell 2</td>";
      tbody.appendChild(r1);
      tbody.appendChild(r2);

      tbody.moveBefore = jest.fn((node, before) => {
        tbody.insertBefore(node, before);
      });

      plugin.reorderRows(tbody, [r2, r1], []);
      expect(tbody.moveBefore).toHaveBeenCalled();
      expect(Array.from(tbody.children).map(c => c.id)).toEqual(["r2", "r1"]);
    });
  });
});
