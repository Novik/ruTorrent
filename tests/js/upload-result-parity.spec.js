import { readFileSync } from "fs";

// The desktop add dialog (js/content.js) and the mobile add page
// (plugins/mobile/init.js) both read their upload's answer out of a hidden
// frame and turn php/addtorrent.php's script of noty(text, kind) calls into
// notifications. This pins the part they are meant to agree on: for the same
// answer, the same messages in the same order, with the same kinds, and the
// same decision to clear or keep the input the upload came from.
//
// What is deliberately not compared, because the two interfaces differ there:
// how a notification reaches the DOM (a $.noty toast against a bootstrap
// alert), which inputs a success clears (desktop clears only the form whose
// answer arrived, mobile clears both), the page change and scroll mobile does
// on a success, and what each does with its send button while the frame still
// holds its initial blank page.
//
// The two UIs use the same element ids, so they cannot stand in one document.
// The desktop runs first and is read out, then the body is replaced and the
// mobile plugin runs the same table.

window.$ = window.jQuery = require("jquery");
window.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} };

const read = (src) => readFileSync(src, { encoding: "utf-8" });

const noty = (name, status, kind) =>
  `noty(${JSON.stringify(name + " - ")}+theUILang["addTorrent"+${JSON.stringify(status)}],${JSON.stringify(kind)});`;
const nginx413 = "\n413 Request Entity Too Large\n\n413 Request Entity Too Large\nnginx\n";

// Marker objects: these answers are not a document body at all.
const LOST = { lost: true };
const BLANK = { blank: true };

const CASES = [
  ["a success", noty("good.torrent", "Success", "success")],
  ["a refused file", noty("bad.torrent", "FailedFile", "error")],
  ["a duplicate", noty("again.torrent", "Duplicate", "alert")],
  ["a batch, some added", noty("one.torrent", "Success", "success") + noty("two.torrent", "Duplicate", "alert")],
  ["a batch, none added", noty("one.torrent", "FailedFile", "error") + noty("two.torrent", "Duplicate", "alert")],
  ["an empty answer", ""],
  ["the origin check's refusal", "Forbidden"],
  ["the web server's 413 page", nginx413],
  ["a server error page", "Internal Server Error"],
  ["text printed around the result", "a stray PHP notice\n" + noty("good.torrent", "Success", "success")],
  ["a statement that is not a result call", "window.__ran = true;\n" + noty("good.torrent", "Success", "success")],
  ["an unreadable result call", 'noty("x.torrent - "+theUILang[,"error");'],
  ["the answer format before upstream #3280", 'noty("bad.torrent - "+theUILang.addTorrentFailedFile,"error");'],
  ["a lost connection", LOST],
  ["the frame's own blank page", BLANK],
];

/** [[text, kind], ...] and whether the upload's own input was cleared. */
function runDesktop() {
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

  return CASES.map(([, body]) => {
    selected = [new File(["d8:announce0:e"], "picked.torrent")];
    const notes = [];
    window.noty = (text, kind) => notes.push([String(text), String(kind)]);
    $("#addtorrent").triggerHandler("submit");

    const frame = document.querySelector("iframe[name='uploadfrm']");
    const href = (body === BLANK) ? "about:blank" : "http://localhost/php/addtorrent.php";
    const doc = (body === LOST) ? null
      : { location: { href }, body: { textContent: body === BLANK ? "" : body, innerText: body === BLANK ? "" : body } };
    Object.defineProperty(frame, "contentDocument", { configurable: true, get: () => doc });
    Object.defineProperty(frame, "contentWindow", {
      configurable: true,
      get: () => ({
        get document() {
          if (doc === null)
            throw new DOMException("Blocked a frame from accessing a cross-origin frame.", "SecurityError");
          return doc;
        },
      }),
    });
    frame.dispatchEvent(new Event("load"));
    return { notes, cleared: $("#torrent_file").val() === "" };
  });
}

// plugin.alertClass() is how mobile renders a kind; read it back to compare
// with the kind the desktop handed to noty(). The server writes only these.
const CLASS_TO_KIND = { "alert-success": "success", "alert-danger": "error", "alert-warning": "alert" };

function runMobile() {
  window.plugin = { disable: jest.fn() };
  window.theWebUI = { getTrackerName: () => "" };
  const el = document.createElement("script");
  el.textContent = read("../plugins/mobile/init.js");
  document.body.appendChild(el);
  const markup = read("../plugins/mobile/mobile.html");

  return CASES.map(([, body]) => {
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

    plugin.createiFrame(document.getElementById("addTorrentFile"));
    const frame = document.querySelector("#addTorrent iframe");
    if (body === LOST) {
      Object.defineProperty(frame, "contentDocument", { configurable: true, get: () => null });
      Object.defineProperty(frame, "contentWindow", {
        configurable: true,
        get: () => ({
          get document() {
            throw new DOMException("Blocked a frame from accessing a cross-origin frame.", "SecurityError");
          },
        }),
      });
    } else {
      const href = (body === BLANK) ? "about:blank" : "http://rutorrent.invalid/php/addtorrent.php?result[]=";
      const page = new DOMParser().parseFromString(body === BLANK ? "" : body, "text/html");
      Object.defineProperty(frame, "contentDocument", {
        configurable: true,
        get: () => ({ location: { href }, body: page.body }),
      });
    }
    frame.dispatchEvent(new Event("load"));

    const notes = $("#alert_placeholder .alert").map((i, node) => {
      const kind = Object.keys(CLASS_TO_KIND).filter((c) => node.classList.contains(c)).map((c) => CLASS_TO_KIND[c]);
      return [[node.textContent, kind.length === 1 ? kind[0] : "information"]];
    }).get();
    return { notes, cleared: $("#torrent_file").val() === "" };
  });
}

const desktop = runDesktop();
const mobile = runMobile();

describe("the desktop and mobile add-torrent result collectors", () => {
  test.each(CASES.map(([label], i) => [label, i]))("agree on %s", (label, i) => {
    expect(mobile[i]).toEqual(desktop[i]);
  });

  test("an answer with no result call at all is still one failure", () => {
    for (const i of CASES.map(([, body], n) => [body, n]).filter(([body]) => body === "" || body === "Forbidden" || body === nginx413 || body === "Internal Server Error" || body === LOST).map(([, n]) => n)) {
      expect(desktop[i].notes).toEqual([[theUILang.addTorrentFailed, "error"]]);
      expect(desktop[i].cleared).toBe(false);
    }
  });

  test("the frame's own blank page reports nothing", () => {
    const i = CASES.findIndex(([, body]) => body === BLANK);
    expect(desktop[i].notes).toEqual([]);
    expect(desktop[i].cleared).toBe(false);
  });

  test("a success clears the upload's input and a refusal keeps it", () => {
    const at = (label) => desktop[CASES.findIndex(([l]) => l === label)];
    expect(at("a success").cleared).toBe(true);
    expect(at("a batch, some added").cleared).toBe(true);
    expect(at("a refused file").cleared).toBe(false);
    expect(at("a duplicate").cleared).toBe(false);
    expect(at("a batch, none added").cleared).toBe(false);
  });

  test("a statement that is not a result call does not run", () => {
    expect(window.__ran).toBeUndefined();
  });
});
