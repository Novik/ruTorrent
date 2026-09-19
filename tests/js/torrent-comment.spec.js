import { readFileSync } from "fs";

window.$ = require("jquery");

function h(char) {
  return Array.from({ length: 40 }, () => char).join("");
}

function loadWebUI() {
  window.theUILang = new Proxy({}, { get: (_target, prop) => prop });
  window.theFormatter = {};
  window.TYPE_STRING = "string";
  window.TYPE_NUMBER = "number";
  window.TYPE_PROGRESS = "progress";
  window.TYPE_PEERS = "peers";
  window.TYPE_SEEDS = "seeds";
  window.ALIGN_RIGHT = "right";
  window.dxSTable = function () {};
  window.rSpeedGraph = function () {};
  window.rSpeedGraph.prototype.addData = jest.fn();
  window.Timer = function () {};
  window.theConverter = {
    bytes: () => "",
    speed: () => "",
    round: () => "",
    time: () => "",
    date: () => "",
  };
  window.getClickableTrackerStatus = () => "";

  for (const src of ["../js/common.js", "../js/webui.js"]) {
    let code = readFileSync(src, { encoding: "utf-8" });
    code = code.replace(
      /\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/,
      ""
    );
    const scriptEl = document.createElement("script");
    scriptEl.textContent = code;
    document.body.appendChild(scriptEl);
  }
}

// updateDetails writes into the detail panel by id. Only #cmt is asserted on,
// but the rest have to exist or the jQuery writes land nowhere and a test could
// pass for the wrong reason.
const DETAIL_IDS = [
  "dl", "ul", "ra", "us", "ds", "rm", "se", "pe", "et",
  "wa", "bf", "co", "tu", "hs", "ts", "cmt", "dsk",
];

function showComment(comment) {
  const hash = h("A");
  document.body.innerHTML = DETAIL_IDS.map((id) => `<div id="${id}"></div>`).join("");
  Object.assign(theWebUI, {
    dID: hash,
    trackers: {},
    torrents: {
      [hash]: {
        downloaded: 0, uploaded: 0, ratio: 0, ul: 0, dl: 0, eta: -1,
        seeds_actual: 0, seeds_all: 0, peers_actual: 0, peers_all: 0,
        state_changed: 0, skip_total: 0, base_path: "/", created: 0,
        tracker_size: 0, msg: "", free_diskspace: "0",
        comment,
      },
    },
    deltaTime: 0,
  });
  theWebUI.updateDetails();
  return $("#cmt");
}

describe("torrent comment rendering", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    delete window.__xss;
    loadWebUI();
  });

  // The comment is a bencode field written by whoever created the torrent, so
  // for every client that opens the torrent it is remote input from a stranger.
  // <a>, <b> and <strong> used to reach the DOM with all of their attributes,
  // which is enough to carry a handler.
  it("does not run a handler attribute on an anchor the comment supplies", () => {
    const cmt = showComment(`<a href="#" onclick="window.__xss=1">download</a>`);
    cmt.find("a").each((_, el) => el.dispatchEvent(new MouseEvent("click")));
    expect(window.__xss).toBeUndefined();
    expect(cmt.find("[onclick]").length).toBe(0);
  });

  it("does not run a handler attribute on a bold element the comment supplies", () => {
    const cmt = showComment(`<b onmouseover="window.__xss=1">hover me</b>`);
    cmt.find("*").each((_, el) => el.dispatchEvent(new MouseEvent("mouseover")));
    expect(window.__xss).toBeUndefined();
    expect(cmt.find("[onmouseover]").length).toBe(0);
  });

  it("does not emit an anchor pointing at a javascript: url", () => {
    const cmt = showComment(`<a href="javascript:window.__xss=1">click</a>`);
    expect(cmt.find("a").length).toBe(0);
    expect(cmt.find("[href]").length).toBe(0);
    expect(cmt.text()).toBe(`<a href="javascript:window.__xss=1">click</a>`);
  });

  it("does not linkify a bare javascript: url", () => {
    const cmt = showComment(`javascript:window.__xss=1`);
    expect(cmt.find("a").length).toBe(0);
    expect(cmt.text()).toBe("javascript:window.__xss=1");
  });

  it("does not create an element for any tag the comment contains", () => {
    const cmt = showComment(`<img src=x onerror="window.__xss=1"><b>x</b>`);
    expect(cmt.find("img, b").length).toBe(0);
    expect(window.__xss).toBeUndefined();
  });

  it("shows markup in a comment as the literal text it is", () => {
    const cmt = showComment(`<b>bold</b>`);
    expect(cmt.text()).toBe("<b>bold</b>");
  });

  // Linkification is the reason this field was ever rendered as HTML, so it stays.
  it("turns a bare http url into a working link", () => {
    const cmt = showComment("http://example.com/x");
    const a = cmt.find("a");
    expect(a.length).toBe(1);
    expect(a.attr("href")).toBe("http://example.com/x");
    expect(a.attr("target")).toBe("_blank");
    expect(a.text()).toBe("http://example.com/x");
    expect(cmt.text()).toBe("http://example.com/x");
  });

  it("keeps the text around a url", () => {
    const cmt = showComment("see https://example.com/x for details");
    expect(cmt.find("a").attr("href")).toBe("https://example.com/x");
    expect(cmt.text()).toBe("see https://example.com/x for details");
  });

  it("links every url in the comment, not just the first", () => {
    const cmt = showComment("http://a.example/1 and http://b.example/2");
    expect(cmt.find("a").length).toBe(2);
    expect(cmt.find("a").eq(1).attr("href")).toBe("http://b.example/2");
  });

  it("leaves a plain comment as plain text", () => {
    const cmt = showComment("Ripped by someone. Enjoy! (2 & 3) <not a tag>");
    expect(cmt.find("a").length).toBe(0);
    expect(cmt.text()).toBe("Ripped by someone. Enjoy! (2 & 3) <not a tag>");
  });

  it("renders an empty comment as nothing", () => {
    const cmt = showComment("");
    expect(cmt.text()).toBe("");
    expect(cmt.children().length).toBe(0);
  });

  it("does not let a quote after a url smuggle an attribute", () => {
    const cmt = showComment(`http://example.com/x' onmouseover='window.__xss=1`);
    cmt.find("*").each((_, el) => el.dispatchEvent(new MouseEvent("mouseover")));
    expect(window.__xss).toBeUndefined();
    expect(cmt.find("[onmouseover]").length).toBe(0);
    expect(cmt.find("a").attr("href")).toBe("http://example.com/x");
  });
});
