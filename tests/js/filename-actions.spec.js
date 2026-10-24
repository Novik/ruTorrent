import { readFileSync } from "fs";

window.$ = require("jquery");

const hash = "A".repeat(40);

function loadScript(path, plugin = false) {
  const script = document.createElement("script");
  const source = readFileSync(`../${path}`, "utf8");
  script.textContent = plugin ? `(function(plugin) {\n${source}\n})(actionPlugin);` : source;
  document.body.appendChild(script);
}

function fileMenu(pluginName, file, flat, directory = false, index = 0) {
  document.body.innerHTML = '<input id="edit_unpack" value="/output"><input id="scimgfile"><input id="soximgfile">';
  loadScript("lang/en.js");
  loadScript("js/common.js");
  const fid = directory ? "_d_directory.mkv" : flat ? `${hash}_f_${index}` : `_f_${index}`;
  const table = {
    selCount: 1,
    getFirstSelected: () => fid,
    getRawValue: () => file ? (flat ? file.name : file.name.split('/').pop())
      : pluginName === "spectrogram" ? "missing.flac" : "missing.mkv",
  };
  window.theWebUI = {
    dID: hash,
    settings: { "webui.fls.view": flat },
    createFileMenu: () => true,
    createMenu() {},
    getTable: () => table,
    files: { [hash]: file ? { [index]: file } : {} },
    torrents: { [hash]: { done: 1000 } },
    dirs: { [hash]: new rDirectory() },
    startConsoleTask: jest.fn(),
  };
  if (directory)
    theWebUI.dirs[hash].addFile({ name: "directory.mkv/child", size: 1, done: 1, priority: "1" }, 0);
  window.thePlugins = { isInstalled: () => false };
  window.CMENU_SEP = "-";
  window.theUILang = { unpack: "Unpack", exFFMPEG: "Screenshots", exsox: "Spectrogram" };
  window.theContextMenu = { add: jest.fn(), get: () => false };
  window.theDialogManager = { hide: jest.fn(), show: jest.fn() };
  window.actionPlugin = {
    name: pluginName,
    enabled: true,
    allStuffLoaded: true,
    useUnrar: true,
    useUnzip: true,
    extensions: [pluginName === "screenshots" ? "mkv" : "flac"],
    loadLang() {},
    loadMainCSS() {},
    canChangeMenu: () => true,
    canChangeOptions: () => false,
  };
  loadScript(`plugins/${pluginName}/init.js`, true);
  expect(theWebUI.createFileMenu({}, fid)).toBe(true);
  return theContextMenu.add.mock.calls.at(-1)[0][1];
}

