import { readFileSync } from "fs";

window.$ = window.jQuery = require("jquery");
window.theUILang = {};

const coreEl = document.createElement("script");
coreEl.textContent = readFileSync("../js/common.js", { encoding: "utf-8" });
document.body.appendChild(coreEl);

const code = readFileSync("../plugins/mobile/init.js", { encoding: "utf-8" });
const markup = readFileSync("../plugins/mobile/mobile.html", { encoding: "utf-8" });

const UA = {
  windows: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36",
  iphone: "Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1",
  macintosh: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15",
};

const KEY = "mobile-ui";

let handlers;
let desktopConfig;
let desktopSetSettings;
let navigated;

// Runs init.js the way a page load does, ending in the plugin's own
// disableOthers() call. Returns the plugin and which UI it started.
function boot({ url = "/", ua = UA.windows, coarse = false, touchPoints = 0 } = {}) {
  window.history.replaceState(null, "", url);
  Object.defineProperty(window.navigator, "userAgent", { value: ua, configurable: true });
  Object.defineProperty(window.navigator, "maxTouchPoints", { value: touchPoints, configurable: true });
  window.matchMedia = (query) => ({
    matches: coarse && query === "(pointer: coarse) and (hover: none)",
    media: query,
    addEventListener() {},
  });
  delete window.jQuery.browser;

  handlers = [];
  desktopConfig = jest.fn();
  desktopSetSettings = jest.fn();
  navigated = [];
  window.thePlugins = { list: {}, get: () => undefined };
  window.dxSTable = function () {};
  window.theWebUI = { settings: {}, config: desktopConfig, setSettings: desktopSetSettings };
  window.theDialogManager = {
    addHandler: (id, type, handler) => handlers.push({ id, type, handler }),
  };
  window.theUILang = {
    Mobile_interface: "Interface",
    Mobile_auto: "Detect automatically",
    Mobile_desktop: "Desktop version",
    Mobile_mobile: "Mobile version",
  };
  window.__plugin = {
    path: "../plugins/mobile/",
    disable: jest.fn(),
    loadLang: jest.fn(),
  };

  const el = document.createElement("script");
  el.textContent = `(function () { var plugin = window.__plugin; ${code} })();`;
  document.body.appendChild(el);

  const plugin = window.__plugin;
  plugin.navigate = (target) => navigated.push(target);
  const started = window.theWebUI.config !== desktopConfig;
  // The two outcomes are exclusive: the mobile UI takes over config, the
  // desktop UI disables the plugin
  expect(started).toBe(!plugin.disable.mock.calls.length);
  return { plugin, ui: started ? "mobile" : "desktop" };
}

function stored() {
  return window.localStorage.getItem(KEY);
}

