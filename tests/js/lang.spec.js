const fs = require("fs");
const path = require("path");
const vm = require("vm");

/**
 * Every string the interface shows comes from theUILang, which is built in two
 * steps: lang/en.js creates the object, and each plugin's lang/en.js adds to
 * the object that already exists. A key referenced but never defined does not
 * throw -- it is undefined, and jQuery's .text(undefined) is a *getter*, so the
 * element silently keeps whatever it held before. That is how a confirmation
 * dialog can end up showing the previous dialog's title.
 *
 * These checks are a net, not a proof: a dozen call sites build the key at
 * runtime (theUILang["addTorrent" + result], theUILang[`mnu_${id}`]) and no
 * static reading can resolve those.
 */

const ROOT = path.join(__dirname, "..", "..");
const readLang = (file) => fs.readFileSync(file, "utf8").replace(/^﻿/, "");

function keysOf(files) {
  const context = vm.createContext({
    theUILang: {},
    thePlugins: { get: () => ({ langLoaded() {} }) },
  });
  for (const file of files) {
    vm.runInContext(readLang(file), context, { filename: file });
  }
  return context.theUILang;
}

/**
 * A language file is not flat. geoip hangs 251 country names off
 * theUILang.country, scheduler hangs the weekday names off two arrays, and
 * history hangs four notification messages off theUILang.notifTip. Compared
 * by top-level key those are one key each, so a translation could lose every
 * country name but one and still look complete.
 *
 * Flatten to "country.de" and compare there instead. Arrays are walked the
 * same way as objects: theUILang.country is built with new Array() and keyed
 * by country code, and the weekday arrays are keyed 0..6.
 */
function leafEntries(value, prefix = "", out = new Map()) {
  for (const key of Object.keys(value)) {
    const child = value[key];
    const name = prefix ? `${prefix}.${key}` : key;
    if (child !== null && typeof child === "object") leafEntries(child, name, out);
    else out.set(name, child);
  }
  return out;
}

/**
 * A blank translation is worse than an untranslated one: the element is set
 * to the empty string and shows nothing, with no hint of which string is
 * gone. en.js has one deliberately blank value of its own -- the notification
 * tip for the state where the browser has already granted permission and
 * there is nothing to say -- so blankness is only a fault where English has
 * text.
 */
function shortfall(en, theirs, label, out) {
  for (const [key, english] of en) {
    if (!theirs.has(key)) {
      out.push(`${label}: ${key}`);
      continue;
    }
    const translated = theirs.get(key);
    if (typeof english === "string" && english.trim() !== "" &&
        typeof translated === "string" && translated.trim() === "")
      out.push(`${label}: ${key} is blank`);
  }
}

const coreEn = path.join(ROOT, "lang", "en.js");
const pluginEn = fs
  .readdirSync(path.join(ROOT, "plugins"))
  .map((name) => path.join(ROOT, "plugins", name, "lang", "en.js"))
  .filter((file) => fs.existsSync(file));

function sourceFiles() {
  const found = [];
  const walk = (dir) => {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        if (entry.name !== "node_modules" && entry.name !== "lang") walk(full);
      } else if (entry.name.endsWith(".js")) {
        found.push(full);
      }
    }
  };
  walk(path.join(ROOT, "js"));
  walk(path.join(ROOT, "plugins"));
  return found;
}

