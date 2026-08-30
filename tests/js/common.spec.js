import { readFileSync } from "fs";

window.$ = require("jquery");

for (const src of ["../lang/en.js", "../js/common.js"]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}

describe("escapeHTML", () => {
  it("returns an empty string for null and undefined", () => {
    expect(window.escapeHTML(null)).toBe("");
    expect(window.escapeHTML(undefined)).toBe("");
  });

  it("leaves text without special characters untouched", () => {
    expect(window.escapeHTML("")).toBe("");
    expect(window.escapeHTML("plain text 123")).toBe("plain text 123");
  });

  it("escapes each of the five special characters", () => {
    expect(window.escapeHTML("&")).toBe("&amp;");
    expect(window.escapeHTML("<")).toBe("&lt;");
    expect(window.escapeHTML(">")).toBe("&gt;");
    expect(window.escapeHTML('"')).toBe("&quot;");
    expect(window.escapeHTML("'")).toBe("&#039;");
  });

  it("escapes '&' first so replacements are not double-escaped", () => {
    // If '&' were escaped last, "<" would become "&amp;lt;" instead of "&lt;".
    expect(window.escapeHTML("<")).toBe("&lt;");
    expect(window.escapeHTML('"')).toBe("&quot;");
    expect(window.escapeHTML("<a href=\"x\">")).toBe("&lt;a href=&quot;x&quot;&gt;");
  });

  it("escapes an already-escaped entity again (no unescaping is attempted)", () => {
    expect(window.escapeHTML("&lt;")).toBe("&amp;lt;");
    expect(window.escapeHTML("&amp;")).toBe("&amp;amp;");
  });

  it("neutralises a script tag payload", () => {
    expect(window.escapeHTML("<script>alert('xss')</script>")).toBe(
      "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;"
    );
  });

  it("escapes every occurrence, not just the first", () => {
    expect(window.escapeHTML("a<b<c")).toBe("a&lt;b&lt;c");
    expect(window.escapeHTML("&&&")).toBe("&amp;&amp;&amp;");
  });

  it("stringifies non-string input", () => {
    expect(window.escapeHTML(42)).toBe("42");
    expect(window.escapeHTML(0)).toBe("0");
    expect(window.escapeHTML(false)).toBe("false");
    expect(window.escapeHTML({})).toBe("[object Object]");
    expect(window.escapeHTML(["<a>", "b"])).toBe("&lt;a&gt;,b");
  });
});

describe("iv", () => {
  it("returns 0 for falsey input", () => {
    expect(window.iv(null)).toBe(0);
    expect(window.iv(undefined)).toBe(0);
    expect(window.iv(false)).toBe(0);
    expect(window.iv("")).toBe(0);
    expect(window.iv(0)).toBe(0);
  });

  it("parses integers from numbers and numeric strings", () => {
    expect(window.iv(42)).toBe(42);
    expect(window.iv("42")).toBe(42);
    expect(window.iv("0")).toBe(0);
    expect(window.iv("  42  ")).toBe(42);
  });

  it("truncates floats towards zero", () => {
    expect(window.iv(3.9)).toBe(3);
    expect(window.iv("3.9")).toBe(3);
    expect(window.iv(-4.7)).toBe(-4);
    expect(window.iv("-4.7")).toBe(-4);
  });

  it("handles negative values", () => {
    expect(window.iv(-7)).toBe(-7);
    expect(window.iv("-7")).toBe(-7);
  });

  it("takes the leading numeric part of a partially numeric string", () => {
    expect(window.iv("12abc")).toBe(12);
    expect(window.iv("7 torrents")).toBe(7);
    expect(window.iv("-3px")).toBe(-3);
  });

  it("returns 0 for a non-numeric string", () => {
    expect(window.iv("abc")).toBe(0);
    expect(window.iv("abc12")).toBe(0);
    expect(window.iv("NaN")).toBe(0);
  });

  it("parses 0x-prefixed strings as hexadecimal (parseInt radix detection)", () => {
    expect(window.iv("0x10")).toBe(16);
  });

  it("returns 0 for booleans, since parseInt('true') is NaN", () => {
    expect(window.iv(true)).toBe(0);
  });

  it("coerces arrays through their string form", () => {
    expect(window.iv([])).toBe(0);
    expect(window.iv([7])).toBe(7);
  });
});

