import { readFileSync } from "fs";

window.$ = require("jquery");
window.jQuery = window.$;

for (const src of ["../lang/en.js", "../js/common.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// jsdom has no matchMedia, and the plugin probes it while detecting tablets.
window.matchMedia =
  window.matchMedia ||
  (() => ({ matches: false, addEventListener: () => {}, addListener: () => {} }));

window.theWebUI = {
  deltaTime: 0,
  settings: {},
  torrents: {},
  getTrackerName: (url) => url,
  config: function () {},
  // Consulted by theConverter.bytes when the pane renders sizes.
  sizeDecimalPlaces: () => 2,
  // fillDetails renders the status icon and the label row alongside the
  // times; neither is what this spec is about.
  getStatusIcon: () => "",
};
window.thePlugins = { isInstalled: () => false, get: () => null };
window.theConverter = window.theConverter || {};

// The plugin loader normally supplies `plugin`; expose ours so the test can
// call the real fillDetails afterwards.
window.__mobile = {
  loadLang: function () {},
  attachPageToMenu: function () {},
  // Supplied by the plugin loader in the browser. jQuery.browser.mobile is
  // false under jsdom, so the plugin disables itself as it loads and calls
  // this; fillDetails itself does not depend on the plugin being active.
  disable: function () {},
  langLoaded: true,
  allStuffLoaded: true,
};

{
  const code = readFileSync("../plugins/mobile/init.js", { encoding: "utf-8" });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function () { var plugin = window.__mobile;\n" + code + "\n})();";
  document.body.appendChild(scriptEl);
}

const plugin = window.__mobile;

describe("mobile file response ownership", () => {
  let pending;
  let sent;

  beforeEach(() => {
    document.body.innerHTML = '<div id="detailsFilesPage"></div>';
    plugin.torrent = undefined;
    plugin.files = undefined;
    plugin.filesDownloaded = undefined;
    pending = {};
    sent = [];
    plugin.request = (url, callback) => {
      if (callback) pending[url] = callback;
      else sent.push(url);
    };
  });

  function select(hash, downloaded = 0) {
    plugin.torrent = { hash, downloaded };
    plugin.loadFiles();
  }

  function reply(hash, names) {
    pending['?action=getfiles&hash=' + hash]({
      [hash]: names.map(name => ({ name, size: 100, done: 20, priority: 1 })),
    });
  }

  function changeSecondFilePriority(hash) {
    $('#detailsFilesPage select').eq(1).val('0').trigger('change');
    expect(sent).toEqual(['?action=setprio&hash=' + hash + '&v=1&s=0']);
  }

  // A folder's row carries every file under it. The request names one file
  // index per file, and names no other file: the priority a reader picks for
  // a folder is the priority of that folder's files.
  it("names every file under a folder, and only those, when its priority changes", () => {
    select('A', 20);
    reply('A', ['season/ep1', 'season/ep2', 'readme.txt']);
    $('#detailsFilesPage select').eq(0).val('0').trigger('change');
    expect(sent).toEqual(['?action=setprio&hash=A&v=0&v=1&s=0']);
  });

  it("names the files of a nested folder from the folder's own row", () => {
    select('A', 20);
    reply('A', ['season/extra/behind', 'season/ep1', 'readme.txt']);
    $('#detailsFilesPage select').eq(0).val('2').trigger('change');
    expect(sent).toEqual(['?action=setprio&hash=A&v=0&v=1&s=2']);
  });

  // The whole page is one folder's worth of rows, so the file rows have to
  // keep working beside it.
  it("names one file when a file's own row changes", () => {
    select('A', 20);
    reply('A', ['season/ep1', 'readme.txt']);
    $('#detailsFilesPage select').eq(1).val('0').trigger('change');
    expect(sent).toEqual(['?action=setprio&hash=A&v=1&s=0']);
  });

  it("renders the selected torrent files and applies priority to its own file index", () => {
    select('A', 20);
    reply('A', ['first-a', 'second-a']);
    expect(Object.keys(plugin.files.container)).toEqual(['first-a', 'second-a']);
    expect($('#detailsFilesPage').text()).toContain('second-a');
    expect(plugin.filesDownloaded).toBe(20);
    changeSecondFilePriority('A');
  });

  it("accepts a response while a newer snapshot still selects the same hash", () => {
    select('A', 20);
    plugin.torrent = { hash: 'A', downloaded: 40 };
    reply('A', ['first-a', 'second-a']);
    expect(plugin.filesDownloaded).toBe(40);
    expect(Object.keys(plugin.files.container)).toEqual(['first-a', 'second-a']);
    changeSecondFilePriority('A');
  });

  it("keeps the selected torrent tree when an older torrent response arrives last", () => {
    select('A', 20);
    select('B', 50);
    reply('B', ['first-b', 'second-b']);
    const tree = plugin.files;
    reply('A', ['old-a']);
    expect(plugin.files).toBe(tree);
    expect(Object.keys(plugin.files.container)).toEqual(['first-b', 'second-b']);
    expect($('#detailsFilesPage').text()).not.toContain('old-a');
    expect(plugin.filesDownloaded).toBe(50);
    changeSecondFilePriority('B');
  });

  it("leaves the new torrent page empty until its own response arrives", () => {
    select('A');
    select('B');
    reply('A', ['old-a']);
    expect(plugin.files).toBeUndefined();
    expect($('#detailsFilesPage').children()).toHaveLength(0);
    reply('B', ['first-b', 'second-b']);
    expect(Object.keys(plugin.files.container)).toEqual(['first-b', 'second-b']);
    changeSecondFilePriority('B');
  });

  it("ignores a late response when the old torrent is absent from it", () => {
    select('A');
    select('B');
    reply('B', ['first-b', 'second-b']);
    const tree = plugin.files;
    expect(() => pending['?action=getfiles&hash=A']({})).not.toThrow();
    expect(plugin.files).toBe(tree);
    changeSecondFilePriority('B');
  });

  it.each([undefined, null])("ignores pending files after the selection becomes %s", selection => {
    select('A');
    plugin.torrent = selection;
    expect(() => reply('A', ['old-a'])).not.toThrow();
    expect(plugin.files).toBeUndefined();
    expect(plugin.filesDownloaded).toBeUndefined();
    expect($('#detailsFilesPage').children()).toHaveLength(0);
  });

  it.each([undefined, null])("does not request files without a selection (%s)", selection => {
    plugin.torrent = selection;
    plugin.loadFiles();
    expect(Object.keys(pending)).toEqual([]);
    expect(plugin.files).toBeUndefined();
    expect($('#detailsFilesPage').children()).toHaveLength(0);
  });
});

// The rows fillDetails writes into, with the ids the real markup uses.
function detailsMarkup() {
  document.body.insertAdjacentHTML(
    "beforeend",
    `<div id="torrentDetails">
       <table>
         <tr id="seedtime"><td>Seeding Time</td><td></td></tr>
         <tr id="dateAdded"><td>Added</td><td></td></tr>
         <tr id="created"><td>Created</td><td></td></tr>
       </table>
     </div>`
  );
}

function seedtimeCell() {
  return $("#torrentDetails #seedtime td:last").text();
}

describe("mobile details pane", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    detailsMarkup();
    plugin.seedingtimeLoaded = true;
  });

  it("renders seeding time as the elapsed duration it already is", () => {
    // The seedingtime plugin converts rTorrent's epoch into elapsed seconds
    // before the details pane ever sees it (plugins/seedingtime/init.js).
    plugin.fillDetails({ seedingtime: 7320, addtime: -1, created: 0 });

    expect(seedtimeCell()).toBe(theConverter.time(7320, true));
    expect(seedtimeCell()).not.toBe("");
  });

  it("renders a duration under a year, which the old year-guard hid", () => {
    plugin.fillDetails({ seedingtime: 3600, addtime: -1, created: 0 });

    expect(seedtimeCell()).toBe(theConverter.time(3600, true));
  });

  it("leaves the row empty when the torrent has never seeded", () => {
    plugin.fillDetails({ seedingtime: -1, addtime: -1, created: 0 });

    expect(seedtimeCell()).toBe("");
  });

  it("renders nothing rather than a 1970 date when the value is missing", () => {
    plugin.fillDetails({ addtime: -1, created: 0 });

    expect(seedtimeCell()).toBe("");
  });
});

