import { mapBBCodeToHTML } from "../../../plugins/rss/bbcode";

window.$ = require("jquery");

// A feed description is remote html. plugins/rss/init.js hands it to
// mapBBCodeToHTML first and sanitizes the result only afterwards, so every
// parser call mapBBCodeToHTML makes runs on markup nobody has vetted yet.
//
// Whether that markup is dangerous is decided by the document it is parsed
// into. An element in a document that has a browsing context (the window's
// own document has one, and document.defaultView is that context) loads its
// resources and runs its scripts the moment the parser builds it -- an
// <img src> starts fetching and fires onerror while still detached from the
// page. A document with no browsing context, such as the one
// document.implementation.createHTMLDocument() returns or the contents of a
// <template>, builds the same nodes inertly: nothing is fetched, nothing runs.
//
// jsdom is not a browser and loads no images at all, so an injected onerror
// never fires here whichever document it lands in; the firing cannot be the
// assertion. The condition that decides the firing can be, and is what this
// spec measures: every string mapBBCodeToHTML hands to an html parser must be
// parsed into a document with no browsing context.
//
// The payload needs a bbcode tag around it, because every parser call sits in
// a bbcode tag handler -- text outside any tag is never parsed.
const PAYLOAD = '<img src="x" onerror="window.__bbcodeXSS = 1">';

// innerHTML on a <template> parses into the template's contents, which belong
// to a separate document, so the template element's own document says nothing
// about where the markup went.
const parsingDocumentOf = (element) =>
  typeof HTMLTemplateElement !== "undefined" &&
  element instanceof HTMLTemplateElement
    ? element.content.ownerDocument
    : element.ownerDocument;

function recordLiveParses(run) {
  const live = [];
  const descriptor = Object.getOwnPropertyDescriptor(
    Element.prototype,
    "innerHTML"
  );
  Object.defineProperty(Element.prototype, "innerHTML", {
    ...descriptor,
    set(value) {
      const text = String(value);
      if (parsingDocumentOf(this).defaultView && text.includes("onerror")) {
        live.push({ tag: this.nodeName.toLowerCase(), html: text });
      }
      descriptor.set.call(this, value);
    },
  });
  try {
    run();
    return live;
  } finally {
    Object.defineProperty(Element.prototype, "innerHTML", descriptor);
  }
}

describe("bbcode parses feed markup inertly", () => {
  const cases = [
    ["a simple tag", "[b]" + PAYLOAD + "[/b]"],
    ["a list", "[list]" + PAYLOAD + "[/list]"],
    ['a quote', '[quote="a"]' + PAYLOAD + "[/quote]"],
    ["a spoiler argument", "[spoiler=" + PAYLOAD + "]text[/spoiler]"],
    ["a table cell", "[table][tr][td]" + PAYLOAD + "[/td][/tr][/table]"],
  ];

  it.each(cases)(
    "never parses %s into a document with a browsing context",
    (_name, bbcode) => {
      expect(recordLiveParses(() => mapBBCodeToHTML(bbcode))).toEqual([]);
    }
  );

  it("still reproduces the payload in its output", () => {
    // Not a security assertion. It proves the payload reaches a parser at all,
    // so that an empty list above means inert parsing rather than a bbcode
    // string the mapper quietly dropped.
    expect(mapBBCodeToHTML("[b]" + PAYLOAD + "[/b]")).toContain("onerror");
  });
});
