import { readFileSync } from "fs";

window.$ = require("jquery");

const OUTAGE = "Could not reach rTorrent over XMLRPC. Is rTorrent running?";

const FAULT =
  '<?xml version="1.0" encoding="UTF-8"?>\n' +
  "<methodResponse><fault><value><struct>" +
  "<member><name>faultCode</name><value><i4>-501</i4></value></member>" +
  "<member><name>faultString</name><value><string>" +
  "The command 'execute.capture' was rejected by this server." +
  "</string></value></member>" +
  "</struct></value></fault></methodResponse>";

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
}

// Drives the real Ajax() fail path: only the transport is replaced, by a jQuery
// Deferred the test rejects with the jqXHR a failing request would carry.
// Header lookup is case-insensitive, the way a real one is.
function answerFor(status, responseText, headers) {
  const deferred = $.Deferred();
  const realAjax = $.ajax;
  $.ajax = () => deferred.promise();
  let seen = null;
  try {
    Ajax(
      "?list=1",
      true,
      () => {},
      () => {},
      (...args) => {
        seen = args;
      },
      1000
    );
  } finally {
    $.ajax = realAjax;
  }
  const sent = headers || {};
  deferred.reject(
    {
      status: status,
      responseText: responseText,
      getResponseHeader: (name) => {
        const wanted = String(name).toLowerCase();
        for (const key of Object.keys(sent))
          if (key.toLowerCase() === wanted) return sent[key];
        return null;
      },
    },
    "error",
    ""
  );
  return seen;
}

describe("how a failed request names the daemon's state", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    loadRtorrent();
  });

  it("hands the caller the state the answer established", () => {
    const seen = answerFor(500, OUTAGE, { "X-rTorrent-State": "unreachable" });
    expect(seen).not.toBeNull();
    expect(seen[2]).toBe("unreachable");
  });

  it("reads the header whatever case it arrives in", () => {
    expect(answerFor(500, OUTAGE, { "x-rtorrent-state": "unreachable" })[2]).toBe(
      "unreachable"
    );
    expect(answerFor(500, OUTAGE, { "X-RTORRENT-STATE": "unreachable" })[2]).toBe(
      "unreachable"
    );
  });

  it("still hands over the sentence, with nothing added to it", () => {
    const seen = answerFor(500, OUTAGE, { "X-rTorrent-State": "unreachable" });
    // What an older client reads is the body, and the body is what it was:
    // the marker is out of band, so there is no token for it to display.
    expect(seen[1]).toBe(OUTAGE);
  });

  it("says nothing about the daemon when the answer did not establish it", () => {
    expect(answerFor(500, "Warning: the XMLRPC call failed.", {})[2]).toBeNull();
    expect(answerFor(503, "Service unavailable", {})[2]).toBeNull();
    expect(answerFor(403, "Forbidden", {})[2]).toBeNull();
  });

  it("says nothing on a refusal that came back from a daemon that answered", () => {
    const seen = answerFor(403, FAULT, {});
    expect(seen[2]).toBeNull();
    expect(seen[1]).toBe(
      "The command 'execute.capture' was rejected by this server."
    );
  });

  it("hands over whatever state it was told, so a later one needs no transport change", () => {
    expect(answerFor(500, "Something else entirely", {
      "X-rTorrent-State": "some-later-state",
    })[2]).toBe("some-later-state");
  });
});
