import { readFileSync } from "fs";

window.$ = require("jquery");

function run(code) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

function load(src) {
  run(readFileSync(src, { encoding: "utf-8" }));
}

for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js", "../js/stable.js"]) {
  load(src);
}

// What js/webui.js expects to find already defined, reduced to what the
// plugins menu reaches.
Object.assign(window, {
  theFormatter: {},
  TYPE_STRING: "string",
  TYPE_NUMBER: "number",
  TYPE_PROGRESS: "progress",
  TYPE_PEERS: "peers",
  TYPE_SEEDS: "seeds",
  ALIGN_RIGHT: "right",
  rSpeedGraph: function () {},
  Timer: function () {},
  dStatus: { started: 1, paused: 2, checking: 4, hashing: 8, error: 16 },
});
window.rSpeedGraph.prototype.addData = () => {};
run(
  readFileSync("../js/webui.js", { encoding: "utf-8" }).replace(
    /\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/,
    ""
  )
);

function entryNamed(label) {
  return theContextMenu.obj
    .find("a.menu-cmd")
    .filter((ndx, a) => $(a).text() === label);
}

// The plugin the menu is built for. plugin.help is read out of the plugin's
// plugin.info file and reaches the browser through php/getplugins.php, so it
// is an address the panel did not write and has not checked.
function selectPluginWithHelp(help) {
  window.thePlugins = {
    get: () => ({
      enabled: true,
      launched: false,
      canShutdown: () => false,
      canBeLaunched: () => false,
      help: help,
    }),
  };
  theWebUI.getTable = () => ({ selCount: 1 });
  theContextMenu.init();
  theWebUI.plgSelect({ which: 3, clientX: 0, clientY: 0 }, "plgid_someplugin");
}

describe("the help entry of the plugins menu", () => {
  let opened;
  let realOpen;

  beforeEach(() => {
    opened = [];
    realOpen = window.open;
    window.open = (...args) => {
      opened.push(args);
      return null;
    };
  });

  afterEach(() => {
    window.open = realOpen;
    theContextMenu.clear();
  });

  // #3310 severed window.opener on every other remote destination the panel
  // opens. This one was left opening with a handle back to the tab.
  it("opens the help address with no handle back to this tab", () => {
    selectPluginWithHelp("https://example.com/PluginHelp");

    entryNamed(theUILang.Help).trigger("click");

    expect(opened.length).toBe(1);
    expect(opened[0][0]).toBe("https://example.com/PluginHelp");
    expect(String(opened[0][2])).toContain("noopener");
  });

  // A plugin.info line is a string the panel hands to window.open() as given.
  // openExternalURL() is what decides whether an address may be opened at all,
  // and a scheme that runs code in the new window is not one of them.
  it("refuses a help address whose scheme is not one that may be opened", () => {
    selectPluginWithHelp("javascript:window.__pluginHelpRan = 1");

    entryNamed(theUILang.Help).trigger("click");

    expect(opened).toEqual([]);
  });

  it("leaves a plugin without a help address without the entry", () => {
    selectPluginWithHelp("");

    expect(entryNamed(theUILang.Help).length).toBe(0);
  });
});
