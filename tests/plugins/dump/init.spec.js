import { readFileSync } from "fs";

window.$ = require("jquery");
// plugins/dump/lang/en.js ends by telling the loader it is done.
window.thePlugins = { get: () => ({ langLoaded() {} }) };
// Core lang/en.js is what creates this in a running instance; only the
// plugin's own file is loaded here, and it adds to what is already there.
window.theUILang = {};

for (const src of ["../js/common.js", "../plugins/dump/lang/en.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

const actionSource = readFileSync("../plugins/dump/action.php", { encoding: "utf-8" });
const pluginCode = readFileSync("../plugins/dump/init.js", { encoding: "utf-8" });

/**
 * canChangeMenu() answers false, so only the part under test is built: the
 * menu entry needs theWebUI and the context menu, and the renderer needs
 * neither.
 */
function loadPlugin() {
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function () { var plugin = { name: 'dump', enabled: true, loadLang: function () {}, " +
    "canChangeMenu: function () { return false; } }; " +
    pluginCode +
    "\nwindow.__plugin = plugin;\n})();";
  document.body.appendChild(scriptEl);
  return window.__plugin;
}

const plugin = loadPlugin();
const show = (line) => plugin.onTaskShowLog({}, line, "tskcmderrors", 0);

describe("the dump plugin's task console", () => {
  /**
   * The error arrives from the server as a key and the values to put in it,
   * so that what the user reads is in their language rather than the
   * server's. Rendering it is the only thing that turns one into the other.
   */
  it("renders a refusal from the language file, with the file and the user in it", () => {
    const html = show({
      reason: "dumpErrorUnreadable",
      file: "/home/u/.session/ABC.torrent",
      user: "www-data",
    });

    // Spelled out rather than compared to the plugin's own output: the
    // mapping of each value to its placeholder, the escaping and the line
    // break are each written here independently of how init.js does them.
    const expected = theUILang.dumpErrorUnreadable
      .replace("{file}", "/home/u/.session/ABC.torrent")
      .replace("{user}", "www-data");
    expect(html).toBe(escapeHTML(expected) + "<br>");
    expect(html).toContain("/home/u/.session/ABC.torrent");
    expect(html).toContain("www-data");
  });

  /** A path is bytes from the filesystem, and it is written into innerHTML. */
  it("escapes the values it substitutes", () => {
    const html = show({
      reason: "dumpErrorNoFile",
      file: "/tmp/<img src=x onerror=alert(1)>.torrent",
      user: "",
    });

    expect(html).not.toContain("<img");
    expect(html).toContain("&lt;img");
  });

  /** The dumper's own output still has to come through unchanged. */
  it("passes a plain log line through, escaped", () => {
    expect(plugin.onTaskShowLog({}, "name: <b>t</b>", "tskcmdlog", 0))
      .toBe("name: &lt;b&gt;t&lt;/b&gt;<br>");
  });

  /**
   * theUILang[key] for a key no language file defines is undefined, and
   * jQuery renders that as nothing at all -- an empty error pane says less
   * than the message it replaced. Show the key instead.
   */
  it("shows the key rather than nothing when no string is defined for it", () => {
    const html = show({ reason: "dumpErrorThatDoesNotExist", file: "", user: "" });

    expect(html).toContain("dumpErrorThatDoesNotExist");
    expect(html).not.toContain("undefined");
  });

  /**
   * The two sides are only connected by the name. A reason the server can
   * send that no string is defined for reaches the user as the name itself,
   * which is the check above -- this is so that never ships.
   */
  it("defines a string for every reason action.php can send", () => {
    const sent = [...actionSource.matchAll(/"(dumpError[A-Za-z]+)"/g)].map((m) => m[1]);

    expect(sent.length).toBeGreaterThan(0);
    expect([...new Set(sent)].filter((reason) => typeof theUILang[reason] !== "string"))
      .toEqual([]);
  });

  /**
   * And the other way: a string carrying a placeholder the server never
   * fills reaches the user with the braces still in it.
   */
  it("fills every placeholder its strings ask for", () => {
    const reasons = Object.keys(theUILang).filter((k) => k.startsWith("dumpError"));
    const leftover = reasons
      .map((reason) => [reason, show({ reason, file: "F", user: "U" })])
      .filter(([, html]) => html.includes("{"))
      .map(([reason, html]) => reason + " -> " + html);

    expect(reasons.length).toBeGreaterThan(0);
    expect(leftover).toEqual([]);
  });
});
