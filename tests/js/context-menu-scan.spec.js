const { scan } = require("./context-menu-scan");

// The scanner that context-menu-string-commands.spec.js runs over the tree
// is checked here on small sources: one for each way a string can be handed
// to the menu, which it must report, and one for each way the tree builds a
// menu correctly, which it must accept. A scanner with no test of its own is
// a claim, not a check.

function findings(...codes) {
  return scan(codes.map((code, i) => ({ file: i ? `other${i}.js` : "fixture.js", code }))).findings;
}

describe("the context menu scanner reports", () => {
  it("a string command", () => {
    const f = findings(`theContextMenu.add([theUILang.x, "theWebUI.foo()"]);`);
    expect(f).toEqual([`fixture.js:1  string command: "theWebUI.foo()"`]);
  });

  it("a command built by concatenation", () => {
    const f = findings(`theContextMenu.add([label, "theWebUI.showURLInfo('" + id + "')"]);`);
    expect(f).toHaveLength(1);
    expect(f[0]).toMatch(/built by concatenation/);
  });

  it("a template literal command", () => {
    const f = findings("theContextMenu.add([label, `theWebUI.showURLInfo('${id}')`]);");
    expect(f).toHaveLength(1);
    expect(f[0]).toMatch(/string command/);
  });

  it("a string held in a variable", () => {
    const f = findings(`
      const command = "theWebUI.RSSRefresh()";
      theContextMenu.add([label, command]);
    `);
    expect(f).toEqual([`fixture.js:2  string command: "theWebUI.RSSRefresh()"`]);
  });

  it("a string assigned to a variable that held a function", () => {
    const f = findings(`
      let command = () => theWebUI.RSSRefresh();
      if (legacy) command = "theWebUI.RSSRefresh()";
      theContextMenu.add([label, command]);
    `);
    expect(f).toHaveLength(1);
    expect(f[0]).toMatch(/fixture.js:3  string command/);
  });

  it("a string in one branch of a conditional", () => {
    const f = findings(`
      function fn() {}
      theContextMenu.add([label, ok ? () => theWebUI.foo() : "theWebUI.foo()"]);
      theContextMenu.add([label, fn || "theWebUI.foo()"]);
    `);
    expect(f).toHaveLength(2);
    expect(f[0]).toMatch(/fixture.js:3  string command/);
    expect(f[1]).toMatch(/fixture.js:4  string command/);
  });

  it("a string on a selected entry", () => {
    const f = findings(`theContextMenu.add([CMENU_SEL, label, "theWebUI.foo()"]);`);
    expect(f).toHaveLength(1);
    expect(f[0]).toMatch(/string command/);
  });

  it("a string inside a nested submenu", () => {
    const f = findings(`
      theContextMenu.add([CMENU_CHILD, label, [
        [inner, () => theWebUI.foo()],
        [CMENU_CHILD, deeper, [[leaf, "theWebUI.bar()"]]],
      ]]);
    `);
    expect(f).toEqual([`fixture.js:4  string command: "theWebUI.bar()"`]);
  });

  it("a string pushed onto the variable a submenu is built in", () => {
    const f = findings(`
      var _bf = [];
      _bf.push([theUILang.High_priority, () => theWebUI.setPriority(id, 2)]);
      _bf.push([CMENU_SEP]);
      _bf.unshift([theUILang.Dont_download, "theWebUI.setPriority(" + id + ",0)"]);
      theContextMenu.add([CMENU_CHILD, theUILang.Priority, _bf]);
    `);
    expect(f).toHaveLength(1);
    expect(f[0]).toMatch(/fixture.js:5  string command \(built by concatenation\)/);
  });

  it("a string in a list rebuilt by concat and assignment", () => {
    const f = findings(`
      let entries = [];
      entries = [[theUILang.a, () => theWebUI.a()]];
      if (actLabelId) {
        entries = entries.concat(enabled ? [
          [theUILang.b, () => theWebUI.b()],
        ] : [
          [theUILang.c, "theWebUI.c()"],
        ]).concat([[theUILang.d, () => theWebUI.d()]]);
      }
      for (const entry of entries) theContextMenu.add(entry);
    `);
    expect(f).toEqual([`fixture.js:8  string command: "theWebUI.c()"`]);
  });

  it("a string in a spread-built list", () => {
    const f = findings(`
      const base = [[a, () => theWebUI.a()]];
      const all = [...base, [b, "theWebUI.b()"]];
      theContextMenu.add([CMENU_CHILD, label, all]);
      theContextMenu.add(...base);
    `);
    expect(f).toEqual([`fixture.js:3  string command: "theWebUI.b()"`]);
  });

  it("a string returned by a class method, inside a map-built submenu", () => {
    const f = findings(
      `
      const entries = theWebUI.categoryList.contextMenuEntries(e.panelId, e.labelId);
      for (const entry of entries) {
        theContextMenu.add(entry);
      }
      `,
      `
      export class CategoryList {
        contextMenuEntries(panelId, labelId) {
          return (
            {
              psearch: (this.selection.count("psearch") > 0
                ? [[this.theUILang.removeTeg, () => this.removeActiveTextSearches()]]
                : []
              ).concat([[this.theUILang.removeAllTegs, () => this.removeAllTextSearches()]]),
              pview: this.selection.count("pview") > 0
                ? [
                    [
                      CMENU_CHILD,
                      this.theUILang.MoveView.base,
                      ["top", "up", "down", "bottom"].map((action) => [
                        this.theUILang.MoveView[action],
                        "theWebUI.moveView('" + labelId + "','" + action + "')",
                      ]),
                    ],
                  ]
                : [],
            }[panelId] ?? []
          );
        }
      }
      `
    );
    expect(f).toHaveLength(1);
    expect(f[0]).toMatch(/^other1.js:17  string command \(built by concatenation\)/);
  });

  it("a string returned by an object method the menu is built from", () => {
    const f = findings(`
      var theWebUI = {
        createFooMenuPrim: function() {
          return [[theUILang.foo, "theWebUI.foo()"]];
        },
      };
      theContextMenu.add([CMENU_CHILD, label, theWebUI.createFooMenuPrim()]);
    `);
    expect(f).toEqual([`fixture.js:4  string command: "theWebUI.foo()"`]);
  });

  it("a string pushed by a wrapper around a producer called through .call", () => {
    const f = findings(
      `
      theWebUI.createRSSMenuPrim = function() {
        return [[theUILang.addRSS, () => theDialogManager.toggle('dlgAddRSS')]];
      };
      catlist.contextMenuEntries = function(panelId, labelId) {
        return panelId === 'prss' ? theWebUI.createRSSMenuPrim() : plugin.contextMenuEntries(panelId, labelId);
      };
      `,
      `
      plugin.createRSSMenuPrim = theWebUI.createRSSMenuPrim;
      theWebUI.createRSSMenuPrim = function() {
        let entries = plugin.createRSSMenuPrim.call(this);
        entries.push([CMENU_SEP]);
        entries.push([theUILang.rssRulesManager, "theWebUI.showRules()"]);
        return entries;
      };
      `,
      `
      for (const entry of theWebUI.categoryList.contextMenuEntries(e.panelId, e.labelId)) theContextMenu.add(entry);
      `
    );
    expect(f).toEqual([`other1.js:6  string command: "theWebUI.showRules()"`]);
  });

  it("a string given to setCommand", () => {
    const f = findings(`menu.setCommand($("<a>").text(label), "theWebUI.foo()");`);
    expect(f).toEqual([`fixture.js:1  string command: "theWebUI.foo()"`]);
  });

  it("a command it cannot prove is a function", () => {
    const f = findings(
      `
      theContextMenu.add([label, makeCommand(id)]);
      theContextMenu.add([label, theWebUI.nothingDefinesThis]);
      theContextMenu.add([label, nothingDefinesThisEither]);
      theContextMenu.add([label, theWebUI[name]]);
      theContextMenu.add([label, true]);
      `
    );
    expect(f).toHaveLength(5);
    expect(f[0]).toMatch(/fixture.js:2  command could not be proved a function \(result of a call\)/);
    expect(f[1]).toMatch(/fixture.js:3  command theWebUI.nothingDefinesThis: nothing in the tree defines a method/);
    expect(f[2]).toMatch(/fixture.js:4  command nothingDefinesThisEither: nothing in the tree defines a function/);
    expect(f[3]).toMatch(/fixture.js:5  command could not be proved a function \(computed property\)/);
    expect(f[4]).toMatch(/fixture.js:6  command could not be proved a function \(BooleanLiteral\)/);
  });

  it("an entry or list it cannot read", () => {
    const f = findings(
      `
      function addAll(entry) { theContextMenu.add(entry); }
      theContextMenu.add(someGlobalEntry);
      theContextMenu.add(someElement, [label, () => theWebUI.foo()]);
      theContextMenu.add([CMENU_CHILD, label, buildList()]);
      theContextMenu.add([CMENU_CHILD, label, this.lists[panelId]]);
      const list = [[label, () => theWebUI.foo()]];
      decorate(list);
      theContextMenu.add([CMENU_CHILD, label, list]);
      `
    );
    expect(f).toHaveLength(6);
    expect(f[0]).toMatch(/fixture.js:2  entry entry came in as a parameter/);
    expect(f[1]).toMatch(/fixture.js:3  entry someGlobalEntry is not bound here/);
    expect(f[2]).toMatch(/fixture.js:4  entry someElement is not bound here/);
    expect(f[3]).toMatch(/fixture.js:5  entry list comes from buildList\(\), which nothing in the tree defines/);
    expect(f[4]).toMatch(/fixture.js:6  entry list could not be read \(MemberExpression\)/);
    expect(f[5]).toMatch(/fixture.js:8  entry list list is passed to decorate, which the scanner does not follow/);
  });

  it("an entry or list changed in place in a way it does not follow", () => {
    const f = findings(
      `
      var a = [label, () => theWebUI.foo()];
      a.push("theWebUI.foo()");
      theContextMenu.add(a);
      const list = [[label, () => theWebUI.foo()]];
      list[0][1] = "theWebUI.foo()";
      list.forEach((e) => e);
      theContextMenu.add([CMENU_CHILD, label, list]);
      `
    );
    expect(f).toHaveLength(3);
    expect(f[0]).toMatch(/fixture.js:3  entry a is rebuilt in place/);
    expect(f[1]).toMatch(/fixture.js:6  entry list list is changed in place/);
    expect(f[2]).toMatch(/fixture.js:7  entry list list is used through list.forEach/);
  });

  it("a menu added through a method named by a string", () => {
    expect(findings(`theContextMenu["add"]([label, "theWebUI.foo()"]);`)).toEqual([
      `fixture.js:1  string command: "theWebUI.foo()"`,
    ]);
    expect(findings(`menu["setCommand"](anchor, "theWebUI.foo()");`)).toEqual([
      `fixture.js:1  string command: "theWebUI.foo()"`,
    ]);
  });

  it("the menu being called through a name the source does not fix", () => {
    const f = findings(`theContextMenu[method]([label, "theWebUI.foo()"]);`);
    expect(f).toHaveLength(1);
    expect(f[0]).toMatch(/theContextMenu is called through a name the source does not fix/);
  });

  it("a string in a property of an object built here, whatever else shares its name", () => {
    const f = findings(`
      const good = { run() {} };
      const bad = { run: "theWebUI.foo()" };
      theContextMenu.add([label, bad.run]);
    `);
    expect(f).toEqual([`fixture.js:3  string command: "theWebUI.foo()"`]);
  });

  it("an entry written into after it was built, through its own name or a second one", () => {
    expect(
      findings(`
        const entry = [label, () => theWebUI.foo()];
        entry[1] = "theWebUI.foo()";
        theContextMenu.add(entry);
      `)
    ).toHaveLength(1);
    const f = findings(`
      const entry = [label, () => theWebUI.foo()];
      const alias = entry;
      alias[1] = "theWebUI.foo()";
      theContextMenu.add(entry);
    `);
    expect(f).toHaveLength(1);
    expect(f[0]).toMatch(/fixture.js:4  entry alias is changed in place/);
    const g = findings(`
      const entry = [label, () => theWebUI.foo()];
      const alias = entry;
      alias.push("theWebUI.foo()");
      theContextMenu.add(entry);
    `);
    expect(g).toHaveLength(1);
    expect(g[0]).toMatch(/fixture.js:4  entry alias is rebuilt in place/);
  });

  it("a list pushed onto through a method named by a string", () => {
    const f = findings(`
      const xs = [];
      xs["push"]([label, "theWebUI.foo()"]);
      theContextMenu.add([CMENU_CHILD, label, xs]);
    `);
    expect(f).toEqual([`fixture.js:3  string command: "theWebUI.foo()"`]);
  });

  it("a list handed to a method that can replace an entry", () => {
    for (const call of [`ys.forEach((e) => { e[1] = "theWebUI.foo()"; })`, `ys.fill([label, "x"])`, `ys.at(0)[1] = "x"`]) {
      const f = findings(`
        const ys = [[label, () => theWebUI.foo()]];
        ${call};
        theContextMenu.add([CMENU_CHILD, label, ys]);
      `);
      expect(f).toHaveLength(1);
      expect(f[0]).toMatch(/entry list ys is used through/);
    }
  });

  it("an entry written into by the callback of a list method it does follow", () => {
    const f = findings(`
      const ys = [[label, () => theWebUI.foo()]];
      theContextMenu.add([CMENU_CHILD, label, ys.filter((e) => { e[1] = "theWebUI.foo()"; return true; })]);
    `);
    expect(f).toHaveLength(1);
    expect(f[0]).toMatch(/entry list ys has an entry written to in place/);
  });

  it("a producer a binding was pointed at after it was declared", () => {
    const f = findings(`
      function unsafe() { return [[label, "theWebUI.foo()"]]; }
      let make = () => [];
      make = unsafe;
      theContextMenu.add([CMENU_CHILD, label, make()]);
    `);
    expect(f).toEqual([`fixture.js:2  string command: "theWebUI.foo()"`]);
  });

  it("a string command built inside the dispatcher's own object", () => {
    const f = findings(`
      var theContextMenu = {
        setCommand: function(a, command) { return a; },
        add: function() { theContextMenu.add([label, "theWebUI.foo()"]); },
      };
    `);
    expect(f).toEqual([`fixture.js:4  string command: "theWebUI.foo()"`]);
  });

  it("the menu being used other than through a method call", () => {
    const f = findings(
      `
      const m = theContextMenu;
      m.add([label, "theWebUI.foo()"]);
      const add = theContextMenu.add;
      register(theContextMenu);
      `
    );
    expect(f).toHaveLength(3);
    for (const line of f) expect(line).toMatch(/theContextMenu is used other than by calling a method on it/);
  });
});