describe("ir", () => {
  it("returns 0 for falsey input", () => {
    expect(window.ir(null)).toBe(0);
    expect(window.ir(undefined)).toBe(0);
    expect(window.ir(false)).toBe(0);
    expect(window.ir("")).toBe(0);
    expect(window.ir(0)).toBe(0);
  });

  it("keeps the fractional part", () => {
    expect(window.ir(3.9)).toBe(3.9);
    expect(window.ir("3.9")).toBe(3.9);
    expect(window.ir(".5")).toBe(0.5);
    expect(window.ir("-0.25")).toBe(-0.25);
  });

  it("parses plain integers and the string '0'", () => {
    expect(window.ir(42)).toBe(42);
    expect(window.ir("42")).toBe(42);
    expect(window.ir("0")).toBe(0);
  });

  it("takes the leading numeric part of a partially numeric string", () => {
    expect(window.ir("12.5abc")).toBe(12.5);
    expect(window.ir("1.5 GB")).toBe(1.5);
  });

  it("returns 0 for a non-numeric string", () => {
    expect(window.ir("abc")).toBe(0);
    expect(window.ir("abc1.5")).toBe(0);
  });

  it("understands exponent notation", () => {
    expect(window.ir("1e3")).toBe(1000);
  });

  it("does not parse hexadecimal, unlike iv", () => {
    expect(window.ir("0x10")).toBe(0);
    expect(window.iv("0x10")).toBe(16);
  });
});

describe("addslashes", () => {
  it("escapes backslashes, double quotes and single quotes", () => {
    expect(window.addslashes("a\\b")).toBe("a\\\\b");
    expect(window.addslashes('say "hi"')).toBe('say \\"hi\\"');
    expect(window.addslashes("O'Reilly")).toBe("O\\'Reilly");
  });

  it("escapes NUL, LF and CR as their two-character escapes", () => {
    expect(window.addslashes("a\u0000b")).toBe("a\\0b");
    expect(window.addslashes("a\nb")).toBe("a\\nb");
    expect(window.addslashes("a\rb")).toBe("a\\rb");
  });

  it("leaves ordinary text unchanged", () => {
    expect(window.addslashes("")).toBe("");
    expect(window.addslashes("plain text")).toBe("plain text");
  });

  it("escapes every occurrence", () => {
    expect(window.addslashes("''")).toBe("\\'\\'");
  });

  it("coerces non-string input to a string", () => {
    expect(window.addslashes(42)).toBe("42");
    expect(window.addslashes(null)).toBe("null");
    expect(window.addslashes(undefined)).toBe("undefined");
  });
});

describe("strip_tags", () => {
  it("removes all tags when no allow list is given", () => {
    expect(window.strip_tags("<p>Hello <b>World</b></p>")).toBe("Hello World");
    expect(window.strip_tags("<br/>")).toBe("");
  });

  it("keeps the text content of a script tag but drops the tag itself", () => {
    // strip_tags is not a sanitiser: escapeHTML is what protects against markup.
    expect(window.strip_tags("<script>alert(1)</script>hi")).toBe("alert(1)hi");
  });

  it("keeps tags present in the allow list", () => {
    expect(window.strip_tags("<p>Hello <b>World</b></p>", "<b>")).toBe("Hello <b>World</b>");
    expect(window.strip_tags("<p>a<b>b</b><i>c</i></p>", "<b><i>")).toBe("a<b>b</b><i>c</i>");
  });

  it("matches the allow list case-insensitively", () => {
    expect(window.strip_tags("<B>x</B>", "<b>")).toBe("<B>x</B>");
    expect(window.strip_tags("<b>x</b>", "<B>")).toBe("<b>x</b>");
  });

  it("keeps the attributes of an allowed tag as-is", () => {
    expect(window.strip_tags('<a href="x" onclick="y">z</a>', "<a>")).toBe(
      '<a href="x" onclick="y">z</a>'
    );
  });

  it("ignores allow-list entries that carry attributes", () => {
    // The allow list only understands bare <tag> forms, so this allows nothing.
    expect(window.strip_tags('<a href="x">z</a>', '<a href="x">')).toBe("z");
  });

  it("strips HTML comments and PHP tags", () => {
    expect(window.strip_tags("a<!-- comment -->b")).toBe("ab");
    expect(window.strip_tags("a<!-- multi\nline -->b")).toBe("ab");
    expect(window.strip_tags("a<?php echo 1; ?>b")).toBe("ab");
    expect(window.strip_tags("a<? echo 1; ?>b")).toBe("ab");
  });

  it("strips comments even when their tags are in the allow list", () => {
    expect(window.strip_tags("<b>a<!-- c --></b>", "<b>")).toBe("<b>a</b>");
  });

  it("leaves stray angle brackets that are not tags alone", () => {
    expect(window.strip_tags("a < b > c")).toBe("a < b > c");
    expect(window.strip_tags("1 <2> 3")).toBe("1 <2> 3");
  });

  it("returns the input unchanged when there is nothing to strip", () => {
    expect(window.strip_tags("")).toBe("");
    expect(window.strip_tags("plain text")).toBe("plain text");
  });
});

