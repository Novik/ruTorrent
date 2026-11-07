import { readFileSync } from "fs";

// A title tooltip never opens on a touch screen, so the status bar panes that
// keep their numbers in one give nothing back when tapped. theStatusTip shows
// the title in a bubble instead. The panes rewrite their title while it is
// open, and the bar itself scrolls sideways, so a drag must not open one.
window.$ = require("jquery");

function loadWebUI() {
  window.theUILang = new Proxy({}, { get: (_t, prop) => prop });
  window.theFormatter = {};
  window.TYPE_STRING = "string";
  window.TYPE_NUMBER = "number";
  window.TYPE_PROGRESS = "progress";
  window.TYPE_PEERS = "peers";
  window.TYPE_SEEDS = "seeds";
  window.ALIGN_RIGHT = "right";
  window.CMENU_CHILD = "child";
  window.CMENU_SEP = "sep";
  window.dxSTable = function () {};
  window.rSpeedGraph = function () {};
  window.rSpeedGraph.prototype.addData = () => {};
  window.Timer = function () {};
  window.$type = (o) => (o == undefined ? false : typeof o);
  window.iv = (v) => parseInt(v, 10) || 0;

  let code = readFileSync("../js/webui.js", { encoding: "utf-8" });
  code = code.replace(/\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/, "");
  const el = document.createElement("script");
  el.textContent = code;
  document.body.appendChild(el);
}

loadWebUI();

const PANE_TOP = 500;

function statusBar() {
  document.body.innerHTML =
    '<div id="StatusBar">' +
    '  <div class="status-cell" id="st_up"><div class="stval">1 kB/s</div></div>' +
    '  <div class="status-cell" id="meter-disk-pane" title="328.0 GiB/386.4 GiB (58.3 GiB, 15% free)">' +
    '    <div id="meter-disk-text">85%</div>' +
    "  </div>" +
    '  <div class="status-cell" id="cpu-pane" title="load 0.42"></div>' +
    "</div>";
  // jsdom gives every element a zero rect; the bubble is placed from the
  // pane's top edge, so give the panes one.
  for (const pane of document.querySelectorAll(".status-cell")) {
    pane.getBoundingClientRect = () => ({ top: PANE_TOP, bottom: PANE_TOP + 25, left: 0, right: 100 });
  }
  theStatusTip.hide();
  theStatusTip.init();
}

function touch(type, target, x, y) {
  const event = new Event(type, { bubbles: true, cancelable: true });
  event.changedTouches = [{ clientX: x, clientY: y, target }];
  target.dispatchEvent(event);
}

function tap(target, x = 100, y = PANE_TOP + 10) {
  touch("touchstart", target, x, y);
  touch("touchend", target, x, y);
}

function bubble() {
  return document.querySelector(".status-tip");
}

beforeEach(statusBar);

describe("tapping a status bar pane", () => {
  it("shows the pane's title and leaves it open after the finger lifts", () => {
    tap(document.getElementById("meter-disk-text"));
    expect(bubble()).not.toBeNull();
    expect(bubble().textContent).toBe("328.0 GiB/386.4 GiB (58.3 GiB, 15% free)");
  });

  it("styles the bubble from a class, so a theme can restyle it", () => {
    tap(document.getElementById("meter-disk-pane"));
    expect(bubble().className).toBe("status-tip");
    expect(bubble().getAttribute("style")).not.toMatch(/background|color/);
  });

  it("places it above the pane rather than under the finger", () => {
    tap(document.getElementById("meter-disk-pane"));
    const bottom = parseInt(bubble().style.bottom, 10);
    expect(bottom).toBe(window.innerHeight - PANE_TOP + theStatusTip.GAP);
  });

  it("keeps it on screen when the pane is at the edge", () => {
    tap(document.getElementById("meter-disk-pane"), 0);
    expect(parseInt(bubble().style.left, 10)).toBeGreaterThanOrEqual(8);
  });

  it("says nothing for a pane that has no title", () => {
    tap(document.getElementById("st_up"));
    expect(bubble()).toBeNull();
  });

  it("says nothing for a pane whose title is empty", () => {
    // check_port and diskspace both set the attribute before they have
    // anything to put in it, so an empty title reaches this code.
    const pane = document.getElementById("cpu-pane");
    pane.setAttribute("title", "");
    tap(pane);
    expect(bubble()).toBeNull();
  });

  it("follows the title while the pane refreshes it", () => {
    const pane = document.getElementById("meter-disk-pane");
    tap(pane);
    pane.setAttribute("title", "320.0 GiB/386.4 GiB (66.4 GiB, 17% free)");
    return Promise.resolve().then(() => {
      expect(bubble().textContent).toBe("320.0 GiB/386.4 GiB (66.4 GiB, 17% free)");
    });
  });
});

