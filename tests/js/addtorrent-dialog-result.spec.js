import { readFileSync } from "fs";

// The add dialog posts into a hidden frame, and php/addtorrent.php answers
// there with one noty(text, kind) call per item. An answer without one --
// the origin check's 403, the web server's 413 or 5xx page, a browser error
// page after a lost connection -- used to end the upload with no message at
// all and with the selection cleared. The mobile UI already shows such
// an answer as a failure (plugins/mobile/init.js uploadFinished).

window.$ = window.jQuery = require("jquery");
// jsdom has no ResizeObserver; theDialogManager only observes dialog sizes.
window.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} };
window.theWebUI = {
  settings: {},
  version: "test",
  save: () => {},
  upRateMenu: () => {},
  downRateMenu: () => {},
};
// makeContent() ends by building the settings window, which this test leaves out.
window.theOptionsWindow = { init: () => {} };
document.body.innerHTML = '<div id="dialog-container"></div>';
for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js", "../js/content.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}
makeContent();

/** Make the dialog's file input hold files with these names, as a browser would. */
function select(names) {
  const input = document.getElementById("torrent_file");
  let selected = names.map((name) => new File(["d8:announce0:e"], name));
  Object.defineProperty(input, "files", {
    configurable: true,
    get: () => Object.assign(Object.create(null), selected, { length: selected.length }),
  });
  Object.defineProperty(input, "value", {
    configurable: true,
    get: () => (selected.length ? "C:\\fakepath\\" + selected[0].name : ""),
    // A script can only clear a file input.
    set: (value) => { if (value === "") selected = []; },
  });
}

/**
 * Load the frame with this answer: a body from the server, or null for the
 * browser's own error page, which is cross-origin and cannot be read.
 */
function answer(frameName, body, href = "http://localhost/php/addtorrent.php") {
  const frame = document.querySelector(`iframe[name='${frameName}']`);
  const doc = (body === null) ? null : { location: { href }, body: { textContent: body, innerText: body } };
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
}

const result = (name, status, kind) =>
  `noty(${JSON.stringify(name + " - ")}+theUILang["addTorrent"+${JSON.stringify(status)}],${JSON.stringify(kind)});`;

const nginx413 = "\n413 Request Entity Too Large\n\n413 Request Entity Too Large\nnginx\n";

let notes;
beforeEach(() => {
  notes = [];
  window.noty = (text, kind) => notes.push([String(text), kind]);
  delete window.__ran;
});

describe("the file upload", () => {
  beforeEach(() => {
    select(["one.torrent"]);
    expect($("#addtorrent").triggerHandler("submit")).toBe(true);
    expect($("#add_button").prop("disabled")).toBe(true);
  });

  it("shows the server's result and clears the selection once something was added", () => {
    answer("uploadfrm", result("one.torrent", "Success", "success"));
    expect(notes).toEqual([["one.torrent - " + theUILang.addTorrentSuccess, "success"]]);
    expect($("#torrent_file").val()).toBe("");
    expect($("#add_button").prop("disabled")).toBe(true);
  });

  it.each([
    ["the web server's 413 page", nginx413],
    ["the origin check's 403", "Forbidden"],
    ["a server error page", "Internal Server Error"],
    ["an empty answer", ""],
    ["a lost connection", null],
  ])("shows %s as a failure and keeps the selection to send again", (_name, body) => {
    answer("uploadfrm", body);
    expect(notes).toEqual([[theUILang.addTorrentFailed, "error"]]);
    expect($("#torrent_file").val()).toBe("C:\\fakepath\\one.torrent");
    expect($("#add_button").prop("disabled")).toBe(false);
  });

  it("keeps the selection when the server refused every item", () => {
    answer("uploadfrm", result("one.torrent", "FailedFile", "error"));
    expect(notes).toEqual([["one.torrent - " + theUILang.addTorrentFailedFile, "error"]]);
    expect($("#torrent_file").val()).toBe("C:\\fakepath\\one.torrent");
    expect($("#add_button").prop("disabled")).toBe(false);
  });

  it("shows every item of a batch", () => {
    answer("uploadfrm", result("one.torrent", "Success", "success") + result("two.torrent", "Duplicate", "alert"));
    expect(notes).toEqual([
      ["one.torrent - " + theUILang.addTorrentSuccess, "success"],
      ["two.torrent - " + theUILang.addTorrentDuplicate, "alert"],
    ]);
  });

  it("ignores unrelated lines around the result calls", () => {
    answer("uploadfrm", "window.__ran = true;\n" + result("one.torrent", "Success", "success"));
    expect(window.__ran).toBeUndefined();
    expect(notes).toEqual([["one.torrent - " + theUILang.addTorrentSuccess, "success"]]);
  });

  it("does not treat the frame's own blank page as an answer", () => {
    answer("uploadfrm", "", "about:blank");
    expect(notes).toEqual([]);
    expect($("#torrent_file").val()).toBe("C:\\fakepath\\one.torrent");
    expect($("#add_button").prop("disabled")).toBe(true);
  });
});

describe("the URL add", () => {
  const magnet = "magnet:?xt=urn:btih:0123456789ABCDEF0123456789ABCDEF01234567";

  beforeEach(() => {
    $("#url").val(magnet);
    expect($("#addtorrenturl").triggerHandler("submit")).toBe(true);
    expect($("#add_url").prop("disabled")).toBe(true);
  });

  it("clears the URLs once something was added", () => {
    answer("uploadfrmurl", result(magnet, "Success", "success"));
    expect(notes).toEqual([[magnet + " - " + theUILang.addTorrentSuccess, "success"]]);
    expect($("#url").val()).toBe("");
    expect($("#add_url").prop("disabled")).toBe(true);
  });

  it.each([
    ["the origin check's 403", "Forbidden"],
    ["the web server's 413 page", nginx413],
    ["an empty answer", ""],
    ["a lost connection", null],
  ])("shows %s as a failure and keeps the URLs to send again", (_name, body) => {
    answer("uploadfrmurl", body);
    expect(notes).toEqual([[theUILang.addTorrentFailed, "error"]]);
    expect($("#url").val()).toBe(magnet);
    expect($("#add_url").prop("disabled")).toBe(false);
  });
});

describe("independent add forms", () => {
  it("restores only the form whose answer arrived", () => {
    select(["retry.torrent"]);
    $("#url").val("https://example.invalid/new.torrent");
    $("#addtorrent").triggerHandler("submit");
    $("#addtorrenturl").triggerHandler("submit");
    answer("uploadfrm", "Forbidden");
    expect($("#torrent_file").val()).toBe("C:\\fakepath\\retry.torrent");
    expect($("#add_button").prop("disabled")).toBe(false);
    expect($("#url").val()).toBe("https://example.invalid/new.torrent");
    expect($("#add_url").prop("disabled")).toBe(true);
    answer("uploadfrmurl", result("new.torrent", "Success", "success"));
    expect(notes).toEqual([
      [theUILang.addTorrentFailed, "error"],
      ["new.torrent - " + theUILang.addTorrentSuccess, "success"],
    ]);
    expect($("#url").val()).toBe("");
    expect($("#add_url").prop("disabled")).toBe(true);
    expect($("#torrent_file").val()).toBe("C:\\fakepath\\retry.torrent");
    expect($("#add_button").prop("disabled")).toBe(false);
  });
});
