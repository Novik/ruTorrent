import { readFileSync } from "fs";

window.$ = require("jquery");

// Constants and widget constructors that webui.js references at load time.
// The real ones live in js/stable.js, which needs a full DOM to be useful.
window.TYPE_STRING = "string";
window.TYPE_NUMBER = "number";
window.TYPE_PROGRESS = "progress";
window.TYPE_PEERS = "peers";
window.TYPE_SEEDS = "seeds";
window.ALIGN_RIGHT = "right";
window.dxSTable = function () {};
window.rSpeedGraph = function () {};
window.Timer = function () {};

for (const src of ["../lang/en.js", "../js/common.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// webui.js redefines theWebUI wholesale; drop the $(document).ready block,
// which needs the full page markup (same trick as webui-stale-details.spec.js).
{
  let code = readFileSync("../js/webui.js", { encoding: "utf-8" });
  code = code.replace(/\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/, "");
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

function h(char) {
  return Array.from({ length: 40 }, () => char).join("");
}

// A creation timestamp measured on a live system and a clock skew large
// enough (36 hours, in milliseconds) that adding it to a timestamp moves
// the rendered date to another calendar day.
const CREATED = 1786467616;
const BIG_DELTA = 36 * 3600 * 1000;

afterEach(() => {
  theWebUI.deltaTime = 0;
});

describe("torrent list date formatter", () => {
  function formatCreatedColumn(deltaTime) {
    theWebUI.deltaTime = deltaTime;
    const arr = [];
    arr[14] = CREATED; // index of the "created" column in the trt table
    return theFormatter.torrents(null, arr)[14];
  }

  it("renders the creation date from the raw timestamp regardless of deltaTime", () => {
    expect(formatCreatedColumn(BIG_DELTA)).toBe(theConverter.date(CREATED));
    expect(formatCreatedColumn(BIG_DELTA)).toBe(formatCreatedColumn(0));
  });
});

describe("torrent details pane", () => {
  const hash = h("A");
  const NOW = (CREATED + 10 * 86400) * 1000; // pinned browser clock, ms
  const ELAPSED = 3 * 86400; // seconds since state_changed at that clock

  beforeEach(() => {
    // Pin the clock so both renders share one "now" and the elapsed
    // strings can be asserted exactly.
    jest.useFakeTimers();
    jest.setSystemTime(NOW);
    document.body.innerHTML = '<span id="co"></span><span id="et"></span>';
    theWebUI.settings = theWebUI.settings || {};
    theWebUI.dID = hash;
    theWebUI.trackers = {};
    theWebUI.torrents = {
      [hash]: {
        downloaded: 0,
        uploaded: 0,
        ratio: 1000,
        ul: 0,
        dl: 0,
        eta: 0,
        seeds_actual: 0,
        seeds_all: 0,
        peers_actual: 0,
        peers_all: 0,
        state_changed: NOW / 1000 - ELAPSED,
        skip_total: 0,
        base_path: "",
        created: CREATED,
        tracker_size: 1,
        msg: "",
        comment: "",
        free_diskspace: "0",
      },
    };
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  function renderDetails(deltaTime) {
    theWebUI.deltaTime = deltaTime;
    theWebUI.updateDetails();
    return { created: $("#co").text(), elapsed: $("#et").text() };
  }

  it("keeps the creation date fixed while elapsed time still compensates for clock skew", () => {
    const skewed = renderDetails(BIG_DELTA);
    const accurate = renderDetails(0);

    // An absolute date names a moment; the browser renders it correctly
    // from the raw timestamp alone, no matter how wrong its clock is.
    expect(skewed.created).toBe(theConverter.date(CREATED));
    expect(skewed.created).toBe(accurate.created);

    // A duration compares a server timestamp with the browser's "now",
    // so it must keep using deltaTime to cancel the clock skew.
    expect(accurate.elapsed).toBe(theConverter.time(ELAPSED, true));
    expect(skewed.elapsed).toBe(theConverter.time(ELAPSED - BIG_DELTA / 1000, true));
  });
});
