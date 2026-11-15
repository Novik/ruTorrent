import { readFileSync } from "fs";

// ArrowLeft in a _getdir directory list opens the parent directory: the
// "../" entry and nothing else, whatever the other entries are called.
// plugins/_getdir/init.js is loaded as it ships, with the server answered
// by a stub of listdir.php.

window.$ = window.jQuery = require("jquery");

// jsdom has no innerText; the list entries hold text only, so textContent
// is what a browser would answer.
Object.defineProperty(window.HTMLElement.prototype, "innerText", {
  configurable: true,
  get() { return this.textContent; },
});

const initSrc = readFileSync("../plugins/_getdir/init.js", { encoding: "utf-8" });

let listings;
let requested;

function browser() {
  document.body.innerHTML = `
    <div class="dlg-window" id="tadd"><input type="text" id="dir_edit"></div>
    <div id="dir-container"></div>`;
  $("#tadd").data("dnd", { options: {} });
  requested = [];
  window.$.ajax = (url, options) => {
    const dir = decodeURIComponent(new URL(url, "http://x/").searchParams.get("dir"));
    requested.push(dir);
    options.success(listings[dir] || { path: dir, directories: [], files: [] });
  };
  const plugin = { loadLang() {}, loadMainCSS() {} };
  const theWebUI = {};
  const theDialogManager = { addHandler() {}, bringToTop() {} };
  const theOptionsWindow = { addHandler() {} };
  const theUILang = { typeToFilter: "Type to filter" };
  new Function("$", "plugin", "theWebUI", "theDialogManager", "theOptionsWindow", "theUILang", initSrc)(
    window.$, plugin, theWebUI, theDialogManager, theOptionsWindow, theUILang
  );
  return new theWebUI.rDirBrowser("dir_edit");
}

function openAt(dir) {
  const b = browser();
  $("#dir_edit").val(dir);
  b.show();
  requested = [];
  return b;
}

function pressArrowLeft(b) {
  b.frame.trigger($.Event("keydown", { key: "ArrowLeft" }));
}

describe("_getdir ArrowLeft", () => {
  test("opens the parent when a sibling's name contains two dots", () => {
    listings = {
      "/top/dl/": { path: "/top/dl/", directories: ["..", "a..b", "plain"], files: [] },
      "/top/dl/../": { path: "/top/", directories: ["dl"], files: [] },
    };
    const b = openAt("/top/dl/");
    pressArrowLeft(b);
    expect(requested).toEqual(["/top/dl/../"]);
    expect($("#dir_edit").val()).toBe("/top/");
  });

  test("does nothing at the top directory, which lists no parent", () => {
    listings = {
      "/top/": { path: "/top/", directories: ["a..b", "dl"], files: [] },
    };
    const b = openAt("/top/");
    pressArrowLeft(b);
    expect(requested).toEqual([]);
    expect($("#dir_edit").val()).toBe("/top/");
  });

  test("opens the parent when no other name has dots", () => {
    listings = {
      "/top/dl/": { path: "/top/dl/", directories: ["..", "plain"], files: [] },
      "/top/dl/../": { path: "/top/", directories: ["dl"], files: [] },
    };
    const b = openAt("/top/dl/");
    pressArrowLeft(b);
    expect(requested).toEqual(["/top/dl/../"]);
    expect($("#dir_edit").val()).toBe("/top/");
  });
});
