import { readFileSync } from "fs";

// The status bar CPU graph keeps the last maxSeconds (three minutes) of
// samples: its x axis has no minimum, so whatever is kept is what is drawn.
// A sample is [time in seconds, percent].
window.$ = require("jquery");

function loadPlugin() {
  document.body.innerHTML = '<div id="meter-cpu-holder"></div>';
  window.getCSSRule = () => true;
  window.theWebUI = { settings: { "webui.reqtimeout": 30000 } };
  window.theRequestManager = { addRequest: () => 1, removeRequest: () => {} };
  window.RGBackground = function () {
    this.setGradient = () => this;
    this.getColor = () => "#000000";
  };
  // Drawing is not the subject; addData asks for a frame and that is all.
  window.requestAnimationFrame = () => 1;
  $.plot = () => ({});
  $.ajax = () => {};
  window.plugin = {
    loadMainCSS: () => {},
    markLoaded: () => {},
    addPaneToStatusbar: () => {},
    removePaneFromStatusbar: () => {},
  };

  const el = document.createElement("script");
  el.textContent = readFileSync("../plugins/cpuload/init.js", { encoding: "utf-8" });
  document.body.appendChild(el);
}

// The script declares a class, so it is loaded once; each test starts from
// an empty graph.
loadPlugin();

const START = Date.UTC(2026, 10, 1, 12, 0, 0);

beforeEach(() => {
  jest.useFakeTimers();
  jest.setSystemTime(START);
  plugin.graph.load.data = [];
});

afterEach(() => {
  jest.useRealTimers();
});

// One sample every `step` seconds for `duration` seconds, from START.
function sample(duration, step, percent = 5) {
  for (let t = 0; t <= duration; t += step) {
    jest.setSystemTime(START + t * 1000);
    plugin.graph.addData(percent);
  }
}

function times() {
  return plugin.graph.load.data.map(([time]) => time - START / 1000);
}

describe("the CPU load graph", () => {
  it("drops the samples older than three minutes", () => {
    sample(250, 50);
    // 0 and 50 are more than 180 seconds before 250.
    expect(times()).toEqual([100, 150, 200, 250]);
  });

  it("keeps every sample of the last three minutes", () => {
    sample(180, 3);
    expect(times().length).toBe(61);
    expect(times()[0]).toBe(0);
  });

  it("does not grow over a long session", () => {
    sample(3600, 3);
    expect(times().length).toBe(61);
    expect(times()[0]).toBe(3600 - 180);
  });

  it("still shows the latest sample", () => {
    sample(300, 3, 12);
    jest.setSystemTime(START + 303 * 1000);
    plugin.graph.addData(42);
    expect(plugin.graph.percent).toBe(42);
  });
});
