import { readFileSync } from "fs";

window.$ = require("jquery");

const SOURCES = [
  "../lang/en.js",
  "../js/common.js",
  "../js/content.js",
  "../js/rtorrent.js",
];

// correctContent() builds theRequestManager.aliases by $.extend-ing one block per
// rtorrent version, so the alias table can only ever grow. Reload the sources for
// each version under test to get a table that matches that version exactly.
function loadUI(iVersion) {
  window.theWebUI = {
    settings: { "webui.needmessage": true },
    showFlags: 0xffff,
    systemInfo: { rTorrent: { apiVersion: 24, iVersion, started: true } },
  };
  for (const src of SOURCES) {
    const scriptEl = document.createElement("script");
    scriptEl.textContent = readFileSync(src, { encoding: "utf-8" });
    document.body.appendChild(scriptEl);
  }
  correctContent();
}

function commandsFor(query) {
  return new rTorrentStub(query).commands.map((cmd) => [
    cmd.command,
    ...cmd.params.map((prm) => `${prm.type}:${prm.value}`),
  ]);
}

// rtorrent 0.16.19.
const V_SOCKET_ALLOC = 0x1013;

describe("setsettings on rtorrent with adjustable socket allocation", () => {
  beforeEach(() => loadUI(V_SOCKET_ALLOC));

  it("sets both bounds and recomputes for max open files", () => {
    expect(commandsFor("?action=setsettings&s=nmax_open_files&v=20000")).toStrictEqual([
      ["system.sockets.files.min_alloc.set", "string:", "i8:20000"],
      ["system.sockets.files.max_alloc.set", "string:", "i8:20000"],
      ["system.sockets.adjust_alloc"],
    ]);
  });

  it("sets both bounds and recomputes for max open http", () => {
    expect(commandsFor("?action=setsettings&s=nmax_open_http&v=1024")).toStrictEqual([
      ["system.sockets.http.min_alloc.set", "string:", "i8:1024"],
      ["system.sockets.http.max_alloc.set", "string:", "i8:1024"],
      ["system.sockets.adjust_alloc"],
    ]);
  });

  // max_alloc alone is a ceiling and can only lower the allocation, so a raise
  // needs min_alloc too. Neither takes effect before adjust_alloc runs.
  it("emits adjust_alloc once at the end when both settings change together", () => {
    const commands = commandsFor(
      "?action=setsettings&s=nmax_open_files&v=20000&s=nmax_open_http&v=1024"
    );
    expect(commands).toStrictEqual([
      ["system.sockets.files.min_alloc.set", "string:", "i8:20000"],
      ["system.sockets.files.max_alloc.set", "string:", "i8:20000"],
      ["system.sockets.http.min_alloc.set", "string:", "i8:1024"],
      ["system.sockets.http.max_alloc.set", "string:", "i8:1024"],
      ["system.sockets.adjust_alloc"],
    ]);
    expect(commands.filter(([name]) => name === "system.sockets.adjust_alloc")).toHaveLength(1);
  });

  it("does not recompute when no socket setting changed", () => {
    expect(commandsFor("?action=setsettings&s=nmax_peers&v=100")).toStrictEqual([
      ["throttle.max_peers.normal.set", "string:", "i8:100"],
    ]);
  });

  it("keeps the dht setting on its own branch", () => {
    expect(commandsFor("?action=setsettings&s=ndht&v=0")).toStrictEqual([
      ["dht.mode.set", "string:", "string:disable"],
    ]);
    expect(commandsFor("?action=setsettings&s=ndht&v=1")).toStrictEqual([
      ["dht.mode.set", "string:", "string:auto"],
    ]);
  });

  it("leaves other settings in the batch untouched", () => {
    expect(
      commandsFor("?action=setsettings&s=nmax_open_files&v=20000&s=nmax_uploads&v=50")
    ).toStrictEqual([
      ["system.sockets.files.min_alloc.set", "string:", "i8:20000"],
      ["system.sockets.files.max_alloc.set", "string:", "i8:20000"],
      ["throttle.max_uploads.set", "string:", "i8:50"],
      ["system.sockets.adjust_alloc"],
    ]);
  });
});

describe("settings read-back", () => {
  function readCommands(iVersion) {
    loadUI(iVersion);
    return commandsFor("?action=getsettings").map(([name]) => name);
  }

  // max_alloc is only the ceiling and commonly sits orders of magnitude above
  // the allocation in use, so reading it back would misreport the limit.
  it("reads the allocation in effect once the socket manager owns the limits", () => {
    const commands = readCommands(0x1010);
    expect(commands).toContain("system.sockets.http.max_size");
    expect(commands).not.toContain("system.sockets.http.max_alloc");
    expect(commands).toContain("network.max_open_files");
  });

  it("keeps the legacy read-back commands on 0.9.8", () => {
    const commands = readCommands(0x908);
    expect(commands).toContain("network.max_open_files");
    expect(commands).toContain("network.http.max_open");
  });
});

// Sending these commands to an rtorrent that aborts on an over-budget
// adjust_alloc would kill the process, so older versions keep the old command.
describe("setsettings below the socket allocation version gate", () => {
  it("sends the single ceiling command on 0.16.18", () => {
    loadUI(0x1012);
    expect(commandsFor("?action=setsettings&s=nmax_open_files&v=20000")).toStrictEqual([
      ["system.sockets.files.max_alloc.set", "string:", "i8:20000"],
    ]);
  });

  it("sends the legacy command on 0.9.8", () => {
    loadUI(0x908);
    expect(commandsFor("?action=setsettings&s=nmax_open_files&v=20000")).toStrictEqual([
      ["network.max_open_files.set", "string:", "i8:20000"],
    ]);
    expect(commandsFor("?action=setsettings&s=nmax_open_http&v=1024")).toStrictEqual([
      ["network.http.max_open.set", "string:", "i8:1024"],
    ]);
  });
});
