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
