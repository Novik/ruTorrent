import { readFileSync } from "fs";

// The add dialog sends its two forms with fetch() and asks php/addtorrent.php
// for every item's result as json (json_results), so an upload needs no frame:
// a server that forbids framing its pages (X-Frame-Options: DENY, or a CSP
// frame-ancestors 'none') still shows the result. An answer without a
// result -- the origin check's 403, the web server's 413 or 5xx page, a lost
// connection -- is one failure, and the selection is kept to send again.

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

/** Every request the page made, in order, each with the means to answer it. */
let requests;
beforeEach(() => {
  requests = [];
  window.fetch = jest.fn((url, init) => new Promise((resolve, reject) => {
    requests.push({ url: String(url), init, resolve, reject });
  }));
});

/** Answer a request with this status and body, as the server would. */
async function answer(request, body, status = 200) {
  request.resolve({ ok: status >= 200 && status < 300, status, text: () => Promise.resolve(body) });
  await settle();
}

/** The connection was lost: fetch() rejects. */
async function lose(request) {
  request.reject(new TypeError("Failed to fetch"));
  await settle();
}

async function settle() {
  for (let i = 0; i < 20; i++)
    await Promise.resolve();
}

const results = (...items) => JSON.stringify({ results: items.map(([status, name]) => (name === undefined ? { status } : { status, name })) });

const nginx413 = "<html>\n<head><title>413 Request Entity Too Large</title></head>\n<body>\n<center><h1>413 Request Entity Too Large</h1></center>\n<hr><center>nginx</center>\n</body>\n</html>\n";

let notes;
beforeEach(() => {
  notes = [];
  window.noty = (text, kind) => notes.push([String(text), kind]);
});

describe("the dialog", () => {
  it("has no frame for an upload to load into", () => {
    expect(document.querySelectorAll("iframe")).toHaveLength(0);
    expect($("#addtorrent").attr("target")).toBeUndefined();
    expect($("#addtorrenturl").attr("target")).toBeUndefined();
  });
});

describe("the file upload", () => {
  beforeEach(() => {
    select(["one.torrent"]);
    $("#torrents_start_stopped").prop("checked", true);
    $("#dir_edit").val("/downloads/new");
    // The page does not navigate: the form is sent by the script
    expect($("#addtorrent").triggerHandler("submit")).toBe(false);
    expect($("#add_button").prop("disabled")).toBe(true);
  });

  afterEach(() => {
    $("#torrents_start_stopped").prop("checked", false);
    $("#dir_edit").val("");
  });

  it("posts the form's own fields, multipart, asking for every result as json", () => {
    expect(requests).toHaveLength(1);
    const { url, init } = requests[0];
    expect(url).toBe("http://localhost/php/addtorrent.php?torrents_start_stopped=1&dir_edit=%2Fdownloads%2Fnew");
    expect(init.method).toBe("POST");
    expect(init.credentials).toBe("same-origin");
    expect(init.body).toBeInstanceOf(FormData);
    expect(init.body.get("json_results")).toBe("1");
    expect(init.body.get("dir_edit")).toBe("/downloads/new");
    expect(init.body.get("torrents_start_stopped")).toBe("on");
  });

  it("shows the server's result and clears the selection once something was added", async () => {
    await answer(requests[0], results(["Success", "one.torrent"]));
    expect(notes).toEqual([["one.torrent - " + theUILang.addTorrentSuccess, "success"]]);
    expect($("#torrent_file").val()).toBe("");
    expect($("#add_button").prop("disabled")).toBe(true);
  });

  it.each([
    ["the web server's 413 page", nginx413, 413],
    ["the origin check's 403", "Forbidden", 403],
    ["a server error page", "Internal Server Error", 500],
    ["an empty answer", "", 200],
    ["a result page instead of json", 'noty("one.torrent - "+theUILang["addTorrent"+"Success"],"success");', 200],
    ["json without results", "{}", 200],
    ["json with no item", '{"results":[]}', 200],
  ])("shows %s as a failure and keeps the selection to send again", async (_name, body, status) => {
    await answer(requests[0], body, status);
    expect(notes).toEqual([[theUILang.addTorrentFailed, "error"]]);
    expect($("#torrent_file").val()).toBe("C:\\fakepath\\one.torrent");
    expect($("#add_button").prop("disabled")).toBe(false);
  });

  it("shows a lost connection as a failure and keeps the selection", async () => {
    await lose(requests[0]);
    expect(notes).toEqual([[theUILang.addTorrentFailed, "error"]]);
    expect($("#torrent_file").val()).toBe("C:\\fakepath\\one.torrent");
    expect($("#add_button").prop("disabled")).toBe(false);
  });

  it("keeps the selection when the server refused every item", async () => {
    await answer(requests[0], results(["FailedFile", "one.torrent"]));
    expect(notes).toEqual([["one.torrent - " + theUILang.addTorrentFailedFile, "error"]]);
    expect($("#torrent_file").val()).toBe("C:\\fakepath\\one.torrent");
    expect($("#add_button").prop("disabled")).toBe(false);
  });

  it("shows every item of a batch", async () => {
    await answer(requests[0], results(["Success", "one.torrent"], ["Duplicate", "two.torrent"]));
    expect(notes).toEqual([
      ["one.torrent - " + theUILang.addTorrentSuccess, "success"],
      ["two.torrent - " + theUILang.addTorrentDuplicate, "alert"],
    ]);
  });

  it("shows a result without a name by its wording alone", async () => {
    await answer(requests[0], results(["FailedDirectory"]));
    expect(notes).toEqual([[theUILang.addTorrentFailedDirectory, "error"]]);
  });

  it("hands the names to noty() as text", async () => {
    delete window.__xss;
    const hostile = '"+(window.__xss=1)+"</script><img src=x onerror="window.__xss=2">.torrent';
    await answer(requests[0], results(["FailedFile", hostile]));
    expect(notes).toEqual([[hostile + " - " + theUILang.addTorrentFailedFile, "error"]]);
    expect(window.__xss).toBeUndefined();
    expect(document.querySelector("img")).toBeNull();
  });
});

