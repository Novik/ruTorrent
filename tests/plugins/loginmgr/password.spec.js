import { readFileSync } from "fs";

window.$ = require("jquery");

// The settings page shows one row per stored tracker account. The password
// field used to be filled from theWebUI.theAccounts[name].password, which the
// server put into the page javascript; it is now filled from nothing, and the
// password is posted back only when somebody types one.
const STORED = "correct-horse-battery-staple";

function loadPlugin() {
  document.body.innerHTML = "";
  window.theUILang = new Proxy(
    {},
    { get: (_t, prop) => (typeof prop === "string" ? prop : "") }
  );
  window.theWebUI = {
    addAndShowSettings: () => {},
    setSettings: () => {},
    request: () => {},
    theAccounts: {},
  };
  window.rTorrentStub = function () {};
  window.linked = () => {};
  const plugin = {
    name: "loginmgr",
    enabled: true,
    loadLang: () => {},
    canChangeOptions: () => true,
    attachPageToOptions: (element) => document.body.appendChild(element),
    removePageFromOptions: () => {},
  };
  window.__loginmgrPlugin = plugin;
  const code = readFileSync("../plugins/loginmgr/init.js", {
    encoding: "utf-8",
  });
  const scriptEl = document.createElement("script");
  scriptEl.textContent =
    "(function(){ var plugin = window.__loginmgrPlugin;\ntry {\n" +
    code +
    "\n} catch(e) { window.__pluginLoadError = e; }\n})();";
  document.body.appendChild(scriptEl);
  expect(window.__pluginLoadError).toBeUndefined();
  return plugin;
}

function openSettings(accounts) {
  const plugin = loadPlugin();
  theWebUI.theAccounts = accounts;
  plugin.onLangLoaded.call(plugin);
  theWebUI.addAndShowSettings();
  return plugin;
}

// password_set is what the server sends now. The password field alongside it
// is what it used to send, and is here so that the page is held to ignoring it
// rather than merely to not being offered it.
const ACCOUNT = {
  "KinozalTV": {
    login: "someuser",
    password: STORED,
    password_set: 1,
    enabled: 1,
    auto: 0,
  },
};

describe("loginmgr password handling", () => {
  it("does not put a stored password into the field", () => {
    openSettings(ACCOUNT);
    const field = $("#KinozalTV_lmpassword");
    expect(field.length).toBe(1);
    expect(field.val()).toBe("");
    expect(document.body.innerHTML).not.toContain(STORED);
  });

  it("says a password is stored without showing it", () => {
    openSettings(ACCOUNT);
    expect($("#KinozalTV_lmpassword").attr("placeholder")).toBeTruthy();
  });

  it("shows no placeholder for an account with no password stored", () => {
    openSettings({
      "KinozalTV": { login: "u", password_set: 0, enabled: 1, auto: 0 },
    });
    expect($("#KinozalTV_lmpassword").attr("placeholder")).toBe("");
  });

  it("leaves the password out of the request when nothing was typed", () => {
    openSettings(ACCOUNT);
    const stub = new rTorrentStub();
    rTorrentStub.prototype.setacc.call(stub);
    expect(stub.content).toContain("KinozalTV_login=someuser");
    expect(stub.content).not.toContain("KinozalTV_password");
  });

  it("sends the password once it has been typed", () => {
    const plugin = openSettings(ACCOUNT);
    $("#KinozalTV_lmpassword").val("a new one").trigger("input");
    expect(plugin.accWasChanged()).toBe(true);
    const stub = new rTorrentStub();
    rTorrentStub.prototype.setacc.call(stub);
    expect(stub.content).toContain("KinozalTV_password=a%20new%20one");
  });

  it("sends an emptied password field, so a password can be cleared", () => {
    openSettings(ACCOUNT);
    $("#KinozalTV_lmpassword").val("x").trigger("input");
    $("#KinozalTV_lmpassword").val("").trigger("input");
    const stub = new rTorrentStub();
    rTorrentStub.prototype.setacc.call(stub);
    expect(stub.content).toContain("KinozalTV_password=");
  });

  it("still notices a changed login", () => {
    const plugin = openSettings(ACCOUNT);
    expect(plugin.accWasChanged()).toBe(false);
    $("#KinozalTV_lmlogin").val("somebody-else");
    expect(plugin.accWasChanged()).toBe(true);
  });
});
