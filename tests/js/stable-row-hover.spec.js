import { readFileSync } from "fs";

// A touch browser keeps :hover on the element it last tapped, so the row
// dimming stayed on the tapped row. The rule is therefore asked for only where
// hovering exists, and never for the selected row, which already carries the
// selection colour -- on a dark theme the two together are unreadable
// (MaterialDesign sets --row-highlight-brightness to 0.75 over #191919).
//
// jsdom neither evaluates media queries nor applies :hover, so this checks the
// shape of the rule. Whether a hovering pointer still dims an unselected row
// has to be checked in a browser.
describe("row hover dimming", () => {
  let sheet;

  beforeAll(() => {
    const style = document.createElement("style");
    style.textContent = readFileSync("../css/stable.css", { encoding: "utf-8" });
    document.head.appendChild(style);
    sheet = document.styleSheets[document.styleSheets.length - 1];
  });

  const dimmingRules = () => {
    const found = [];
    for (const rule of sheet.cssRules) {
      const media = rule.media ? rule.media.mediaText : "";
      for (const inner of rule.cssRules ?? [rule])
        if ((inner.style?.filter ?? "").includes("--row-highlight-brightness"))
          found.push({ selector: inner.selectorText, media });
    }
    return found;
  };

  it("is asked for only where the pointer can hover", () => {
    const rules = dimmingRules();
    expect(rules.length).toBe(1);
    expect(rules[0].media).toContain("hover: hover");
  });

  it("leaves the selected row alone", () => {
    expect(dimmingRules()[0].selector).toContain(":not(.selected)");
  });
});
