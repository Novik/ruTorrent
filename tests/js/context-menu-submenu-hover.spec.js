import { readFileSync } from "fs";

// Walking the pointer from a submenu's label into the submenu crosses element
// boundaries that all lie inside the same menu item: the label, the submenu's
// own <ul>, and each entry in it. mouseout bubbles, so a close handler bound to
// it fires on every one of those crossings and takes the submenu away from
// under the pointer; the next mouseover puts it back, and the item ends up
// flickering, or hidden for good when a crossing is the last event of the
// gesture. The submenu must stay open for every crossing inside the item and
// close only once the pointer has left it.
//
// jsdom lays nothing out, so no pointer path can be driven here and no
// visibility read off geometry. What this pins is which crossings reach the
// close handler and which reach the open one: each crossing is dispatched as
// the pair of events a browser sends for it, with the relatedTarget it carries,
// and the submenu's own display is read back. The geometry -- that the submenu
// abuts its item with no gap, so there is no dead zone between them -- and the
// real pointer paths are covered by driving chromium, firefox and webkit.

window.$ = require("jquery");

function load(src) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

// lang/en.js before common.js: common.js reads theUILang at load time.
// common.js before objects.js: the menu builder calls $type() on every entry.
for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js"]) load(src);

const LABEL = "Remove And...";

let opened;

function item() {
  return theContextMenu.obj
    .children("li")
    .filter((ndx, li) => $("a.exp", li).text() === LABEL);
}
const label = () => item().children("a.exp")[0];
const submenu = () => item().children("ul")[0];
const entry = (ndx) => $("a.menu-cmd", submenu()).eq(ndx)[0];
const sibling = () =>
  theContextMenu.obj.children("li").not(item()).find("a.menu-cmd")[0];
const hidden = () => submenu().style.display === "none";

// A browser sends two events for one crossing: mouseout on what the pointer
// left, naming what it entered, then mouseover on what it entered, naming what
// it left. Either side is null at the window edge.
//
// The halves are separate here because the submenu has to be open throughout,
// not merely open again once the pair has finished. A submenu hidden by the out
// and restored by the over is the flicker, and when the out is the last event
// the gesture produces, it is a submenu that stays gone.
function out(from, to) {
  from.dispatchEvent(new MouseEvent("mouseout", { bubbles: true, relatedTarget: to }));
}
function over(to, from) {
  to.dispatchEvent(new MouseEvent("mouseover", { bubbles: true, relatedTarget: from }));
}
function move(from, to) {
  if (from) out(from, to);
  if (to) over(to, from);
}

beforeEach(() => {
  theContextMenu.init();
  theContextMenu.clear();
  theContextMenu.add(["Start", () => {}]);
  theContextMenu.add([
    CMENU_CHILD,
    LABEL,
    [["Delete Data", () => {}], ["Delete Data And Path", () => {}]],
  ]);
  opened = 0;
  const open = theContextMenu.openSubmenu;
  theContextMenu.openSubmenu = function () {
    opened++;
    return open.apply(this, arguments);
  };
  theContextMenu.show(10, 10);
  theContextMenu.openSubmenu = open;
});

describe("a context menu submenu under the pointer", () => {
  it("opens when the pointer enters its item", () => {
    expect(hidden()).toBe(true);
    move(document.body, label());
    expect(hidden()).toBe(false);
  });

  it("stays open as the pointer crosses from the label into it", () => {
    move(document.body, label());
    out(label(), submenu());
    expect(hidden()).toBe(false);
    over(submenu(), label());
    expect(hidden()).toBe(false);
  });

  it("stays open as the pointer crosses into one of its entries", () => {
    move(document.body, label());
    move(label(), submenu());
    out(submenu(), entry(0));
    expect(hidden()).toBe(false);
    over(entry(0), submenu());
    expect(hidden()).toBe(false);
  });

  it("stays open as the pointer moves between its entries", () => {
    move(document.body, label());
    move(label(), entry(0));
    out(entry(0), entry(1));
    expect(hidden()).toBe(false);
    over(entry(1), entry(0));
    expect(hidden()).toBe(false);
  });

  it("is positioned once for the whole gesture, not once per crossing", () => {
    move(document.body, label());
    move(label(), submenu());
    move(submenu(), entry(0));
    move(entry(0), entry(1));
    expect(opened).toBe(1);
  });

  it("closes as the pointer moves to another entry of the menu", () => {
    move(document.body, label());
    move(label(), entry(0));
    out(entry(0), sibling());
    expect(hidden()).toBe(true);
  });

  it("closes as the pointer leaves the window", () => {
    move(document.body, label());
    move(label(), entry(0));
    out(entry(0), null);
    expect(hidden()).toBe(true);
  });

  // openSubmenu clamps a submenu that does not fit below its item and makes it
  // scroll; closeSubmenu is the only thing that takes that back off, so a close
  // that no longer runs would leave the next opening clamped to a height it
  // does not need.
  it("gives back the height the fitting logic clamped it to when it closes", () => {
    move(document.body, label());
    $(submenu()).css({ "max-height": "40px", overflow: "visible scroll" });
    out(label(), sibling());
    expect(hidden()).toBe(true);
    expect(submenu().style.maxHeight).toBe("");
    expect(submenu().style.overflow).toBe("visible");
  });

  it("keeps that height while the pointer is still inside it", () => {
    move(document.body, label());
    $(submenu()).css({ "max-height": "40px", overflow: "visible scroll" });
    move(label(), entry(0));
    expect(hidden()).toBe(false);
    expect(submenu().style.maxHeight).toBe("40px");
  });
});
