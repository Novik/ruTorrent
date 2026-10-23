import { readFileSync } from "fs";

window.$ = require("jquery");

// lang/en.js before common.js: common.js reads theUILang at load time.
// common.js before objects.js and stable.js: both reach for $type() on a click.
for (const src of ["../lang/en.js", "../js/common.js", "../js/objects.js", "../js/stable.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

// jsdom reports which === 0 for a constructed MouseEvent whatever its button,
// so the gestures below are triggered with the button the browser would report.
// Everything else -- $.event.fix, the handler binding, the order the handlers
// run in -- is the code the page runs.
const ctrlPrimary = (type) => $.Event(type, { which: 1, ctrlKey: true });
const primary = (type) => $.Event(type, { which: 1, ctrlKey: false });
const secondary = (type) => $.Event(type, { which: 3, ctrlKey: false });

// What a Mac reports for ctrl with the primary button, in the order Chrome
// sends it: the contextmenu arrives before the release.
const ctrlClickGesture = (el) => {
  el.trigger(ctrlPrimary("mousedown"));
  el.trigger(ctrlPrimary("contextmenu"));
  el.trigger(ctrlPrimary("mouseup"));
};

// The same gesture where ctrl is a modifier and nothing else: no contextmenu,
// which is what Linux and Windows send.
const ctrlClickNoMenu = (el) => {
  el.trigger(ctrlPrimary("mousedown"));
  el.trigger(ctrlPrimary("mouseup"));
};

const withNavigator = (props, fn) => {
  const saved = Object.keys(props).map((k) => [k, Object.getOwnPropertyDescriptor(navigator, k)]);
  for (const [k, v] of Object.entries(props))
    Object.defineProperty(navigator, k, { value: v, configurable: true });
  try {
    return fn();
  } finally {
    for (const [k, d] of saved) {
      delete navigator[k];
      if (d) Object.defineProperty(navigator, k, d);
    }
  }
};

const MAC = { platform: "MacIntel", maxTouchPoints: 0, userAgent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)" };
const IPAD = { platform: "MacIntel", maxTouchPoints: 5, userAgent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)" };
const LINUX = { platform: "Linux x86_64", maxTouchPoints: 0, userAgent: "Mozilla/5.0 (X11; Linux x86_64)" };

describe("the platform where ctrl with the primary button is the secondary click", () => {
  it("is macOS", () => {
    expect(withNavigator(MAC, () => new browserDetect().isMac)).toBe(true);
  });

  it("is not Linux", () => {
    expect(withNavigator(LINUX, () => new browserDetect().isMac)).toBe(false);
  });

  // iPadOS reports the platform of a Mac. It has no ctrl-click, and reading it
  // as a Mac would hold back the handling of every ctrl-click it does send.
  it("is not an iPad, which reports a Mac platform with a touchscreen", () => {
    expect(withNavigator(IPAD, () => new browserDetect().isMac)).toBe(false);
  });

  it("is read from userAgentData where the browser offers it", () => {
    expect(
      withNavigator({ ...MAC, userAgentData: { platform: "Windows" } }, () => new browserDetect().isMac)
    ).toBe(false);
    expect(
      withNavigator({ ...LINUX, userAgentData: { platform: "macOS" } }, () => new browserDetect().isMac)
    ).toBe(true);
  });
});

describe("maybeSecondaryClick", () => {
  afterEach(() => {
    browser.isMac = false;
  });

  it("holds for ctrl with the primary button, down and up, on a Mac", () => {
    browser.isMac = true;
    expect(browser.maybeSecondaryClick(ctrlPrimary("mousedown"))).toBe(true);
    expect(browser.maybeSecondaryClick(ctrlPrimary("mouseup"))).toBe(true);
  });

  it("does not hold for the same events anywhere else", () => {
    expect(browser.maybeSecondaryClick(ctrlPrimary("mousedown"))).toBe(false);
    expect(browser.maybeSecondaryClick(ctrlPrimary("mouseup"))).toBe(false);
  });

  it("does not hold for a click that is already a secondary one", () => {
    browser.isMac = true;
    expect(browser.maybeSecondaryClick($.Event("mousedown", { which: 3, ctrlKey: true }))).toBe(false);
  });

  it("does not hold without ctrl", () => {
    browser.isMac = true;
    expect(browser.maybeSecondaryClick(primary("mousedown"))).toBe(false);
  });

  // Ctrl-A still selects every torrent on a Mac, and a ctrl-drag is no click.
  it("does not hold for anything but a press or a release", () => {
    browser.isMac = true;
    expect(browser.maybeSecondaryClick($.Event("keydown", { which: 65, ctrlKey: true }))).toBe(false);
    expect(browser.maybeSecondaryClick(ctrlPrimary("mousemove"))).toBe(false);
    expect(browser.maybeSecondaryClick(ctrlPrimary("click"))).toBe(false);
  });

  it("is carried by every mouse event the handlers are given", () => {
    browser.isMac = true;
    const el = $("<div>").appendTo(document.body);
    const seen = [];
    el.on("mousedown mouseup", (e) => seen.push(e.maybeSecondaryClick));
    el.trigger(ctrlPrimary("mousedown"));
    el.trigger(primary("mouseup"));
    expect(seen).toEqual([true, false]);
    el.remove();
  });
});

describe("mouseclick() through a ctrl-click", () => {
  let el;
  let seen;

  beforeEach(() => {
    seen = [];
    el = $("<div>").appendTo(document.body);
    el.mouseclick(function (e) {
      seen.push({ type: e.type, which: e.which, metaKey: e.metaKey });
    });
  });

  afterEach(() => {
    el.remove();
    browser.isMac = false;
  });

  // The defect: the press was handled as a primary click, and the contextmenu
  // that followed it as a secondary one, so one gesture did both.
  it("reaches the handler once, as the secondary click it is, on a Mac", () => {
    browser.isMac = true;
    ctrlClickGesture(el);
    expect(seen).toEqual([{ type: "contextmenu", which: 3, metaKey: false }]);
  });

  // Ctrl-click is multi-select on Linux and on Windows, and no contextmenu
  // follows it there.
  it("reaches the handler at once, as a primary click, anywhere else", () => {
    ctrlClickNoMenu(el);
    expect(seen).toEqual([{ type: "mousedown", which: 1, metaKey: true }]);
  });

  // The platform test only decides when the press is handled. Where it is
  // wrong, the release that no contextmenu preceded still carries the click.
  it("reaches the handler at the release when no contextmenu arrives", () => {
    browser.isMac = true;
    el.trigger(ctrlPrimary("mousedown"));
    expect(seen).toEqual([]);
    el.trigger(ctrlPrimary("mouseup"));
    expect(seen).toEqual([{ type: "mousedown", which: 1, metaKey: true }]);
  });

  it("leaves a plain press and a secondary press alone on a Mac", () => {
    browser.isMac = true;
    el.trigger(primary("mousedown"));
    el.trigger(secondary("mousedown"));
    el.trigger(secondary("mouseup"));
    expect(seen).toEqual([{ type: "mousedown", which: 1, metaKey: false }]);
  });

  it("holds nothing once the handler is taken off the element", () => {
    browser.isMac = true;
    el.mouseclick(null);
    ctrlClickGesture(el);
    expect(seen).toEqual([]);
  });

  it("drops a held press that the next gesture replaces", () => {
    browser.isMac = true;
    el.trigger(ctrlPrimary("mousedown"));
    el.trigger(primary("mousedown"));
    el.trigger(primary("mouseup"));
    expect(seen).toEqual([{ type: "mousedown", which: 1, metaKey: false }]);
  });
});

describe("the torrent list under a ctrl-click", () => {
  let table;
  let rows;

  const makeTable = (rowIDs) =>
    Object.assign(Object.create(window.dxSTable.prototype), {
      pendingSync: {},
      syncDOMAsync: jest.fn(),
      rowIDs,
      rowdata: Object.fromEntries(rowIDs.map((id) => [id, { enabled: true }])),
      rowSel: Object.fromEntries(rowIDs.map((id) => [id, false])),
      selCount: 0,
      stSel: [],
    });

  beforeEach(() => {
    rows = $("<table>").append(
      $("<tbody>").append(
        ["a", "b", "c"].map((id) => $("<tr>").attr("id", id).append($("<td>"))[0])
      )
    );
    rows.appendTo(document.body);
    table = makeTable(["a", "b", "c"]);
    rows.find("tbody").mouseclick(table.handleClick.bind(table));
  });

  afterEach(() => {
    rows.remove();
    browser.isMac = false;
  });

  const cell = (id) => rows.find(`#${id} td`);

  it("keeps a multiple selection when one of its rows is ctrl-clicked on a Mac", () => {
    cell("a").trigger(primary("mousedown"));
    cell("b").trigger($.Event("mousedown", { which: 1, metaKey: true }));
    expect(table.stSel).toEqual(["a", "b"]);

    browser.isMac = true;
    ctrlClickGesture(cell("b"));

    expect(table.stSel).toEqual(["a", "b"]);
  });

  // What a right-click does to a selection the clicked row is not part of.
  it("selects only the ctrl-clicked row when it was not selected, on a Mac", () => {
    cell("a").trigger(primary("mousedown"));
    cell("b").trigger($.Event("mousedown", { which: 1, metaKey: true }));

    browser.isMac = true;
    ctrlClickGesture(cell("c"));

    expect(table.stSel).toEqual(["c"]);
  });

  it("still adds to the selection where ctrl-click is multi-select", () => {
    cell("a").trigger(primary("mousedown"));
    ctrlClickNoMenu(cell("b"));

    expect(table.stSel).toEqual(["a", "b"]);
  });

  // Where the platform test is wrong, the release still carries the click.
  it("adds to the selection at the release when no contextmenu arrives", () => {
    browser.isMac = true;
    cell("a").trigger(primary("mousedown"));
    ctrlClickNoMenu(cell("b"));

    expect(table.stSel).toEqual(["a", "b"]);
  });

  // The modifier macOS leaves for multiple selection.
  it("adds to the selection on a cmd-click on a Mac", () => {
    browser.isMac = true;
    cell("a").trigger(primary("mousedown"));
    cell("b").trigger($.Event("mousedown", { which: 1, metaKey: true }));

    expect(table.stSel).toEqual(["a", "b"]);
  });
});

describe("the context menu and the release that ends the gesture", () => {
  let hide;

  beforeAll(() => {
    theContextMenu.init();
  });

  beforeEach(() => {
    hide = jest.spyOn(theContextMenu, "hide").mockImplementation(() => false);
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.runOnlyPendingTimers();
    jest.useRealTimers();
    hide.mockRestore();
    browser.isMac = false;
  });

  const body = () => $(document.body);

  // The menu the contextmenu event has just opened is still open when the
  // button is released, and that release is the end of the same gesture.
  it("is not asked to hide by the release of a ctrl-click on a Mac", () => {
    browser.isMac = true;
    ctrlClickGesture(body());
    jest.runOnlyPendingTimers();
    expect(hide).not.toHaveBeenCalled();
  });

  it("is asked to hide by a plain click", () => {
    body().trigger(primary("mousedown"));
    body().trigger(primary("mouseup"));
    jest.runOnlyPendingTimers();
    expect(hide).toHaveBeenCalled();
  });

  // Where the contextmenu event arrives after the release, the flag it sets
  // must not outlive its own gesture and swallow the next click.
  it("is asked to hide by the click that follows a secondary one", () => {
    body().trigger(secondary("mousedown"));
    body().trigger(secondary("mouseup"));
    body().trigger(secondary("contextmenu"));
    jest.runOnlyPendingTimers();
    expect(hide).not.toHaveBeenCalled();

    body().trigger(primary("mousedown"));
    body().trigger(primary("mouseup"));
    jest.runOnlyPendingTimers();
    expect(hide).toHaveBeenCalled();
  });
});

describe("a drag under a ctrl-click", () => {
  let el;
  let onStart;

  beforeEach(() => {
    el = $("<div>").appendTo(document.body);
    onStart = jest.fn(() => true);
    new DnD(el[0], { onStart, allowMobile: true });
  });

  afterEach(() => {
    el.remove();
    browser.isMac = false;
  });

  // A column header is dragged to move it and clicked to sort by it, so a
  // ctrl-click on one sorted the table while opening its menu.
  it("does not start on a ctrl-click on a Mac", () => {
    browser.isMac = true;
    el.trigger(ctrlPrimary("mousedown"));
    expect(onStart).not.toHaveBeenCalled();
  });

  it("starts on a plain press", () => {
    el.trigger(primary("mousedown"));
    expect(onStart).toHaveBeenCalled();
  });

  // The header menu is opened from this call.
  it("still reports a secondary press", () => {
    el.trigger(secondary("mousedown"));
    expect(onStart).toHaveBeenCalled();
  });
});
