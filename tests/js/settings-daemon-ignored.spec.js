import { readFileSync } from "fs";
window.$ = window.jQuery = require("jquery");
// jsdom has no ResizeObserver; theDialogManager only observes dialog sizes.
window.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} };
// The options window opens its first page through Bootstrap's tab plugin.
window.bootstrap = { Tab: { getOrCreateInstance: () => ({ show() {} }) } };

// The Settings dialog as index.html builds it: the UI stack in load order,
// then makeContent() and correctContent() for the daemon getplugins.php saw.
for (const src of ["../lang/langs.js", "../lang/en.js", "../js/sanitize.js", "../js/sanitize.config.js",
  "../js/browser.js", "../js/common.js", "../js/objects.js", "../js/options.js",
  "../js/content.js", "../js/stable.js", "../js/graph.js", "../js/plugins.js",
  "../js/rtorrent.js", "../js/webui.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

const V = {
  "0.9.8": 0x908, "0.16.0": 0x1000, "0.16.1": 0x1001, "0.16.11": 0x100b, "0.16.12": 0x100c,
  "0.16.17": 0x1011, "0.16.18": 0x1012, "0.16.28": 0x101c, "0.16.29": 0x101d,
};

// The settings getsettings answers with, as rtorrent reports them.
const DAEMON = { dht: 0, dht_port: "6881", use_udp_trackers: "1", timeout_safe_sync: "900", max_peers: "100" };

let sent;

function openDialog(version) {
  document.getElementById("dialog-container")?.remove();
  $("<div>").attr("id", "dialog-container").appendTo(document.body);
  theWebUI.systemInfo = { rTorrent: { started: true, iVersion: V[version], apiVersion: 10, version } };
  theWebUI.showFlags = 0xffff;
  theWebUI.settings = {};
  makeContent();
  correctContent();
  theWebUI.addSettings({ ...DAEMON });
}

// What OK in the dialog would send to rtorrent, as the s= names of the request.
function savedNames() {
  sent = [];
  theWebUI.settingsSavePending = false;
  theWebUI.setSettings();
  return sent;
}

beforeEach(() => {
  theWebUI.request = jest.fn((req) => {
    if (req && req.ss) sent.push(...req.ss.map((s) => s.slice(1)));
  });
  theDialogManager.show = jest.fn();
});

const control = (id) => document.getElementById(id);
const label = (id) => document.querySelector(`label[for="${id}"]`);

describe("a setting rtorrent ignores", () => {
  // Each setter answers success and changes nothing on these daemons.
  const ignored = [
    ["use_udp_trackers", "0.16.12", "0.16.11"],
    ["timeout_safe_sync", "0.16.18", "0.16.17"],
    ["dht_port", "0.16.1", "0.16.0"],
  ];

  describe.each(ignored)("%s", (id, since, before) => {
    it.each([since, "0.16.28", "0.16.29"])("cannot be changed on %s, and says why", (version) => {
      openDialog(version);
      $(control("dht")).prop("checked", true).trigger("change");
      expect(control(id).disabled).toBe(true);
      expect(control(id).title).toBe("Ignored by rTorrent >= v" + since);
      expect(label(id).classList.contains("disabled")).toBe(true);
      expect(label(id).title).toBe(control(id).title);
    });

    it.each([before, "0.9.8"])("is offered as before on %s", (version) => {
      openDialog(version);
      $(control("dht")).prop("checked", true).trigger("change");
      expect(control(id).disabled).toBe(false);
      expect(control(id).title).toBe("");
      expect(label(id).classList.contains("disabled")).toBe(false);
    });
  });

  it("still shows the value rtorrent reports", () => {
    openDialog("0.16.29");
    expect(control("use_udp_trackers").checked).toBe(true);
    expect(control("timeout_safe_sync").value).toBe("900");
    expect(control("dht_port").value).toBe("6881");
  });

  it("is not sent when the user clicks it on 0.16.29", () => {
    openDialog("0.16.29");
    control("use_udp_trackers").click();
    expect(savedNames()).not.toContain("use_udp_trackers");
  });

  it("is sent when the user clicks it on 0.9.8", () => {
    openDialog("0.9.8");
    control("use_udp_trackers").click();
    expect(savedNames()).toEqual(["use_udp_trackers"]);
  });

  it("does not become editable when DHT is turned off and on again", () => {
    openDialog("0.16.29");
    control("dht").click();
    control("dht").click();
    control("dht").click();
    expect(control("dht").checked).toBe(true);
    expect(control("dht_port").disabled).toBe(true);
    expect(label("dht_port").classList.contains("disabled")).toBe(true);
  });
});

describe("hash_interval, hash_max_tries and hash_read_ahead", () => {
  const HASH = ["hash_interval", "hash_max_tries", "hash_read_ahead"];

  // rtorrent 0.9.0 dropped the commands; ruTorrent sends them to "cat".
  it.each(["0.9.8", "0.16.29"])("are not in the dialog on %s", (version) => {
    openDialog(version);
    for (const id of HASH) {
      expect(control(id)).toBeNull();
      expect(label(id)).toBeNull();
    }
  });

  it("are offered on 0.8.x", () => {
    openDialog("0.9.8");
    theWebUI.systemInfo.rTorrent.iVersion = 0x809;
    document.getElementById("dialog-container").remove();
    $("<div>").attr("id", "dialog-container").appendTo(document.body);
    makeContent();
    correctContent();
    for (const id of HASH)
      expect(control(id)).not.toBeNull();
  });

  it("leave every other Advanced label next to its field", () => {
    openDialog("0.16.29");
    const cells = [...document.querySelectorAll("#st_ao_h > div")];
    cells.forEach((cell, i) => {
      const lbl = cell.querySelector("label");
      if (lbl && !cell.querySelector("input,select")) {
        const field = cells[i + 1].querySelector("input,select");
        expect(lbl.getAttribute("for")).toBe(field.id);
      }
    });
  });
});