describe("cloneObject", () => {
  it("returns null and undefined unchanged", () => {
    expect(window.cloneObject(null)).toBe(null);
    expect(window.cloneObject(undefined)).toBe(undefined);
  });

  it("returns primitives unchanged", () => {
    expect(window.cloneObject(5)).toBe(5);
    expect(window.cloneObject("str")).toBe("str");
    expect(window.cloneObject(true)).toBe(true);
  });

  it("copies a flat object by value", () => {
    const src = { a: 1, b: "two", c: false };
    const copy = window.cloneObject(src);
    expect(copy).toEqual(src);
    expect(copy).not.toBe(src);
  });

  it("deep-clones nested objects so the copy is independent", () => {
    const src = { outer: { inner: { n: 1 } } };
    const copy = window.cloneObject(src);
    expect(copy).toEqual(src);
    expect(copy.outer).not.toBe(src.outer);
    expect(copy.outer.inner).not.toBe(src.outer.inner);

    copy.outer.inner.n = 99;
    expect(src.outer.inner.n).toBe(1);

    src.outer.inner.n = 7;
    expect(copy.outer.inner.n).toBe(99);
  });

  it("clones arrays as arrays, including nested ones", () => {
    const src = [1, [2, 3], { a: 4 }];
    const copy = window.cloneObject(src);
    expect(Array.isArray(copy)).toBe(true);
    expect(copy).toEqual(src);
    expect(copy[1]).not.toBe(src[1]);

    copy[1][0] = 99;
    expect(src[1][0]).toBe(2);
  });

  it("preserves nulls stored inside an object", () => {
    const copy = window.cloneObject({ a: null, b: { c: null } });
    expect(copy).toEqual({ a: null, b: { c: null } });
  });

  it("copies inherited object-valued properties but not inherited primitives", () => {
    // The hasOwnProperty check is OR-ed with a typeof 'object' test, so
    // inherited objects are pulled onto the clone while inherited
    // primitives are dropped.
    const proto = { inheritedObj: { x: 1 }, inheritedPrim: 5 };
    const src = Object.create(proto);
    src.own = 1;

    const copy = window.cloneObject(src);
    expect(Object.prototype.hasOwnProperty.call(copy, "own")).toBe(true);
    expect(Object.prototype.hasOwnProperty.call(copy, "inheritedObj")).toBe(true);
    expect(Object.prototype.hasOwnProperty.call(copy, "inheritedPrim")).toBe(false);
    expect(copy.inheritedObj).not.toBe(proto.inheritedObj);
  });

  it("does not preserve the value of a Date (only its type)", () => {
    // new srcObj.constructor() plus a for..in copy cannot carry a Date's
    // internal time, so the clone is a Date of 'now'. Callers must not
    // rely on cloneObject for Dates.
    const src = new Date(Date.UTC(2020, 0, 1));
    const copy = window.cloneObject(src);
    expect(copy).toBeInstanceOf(Date);
    expect(copy.getTime()).not.toBe(src.getTime());
  });
});

describe("propsCount", () => {
  it("counts own enumerable properties", () => {
    expect(window.propsCount({})).toBe(0);
    expect(window.propsCount({ a: 1 })).toBe(1);
    expect(window.propsCount({ a: 1, b: 2, c: 3 })).toBe(3);
  });

  it("counts properties whose values are falsey or undefined", () => {
    expect(window.propsCount({ a: 0, b: "", c: null, d: undefined })).toBe(4);
  });

  it("ignores inherited properties", () => {
    const src = Object.create({ inherited: 1, alsoInherited: 2 });
    src.own = 1;
    expect(window.propsCount(src)).toBe(1);
  });

  it("counts array indices", () => {
    expect(window.propsCount([])).toBe(0);
    expect(window.propsCount([1, 2, 3])).toBe(3);
  });

  it("returns an inherited numeric __count__ instead of counting", () => {
    const src = Object.create({ __count__: 42 });
    src.a = 1;
    expect(window.propsCount(src)).toBe(42);
  });

  it("counts normally when __count__ is an own property", () => {
    expect(window.propsCount({ __count__: 7, a: 1 })).toBe(2);
  });
});

