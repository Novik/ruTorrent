import { readFileSync } from "fs";

// The desktop add dialog (js/content.js) and the mobile add page
// (plugins/mobile/init.js) both send their forms with uploadTorrentForm() and
// turn php/addtorrent.php's json results into notifications. This pins the
// part they are meant to agree on: for the same answer, the same messages in
// the same order, with the same kinds, and the same decision to clear or keep
// the input the upload came from.
//
// What is deliberately not compared, because the two interfaces differ there:
// how a notification reaches the DOM (a $.noty toast against a bootstrap
// alert), which inputs a success clears (desktop clears only the form whose
// answer arrived, mobile clears both), and the page change and scroll mobile
// does on a success.
//
// The two UIs use the same element ids, so they cannot stand in one document.
// The desktop runs first and is read out, then the body is replaced and the
// mobile plugin runs the same table.

window.$ = window.jQuery = require("jquery");
window.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} };

const read = (src) => readFileSync(src, { encoding: "utf-8" });

const results = (...items) => JSON.stringify({ results: items.map(([status, name]) => (name === undefined ? { status } : { status, name })) });
const nginx413 = "\n413 Request Entity Too Large\n\n413 Request Entity Too Large\nnginx\n";

// Marker object: the connection was lost, fetch() rejects.
const LOST = { lost: true };

const CASES = [
  ["a success", 200, results(["Success", "good.torrent"])],
  ["a refused file", 200, results(["FailedFile", "bad.torrent"])],
  ["a duplicate", 200, results(["Duplicate", "again.torrent"])],
  ["a refused directory", 200, results(["FailedDirectory"])],
  ["a batch, some added", 200, results(["Success", "one.torrent"], ["Duplicate", "two.torrent"])],
  ["a batch, none added", 200, results(["FailedFile", "one.torrent"], ["Duplicate", "two.torrent"])],
  ["an empty answer", 200, ""],
  ["the origin check's refusal", 403, "Forbidden"],
  ["the web server's 413 page", 413, nginx413],
  ["a server error page", 500, "Internal Server Error"],
  ["a result page instead of json", 200, 'noty("good.torrent - "+theUILang["addTorrent"+"Success"],"success");'],
  ["json with no item", 200, '{"results":[]}'],
  ["a lost connection", 0, LOST],
];

let pending;
function stubFetch() {
  pending = [];
  window.fetch = (url, init) => new Promise((resolve, reject) => pending.push({ resolve, reject }));
}

async function settle() {
  for (let i = 0; i < 20; i++)
    await Promise.resolve();
}

/** Answer the one request in flight with this case. */
async function reply(status, body) {
  expect(pending).toHaveLength(1);
  const request = pending.pop();
  if (body === LOST)
    request.reject(new TypeError("Failed to fetch"));
  else
    request.resolve({ ok: status >= 200 && status < 300, status, text: () => Promise.resolve(body) });
  await settle();
}

/** [[text, kind], ...] and whether the upload's own input was cleared. */
async function runDesktop() {
  window.theWebUI = { settings: {}, version: "test", save() {}, upRateMenu() {}, downRateMenu() {} };
  window.theOptionsWindow = { init: () => {} };
  document.body.innerHTML = '<div id="dialog-container"></div>';
  for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js", "../js/content.js"]) {
    const el = document.createElement("script");
    el.textContent = read(src);
    document.body.appendChild(el);
  }
  makeContent();

  const input = document.getElementById("torrent_file");
  let selected = [];
  Object.defineProperty(input, "files", {
    configurable: true,
    get: () => Object.assign(Object.create(null), selected, { length: selected.length }),
  });
  Object.defineProperty(input, "value", {
    configurable: true,
    get: () => (selected.length ? "C:\\fakepath\\" + selected[0].name : ""),
    set: (v) => { if (v === "") selected = []; },
  });

  const out = [];
  for (const [, status, body] of CASES) {
    selected = [new File(["d8:announce0:e"], "picked.torrent")];
    const notes = [];
    window.noty = (text, kind) => notes.push([String(text), String(kind)]);
    stubFetch();
    $("#addtorrent").triggerHandler("submit");
    await reply(status, body);
    out.push({ notes, cleared: $("#torrent_file").val() === "" });
  }
  return out;
}

// plugin.alertClass() is how mobile renders a kind; read it back to compare
// with the kind the desktop handed to noty(). The server writes only these.
const CLASS_TO_KIND = { "alert-success": "success", "alert-danger": "error", "alert-warning": "alert" };

async function runMobile() {
  window.plugin = { disable: jest.fn(), loadLang: jest.fn() };
  window.theDialogManager = { addHandler: jest.fn() };
  window.theWebUI = { getTrackerName: () => "" };
  const el = document.createElement("script");
  el.textContent = read("../plugins/mobile/init.js");
  document.body.appendChild(el);
  const markup = read("../plugins/mobile/mobile.html");

  const out = [];
  for (const [, status, body] of CASES) {
    document.body.innerHTML = markup;
    plugin.currentPage = "addTorrent";
    plugin.scrollToAddedUntil = 0;
    plugin.update = jest.fn();
    plugin.showList = jest.fn();
    window.noty = jest.fn();

    const input = document.getElementById("torrent_file");
    let value = "C:\\fakepath\\picked.torrent";
    Object.defineProperty(input, "value", { configurable: true, get: () => value, set: (v) => { value = v; } });
    Object.defineProperty(input, "files", { configurable: true, get: () => (value ? [{ name: "picked.torrent" }] : []) });

    stubFetch();
    plugin.sendAddForm(document.getElementById("addTorrentFile"));
    await reply(status, body);

    const notes = $("#alert_placeholder .alert").map((i, node) => {
      const kind = Object.keys(CLASS_TO_KIND).filter((c) => node.classList.contains(c)).map((c) => CLASS_TO_KIND[c]);
      return [[node.textContent, kind.length === 1 ? kind[0] : "information"]];
    }).get();
    out.push({ notes, cleared: $("#torrent_file").val() === "" });
  }
  return out;
}

let desktop, mobile;
beforeAll(async () => {
  desktop = await runDesktop();
  mobile = await runMobile();
});

describe("the desktop and mobile add-torrent results", () => {
  test.each(CASES.map(([label], i) => [label, i]))("agree on %s", (label, i) => {
    expect(mobile[i]).toEqual(desktop[i]);
  });

  test("an answer with no result at all is still one failure", () => {
    const failures = ["an empty answer", "the origin check's refusal", "the web server's 413 page",
      "a server error page", "a result page instead of json", "json with no item", "a lost connection"];
    for (const label of failures) {
      const i = CASES.findIndex(([l]) => l === label);
      expect(desktop[i].notes).toEqual([[theUILang.addTorrentFailed, "error"]]);
      expect(desktop[i].cleared).toBe(false);
    }
  });

  test("a success clears the upload's input and a refusal keeps it", () => {
    const at = (label) => desktop[CASES.findIndex(([l]) => l === label)];
    expect(at("a success").cleared).toBe(true);
    expect(at("a batch, some added").cleared).toBe(true);
    expect(at("a refused file").cleared).toBe(false);
    expect(at("a duplicate").cleared).toBe(false);
    expect(at("a batch, none added").cleared).toBe(false);
  });
});
