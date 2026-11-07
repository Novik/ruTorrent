import { readFileSync } from "fs";

window.$ = require("jquery");

// setxmpp() builds the request body by concatenating field values into an
// application/x-www-form-urlencoded string. rXmpp::set() in
// plugins/xmpp/xmpp.php reads it back by splitting on '&' and '='.
//
// tests/fixtures/xmpp-form-values.json holds one value per row with the form
// it has to travel as. This file asserts that setxmpp() writes that form;
// tests/plugins/xmpp/XmppFormEncodingTest.php asserts that set() reads the
// same form back as the value. Neither side can be changed alone.
const VALUES = JSON.parse(
  readFileSync("fixtures/xmpp-form-values.json", { encoding: "utf-8" })
);

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

const SETTINGS = {
  JabberHost: "",
  JabberPort: 5222,
  JabberJID: "someone@jabber.example",
  JabberPasswd_set: 1,
  UseEncryption: 1,
  AdvancedSettings: 0,
  JabberFor: "someone-else@jabber.example",
  Message: "Torrent '{TORRENT}' has been downloaded.",
};

function openSettings() {
  const plugin = loadPlugin();
  theWebUI.xmpp = SETTINGS;
  plugin.onLangLoaded.call(plugin);
  theWebUI.addAndShowSettings();
  return plugin;
}

function sent() {
  const stub = new rTorrentStub();
  rTorrentStub.prototype.setxmpp.call(stub);
  return stub.content;
}

// The names the body carries, in the order it carries them. A value that
// escaped its field shows up here as an extra name, or as a name gone missing.
function fieldNames(body) {
  return body.split("&").map((pair) => pair.split("=")[0]);
}

const ALWAYS = [
  "formEncoding",
  "advancedSettings",
  "useEncryption",
  "jabberHost",
  "jabberPort",
  "jabberJid",
  "jabberFor",
  "message",
];

describe("what a typed password travels as", () => {
  for (const row of VALUES) {
    it(`carries ${JSON.stringify(row.value)} -- ${row.why}`, () => {
      openSettings();
      $("#jabberPasswd").val(row.value).trigger("input");

      const body = sent();
      expect(fieldNames(body)).toEqual(ALWAYS.concat(["jabberPasswd"]));
      expect(body).toContain("jabberPasswd=" + row.encoded);
    });
  }
});

describe("what the rest of the form travels as", () => {
  for (const row of VALUES) {
    it(`carries ${JSON.stringify(row.value)} in the recipient and the message`, () => {
      openSettings();
      $$("jabberFor").value = row.value;
      $$("message").value = row.value;

      const body = sent();
      expect(fieldNames(body)).toEqual(ALWAYS);
      expect(body).toContain("jabberFor=" + row.encoded);
      expect(body).toContain("message=" + row.encoded);
    });
  }
});

describe("the body as a whole", () => {
  // rXmpp::set() cannot tell a value escaped here from one a settings
  // page before this wrote literally: both reach it as the same bytes.
  // This word is what tells it apart, and it unescapes nothing without
  // it.
  it("says which representation it is in", () => {
    openSettings();
    expect(sent().split("&")[0]).toBe("formEncoding=percent-v1");
  });

  // Somebody whose password happens to look escaped already: the five
  // characters go out escaped a second time, and come back once.
  it("escapes a value that already looks escaped", () => {
    openSettings();
    $("#jabberPasswd").val("p%26ssword").trigger("input");
    expect(sent()).toContain("jabberPasswd=p%2526ssword");
  });

  // The names on the wire are what plugins/xmpp/xmpp.php looks for, so the
  // encoding must not reach them.
  it("leaves the field names alone", () => {
    openSettings();
    expect(fieldNames(sent())).toEqual(ALWAYS);
  });

  it("still lets an emptied password field clear the stored password", () => {
    openSettings();
    $("#jabberPasswd").val("x").trigger("input");
    $("#jabberPasswd").val("").trigger("input");
    expect(fieldNames(sent())).toEqual(ALWAYS.concat(["jabberPasswd"]));
    expect(sent()).toContain("jabberPasswd=");
  });

  // The reading side unescapes with rawurldecode(), which is the inverse of
  // this one and leaves a plus alone. A form-urlencoded sender would write a
  // space as '+'; this one writes '%20' and emits no bare plus at all.
  it("never puts a bare plus in the body", () => {
    openSettings();
    $$("jabberFor").value = "a + b";
    $("#jabberPasswd").val("p+ssword").trigger("input");
    expect(sent()).not.toContain("+");
  });

  // The jid is split on '@' by the server after it is decoded, so the '@'
  // itself travels escaped and the parts survive whatever they contain.
  it("escapes the jid, separator and all", () => {
    openSettings();
    $$("jabberJid").value = "some&one@jabber.example";
    expect(sent()).toContain("jabberJid=some%26one%40jabber.example");
  });
});
