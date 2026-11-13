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
window.plugin = { disable: jest.fn(), loadLang: jest.fn() };
window.theDialogManager = { addHandler: jest.fn() };
window.theWebUI = { getTrackerName: () => "" };
const pluginEl = document.createElement("script");
pluginEl.textContent = readFileSync("../plugins/mobile/init.js", "utf8");
document.body.appendChild(pluginEl);
const markup = readFileSync("../plugins/mobile/mobile.html", "utf8");

// The mobile add page sends its forms with fetch() and reads
// php/addtorrent.php's json results, so it needs no frame and a server that
// forbids framing its pages still shows the result.
const results = (...items) => JSON.stringify({ results: items.map(([status, name]) => (name === undefined ? { status } : { status, name })) });
const ANSWER = {
  failedFile: results(["FailedFile", "bad.torrent"]),
  success: results(["Success", "good.torrent"]),
  duplicate: results(["Duplicate", "again.torrent"]),
  failedDirectory: results(["FailedDirectory"]),
  failedURL: results(["FailedURL", "http://bad.invalid/x.torrent"]),
  batch: results(
    ["Success", "Кириллица.torrent"],
    ["FailedFile", '"+(window.__xss=1)+".torrent'],
    ["Duplicate", '</script><img src=x onerror="window.__xss=2">.torrent'],
    ["FailedFile", "line\u2028break\u2029.torrent"],
    ["Success", "plain.torrent"],
  ),
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

/** Every request the page made, in order, each with the means to answer it. */
let requests;

/** Send a form as its submit handler does; answers the request it made. */
function startUpload(form) {
  plugin.sendAddForm(form);
  return requests[requests.length - 1];
}

async function settle() {
  for (let i = 0; i < 20; i++)
    await Promise.resolve();
}

/** The server answered the request with this status and body. */
async function finishUpload(request, body, status = 200) {
  request.resolve({ ok: status >= 200 && status < 300, status, text: () => Promise.resolve(body) });
  await settle();
}

/** The connection was lost: fetch() rejects. */
async function loseUpload(request) {
  request.reject(new TypeError("Failed to fetch"));
  await settle();
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
  requests = [];
  window.fetch = jest.fn((url, init) => new Promise((resolve, reject) => {
    requests.push({ url: String(url), init, resolve, reject });
  }));
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
  test("the add page has no frame for an upload to load into", () => {
    expect(document.querySelectorAll("iframe")).toHaveLength(0);
    expect(fileForm().getAttribute("target")).toBeNull();
    expect(urlForm().getAttribute("target")).toBeNull();
  });

  test("a file upload posts the form multipart, asking for every result as json", () => {
    fileForm().action = "php/addtorrent.php?not_add_path=1&";
    const { url, init } = startUpload(fileForm());
    expect(url).toBe("http://localhost/php/addtorrent.php?not_add_path=1&");
    expect(init.method).toBe("POST");
    expect(init.credentials).toBe("same-origin");
    expect(init.body).toBeInstanceOf(FormData);
    expect(init.body.get("json_results")).toBe("1");
  });

  test("a URL add posts the form url-encoded, asking for every result as json", () => {
    const { init } = startUpload(urlForm());
    expect(init.body).toBeInstanceOf(URLSearchParams);
    expect(init.body.get("url")).toBe("magnet:?xt=urn:btih:" + "a".repeat(40));
    expect(init.body.get("json_results")).toBe("1");
  });

  test("a refused file shows its error and keeps the form for correction", async () => {
    await finishUpload(startUpload(fileForm()), ANSWER.failedFile);

    expect(alerts()).toEqual([
      { text: "bad.torrent - " + theUILang.addTorrentFailedFile, kind: "alert-danger" },
    ]);
    expectFormKept();
    expect(plugin.update).toHaveBeenCalledWith(true);
  });

  test("a success clears the forms and returns to the list", async () => {
    await finishUpload(startUpload(fileForm()), ANSWER.success);

    expect(alerts()).toEqual([
      { text: "good.torrent - " + theUILang.addTorrentSuccess, kind: "alert-success" },
    ]);
    expectFormCleared();
    expect(plugin.showList).toHaveBeenCalledTimes(1);
    expect(plugin.update).toHaveBeenCalledWith(true);
  });

  test("a success does not leave the page the user moved to meanwhile", async () => {
    const request = startUpload(urlForm());
    plugin.currentPage = "torrentDetails";
    await finishUpload(request, ANSWER.success);

    expect(alerts()).toEqual([
      { text: "good.torrent - " + theUILang.addTorrentSuccess, kind: "alert-success" },
    ]);
    expectFormCleared();
    expect(plugin.showList).not.toHaveBeenCalled();
  });

  test("a duplicate is a warning and keeps the form for correction", async () => {
    await finishUpload(startUpload(fileForm()), ANSWER.duplicate);

    expect(alerts()).toEqual([
      { text: "again.torrent - " + theUILang.addTorrentDuplicate, kind: "alert-warning" },
    ]);
    expectFormKept();
  });

  test("refusals without a name and URL refusals are shown", async () => {
    await finishUpload(startUpload(fileForm()), ANSWER.failedDirectory);
    await finishUpload(startUpload(urlForm()), ANSWER.failedURL);

    expect(alerts()).toEqual([
      { text: theUILang.addTorrentFailedDirectory, kind: "alert-danger" },
      { text: "http://bad.invalid/x.torrent - " + theUILang.addTorrentFailedURL, kind: "alert-danger" },
    ]);
    expectFormKept();
  });

  test("a batch reports every item literally and runs the success effects once", async () => {
    await finishUpload(startUpload(fileForm()), ANSWER.batch);

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
    // Each item is shown once, as a mobile alert, not also as a toast
    expect(window.noty).not.toHaveBeenCalled();
    expectFormCleared();
    expect(plugin.showList).toHaveBeenCalledTimes(1);
  });

  test.each([
    ["the origin check's refusal", "Forbidden", 403],
    ["a web server error page", "<html><head><title>502 Bad Gateway</title></head><body><center><h1>502 Bad Gateway</h1></center><hr><center>nginx</center></body></html>", 502],
    ["an empty answer", "", 200],
    ["a result page instead of json", 'noty("good.torrent - "+theUILang["addTorrent"+"Success"],"success");', 200],
  ])("an answer without a result is a visible failure: %s", async (label, answer, status) => {
    await finishUpload(startUpload(fileForm()), answer, status);

    expect(alerts()).toEqual([{ text: theUILang.addTorrentFailed, kind: "alert-danger" }]);
    expectFormKept();
    expect(plugin.update).toHaveBeenCalledWith(true);
  });

  test("a lost answer is a visible failure and the forms become usable again", async () => {
    await loseUpload(startUpload(urlForm()));

    expect(alerts()).toEqual([{ text: theUILang.addTorrentFailed, kind: "alert-danger" }]);
    expectFormKept();
    expect(plugin.update).toHaveBeenCalledWith(true);
  });

  test("uploads in flight together each report their own answer", async () => {
    const first = startUpload(fileForm());
    const second = startUpload(urlForm());
    expect(requests).toHaveLength(2);

    await finishUpload(first, ANSWER.failedFile);
    expect(alerts()).toEqual([
      { text: "bad.torrent - " + theUILang.addTorrentFailedFile, kind: "alert-danger" },
    ]);

    await finishUpload(second, ANSWER.failedURL);
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