describe.each([false, true])("filename actions in flat view=%s", flat => {
  test.each([
    [{ name: "archive.rar", pathKey: "raw%FF.zip" }, "zip"],
    [{ name: "archive.zip", pathKey: "raw%FF.R01" }, "rar"],
    [{ name: "archive.zip", pathKey: "raw%FF.001" }, "rar"],
    [{ name: "archive.txt", pathKey: "raw%FF.RAR" }, "rar"],
    [{ name: "archive.rar", pathKey: "raw%FF.txt" }, null],
    [{ name: "Архив.ZIP" }, "zip"],
    [{ name: ".zip" }, "zip"],
    [{ name: ".RAR" }, "rar"],
    [{ name: ".r00" }, "rar"],
    [{ name: ".001" }, "rar"],
    [{ name: "Архив.txt", pathKey: "%FF/.zip" }, "zip"],
    [{ name: ".zip", pathKey: "raw%FF.txt" }, null],
    [{ name: "literal%2Ezip" }, null],
    [{ name: "literal.%72ar" }, null],
    [{ name: "archive.rar", pathKey: "raw%FF.%2572ar" }, null],
  ])("unpack derives its task mode from the raw filename %j", (file, mode) => {
    const action = fileMenu("unpack", { ...file, percent: 100 }, flat);
    if (mode === null) {
      expect(action).toBeNull();
      expect(theWebUI.startConsoleTask).not.toHaveBeenCalled();
    } else {
      expect(typeof action).toBe("function");
      action();
      expect(theDialogManager.show).toHaveBeenCalledWith("dlg_unpack");
      theWebUI.unpack();
      expect(theWebUI.startConsoleTask).toHaveBeenCalledWith("unpack", "unpack",
        { hash, no: "0", mode, dir: "/output" }, { noclose: true });
    }
  });

  test("unpack still refuses an incomplete archive", () => {
    expect(fileMenu("unpack", { name: "Архив.rar", pathKey: "raw%FF.zip", percent: 99 }, flat)).toBeNull();
  });

  test("unpack preserves a nonzero file index", () => {
    fileMenu("unpack", { name: "Архив.txt", pathKey: "raw%FF.zip", percent: 100 }, flat, false, 83)();
    theWebUI.unpack();
    expect(theWebUI.startConsoleTask).toHaveBeenCalledWith("unpack", "unpack",
      { hash, no: "83", mode: "zip", dir: "/output" }, { noclose: true });
  });

  describe.each([
    ["screenshots", "mkv", "ffmpeg", "scimgfile"],
    ["spectrogram", "flac", "sox", "soximgfile"],
  ])("%s", (pluginName, extension, task, labelId) => {
    test("raw extension enables the indexed task and keeps its readable output label", () => {
      const action = fileMenu(pluginName, { name: "Читаемое имя.txt", pathKey: `raw%FF.${extension.toUpperCase()}`, percent: 40 }, flat);
      expect(typeof action).toBe("function");
      expect($(`#${labelId}`).val()).toBe("Читаемое имя");
      action();
      expect(theWebUI.startConsoleTask).toHaveBeenCalledWith(task, pluginName,
        { hash, no: "0" }, { noclose: true });
    });

    test("a display extension cannot enable a task for another raw type", () => {
      expect(fileMenu(pluginName, { name: `Файл.${extension}`, pathKey: "raw%FF.txt" }, flat)).toBeNull();
      expect(theWebUI.startConsoleTask).not.toHaveBeenCalled();
    });

    test("a leading dot remains a filename rather than a media extension", () => {
      expect(fileMenu(pluginName, { name: `.${extension}` }, flat)).toBeNull();
      expect(fileMenu(pluginName, { name: `Файл.${extension}`, pathKey: `.${extension}` }, flat)).toBeNull();
      expect(theWebUI.startConsoleTask).not.toHaveBeenCalled();
    });

    test("a nested raw dotfile retains the flat versus tree menu behavior", () => {
      const action = fileMenu(pluginName,
        { name: "Читаемая/Серия.txt", pathKey: `raw%FF/.${extension}` }, flat);
      expect(typeof action === "function").toBe(flat);
      if (flat) {
        expect($(`#${labelId}`).val()).toBe("Читаемая/Серия");
        action();
        expect(theWebUI.startConsoleTask).toHaveBeenCalledWith(task, pluginName,
          { hash, no: "0" }, { noclose: true });
      }
    });

    test("a display dotfile cannot hide a normal raw media extension", () => {
      const action = fileMenu(pluginName, { name: `.${extension}`, pathKey: `raw%FF.${extension}` }, flat);
      action();
      expect(theWebUI.startConsoleTask).toHaveBeenCalledWith(task, pluginName,
        { hash, no: "0" }, { noclose: true });
    });

    test("normal Unicode filenames without a path key keep working", () => {
      const action = fileMenu(pluginName, { name: `Файл.${extension.toUpperCase()}` }, flat);
      action();
      expect(theWebUI.startConsoleTask).toHaveBeenCalledWith(task, pluginName,
        { hash, no: "0" }, { noclose: true });
    });

    test("the task keeps a nonzero index and a literal percent sequence in the basename", () => {
      const action = fileMenu(pluginName, { name: `Файл%2Eимя.${extension}` }, flat, false, 83);
      action();
      expect(theWebUI.startConsoleTask).toHaveBeenCalledWith(task, pluginName,
        { hash, no: "83" }, { noclose: true });
    });

    test("literal percent-encoded-looking text does not become an extension", () => {
      expect(fileMenu(pluginName, { name: `literal%2E${extension}` }, flat)).toBeNull();
      expect(fileMenu(pluginName, { name: `Файл.${extension}`, pathKey: `raw%FF.%25${extension}` }, flat)).toBeNull();
    });

    test("a stale selected row cannot start a file task", () => {
      expect(fileMenu(pluginName, null, flat)).toBeNull();
      expect(theWebUI.startConsoleTask).not.toHaveBeenCalled();
    });

    test("a missing torrent file list disables the action", () => {
      fileMenu(pluginName, { name: `Файл.${extension}` }, flat);
      delete theWebUI.files[hash];
      const fid = flat ? `${hash}_f_0` : "_f_0";
      expect(theWebUI.createFileMenu({}, fid)).toBe(true);
      expect(theContextMenu.add.mock.calls.at(-1)[0][1]).toBeNull();
    });
  });
});

test.each(["screenshots", "spectrogram"])("%s refuses a selected directory", pluginName => {
  const name = pluginName === "screenshots" ? "directory.mkv" : "directory.flac";
  expect(fileMenu(pluginName, { name }, false, true)).toBeNull();
  expect(theWebUI.startConsoleTask).not.toHaveBeenCalled();
});

test.each([
  [{ name: "archive.txt", pathKey: "raw%FF.zip" }, true],
  [{ name: "archive.zip", pathKey: "raw%FF.txt" }, false],
  [{ name: "Архив.RAR" }, true],
  [{ name: ".ZIP" }, true],
  [{ name: ".rar" }, true],
  [{ name: "Архив.txt", pathKey: "%FF/.rar" }, true],
  [{ name: "literal%2Ezip" }, false],
])("torrent unpack availability uses raw archive types %j", (file, enabled) => {
  fileMenu("unpack", { ...file, percent: 100 }, false);
  theContextMenu.add.mockClear();
  theWebUI.createMenu({}, hash);
  const action = theContextMenu.add.mock.calls.at(-1)[0][1];
  expect(typeof action === "function").toBe(enabled);
  if (enabled) {
    action();
    theWebUI.unpack();
    expect(theWebUI.startConsoleTask).toHaveBeenCalledWith("unpack", "unpack",
      { hash, no: "", mode: "", dir: "/output" }, { noclose: true });
  }
});