describe("getCRC", () => {
  it("is deterministic for the same input", () => {
    expect(window.getCRC("ruTorrent")).toBe(window.getCRC("ruTorrent"));
  });

  it("returns known values (regression guard on the CRC-16 table)", () => {
    expect(window.getCRC("a")).toBe(31879);
    expect(window.getCRC("b")).toBe(19684);
    expect(window.getCRC("hello world")).toBe(15332);
    expect(window.getCRC("ruTorrent")).toBe(46321);
  });

  it("walks the whole lookup table, catching a single corrupted entry", () => {
    // Each table slot is reachable only from a specific (crc, charCode) pair,
    // so short ASCII inputs leave most of the table unread. Feeding every byte
    // value sweeps a wide slice of it; slot 0 in particular is only reached by
    // a leading NUL with a zero seed.
    let allBytes = "";
    for (let i = 0; i < 256; i++) allBytes += String.fromCharCode(i);
    expect(window.getCRC(allBytes)).toBe(32341);
    expect(window.getCRC(String.fromCharCode(0))).toBe(0);
  });

  it("produces different values for different inputs", () => {
    expect(window.getCRC("a")).not.toBe(window.getCRC("b"));
    expect(window.getCRC("hello world")).not.toBe(window.getCRC("hello worlds"));
    expect(window.getCRC("ab")).not.toBe(window.getCRC("ba"));
  });

  it("treats the second argument as a seed", () => {
    expect(window.getCRC("ruTorrent", 1)).toBe(62242);
    expect(window.getCRC("ruTorrent", 1)).not.toBe(window.getCRC("ruTorrent"));
  });

  it("defaults the seed to 0 when it is missing or not a number", () => {
    expect(window.getCRC("a", undefined)).toBe(window.getCRC("a", 0));
    expect(window.getCRC("a", null)).toBe(window.getCRC("a", 0));
    expect(window.getCRC("a", "not a number")).toBe(window.getCRC("a", 0));
    expect(window.getCRC("a")).toBe(window.getCRC("a", 0));
  });

  it("returns the seed unchanged for an empty string", () => {
    expect(window.getCRC("")).toBe(0);
    expect(window.getCRC("", 1234)).toBe(1234);
  });

  it("can be resumed by feeding a previous result back as the seed", () => {
    expect(window.getCRC("world", window.getCRC("hello "))).toBe(window.getCRC("hello world"));
  });

  it("stays inside 16 bits", () => {
    for (const s of ["a", "ruTorrent", "hello world", "\u00ff\u00ff"]) {
      const crc = window.getCRC(s);
      expect(crc).toBeGreaterThanOrEqual(0);
      expect(crc).toBeLessThanOrEqual(0xffff);
    }
  });
});

describe("$type", () => {
  it("returns false for null and undefined", () => {
    expect(window.$type(null)).toBe(false);
    expect(window.$type(undefined)).toBe(false);
  });

  it("returns 'array' for arrays", () => {
    expect(window.$type([])).toBe("array");
    expect(window.$type([1, 2])).toBe("array");
  });

  it("returns the typeof for everything else", () => {
    expect(window.$type("s")).toBe("string");
    expect(window.$type(0)).toBe("number");
    expect(window.$type(false)).toBe("boolean");
    expect(window.$type({})).toBe("object");
    expect(window.$type(function () {})).toBe("function");
  });
});

describe("$$", () => {
  afterEach(() => {
    document.getElementById("fixture")?.remove();
  });

  it("looks up an element by id when given a string", () => {
    const div = document.createElement("div");
    div.id = "fixture";
    document.body.appendChild(div);
    expect(window.$$("fixture")).toBe(div);
  });

  it("returns null for an unknown id", () => {
    expect(window.$$("no-such-id")).toBe(null);
  });

  it("passes non-string arguments straight through", () => {
    const div = document.createElement("div");
    expect(window.$$(div)).toBe(div);
    expect(window.$$(null)).toBe(null);
  });
});

