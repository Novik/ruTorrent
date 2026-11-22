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

// The Settings dialog shows three rtorrent values in a larger unit than the
// daemon keeps them in, rounded up to a whole one: max memory usage in MiB,
// the global rates in KiB/s. Pressing OK without touching them is not an
// instruction to write the rounded value back.
describe("theWebUI.setSettings with a converted unit", () => {
  // pieces.memory.max as rtorrent 0.9.8 reports it by default, and rates that
  // are not whole KiB/s (throttle.global_*.max_rate.set takes bytes).
  const daemon = { max_memory_usage: "13352332492", upload_rate: "1100000", download_rate: "1536000",
    max_peers: "100" };
  let requested;

  const sent = () => {
    if (!requested.mock.calls.length)
      return {};
    const out = {};
    const vars = requested.mock.calls[0][0].URI.split("&");
    for (let n = 0; n < vars.length; n++) {
      if (vars[n].startsWith("s=")) {
        out[vars[n].slice(3)] = vars[n + 1].slice(2);
      }
    }
    return out;
  };

  beforeEach(() => {
    theWebUI.systemInfo = { rTorrent: { started: true, iVersion: 0x908 } };
    theWebUI.settingsSavePending = false;
    global.noty = jest.fn();
    requested = jest.fn();
    theWebUI.request = requested;
    theWebUI.save = jest.fn();
    $("<div>").attr("id", "unit_fixture").append(
      $("<input>").attr({ type: "number", id: "max_memory_usage" }).addClass("num"),
      $("<input>").attr({ type: "text", id: "upload_rate" }),
      $("<input>").attr({ type: "text", id: "download_rate" }),
      $("<input>").attr({ type: "number", id: "max_peers" }).addClass("num"),
    ).appendTo(document.body);
    theWebUI.settings = { ...daemon };
    theWebUI.loadSettings();
  });
  afterEach(() => { delete theWebUI.systemInfo; $("#unit_fixture").remove(); });

  it("shows each value rounded up to a whole unit", () => {
    expect($("#max_memory_usage").val()).toBe("12734");
    expect($("#upload_rate").val()).toBe("1075");
    expect($("#download_rate").val()).toBe("1500");
  });

  it("sends nothing when no field was changed", () => {
    theWebUI.setSettings();
    expect(sent()).toStrictEqual({});
    expect(theWebUI.settings).toStrictEqual(daemon);
  });

  it("sends only the field that was changed, not the rounded ones", () => {
    $("#max_peers").val("200");
    theWebUI.setSettings();
    expect(sent()).toStrictEqual({ max_peers: "200" });
    expect(theWebUI.settings.max_memory_usage).toBe(daemon.max_memory_usage);
  });

  it("sends a changed value in the daemon's unit", () => {
    $("#max_memory_usage").val("2048");
    $("#upload_rate").val("100");
    theWebUI.setSettings();
    expect(sent()).toStrictEqual({ max_memory_usage: String(2048 * 1048576), upload_rate: String(100 * 1024) });
  });

  it("shows the same value again after a Save that changed nothing", () => {
    theWebUI.setSettings();
    theWebUI.loadSettings();
    expect($("#max_memory_usage").val()).toBe("12734");
    expect($("#upload_rate").val()).toBe("1075");
    expect($("#download_rate").val()).toBe("1500");
  });

  it("still writes a rate cleared to empty as unlimited", () => {
    $("#download_rate").val("");
    theWebUI.setSettings();
    expect(sent()).toStrictEqual({ download_rate: "0" });
  });
});
