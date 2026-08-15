import { readFileSync } from "fs";

window.$ = require("jquery");
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
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// A response handing out exactly the headers a test asks for.
function response(headers) {
  return {
    getResponseHeader: (name) => (name in headers ? headers[name] : null),
  };
}

// Real clocks, so no global Date mocking: assertions allow the second or so
// that the Date header's whole-second resolution and the test itself cost.
const SLACK = 2000;

describe("clock offset", () => {
  beforeEach(() => {
    theWebUI.deltaTime = 0;
    theWebUI.serverDeltaTime = 0;
  });

  it("prefers X-Server-Timestamp over a stale Date header", () => {
    const twoDaysAgo = Date.now() - 2 * 24 * 3600 * 1000;

    Ajax_UpdateTime(
      response({
        Date: new Date(twoDaysAgo).toUTCString(),
        "X-Server-Timestamp": String(Math.floor(Date.now() / 1000)),
      })
    );

    expect(Math.abs(theWebUI.deltaTime)).toBeLessThan(SLACK);
    expect(Math.abs(theWebUI.serverDeltaTime)).toBeLessThan(SLACK);
  });

  it("still measures a genuine offset from X-Server-Timestamp", () => {
    Ajax_UpdateTime(
      response({
        "X-Server-Timestamp": String(Math.floor(Date.now() / 1000) - 90),
      })
    );

    expect(theWebUI.deltaTime).toBeGreaterThan(90000 - SLACK);
    expect(theWebUI.deltaTime).toBeLessThan(90000 + SLACK);
  });

  it("falls back to the Date header when the server timestamp is absent", () => {
    Ajax_UpdateTime(
      response({ Date: new Date(Date.now() - 30000).toUTCString() })
    );

    expect(theWebUI.deltaTime).toBeGreaterThan(30000 - SLACK);
    expect(theWebUI.deltaTime).toBeLessThan(30000 + SLACK);
  });

  it("leaves the offset alone when the response carries neither header", () => {
    Ajax_UpdateTime(response({}));

    expect(theWebUI.deltaTime).toBe(0);
    expect(theWebUI.serverDeltaTime).toBe(0);
  });

  it("measures once and keeps that value until something resets it", () => {
    const secondsNow = Math.floor(Date.now() / 1000);

    Ajax_UpdateTime(response({ "X-Server-Timestamp": String(secondsNow - 30) }));
    const first = theWebUI.deltaTime;
    Ajax_UpdateTime(response({ "X-Server-Timestamp": String(secondsNow - 600) }));

    expect(theWebUI.deltaTime).toBe(first);
  });
});
