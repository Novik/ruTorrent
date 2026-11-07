const fs = require("fs");
const path = require("path");
const { parseSync, traverse } = require("@babel/core");

// A menu entry carries the function theContextMenu calls when the entry is
// picked (js/objects.js, setCommand). An entry that carries a string instead
// is inert: it is drawn, marked the way a command-less entry is, and does
// nothing. Nothing throws, so a missed conversion is only visible as a menu
// entry that no longer works.
//
// This reads every entry that reaches theContextMenu.add or setCommand and
// fails closed: a command is accepted only when the source proves it is a
// function or nothing at all, and every other expression is reported. The
// proof is syntactic. A command is accepted when it is
//
//   - a function or arrow expression, or null, undefined, false, or absent;
//   - an identifier bound in scope to one of those (through a declaration
//     and every assignment to it), or to a function declaration;
//   - a property whose object is built here, when the object literal gives
//     that property an accepted value;
//   - a name, or a property such as theWebUI.name, that the scanned tree
//     defines as a function somewhere -- the one check made by name rather
//     than by binding, because a plugin's methods are installed onto shared
//     objects from other files;
//   - fn.bind(...) where fn is accepted;
//   - a conditional or logical expression whose result branches are all
//     accepted (the left side of && is a guard, not a result).
//
// Entries are read out of array literals, out of the variable a submenu is
// pushed onto (following the declaration, every assignment, push, unshift,
// splice, concat, slice, filter and map), out of spread elements, and out of
// the functions whose return value a menu is built from -- found through the
// binding the call names, or, for a method installed from another file, by
// walking to every function of that name in the tree. A list or an entry
// that is passed to some other function, stored on an object, changed in
// place, or read through a parameter cannot be followed and is reported.
//
// obj["add"] names the same method as obj.add, so a key written as a string
// literal is read as that name. A key the source does not fix cannot be
// resolved and is reported where it is used.

const CMENU = new Set(["CMENU_SEP", "CMENU_CHILD", "CMENU_SEL"]);
const EMPTY_LIST = new Set(["NullLiteral"]);
const NEW_LIST_FROM = new Set(["slice", "filter", "reverse", "sort"]);
// Methods that cannot put a new command into a list. Everything else called
// on a list of entries -- forEach, fill, copyWithin, at, a helper of the
// tree's own -- is reported, because it can replace an entry after the list
// was read.
const LIST_READS = new Set([
  "concat", "slice", "filter", "map", "reverse", "sort", "join", "every", "some",
  "find", "findIndex", "indexOf", "lastIndexOf", "includes", "toString",
]);
// The same for one entry: its label and command can be read, and a marker
// can be put in front of it, but nothing else.
const ENTRY_READS = new Set(["indexOf", "includes", "join", "slice", "toString"]);

function sourceFiles(tree) {
  const out = [];
  (function walk(dir) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
      const p = path.join(dir, e.name);
      if (e.isDirectory()) {
        // Vendored third-party JS is not ours to rewrite.
        if (["node_modules", ".git", "bin", "jquery", "jscolor"].includes(e.name)) continue;
        walk(p);
      } else if (e.name.endsWith(".js")) {
        out.push(p);
      }
    }
  })(path.join(tree, "js"));
  (function walk(dir) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
      const p = path.join(dir, e.name);
      if (e.isDirectory()) {
        if (["node_modules", "jscolor"].includes(e.name)) continue;
        walk(p);
      } else if (e.name.endsWith(".js")) {
        out.push(p);
      }
    }
  })(path.join(tree, "plugins"));
  return out.map((file) => ({
    file: path.relative(tree, file),
    code: fs.readFileSync(file, "utf8"),
  }));
}

function parse(file, code) {
  return parseSync(code, {
    filename: file,
    configFile: false,
    babelrc: false,
    // Some of the tree is ES modules and some is classic scripts.
    sourceType: "unambiguous",
    parserOpts: { allowReturnOutsideFunction: true },
  });
}

