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

const V_SOCKET_ALLOC = [
  ["0.16.19", 0x1013],
  ["0.16.21", 0x1015],
];

describe.each(V_SOCKET_ALLOC)("setsettings on rtorrent %s with adjustable socket allocation", (_name, iVersion) => {
  beforeEach(() => loadUI(iVersion));

  it("sets both bounds and recomputes for max open files", () => {
    expect(commandsFor("?action=setsettings&s=nmax_open_files&v=20000")).toStrictEqual([
      ["system.sockets.files.min_alloc"],
      ["system.sockets.files.max_alloc"],
      ["system.sockets.files.min_alloc.set", "string:", "i8:20000"],
      ["system.sockets.files.max_alloc.set", "string:", "i8:20000"],
      ["system.sockets.adjust_alloc"],
    ]);
  });

  it("sets both bounds and recomputes for max open http", () => {
    expect(commandsFor("?action=setsettings&s=nmax_open_http&v=1024")).toStrictEqual([
      ["system.sockets.http.min_alloc"],
      ["system.sockets.http.max_alloc"],
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
      ["system.sockets.files.min_alloc"],
      ["system.sockets.files.max_alloc"],
      ["system.sockets.http.min_alloc"],
      ["system.sockets.http.max_alloc"],
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
      ["system.sockets.files.min_alloc"],
      ["system.sockets.files.max_alloc"],
      ["system.sockets.files.min_alloc.set", "string:", "i8:20000"],
      ["system.sockets.files.max_alloc.set", "string:", "i8:20000"],
      ["throttle.max_uploads.set", "string:", "i8:50"],
      ["system.sockets.adjust_alloc"],
    ]);
  });
});