describe("linked", () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <input type="checkbox" id="toggle">
      <select id="picker">
        <option value="off">off</option>
        <option value="on">on</option>
      </select>
      <input type="text" id="target1">
      <label id="lbl_target1">t1</label>
      <input type="text" id="target2">
      <label id="lbl_target2">t2</label>
      <span id="notafield"></span>
    `;
  });

  afterEach(() => {
    document.body.innerHTML = "";
  });

  const disabled = (id) => window.$(`#${id}`).prop("disabled");
  const labelDisabled = (id) => window.$(`#lbl_${id}`).hasClass("disabled");

  it("enables the targets when a checkbox is checked (falsey second argument)", () => {
    const cb = document.getElementById("toggle");
    cb.checked = true;
    window.linked(cb, false, ["target1", "target2"]);
    expect(disabled("target1")).toBe(false);
    expect(disabled("target2")).toBe(false);
    expect(labelDisabled("target1")).toBe(false);
  });

  it("disables the targets when a checkbox is unchecked (falsey second argument)", () => {
    const cb = document.getElementById("toggle");
    cb.checked = false;
    window.linked(cb, false, ["target1", "target2"]);
    expect(disabled("target1")).toBe(true);
    expect(disabled("target2")).toBe(true);
    expect(labelDisabled("target1")).toBe(true);
    expect(labelDisabled("target2")).toBe(true);
  });

  it("inverts the mapping when the second argument is truthy", () => {
    const cb = document.getElementById("toggle");
    cb.checked = true;
    window.linked(cb, true, ["target1"]);
    expect(disabled("target1")).toBe(true);
    expect(labelDisabled("target1")).toBe(true);

    cb.checked = false;
    window.linked(cb, true, ["target1"]);
    expect(disabled("target1")).toBe(false);
    expect(labelDisabled("target1")).toBe(false);
  });

  it("disables the targets when a select matches the given value", () => {
    const sel = document.getElementById("picker");
    sel.value = "off";
    window.linked(sel, "off", ["target1"]);
    expect(disabled("target1")).toBe(true);
    expect(labelDisabled("target1")).toBe(true);

    sel.value = "on";
    window.linked(sel, "off", ["target1"]);
    expect(disabled("target1")).toBe(false);
    expect(labelDisabled("target1")).toBe(false);
  });

  it("compares select values strictly, so a non-matching value enables", () => {
    const sel = document.getElementById("picker");
    sel.value = "on";
    window.linked(sel, "never-an-option", ["target1"]);
    expect(disabled("target1")).toBe(false);
  });

  it("does nothing for an element that is neither a checkbox nor a select", () => {
    window.$("#target1").prop("disabled", true);
    window.linked(document.getElementById("notafield"), false, ["target1"]);
    expect(disabled("target1")).toBe(true);
  });

  it("does nothing for a non-checkbox input", () => {
    window.$("#target2").prop("disabled", true);
    window.linked(document.getElementById("target1"), false, ["target2"]);
    expect(disabled("target2")).toBe(true);
  });

  it("accepts an empty target list without throwing", () => {
    const cb = document.getElementById("toggle");
    expect(() => window.linked(cb, false, [])).not.toThrow();
  });
});

describe("socketAllocationFits", () => {
  // rtorrent reports no budget: every pair has to be allowed through.
  it("accepts anything when there is no budget", () => {
    expect(window.socketAllocationFits(0, 100000, 100000)).toBe(true);
    expect(window.socketAllocationFits(undefined, 1, 1)).toBe(true);
  });

  it("accepts a pair that fits and the exact boundary", () => {
    expect(window.socketAllocationFits(3448, 2048, 128)).toBe(true);
    expect(window.socketAllocationFits(3448, 3320, 128)).toBe(true);
    expect(window.socketAllocationFits(3448, 3448, 0)).toBe(true);
  });

  it("refuses one over the boundary, whichever field carries it", () => {
    expect(window.socketAllocationFits(3448, 3321, 128)).toBe(false);
    expect(window.socketAllocationFits(3448, 2048, 1401)).toBe(false);
    expect(window.socketAllocationFits(3448, 4096, 128)).toBe(false);
  });

  // The fields come straight from text inputs.
  it("copes with the values arriving as strings", () => {
    expect(window.socketAllocationFits("3448", "3320", "128")).toBe(true);
    expect(window.socketAllocationFits("3448", "3321", "128")).toBe(false);
    expect(window.socketAllocationFits(3448, "", "")).toBe(true);
  });
});
