import { readFileSync } from "fs";

window.$ = require("jquery");

// setautotools() builds the request body by concatenating field values into
// an application/x-www-form-urlencoded string. rAutoTools::set() in
// plugins/autotools/autotools.php reads it back by splitting on '&' and '='.
//
// tests/fixtures/autotools-form-values.json holds one value per row with the
// form it has to travel as. This file asserts that setautotools() writes that
// form; tests/plugins/autotools/AutoToolsFormEncodingTest.php asserts that
// set() reads the same form back as the value. Neither side can be changed
// alone.
const VALUES = JSON.parse(
  readFileSync("fixtures/autotools-form-values.json", { encoding: "utf-8" })
);

const TEXT_FIELDS = [
  "label_template",
  "automove_filter",
  "skip_move_for_files",
  "path_to_finished",
  "path_to_watch",
];

const NAMES = [
  "formEncoding",
  "enable_label",
  "label_template",
  "enable_move",
  "path_to_finished",
  "skip_move_for_files",
  "fileop_type",
  "enable_watch",
  "add_label",
  "add_name",
  "path_to_watch",
  "automove_filter",
  "watch_start",
];

function openSettings() {
  document.body.innerHTML = "";
  window.$$ = (id) => document.getElementById(id);
  window.linked = () => {};
  window.theUILang = new Proxy(
    {},
    { get: (_t, prop) => (typeof prop === "string" ? prop : "") }
  );
  window.thePlugins = { isInstalled: () => false };
  window.theWebUI = {
    addAndShowSettings: () => {},
    setSettings: () => {},
    request: () => {},
    autotools: {
      EnableLabel: 1,
      LabelTemplate: "{DIR}",
      EnableMove: 1,
      FileOpType: "Move",
      PathToFinished: "/done/",
      SkipMoveForFiles: "",
      EnableWatch: 1,
      PathToWatch: "/watch/",
      MoveFilter: "/.*/",
      WatchStart: 0,
      AddLabel: 0,
      AddName: 0,
    },
  };
  window.rTorrentStub = function () {};
  const plugin = {
    name: "autotools",
    enabled: true,
    loadLang: () => {},
    canChangeOptions: () => true,
    attachPageToOptions: (element) => document.body.appendChild(element),
    removePageFromOptions: () => {},
  };
  window.__autotoolsPlugin = plugin;
  const code = readFileSync("../plugins/autotools/init.js", { encoding: "utf-8" });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function(){ var plugin = window.__autotoolsPlugin;\ntry {\n" +
    code +
    "\n} catch(e) { window.__pluginLoadError = e; }\n})();";
  document.body.appendChild(scriptEl);
  expect(window.__pluginLoadError).toBeUndefined();
  plugin.onLangLoaded.call(plugin);
  theWebUI.addAndShowSettings();
  return plugin;
}

function sent() {
  const stub = new rTorrentStub();
  rTorrentStub.prototype.setautotools.call(stub);
  return stub.content;
}

// [name, value] in the order the body carries them. A value that escaped its
// field shows up here as an extra name, or as a name gone missing.
function fields(body) {
  return body.split("&").map((pair) => {
    const at = pair.indexOf("=");
    return [pair.slice(0, at), pair.slice(at + 1)];
  });
}

describe("what a typed value travels as", () => {
  for (const row of VALUES) {
    for (const id of TEXT_FIELDS) {
      it(`${id} carries ${JSON.stringify(row.value)} -- ${row.why}`, () => {
        openSettings();
        $$(id).value = row.value;

        const body = fields(sent());
        expect(body.map(([name]) => name)).toEqual(NAMES);
        expect(body.find(([name]) => name === id)[1]).toBe(row.encoded);
      });
    }
  }
});

describe("the body as a whole", () => {
  // rAutoTools::set() cannot tell a value escaped here from one a settings
  // page before this wrote literally: both reach it as the same bytes. This
  // word is what tells it apart, and it unescapes nothing without it.
  it("says which representation it is in", () => {
    openSettings();
    expect(sent().split("&")[0]).toBe("formEncoding=percent-v1");
  });

  // The reading side unescapes with rawurldecode(), which is the inverse of
  // this one and leaves a plus alone, so no bare plus may go out.
  it("never puts a bare plus in the body", () => {
    openSettings();
    $$("label_template").value = "a + b";
    $$("automove_filter").value = "/x+/";
    expect(sent()).not.toContain("+");
  });

  it("still sends the checkboxes and the file operation", () => {
    openSettings();
    $$("enable_label").checked = true;
    $$("auto_add_name").checked = true;
    $$("auto_add_label").checked = false;
    $$("fileop_type").value = "Copy";
    const body = Object.fromEntries(fields(sent()));
    expect(body.enable_label).toBe("1");
    expect(body.add_name).toBe("1");
    expect(body.add_label).toBe("0");
    expect(body.fileop_type).toBe("Copy");
  });
});
