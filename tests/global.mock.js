CSSStyleSheet.prototype.replaceSync = function (text) {
  this.textContent = text;
};
global.structuredClone = (v) => JSON.parse(JSON.stringify(v));

// js/browser.js is a classic <script> (class browserDetect) in production, not an
// ES module. Specs neither inject nor import it, so common.js's `new browserDetect()`
// throws (which also aborts common.js before theURLs is defined). Expose the real
// class and the `browser` instance globally for both inject-script and import specs.
const { readFileSync } = require("fs");
const { join } = require("path");
const browserDetect = new Function(
  readFileSync(join(__dirname, "../js/browser.js"), "utf-8") + "\nreturn browserDetect;"
)();
global.browserDetect = browserDetect;
global.browser = new browserDetect();

// jsdom evaluates no media queries and defines no matchMedia at all, so the
// capability tests common.js runs at load throw there. Answer every query with
// a non-match; a spec that cares about one replaces this.
if (typeof window.matchMedia !== "function") {
  window.matchMedia = (query) => ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener() {},
    removeEventListener() {},
    addListener() {},
    removeListener() {},
    dispatchEvent: () => false,
  });
}
