import { readFileSync } from "fs";
window.$ = window.jQuery = require("jquery");
const HASH = "A".repeat(40);
let sent;
function load(path, prefix = "", suffix = "") {
  const script = document.createElement("script");
  script.textContent = prefix + readFileSync(path, "utf8") + suffix;
  document.body.appendChild(script);
}
// php/getplugins.php loads plugins by runlevel: erasedata (default 10) before
// httprpc (13). erasedata's init.php writes its settings ahead of its init.js,
// here $enableForceDeletion = true.
function setup(httprpc, menu = true) {
  document.body.innerHTML = "";
  sent = null;
  window.theWebUI = { settings: {"webui.needmessage": true, "webui.confirm_when_deleting": false},
    showFlags: 0xffff, systemInfo: {rTorrent: {apiVersion: 10, iVersion: 0x908, started: true}},
    // The request theWebUI.perform() makes for one selected torrent.
    perform: cmd => { sent = new rTorrentStub("?action=" + cmd + "&hash=" + HASH + "&list=1&getmsg=1"); } };
  for (const path of ["../lang/en.js", "../js/common.js", "../js/content.js", "../js/rtorrent.js"]) load(path);
  correctContent();
  window.__erasedata = { loadLang() {}, canChangeMenu: () => menu };
  load("../plugins/erasedata/init.js", "(function(){var plugin=window.__erasedata;" +
    "plugin.enableForceDeletion = 1;plugin.replaceRemoveTorrent = 0;", "})();");
  if (httprpc) {
    window.__httprpc = { enabled: true };
    load("../plugins/httprpc/init.js", "(function(){var plugin=window.__httprpc;", "})();");
  }
}
describe("with httprpc", () => {
  beforeEach(() => setup(true));
  test.each([["Delete Path", "2", true], ["Delete Data", "1", false]])("%s sends v=%s", (_, v, force) => {
    theWebUI.removeWithData(force);
    expect(sent.mountPoint).toBe("plugins/httprpc/action.php");
    expect(sent.content).toBe("mode=removewithdata&hash=" + HASH + "&v=" + v);
  });
  test("the mobile plugin's request, which sets no force, sends v=1", () => {
    sent = new rTorrentStub("?action=removewithdata&hash=" + HASH);
    expect(sent.mountPoint).toBe("plugins/httprpc/action.php");
    expect(sent.content).toBe("mode=removewithdata&hash=" + HASH + "&v=1");
  });
});
// The mobile plugin offers "Delete Data" whenever erasedata is enabled, even with erasedata's menu turned off.
test("with erasedata's menu turned off, the mobile plugin's request reaches httprpc with v=1", () => {
  setup(true, false);
  sent = new rTorrentStub("?action=removewithdata&hash=" + HASH);
  expect(sent.mountPoint).toBe("plugins/httprpc/action.php");
  expect(sent.content).toBe("mode=removewithdata&hash=" + HASH + "&v=1");
});
test("without httprpc, Delete Path sends v=2 to erasedata's action.php", () => {
  setup(false);
  theWebUI.removeWithData(true);
  expect(sent.mountPoint).toBe("plugins/erasedata/action.php");
  expect(sent.content).toBe("mode=removewithdata&hash=" + HASH + "&v=2");
});