describe("mobile plugin: desktop or mobile UI", () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.body.innerHTML = "";
  });

  describe("detection, with nothing stored", () => {
    it("starts the mobile UI on a touch-primary device with a desktop user agent", () => {
      expect(boot({ coarse: true }).ui).toBe("mobile");
      expect(stored()).toBe(null);
    });

    it("starts the desktop UI with a mouse", () => {
      expect(boot().ui).toBe("desktop");
    });

    it("starts the mobile UI on a phone", () => {
      expect(boot({ ua: UA.iphone, coarse: true }).ui).toBe("mobile");
    });

    it("starts the mobile UI on an iPad that reports a Macintosh user agent", () => {
      expect(boot({ ua: UA.macintosh, touchPoints: 5 }).ui).toBe("mobile");
      expect(boot({ ua: UA.macintosh }).ui).toBe("desktop");
    });
  });

  describe("the mobile argument", () => {
    it("mobile=0 starts the desktop UI on a touch-primary device and remembers it", () => {
      expect(boot({ url: "/?mobile=0", coarse: true }).ui).toBe("desktop");
      expect(stored()).toBe("desktop");
      expect(boot({ url: "/", coarse: true }).ui).toBe("desktop");
    });

    it("mobile=1 starts the mobile UI with a mouse and remembers it", () => {
      expect(boot({ url: "/?mobile=1" }).ui).toBe("mobile");
      expect(stored()).toBe("mobile");
      expect(boot({ url: "/" }).ui).toBe("mobile");
    });

    it("mobile=0 leaves a stored mobile choice behind", () => {
      window.localStorage.setItem(KEY, "mobile");
      expect(boot({ url: "/?mobile=0" }).ui).toBe("desktop");
      expect(stored()).toBe("desktop");
    });

    it("is read among other arguments", () => {
      expect(boot({ url: "/index.html?a=b&mobile=0&c=d", coarse: true }).ui).toBe("desktop");
    });

    it.each(["2", "", "yes", "true", "10", "01"])("ignores mobile=%s", (value) => {
      expect(boot({ url: `/?mobile=${value}`, coarse: true }).ui).toBe("mobile");
      expect(boot({ url: `/?mobile=${value}` }).ui).toBe("desktop");
      expect(stored()).toBe(null);
    });

    it("is not matched inside another argument's name or value", () => {
      expect(boot({ url: "/?xmobile=1" }).ui).toBe("desktop");
      expect(boot({ url: "/?q=mobile=1" }).ui).toBe("desktop");
      expect(stored()).toBe(null);
    });
  });

  describe("the stored choice", () => {
    it("ignores a value it did not write", () => {
      window.localStorage.setItem(KEY, "tablet");
      expect(boot({ coarse: true }).ui).toBe("mobile");
      expect(boot().ui).toBe("desktop");
    });

    it("detects when the browser refuses storage, and still honours the argument", () => {
      const getItem = jest.spyOn(Storage.prototype, "getItem").mockImplementation(() => {
        throw new Error("SecurityError");
      });
      const setItem = jest.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
        throw new Error("QuotaExceededError");
      });
      try {
        expect(boot({ coarse: true }).ui).toBe("mobile");
        expect(boot({ url: "/?mobile=0", coarse: true }).ui).toBe("desktop");
      } finally {
        getItem.mockRestore();
        setItem.mockRestore();
      }
    });
  });

  describe("switchUI", () => {
    it("stores the choice and reloads without the argument or the page hash", () => {
      const { plugin } = boot({ url: "/rtorrent/?mobile=1&x=y#details" });
      plugin.switchUI("desktop");
      expect(stored()).toBe("desktop");
      expect(navigated).toEqual([window.location.origin + "/rtorrent/?x=y"]);
    });

    it("forgets the choice for null", () => {
      window.localStorage.setItem(KEY, "desktop");
      const { plugin } = boot({ url: "/rtorrent/" });
      plugin.switchUI(null);
      expect(stored()).toBe(null);
      expect(navigated).toEqual([window.location.origin + "/rtorrent/"]);
    });

    it("carries the choice in the address when the browser refuses storage", () => {
      const { plugin } = boot({ url: "/rtorrent/", coarse: true });
      const setItem = jest.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
        throw new Error("QuotaExceededError");
      });
      try {
        plugin.switchUI("desktop");
      } finally {
        setItem.mockRestore();
      }
      expect(navigated).toEqual([window.location.origin + "/rtorrent/?mobile=0"]);
    });
  });

  describe("the Desktop version button of the mobile UI", () => {
    it("switches to the desktop UI", () => {
      const { plugin } = boot({ url: "/rtorrent/#settings", coarse: true });
      document.body.innerHTML = markup;
      const button = document.getElementById("desktopVersion");
      expect(button).not.toBe(null);
      expect(button.closest("#globalSettings")).not.toBe(null);
      plugin.onLangLoaded();
      expect(button.textContent).toBe("Desktop version");
      button.click();
      expect(stored()).toBe("desktop");
      expect(navigated).toEqual([window.location.origin + "/rtorrent/"]);
    });
  });

  describe("the setting of the desktop UI", () => {
    function openSettings() {
      document.body.innerHTML =
        '<div id="st_gl"><div class="row"><div><select id="webui.lang"></select></div></div></div>';
      const show = handlers.filter((h) => h.id === "stg" && h.type === "beforeShow");
      expect(show.length).toBe(1);
      show[0].handler();
      return document.getElementById("mobile_ui");
    }

    it("is not offered while the mobile UI runs", () => {
      boot({ coarse: true });
      expect(handlers).toEqual([]);
    });

    it("offers detection, desktop and mobile, showing what is stored", () => {
      const { plugin } = boot();
      expect(plugin.loadLang).toHaveBeenCalled();
      let select = openSettings();
      expect(select.closest(".row")).not.toBe(null);
      expect($(select).find("option").map((i, o) => [[o.value, o.textContent]]).get()).toEqual([
        ["", "Detect automatically"],
        ["desktop", "Desktop version"],
        ["mobile", "Mobile version"],
      ]);
      expect(select.value).toBe("");

      window.localStorage.setItem(KEY, "desktop");
      select = openSettings();
      expect(document.querySelectorAll("#mobile_ui").length).toBe(1);
      expect(select.value).toBe("desktop");
    });

    it("switches to the mobile UI when saved with Mobile version", () => {
      boot({ url: "/rtorrent/?mobile=0", coarse: true });
      const select = openSettings();
      select.value = "mobile";
      window.theWebUI.setSettings();
      expect(desktopSetSettings).toHaveBeenCalled();
      expect(stored()).toBe("mobile");
      expect(navigated).toEqual([window.location.origin + "/rtorrent/"]);
    });

    it("goes back to detection when saved with Detect automatically", () => {
      window.localStorage.setItem(KEY, "desktop");
      boot({ url: "/rtorrent/", coarse: true });
      const select = openSettings();
      select.value = "";
      window.theWebUI.setSettings();
      expect(stored()).toBe(null);
      expect(navigated.length).toBe(1);
      // ...and the next load detects the touch device again
      expect(boot({ url: "/rtorrent/", coarse: true }).ui).toBe("mobile");
    });

    it("does not reload when the choice is unchanged", () => {
      window.localStorage.setItem(KEY, "desktop");
      boot({ url: "/rtorrent/" });
      openSettings();
      window.theWebUI.setSettings();
      expect(desktopSetSettings).toHaveBeenCalled();
      expect(navigated).toEqual([]);
    });

    it("waits for the settings save before leaving the page", () => {
      jest.useFakeTimers();
      try {
        boot({ url: "/rtorrent/" });
        const select = openSettings();
        select.value = "mobile";
        window.theWebUI.settingsSavePending = true;
        window.theWebUI.setSettings();
        jest.advanceTimersByTime(300);
        expect(navigated).toEqual([]);
        window.theWebUI.settingsSavePending = false;
        jest.advanceTimersByTime(100);
        expect(navigated.length).toBe(1);
      } finally {
        jest.useRealTimers();
      }
    });

    // The desktop UI keeps the lock for good after a save of unknown
    // outcome, with an alert asking for a manual reload. The next load is
    // on a device that would start the other UI without the stored choice.
    it.each([
      ["mobile", { url: "/rtorrent/" }, "mobile", false, "mobile"],
      ["desktop", { url: "/rtorrent/" }, "desktop", true, "desktop"],
      ["", { url: "/rtorrent/?mobile=0", coarse: true }, null, true, "mobile"],
    ])("stays while the save holds its lock; the next load applies %j", (choice, page, kept, coarse, ui) => {
      jest.useFakeTimers();
      try {
        boot(page);
        const select = openSettings();
        select.value = choice;
        window.theWebUI.settingsSavePending = true;
        window.theWebUI.setSettings();
        jest.advanceTimersByTime(60000);
        expect(navigated).toEqual([]);
        expect(stored()).toBe(kept);
      } finally {
        jest.useRealTimers();
      }
      expect(boot({ url: "/rtorrent/", coarse }).ui).toBe(ui);
    });

    it("leaves the page when a slow save releases its lock", () => {
      jest.useFakeTimers();
      try {
        boot({ url: "/rtorrent/" });
        const select = openSettings();
        select.value = "mobile";
        window.theWebUI.settingsSavePending = true;
        window.theWebUI.setSettings();
        jest.advanceTimersByTime(8000);
        expect(navigated).toEqual([]);
        window.theWebUI.settingsSavePending = false;
        jest.advanceTimersByTime(100);
        expect(navigated).toEqual([window.location.origin + "/rtorrent/"]);
      } finally {
        jest.useRealTimers();
      }
    });

    it("waits at most 5 seconds for other requests", () => {
      jest.useFakeTimers();
      try {
        boot({ url: "/rtorrent/" });
        const select = openSettings();
        select.value = "mobile";
        $.active = 1;
        window.theWebUI.setSettings();
        jest.advanceTimersByTime(4900);
        expect(navigated).toEqual([]);
        jest.advanceTimersByTime(100);
        expect(navigated.length).toBe(1);
      } finally {
        $.active = 0;
        jest.useRealTimers();
      }
    });
  });

  describe("translations", () => {
    it.each(["Mobile_interface", "Mobile_auto", "Mobile_desktop", "Mobile_mobile"])(
      "%s is in the English language file",
      (key) => {
        const en = readFileSync("../plugins/mobile/lang/en.js", { encoding: "utf-8" });
        expect(en).toMatch(new RegExp(`theUILang\\.${key}\\s*=\\s*"[^"]+"`));
      }
    );
  });
});
