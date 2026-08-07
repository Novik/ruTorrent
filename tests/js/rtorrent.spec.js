import { readFileSync } from "fs";
window.$ = require("jquery");
window.theWebUI = {
  settings: {
    "webui.needmessage": true,
  },

  showFlags: 0xffff,
  systemInfo: {
    rTorrent: {
      apiVersion: 10,
      iVersion: 0x908,
      started: true,
    },
  },
};

for (const src of [
  "../lang/en.js",
  "../js/common.js",
  "../js/content.js",
  "../js/rtorrent.js",
]) {
  const scriptEl = document.createElement("script");
  scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
  document.body.appendChild(scriptEl);
}
correctContent();

function h(char) {
  return Array.from({ length: 40 }, () => char).join("");
}

function loadXML(action) {
  const fileName = `xml-responses/${action}.xml`;
  const parser = new DOMParser();
  const doc = parser.parseFromString(readFileSync(fileName), "application/xml");
  if (doc.querySelector("parsererror")) {
    throw new Error(`Error parsing xml file: ${fileName}`);
  }
  return doc;
}

describe("xmlrpc calls", () => {
  it("should parse getprops response", () => {
    const stub = new rTorrentStub(`?action=getprops&hash=${h("A")}`);
    //console.log(stub.content);
    const ret = stub.getResponse(loadXML(stub.action));
    //console.log(ret);
    expect(ret).toStrictEqual({
      [h("A")]: {
        pex: "0",
        peers_max: "100",
        peers_min: "40",
        tracker_numwant: "-1",
        ulslots: "50",
        superseed: 0,
      },
    });
  });

  it("should parse gettotal response", () => {
    const stub = new rTorrentStub("?action=gettotal");
    //console.log(stub.content);
    const ret = stub.getResponse(loadXML(stub.action));
    //console.log(ret);
    expect(ret).toStrictEqual({ UL: 2222, DL: 1111, rateUL: 222, rateDL: 111 });
  });

  it("should parse getopen response", () => {
    const stub = new rTorrentStub("?action=getopen");
    //console.log(stub.content);
    const ret = stub.getResponse(loadXML(stub.action));
    //console.log(ret);
    expect(ret).toStrictEqual({ http: 1, sock: 22, fd: -1 });
  });

  it("should parse getsettings response", () => {
    const stub = new rTorrentStub("?action=getsettings");
    //console.log(stub.content);
    const ret = stub.getResponse(loadXML(stub.action));
    //console.log(ret);
    expect(ret.dht).toBe(1);
    expect(ret.directory).toBe("/downloads");
    expect(ret.session).toBe("/rtorrent-path/.session/");
    const ret2 = stub.getResponse(loadXML("getsettings-nodht"));
    //console.log(ret2);
    expect(ret2.dht).toBe(0);
    expect(ret2.directory).toBe("/downloads");
    expect(ret2.session).toBe("/rtorrent-path/.session/");
  });

  it("should parse getalltrackers response", () => {
    const stub = new rTorrentStub(
      `?action=getalltrackers&hash=${h("A")}&hash=${h("B")}`
    );
    //console.log(stub.content);
    const ret = stub.getResponse(loadXML(stub.action));
    //console.log(ret);
    expect(Object.keys(ret).length).toBe(2);
    expect(ret[h("A")][0].name).toBe(
      "http://sometracker.com/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx/announce"
    );
    expect(ret[h("B")][0].name).toBe(
      "http://sometracker2.com/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx/announce"
    );
  });

  it("should parse getfiles response", () => {
    const stub = new rTorrentStub(`?action=getfiles&hash=${h("A")}`);
    //console.log(stub.content);
    const ret = stub.getResponse(loadXML(stub.action));
    //console.log(ret);
    expect(Object.keys(ret).length).toBe(1);
    const size = 512 * 512;
    expect(ret[h("A")]).toStrictEqual([
      { name: "File 1", size, done: 0 * size, priority: "1" },
      { name: "File 2", size, done: 0.5 * size, priority: "2" },
      { name: "File 3", size, done: 1 * size, priority: "3" },
    ]);
  });

  it("should parse getpeers response", () => {
    const stub = new rTorrentStub(`?action=getpeers&hash=${h("A")}`);
    //console.log(stub.content);
    const ret = stub.getResponse(loadXML(stub.action));
    //console.log(ret);
    expect(Object.keys(ret).length).toBe(2);
    expect(ret[h("X")].ip).toBe("111.111.111.111");
    expect(ret[h("Y")].ip).toBe("222.222.222.222");
  });

  it("should parse gettrackers response", () => {
    const stub = new rTorrentStub(`?action=gettrackers&hash=${h("A")}`);
    //console.log(stub.content);
    const ret = stub.getResponse(loadXML(stub.action));
    //console.log(ret);
    expect(Object.keys(ret).length).toBe(1);
    expect(ret[h("A")][0].name).toBe(
      "https://tracker.site.com/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx/announce"
    );
    expect(ret[h("A")][1].name).toBe(
      "https://tracker.site2.com/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx/announce"
    );
  });

  it("should parse list response", () => {
    const stub = new rTorrentStub("?list=1");
    //console.log(stub.content);
    const ret = stub.getResponse(loadXML(stub.action));
    //console.log(ret);
    for (const [hash, label, comment] of [
      [h("A"), "Cat A", ""],
      [h("B"), "Cat A/Sec 1", ""],
      [h("C"), "Cat B/Sec 1", "some comment"],
      [h("D"), "Cat C", "https://site.com/content"],
    ]) {
      expect(ret.torrents[hash].name).toBe(`Name of ${hash}`);
      expect(ret.torrents[hash].save_path).toBe("/path/to/torrent");
      expect(ret.torrents[hash].label).toBe(label);
      expect(ret.torrents[hash].comment).toBe(comment);
    }
  });
});

describe("visibilitychange handler", () => {
  let hiddenDescriptor;

  beforeEach(() => {
    // Save original descriptor so we can restore it after each test
    hiddenDescriptor = Object.getOwnPropertyDescriptor(
      Document.prototype,
      "hidden"
    );
  });

  afterEach(() => {
    // Restore original document.hidden behavior
    delete document.hidden;
    if (hiddenDescriptor) {
      Object.defineProperty(Document.prototype, "hidden", hiddenDescriptor);
    }
    // Reset state
    theWebUI.deltaTime = 0;
    theWebUI.serverDeltaTime = 0;
  });

  it("should reset deltaTime and serverDeltaTime when tab becomes visible", () => {
    // Simulate cached deltas from a previous Ajax_UpdateTime call
    theWebUI.deltaTime = 5000;
    theWebUI.serverDeltaTime = 3000;

    // Mock document.hidden to return false (tab is now visible)
    Object.defineProperty(document, "hidden", {
      configurable: true,
      get: () => false,
    });

    document.dispatchEvent(new Event("visibilitychange"));

    expect(theWebUI.deltaTime).toBe(0);
    expect(theWebUI.serverDeltaTime).toBe(0);
  });

  it("should NOT reset deltas when tab becomes hidden", () => {
    theWebUI.deltaTime = 5000;
    theWebUI.serverDeltaTime = 3000;

    // Mock document.hidden to return true (tab is now hidden)
    Object.defineProperty(document, "hidden", {
      configurable: true,
      get: () => true,
    });

    document.dispatchEvent(new Event("visibilitychange"));

    // Values should remain unchanged
    expect(theWebUI.deltaTime).toBe(5000);
    expect(theWebUI.serverDeltaTime).toBe(3000);
  });
});