describe("closing it", () => {
  it("closes on a second tap on the same pane", () => {
    const pane = document.getElementById("meter-disk-pane");
    tap(pane);
    tap(pane);
    expect(bubble()).toBeNull();
  });

  it("moves to another pane that has a title", () => {
    tap(document.getElementById("meter-disk-pane"));
    tap(document.getElementById("cpu-pane"));
    expect(document.querySelectorAll(".status-tip").length).toBe(1);
    expect(bubble().textContent).toBe("load 0.42");
  });

  it("closes on a tap outside the status bar", () => {
    tap(document.getElementById("meter-disk-pane"));
    tap(document.body, 10, 10);
    expect(bubble()).toBeNull();
  });

  it("closes when the page scrolls under it", () => {
    tap(document.getElementById("meter-disk-pane"));
    $(window).trigger("scroll");
    expect(bubble()).toBeNull();
  });

  it("closes when the status bar is scrolled sideways", () => {
    tap(document.getElementById("meter-disk-pane"));
    // not .trigger(): jQuery simulates bubbling, and a real scroll on an
    // inner element never reaches window.
    document.getElementById("StatusBar").dispatchEvent(new Event("scroll"));
    expect(bubble()).toBeNull();
  });

  it("closes on resize, which moves every pane", () => {
    tap(document.getElementById("meter-disk-pane"));
    $(window).trigger("resize");
    expect(bubble()).toBeNull();
  });

  it("does not write a closed pane's title into the next bubble", () => {
    const disk = document.getElementById("meter-disk-pane");
    const cpu = document.getElementById("cpu-pane");
    tap(disk);
    tap(disk);
    tap(cpu);
    disk.setAttribute("title", "disk refreshed while the cpu bubble is open");
    return Promise.resolve().then(() => {
      expect(bubble().textContent).toBe("load 0.42");
    });
  });

  it("stops watching the title once closed", () => {
    const pane = document.getElementById("meter-disk-pane");
    tap(pane);
    tap(pane);
    pane.setAttribute("title", "something else");
    return Promise.resolve().then(() => {
      expect(bubble()).toBeNull();
    });
  });
});

describe("a drag is not a tap", () => {
  it("opens nothing when the finger travels across the bar", () => {
    const pane = document.getElementById("meter-disk-pane");
    touch("touchstart", pane, 200, PANE_TOP + 10);
    touch("touchend", pane, 140, PANE_TOP + 10);
    expect(bubble()).toBeNull();
  });

  it("still opens for the small movement of a real finger", () => {
    const pane = document.getElementById("meter-disk-pane");
    touch("touchstart", pane, 200, PANE_TOP + 10);
    touch("touchend", pane, 204, PANE_TOP + 12);
    expect(bubble()).not.toBeNull();
  });

  it("ignores a touchend with no touchstart of its own", () => {
    // Near the origin on purpose: a missing start must be rejected outright,
    // not treated as {0, 0} and compared against the tolerance.
    touch("touchend", document.getElementById("meter-disk-pane"), 2, 2);
    expect(bubble()).toBeNull();
  });
});
