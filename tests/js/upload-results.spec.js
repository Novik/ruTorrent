import { readFileSync } from "fs";

// js/common.js holds the add-torrent upload's answer reader and result
// collector, which the desktop dialog (js/content.js) and the mobile add page
// (plugins/mobile/init.js) share. Everything the two interfaces do with the
// results -- how a notification is drawn, which inputs a success clears, the
// send buttons, the page change -- stays with each of them.

window.$ = window.jQuery = require("jquery");
window.theWebUI = { settings: {} };
for (const src of ["../lang/en.js", "../js/common.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

const noty = (name, status, kind) =>
  `noty(${JSON.stringify(name + " - ")}+theUILang["addTorrent"+${JSON.stringify(status)}],${JSON.stringify(kind)});`;

/** A frame whose document reads like this. */
const framed = (doc) => ({ contentDocument: doc, contentWindow: { document: doc } });
/** A frame holding the browser's own error page, which is cross-origin. */
const crossOrigin = () => ({
  contentDocument: null,
  get contentWindow() {
    throw new DOMException("Blocked a frame from accessing a cross-origin frame.", "SecurityError");
  },
});
const page = (text, href = "http://localhost/php/addtorrent.php") =>
  framed({ location: { href }, body: { textContent: text, innerText: text } });

describe("the upload frame's answer", () => {
  it("is the text of the page the server sent", () => {
    expect(uploadFrameAnswer(page("hello"))).toBe("hello");
  });

  it("is null while the frame still holds the blank page it was inserted with", () => {
    expect(uploadFrameAnswer(page("", "about:blank"))).toBeNull();
  });

  it("is empty, not null, when the frame cannot be read at all", () => {
    expect(uploadFrameAnswer(crossOrigin())).toBe("");
    expect(uploadFrameAnswer(framed(null))).toBe("");
    expect(uploadFrameAnswer(framed({ location: { href: "http://localhost/x" }, body: null }))).toBe("");
  });
});

describe("the upload's results", () => {
  it("are one entry per rendered call, in the order the server wrote them", () => {
    expect(uploadResults(noty("one.torrent", "Success", "success") + noty("two.torrent", "Duplicate", "alert"))).toEqual([
      { text: "one.torrent - " + theUILang.addTorrentSuccess, kind: "success" },
      { text: "two.torrent - " + theUILang.addTorrentDuplicate, kind: "alert" },
    ]);
  });

  it("read the message key spelling from before upstream #3280 too", () => {
    expect(uploadResults('noty("bad.torrent - "+theUILang.addTorrentFailedFile,"error");')).toEqual([
      { text: "bad.torrent - " + theUILang.addTorrentFailedFile, kind: "error" },
    ]);
  });

  it.each([
    ["an empty answer", ""],
    ["the origin check's refusal", "Forbidden"],
    ["the web server's 413 page", "413 Request Entity Too Large\nnginx\n"],
    ["an unreadable call", 'noty("x.torrent - "+theUILang[,"error");'],
  ])("are one error when the answer carries no result: %s", (_label, answer) => {
    expect(uploadResults(answer)).toEqual([{ text: theUILang.addTorrentFailed, kind: "error" }]);
  });

  it("ignore text printed around the calls", () => {
    const notice = "<br />\n<b>Deprecated</b>:  Creation of dynamic property on line <b>3</b><br />\n";
    expect(uploadResults(notice + noty("good.torrent", "Success", "success") + "\n")).toEqual([
      { text: "good.torrent - " + theUILang.addTorrentSuccess, kind: "success" },
    ]);
  });

  it("run nothing on a line that is not a result call", () => {
    delete window.__ran;
    uploadResults("window.__ran = true;\n" + noty("good.torrent", "Success", "success"));
    expect(window.__ran).toBeUndefined();
  });

  it("run nothing on a line that carries a result call after something else", () => {
    delete window.__ran;
    expect(uploadResults("window.__ran = true; " + noty("good.torrent", "Success", "success"))).toEqual([
      { text: theUILang.addTorrentFailed, kind: "error" },
    ]);
    expect(window.__ran).toBeUndefined();
  });

  it("hand back the server's names as text, without touching the page", () => {
    // Captured from php/addtorrent.php: it writes the name as an escaped
    // JSON literal, so a name that reads as markup or as code stays data
    const hostile = String.raw`noty("\u003C/script\u003E\u003Cimg src=x onerror=\u0022window.__xss=1\u0022\u003E.torrent - "+theUILang["addTorrent"+"FailedFile"],"error");`;
    delete window.__xss;
    expect(uploadResults(hostile)).toEqual([
      { text: '</script><img src=x onerror="window.__xss=1">.torrent - ' + theUILang.addTorrentFailedFile, kind: "error" },
    ]);
    expect(window.__xss).toBeUndefined();
    expect(document.querySelector("img")).toBeNull();
  });

  it("are always strings, whatever the call passed", () => {
    expect(uploadResults("noty(1,undefined);")).toEqual([{ text: "1", kind: "undefined" }]);
  });
});

// js/common.js is loaded before js/content.js and before the plugins, and
// these two read theUILang only when called, so a plugin's own init.js sees
// them and nothing here needs anything the desktop UI defines.
describe("the helpers", () => {
  it("are reachable with only lang and common.js loaded", () => {
    expect(typeof window.uploadFrameAnswer).toBe("function");
    expect(typeof window.uploadResults).toBe("function");
    expect(window.theDialogManager).toBeUndefined();
    expect(window.theWebUI.version).toBeUndefined();
  });
});