describe("localization", () => {
  it("defines every key the interface asks for by name", () => {
    // A plugin may use a key another plugin defines -- the mobile plugin
    // renders values whose labels belong to ratio, geoip and others -- so the
    // set to check against is core plus every plugin, which is what a running
    // instance has.
    const defined = new Set(Object.keys(keysOf([coreEn, ...pluginEn])));
    const missing = [];
    for (const file of sourceFiles()) {
      const source = fs.readFileSync(file, "utf8");
      for (const match of source.matchAll(/theUILang\.([A-Za-z_][A-Za-z0-9_]*)/g)) {
        if (!defined.has(match[1])) {
          missing.push(path.relative(ROOT, file) + " -> theUILang." + match[1]);
        }
      }
    }
    expect(missing).toEqual([]);
  });

  it("keeps every translation to the keys en.js defines", () => {
    // A key here that en.js does not have is a typo or a leftover: nothing
    // reads it, and it will never be shown.
    const english = new Set(leafEntries(keysOf([coreEn])).keys());
    const unknown = [];
    for (const name of fs.readdirSync(path.join(ROOT, "lang"))) {
      if (!name.endsWith(".js") || name === "en.js") continue;
      const file = path.join(ROOT, "lang", name);
      for (const key of leafEntries(keysOf([file])).keys()) {
        if (!english.has(key)) unknown.push("lang/" + name + " -> " + key);
      }
    }
    expect(unknown).toEqual([]);
  });

  it("defines persistent settings-save recovery guidance in every core language", () => {
    const missing = [];
    for (const name of fs.readdirSync(path.join(ROOT, "lang"))) {
      if (!name.endsWith(".js") || name === "langs.js") continue;
      const value = keysOf([path.join(ROOT, "lang", name)]).Settings_save_indeterminate;
      if (typeof value !== "string" || value.trim() === "") missing.push("lang/" + name);
    }
    expect(missing).toEqual([]);
  });

  /**
   * The mirror of the two checks above. A key en.js defines but a translation
   * does not is not a visible fallback: theUILang[key] is undefined, and
   * jQuery's .text(undefined) is a *getter*, so the element keeps whatever it
   * held before. That is how the check_port plugin showed a stale label to
   * every non-English user (Novik#3241), and how five settings strings this
   * repository added itself went missing from all 26 core languages.
   *
   * A language that has not been translated yet carries the English text, so
   * the key is present and the interface reads -- in English -- rather than
   * silently keeping the previous string.
   *
   * Compared leaf by leaf, so that a nested group counts as its members and
   * not as one key.
   */
  it("defines every key of core en.js in every core language", () => {
    const en = leafEntries(keysOf([coreEn]));
    const missing = [];
    for (const name of fs.readdirSync(path.join(ROOT, "lang"))) {
      if (!name.endsWith(".js") || name === "en.js" || name === "langs.js") continue;
      const theirs = leafEntries(keysOf([path.join(ROOT, "lang", name)]));
      shortfall(en, theirs, `lang/${name}`, missing);
    }
    expect(missing).toEqual([]);
  });

  it("defines every key of a plugin's en.js in that plugin's other languages", () => {
    const missing = [];
    for (const file of pluginEn) {
      const dir = path.dirname(file);
      const en = leafEntries(keysOf([file]));
      for (const name of fs.readdirSync(dir)) {
        if (!name.endsWith(".js") || name === "en.js") continue;
        const theirs = leafEntries(keysOf([path.join(dir, name)]));
        shortfall(en, theirs, `${path.relative(ROOT, dir)}/${name}`, missing);
      }
    }
    expect(missing).toEqual([]);
  });

  /**
   * Two plugins put a rules editor behind the same addon menu, and both used to
   * title it "Rules Manager" -- so the window said nothing about which one had
   * opened (Novik#3237). Fixing en.js alone leaves every other language with the
   * collision, and a title is only useful in the language the user reads.
   */
  it("gives the ratio and RSS rules dialogs distinct titles in every language", () => {
    const same = [];
    const dir = path.join(ROOT, "plugins", "extratio", "lang");
    for (const name of fs.readdirSync(dir)) {
      if (!name.endsWith(".js")) continue;
      const ratioFile = path.join(dir, name);
      const rssFile = path.join(ROOT, "plugins", "rssurlrewrite", "lang", name);
      if (!fs.existsSync(rssFile)) continue;
      const ratio = keysOf([ratioFile]).ratioRulesManager;
      const rss = keysOf([rssFile]).rssRulesManager;
      // Case and separators do not distinguish one window from another.
      const plain = (s) => String(s).toLowerCase().replace(/[\s\-_]/g, "");
      if (plain(ratio) === plain(rss)) same.push(`${name}: ${ratio}`);
    }
    expect(same).toEqual([]);
  });

  it("keeps every plugin translation to the keys that plugin's en.js defines", () => {
    const unknown = [];
    for (const file of pluginEn) {
      const dir = path.dirname(file);
      const english = new Set(leafEntries(keysOf([file])).keys());
      for (const name of fs.readdirSync(dir)) {
        if (!name.endsWith(".js") || name === "en.js") continue;
        for (const key of leafEntries(keysOf([path.join(dir, name)])).keys()) {
          if (!english.has(key)) {
            unknown.push(path.relative(ROOT, path.join(dir, name)) + " -> " + key);
          }
        }
      }
    }
    expect(unknown).toEqual([]);
  });
});