describe("direct setsettings refusal recovery", () => {
  beforeEach(() => loadUI(V_SOCKET_ALLOC[0][1]));
  afterEach(() => jest.restoreAllMocks());

  function value(v) {
    return `<value><array><data><value><i8>${v}</i8></value></data></array></value>`;
  }

  function fault(message) {
    return (
      "<value><struct>" +
      "<member><name>faultCode</name><value><i4>-501</i4></value></member>" +
      `<member><name>faultString</name><value><string>${message}</string></value></member>` +
      "</struct></value>"
    );
  }

  function multicallResponse(members) {
    return new DOMParser().parseFromString(
      '<?xml version="1.0"?><methodResponse><params><param><value><array><data>' +
        members.join("") +
        "</data></array></value></param></params></methodResponse>",
      "text/xml"
    );
  }

  it("writes zero for a cleared numeric setting but keeps a cleared string empty", () => {
    expect(commandsFor("?action=setsettings&s=nmax_uploads_global&v=")).toStrictEqual([
      ["throttle.max_uploads.global.set", "string:", "i8:0"],
    ]);
    expect(new rTorrentStub("?action=setsettings&s=nmax_uploads_global&v=").content).toContain("<i8>0</i8>");
    expect(commandsFor("?action=setsettings&s=sdirectory&v=")).toStrictEqual([
      ["directory.default.set", "string:", "string:"],
    ]);
  });

  it("restores both socket categories once after a fault and keeps the snapshot across responses", () => {
    const stub = new rTorrentStub(
      "?action=setsettings&s=nmax_open_files&v=20000&s=nmax_open_http&v=1024"
    );
    const restoreCalls = [];
    window.Ajax = jest.fn((uri, _async, complete) => {
      restoreCalls.push(uri);
      complete();
    });
    const afterFailure = jest.fn();
    stub.onSetsettingsFailure = afterFailure;

    stub.getResponse(multicallResponse([value(1024), value(4096), value(32), value(64)]));
    expect(afterFailure).not.toHaveBeenCalled();
    stub.getResponse(multicallResponse([value(0), value(0), value(0), value(0), fault("over budget")]));

    expect(restoreCalls).toHaveLength(1);
    expect(commandsFor(restoreCalls[0].URI)).toStrictEqual([
      ["system.sockets.files.min_alloc.set", "string:", "i8:1024"],
      ["system.sockets.files.max_alloc.set", "string:", "i8:4096"],
      ["system.sockets.http.min_alloc.set", "string:", "i8:32"],
      ["system.sockets.http.max_alloc.set", "string:", "i8:64"],
      ["system.sockets.adjust_alloc"],
    ]);
    expect(afterFailure).toHaveBeenCalledTimes(1);
  });

  it("does not invent a restore when a bound is faulted", () => {
    const stub = new rTorrentStub("?action=setsettings&s=nmax_open_files&v=20000");
    window.Ajax = jest.fn();
    stub.getResponse(multicallResponse([
      fault("missing min bound"), value(4096), value(0), value(0), fault("over budget"),
    ]));
    expect(window.Ajax).not.toHaveBeenCalled();
  });

  it("restores socket bounds when an unrelated batch member faults", () => {
    const stub = new rTorrentStub(
      "?action=setsettings&s=nmax_open_files&v=20000&s=nmax_uploads&v=50"
    );
    const restores = [];
    window.Ajax = jest.fn((request, _async, complete) => {
      restores.push(request);
      complete();
    });

    stub.getResponse(multicallResponse([
      value(1024), value(4096), value(0), value(0), fault("throttle refused"), value(0),
    ]));

    expect(restores).toHaveLength(1);
    expect(commandsFor(restores[0].URI)).toStrictEqual([
      ["system.sockets.files.min_alloc.set", "string:", "i8:1024"],
      ["system.sockets.files.max_alloc.set", "string:", "i8:4096"],
      ["system.sockets.adjust_alloc"],
    ]);
  });

  it("parses a direct XML-RPC fault before showing its one diagnostic or running success", () => {
    const deferred = $.Deferred();
    const headers = { getResponseHeader: () => null };
    jest.spyOn($, "ajax").mockReturnValue(deferred);
    const notice = jest.spyOn(window, "noty").mockImplementation(() => {});
    const success = jest.fn();

    Ajax("?action=setsettings&s=nmax_uploads_global&v=1", true, success);
    deferred.resolve(
      new DOMParser().parseFromString(
        "<methodResponse><fault><value><struct><member><name>faultCode</name><value><i4>-501</i4></value></member><member><name>faultString</name><value><string>denied</string></value></member></struct></value></fault></methodResponse>",
        "text/xml"
      ),
      "success",
      headers
    );

    expect(notice).toHaveBeenCalledTimes(1);
    expect(notice).toHaveBeenCalledWith(expect.stringContaining("denied"), "error");
    expect(success).not.toHaveBeenCalled();
  });

	it("reports one useful diagnostic for a setsettings batch with two faulted members", () => {
    const deferred = $.Deferred();
    const headers = { getResponseHeader: () => null };
    jest.spyOn($, "ajax").mockReturnValue(deferred);
    const notice = jest.spyOn(window, "noty").mockImplementation(() => {});

    Ajax("?action=setsettings&s=nmax_uploads_global&v=1", true);
    deferred.resolve(multicallResponse([fault("setter refused"), fault("allocation refused")]), "success", headers);

    expect(notice).toHaveBeenCalledTimes(1);
		expect(notice).toHaveBeenCalledWith(expect.stringContaining("setter refused"), "error");
	});

	it("keeps member diagnostics separate for other actions", () => {
		const deferred = $.Deferred();
		const headers = { getResponseHeader: () => null };
		jest.spyOn($, "ajax").mockReturnValue(deferred);
		const notice = jest.spyOn(window, "noty").mockImplementation(() => {});

		Ajax("?action=getsettings", true);
		deferred.resolve(multicallResponse([fault("first read refused"), fault("second read refused")]), "success", headers);

		expect(notice).toHaveBeenCalledTimes(2);
	});

	it("waits for the direct restore response before completing failure recovery", () => {
    const first = $.Deferred();
    const restore = $.Deferred();
    const headers = { getResponseHeader: () => null };
    jest.spyOn($, "ajax").mockImplementationOnce(() => first).mockImplementationOnce(() => restore);
    const recovered = jest.fn();
    const stub = new rTorrentStub("?action=setsettings&s=nmax_open_files&v=20000");
    stub.onSetsettingsFailure = recovered;

    Ajax(stub, true);
    first.resolve(
      multicallResponse([value(1024), value(4096), value(0), value(0), fault("over budget")]),
      "success",
      headers
    );
    expect(recovered).not.toHaveBeenCalled();

    restore.resolve(multicallResponse([value(0), value(0), value(0)]), "success", headers);
    expect(recovered).toHaveBeenCalledTimes(1);
  });

  it("starts reconciliation after the HTTP setsettings response fails", () => {
    const request = $.Deferred();
    jest.spyOn($, "ajax").mockReturnValue(request);
    const recovered = jest.fn();
    const stub = new rTorrentStub("?action=setsettings&s=nmax_open_files&v=20000");
    stub.onSetsettingsFailure = recovered;

    Ajax(stub, true);
    request.reject({ status: 500, responseText: "rolled back", getResponseHeader: () => null }, "error", "error");

    expect(recovered).toHaveBeenCalledTimes(1);
  });
});

