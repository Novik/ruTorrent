import { readFileSync } from "fs";

// Safari sends its own mouse events for a tap, so the plugin must add none of
// its own; the long press, which Safari has no gesture for, stays.
window.$ = require("jquery");
window.browser = { isSafari: true };

{
  const el = document.createElement("script");
  const code = readFileSync("../plugins/ipad/init.js", { encoding: "utf-8" });
  el.textContent = "(function () { var plugin = {}; " + code + "\nwindow.__ipad = plugin;\n})();";
  document.body.appendChild(el);
}

const plugin = window.__ipad;

const touchEvent = (target) => ({
  changedTouches: [{ target: target, screenX: 10, screenY: 10, clientX: 10, clientY: 10 }],
  preventDefault: () => {},
});

describe("the ipad plugin", () => {
  let target;
  const seen = [];

  beforeAll(() => {
    // One set of listeners for the file: re-registering them per test would
    // count the same event once per test that has run.
    for (const type of ["mousemove", "mousedown", "mouseup", "click", "dblclick", "contextmenu"])
      document.addEventListener(type, (e) => seen.push(e.type), true);
  });

  beforeEach(() => {
    jest.useFakeTimers();
    document.body.innerHTML = "<div id='row'>a row</div>";
    target = document.getElementById("row");
    seen.length = 0;
    plugin.rightClick = null;
    plugin.cancelMouseUp = false;
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
    document.body.innerHTML = "";
  });

  it("synthesizes nothing for a tap", () => {
    plugin.touchStart(touchEvent(target));
    plugin.touchEnd(touchEvent(target));
    jest.advanceTimersByTime(1000);

    expect(seen).toEqual([]);
  });

  it("still opens the context menu on a long press", () => {
    plugin.touchStart(touchEvent(target));
    jest.advanceTimersByTime(600);

    expect(seen).toEqual(["contextmenu"]);
  });

  it("drops the long press when the finger leaves first", () => {
    plugin.touchStart(touchEvent(target));
    jest.advanceTimersByTime(300);
    plugin.touchEnd(touchEvent(target));
    jest.advanceTimersByTime(600);

    expect(seen).toEqual([]);
  });

  it("leaves form controls to the browser", () => {
    document.body.innerHTML = "<input id='field'>";
    plugin.touchStart(touchEvent(document.getElementById("field")));
    jest.advanceTimersByTime(1000);

    expect(seen).toEqual([]);
    expect(plugin.rightClick).toBeNull();
  });
});
