import { readFileSync } from "fs";

window.$ = require("jquery");
window.jQuery = window.$;

// lang/en.js before common.js: common.js reads theUILang at load time, and
// the plugin's showAlert() escapes with common.js's escapeHTML().
for (const src of ["../lang/en.js", "../js/common.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}
window.plugin = { disable: jest.fn() };
window.theWebUI = { getTrackerName: () => "" };
const pluginEl = document.createElement("script");
pluginEl.textContent = readFileSync("../plugins/mobile/init.js", "utf8");
document.body.appendChild(pluginEl);
const markup = readFileSync("../plugins/mobile/mobile.html", "utf8");

// NEW is captured verbatim from the result branch of php/addtorrent.php at
// upstream f9c5a32bd0e6d64f9411e6d56fe4ed532a1dc0e1, executed with these
// statuses and names. OLD is captured from upstream de84e273ff8b959e,
// the parent of #3280. These are server output, including its escaping.
const NEW = {
  failedFile: String.raw`noty("bad.torrent - "+theUILang["addTorrent"+"FailedFile"],"error");`,
  success: String.raw`noty("good.torrent - "+theUILang["addTorrent"+"Success"],"success");`,
  duplicate: String.raw`noty("again.torrent - "+theUILang["addTorrent"+"Duplicate"],"alert");`,
  failedDirectory: String.raw`noty(""+theUILang["addTorrent"+"FailedDirectory"],"error");`,
  failedURL: String.raw`noty("http://bad.invalid/x.torrent - "+theUILang["addTorrent"+"FailedURL"],"error");`,
  batch: String.raw`noty("\u041a\u0438\u0440\u0438\u043b\u043b\u0438\u0446\u0430.torrent - "+theUILang["addTorrent"+"Success"],"success");noty("\u0022+(window.__xss=1)+\u0022.torrent - "+theUILang["addTorrent"+"FailedFile"],"error");noty("\u003C/script\u003E\u003Cimg src=x onerror=\u0022window.__xss=2\u0022\u003E.torrent - "+theUILang["addTorrent"+"Duplicate"],"alert");noty("line\u2028break\u2029.torrent - "+theUILang["addTorrent"+"FailedFile"],"error");noty("plain.torrent - "+theUILang["addTorrent"+"Success"],"success");`,
};
const OLD = {
  failedFile: String.raw`noty("bad.torrent - "+theUILang.addTorrentFailedFile,"error");`,
  batch: String.raw`noty("one.torrent - "+theUILang.addTorrentFailedFile,"error");noty("two.torrent - "+theUILang.addTorrentSuccess,"success");noty(""+theUILang.addTorrentSuccess,"success");`,
  // htmlspecialchars() put an entity into the script; the page's text has
  // the character back, which is what the desktop evaluates
  ampersand: String.raw`noty("Tom &amp; Jerry.torrent - "+theUILang.addTorrentSuccess,"success");`,
};

const KINDS = ["alert-success", "alert-danger", "alert-warning", "alert-info"];
function alerts() {
  return $("#alert_placeholder .alert").map((i, el) => ({
    text: el.textContent,
    kind: KINDS.filter((c) => el.classList.contains(c)).join(" "),
  })).get();
}

// A file input cannot be given files in jsdom; stand in for one that holds a
// picked file until the plugin clears it.
function pickFile(name) {
  const input = document.getElementById("torrent_file");
  let value = "C:\\fakepath\\" + name;
  Object.defineProperty(input, "value", { configurable: true, get: () => value, set: (v) => { value = v; } });
  Object.defineProperty(input, "files", { configurable: true, get: () => (value ? [{ name }] : []) });
}

// The newest upload frame: createiFrame() prepends it to the add page.
function startUpload(form) {
  plugin.createiFrame(form);
  return document.querySelector("#addTorrent iframe");
}

// The frame has loaded the answer: a text/html document parsed from it, as
// the browser parses the response.
function finishUpload(frame, answer) {
  const page = new DOMParser().parseFromString(answer, "text/html");
  Object.defineProperty(frame, "contentDocument", {
    configurable: true,
    get: () => ({ location: { href: "http://rutorrent.invalid/php/addtorrent.php?result[]=" }, body: page.body }),
  });
  frame.dispatchEvent(new Event("load"));
}

// A failed request leaves the browser's own error page in the frame, which is
// cross-origin: contentDocument is null and contentWindow.document throws.
function loseUpload(frame) {
  Object.defineProperty(frame, "contentDocument", { configurable: true, get: () => null });
  Object.defineProperty(frame, "contentWindow", {
    configurable: true,
    get: () => ({
      get document() {
        throw new DOMException("Blocked a frame from accessing a cross-origin frame.", "SecurityError");
      },
    }),
  });
  frame.dispatchEvent(new Event("load"));
}

const fileForm = () => document.getElementById("addTorrentFile");
const urlForm = () => document.getElementById("addTorrentUrl");

beforeEach(() => {
  jest.useFakeTimers();
  document.body.innerHTML = markup;
  plugin.currentPage = "addTorrent";
  plugin.scrollToAddedUntil = 0;
  plugin.update = jest.fn();
  plugin.showList = jest.fn();
  window.noty = jest.fn();
  window.alert = jest.fn();
  delete window.__xss;
  pickFile("picked.torrent");
  $("#url").val("magnet:?xt=urn:btih:" + "a".repeat(40));
  $("#torrentFileSend, #addUrl").prop("disabled", true);
});

afterEach(() => {
  jest.useRealTimers();
});

function expectFormKept() {
  expect($("#url").val()).toBe("magnet:?xt=urn:btih:" + "a".repeat(40));
  expect($("#torrent_file").val()).toBe("C:\\fakepath\\picked.torrent");
  expect($("#torrentFileSend").prop("disabled")).toBe(false);
  expect($("#addUrl").prop("disabled")).toBe(false);
  expect(plugin.showList).not.toHaveBeenCalled();
  expect(plugin.scrollToAddedUntil).toBe(0);
}

function expectFormCleared() {
  expect($("#url").val()).toBe("");
  expect($("#torrent_file").val()).toBe("");
  expect($("#torrentFileSend").prop("disabled")).toBe(true);
  expect($("#addUrl").prop("disabled")).toBe(true);
  expect(plugin.scrollToAddedUntil).toBeGreaterThan(Date.now());
}

describe("mobile add-torrent result", () => {
  test("a refused file shows its error and keeps the form for correction", () => {
    const frame = startUpload(fileForm());
    finishUpload(frame, NEW.failedFile);

    expect(alerts()).toEqual([
      { text: "bad.torrent - " + theUILang.addTorrentFailedFile, kind: "alert-danger" },
    ]);
    expectFormKept();
    expect(frame.isConnected).toBe(false);
    expect(plugin.update).toHaveBeenCalledWith(true);
  });

  test("a success clears the forms and returns to the list", () => {
    const frame = startUpload(fileForm());
    finishUpload(frame, NEW.success);

    expect(alerts()).toEqual([
      { text: "good.torrent - " + theUILang.addTorrentSuccess, kind: "alert-success" },
    ]);
    expectFormCleared();
    expect(plugin.showList).toHaveBeenCalledTimes(1);
    expect(frame.isConnected).toBe(false);
    expect(plugin.update).toHaveBeenCalledWith(true);
  });

  test("a success does not leave the page the user moved to meanwhile", () => {
    const frame = startUpload(urlForm());
    plugin.currentPage = "torrentDetails";
    finishUpload(frame, NEW.success);

    expect(alerts()).toEqual([
      { text: "good.torrent - " + theUILang.addTorrentSuccess, kind: "alert-success" },
    ]);
    expectFormCleared();
    expect(plugin.showList).not.toHaveBeenCalled();
  });

  test("a duplicate is a warning and keeps the form for correction", () => {
    finishUpload(startUpload(fileForm()), NEW.duplicate);

    expect(alerts()).toEqual([
      { text: "again.torrent - " + theUILang.addTorrentDuplicate, kind: "alert-warning" },
    ]);
    expectFormKept();
  });

  test("refusals without a name and URL refusals are shown", () => {
    finishUpload(startUpload(fileForm()), NEW.failedDirectory);
    finishUpload(startUpload(urlForm()), NEW.failedURL);

    expect(alerts()).toEqual([
      { text: theUILang.addTorrentFailedDirectory, kind: "alert-danger" },
      { text: "http://bad.invalid/x.torrent - " + theUILang.addTorrentFailedURL, kind: "alert-danger" },
    ]);
    expectFormKept();
  });

  test("a batch reports every item literally and runs the success effects once", () => {
    const frame = startUpload(fileForm());
    finishUpload(frame, NEW.batch);

    expect(alerts()).toEqual([
      { text: "Кириллица.torrent - " + theUILang.addTorrentSuccess, kind: "alert-success" },
      { text: '"+(window.__xss=1)+".torrent - ' + theUILang.addTorrentFailedFile, kind: "alert-danger" },
      { text: '</script><img src=x onerror="window.__xss=2">.torrent - ' + theUILang.addTorrentDuplicate, kind: "alert-warning" },
      { text: "line\u2028break\u2029.torrent - " + theUILang.addTorrentFailedFile, kind: "alert-danger" },
      { text: "plain.torrent - " + theUILang.addTorrentSuccess, kind: "alert-success" },
    ]);
    // The hostile names stayed text: nothing ran, no markup was made
    expect(window.__xss).toBeUndefined();
    expect(window.alert).not.toHaveBeenCalled();
    expect(document.querySelector("#alert_placeholder img, #alert_placeholder script")).toBeNull();
    // The answer's noty is a local collector: the global one (the mobile
    // toast in production) would show every item a second time
    expect(window.noty).not.toHaveBeenCalled();
    expectFormCleared();
    expect(plugin.showList).toHaveBeenCalledTimes(1);
    expect(frame.isConnected).toBe(false);
  });

  test("the answer format before upstream #3280 is still reported", () => {
    finishUpload(startUpload(fileForm()), OLD.failedFile);
    expect(alerts()).toEqual([
      { text: "bad.torrent - " + theUILang.addTorrentFailedFile, kind: "alert-danger" },
    ]);
    expectFormKept();

    $("#alert_placeholder").empty();
    finishUpload(startUpload(fileForm()), OLD.batch);
    expect(alerts()).toEqual([
      { text: "one.torrent - " + theUILang.addTorrentFailedFile, kind: "alert-danger" },
      { text: "two.torrent - " + theUILang.addTorrentSuccess, kind: "alert-success" },
      { text: theUILang.addTorrentSuccess, kind: "alert-success" },
    ]);
    expectFormCleared();
    expect(plugin.showList).toHaveBeenCalledTimes(1);

    $("#alert_placeholder").empty();
    finishUpload(startUpload(fileForm()), OLD.ampersand);
    expect(alerts()).toEqual([
      { text: "Tom & Jerry.torrent - " + theUILang.addTorrentSuccess, kind: "alert-success" },
    ]);
  });

  test("text printed around the result does not hide it", () => {
    // A PHP notice printed into the page with html_errors on
    const notice = "<br />\n<b>Deprecated</b>:  Creation of dynamic property in <b>/var/www/php/x.php</b> on line <b>3</b><br />\n";
    finishUpload(startUpload(fileForm()), notice + NEW.failedFile + "\n");

    expect(alerts()).toEqual([
      { text: "bad.torrent - " + theUILang.addTorrentFailedFile, kind: "alert-danger" },
    ]);
  });

  test.each([
    ["the origin check's refusal", "Forbidden"],
    ["a web server error page", "<html><head><title>502 Bad Gateway</title></head><body><center><h1>502 Bad Gateway</h1></center><hr><center>nginx</center></body></html>"],
    ["an empty answer", ""],
  ])("an answer without a result is a visible failure: %s", (label, answer) => {
    const frame = startUpload(fileForm());
    finishUpload(frame, answer);

    expect(alerts()).toEqual([{ text: theUILang.addTorrentFailed, kind: "alert-danger" }]);
    expectFormKept();
    expect(frame.isConnected).toBe(false);
    expect(plugin.update).toHaveBeenCalledWith(true);
  });

  test("a lost answer is a visible failure and the forms become usable again", () => {
    const frame = startUpload(urlForm());
    loseUpload(frame);

    expect(alerts()).toEqual([{ text: theUILang.addTorrentFailed, kind: "alert-danger" }]);
    expectFormKept();
    expect(frame.isConnected).toBe(false);
    expect(plugin.update).toHaveBeenCalledWith(true);
  });

  test("the frame's own blank page is not an answer", () => {
    const frame = startUpload(fileForm());
    Object.defineProperty(frame, "contentDocument", {
      configurable: true,
      get: () => ({ location: { href: "about:blank" }, body: document.createElement("body") }),
    });
    frame.dispatchEvent(new Event("load"));

    expect(alerts()).toEqual([]);
  });

  test("uploads in flight together each report their own answer", () => {
    const first = startUpload(fileForm());
    const second = startUpload(urlForm());

    // Each form targets its own frame
    expect(first).not.toBe(second);
    expect(fileForm().target).toBe(first.name);
    expect(urlForm().target).toBe(second.name);
    expect(fileForm().target).not.toBe(urlForm().target);

    finishUpload(first, NEW.failedFile);
    expect(first.isConnected).toBe(false);
    expect(second.isConnected).toBe(true);

    finishUpload(second, NEW.failedURL);
    expect(second.isConnected).toBe(false);
    expect(alerts()).toEqual([
      { text: "bad.torrent - " + theUILang.addTorrentFailedFile, kind: "alert-danger" },
      { text: "http://bad.invalid/x.torrent - " + theUILang.addTorrentFailedURL, kind: "alert-danger" },
    ]);
  });

  test("every noty kind maps to one mobile alert class", () => {
    expect(["success", "error", "alert", "warning", "information", undefined].map((kind) => plugin.alertClass(kind)))
      .toEqual(["alert-success", "alert-danger", "alert-warning", "alert-warning", "alert-info", "alert-info"]);
  });
});
