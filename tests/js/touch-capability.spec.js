import { readFileSync } from "fs";

window.$ = require("jquery");

const commonSrc = readFileSync("../js/common.js", { encoding: "utf-8" });
const langSrc = readFileSync("../lang/en.js", { encoding: "utf-8" });

{
  const el = document.createElement("script");
  el.textContent = langSrc;
  document.body.appendChild(el);
}

// Re-runs common.js over a window dressed as the device under test, and hands
// back the flags it decided on plus the media queries it asked.
function detect({ ontouchstart = false, maxTouchPoints = 0, media = {} }) {
  const asked = [];
  const realMatchMedia = window.matchMedia;
  if (ontouchstart) window.ontouchstart = null;
  else delete window.ontouchstart;
  Object.defineProperty(window.navigator, "maxTouchPoints", {
    value: maxTouchPoints,
    configurable: true,
  });
  window.matchMedia = (q) => {
    asked.push(q);
    return { matches: !!media[q], media: q };
  };
  try {
    $.support = {};
    const el = document.createElement("script");
    el.textContent = commonSrc;
    document.body.appendChild(el);
    return { support: $.support, asked };
  } finally {
    window.matchMedia = realMatchMedia;
  }
}

const TOUCH_PRIMARY = "(hover: none) and (pointer: coarse)";

describe("the touch capability flags", () => {
  describe("$.support.touchable -- can the device deliver touch events", () => {
    it("is false for a mouse-only desktop", () => {
      expect(detect({}).support.touchable).toBe(false);
    });

    it("is true when the window carries ontouchstart", () => {
      expect(detect({ ontouchstart: true }).support.touchable).toBe(true);
    });

    // iPadOS Safari reports maxTouchPoints, and the ontouchstart property is
    // not the only way a browser says it has a touchscreen.
    it("is true when the device reports touch points", () => {
      expect(detect({ maxTouchPoints: 5 }).support.touchable).toBe(true);
    });

    it("does not consult hover or pointer", () => {
      const { asked } = detect({ ontouchstart: true });
      expect(asked.filter((q) => /hover|pointer/.test(q))).toEqual([TOUCH_PRIMARY]);
    });
  });

  describe("$.support.touchPrimary -- is touch how the device is pointed at", () => {
    it("asks for a coarse pointer that cannot hover", () => {
      expect(detect({}).asked).toContain(TOUCH_PRIMARY);
    });

    it("is true on a phone or tablet", () => {
      const r = detect({ ontouchstart: true, media: { [TOUCH_PRIMARY]: true } });
      expect(r.support.touchPrimary).toBe(true);
    });

    // The case the two flags exist to keep apart: a laptop with a touchscreen
    // has touch *and* a mouse and a keyboard, so anything that would otherwise
    // be done by hovering or typing has to stay.
    it("is false on a touchscreen laptop, which is still touchable", () => {
      const r = detect({ ontouchstart: true, maxTouchPoints: 10, media: {} });
      expect(r.support.touchable).toBe(true);
      expect(r.support.touchPrimary).toBe(false);
    });

    it("is false for a mouse-only desktop", () => {
      expect(detect({}).support.touchPrimary).toBe(false);
    });
  });
});
