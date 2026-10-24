import { readFileSync } from "fs";

window.$ = require("jquery");

function loadUI(enabled = null) {
  window.theWebUI = {
    settings: { "webui.needmessage": true },
    showFlags: 0xffff,
    systemInfo: { rTorrent: { apiVersion: 26, iVersion: 0x101a, started: true } },
  };
  for (const file of ["lang/en.js", "js/common.js", "js/content.js", "js/rtorrent.js"]) {
    const script = document.createElement("script");
    script.textContent = readFileSync(`../${file}`, "utf8");
    document.body.appendChild(script);
  }
  correctContent();
  if (enabled !== null) {
    const script = document.createElement("script");
    script.textContent = `(function () { var plugin = { enabled: ${enabled} };\n`
      + readFileSync("../plugins/httprpc/init.js", "utf8") + "\n})();";
    document.body.appendChild(script);
  }
}

const hash = "A".repeat(40);
const row = (name, size = 100) => [name, "1", "2", String(size), "1"];

test("file names use the shared server resolver with httprpc", () => {
  loadUI(true);
  theRequestManager.addRequest("fls", "f.get_priority=", () => {});
  const request = new rTorrentStub(`?action=getfiles&hash=${hash}`);
  expect(request.mountPoint).toBe("plugins/httprpc/action.php");
  expect(request.dataType).toBe("json");
  expect(request.content).toBe(`mode=fls&hash=${hash}&cmd=f.prioritize_first%3D&cmd=f.prioritize_last%3D&cmd=f.priority%3D`);
  expect(request.commands).toHaveLength(0);
});

test("an attested display name reaches the model and extension callbacks without losing its identity", () => {
  loadUI(true);
  let extensionResult;
  theRequestManager.addRequest("fls", "f.get_priority=", (hash, _files, value) => { extensionResult = { hash, value }; });
  const request = new rTorrentStub(`?action=getfiles&hash=${hash}`);
  const envelope = {
    name: "Panty And Stocking TV 01 серия.mkv",
    pathKey: "Panty%20And%20Stocking%20TV%2001%20%F1%E5%F0%E8%FF.mkv",
  };
  const input = row(envelope, 3014966836);
  input.push("0", "0", "2");
  const file = request.getResponse([input])[hash][0];
  expect(file.name).toBe(envelope.name);
  expect(file.pathKey).toBe(envelope.pathKey);
  expect(file.size).toBe(3014966836);
  expect(file.done).toBe(1507483418);
  expect(extensionResult).toEqual({ hash, value: "2" });
  expect(input[0]).toBe(envelope);
});

test("valid Unicode and file indices survive alongside legacy names", () => {
  loadUI(true);
  const request = new rTorrentStub(`?action=getfiles&hash=${hash}`);
  const files = request.getResponse([
    row("映画/Фильм 🎬.mkv"),
    row({ name: "Серия.mkv", pathKey: "%D1%E5%F0%E8%FF.mkv" }),
    row(" literal %F1!().mkv "),
  ])[hash];
  expect(files.map(file => file.name)).toEqual([
    "映画/Фильм 🎬.mkv", "Серия.mkv", " literal %F1!().mkv ",
  ]);
  const directory = new rDirectory();
  files.forEach((file, index) => directory.addFile(file, index));
  const ids = [];
  directory.getFilesIds(ids, "", "_f_1", 0);
  expect(ids).toEqual(["1"]);
});

test("equal display labels cannot merge separate raw directories or their selected files", () => {
  loadUI();
  const directory = new rDirectory();
  directory.addFile({ ...rowData(), name: "Сезон/one.mkv", pathKey: "%D1%E5%E7%EE%ED/one.mkv" }, 83);
  directory.addFile({ ...rowData(), name: "Сезон/two.mkv" }, 84);
  const raw = "_d_%D1%E5%E7%EE%ED";
  const utf8 = "_d_%D0%A1%D0%B5%D0%B7%D0%BE%D0%BD";
  const entries = directory.getDirectory();
  expect(Object.keys(entries)).toEqual([raw, utf8]);
  expect(entries[raw].data.name).toBe("Сезон");
  expect(entries[utf8].data.name).toBe("Сезон");
  const ids = [];
  directory.getFilesIds(ids, "", raw, 2, "priority");
  expect(ids).toEqual(["83"]);
  directory.setDirectory(entries[raw].link);
  expect(directory.getEntry("_f_83").name).toBe("one.mkv");
  expect(directory.getEntry("_f_84")).toBeNull();
  expect(directory.getDirectory()["_d_"].link).toBe("");
});

test("valid prefixes share one directory with legacy children, including percent and punctuation", () => {
  loadUI();
  const directory = new rDirectory();
  directory.addFile({ ...rowData(), name: "a %!()'/серия.mkv", pathKey: "a%20%25%21%28%29%27/%F1%E5%F0%E8%FF.mkv" }, 1);
  directory.addFile({ ...rowData(), name: "a %!()'/plain.mkv" }, 2);
  const entries = directory.getDirectory();
  expect(Object.keys(entries)).toEqual(["_d_a%20%25%21%28%29%27"]);
  expect(entries["_d_a%20%25%21%28%29%27"].data.name).toBe("a %!()'");
  const ids = [];
  directory.getFilesIds(ids, "", Object.keys(entries)[0], 0);
  expect(ids).toEqual(["1", "2"]);
});

test("legacy leaf labels and recovering directory labels update without changing selection", () => {
  loadUI();
  const directory = new rDirectory();
  const pathKey = "%D1%E5%E7%EE%ED/%CF%E0%EF%EA%E0/%F1%E5%F0%E8%FF.mkv";
  directory.addFile({ ...rowData(), name: "old/old/old.mkv", pathKey }, 83);
  directory.addFile({ ...rowData(), name: "Сезон/Папка/серия.mkv", pathKey }, 83);
  const outer = directory.getDirectory()["_d_%D1%E5%E7%EE%ED"];
  expect(outer.data.name).toBe("Сезон");
  directory.setDirectory(outer.link);
  const inner = directory.getDirectory()["_d_%D1%E5%E7%EE%ED/%CF%E0%EF%EA%E0"];
  expect(inner.data.name).toBe("Папка");
  directory.setDirectory(inner.link);
  expect(directory.getEntry("_f_83").name).toBe("серия.mkv");
  const ids = [];
  directory.getFilesIds(ids, directory.current, "_f_83", 0);
  expect(ids).toEqual(["83"]);
});

function rowData() {
  return { size: 100, done: 50, priority: "1", prioritize: 0 };
}
