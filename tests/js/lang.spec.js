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
    const english = new Set(Object.keys(keysOf([coreEn])));
    const unknown = [];
    for (const name of fs.readdirSync(path.join(ROOT, "lang"))) {
      if (!name.endsWith(".js") || name === "en.js") continue;
      const file = path.join(ROOT, "lang", name);
      for (const key of Object.keys(keysOf([file]))) {
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

  it("keeps every plugin translation to the keys that plugin's en.js defines", () => {
    const unknown = [];
    for (const file of pluginEn) {
      const dir = path.dirname(file);
      const english = new Set(Object.keys(keysOf([file])));
      for (const name of fs.readdirSync(dir)) {
        if (!name.endsWith(".js") || name === "en.js") continue;
        for (const key of Object.keys(keysOf([path.join(dir, name)]))) {
          if (!english.has(key)) {
            unknown.push(path.relative(ROOT, path.join(dir, name)) + " -> " + key);
          }
        }
      }
    }
    expect(unknown).toEqual([]);
  });
});
