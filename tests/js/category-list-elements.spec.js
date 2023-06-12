import { log } from "console";
import { readFileSync } from "fs";
const indexDocument = new DOMParser().parseFromString(
  readFileSync("../index.html", { encoding: "utf-8" }),
  "text/html"
);

const categoryListElements = () =>
  indexDocument.getElementById("CatList").cloneNode(true);

document.head.append(
  indexDocument.getElementById("panel-label-template"),
  indexDocument.getElementById("category-panel-template")
);
for (const src of ["../js/custom-elements.js", "../js/category-list-elements.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.head.appendChild(scriptEl);
}

let catList;
describe("category-list", () => {
  beforeEach(() => {
    document.body.replaceChildren(categoryListElements());
    catList = document.getElementById("CatList");
  });

  it("should create", () => {
    const panelLabelAttribs = catList.panelLabelAttribs;
    const panelAttribs = catList.panelAttribs;
    catList.sync(panelLabelAttribs, panelAttribs);
    expect(panelAttribs).toEqual(catList.panelAttribs);
    expect(panelLabelAttribs).toEqual(catList.panelLabelAttribs);
    log("CategoryList: ", catList.panelAttribs, catList.panelLabelAttribs);
  });

  it("should emit label-click", () => {
    const onClick = jest.fn();
    const catList = document.getElementById("CatList");
    catList.addEventListener("label-click", onClick);

    // For initial element
    const panelLabelEl = document.getElementById("-_-_-com-_-_-");
    const e = new MouseEvent("mousedown");
    panelLabelEl.dispatchEvent(e);
    const callArg = onClick.mock.calls[0][0];
    expect(callArg.labelId).toEqual(panelLabelEl.id);
    expect(callArg.panelId).toEqual("pstate");
    onClick.mockClear();

    const plA = catList.panelLabelAttribs;
    const newLabelId = "clabel__some/label";
    plA.plabel.set(newLabelId, { text: "some/label" });
    catList.sync(plA, catList.panelAttribs);
    document.getElementById(newLabelId).dispatchEvent(e);
    const callArg2 = onClick.mock.calls[0][0];
    expect(callArg2.labelId).toEqual(newLabelId);
    expect(callArg2.panelId).toEqual("plabel");
  });
  it("should emit panel-close", async () => {
    const pstateEl = document.getElementById("pstate");
    pstateEl.closed = true;
    const onClose = jest.fn();
    const catList = document.getElementById("CatList");
    catList.addEventListener("panel-close", onClose);

    // For initial
    pstateEl.parts.heading.dispatchEvent(new MouseEvent("click"));
    await new Promise(process.nextTick);
    expect(pstateEl.closed).toBe(false);
    const callArg = onClose.mock.calls[0][0];
    expect(callArg.panelId).toEqual("pstate");
    expect(callArg.closed).toBe(false);
    onClose.mockClear();

    // For added
    const pA = catList.panelAttribs;
    const panelId = "psome";
    pA[panelId] = { text: "Some Panel" };
    const plA = catList.panelLabelAttribs;
    plA[panelId] = new Map();
    catList.sync(plA, pA);
    document
      .getElementById("psome")
      .parts.heading.dispatchEvent(new MouseEvent("click"));
    await new Promise(process.nextTick);
    const callArg2 = onClose.mock.calls[0][0];
    expect(callArg2.panelId).toEqual("psome");
    expect(callArg2.closed).toBe(true);
  });
});

describe("category-panel", () => {
  it("should create", () => {
    const p = document.createElement("category-panel");
    p.id = "ptest";
    p.text = "Test Panel";
    log("Panel: ", p.shadowRoot.innerHTML);
  });

  it("should open and close", async () => {
    const p = document.createElement("category-panel");
    expect(p.closed).toBe(false);
    p.closed = true;
    expect(p.closed).toBe(true);
    p.parts.heading.dispatchEvent(new MouseEvent("click"));
    await new Promise(process.nextTick);
    expect(p.closed).toBe(false);
    p.parts.heading.dispatchEvent(new MouseEvent("click"));
    await new Promise(process.nextTick);
    expect(p.closed).toBe(true);
  });
});

describe("panel-label", () => {
  it("should create", async () => {
    const label = document.createElement("panel-label");
    label.id = "label1";
    const attr = {
      prefix: "I",
      icon: "icon",
      text: "Test Label",
      count: "1",
      size: "2mb",
    };
    for (const [attrName, value] of Object.entries(attr)) {
      label[attrName] = value;
      expect(label[attrName]).toEqual(value);
    }

    document.body.appendChild(label);
    log("Label: ", label.shadowRoot.innerHTML);
  });

  it("should display prefix attribute", async () => {
    const label = document.createElement("panel-label");
    expect(label.parts.prefix.hidden).toBe(true);
    label.prefix = "II";
    await new Promise(process.nextTick);
    expect(label.parts.prefix.hidden).toBe(false);
    expect(label.parts.prefix.children.length).toBe(2);
    label.prefix = "";
    await new Promise(process.nextTick);
    expect(label.parts.prefix.hidden).toBe(false);
    expect(label.parts.prefix.children.length).toBe(0);
    label.prefix = null;
    await new Promise(process.nextTick);
    expect(label.parts.prefix.hidden).toBe(true);
  });

  it("should display icon attribute", async () => {
    const label = document.createElement("panel-label");
    const ic = label.parts.icon;
    label.icon = "url:name";
    await new Promise(process.nextTick);
    expect(ic.style.backgroundImage).toEqual(`url(name)`);
    expect(ic.children.length).toBe(0);
    expect([...ic.classList]).toEqual(["icon-by-url"]);
    label.icon = "name";
    await new Promise(process.nextTick);
    expect(ic.style.backgroundImage).toEqual("");
    expect(ic.children.length).toBe(0);
    expect([...ic.classList]).toEqual([]);
    label.icon = "n";
    await new Promise(process.nextTick);
    expect(ic.style.backgroundImage).toEqual("");
    expect(ic.children.length).toBeGreaterThan(0);
    expect([...ic.classList]).toEqual(["icon-letter"]);
  });
});
