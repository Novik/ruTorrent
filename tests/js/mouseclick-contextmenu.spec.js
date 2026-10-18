import { readFileSync } from "fs";

window.$ = require("jquery");

for (const src of ["../lang/en.js", "../js/common.js"]) {
  const el = document.createElement("script");
  el.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(el);
}

// Whether the browser fires a contextmenu event is its own question: it is not
// answered by whether the device has a touchscreen, and the right-click menu is
// what hangs on getting it wrong.
describe("mouseclick() and the right-click menu", () => {
  let el;
  let seen;

  const arm = () => {
    seen = [];
    el = $("<div>").appendTo(document.body);
    el.mouseclick(function (e) {
      seen.push({ which: e.which, type: e.type });
    });
  };

  const rightClick = () =>
    el[0].dispatchEvent(new MouseEvent("contextmenu", { bubbles: true, cancelable: true }));

  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("binds the contextmenu handler on a mouse-only desktop", () => {
    $.support.touchable = false;
    $.support.touchPrimary = false;
    arm();
    rightClick();
    expect(seen).toEqual([{ which: 3, type: "contextmenu" }]);
  });

  it("binds it the same way on a touch device", () => {
    $.support.touchable = true;
    $.support.touchPrimary = true;
    arm();
    rightClick();
    expect(seen).toEqual([{ which: 3, type: "contextmenu" }]);
  });

  // The plugin that turns a long press into a right click dispatches exactly
  // this event, so the handler has to be there for it to reach.
  it("reports the synthesized right click as button 3", () => {
    $.support.touchable = true;
    $.support.touchPrimary = true;
    arm();
    const ev = document.createEvent("MouseEvent");
    ev.initMouseEvent("contextmenu", true, true, window, 1, 30, 15, 30, 15, false, false, false, false, 2, null);
    el[0].dispatchEvent(ev);
    expect(seen).toEqual([{ which: 3, type: "contextmenu" }]);
  });

  it("leaves a plain left click to the mousedown handler", () => {
    $.support.touchable = false;
    $.support.touchPrimary = false;
    arm();
    el[0].dispatchEvent(new MouseEvent("mousedown", { bubbles: true, cancelable: true, button: 0 }));
    expect(seen.map((s) => s.type)).toEqual(["mousedown"]);
    expect(seen[0].which).not.toBe(3);
  });
});
