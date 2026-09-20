import { readFileSync } from "fs";

window.$ = require("jquery");

for (const src of ["../lang/en.js", "../js/common.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// The other half of tests/plugins/rss/OpenableLinkTest.php. That file asserts
// the 'server' column of the shared table against rRSS::isOpenableLink(); this
// one asserts the 'client' column against isExternalURL(), so the table stands
// for what both sides actually do rather than for what one of them is assumed
// to do. Widening or narrowing either side without the other fails here or
// there.
const ADDRESSES = JSON.parse(
  readFileSync("fixtures/openable-addresses.json", { encoding: "utf-8" })
);

describe("isExternalURL against the shared address table", () => {
  it("reads a table with addresses in it", () => {
    expect(ADDRESSES.length).toBeGreaterThan(0);
  });

  it("gives every address the verdict the table records", () => {
    const disagreed = ADDRESSES.filter(
      (row) => window.isExternalURL(row.url) !== row.client
    ).map((row) => `${JSON.stringify(row.url)} (${row.why})`);
    expect(disagreed).toEqual([]);
  });

  // The verdicts have to be the ones window.open() acts on, not a separate
  // opinion held alongside it.
  it("opens exactly the addresses it says it will", () => {
    const realOpen = window.open;
    const opened = [];
    window.open = (...args) => {
      opened.push(args[0]);
      return null;
    };
    try {
      for (const row of ADDRESSES) {
        expect(window.openExternalURL(row.url)).toBe(row.client);
      }
    } finally {
      window.open = realOpen;
    }
    expect(opened).toEqual(ADDRESSES.filter((r) => r.client).map((r) => r.url));
  });

  // The rule the table exists to hold, from this side: an address the parser
  // keeps has to be one this will open.
  it("opens every address the feed parser is allowed to keep", () => {
    const dead = ADDRESSES.filter((row) => row.server && !row.client).map(
      (row) => JSON.stringify(row.url)
    );
    expect(dead).toEqual([]);
  });
});
