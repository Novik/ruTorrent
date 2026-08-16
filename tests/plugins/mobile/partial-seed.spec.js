import { readFileSync } from "fs";

window.$ = window.jQuery = require("jquery");

window.theUILang = {
  Name: "Name", Status: "Status", Size: "Size", Uploaded: "Uploaded",
  Downloaded: "Downloaded", Done: "Done", ETA: "ETA", Ul_speed: "UL",
  Down_speed: "DL", Ratio: "Ratio", addTime: "Added", seedingTime: "Seeding",
};

window.theWebUI = {
  settings: {},
  requestWithoutTimeout: function (url, callback) {
    callback({});
  },
  save: function () {},
};

// $type and friends come from the core, as they do in the browser
const coreEl = document.createElement("script");
coreEl.textContent = readFileSync("../js/common.js", { encoding: "utf-8" });
document.body.appendChild(coreEl);

let code = readFileSync("../plugins/mobile/init.js", { encoding: "utf-8" });
// The takeover call at the end of the file needs the whole desktop UI; this
// spec exercises the row snapshot helper, a plain assignment above it
code = code.replace(/plugin\.disableOthers\(\);\s*$/, "");
const scriptEl = document.createElement("script");
scriptEl.textContent = `(function () { var plugin = {}; plugin.path = "../plugins/mobile/"; ${code} })();`;
document.body.appendChild(scriptEl);

const plugin = window.mobile;

// A torrent as processTorrents() sees it, with only the fields the snapshot
// reads. Everything else is held constant so the flag is the sole variable.
function row(overrides) {
  return Object.assign({
    hash: "A".repeat(40), name: "Some Release 1080p", size: 1024, label: "",
    state: 1, done: 500, ul: 0, dl: 0, eta: -1, ratio: 0, msg: "",
    partially_done: 0,
  }, overrides);
}

describe("mobile plugin, partial seeds", () => {
  // The list redraws a row only when its snapshot changed. partially_done
  // drives both the status text and the status class, and it can flip while
  // every other snapshotted field stays put -- file priorities edited from
  // another client on an idle torrent -- so a snapshot that omitted it would
  // leave the row showing the old status until something else moved.
  it("keeps the partially done flag in the row snapshot", () => {
    const before = plugin.rowSnapshot(row({ partially_done: 0 }));
    const after = plugin.rowSnapshot(row({ partially_done: 1 }));

    expect(before).toHaveProperty("partially_done", 0);
    expect(after).toHaveProperty("partially_done", 1);
    expect(after).not.toEqual(before);
  });

  it("ignores fields the row does not render", () => {
    const before = plugin.rowSnapshot(row({ seedingtime: "100" }));
    const after = plugin.rowSnapshot(row({ seedingtime: "200" }));

    expect(after).toEqual(before);
  });
});
