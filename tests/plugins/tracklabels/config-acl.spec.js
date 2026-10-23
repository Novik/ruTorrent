import { readFileSync } from "fs";

function loadTracklabels(canChangeColumns) {
  window.__tracklabels = {
    loadLang() {},
    canChangeColumns: () => canChangeColumns,
  };
  const script = document.createElement("script");
  script.textContent = "(function(plugin) {\n" +
    readFileSync("../plugins/tracklabels/init.js", "utf-8") +
    "\n})(window.__tracklabels);";
  document.body.appendChild(script);
}

describe("tracklabels configuration ACL", () => {
  let coreConfig;
  let events;

  beforeEach(() => {
    document.body.innerHTML = "";
    events = [];
    coreConfig = jest.fn(function(settings) {
      this.configured = settings;
      events.push(["core", theWebUI.tables.trt.columns.map(column => column.id)]);
    });
    window.$type = value => typeof value;
    window.TYPE_STRING = 0;
    window.theUILang = { All: "All", Tracker: "Tracker" };
    window.theWebUI = {
      categoryList: {
        refreshPanel: { plabel: attribs => attribs },
        contextMenuEntries: () => [],
      },
      tables: { trt: { columns: [{ id: "name" }] } },
      config: coreConfig,
      addTrackers() {},
      getTrackerName() {},
    };
    window.theRequestManager = {
      addRequest: jest.fn(() => {
        events.push(["request"]);
        return "tracker-request";
      }),
    };
  });

  it.each([false, true])("configures the core exactly once with canChangeColumns=%s", allowed => {
    loadTracklabels(allowed);
    const receiver = {};
    const settings = { restricted: !allowed };
    const continuation = { ready: true };

    expect(theWebUI.config.call(receiver, settings, continuation)).toBeUndefined();

    expect(coreConfig).toHaveBeenCalledTimes(1);
    expect(receiver.configured).toBe(settings);
    expect(coreConfig.mock.contexts[0]).toBe(receiver);
    expect(coreConfig).toHaveBeenCalledWith(settings, continuation);
    expect(events).toEqual(allowed
      ? [["core", ["name", "tracker"]], ["request"]]
      : [["core", ["name"]]]);
    expect(theWebUI.tables.trt.columns.map(column => column.id)).toEqual(allowed
      ? ["name", "tracker"] : ["name"]);
    expect(theRequestManager.addRequest).toHaveBeenCalledTimes(allowed ? 1 : 0);
    expect(window.__tracklabels.reqId).toBe(allowed ? "tracker-request" : undefined);
  });

  it.each([false, true])("propagates a core configuration failure with canChangeColumns=%s", allowed => {
    loadTracklabels(allowed);
    const failure = new Error("configuration failed");
    coreConfig.mockImplementation(() => { throw failure; });

    expect(() => theWebUI.config()).toThrow(failure);

    expect(coreConfig).toHaveBeenCalledTimes(1);
    expect(theRequestManager.addRequest).not.toHaveBeenCalled();
  });
});
