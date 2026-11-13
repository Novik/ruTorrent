import { readFileSync } from "fs";

// js/common.js holds the add-torrent form sender and its result reader, which
// the desktop dialog (js/content.js) and the mobile add page
// (plugins/mobile/init.js) share, and the reader of a result page loaded into
// a frame, kept for code outside them. Everything the two interfaces do with the
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

describe("the json results", () => {
  const json = (...items) => ({ results: items });

  it("are one entry per item, in the order the server wrote them, worded as the result page words them", () => {
    expect(addTorrentResults(json(
      { status: "Success", name: "one.torrent" },
      { status: "Duplicate", name: "two.torrent" },
      { status: "FailedFile", name: "three.torrent" },
    ))).toEqual([
      { text: "one.torrent - " + theUILang.addTorrentSuccess, kind: "success" },
      { text: "two.torrent - " + theUILang.addTorrentDuplicate, kind: "alert" },
      { text: "three.torrent - " + theUILang.addTorrentFailedFile, kind: "error" },
    ]);
  });

  it("word an item without a name by its status alone", () => {
    expect(addTorrentResults(json({ status: "FailedDirectory" }))).toEqual([
      { text: theUILang.addTorrentFailedDirectory, kind: "error" },
    ]);
  });

  it("keep a name as the text it is", () => {
    const name = '</script><img src=x onerror="window.__xss=1">.torrent';
    expect(addTorrentResults(json({ status: "Success", name }))).toEqual([
      { text: name + " - " + theUILang.addTorrentSuccess, kind: "success" },
    ]);
  });

  it.each([
    ["no answer", null],
    ["an answer that is not an object", "Forbidden"],
    ["an object without results", {}],
    ["results that are not a list", { results: "Success" }],
    ["an empty list", { results: [] }],
    ["items without a status", { results: [null, 1, { name: "x.torrent" }] }],
  ])("are one error when the answer carries no result: %s", (_label, answer) => {
    expect(addTorrentResults(answer)).toEqual([{ text: theUILang.addTorrentFailed, kind: "error" }]);
  });
});

describe("a form sent with uploadTorrentForm()", () => {
  const form = (html) => {
    document.body.insertAdjacentHTML("beforeend", html);
    return document.body.lastElementChild;
  };
  const reply = (status, body) => () => Promise.resolve({ ok: status >= 200 && status < 300, status, text: () => Promise.resolve(body) });

  afterEach(() => {
    document.querySelectorAll("form").forEach((f) => f.remove());
    delete window.fetch;
  });

  it("is posted to its action with its own fields and json_results, and needs no frame", async () => {
    window.fetch = jest.fn(reply(200, '{"results":[{"status":"Success","name":"a.torrent"}]}'));
    const done = jest.fn();
    const f = form('<form action="php/addtorrent.php?label=x" method="post" enctype="multipart/form-data"><input name="dir_edit" value="/d"></form>');
    await uploadTorrentForm(f, done);
    expect(window.fetch).toHaveBeenCalledTimes(1);
    const [url, init] = window.fetch.mock.calls[0];
    expect(url).toBe("http://localhost/php/addtorrent.php?label=x");
    expect(init.method).toBe("POST");
    expect(init.credentials).toBe("same-origin");
    expect(init.body).toBeInstanceOf(FormData);
    expect([...init.body.entries()]).toEqual([["dir_edit", "/d"], ["json_results", "1"]]);
    expect(done).toHaveBeenCalledWith([{ text: "a.torrent - " + theUILang.addTorrentSuccess, kind: "success" }]);
    expect(document.querySelector("iframe")).toBeNull();
  });

  it("is url-encoded when the form is not multipart", async () => {
    window.fetch = jest.fn(reply(200, '{"results":[{"status":"Success"}]}'));
    const f = form('<form action="php/addtorrent.php" method="post"><textarea name="url">magnet:?xt=urn:btih:1</textarea></form>');
    await uploadTorrentForm(f, () => {});
    const body = window.fetch.mock.calls[0][1].body;
    expect(body).toBeInstanceOf(URLSearchParams);
    expect(body.toString()).toBe("url=magnet%3A%3Fxt%3Durn%3Abtih%3A1&json_results=1");
  });

  it.each([
    ["the origin check's 403", reply(403, "Forbidden")],
    ["a 413 page", reply(413, "413 Request Entity Too Large")],
    ["a 200 that is not json", reply(200, "<html>login</html>")],
    ["a lost connection", () => Promise.reject(new TypeError("Failed to fetch"))],
    ["a body lost on the way", () => Promise.resolve({ ok: true, status: 200, text: () => Promise.reject(new TypeError("network error")) })],
  ])("ends with one error on %s", async (_label, answer) => {
    window.fetch = jest.fn(answer);
    const done = jest.fn();
    await uploadTorrentForm(form('<form action="php/addtorrent.php" method="post"></form>'), done);
    expect(done).toHaveBeenCalledTimes(1);
    expect(done).toHaveBeenCalledWith([{ text: theUILang.addTorrentFailed, kind: "error" }]);
  });
});

// js/common.js is loaded before js/content.js and before the plugins, and
// these read theUILang only when called, so a plugin's own init.js sees
// them and nothing here needs anything the desktop UI defines.
describe("the helpers", () => {
  it("are reachable with only lang and common.js loaded", () => {
    expect(typeof window.uploadTorrentForm).toBe("function");
    expect(typeof window.addTorrentResults).toBe("function");
    expect(typeof window.uploadFrameAnswer).toBe("function");
    expect(typeof window.uploadResults).toBe("function");
    expect(window.theDialogManager).toBeUndefined();
    expect(window.theWebUI.version).toBeUndefined();
  });
});
