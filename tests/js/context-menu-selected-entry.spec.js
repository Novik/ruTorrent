import { readFileSync } from "fs";

window.$ = require("jquery");

// lang/en.js before common.js: common.js reads theUILang at load time.
// common.js before objects.js: the menu builder calls $type() on every entry.
for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

function labelsOf(menu) {
  return menu
    .children("li.menuitem")
    .map((ndx, li) => $(li).children("a").text())
    .get();
}

describe("context menu entries without a command", () => {
  beforeEach(() => {
    theContextMenu.init();
    theContextMenu.clear();
  });

  it("renders a selected entry that carries no command", () => {
    theContextMenu.add([CMENU_SEL, "Current label"]);

    expect(labelsOf(theContextMenu.obj)).toEqual(["Current label"]);
  });

  it("keeps every entry of a batch, whatever each one's command is", () => {
    theContextMenu.add(
      ["Plain with command", () => {}],
      ["Plain without command", null],
      [CMENU_SEL, "Selected with command", () => {}],
      [CMENU_SEL, "Selected without command"]
    );

    expect(labelsOf(theContextMenu.obj)).toEqual([
      "Plain with command",
      "Plain without command",
      "Selected with command",
      "Selected without command",
    ]);
  });

  it("renders a command-less selected entry inside a child menu", () => {
    // The labels submenu: the label already on the torrent is pushed as a
    // selected entry with nothing to run, the others as commands.
    theContextMenu.add([
      CMENU_CHILD,
      "Labels",
      [
        ["Other label", () => {}],
        [CMENU_SEL, "Label on this torrent"],
      ],
    ]);

    const submenu = theContextMenu.obj.find("ul.CMenu");
    expect(labelsOf(submenu)).toEqual(["Other label", "Label on this torrent"]);
  });

  it("marks a command-less selected entry the way a command-less plain entry is marked", () => {
    theContextMenu.add(
      ["Plain without command", null],
      [CMENU_SEL, "Selected without command"]
    );

    const [plain, selected] = theContextMenu.obj.children("li.menuitem").get();
    expect($(plain).children("a").hasClass("dis")).toBe(true);
    expect($(selected).children("a").hasClass("dis")).toBe(true);
    expect($(selected).children("a").hasClass("sel")).toBe(true);
  });
});
