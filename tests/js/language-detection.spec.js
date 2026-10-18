const fs = require("fs");
const path = require("path");
const vm = require("vm");

/**
 * The browser tells the page one language tag, and lang/langs.js has to turn
 * it into one of the files in lang/. A tag it cannot place falls back to
 * English, which is silent: the interface simply comes up in the wrong
 * language and nothing is logged.
 *
 * The mapping is not always the file name. "nb" is Bokmal's ISO 639-1 code
 * and every Norwegian browser sends it, but the file is no.js. A tag that
 * carries a script -- "zh-Hant-TW" -- shares no two-letter prefix with any
 * file name. Those belong in LanguageAliases rather than in
 * AvailableLanguages, because AvailableLanguages is also the list the
 * settings dialog renders as the language selector, and a second entry there
 * is a second Norwegian in the dropdown.
 */

const ROOT = path.join(__dirname, "..", "..");
const source = fs.readFileSync(path.join(ROOT, "lang", "langs.js"), "utf8").replace(/^﻿/, "");

function load(language) {
  const context = vm.createContext({
    navigator: language === undefined ? {} : { language },
    document: { cookie: "" },
  });
  vm.runInContext(source, context, { filename: "lang/langs.js" });
  return context;
}

const detect = (language) => load(language).GetActiveLanguage();

describe("language detection", () => {
  // Every tag here is one a real browser sends: the probe ran chromium,
  // firefox and webkit with each of these locales and read navigator.language
  // back unchanged.
  it.each([
    ["en-US", "en"],
    ["de-AT", "de"],
    ["sv-FI", "sv"],
    ["sr-Latn-RS", "sr"],
    ["no-NO", "no"],
    ["nn-NO", "nn"],
    ["pt-PT", "pt-pt"],
    ["pt-BR", "pt-br"],
    ["zh-CN", "zh-cn"],
    ["zh-TW", "zh-tw"],
  ])("keeps reading %s as %s", (tag, expected) => {
    expect(detect(tag)).toBe(expected);
  });

  // Bokmal is written no.js here; ICU resolves pt to pt-BR, zh and zh-SG to
  // Simplified, and zh-HK and zh-MO to Traditional.
  it.each([
    ["nb", "no"],
    ["nb-NO", "no"],
    ["pt", "pt-br"],
    ["zh", "zh-cn"],
    ["zh-Hans-CN", "zh-cn"],
    ["zh-SG", "zh-cn"],
    ["zh-Hant-TW", "zh-tw"],
    ["zh-HK", "zh-tw"],
    ["zh-MO", "zh-tw"],
  ])("reads %s as %s instead of falling back to English", (tag, expected) => {
    expect(detect(tag)).toBe(expected);
  });

  // No Hebrew or Indonesian file exists, so English is the right answer and
  // there is nothing to alias.
  it.each(["he-IL", "iw", "id-ID", "in", "ga-IE", ""])(
    "falls back to English for %s, which has no translation",
    (tag) => {
      expect(detect(tag)).toBe("en");
    },
  );

  it("prefers the language the user picked over the browser's", () => {
    const context = vm.createContext({
      navigator: { language: "de-DE" },
      document: { cookie: "Language=fr; path=/" },
    });
    vm.runInContext(source, context, { filename: "lang/langs.js" });
    expect(context.GetActiveLanguage()).toBe("fr");
  });

  it("offers a file for every language the selector lists", () => {
    const { AvailableLanguages } = load("en-US");
    const missing = Object.keys(AvailableLanguages).filter(
      (code) => !fs.existsSync(path.join(ROOT, "lang", `${code}.js`)),
    );
    expect(missing).toEqual([]);
  });

  /**
   * js/options.js builds the language selector straight out of
   * AvailableLanguages, so an alias that also sat there would show the same
   * language twice -- which is why the aliases are a separate table.
   */
  it("keeps the aliases out of the selector and pointed at real files", () => {
    const { AvailableLanguages, LanguageAliases } = load("en-US");
    expect(Object.keys(LanguageAliases).filter((code) => code in AvailableLanguages)).toEqual([]);
    expect(
      Object.values(LanguageAliases).filter((target) => !(target in AvailableLanguages)),
    ).toEqual([]);
  });
});
