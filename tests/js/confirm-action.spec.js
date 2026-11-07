import { readFileSync } from "fs";

const path = require("path");
const { parseSync, traverse } = require("@babel/core");
const { sourceFiles } = require("./context-menu-scan");

window.$ = require("jquery");

function run(code) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

for (const src of ["../lang/en.js", "../js/common.js", "../js/plugins.js"]) {
  run(readFileSync(src, { encoding: "utf-8" }));
}

// The confirmation dialog and the toolbar, reduced to the nodes the two
// functions write to.
document.body.insertAdjacentHTML("beforeend",
  '<div id="yesnoDlg-header"></div><div id="yesnoDlg-content"></div>' +
  '<a id="yesnoOK" href="#"></a><div id="toolbar"><span id="mnu_help"></span></div>');
window.theDialogManager = { shown: [], show(id) { this.shown.push(id); }, hide() {} };

function quietly(fn) {
  const warn = jest.spyOn(console, "warn").mockImplementation(() => {});
  try {
    return fn(warn);
  } finally {
    warn.mockRestore();
  }
}

beforeEach(() => {
  delete window.__ranAsCode;
  theDialogManager.shown = [];
});

describe("askYesNo", () => {
  it("runs the function it is given when the action is confirmed", () => {
    let ran = 0;
    askYesNo("Title", "Sure?", () => ran++);
    $("#yesnoOK").trigger("click");
    expect(ran).toBe(1);
  });

  it("does not run a string as code", () => {
    quietly(() => {
      askYesNo("Title", "Sure?", "window.__ranAsCode = 1");
      $("#yesnoOK").trigger("click");
    });
    expect(window.__ranAsCode).toBeUndefined();
  });

  it("names the action it will not run, and asks nothing", () => {
    quietly((warn) => {
      askYesNo("Title", "Sure?", "theWebUI.doRemove()");
      expect(warn).toHaveBeenCalledWith(expect.stringContaining("theWebUI.doRemove()"));
    });
    expect(theDialogManager.shown).toEqual([]);
  });
});

describe("a toolbar button", () => {
  function button(onclick) {
    const plugin = new rPlugin("confirmtest", 1, "", "", 0, "");
    plugin.canChangeToolbar = () => true;
    plugin.addButtonToToolbar("confirmtest", "Test", onclick, "help");
    return $("#mnu_confirmtest");
  }

  afterEach(() => $("#mnu_confirmtest").remove());

  it("runs the function it is given", () => {
    let ran = 0;
    button(() => ran++).trigger("click");
    expect(ran).toBe(1);
  });

  it("does not run a string as code", () => {
    quietly(() => button("window.__ranAsCode = 1").trigger("click"));
    expect(window.__ranAsCode).toBeUndefined();
  });
});

// Every caller in the tree hands both functions a function. A string would
// now do nothing, so a missed conversion is a button or a delete that
// silently stops working.
describe("every caller in the tree", () => {
  const TREE = path.resolve(__dirname, "..", "..");
  const COMMAND_ARG = new Map([["askYesNo", 2], ["addButtonToToolbar", 2]]);
  const findings = [];
  let calls = 0;
  for (const { file, code } of sourceFiles(TREE)) {
    let ast;
    try {
      ast = parseSync(code, {
        filename: file,
        configFile: false,
        babelrc: false,
        sourceType: "unambiguous",
        parserOpts: { allowReturnOutsideFunction: true },
      });
    } catch (e) {
      // A file that cannot be read cannot be checked either.
      findings.push(`${file} does not parse: ${e.message}`);
      continue;
    }
    traverse(ast, {
      CallExpression(p) {
        const callee = p.node.callee;
        const name = callee.type === "Identifier" ? callee.name :
          (callee.type === "MemberExpression" && !callee.computed ? callee.property.name : null);
        if (!COMMAND_ARG.has(name)) return;
        const arg = p.node.arguments[COMMAND_ARG.get(name)];
        calls++;
        if (arg && ["StringLiteral", "TemplateLiteral", "BinaryExpression"].includes(arg.type))
          findings.push(`${file}:${arg.loc.start.line} ${name} ${code.slice(arg.start, arg.end)}`);
      },
    });
  }

  it("is found", () => {
    expect(calls).toBeGreaterThan(10);
  });

  it("passes a function, not a string", () => {
    expect(findings).toEqual([]);
  });
});
