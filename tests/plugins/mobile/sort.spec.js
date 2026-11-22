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

      // Mock reorderRows to spy on whether animation was requested
      const origReorder = plugin.reorderRows;
      let reorderCalled = false;
      let reorderAnimated = false;
      plugin.reorderRows = function(container, nodes, animate) {
        reorderCalled = true;
        reorderAnimated = animate;
        origReorder.call(this, container, nodes, animate);
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
      expect(reorderAnimated).toBe(true);

      domOrder = $("#torrentsList #list table tbody tr").map((_, el) => el.id).get();
      expect(domOrder).toEqual([hash1, hash2, hash3]);

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

    it("assigns view-transition-name to table rows", () => {
      plugin.sort = "name";
      const torrents = {
        [hash1]: { name: "Torrent 1", size: 1000, state: 1, done: 1000, ul: 0, dl: 0, eta: -1, ratio: 0, label: "" },
      };
      plugin.processTorrents(torrents, true);
      const row = document.getElementById(hash1);
      expect(row.style.viewTransitionName || row.getAttribute("style")).toMatch(/tr-1111111111111111111111111111111111111111/);
    });

    it("skips animation for large lists (> 200 items)", () => {
      plugin.sort = "-ul";
      const manyTorrents = {};
      for (let i = 0; i < 205; i++) {
        const h = "a" + i.toString().padStart(39, "0");
        manyTorrents[h] = { name: `T ${i}`, size: 1000, state: 1, done: 1000, ul: i, dl: 0, eta: -1, ratio: 0, label: "" };
      }
      plugin.processTorrents(manyTorrents, true);

      const origReorder = plugin.reorderRows;
      let reorderAnimated = null;
      plugin.reorderRows = function(container, nodes, animate) {
        reorderAnimated = animate;
        origReorder.call(this, container, nodes, animate);
      };

      // Change speed of first torrent
      manyTorrents["a" + "0".padStart(39, "0")].ul = 99999;
      plugin.processTorrents(manyTorrents);

      expect(reorderAnimated).toBe(false);
      plugin.reorderRows = origReorder;
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

  describe("reorderRows transition behaviors", () => {
    it("calls document.startViewTransition when available", () => {
      const tbody = document.createElement("tbody");
      const r1 = document.createElement("tr");
      r1.id = "r1";
      const r2 = document.createElement("tr");
      r2.id = "r2";
      tbody.appendChild(r1);
      tbody.appendChild(r2);

      let transitionCalled = false;
      document.startViewTransition = (cb) => {
        transitionCalled = true;
        cb();
      };

      plugin.reorderRows(tbody, [r2, r1], true);

      expect(transitionCalled).toBe(true);
      expect(Array.from(tbody.children).map(c => c.id)).toEqual(["r2", "r1"]);
      delete document.startViewTransition;
    });

    it("falls back to synchronous reorder when prefers-reduced-motion is active", () => {
      const tbody = document.createElement("tbody");
      const r1 = document.createElement("tr");
      r1.id = "r1";
      const r2 = document.createElement("tr");
      r2.id = "r2";
      tbody.appendChild(r1);
      tbody.appendChild(r2);

      let transitionCalled = false;
      document.startViewTransition = (cb) => {
        transitionCalled = true;
        cb();
      };

      const origMatchMedia = window.matchMedia;
      window.matchMedia = (query) => ({
        matches: query.includes("prefers-reduced-motion"),
        addEventListener: () => {},
        addListener: () => {}
      });

      plugin.reorderRows(tbody, [r2, r1], true);

      // Animation skipped due to reduced motion preference
      expect(transitionCalled).toBe(false);
      expect(Array.from(tbody.children).map(c => c.id)).toEqual(["r2", "r1"]);

      window.matchMedia = origMatchMedia;
      delete document.startViewTransition;
    });

    it("prefers container.startViewTransition when available", () => {
      const tbody = document.createElement("tbody");
      const r1 = document.createElement("tr");
      r1.id = "r1";
      const r2 = document.createElement("tr");
      r2.id = "r2";
      tbody.appendChild(r1);
      tbody.appendChild(r2);

      let containerTransitionCalled = false;
      let docTransitionCalled = false;
      tbody.startViewTransition = (cb) => {
        containerTransitionCalled = true;
        cb();
      };
      document.startViewTransition = (cb) => {
        docTransitionCalled = true;
        cb();
      };

      plugin.reorderRows(tbody, [r2, r1], true);

      expect(containerTransitionCalled).toBe(true);
      expect(docTransitionCalled).toBe(false);
      expect(Array.from(tbody.children).map(c => c.id)).toEqual(["r2", "r1"]);
      delete document.startViewTransition;
    });

    it("animates via FLIP transforms when View Transitions are unavailable", (done) => {
      const tbody = document.createElement("tbody");
      const r1 = document.createElement("tr");
      r1.id = "r1";
      const r2 = document.createElement("tr");
      r2.id = "r2";
      tbody.appendChild(r1);
      tbody.appendChild(r2);

      let r1Top = 0;
      let r2Top = 50;

      r1.getBoundingClientRect = () => ({ top: r1Top, bottom: r1Top + 50, left: 0, right: 100, width: 100, height: 50 });
      r2.getBoundingClientRect = () => ({ top: r2Top, bottom: r2Top + 50, left: 0, right: 100, width: 100, height: 50 });

      // When moved, r2 becomes first (top: 0), r1 becomes second (top: 50)
      const origAppend = tbody.appendChild;
      tbody.appendChild = function(node) {
        origAppend.call(this, node);
        if (node.id === "r2") {
          r2Top = 0;
          r1Top = 50;
        }
      };

      const origRAF = window.requestAnimationFrame;
      window.requestAnimationFrame = (fn) => setTimeout(fn, 10);

      plugin.reorderRows(tbody, [r2, r1], true);

      // Invert stage applied
      expect(r2.style.transform).toBe("translateY(50px)");
      expect(r1.style.transform).toBe("translateY(-50px)");

      setTimeout(() => {
        // Play stage clears transform
        expect(r2.style.transform).toBe("");
        expect(r1.style.transform).toBe("");
        window.requestAnimationFrame = origRAF;
        done();
      }, 50);
    });
  });
});
