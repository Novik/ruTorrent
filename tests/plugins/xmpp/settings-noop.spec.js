import { readFileSync } from "fs";

window.$ = require("jquery");

// OK on Settings saves every page, and the XMPP page posts its settings only
// when xmppWasChanged() says the form differs from theWebUI.xmpp. That has to
// hold for every stored value, the advanced settings switch included.

function open(settings) {
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
    xmpp: settings,
  };
  window.rTorrentStub = function () {};
  const plugin = {
    name: "xmpp",
    enabled: true,
    loadLang: () => {},
    canChangeOptions: () => true,
    attachPageToOptions: (element) => document.body.appendChild(element),
    removePageFromOptions: () => {},
  };
  window.__xmppPlugin = plugin;
  const code = readFileSync("../plugins/xmpp/init.js", { encoding: "utf-8" });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function(){ var plugin = window.__xmppPlugin;\ntry {\n" +
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
  return window.requests.filter((a) => a === "?action=setxmpp").length;
}

const SETTINGS = {
  JabberHost: "jabber.example",
  JabberPort: 5223,
  JabberJID: "someone@jabber.example",
  JabberPasswd_set: 1,
  UseEncryption: 1,
  AdvancedSettings: 1,
  JabberFor: "someone-else@jabber.example",
  Message: "Torrent '{TORRENT}' has been downloaded.",
};

describe("OK on Settings with the XMPP page left alone", () => {
  it.each([
    ["on", 1],
    ["off", 0],
  ])("does not post with advanced settings %s", (_name, advanced) => {
    open({ ...SETTINGS, AdvancedSettings: advanced });
    expect(ok()).toBe(0);
  });
});

describe("a change on the XMPP page", () => {
  it("posts advanced settings switched off", () => {
    open(SETTINGS);
    $$("advancedSettings").checked = false;
    expect(ok()).toBe(1);
  });

  it("posts advanced settings switched on", () => {
    open({ ...SETTINGS, AdvancedSettings: 0 });
    $$("advancedSettings").checked = true;
    expect(ok()).toBe(1);
  });

  it("posts a changed message", () => {
    open(SETTINGS);
    $$("message").value = "done: {TORRENT}";
    expect(ok()).toBe(1);
  });

  it("posts a typed password, and no password when none was typed", () => {
    open(SETTINGS);
    $$("advancedSettings").checked = false;
    expect(ok()).toBe(1);
    const stub = new rTorrentStub();
    rTorrentStub.prototype.setxmpp.call(stub);
    expect(stub.content).not.toContain("jabberPasswd");
    open(SETTINGS);
    $("#jabberPasswd").val("a-new-one").trigger("input");
    expect(ok()).toBe(1);
  });
});