// The name a function is defined under, and whether it is a property of some
// object (a method) rather than a free function or variable.
function definitionOf(fnPath) {
  const n = fnPath.node;
  if (fnPath.isFunctionDeclaration()) return n.id && { name: n.id.name, attached: false };
  if (fnPath.isObjectMethod() || fnPath.isClassMethod()) {
    if (fnPath.isClassMethod() && n.kind !== "method") return null;
    return keyName(n) && { name: keyName(n), attached: true };
  }
  const parent = fnPath.parent;
  switch (parent.type) {
    case "VariableDeclarator":
      return parent.id.type === "Identifier" ? { name: parent.id.name, attached: false } : null;
    case "AssignmentExpression":
      if (parent.right !== n) return null;
      if (parent.left.type === "Identifier") return { name: parent.left.name, attached: false };
      if (parent.left.type === "MemberExpression")
        return propName(parent.left) && { name: propName(parent.left), attached: true };
      return null;
    case "ObjectProperty":
      return parent.value === n && keyName(parent) ? { name: keyName(parent), attached: true } : null;
    default:
      return null;
  }
}

function keyName(n) {
  if (n.key.type === "StringLiteral") return n.key.value;
  if (!n.computed && n.key.type === "Identifier") return n.key.name;
  return null;
}

// The property a member expression names, or null when the source does not
// fix it. obj.add and obj["add"] are the same property; obj[name] is not a
// property this can resolve.
function propName(n) {
  if (n.computed) return n.property.type === "StringLiteral" ? n.property.value : null;
  return n.property.type === "Identifier" ? n.property.name : null;
}

function isMember(p) {
  return p.isMemberExpression() || p.isOptionalMemberExpression();
}

