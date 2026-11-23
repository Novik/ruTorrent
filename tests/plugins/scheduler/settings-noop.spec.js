import { readFileSync } from "fs";

window.$ = require("jquery");

// OK on Settings saves every page, and the scheduler page posts its table only
// when schedulerWasChanged() says it differs from theWebUI.scheduleTable. The
// table's cells keep their state in a "clr" attribute, read back as a string,
// while scheduler.php sends the stored week as numbers.

function week(fill) {
  return Array.from(Array(7).keys()).map((day) =>
    Array.from(Array(24).keys()).map((hour) => fill(day, hour))
  );
}

const STORED = {
  enabled: true,
  UL: [100, 200, 300],
  DL: [400, 500, 600],
  week: week((day, hour) => (day * 24 + hour) % 6),
};

function open(table = STORED) {
  document.body.innerHTML = "";
  window.$$ = (id) => document.getElementById(id);
  window.linked = () => {};
  window.theUILang = new Proxy(
    {},
    { get: (_t, prop) => (typeof prop === "string" ? prop : "") }
  );
  window.requests = [];
  window.theWebUI = {
    addAndShowSettings: () => {},
    setSettings: () => {},
    request: (action) => window.requests.push(action),
    scheduleTable: JSON.parse(JSON.stringify(table)),
  };
  window.rTorrentStub = function () {};
  const plugin = {
    name: "scheduler",
    enabled: true,
    loadLang: () => {},
    loadMainCSS: () => {},
    canChangeMenu: () => false,
    canChangeOptions: () => true,
    attachPageToOptions: (element) => document.body.appendChild(element),
    removePageFromOptions: () => {},
  };
  window.__schedulerPlugin = plugin;
  const code = readFileSync("../plugins/scheduler/init.js", { encoding: "utf-8" });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function(){ var plugin = window.__schedulerPlugin;\ntry {\n" +
    code +
    "\n} catch(e) { window.__pluginLoadError = e; }\n})();";
  document.body.appendChild(scriptEl);
  expect(window.__pluginLoadError).toBeUndefined();
  plugin.onLangLoaded.call(plugin);
  theWebUI.addAndShowSettings();
  return plugin;
}

function ok() {
  window.requests = [];
  theWebUI.setSettings();
  return window.requests.filter((a) => a === "?action=setschedule").length;
}

describe("OK on Settings with the scheduler page left alone", () => {
  it("does not post the schedule", () => {
    open();
    expect(ok()).toBe(0);
  });

  it("does not post an all-unlimited schedule either", () => {
    open({ ...STORED, enabled: false, week: week(() => 0) });
    expect(ok()).toBe(0);
  });
});

describe("a change on the scheduler page", () => {
  it("posts a cell that was clicked", () => {
    open();
    const cell = document.querySelector(".day-2.hour-5");
    theWebUI.schClick(cell, 2, 5);
    expect(ok()).toBe(1);
  });

  it("posts a changed limit", () => {
    open();
    $$("restrictedDL2").value = "501";
    expect(ok()).toBe(1);
  });

  it("posts the scheduler switched off", () => {
    open();
    $$("sch_enable").checked = false;
    expect(ok()).toBe(1);
  });
});
