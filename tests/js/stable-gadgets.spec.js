import { readFileSync } from "fs";

// The children of .stable-gadgets (the "Current Row:" indicator and the
// "not established" banner among them) size themselves in percentages of it,
// so it has to be as wide as the table. It also sits on top of the grid, so it
// must let clicks through to the rows while its children still take them.
//
// jsdom does no layout: this checks the cascade, not rendered boxes. The
// rendered geometry has to be checked in a browser.
describe(".stable-gadgets", () => {
  let gadgets;

  beforeAll(() => {
    const style = document.createElement("style");
    style.textContent = readFileSync("../css/stable.css", { encoding: "utf-8" });
    document.head.appendChild(style);
    document.body.innerHTML =
      '<div class="stable">' +
        '<div class="stable-body"><table></table></div>' +
        '<div class="stable-gadgets">' +
          '<div class="rowcover"></div>' +
          '<div class="stable-move-header"></div>' +
          '<div class="stable-separator-header"></div>' +
          '<div class="stable-resize-header"></div>' +
          '<span class="stable-scrollpos"></span>' +
        "</div>" +
      "</div>";
    gadgets = document.querySelector(".stable-gadgets");
  });

  test("is positioned over the whole table", () => {
    const table = getComputedStyle(document.querySelector(".stable"));
    const cs = getComputedStyle(gadgets);
    expect(table.position).toBe("relative");
    expect(cs.position).toBe("absolute");
    expect(cs.width).toBe("100%");
    expect(cs.height).toBe("100%");
  });

  test("the scroll position indicator is sized against it", () => {
    const cs = getComputedStyle(document.querySelector(".stable-scrollpos"));
    expect(cs.position).toBe("absolute");
    expect(cs.width).toBe("80%");
    expect(cs.left).toBe("10%");
  });

  test("lets clicks through to the grid", () => {
    expect(getComputedStyle(gadgets).pointerEvents).toBe("none");
  });

  test("while every child still takes them", () => {
    for (const child of gadgets.children)
      expect([child.className, getComputedStyle(child).pointerEvents])
        .toEqual([child.className, "auto"]);
  });
});
