import { readFileSync } from "fs";

window.$ = require("jquery");

const initSrc = readFileSync("../plugins/ipad/init.js", { encoding: "utf-8" });

// Loads the plugin the way the plugin loader does, over a given set of flags,
// and reports which document listeners it asked for.
function load({ touchable, isSafari }) {
  const added = [];
  const real = document.addEventListener.bind(document);
  document.addEventListener = (t, f, o) => { added.push(t); return real(t, f, o); };
  window.browser = { isSafari };
  $.support = { touchable };
  try {
    const el = document.createElement("script");
    el.textContent = "(function(){ var plugin = {}; " + initSrc + "\nwindow.__ipad = plugin;\n})();";
    document.body.appendChild(el);
  } finally {
    document.addEventListener = real;
  }
  return added.filter((t) => t === "touchstart" || t === "touchend");
}

// The long press is the only gesture this plugin still supplies, and it is only
// supplied where there is a finger to press with.
describe("the ipad plugin's long-press listeners", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("are added on a Safari that has touch", () => {
    expect(load({ touchable: true, isSafari: true })).toEqual(["touchstart", "touchend"]);
  });

  // Safari on a desktop reports no touch: it has a right mouse button, and the
  // document should not be carrying touch listeners for it.
  it("are not added on a Safari without touch", () => {
    expect(load({ touchable: false, isSafari: true })).toEqual([]);
  });

  it("are not added on a non-Safari browser", () => {
    expect(load({ touchable: true, isSafari: false })).toEqual([]);
  });

  it("does not define the flag itself", () => {
    $.support = {};
    load({ touchable: undefined, isSafari: true });
    expect($.support.touchable).toBeUndefined();
  });
});
