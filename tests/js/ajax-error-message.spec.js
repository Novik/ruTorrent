import { readFileSync } from "fs";

window.$ = require("jquery");

const FAULT =
  '<?xml version="1.0" encoding="UTF-8"?>\n' +
  "<methodResponse><fault><value><struct>" +
  "<member><name>faultCode</name><value><i4>-501</i4></value></member>" +
  "<member><name>faultString</name><value><string>" +
  "The command 'execute.capture' was rejected by this server." +
  "</string></value></member>" +
  "</struct></value></fault></methodResponse>";

const OUTAGE = "Could not reach rTorrent over XMLRPC. Is rTorrent running?";

function loadRtorrent() {
  window.theWebUI = {
    settings: { "webui.needmessage": true },
    showFlags: 0xffff,
    systemInfo: { rTorrent: { apiVersion: 10, iVersion: 0x908, started: true } },
  };
  for (const src of [
    "../lang/en.js",
    "../js/common.js",
    "../js/content.js",
    "../js/rtorrent.js",
  ]) {
    const el = document.createElement("script");
    el.textContent = readFileSync(src, { encoding: "utf-8" });
    document.body.appendChild(el);
  }
  correctContent();
}

// Drives the real Ajax() fail path: only the transport is replaced, by a jQuery
// Deferred the test rejects with the jqXHR a failing request would carry.
function errorTextFor(status, responseText) {
  const deferred = $.Deferred();
  const realAjax = $.ajax;
  $.ajax = () => deferred.promise();
  let seen = null;
  try {
    Ajax(
      "?action=none",
      true,
      () => {},
      () => {},
      (s, text) => {
        seen = { status: s, text: text };
      },
      1000
    );
  } finally {
    $.ajax = realAjax;
  }
  deferred.reject(
    { status: status, responseText: responseText, getResponseHeader: () => null },
    "error",
    ""
  );
  return seen;
}

describe("what a failed request tells the caller", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    loadRtorrent();
  });

  it("hands over the faultString of a refusal, not the XMLRPC envelope", () => {
    const seen = errorTextFor(403, FAULT);
    expect(seen).not.toBeNull();
    expect(seen.text).toBe(
      "The command 'execute.capture' was rejected by this server."
    );
    expect(seen.text).not.toContain("methodResponse");
  });

  it("leaves a plain-text body alone, so an outage still reads as one", () => {
    const seen = errorTextFor(500, OUTAGE);
    expect(seen.text).toBe(OUTAGE);
  });

  it("leaves a body that is not a fault alone", () => {
    const seen = errorTextFor(502, "<html><body>Bad Gateway</body></html>");
    expect(seen.text).toBe("<html><body>Bad Gateway</body></html>");
  });
});

function loadWebUI() {
  window.theUILang = new Proxy({}, { get: (_target, prop) => prop });
  window.theFormatter = {};
  window.TYPE_STRING = "string";
  window.TYPE_NUMBER = "number";
  window.TYPE_PROGRESS = "progress";
  window.TYPE_PEERS = "peers";
  window.TYPE_SEEDS = "seeds";
  window.ALIGN_RIGHT = "right";
  window.dxSTable = function () {};
  window.rSpeedGraph = function () {};
  window.rSpeedGraph.prototype.addData = jest.fn();
  window.Timer = function () {};
  window.dStatus = { started: 1, paused: 2, checking: 4, hashing: 8, error: 16 };
  window.noty = jest.fn();

  let code = readFileSync("../js/webui.js", { encoding: "utf-8" });
  code = code.replace(
    /\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/,
    ""
  );
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

describe("what the message says when there is nothing to quote", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    loadWebUI();
    theWebUI.show = () => {};
  });

  it("says the server sent no message rather than trailing off", () => {
    theWebUI.error("0 [error,list]", "");
    expect(noty).toHaveBeenCalledWith(
      "Bad response from server: (0 [error,list]) the server sent no message",
      "error"
    );
  });

  it("still quotes a message when there is one", () => {
    theWebUI.error("500 [error,list]", OUTAGE);
    expect(noty).toHaveBeenCalledWith(
      "Bad response from server: (500 [error,list]) " + OUTAGE,
      "error"
    );
  });
});
