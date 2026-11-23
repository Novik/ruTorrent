import { readFileSync } from "fs";

window.$ = require("jquery");

// OK on Settings saves every page, and the cookies page posts its list only
// when cookiesWasChanged() says the textarea differs from hostCookies.
// rCookies::set() skips blank lines, so a blank line is no change.

function open(cookies) {
  document.body.innerHTML = "";
  window.theUILang = new Proxy(
    {},
    { get: (_t, prop) => (typeof prop === "string" ? prop : "") }
  );
  window.hostCookies = cookies;
  window.requests = [];
  window.theWebUI = {
    addAndShowSettings: () => {},
    setSettings: () => {},
    request: (action) => window.requests.push(action),
  };
  window.rTorrentStub = function () {};
  const plugin = {
    name: "cookies",
    enabled: true,
    loadLang: () => {},
    loadMainCSS: () => {},
    canChangeOptions: () => true,
    attachPageToOptions: (element) => document.body.appendChild(element),
    removePageFromOptions: () => {},
  };
  window.__cookiesPlugin = plugin;
  const code = readFileSync("../plugins/cookies/init.js", { encoding: "utf-8" });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function(){ var plugin = window.__cookiesPlugin;\ntry {\n" +
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
  return window.requests.filter((a) => a === "?action=setcookies").length;
}

const TWO = [
  { host: "tracker.example", cookies: "uid=1;pass=abc;" },
  { host: "other.example", cookies: "sid=xyz;" },
];

describe("OK on Settings with the cookies page left alone", () => {
  it("does not post an empty list", () => {
    open([]);
    expect($("#hostcookies").val()).toBe("");
    expect(ok()).toBe(0);
  });

  it("does not post a stored list", () => {
    open(TWO);
    expect(ok()).toBe(0);
  });

  it("does not post for a blank line added at the end", () => {
    open(TWO);
    $("#hostcookies").val($("#hostcookies").val() + "\n");
    expect(ok()).toBe(0);
  });
});

describe("a change on the cookies page", () => {
  it("posts the first entry typed into an empty list", () => {
    open([]);
    $("#hostcookies").val("tracker.example|uid=1;");
    expect(ok()).toBe(1);
  });

  it("posts a changed cookie", () => {
    open(TWO);
    $("#hostcookies").val("tracker.example|uid=2;pass=abc;\nother.example|sid=xyz;");
    expect(ok()).toBe(1);
  });

  it("posts an emptied list", () => {
    open(TWO);
    $("#hostcookies").val("");
    expect(ok()).toBe(1);
  });
});