// A value that becomes an element if the surrounding text is parsed as html,
// and that closes the attribute it sits in before doing so.
const HTML_PAYLOAD = `"><img src=x onerror="window.__ran = 'RAN'">`;

describe("mobile peers table", () => {
  // loadPeers asks the server and fills the table from the reply, so the reply
  // is what this drives.
  function renderPeers(peers) {
    document.body.innerHTML = "";
    document.body.insertAdjacentHTML(
      "beforeend",
      `<div class="tableFixHead"><table id="peersTable"><tbody></tbody></table></div>`
    );
    plugin.torrent = { hash: "HASH" };
    plugin.selectedPeer = null;
    plugin.request = (url, callback) => callback(peers);
    plugin.loadPeers();
    return $("#peersTable tbody");
  }

  const PEER = {
    ip: "10.0.0.1", port: 51413, version: "rt", flags: "I",
    done: 50, downloaded: 0, uploaded: 0, dl: 0, ul: 0,
    peerdl: 0, peerdownloaded: 0, snubbed: 0,
  };

  beforeEach(() => {
    delete window.__ran;
  });

  it("shows an ordinary peer as address, port and percentage", () => {
    const body = renderPeers({ p1: PEER });
    const cells = body.find("tr td").map((_, td) => $(td).text()).get();
    expect(cells[1]).toBe("10.0.0.1:51413");
    expect(cells[4]).toBe("50%");
  });

  // Each of these reaches the row through a different cell, and the peer id
  // reaches it through an attribute rather than a cell.
  for (const field of ["ip", "port", "done"]) {
    it(`does not let a peer's ${field} become markup`, () => {
      const body = renderPeers({ p1: { ...PEER, [field]: HTML_PAYLOAD } });
      expect(window.__ran).toBeUndefined();
      expect(body.find("img").length).toBe(0);
      expect(body.find("tr td").eq(field === "done" ? 4 : 1).text()).toContain(HTML_PAYLOAD);
    });
  }

  it("does not let a peer id become markup", () => {
    const peers = {};
    peers[HTML_PAYLOAD] = PEER;
    const body = renderPeers(peers);
    expect(window.__ran).toBeUndefined();
    expect(body.find("img").length).toBe(0);
    expect(body.find("tr").attr("data-pid")).toBe(HTML_PAYLOAD);
  });
});