describe("the options save request", () => {
	let optionsLoaded = false;
	let restoreHttprpc = null;
	afterEach(() => {
		if (restoreHttprpc) {
			restoreHttprpc();
			restoreHttprpc = null;
		}
		jest.restoreAllMocks();
	});

	function loadSettingsUI() {
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
    window.Timer = function () {};
    window.bootstrap = { Tab: { getOrCreateInstance: () => ({ show() {} }) } };
    window.AvailableLanguages = { en: "English" };
    window.GetActiveLanguage = () => "en";
    document.body.innerHTML = '<div id="stg_c"><div class="list-group"></div><div id="st_btns"></div></div>';

    for (const src of ["../js/common.js", "../js/webui.js", "../js/content.js", "../js/rtorrent.js"]) {
      let code = readFileSync(src, { encoding: "utf-8" });
      code = code.replace(/\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/, "");
      const scriptEl = document.createElement("script");
      scriptEl.textContent = code;
      document.body.appendChild(scriptEl);
    }
    if (!optionsLoaded) {
      const scriptEl = document.createElement("script");
      scriptEl.textContent = readFileSync("../js/options.js", { encoding: "utf-8" }).replace(
        /\n\$\(document\)\.ready\(function\(\)\n\{[\s\S]*?\n\}\);\s*$/,
        ""
      );
      document.body.appendChild(scriptEl);
      optionsLoaded = true;
    }
    theOptionsWindow.init();
		theWebUI.systemInfo = { rTorrent: { apiVersion: 24, iVersion: V_SOCKET_ALLOC[0][1], started: true } };
	}

	function addSettingsSaveButton() {
		const button = document.createElement("button");
		button.textContent = "Save";
		button.addEventListener("click", (event) => {
			event.preventDefault();
			event.stopPropagation();
			theWebUI.setSettings();
		});
		document.querySelector("#st_btns").append(button);
		return button;
	}

	function enableHttprpc() {
		const descriptors = Object.getOwnPropertyDescriptors(rTorrentStub.prototype);
		const mountPoint = theURLs.XMLRPCMountPoint;
		const scriptEl = document.createElement("script");
		scriptEl.textContent =
			"(function () { var plugin = { enabled: true };\n" +
			readFileSync("../plugins/httprpc/init.js", { encoding: "utf-8" }) +
			"\n})();";
		document.body.appendChild(scriptEl);
		restoreHttprpc = () => {
			for (const name of Object.getOwnPropertyNames(rTorrentStub.prototype)) {
				if (!(name in descriptors))
					delete rTorrentStub.prototype[name];
			}
			Object.defineProperties(rTorrentStub.prototype, descriptors);
			theURLs.XMLRPCMountPoint = mountPoint;
		};
	}

	function configureUISave() {
		theWebUI.configured = true;
		theWebUI.tables = {};
		theWebUI.activeView = "all";
	}

	function value(v) {
		return `<value><array><data><value><i8>${v}</i8></value></data></array></value>`;
	}

	function fault(message) {
		return (
			"<value><struct>" +
			"<member><name>faultCode</name><value><i4>-501</i4></value></member>" +
			`<member><name>faultString</name><value><string>${message}</string></value></member>` +
			"</struct></value>"
		);
	}

	function multicallResponse(members) {
		return new DOMParser().parseFromString(
			'<?xml version="1.0"?><methodResponse><params><param><value><array><data>' +
				members.join("") +
				"</data></array></value></param></params></methodResponse>",
			"text/xml"
		);
	}

	function topLevelFaultResponse(message) {
		return new DOMParser().parseFromString(
			"<methodResponse><fault><value><struct>" +
			"<member><name>faultCode</name><value><i4>-501</i4></value></member>" +
			`<member><name>faultString</name><value><string>${message}</string></value></member>` +
			"</struct></value></fault></methodResponse>",
			"text/xml"
		);
	}

  it("marks every other-limiting input numeric before serializing it", () => {
    loadSettingsUI();
    const ids = [
      "max_uploads_global",
      "max_downloads_global",
      "max_memory_usage",
      "max_open_files",
      "max_open_http",
    ];
    expect(ids.map((id) => $("#" + $.escapeSelector(id)).hasClass("num"))).toStrictEqual([
      true, true, true, true, true,
    ]);
    theWebUI.settings = Object.fromEntries(ids.map((id) => [id, 1]));
    ids.forEach((id) => $("#" + $.escapeSelector(id)).val("2"));
    const requests = [];
    theWebUI.request = function (request) { requests.push(request); };
    theWebUI.setSettings();
    expect(requests).toHaveLength(1);
    expect(requests[0].ss.sort()).toStrictEqual(ids.map((id) => "n" + id).sort());
  });

	it("disables the real Save button until a failed direct save finishes reconciliation", () => {
		loadSettingsUI();
		theWebUI.settings = { max_open_files: 1024 };
		$("#max_open_files").val("20000");
		const save = addSettingsSaveButton();
		const saveRequest = $.Deferred();
		const restoreRequest = $.Deferred();
		const refreshRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax")
			.mockImplementationOnce(() => saveRequest)
			.mockImplementationOnce(() => restoreRequest)
			.mockImplementationOnce(() => refreshRequest);
		const notice = jest.spyOn(window, "noty").mockImplementation(() => {});

		save.click();
		expect(save.disabled).toBe(true);
		expect(ajax).toHaveBeenCalledTimes(1);
		$("#max_open_files").val("30000");
		save.click();
		expect(ajax).toHaveBeenCalledTimes(1);

		saveRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), fault("over budget")]),
			"success",
			headers
		);
		expect(ajax).toHaveBeenCalledTimes(2);
		expect(save.disabled).toBe(true);
		restoreRequest.resolve(multicallResponse([value(0), value(0), value(0)]), "success", headers);
		expect(ajax).toHaveBeenCalledTimes(3);

		refreshRequest.resolve(multicallResponse([fault("getsettings refused")]), "success", headers);
		expect(notice).toHaveBeenCalledWith(expect.stringContaining("getsettings refused"), "error");
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
	});

	it("blocks repeated physical Save until the deferred reload-capable UI request settles", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.settings = { max_open_files: 1024, "webui.normalize_torrent_name": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const restoreRequest = $.Deferred();
		const refreshRequest = $.Deferred();
		const uiSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax")
			.mockImplementationOnce(() => setsettingsRequest)
			.mockImplementationOnce(() => restoreRequest)
			.mockImplementationOnce(() => refreshRequest)
			.mockImplementationOnce(() => uiSaveRequest);
		const reloadStates = [];
		const reload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {
			reloadStates.push({
				pending: theWebUI.settingsSavePending,
				disabled: save.disabled,
			});
		});
		jest.spyOn(theWebUI, "addSettings").mockImplementation(() => {});

		save.click();
		expect(ajax).toHaveBeenCalledTimes(1);
		expect(ajax.mock.calls[0][0].data).toContain("system.sockets.files.min_alloc");
		expect(save.disabled).toBe(true);
		expect(reload).not.toHaveBeenCalled();

		setsettingsRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), fault("over budget")]),
			"success",
			headers
		);
		expect(ajax).toHaveBeenCalledTimes(2);
		restoreRequest.resolve(multicallResponse([value(0), value(0), value(0)]), "success", headers);
		expect(ajax).toHaveBeenCalledTimes(3);
		expect(reload).not.toHaveBeenCalled();

		refreshRequest.resolve(multicallResponse([value(0)]), "success", headers);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);
		expect(ajax).toHaveBeenCalledTimes(4);
		expect(ajax.mock.calls[3][0].url).toBe(theURLs.SetSettingsURL);
		expect(reload).not.toHaveBeenCalled();

		$("#max_open_files").val("30000");
		const reopenedSave = addSettingsSaveButton();
		reopenedSave.click();
		expect(ajax).toHaveBeenCalledTimes(4);

		uiSaveRequest.resolve("", "success", headers);
		expect(reload).toHaveBeenCalledTimes(1);
		expect(reloadStates).toStrictEqual([{ pending: true, disabled: true }]);
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
		expect(reopenedSave.disabled).toBe(false);
	});

	it("releases the Save lock after a deferred reload callback throws", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.settings = { max_open_files: 1024, "webui.normalize_torrent_name": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const uiSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		jest.spyOn($, "ajax")
			.mockImplementationOnce(() => setsettingsRequest)
			.mockImplementationOnce(() => uiSaveRequest);
		jest.spyOn(theWebUI, "reload").mockImplementation(() => {
			expect(theWebUI.settingsSavePending).toBe(true);
			expect(save.disabled).toBe(true);
			throw new Error("reload failed");
		});

		save.click();
		setsettingsRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), value(0)]),
			"success",
			headers
		);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);

		expect(() => uiSaveRequest.resolve("", "success", headers)).toThrow("reload failed");
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
	});

	it("releases the Save lock after a deferred non-reload success", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.settings = { max_open_files: 1024, "webui.ignore_timeouts": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.ignore_timeouts")).prop("checked", true);
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const uiSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax")
			.mockImplementationOnce(() => setsettingsRequest)
			.mockImplementationOnce(() => uiSaveRequest);
		const reload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {});

		save.click();
		setsettingsRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), value(0)]),
			"success",
			headers
		);
		expect(ajax).toHaveBeenCalledTimes(2);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);

		uiSaveRequest.resolve("", "success", headers);
		expect(reload).not.toHaveBeenCalled();
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
	});

	it("releases without reload after a deferred UI HTTP error", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.settings = { max_open_files: 1024, "webui.normalize_torrent_name": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const uiSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		jest.spyOn($, "ajax")
			.mockImplementationOnce(() => setsettingsRequest)
			.mockImplementationOnce(() => uiSaveRequest);
		const diagnostic = jest.spyOn(theWebUI, "error").mockImplementation(() => {});
		const reload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {});

		save.click();
		setsettingsRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), value(0)]),
			"success",
			headers
		);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);
		uiSaveRequest.reject(
			{ status: 500, responseText: "UI save refused", getResponseHeader: () => null },
			"error",
			"error"
		);

		expect(diagnostic).toHaveBeenCalledWith(expect.stringContaining("500"), "UI save refused");
		expect(reload).not.toHaveBeenCalled();
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
	});

	it("routes deferred UI HTTP 401 through one terminal error without reload", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.authExpired = false;
		theWebUI.settings = { max_open_files: 1024, "webui.normalize_torrent_name": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const uiSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax")
			.mockImplementationOnce(() => setsettingsRequest)
			.mockImplementationOnce(() => uiSaveRequest);
		const diagnostic = jest.spyOn(theWebUI, "error").mockImplementation(() => {});
		const savedReload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {});
		jest.spyOn(console, "error").mockImplementation(() => {});

		save.click();
		setsettingsRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), value(0)]),
			"success",
			headers
		);
		expect(ajax).toHaveBeenCalledTimes(2);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);

		uiSaveRequest.reject(
			{ status: 401, responseText: "UI session expired", getResponseHeader: () => null },
			"error",
			"error"
		);

		expect(diagnostic).toHaveBeenCalledTimes(1);
		expect(diagnostic).toHaveBeenCalledWith(expect.stringContaining("401"), "UI session expired");
		expect(savedReload).not.toHaveBeenCalled();
		expect(theWebUI.authExpired).toBe(false);
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
		expect(ajax).toHaveBeenCalledTimes(2);
	});

	it("keeps global auth handling for an unrelated HTTP 401 request", () => {
		loadSettingsUI();
		theWebUI.authExpired = false;
		const request = $.Deferred();
		const ajax = jest.spyOn($, "ajax").mockReturnValue(request);
		const diagnostic = jest.spyOn(theWebUI, "error").mockImplementation(() => {});
		jest.spyOn(console, "error").mockImplementation(() => {});

		theWebUI.request("?action=getsettings");
		request.reject(
			{ status: 401, responseText: "session expired", getResponseHeader: () => null },
			"error",
			"error"
		);

		expect(ajax).toHaveBeenCalledTimes(1);
		expect(theWebUI.authExpired).toBe(true);
		expect(diagnostic).not.toHaveBeenCalled();
	});

	it("releases without reload after a deferred UI timeout", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.settings = { max_open_files: 1024, "webui.normalize_torrent_name": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const uiSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		jest.spyOn($, "ajax")
			.mockImplementationOnce(() => setsettingsRequest)
			.mockImplementationOnce(() => uiSaveRequest);
		const diagnostic = jest.spyOn(theWebUI, "timeout").mockImplementation(() => {});
		const reload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {});

		save.click();
		setsettingsRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), value(0)]),
			"success",
			headers
		);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);
		uiSaveRequest.reject(
			{ status: 504, responseText: "UI save timed out", getResponseHeader: () => null },
			"timeout",
			"timeout"
		);

		expect(diagnostic).toHaveBeenCalledTimes(1);
		expect(reload).not.toHaveBeenCalled();
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
	});

	it("releases without reload after a deferred UI status-zero error", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.settings = { max_open_files: 1024, "webui.normalize_torrent_name": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const uiSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		jest.spyOn($, "ajax")
			.mockImplementationOnce(() => setsettingsRequest)
			.mockImplementationOnce(() => uiSaveRequest);
		const diagnostic = jest.spyOn(theWebUI, "error").mockImplementation(() => {});
		const reload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {});

		save.click();
		setsettingsRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), value(0)]),
			"success",
			headers
		);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);
		uiSaveRequest.reject(
			{ status: 0, responseText: "", getResponseHeader: () => null },
			"error",
			"error"
		);

		expect(diagnostic).toHaveBeenCalledWith(expect.stringContaining("0"), "");
		expect(reload).not.toHaveBeenCalled();
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
	});

	it("releases when the deferred UI save is an early configured-false no-op", () => {
		loadSettingsUI();
		let pendingWhenConfiguredWasChecked = null;
		Object.defineProperty(theWebUI, "configured", {
			configurable: true,
			get() {
				pendingWhenConfiguredWasChecked = theWebUI.settingsSavePending;
				return false;
			},
		});
		theWebUI.settings = { max_open_files: 1024, "webui.normalize_torrent_name": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax").mockReturnValue(setsettingsRequest);
		const reload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {});

		save.click();
		setsettingsRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), value(0)]),
			"success",
			headers
		);

		expect(ajax).toHaveBeenCalledTimes(1);
		expect(pendingWhenConfiguredWasChecked).toBe(true);
		expect(reload).not.toHaveBeenCalled();
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
	});

	it("keeps a WebUI-only reload-capable save immediate", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.settings = { "webui.normalize_torrent_name": 0 };
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const uiSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax").mockReturnValue(uiSaveRequest);
		const reload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {});

		theWebUI.setSettings();
		expect(ajax).toHaveBeenCalledTimes(1);
		expect(ajax.mock.calls[0][0].url).toBe(theURLs.SetSettingsURL);
		expect(theWebUI.settingsSavePending).not.toBe(true);
		expect(reload).not.toHaveBeenCalled();
		uiSaveRequest.resolve("", "success", headers);
		expect(reload).toHaveBeenCalledTimes(1);
	});

	it("fails closed when the initial setsettings outcome is indeterminate", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.settings = { max_open_files: 1024, "webui.normalize_torrent_name": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const ajax = jest.spyOn($, "ajax").mockReturnValue(setsettingsRequest);
		const notice = jest.spyOn(window, "noty").mockImplementation(() => {});
		const reload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {});

		save.click();
		expect(ajax).toHaveBeenCalledTimes(1);
		setsettingsRequest.reject(
			{ status: 0, responseText: "", getResponseHeader: () => null },
			"error",
			"error"
		);

		expect(ajax).toHaveBeenCalledTimes(1);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);
		expect(reload).not.toHaveBeenCalled();
		expect(notice).toHaveBeenCalledTimes(1);
		expect(notice).toHaveBeenCalledWith(expect.stringMatching(/outcome is unknown/i), "error");
		expect(notice).toHaveBeenCalledWith(expect.stringMatching(/Save remains locked/), "error");
		expect(notice).toHaveBeenCalledWith(expect.stringMatching(/reload.*after rTorrent responds/i), "error");
	});

	it("unlocks after getsettings returns an HTTP-200 XML-RPC fault", () => {
		loadSettingsUI();
		theWebUI.settings = { max_open_files: 1024 };
		$("#max_open_files").val("20000");
		const save = addSettingsSaveButton();
		const saveRequest = $.Deferred();
		const restoreRequest = $.Deferred();
		const refreshRequest = $.Deferred();
		const laterSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax")
			.mockImplementationOnce(() => saveRequest)
			.mockImplementationOnce(() => restoreRequest)
			.mockImplementationOnce(() => refreshRequest)
			.mockImplementationOnce(() => laterSaveRequest);
		const notice = jest.spyOn(window, "noty").mockImplementation(() => {});

		save.click();
		saveRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), fault("over budget")]),
			"success",
			headers
		);
		restoreRequest.resolve(multicallResponse([value(0), value(0), value(0)]), "success", headers);
		refreshRequest.resolve(
			new DOMParser().parseFromString(
				"<methodResponse><fault><value><struct>" +
				"<member><name>faultCode</name><value><i4>-501</i4></value></member>" +
				"<member><name>faultString</name><value><string>getsettings denied</string></value></member>" +
				"</struct></value></fault></methodResponse>",
				"text/xml"
			),
			"success",
			headers
		);

		expect(notice).toHaveBeenCalledWith(expect.stringContaining("getsettings denied"), "error");
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
		$("#max_open_files").val("4096");
		save.click();
		expect(ajax).toHaveBeenCalledTimes(4);
	});

	it("fails closed when the direct restore outcome is indeterminate", () => {
		loadSettingsUI();
		configureUISave();
		theWebUI.settings = { max_open_files: 1024, "webui.normalize_torrent_name": 0 };
		$("#max_open_files").val("20000");
		$("#" + $.escapeSelector("webui.normalize_torrent_name")).prop("checked", true);
		const save = addSettingsSaveButton();
		const saveRequest = $.Deferred();
		const restoreRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax")
			.mockImplementationOnce(() => saveRequest)
			.mockImplementationOnce(() => restoreRequest);
		const notice = jest.spyOn(window, "noty").mockImplementation(() => {});
		const reload = jest.spyOn(theWebUI, "reload").mockImplementation(() => {});

		save.click();
		saveRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), fault("over budget")]),
			"success",
			headers
		);
		restoreRequest.reject(
			{ status: 0, responseText: "", getResponseHeader: () => null },
			"timeout",
			"timeout"
		);
		expect(ajax).toHaveBeenCalledTimes(2);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);
		expect(reload).not.toHaveBeenCalled();
		expect(notice.mock.calls.filter(([message]) => /outcome is unknown/i.test(message))).toHaveLength(1);
		expect(notice).toHaveBeenCalledWith(expect.stringMatching(/Save remains locked/), "error");
	});

	it("reconciles once after a restore HTTP-200 XML-RPC fault and unlocks Save", () => {
		loadSettingsUI();
		theWebUI.settings = { max_open_files: 1024 };
		$("#max_open_files").val("20000");
		const save = addSettingsSaveButton();
		const saveRequest = $.Deferred();
		const restoreRequest = $.Deferred();
		const refreshRequest = $.Deferred();
		const laterSaveRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax")
			.mockImplementationOnce(() => saveRequest)
			.mockImplementationOnce(() => restoreRequest)
			.mockImplementationOnce(() => refreshRequest)
			.mockImplementationOnce(() => laterSaveRequest);
		const notice = jest.spyOn(window, "noty").mockImplementation(() => {});
		const addSettings = jest.spyOn(theWebUI, "addSettings").mockImplementation(() => {});

		save.click();
		saveRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), fault("over budget")]),
			"success",
			headers
		);
		expect(save.disabled).toBe(true);
		restoreRequest.resolve(topLevelFaultResponse("restore denied"), "success", headers);

		expect(notice).toHaveBeenCalledTimes(2);
		expect(notice).toHaveBeenCalledWith(expect.stringContaining("over budget"), "error");
		expect(notice).toHaveBeenCalledWith(expect.stringContaining("restore denied"), "error");
		expect(ajax).toHaveBeenCalledTimes(3);
		expect(save.disabled).toBe(true);
		refreshRequest.resolve(multicallResponse([value(0)]), "success", headers);
		expect(addSettings).toHaveBeenCalledTimes(1);
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
		$("#max_open_files").val("4096");
		save.click();
		expect(ajax).toHaveBeenCalledTimes(4);
	});

	it("reports a definitive restore HTTP failure and reconciles once", () => {
		loadSettingsUI();
		theWebUI.settings = { max_open_files: 1024 };
		$("#max_open_files").val("20000");
		const save = addSettingsSaveButton();
		const saveRequest = $.Deferred();
		const restoreRequest = $.Deferred();
		const refreshRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax")
			.mockImplementationOnce(() => saveRequest)
			.mockImplementationOnce(() => restoreRequest)
			.mockImplementationOnce(() => refreshRequest);
		const notice = jest.spyOn(window, "noty").mockImplementation(() => {});
		jest.spyOn(theWebUI, "addSettings").mockImplementation(() => {});

		save.click();
		saveRequest.resolve(
			multicallResponse([value(1024), value(4096), value(0), value(0), fault("over budget")]),
			"success",
			headers
		);
		restoreRequest.reject(
			{ status: 500, responseText: "restore refused", getResponseHeader: () => null },
			"error",
			"error"
		);

		expect(notice).toHaveBeenCalledWith(expect.stringContaining("restore refused"), "error");
		expect(ajax).toHaveBeenCalledTimes(3);
		expect(save.disabled).toBe(true);
		refreshRequest.resolve(multicallResponse([value(0)]), "success", headers);
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
	});

	it("uses enabled httprpc JSON forms and reconciles after a definitive HTTP refusal", () => {
		loadSettingsUI();
		enableHttprpc();
		theWebUI.settings = { max_open_files: 1024 };
		$("#max_open_files").val("20000");
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const refreshRequest = $.Deferred();
		const headers = { getResponseHeader: () => null };
		const ajax = jest.spyOn($, "ajax")
			.mockImplementationOnce(() => setsettingsRequest)
			.mockImplementationOnce(() => refreshRequest);
		jest.spyOn(window, "noty").mockImplementation(() => {});
		const addSettings = jest.spyOn(theWebUI, "addSettings").mockImplementation(() => {});

		save.click();
		expect(ajax).toHaveBeenCalledTimes(1);
		const setsettingsOptions = ajax.mock.calls[0][0];
		expect(setsettingsOptions.url).toBe("plugins/httprpc/action.php");
		expect(setsettingsOptions.dataType).toBe("json");
		expect(setsettingsOptions.contentType).toBe("application/x-www-form-urlencoded");
		expect(new URLSearchParams(setsettingsOptions.data).get("mode")).toBe("setsettings");
		expect(new URLSearchParams(setsettingsOptions.data).get("s")).toBe("nmax_open_files");

		setsettingsRequest.reject(
			{ status: 500, responseText: "allocation rejected after rollback", getResponseHeader: () => null },
			"error",
			"error"
		);
		expect(ajax).toHaveBeenCalledTimes(2);
		const refreshOptions = ajax.mock.calls[1][0];
		expect(refreshOptions.url).toBe("plugins/httprpc/action.php");
		expect(refreshOptions.dataType).toBe("json");
		expect(refreshOptions.contentType).toBe("application/x-www-form-urlencoded");
		expect(new URLSearchParams(refreshOptions.data).get("mode")).toBe("stg");
		expect(save.disabled).toBe(true);

		refreshRequest.resolve([], "success", headers);
		expect(addSettings).toHaveBeenCalledTimes(1);
		expect(theWebUI.settingsSavePending).toBe(false);
		expect(save.disabled).toBe(false);
	});

	it("keeps an enabled httprpc status-zero refusal indeterminate", () => {
		loadSettingsUI();
		enableHttprpc();
		theWebUI.settings = { max_open_files: 1024 };
		$("#max_open_files").val("20000");
		const save = addSettingsSaveButton();
		const setsettingsRequest = $.Deferred();
		const ajax = jest.spyOn($, "ajax").mockReturnValue(setsettingsRequest);
		const notice = jest.spyOn(window, "noty").mockImplementation(() => {});

		save.click();
		setsettingsRequest.reject(
			{ status: 0, responseText: "", getResponseHeader: () => null },
			"error",
			"error"
		);

		expect(ajax).toHaveBeenCalledTimes(1);
		expect(theWebUI.settingsSavePending).toBe(true);
		expect(save.disabled).toBe(true);
		expect(notice).toHaveBeenCalledTimes(1);
		expect(notice).toHaveBeenCalledWith(expect.stringMatching(/outcome is unknown/i), "error");
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