describe("the context menu scanner accepts", () => {
  it("functions, nothing, and conditionals between them", () => {
    expect(
      findings(`
        theContextMenu.add([theUILang.a, () => theWebUI.a()]);
        theContextMenu.add([theUILang.b, function() { theWebUI.b(); }]);
        theContextMenu.add([theUILang.c, enabled ? () => theWebUI.c() : null]);
        theContextMenu.add([theUILang.d, this.isTorrentCommandEnabled('d', id) && selCount == 1 ? () => theWebUI.d() : null]);
        theContextMenu.add([theUILang.e, ready && (() => theWebUI.e())]);
        theContextMenu.add([theUILang.f, null]);
        theContextMenu.add([theUILang.g, undefined]);
        theContextMenu.add([theUILang.h, false]);
        theContextMenu.add([theUILang.i]);
        theContextMenu.add([CMENU_SEP]);
        theContextMenu.add([CMENU_SEL, theUILang.j, () => theWebUI.j()]);
        theContextMenu.add([CMENU_SEL, theUILang.k]);
        theContextMenu.add([theUILang.DataDir + "...", firstSelectedTorrent() ? () => theWebUI.EditDataDir() : null]);
        theContextMenu.add(null);
      `)
    ).toEqual([]);
  });

  it("a command held in a variable, a declared function, and a bound one", () => {
    expect(
      findings(`
        const run = () => theWebUI.a();
        let later = null;
        if (ok) later = function() { theWebUI.b(); };
        function declared() { theWebUI.c(); }
        theContextMenu.add([theUILang.a, run]);
        theContextMenu.add([theUILang.b, later]);
        theContextMenu.add([theUILang.c, declared]);
        theContextMenu.add([theUILang.d, run.bind(theWebUI)]);
      `)
    ).toEqual([]);
  });

  it("a method or function the tree defines in another file", () => {
    expect(
      findings(
        `
        theContextMenu.add([CMENU_SEL, theUILang.shcIgnore, theWebUI.toggleSchIgnore]);
        theContextMenu.add([theUILang.Name, plugin.copyName]);
        theContextMenu.add([theUILang.Hash, plugin.copyHash]);
        theContextMenu.add([theUILang.Magnet, theWebUI.copyMagnet.bind(theWebUI)]);
        theContextMenu.add([theUILang.Port, refreshPort]);
        `,
        `
        theWebUI.toggleSchIgnore = function() {};
        plugin.copyName = () => {};
        var plugin2 = { copyHash: function() {} };
        class Copier { copyMagnet() {} }
        function refreshPort() {}
        `
      )
    ).toEqual([]);
  });

  it("nested submenus, spreads, and lists built up in a variable", () => {
    expect(
      findings(`
        var _bf = [];
        _bf.push([theUILang.High_priority, (p.priority == 2) ? null : () => theWebUI.setPriority(id, 2)]);
        _bf.push([CMENU_SEP]);
        _bf.unshift([theUILang.Top, () => theWebUI.top()]);
        _bf.splice(1, 0, [theUILang.Mid, () => theWebUI.mid()]);
        _bf[0] = [theUILang.First, () => theWebUI.first()];
        if (_bf.length && this.isTorrentCommandEnabled('setprio', this.dID))
          theContextMenu.add([CMENU_CHILD, theUILang.Priority, _bf]);
        else
          theContextMenu.add([theUILang.Priority]);
        _bf = [];
        _bf.push([theUILang.AsTree, () => theWebUI.toggleFileView()]);
        const more = [..._bf, [theUILang.More, () => theWebUI.more()]];
        const same = more;
        theContextMenu.add([CMENU_CHILD, theUILang.View, more.concat(same.slice(0, 1), [[theUILang.Last, () => theWebUI.last()]])]);
        theContextMenu.add([CMENU_CHILD, theUILang.Deep, [
          [theUILang.a, () => theWebUI.a()],
          [CMENU_CHILD, theUILang.b, [[theUILang.c, () => theWebUI.c()], [CMENU_SEP]]],
        ]]);
        theContextMenu.add(...more);
        theContextMenu.add([CMENU_CHILD, theUILang.Filtered, more.filter((e) => e.length)]);
      `)
    ).toEqual([]);
  });

  it("an entry marked selected in place, and a var redeclared in a loop", () => {
    expect(
      findings(`
        for (let i = 0; i < this.colsdata.length; i++) {
          var a = [this.colsdata[i].text, () => theWebUI.getTable(this.prefix).toggleColumn(i)];
          if (this.colsdata[i].enabled) a.unshift(CMENU_SEL);
          theContextMenu.add(a);
        }
      `)
    ).toEqual([]);
  });

  it("an entry placed after an existing one", () => {
    expect(
      findings(`
        var el = theContextMenu.get(theUILang.Properties);
        if (el) theContextMenu.add(el, [theUILang.DataDir + "...", () => theWebUI.EditDataDir()]);
        theContextMenu.add(theContextMenu.get(theUILang.Remove), [CMENU_CHILD, theUILang.Remove_and, [[theUILang.Delete_data, () => theWebUI.removeWithData(false)]]]);
      `)
    ).toEqual([]);
  });

  it("a menu built from a class method, a wrapper chain, and a map", () => {
    expect(
      findings(
        `
        const entries = theWebUI.categoryList.contextMenuEntries(e.panelId, e.labelId);
        if (!(entries && (id || entries.length))) return false;
        for (const entry of entries) {
          theContextMenu.add(entry);
        }
        `,
        `
        export class CategoryList {
          contextMenuEntries(panelId, labelId) {
            return (
              {
                psearch: (this.selection.count("psearch") > 0
                  ? [[this.theUILang.removeTeg, () => this.removeActiveTextSearches()]]
                  : []
                ).concat([[this.theUILang.removeAllTegs, () => this.removeAllTextSearches()]]),
                pview: this.selection.count("pview") > 0
                  ? [
                      [this.theUILang.RenameView, () => this.renameViewDialogFn(labelId)],
                      [
                        CMENU_CHILD,
                        this.theUILang.MoveView.base,
                        ["top", "up", "down", "bottom"].map((action) => [
                          this.theUILang.MoveView[action],
                          () => this.moveView(labelId, action),
                        ]),
                      ],
                      [this.theUILang.RemoveActiveViews, () => this.removeActiveViews()],
                    ]
                  : [],
              }[panelId] ?? []
            );
          }
        }
        `,
        `
        plugin.contextMenuEntries = catlist.contextMenuEntries.bind(catlist);
        catlist.contextMenuEntries = function(panelId, labelId) {
          if (panelId === 'psearch' && idIsExTeg(labelId)) {
            return plugin.canChangeMenu() ? [
              [theUILang.tegRefresh, () => theWebUI.tegRefresh()],
              [theUILang.tegMenuDelete, () => theWebUI.extTegDelete()],
            ] : false;
          }
          return plugin.contextMenuEntries(panelId, labelId);
        };
        theWebUI.createRSSMenuPrim = function() {
          if (!plugin.canChangeMenu()) return false;
          let entries = [];
          entries = [[theUILang.addRSS, () => theDialogManager.toggle('dlgAddRSS')]];
          if (actLabelId) {
            entries.push([CMENU_SEP]);
            entries = entries.concat(enabled ? [
              [theUILang.rssMenuDisable, () => theWebUI.RSSToggleStatus()],
            ] : [
              [theUILang.rssMenuEnable, () => theWebUI.RSSToggleStatus()],
              [theUILang.rssMenuRefresh],
            ]).concat([[theUILang.rssMenuEdit, () => theWebUI.RSSEdit()]]);
          }
          return entries;
        };
        `,
        `
        plugin.createRSSMenuPrim = theWebUI.createRSSMenuPrim;
        theWebUI.createRSSMenuPrim = function() {
          if (plugin.enabled) {
            let entries = plugin.createRSSMenuPrim.call(this);
            entries.push([CMENU_SEP]);
            entries.push([theUILang.rssRulesManager, () => theWebUI.showRules()]);
            return entries;
          }
          return plugin.createRSSMenuPrim.call(this);
        };
        const base = () => theWebUI.createRSSMenuPrim();
        theContextMenu.add([CMENU_CHILD, theUILang.Feeds, base()]);
        `
      )
    ).toEqual([]);
  });

  it("a method of an object built here, however the property is written", () => {
    expect(
      findings(`
        const run = () => theWebUI.a();
        const x = { run };
        const y = { ["run"]() {} };
        const z = { run: function() {} };
        theContextMenu.add([theUILang.a, x.run]);
        theContextMenu.add([theUILang.b, y.run]);
        theContextMenu.add([theUILang.c, z?.run]);
        theContextMenu.add([theUILang.d, z["run"]]);
      `)
    ).toEqual([]);
  });

  it("the dispatcher's own recursion", () => {
    expect(
      findings(`
        var theContextMenu = {
          setCommand: function(a, command) { return a; },
          add: function() {
            var self = this;
            $.each(arguments, function(ndx, val) {
              if (val[0] == CMENU_CHILD) {
                for (var j = 0; j < val[2].length; j++) self.add(ul, val[2][j]);
              } else {
                self.setCommand($("<a>").text(val[0]), val[1]);
              }
            });
          },
        };
        window.setTimeout(() => theContextMenu.hide(), 50);
      `)
    ).toEqual([]);
  });
});
