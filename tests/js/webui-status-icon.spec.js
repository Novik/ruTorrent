import { readFileSync } from "fs";

window.$ = require("jquery");

function loadWebUI() {
  // theUILang answers every key with the key itself, so a status assertion
  // names the language key it expects rather than one language's wording.
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
  window.dStatus = { started: 1, paused: 2, checking: 4, hashing: 8, error: 16 };

  let code = readFileSync("../js/webui.js", { encoding: "utf-8" });
  code = code.replace(
    /\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/,
    ""
  );
  const scriptEl = document.createElement("script");
  scriptEl.textContent = code;
  document.body.appendChild(scriptEl);
}

describe("getStatusIcon", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
    loadWebUI();
  });

  it("calls a started torrent with its selected files done a partial seed", () => {
    expect(
      theWebUI.getStatusIcon({ state: 1, done: 180, partially_done: 1 })
    ).toEqual(["Status_Up", "PartialSeed"]);
  });

  it("treats a stopped partially downloaded torrent like a stopped completed one", () => {
    expect(
      theWebUI.getStatusIcon({ state: 0, done: 180, partially_done: 1 })
    ).toEqual(["Status_Completed", "Finished"]);
  });

  it("leaves an ordinary incomplete torrent downloading", () => {
    expect(
      theWebUI.getStatusIcon({ state: 1, done: 180, partially_done: 0 })
    ).toEqual(["Status_Down", "Downloading"]);
  });

  it("leaves an ordinary incomplete stopped torrent stopped", () => {
    expect(
      theWebUI.getStatusIcon({ state: 0, done: 180, partially_done: 0 })
    ).toEqual(["Status_Incompleted", "Stopped"]);
  });

  it("leaves a fully downloaded torrent seeding and completed", () => {
    expect(
      theWebUI.getStatusIcon({ state: 1, done: 1000, partially_done: 0 })
    ).toEqual(["Status_Up", "Seeding"]);
    expect(
      theWebUI.getStatusIcon({ state: 0, done: 1000, partially_done: 0 })
    ).toEqual(["Status_Completed", "Finished"]);
  });

  it("keeps a fully downloaded torrent seeding, though the daemon calls it partially done too", () => {
    // d.is_partially_done answers "every selected chunk is on disk", which a
    // complete torrent satisfies as well -- live rTorrent 0.16.20 returns 1 for
    // every completed torrent. Only the percentage separates the two cases.
    expect(
      theWebUI.getStatusIcon({ state: 1, done: 1000, partially_done: 1 })
    ).toEqual(["Status_Up", "Seeding"]);
    expect(
      theWebUI.getStatusIcon({ state: 0, done: 1000, partially_done: 1 })
    ).toEqual(["Status_Completed", "Finished"]);
  });

  it("falls back to the old behaviour when the daemon sends no value", () => {
    expect(theWebUI.getStatusIcon({ state: 1, done: 180 })).toEqual([
      "Status_Down",
      "Downloading",
    ]);
    expect(theWebUI.getStatusIcon({ state: 0, done: 180 })).toEqual([
      "Status_Incompleted",
      "Stopped",
    ]);
  });

  it("gives an errored partial seed the upload error icon", () => {
    expect(
      theWebUI.getStatusIcon({ state: 1 | 16, done: 180, partially_done: 1 })
    ).toEqual(["Status_Error_Up", "PartialSeed"]);
  });

  it("keeps checking ahead of everything else", () => {
    expect(
      theWebUI.getStatusIcon({ state: 1 | 4, done: 180, partially_done: 1 })
    ).toEqual(["Status_Checking", "Checking"]);
  });
});