describe("a file that is not a torrent", () => {
  it("is refused before anything is sent", () => {
    window.alert = jest.fn();
    select(["notes.txt"]);
    expect($("#addtorrent").triggerHandler("submit")).toBe(false);
    expect(window.alert).toHaveBeenCalledWith(theUILang.Not_torrent_file);
    expect(requests).toHaveLength(0);
  });
});

describe("the URL add", () => {
  const magnet = "magnet:?xt=urn:btih:0123456789ABCDEF0123456789ABCDEF01234567";

  beforeEach(() => {
    $("#url").val(magnet);
    expect($("#addtorrenturl").triggerHandler("submit")).toBe(false);
    expect($("#add_url").prop("disabled")).toBe(true);
  });

  it("posts the URLs url-encoded, asking for every result as json", () => {
    expect(requests).toHaveLength(1);
    const { url, init } = requests[0];
    expect(url).toBe("http://localhost/php/addtorrent.php");
    expect(init.method).toBe("POST");
    expect(init.body).toBeInstanceOf(URLSearchParams);
    expect(init.body.get("url")).toBe(magnet);
    expect(init.body.get("json_results")).toBe("1");
  });

  it("clears the URLs once something was added", async () => {
    await answer(requests[0], results(["Success", magnet]));
    expect(notes).toEqual([[magnet + " - " + theUILang.addTorrentSuccess, "success"]]);
    expect($("#url").val()).toBe("");
    expect($("#add_url").prop("disabled")).toBe(true);
  });

  it.each([
    ["the origin check's 403", "Forbidden", 403],
    ["the web server's 413 page", nginx413, 413],
    ["an empty answer", "", 200],
  ])("shows %s as a failure and keeps the URLs to send again", async (_name, body, status) => {
    await answer(requests[0], body, status);
    expect(notes).toEqual([[theUILang.addTorrentFailed, "error"]]);
    expect($("#url").val()).toBe(magnet);
    expect($("#add_url").prop("disabled")).toBe(false);
  });

  it("shows a lost connection as a failure and keeps the URLs", async () => {
    await lose(requests[0]);
    expect(notes).toEqual([[theUILang.addTorrentFailed, "error"]]);
    expect($("#url").val()).toBe(magnet);
    expect($("#add_url").prop("disabled")).toBe(false);
  });
});

describe("independent add forms", () => {
  it("restores only the form whose answer arrived", async () => {
    select(["retry.torrent"]);
    $("#url").val("https://example.invalid/new.torrent");
    $("#addtorrent").triggerHandler("submit");
    $("#addtorrenturl").triggerHandler("submit");
    expect(requests).toHaveLength(2);
    await answer(requests[0], "Forbidden", 403);
    expect($("#torrent_file").val()).toBe("C:\\fakepath\\retry.torrent");
    expect($("#add_button").prop("disabled")).toBe(false);
    expect($("#url").val()).toBe("https://example.invalid/new.torrent");
    expect($("#add_url").prop("disabled")).toBe(true);
    await answer(requests[1], results(["Success", "new.torrent"]));
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
