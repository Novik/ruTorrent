import { readFileSync } from "fs";
import { CategoryList } from "../../../js/category-list";
import * as bbcodeModule from "../../../plugins/rss/bbcode";

window.$ = require("jquery");
window.theWebUI = {
  version: "0.0.0",
  settings: {
    "webui.needmessage": true,
  },

  showFlags: 0xffff,
  systemInfo: {
    rTorrent: {
      apiVersion: 10,
      iVersion: 0x908,
      started: true,
    },
  },
  categoryList: new CategoryList({}),
  resizeTop: (w, h) => console.log(`resizeTop ${w}w ${h}h`),
};
window.GetActiveLanguage = function () {
  return "en";
};
window.rsstestingbbcodeModule = bbcodeModule;

document.body.append(
  ...["category-list", "panel-label"].map((elementTag) =>
    Object.assign(document.createElement("template"), {
      id: `${elementTag}-template`,
      innerHTML: '<link rel="stylesheet" />',
    })
  )
);

for (const src of [
  "../js/sanitize.js",
  "../js/sanitize.config.js",
  "../js/custom-elements.js",
  "../js/category-list-elements.js",
  "../lang/en.js",
  "../js/common.js",
  "../js/content.js",
  "../js/rtorrent.js",
  "../js/plugins.js",
  "../plugins/rss/init.js",
]) {
  const scriptEl = document.createElement("script");
  let code = readFileSync(src, { encoding: "utf-8" });
  if (src.endsWith("rss/init.js")) {
    // Dynamic imports and <script type="module"> are not supported by jsdom
    // (See https://github.com/jsdom/jsdom/issues/2475)
    // Workaround: initialize module by replacing code
    code = code.replace(
      "var bbcode = null;",
      "var bbcode = window.rsstestingbbcodeModule;"
    );
    code = `(function () { var plugin = new rPlugin('rss', 4.0, 'a', 'b', 'c', 'd'); plugin.path="../plugins/rss/"; ${code}; })();`;
  }
  scriptEl.setAttribute("type", "text/javascript");
  scriptEl.textContent = code;
  document.head.appendChild(scriptEl);
}
correctContent();
document.body.appendChild($("<div>").attr("id", "rsslayout")[0]);

test("RSS filter selection, persistence and torrent loading retain raw directory keys", () => {
  const directory = { name: "/downloads/\\xFF", pathKey: "%2Fdownloads%2F%FF" };
  const holder = $('<div><input id="FLTdir_edit"><input id="FLT_rss"><input id="RSSdir_edit"><input id="RSS_label"><input id="_fn0"><input id="_fe0" type="checkbox"></div>').appendTo(document.body);
  theWebUI.filters = [{ name: "filter", pattern: ".*", exclude: "", dir: directory, label: "", hash: "", start: 1, add_path: 1, interval: -1, no: 0 }];
  theWebUI.curFilter = null;
  const filter = { id: "flt0" };
  theWebUI.selectFilter(filter);
  expect(getPathInput("#FLTdir_edit")).toEqual(directory);
  const stub = new rTorrentStub("?action=setfilters");
  expect(stub.content).toContain(`&dir=${directory.pathKey}`);
  expect(theWebUI.filters[0].dir).toEqual(directory);
  expect(new rTorrentStub("?action=checkfilter").content).toContain(`&directory=${directory.pathKey}`);
  setPathInput("#RSSdir_edit", directory);
  theWebUI.rssArray = [];
  expect(new rTorrentStub("?action=loadrsstorrents").content).toContain(`&dir_edit=${directory.pathKey}`);
  $("#FLTdir_edit").val("/manual%2F\\path").trigger("input");
  expect(new rTorrentStub("?action=setfilters").content).toContain("&dir=%2Fmanual%252F%5Cpath");
  holder.remove();
});

describe("rss details", () => {
  beforeEach(() => {
    $("#rsslayout").text("");
  });

  it("should sanitize html code", () => {
    theWebUI.rssItems = { rssid: "rsslink" };
    const stub = new rTorrentStub("?action=getrssdetails&s=rssid");
    stub.getrssdetailsResponse(
      '[b][color=#556677]<ins>Important</ins> :smile:[/color]<img src="https://linkto.img" onerror=alert("hax2")/><script>alert(\'hax\');</script>[/b]'
    );
    // Sanitize.RESTRICTED does not allow img
    expect($("#rsslayout div").html()).toEqual(
      '<b><span class="bbcode-color" style="color: #556677"><ins>Important</ins> 🙂</span>alert(\'hax\');</b>'
    );
    stub.getrssdetailsResponse(
      '[style color=0000FF font="times" size=18 wild=attr align=center]Text[/style]'
    );
    expect($("#rsslayout div").html()).toEqual(
      '<span class="bbcode-color bbcode-font-times bbcode-size bbcode-align-center" style="color: #0000FF; font-size: 18px">Text</span>'
    );
    stub.getrssdetailsResponse("[style color=bad font=caps]Text[/style]");
    expect($("#rsslayout div").html()).toEqual(
      '<span class="bbcode-font-caps">Text</span>'
    );
  });
});
