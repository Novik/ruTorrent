import { readFileSync } from "fs";

window.$ = require("jquery");
window.jQuery = window.$;

const hash = "A".repeat(40);

beforeAll(() => {
  for (const file of ["lang/en.js", "js/common.js"]) {
    const script = document.createElement("script");
    script.textContent = readFileSync(`../${file}`, "utf8");
    document.body.appendChild(script);
  }
});

beforeEach(() => {
  document.body.innerHTML = '<div id="detailsFilesPage"></div>';
  window.plugin = { disable: jest.fn() };
  window.theWebUI = { getTrackerName: () => "", sizeDecimalPlaces: () => 1 };
  const script = document.createElement("script");
  script.textContent = readFileSync("../plugins/mobile/init.js", "utf8");
  document.body.appendChild(script);
  window.mobile = plugin;
  plugin.torrent = { hash, downloaded: 0 };
});

function file(name, pathKey, priority = 1) {
  return { name, pathKey, size: 100, done: 0, priority };
}

function loadFiles(files) {
  const requests = [];
  plugin.request = (query, receive) => {
    if (query === `?action=getfiles&hash=${hash}`) receive({ [hash]: files });
    else requests.push(query);
  };
  plugin.loadFiles();
  return requests;
}

function refreshFiles() {
  const visible = $.expr.pseudos.visible;
  $.expr.pseudos.visible = () => true;
  try {
    plugin.refreshFiles();
  } finally {
    $.expr.pseudos.visible = visible;
  }
}

test("equal directory labels keep separate navigation and priority targets", () => {
  const requests = loadFiles([
    file("Сезон/series.mkv", "%D1%E5%E7%EE%ED/series.mkv"),
    file("Сезон/series.mkv", undefined, 2),
  ]);
  const folders = $("#detailsFilesPage > div > a");
  expect(folders).toHaveLength(2);
  expect(folders.map((_, a) => $(a).text().trim()).get()).toEqual(["Сезон", "Сезон"]);
  $("#detailsFilesPage select").eq(0).val("0").trigger("change");
  $("#detailsFilesPage select").eq(1).val("1").trigger("change");
  expect(requests).toEqual([
    `?action=setprio&hash=${hash}&v=0&s=0`,
    `?action=setprio&hash=${hash}&v=1&s=1`,
  ]);

  folders.eq(0).trigger("click");
  expect($("#file0")).toHaveLength(1);
  expect($("#file1")).toHaveLength(0);
  $("#detailsFilesPage > a").trigger("click");
  $("#detailsFilesPage > div > a").eq(1).trigger("click");
  expect($("#file1")).toHaveLength(1);
  expect($("#file0")).toHaveLength(0);
});

test("equal file labels retain every original file index", () => {
  const requests = loadFiles([
    file("серия.mkv", "%F1%E5%F0%E8%FF.mkv"),
    file("серия.mkv", undefined),
  ]);
  expect($("#file0")).toHaveLength(1);
  expect($("#file1")).toHaveLength(1);
  expect($("#detailsFilesPage > div > a").map((_, a) => $(a).text().trim()).get())
    .toEqual(["серия.mkv", "серия.mkv"]);
  $("#detailsFilesPage select").eq(0).val("2").trigger("change");
  $("#detailsFilesPage select").eq(1).val("0").trigger("change");
  expect(requests).toEqual([
    `?action=setprio&hash=${hash}&v=0&s=2`,
    `?action=setprio&hash=${hash}&v=1&s=0`,
  ]);
});

test("UTF8 and legacy children share the same raw prefix without exposing encoded keys", () => {
  const requests = loadFiles([
    file("a %!()'/серия.mkv", "a%20%25%21%28%29%27/%F1%E5%F0%E8%FF.mkv"),
    file("a %!()'/映画 🎬.mkv", undefined),
  ]);
  const folders = $("#detailsFilesPage > div > a");
  expect(folders).toHaveLength(1);
  expect(folders.text().trim()).toBe("a %!()'");
  $("#detailsFilesPage select").val("0").trigger("change");
  expect(requests).toEqual([`?action=setprio&hash=${hash}&v=0&v=1&s=0`]);
  folders.trigger("click");
  expect($("#detailsFilesPage > div > a").map((_, a) => $(a).text().trim()).get())
    .toEqual(["серия.mkv", "映画 🎬.mkv"]);
});

test("refresh updates both raw files when their display names match", () => {
  const files = [
    file("серия.mkv", "%F1%E5%F0%E8%FF.mkv"),
    file("серия.mkv", undefined),
  ];
  loadFiles(files);
  files[0].done = 10;
  files[1].done = 80;
  refreshFiles();
  // Redrawing reads the retained tree, so an update of only the visible cells
  // cannot hide a wrong path lookup during the refresh.
  plugin.drawFiles("");
  expect($("#file0 tr:first td:last").text()).toContain("10.0%");
  expect($("#file1 tr:first td:last").text()).toContain("80.0%");
});

test.each([
  ["\\xD1/\\xE0/\\xF1.mkv", "Сезон/Часть/серия.mkv"],
  ["Сезон/Часть/серия.mkv", "\\xD1/\\xE0/\\xF1.mkv"],
  ["\\xD1/\\xE0/\\xF1.mkv", "<b>/<i>/<img src=x>.mkv"],
])("refresh changes recovered labels from %s without resetting the open file", (before, after) => {
  const files = [file(before, "%D1/%E0/%F1.mkv")];
  const requests = loadFiles(files);
  $("#detailsFilesPage > div > a").trigger("click");
  $("#detailsFilesPage > div > a").trigger("click");
  $("#detailsFilesPage > div > a").trigger("click");
  $("#detailsFilesPage > div > button").trigger("click");
  const details = $("#file0")[0];
  const priority = $("#detailsFilesPage select")[0];
  expect($(details).css("display")).not.toBe("none");
  expect($(priority).css("display")).not.toBe("none");

  files[0].name = after;
  files[0].done = 30;
  refreshFiles();
  expect($("#detailsFilesPage > div > a").text().trim()).toBe(after.split("/")[2]);
  expect($("#detailsFilesPage > div > a > .bi-file-earmark")).toHaveLength(1);
  expect($("#detailsFilesPage .file-name").children()).toHaveLength(0);
  expect($("#file0")[0]).toBe(details);
  expect($("#detailsFilesPage select")[0]).toBe(priority);
  expect($(details).css("display")).not.toBe("none");
  expect($(priority).css("display")).not.toBe("none");
  expect($("#file0 tr:first td:last").text()).toContain("30.0%");
  $(priority).val("2").trigger("change");
  expect(requests).toEqual([`?action=setprio&hash=${hash}&v=0&s=2`]);

  $("#detailsFilesPage > a").trigger("click");
  expect($("#detailsFilesPage > div > a").text().trim()).toBe(after.split("/")[1]);
  $("#detailsFilesPage > a").trigger("click");
  expect($("#detailsFilesPage > div > a").text().trim()).toBe(after.split("/")[0]);

  // A visible directory label changes immediately as well, without navigation.
  files[0].name = before;
  const directoryLink = $("#detailsFilesPage > div > a")[0];
  refreshFiles();
  expect($("#detailsFilesPage > div > a")[0]).toBe(directoryLink);
  expect($(directoryLink).text().trim()).toBe(before.split("/")[0]);
  expect($(directoryLink).children('.bi-folder2-open')).toHaveLength(1);
});
