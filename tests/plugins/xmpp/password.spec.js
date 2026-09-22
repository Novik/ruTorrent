import { readFileSync } from "fs";

window.$ = require("jquery");

// The XMPP settings page shows the account the notifier logs in with. The
// password field used to be filled from theWebUI.xmpp.JabberPasswd, which the
// server put into the javascript of every page load; it is now filled from
// nothing, and a password is posted back only when somebody types one.
const STORED = "correct-horse-battery-staple";

function loadPlugin() {
  document.body.innerHTML = "";
  window.$$ = (id) => document.getElementById(id);
  window.linked = () => {};
  window.theUILang = new Proxy(
    {},
    { get: (_t, prop) => (typeof prop === "string" ? prop : "") }
  );
  window.theWebUI = {
    addAndShowSettings: () => {},
    setSettings: () => {},
    request: () => {},
    xmpp: {},
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
  return plugin;
}

function openSettings(settings) {
  const plugin = loadPlugin();
  theWebUI.xmpp = settings;
  plugin.onLangLoaded.call(plugin);
  theWebUI.addAndShowSettings();
  return plugin;
}

// JabberPasswd_set is what the server sends now. The JabberPasswd alongside it
// is what it used to send, and is here so that the page is held to ignoring it
// rather than merely to not being offered it.
const SETTINGS = {
  JabberHost: "",
  JabberPort: 5222,
  JabberJID: "someone@jabber.example",
  JabberPasswd: STORED,
  JabberPasswd_set: 1,
  UseEncryption: 1,
  AdvancedSettings: 0,
  JabberFor: "someone-else@jabber.example",
  Message: "Torrent '{TORRENT}' has been downloaded.",
};

function sent() {
  const stub = new rTorrentStub();
  rTorrentStub.prototype.setxmpp.call(stub);
  return stub.content;
}

describe("xmpp password handling", () => {
  it("does not put a stored password into the field", () => {
    openSettings(SETTINGS);
    expect($$("jabberPasswd").value).toBe("");
    expect(document.body.innerHTML).not.toContain(STORED);
  });

  it("says a password is stored without showing it", () => {
    openSettings(SETTINGS);
    expect($$("jabberPasswd").placeholder).toBeTruthy();
  });

  it("shows no placeholder when no password is stored", () => {
    openSettings({ ...SETTINGS, JabberPasswd: "", JabberPasswd_set: 0 });
    expect($$("jabberPasswd").placeholder).toBe("");
  });

  it("leaves the password out of the request when nothing was typed", () => {
    openSettings(SETTINGS);
    const content = sent();
    expect(content).toContain("jabberJid=someone%40jabber.example");
    expect(content).not.toContain("jabberPasswd");
    expect(content).not.toContain(STORED);
  });

  it("sends the password once it has been typed", () => {
    const plugin = openSettings(SETTINGS);
    $("#jabberPasswd").val("a-new-one").trigger("input");
    expect(theWebUI.xmppWasChanged()).toBe(true);
    expect(sent()).toContain("jabberPasswd=a-new-one");
    expect(plugin.passwordWasTyped()).toBe(true);
  });

  it("sends an emptied password field, so a password can be cleared", () => {
    openSettings(SETTINGS);
    $("#jabberPasswd").val("x").trigger("input");
    $("#jabberPasswd").val("").trigger("input");
    expect(sent()).toContain("jabberPasswd=");
  });

  it("reports no change when the form was only looked at", () => {
    openSettings(SETTINGS);
    expect(theWebUI.xmppWasChanged()).toBe(false);
  });

  it("still notices a changed recipient", () => {
    openSettings(SETTINGS);
    $$("jabberFor").value = "somebody-else@jabber.example";
    expect(theWebUI.xmppWasChanged()).toBe(true);
  });

  it("forgets that a password was typed when the page is shown again", () => {
    const plugin = openSettings(SETTINGS);
    $("#jabberPasswd").val("a-new-one").trigger("input");
    expect(plugin.passwordWasTyped()).toBe(true);
    theWebUI.addAndShowSettings();
    expect(plugin.passwordWasTyped()).toBe(false);
    expect(sent()).not.toContain("jabberPasswd");
  });
});