function scan(sources) {
  const findings = [];
  const sourceOf = new Map(); // Program node -> source
  const defs = new Map(); // function name -> [{ fnPath, attached }]
  const sinks = []; // { kind: "add" | "setCommand", path }
  const dispatchers = new Set(); // theContextMenu's own object literal
  const seen = new Map(); // node -> Set of roles it was already read in
  const producers = new Set(); // function names a menu was read out of

  function def(name, entry) {
    if (!defs.has(name)) defs.set(name, []);
    defs.get(name).push(entry);
  }

  for (const src of sources) {
    const ast = parse(src.file, src.code);
    sourceOf.set(ast.program, src);
    traverse(ast, {
      VariableDeclarator(p) {
        const { id, init } = p.node;
        if (id.type === "Identifier" && id.name === "theContextMenu" && init && init.type === "ObjectExpression")
          dispatchers.add(init);
      },
      Function(p) {
        const d = definitionOf(p);
        if (d) def(d.name, { fnPath: p, attached: d.attached });
      },
      CallExpression(p) {
        const callee = p.node.callee;
        if (callee.type !== "MemberExpression") return;
        const method = propName(callee);
        if (method === "add" && callee.object.type === "Identifier" && callee.object.name === "theContextMenu")
          sinks.push({ kind: "add", path: p });
        else if (method === "setCommand") sinks.push({ kind: "setCommand", path: p });
      },
      // Only member calls on theContextMenu are read. A copy of it, of its
      // add, or a method named by something the source does not fix would
      // take entries the scan never sees, so no other use is allowed.
      Identifier(p) {
        if (p.node.name !== "theContextMenu") return;
        const parent = p.parentPath;
        if (parent.isVariableDeclarator() && p.key === "id") return;
        if ((parent.isObjectProperty() || parent.isObjectMethod() || parent.isClassMethod()) && p.key === "key") return;
        if (parent.isMemberExpression() && p.key === "property" && !parent.node.computed) return;
        if (parent.isMemberExpression() && p.key === "object") {
          const method = propName(parent.node);
          if (method === null)
            return report(p, "theContextMenu is called through a name the source does not fix, so entries can reach it unseen");
          if (!["add", "setCommand"].includes(method)) return;
          if (parent.parentPath.isCallExpression() && parent.key === "callee") return;
        }
        report(p, "theContextMenu is used other than by calling a method on it, so entries can reach it unseen");
      },
    });
  }

  function report(p, what) {
    const src = sourceOf.get(p.scope.getProgramParent().path.node);
    findings.push(`${src.file}:${p.node.loc.start.line}  ${what}`);
  }

  function text(p) {
    const src = sourceOf.get(p.scope.getProgramParent().path.node);
    const s = src.code.slice(p.node.start, p.node.end).replace(/\s+/g, " ");
    return s.length > 100 ? s.slice(0, 97) + "..." : s;
  }

  function once(p, role) {
    if (!seen.has(p.node)) seen.set(p.node, new Set());
    const roles = seen.get(p.node);
    if (roles.has(role)) return false;
    roles.add(role);
    return true;
  }

  function has(p) {
    return p && p.node;
  }

  function isMarker(p) {
    return has(p) && p.isIdentifier() && CMENU.has(p.node.name);
  }

  function isNothing(p) {
    return (
      !has(p) ||
      EMPTY_LIST.has(p.node.type) ||
      (p.isIdentifier() && p.node.name === "undefined") ||
      (p.isBooleanLiteral() && p.node.value === false)
    );
  }

  function definedAsFunction(name, attached) {
    return (defs.get(name) || []).some((d) => !attached || d.attached);
  }

  // What an object literal bound here gives a property: true for a method,
  // the path of the value otherwise, or null when the object is not built
  // here, is built from something else, or does not name the property in its
  // literal -- a property can also be installed onto it from another file.
  function ownProperty(objPath, name) {
    let obj = objPath;
    if (obj.isIdentifier()) {
      const binding = obj.scope.getBinding(obj.node.name);
      if (!binding || binding.constantViolations.length || !binding.path.isVariableDeclarator()) return null;
      obj = binding.path.get("init");
      if (!has(obj)) return null;
    }
    if (!obj.isObjectExpression()) return null;
    let out = null;
    for (const prop of obj.get("properties")) {
      // A spread can bring the property in, or hide the one written here.
      if (prop.isSpreadElement()) return null;
      if (keyName(prop.node) !== name) continue;
      out = prop.isObjectMethod() ? prop.node.kind === "method" : prop.get("value");
    }
    return out;
  }

  // Every value a binding is given after its declaration: plain assignments
  // and redeclarations (a `var` in a loop body is one) are checked with the
  // role of the binding; anything else changing it is reported.
  function violations(binding, role, check) {
    const name = binding.identifier.name;
    for (const v of binding.constantViolations) {
      if (v.isAssignmentExpression() && v.node.operator === "=" && v.node.left.type === "Identifier")
        check(v.get("right"));
      else if (v.isVariableDeclarator() && v.node.id.type === "Identifier") {
        if (v.node.init) check(v.get("init"));
      } else report(v, `${role} ${name} is changed in a way the scanner does not follow: ${text(v)}`);
    }
  }

  // The expression in a command position. Anything not proved to be a
  // function or nothing at all is reported.
  function checkCommand(p) {
    if (isNothing(p) || !once(p, "command")) return;
    const n = p.node;
    switch (n.type) {
      case "ArrowFunctionExpression":
      case "FunctionExpression":
        return;
      case "StringLiteral":
      case "TemplateLiteral":
        return report(p, `string command: ${text(p)}`);
      case "BinaryExpression":
        return report(p, `string command (built by concatenation): ${text(p)}`);
      case "ConditionalExpression":
        checkCommand(p.get("consequent"));
        checkCommand(p.get("alternate"));
        return;
      case "LogicalExpression":
        if (n.operator !== "&&") checkCommand(p.get("left"));
        checkCommand(p.get("right"));
        return;
      case "Identifier":
        return checkCommandIdentifier(p);
      case "MemberExpression":
      case "OptionalMemberExpression": {
        const name = propName(n);
        if (name === null)
          return report(p, `command could not be proved a function (computed property): ${text(p)}`);
        // An object built here says what its own properties hold. One built
        // elsewhere, or added to from another file, falls back to the name.
        const own = ownProperty(p.get("object"), name);
        if (own === true) return;
        if (own) return checkCommand(own);
        if (definedAsFunction(name, true)) return;
        return report(p, `command ${text(p)}: nothing in the tree defines a method ${name}`);
      }
      case "CallExpression": {
        const callee = p.get("callee");
        if (isMember(callee) && propName(callee.node) === "bind") return checkCommand(callee.get("object"));
        return report(p, `command could not be proved a function (result of a call): ${text(p)}`);
      }
      default:
        return report(p, `command could not be proved a function (${n.type}): ${text(p)}`);
    }
  }

  function checkCommandIdentifier(p) {
    const name = p.node.name;
    const binding = p.scope.getBinding(name);
    if (!binding) {
      if (definedAsFunction(name, false)) return;
      return report(p, `command ${name}: nothing in the tree defines a function ${name}`);
    }
    const b = binding.path;
    if (b.isFunctionDeclaration()) return;
    if (binding.kind === "param") return report(p, `command ${name} came in as a parameter, so it cannot be read here`);
    if (b.isVariableDeclarator() && b.node.id.type === "Identifier") {
      if (b.node.init) checkCommand(b.get("init"));
      return violations(binding, "command", checkCommand);
    }
    return report(p, `command ${name} could not be proved a function (bound by ${b.node.type})`);
  }

  // One entry: [label], [label, command], [CMENU_SEL, label, command],
  // [CMENU_SEP] or [CMENU_CHILD, label, <entry list>].
  function checkEntry(p) {
    if (isNothing(p) || !once(p, "entry")) return;
    const n = p.node;
    switch (n.type) {
      case "ArrayExpression":
        return checkEntryArray(p);
      case "SpreadElement":
        return checkList(p.get("argument"));
      case "ConditionalExpression":
        checkEntry(p.get("consequent"));
        checkEntry(p.get("alternate"));
        return;
      case "LogicalExpression":
        if (n.operator !== "&&") checkEntry(p.get("left"));
        checkEntry(p.get("right"));
        return;
      case "Identifier":
        return checkEntryIdentifier(p);
      default:
        return report(p, `entry could not be read (${n.type}): ${text(p)}`);
    }
  }

  function checkEntryArray(p) {
    const els = p.get("elements");
    if (!els.length) return;
    const first = els[0];
    if (has(first) && first.isSpreadElement())
      return report(p, `entry could not be read (spread into the entry): ${text(p)}`);
    if (isMarker(first)) {
      if (first.node.name === "CMENU_SEP") return;
      if (first.node.name === "CMENU_CHILD") {
        if (!has(els[2])) return report(p, `submenu without an entry list: ${text(p)}`);
        return checkList(els[2]);
      }
      return checkCommand(els[2]);
    }
    return checkCommand(els[1]);
  }

  function checkEntryIdentifier(p) {
    const name = p.node.name;
    const binding = p.scope.getBinding(name);
    if (!binding) return report(p, `entry ${name} is not bound here, so it cannot be read`);
    checkEntryBinding(binding, p);
  }

  function checkEntryBinding(binding, at) {
    const name = binding.identifier.name;
    const b = binding.path;
    if (!once(b, "entry-binding")) return;
    if (binding.kind === "param") return report(at, `entry ${name} came in as a parameter, so it cannot be read here`);
    if (!b.isVariableDeclarator() || b.node.id.type !== "Identifier")
      return report(at, `entry ${name} could not be read (bound by ${b.node.type})`);
    const loop = b.parentPath.parentPath;
    if (loop.isForOfStatement() && loop.node.left === b.parent) return checkList(loop.get("right"));
    if (loop.isForInStatement() && loop.node.left === b.parent)
      return report(at, `entry ${name} is a for-in key, so it cannot be read`);
    if (b.node.init) checkEntry(b.get("init"));
    violations(binding, "entry", checkEntry);
    for (const ref of binding.referencePaths) checkEntryReference(ref, name);
  }

  // A use of an entry variable that can put a command into it: a method
  // other than the marker that makes an entry a selected one, a write into
  // one of its elements, or a second name the same changes can be made
  // through. An entry proved safe where it was built is still a variable.
  function checkEntryReference(ref, name) {
    const parent = ref.parentPath;
    const call = methodCall(ref);
    if (call) {
      // Putting a CMENU marker in front is how an entry is marked selected.
      if ((call.method === "push" || call.method === "unshift") && call.args.every(isMarker)) return;
      if (ENTRY_READS.has(call.method)) return;
      return report(call.path, `entry ${name} is rebuilt in place, which the scanner does not follow: ${text(call.path)}`);
    }
    if (isMember(parent) && ref.key === "object") {
      let top = parent;
      while (isMember(top.parentPath) && top.key === "object") top = top.parentPath;
      const to = top.parentPath;
      if (
        (to.isAssignmentExpression() && top.key === "left") ||
        to.isUpdateExpression() ||
        (to.isUnaryExpression() && to.node.operator === "delete")
      )
        return report(to, `entry ${name} is changed in place, which the scanner does not follow: ${text(to)}`);
      return; // entry[0], entry.length and so on: reads
    }
    // A second name for the same entry can be changed through as well.
    const alias =
      (parent.isVariableDeclarator() && ref.key === "init" && parent.node.id.type === "Identifier" && parent.node.id.name) ||
      (parent.isAssignmentExpression() &&
        ref.key === "right" &&
        parent.node.operator === "=" &&
        parent.node.left.type === "Identifier" &&
        parent.node.left.name);
    if (alias) {
      const to = parent.scope.getBinding(alias);
      if (!to) return report(parent, `entry ${name} is copied to ${alias}, which is not bound here`);
      return checkEntryBinding(to, ref);
    }
    if (parent.isAssignmentExpression() && ref.key === "right")
      return report(parent, `entry ${name} is stored where the scanner does not follow it: ${text(parent)}`);
  }

  // ref.method(args) where ref is the object: the method and the argument
  // paths, or null when ref is used some other way.
  function methodCall(ref) {
    const mem = ref.parentPath;
    if (!isMember(mem) || ref.key !== "object") return null;
    const call = mem.parentPath;
    if (!call.isCallExpression() || mem.key !== "callee") return null;
    return { method: propName(mem.node), args: call.get("arguments"), path: call };
  }

  // filter, map and sort read the list but are handed each entry, so a
  // callback that writes into the entry it was given is reported.
  function checkCallbacks(call, name) {
    for (const a of call.get("arguments")) {
      if (!a.isFunction()) continue;
      const params = new Set(a.node.params.filter((q) => q.type === "Identifier").map((q) => q.name));
      if (!params.size) continue;
      a.traverse({
        AssignmentExpression(w) {
          let target = w.get("left");
          if (!isMember(target)) return;
          while (isMember(target.get("object"))) target = target.get("object");
          const base = target.get("object");
          if (base.isIdentifier() && params.has(base.node.name))
            report(w, `entry list ${name} has an entry written to in place, which the scanner does not follow: ${text(w)}`);
        },
      });
    }
  }

  function mutatingCall(ref) {
    const c = methodCall(ref);
    if (!c) return null;
    if (c.method === "push" || c.method === "unshift") return c;
    if (c.method === "splice") return { ...c, args: c.args.slice(2) };
    return null;
  }

  // An expression that is a list of entries.
  function checkList(p) {
    if (isNothing(p) || !once(p, "list")) return;
    const n = p.node;
    switch (n.type) {
      case "ArrayExpression":
        for (const el of p.get("elements")) {
          if (!has(el)) continue;
          if (el.isSpreadElement()) checkList(el.get("argument"));
          else checkEntry(el);
        }
        return;
      case "ConditionalExpression":
        checkList(p.get("consequent"));
        checkList(p.get("alternate"));
        return;
      case "LogicalExpression":
        if (n.operator !== "&&") checkList(p.get("left"));
        checkList(p.get("right"));
        return;
      case "Identifier":
        return checkListIdentifier(p);
      case "CallExpression":
        return checkListCall(p);
      case "MemberExpression": {
        // {panelId: [...], ...}[panelId]
        const obj = p.get("object");
        if (n.computed && obj.isObjectExpression()) {
          for (const prop of obj.get("properties")) {
            if (prop.isObjectProperty()) checkList(prop.get("value"));
            else report(prop, `entry list could not be read (${prop.node.type} in the table): ${text(prop)}`);
          }
          return;
        }
        return report(p, `entry list could not be read (${n.type}): ${text(p)}`);
      }
      default:
        return report(p, `entry list could not be read (${n.type}): ${text(p)}`);
    }
  }

  function checkListIdentifier(p) {
    const name = p.node.name;
    const binding = p.scope.getBinding(name);
    if (!binding) return report(p, `entry list ${name} is not bound here, so it cannot be read`);
    checkListBinding(binding, p);
  }

  function checkListBinding(binding, at) {
    const name = binding.identifier.name;
    const b = binding.path;
    if (!once(b, "list-binding")) return;
    if (binding.kind === "param") return report(at, `entry list ${name} came in as a parameter, so it cannot be read here`);
    if (!b.isVariableDeclarator() || b.node.id.type !== "Identifier")
      return report(at, `entry list ${name} could not be read (bound by ${b.node.type})`);
    if (b.node.init) checkList(b.get("init"));
    violations(binding, "entry list", checkList);
    for (const ref of binding.referencePaths) checkListReference(ref, name);
  }

  // Every use of a list variable is either a read the scan understands, an
  // addition it checks, or an escape it reports.
  function checkListReference(ref, name) {
    const parent = ref.parentPath;
    const call = mutatingCall(ref);
    if (call) {
      for (const a of call.args) {
        if (a.isSpreadElement()) checkList(a.get("argument"));
        else checkEntry(a);
      }
      return;
    }
    if (isMember(parent) && ref.key === "object") {
      // list[i] = entry is checked; list[i][j] = x, or list.prop = x, is not followed.
      let top = parent;
      while (isMember(top.parentPath) && top.key === "object") top = top.parentPath;
      const assign = top.parentPath;
      if (assign.isAssignmentExpression() && top.key === "left") {
        if (top === parent && parent.node.computed && assign.node.operator === "=") return checkEntry(assign.get("right"));
        return report(assign, `entry list ${name} is changed in place in a way the scanner does not follow: ${text(assign)}`);
      }
      if (assign.isUpdateExpression() || (assign.isUnaryExpression() && assign.node.operator === "delete"))
        return report(assign, `entry list ${name} is changed in place in a way the scanner does not follow: ${text(assign)}`);
      // A method called on the list. push, unshift and splice were taken
      // above; of the rest only the ones that cannot put a command into an
      // entry are reads. A callback is handed the entries themselves and can
      // write into them, so forEach and anything unrecognized is reported.
      if (top === parent && parent.parentPath.isCallExpression() && parent.key === "callee") {
        const method = propName(parent.node);
        if (method !== null && LIST_READS.has(method)) return checkCallbacks(parent.parentPath, name);
        return report(parent.parentPath, `entry list ${name} is used through ${text(parent)}, which the scanner does not follow`);
      }
      return; // .length, list[i] and so on: reads
    }
    if (parent.isVariableDeclarator() && ref.key === "init" && parent.node.id.type === "Identifier")
      return checkListBinding(parent.scope.getBinding(parent.node.id.name), ref);
    if (parent.isAssignmentExpression() && ref.key === "right") {
      if (parent.node.left.type === "Identifier" && parent.node.operator === "=")
        return checkListBinding(parent.scope.getBinding(parent.node.left.name), ref);
      return report(parent, `entry list ${name} is stored where the scanner does not follow it: ${text(parent)}`);
    }
    if (parent.isCallExpression() && ref.key !== "callee") {
      const callee = parent.get("callee");
      const method = callee.isMemberExpression() && !callee.node.computed && callee.node.property.name;
      if (method === "concat") return; // read where the result is used
      return report(parent, `entry list ${name} is passed to ${text(callee)}, which the scanner does not follow`);
    }
    if (
      parent.isForOfStatement() ||
      parent.isReturnStatement() ||
      parent.isArrowFunctionExpression() ||
      parent.isSpreadElement() ||
      parent.isArrayExpression() ||
      parent.isConditionalExpression() ||
      parent.isLogicalExpression() ||
      parent.isUnaryExpression() ||
      parent.isBinaryExpression() ||
      parent.isIfStatement() ||
      parent.isWhileStatement() ||
      parent.isExpressionStatement()
    )
      return;
    report(ref, `entry list ${name} is used in a way the scanner does not follow (${parent.node.type})`);
  }

  function checkListCall(p) {
    const callee = p.get("callee");
    if (callee.isIdentifier()) return producerIdentifier(callee, p);
    if (!isMember(callee) || propName(callee.node) === null)
      return report(p, `entry list could not be read (result of a call): ${text(p)}`);
    const method = propName(callee.node);
    const args = p.get("arguments");
    if (method === "concat") {
      checkList(callee.get("object"));
      for (const a of args) {
        if (a.isSpreadElement()) checkList(a.get("argument"));
        else checkList(a);
      }
      return;
    }
    if (NEW_LIST_FROM.has(method)) return checkList(callee.get("object"));
    if (method === "map") {
      const fn = args[0];
      if (!has(fn) || !fn.isFunction()) return report(p, `entry list is mapped through something the scanner cannot read: ${text(p)}`);
      return returns(fn, checkEntry);
    }
    if (method === "call" || method === "apply") {
      const target = callee.get("object");
      if (target.isIdentifier()) return producerIdentifier(target, p);
      if (isMember(target) && propName(target.node) !== null) return producer(propName(target.node), p);
      return report(p, `entry list could not be read (result of a call): ${text(p)}`);
    }
    return producer(method, p);
  }

  // A call that builds a list, named by an identifier. The binding says what
  // is called, including anything assigned to it after it was declared; a
  // name that is not bound here is installed from another file and is looked
  // up among every function of that name in the tree.
  function producerIdentifier(callee, at) {
    const name = callee.node.name;
    const binding = callee.scope.getBinding(name);
    if (!binding) return producer(name, at);
    producers.add(name);
    if (!once(binding.path, "producer-binding")) return;
    const b = binding.path;
    if (binding.kind === "param")
      return report(at, `entry list comes from ${name}(), which came in as a parameter`);
    if (b.isFunctionDeclaration()) returns(b, checkList);
    else if (b.isVariableDeclarator() && b.node.id.type === "Identifier") producerValue(b.get("init"), at);
    else return report(at, `entry list comes from ${name}(), which the scanner cannot read (bound by ${b.node.type})`);
    violations(binding, "entry list producer", (v) => producerValue(v, at));
  }

  function producerValue(p, at) {
    if (!has(p)) return;
    if (p.isFunction()) return returns(p, checkList);
    if (p.isIdentifier()) return producerIdentifier(p, at);
    return report(p, `entry list comes from a call the scanner cannot read: ${text(p)}`);
  }

  // The lists a function returns.
  function returns(fnPath, check) {
    if (!once(fnPath, "producer")) return;
    if (!fnPath.get("body").isBlockStatement()) return check(fnPath.get("body"));
    fnPath.traverse({
      ReturnStatement(r) {
        if (r.getFunctionParent().node === fnPath.node && r.node.argument) check(r.get("argument"));
      },
    });
  }

  // A call whose result is a list of entries: every function of that name
  // in the tree is read as a producer.
  function producer(name, at) {
    producers.add(name);
    const found = defs.get(name) || [];
    if (!found.length)
      return report(at, `entry list comes from ${name}(), which nothing in the tree defines as a function`);
    for (const d of found) returns(d.fnPath, checkList);
  }

  function isAnchor(p) {
    const isGet = (c) =>
      c.isCallExpression() &&
      isMember(c.get("callee")) &&
      c.node.callee.object.type === "Identifier" &&
      c.node.callee.object.name === "theContextMenu" &&
      propName(c.node.callee) === "get";
    if (isGet(p)) return true;
    if (!p.isIdentifier()) return false;
    const binding = p.scope.getBinding(p.node.name);
    return !!binding && binding.path.isVariableDeclarator() && has(binding.path.get("init")) && isGet(binding.path.get("init"));
  }

  // theContextMenu's own add hands each entry of a submenu back to itself,
  // and its setCommand takes the command out of the entry it was given.
  // Those calls read what was already checked where the entry came in, and
  // they are the calls theContextMenu makes on itself. Any other call in the
  // dispatcher builds something new and is read like any other.
  function isOwnRecursion(s) {
    if (!s.path.findParent((a) => dispatchers.has(a.node))) return false;
    const obj = s.path.get("callee").get("object");
    if (obj.isThisExpression()) return true;
    if (!obj.isIdentifier()) return false;
    const b = obj.scope.getBinding(obj.node.name);
    return !!b && b.path.isVariableDeclarator() && has(b.path.get("init")) && b.path.get("init").isThisExpression();
  }

  let read = 0;
  let recursion = 0;
  for (const s of sinks) {
    if (isOwnRecursion(s)) {
      recursion++;
      continue;
    }
    read++;
    const args = s.path.get("arguments");
    if (s.kind === "setCommand") {
      if (args.length < 2) report(s.path, `setCommand without a command: ${text(s.path)}`);
      else checkCommand(args[1]);
      continue;
    }
    // An entry placed after an existing one: theContextMenu.add(el, entry).
    const from = args.length && isAnchor(args[0]) ? 1 : 0;
    for (const a of args.slice(from)) checkEntry(a);
  }

  return { findings, sinks: read, recursion, dispatchers: dispatchers.size, producers: [...producers].sort() };
}

module.exports = { scan, sourceFiles };
