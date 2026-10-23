const fs = require("fs");
const path = require("path");
const vm = require("vm");

/**
 * lang/langs.js picks one of the 29 files in lang/ and loads it. Only en.js
 * is guaranteed to define every key: the other 28 are translations of it, so
 * a key that reaches English alone is read as undefined everywhere else and
 * shown as the literal string "undefined".
 *
 * English is therefore loaded underneath the chosen language. Each file
 * assigns theUILang rather than adding to it, so what is tested here is that
 * the second assignment does not simply discard the first.
 */

const ROOT = path.join(__dirname, "..", "..");
const source = fs
  .readFileSync(path.join(ROOT, "lang", "langs.js"), "utf8")
  .replace(/^\uFEFF/, "");

/**
 * Stands in for the browser's script loading. A classic script fires its load
 * event at the end of its own execution, so running the file and then calling
 * onload is the order the real thing uses; a file that is not in `files` is a
 * 404 and fires onerror instead.
 */
function load(language, files) {
  const requested = [];
  const context = vm.createContext({});
  context.navigator = { language };
  context.document = {
    cookie: "",
    readyState: "complete",
    documentElement: { setAttribute() {} },
    querySelectorAll: () => [],
    createElement: () => ({}),
    head: {
      appendChild(el) {
        const code = /\/lang\/(.+)\.js\?/.exec(el.src)[1];
        requested.push(code);
        if (!(code in files)) {
          if (el.onerror) el.onerror();
          return;
        }
        vm.runInContext(files[code], context, { filename: `lang/${code}.js` });
        el.onload();
      },
    },
  };
  vm.runInContext(source, context, { filename: "lang/langs.js" });

  let finished = 0;
  context.loadUILang(() => finished++);
  return { lang: context.theUILang, requested, finished };
}

const file = (obj) => "var theUILang = " + JSON.stringify(obj) + ";";
const realFile = (name) =>
  fs.readFileSync(path.join(ROOT, "lang", name), "utf8").replace(/^\uFEFF/, "");

describe("language fallback", () => {
  it("shows English for a key the chosen language does not define", () => {
    const { lang } = load("es-ES", {
      en: file({ Downloads: "Downloads", NewKey: "Only English has this" }),
      es: file({ Downloads: "Descargas" }),
    });
    expect(lang.NewKey).toBe("Only English has this");
  });

  it("keeps the chosen language where it does define the key", () => {
    const { lang } = load("es-ES", {
      en: file({ Downloads: "Downloads", NewKey: "Only English has this" }),
      es: file({ Downloads: "Descargas" }),
    });
    expect(lang.Downloads).toBe("Descargas");
  });

  it("never leaves a key undefined that English defines", () => {
    const { lang } = load("es-ES", {
      en: file({ a: "A", b: "B", c: "C" }),
      es: file({ b: "Be" }),
    });
    expect(Object.keys(lang).filter((k) => lang[k] === undefined)).toEqual([]);
    expect(lang).toEqual({ a: "A", b: "Be", c: "C" });
  });

  it("fills a group of strings member by member", () => {
    // MoveView, retryOnErrorList and speedGraphDurationOptions each hold a
    // group. Half a group translated must not cost the other half.
    const { lang } = load("es-ES", {
      en: file({ MoveView: { base: "Move view", top: "To top", up: "Up" } }),
      es: file({ MoveView: { base: "Mover vista" } }),
    });
    expect(lang.MoveView).toEqual({ base: "Mover vista", top: "To top", up: "Up" });
  });

  it("asks for one file and does not merge when English is the language", () => {
    const { lang, requested } = load("en-US", {
      en: file({ Downloads: "Downloads" }),
    });
    expect(requested).toEqual(["en"]);
    expect(lang).toEqual({ Downloads: "Downloads" });
  });

  it("loads English first, then the chosen language", () => {
    const { requested } = load("de-DE", {
      en: file({ a: "A" }),
      de: file({ a: "Ah" }),
    });
    expect(requested).toEqual(["en", "de"]);
  });

  it("falls back to English when the language file cannot be fetched", () => {
    const { lang, finished } = load("sv-SE", { en: file({ a: "A", b: "B" }) });
    expect(lang).toEqual({ a: "A", b: "B" });
    expect(finished).toBe(1);
  });

  it("still reports the interface as ready exactly once", () => {
    const { finished } = load("es-ES", {
      en: file({ a: "A" }),
      es: file({ a: "Ah" }),
    });
    expect(finished).toBe(1);
  });

  it("gives a shipped language every key of the shipped English file", () => {
    const { lang } = load("es-ES", { en: realFile("en.js"), es: realFile("es.js") });
    const english = load("en-US", { en: realFile("en.js") }).lang;
    expect(Object.keys(english).filter((k) => !(k in lang))).toEqual([]);
    expect(lang.Downloads).toBe("Descargas");
  });
});
