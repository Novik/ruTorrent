const fs = require("fs");
const path = require("path");
const vm = require("vm");

/**
 * php/getplugins.php diagnoses an installation by asking
 * Permission::doesUserHave() whether the rTorrent user reaches a path, and
 * shows a theUILang string when it does not. The string is the only
 * instruction the administrator gets, so it has to name the permissions the
 * guard actually demands: a string that asks for less than the check requires
 * sends the reader to grant a mode that leaves the error on screen.
 *
 * The flag argument is a POSIX mode nibble. Permission::isUserHavePrim shifts
 * it by 6, 3 or 0 for the owner, group and other class and masks it against
 * the mode, so 0x4 is read, 0x2 is write and 0x1 is execute, exactly as in
 * chmod.
 *
 * Both halves are read from the source here -- the flag from the call site and
 * the text from lang/en.js -- so neither can be changed on its own without the
 * other following.
 *
 * tests/js/lang.spec.js is the companion check and a different question: it
 * asks whether a key exists and is translated everywhere, not whether what it
 * says is true. Only English is checked here, because English is the string
 * the translations are made from.
 */

const ROOT = path.join(__dirname, "..", "..");

const MODE_BITS = [
  [0x4, "read"],
  [0x2, "write"],
  [0x1, "execute"],
];

function permissionsOf(flags) {
  const named = MODE_BITS.filter(([bit]) => flags & bit).map(([, name]) => name);
  // A bit outside the nibble is not a permission this mapping can describe,
  // and silently ignoring it would make the comparison meaningless.
  const unknown = flags & ~0x7;
  return { named, unknown };
}

/**
 * Pair each guard with the message it leads to. The call site is matched up to
 * its own closing parenthesis, and the message is the first theUILang key that
 * follows it before the next guard begins -- which is the shape of every
 * branch in getplugins.php, whether the body is a single statement or a block.
 */
function guardedMessages(file) {
  const source = fs.readFileSync(file, "utf8");
  const call = /Permission::doesUserHave\s*\([^()]*?0x([0-9A-Fa-f]+)\s*\)/g;
  const hits = [];
  for (let match; (match = call.exec(source)); ) {
    hits.push({
      flags: parseInt(match[1], 16),
      raw: match[1],
      start: match.index,
      end: match.index + match[0].length,
      line: source.slice(0, match.index).split("\n").length,
    });
  }
  return hits.map((hit, index) => {
    const limit = index + 1 < hits.length ? hits[index + 1].start : source.length;
    const key = /theUILang\.([A-Za-z_][A-Za-z0-9_]*)/.exec(source.slice(hit.end, limit));
    return { ...hit, key: key && key[1] };
  });
}

function englishStrings() {
  const context = vm.createContext({
    theUILang: {},
    thePlugins: { get: () => ({ langLoaded() {} }) },
  });
  const file = path.join(ROOT, "lang", "en.js");
  vm.runInContext(fs.readFileSync(file, "utf8").replace(/^\uFEFF/, ""), context, { filename: file });
  return context.theUILang;
}

/**
 * The permissions a message names: a run of read, write and execute joined by
 * slashes, in front of the word "access". A message with no such run names
 * nothing and cannot be checked, which is itself a fault rather than a pass.
 */
const SPOKEN = /\b((?:read|write|execute)(?:\s*\/\s*(?:read|write|execute))*)\s+access\b/i;

function spokenPermissions(text) {
  const match = SPOKEN.exec(text);
  if (!match) return null;
  return match[1].toLowerCase().split("/").map((word) => word.trim());
}

describe("permission diagnostics", () => {
  const guards = guardedMessages(path.join(ROOT, "php", "getplugins.php"));
  const lang = englishStrings();

  it("finds the permission guards it is meant to check", () => {
    // Without this the checks below would pass by describing nothing at all,
    // should the call sites ever be renamed or moved.
    expect(guards.length).toBeGreaterThan(0);
  });

  it("names in English every permission the guard requires", () => {
    const wrong = [];
    for (const guard of guards) {
      const at = `php/getplugins.php:${guard.line} (0x${guard.raw})`;
      if (!guard.key) {
        wrong.push(`${at}: guard shows no theUILang message`);
        continue;
      }
      const { named, unknown } = permissionsOf(guard.flags);
      if (unknown) {
        wrong.push(`${at}: flag bit 0x${unknown.toString(16)} is not a permission`);
        continue;
      }
      const text = lang[guard.key];
      if (typeof text !== "string") {
        wrong.push(`${at}: theUILang.${guard.key} is not defined in lang/en.js`);
        continue;
      }
      const spoken = spokenPermissions(text);
      if (!spoken) {
        wrong.push(`${at}: theUILang.${guard.key} names no permissions`);
        continue;
      }
      const sorted = (list) => [...list].sort().join("/");
      if (sorted(spoken) !== sorted(named)) {
        wrong.push(
          `${at}: theUILang.${guard.key} says ${spoken.join("/")}, guard requires ${named.join("/")}`
        );
      }
    }
    expect(wrong).toEqual([]);
  });
});