describe("mobile ratio and throttle pickers", () => {
  function markup() {
    document.body.innerHTML = "";
    document.body.insertAdjacentHTML(
      "beforeend",
      `<table><tr id="priority"><td></td><td></td></tr></table>`
    );
  }

  beforeEach(() => {
    delete window.__ran;
    markup();
    window.thePlugins = {
      isInstalled: () => false,
      get: () => ({ allStuffLoaded: true }),
    };
  });

  afterEach(() => {
    window.thePlugins = { isInstalled: () => false, get: () => null };
  });

  it("lists ratio group names as text, not as markup", () => {
    window.theWebUI.ratios = { 0: { name: HTML_PAYLOAD }, 1: { name: "normal" } };
    plugin.loadRatio();

    expect(window.__ran).toBeUndefined();
    expect($("#torrentRatioGrp img").length).toBe(0);
    const names = $("#torrentRatioGrp option").map((_, o) => $(o).text()).get();
    // The first option is the plugin's own 'unlimited' entry.
    expect(names.slice(1)).toEqual([HTML_PAYLOAD, "normal"]);
  });

  it("lists throttle channel names as text, not as markup", () => {
    window.theWebUI.throttles = { 0: { name: HTML_PAYLOAD }, 1: { name: "normal" } };
    plugin.loadThrottle();

    expect(window.__ran).toBeUndefined();
    expect($("#torrentChannel img").length).toBe(0);
    const names = $("#torrentChannel option").map((_, o) => $(o).text()).get();
    expect(names.slice(1)).toEqual([HTML_PAYLOAD, "normal"]);
  });
});
