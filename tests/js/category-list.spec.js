import { CategoryList } from "../../js/category-list";

const listSettings = {
  "webui.category_panels": [
    "pview",
    "pstate",
    "plabel",
    "psearch",
    "ptrackers",
    "prss",
  ],
  "webui.show_label_path_tree": true,
  "webui.show_viewlabelsize": true,
  "webui.show_statelabelsize": true,
  "webui.show_searchlabelsize": false,
  "webui.show_labelsize": true,
  "webui.show_view_panel": true,

  "webui.closed_panels": {},
  "webui.open_tegs.keep": true,
  "webui.open_tegs.last": ["[Banana]", "less"],
  "webui.selected_labels.keep": true,
  "webui.selected_labels.last": {
    plabel: ["clabel__path/for/torrent/label", "-_-_-nlb-_-_-"],
  },
  "webui.selected_labels.views": [],
};
const toMap = (obj) => new Map(Object.entries(obj));

const listAttribs = () => ({
  panelAttribs: {
    pview: { text: "Views" },
    pstate: { text: "State" },
    plabel: { text: "Labels" },
    psearch: { text: "Search" },
  },
  panelLabelAttribs: {
    pview: toMap({
      pview_all: { icon: "all", text: "All", selected: true },
    }),
    pstate: toMap({
      pstate_all: { icon: "all", text: "All", selected: true },
      "-_-_-dls-_-_-": { icon: "down", text: "Downloading" },
      "-_-_-com-_-_-": { icon: "completed", text: "Finished" },
      "-_-_-act-_-_-": { icon: "up-down", text: "Active" },
      "-_-_-iac-_-_-": { icon: "incompleted", text: "Inactive" },
      "-_-_-err-_-_-": { icon: "error", text: "Error" },
    }),
    plabel: toMap({
      plabel_all: { icon: "all", text: "All", selected: true },
      "-_-_-nlb-_-_-": { icon: "no-label", text: "No label" },
    }),
    psearch: toMap({
      psearch_all: { icon: "all", text: "All", selected: true },
    }),
  },
});

let syncFn, onSelectionChangeFn, onConfigChangeFn, borrowTorrentsFn, list;

describe("Category list", () => {
  beforeEach(() => {
    syncFn = jest.fn();
    onSelectionChangeFn = jest.fn();
    onConfigChangeFn = jest.fn();
    borrowTorrentsFn = jest.fn().mockImplementation(() =>
      Object.fromEntries(
        [
          ["AA", "", "[Test] less", 16, 100 << 20, 0, 0],
          ["BB", "Software", "[Banana] green", 0, 12 << 20, 5000, 10],
          ["CC", "Image/Banana", "[Banana] ripe", 15, 1 << 30, 0, 10000],
          ["DD", "Image/Peach", "[Peach] rotten", 1, 500 << 20, 3000, 5000],
          ["EE", "Misc/Other/More", "[More] or less", 2, 300 << 20, 200, 0],
          ["FF", "Misc/Other/Less", "[Less] or more", 16, 200 << 20, 0, 0],
        ].map(([hash, label, name, status, size, ul, dl]) => [
          hash,
          { label, name, status, size, ul, dl },
        ])
      )
    );
    list = new CategoryList({
      panelAttribs: listAttribs().panelAttribs,
      panelLabelAttribs: listAttribs().panelLabelAttribs,
      syncFn,
      onSelectionChangeFn,
      borrowTorrentsFn,
      onConfigChangeFn,
      byteSizeToStringFn: (size) => `${(size / (1 << 20)).toFixed(2)} MiB`,
      dStatus: { error: 16 },
    });
  });
  it("should restore selection and searches on config", () => {
    expect(syncFn).not.toHaveBeenCalled();
    list.config(listSettings);
    expect(onConfigChangeFn).not.toHaveBeenCalled();
    expect(borrowTorrentsFn).not.toHaveBeenCalled();

    //log("CategoryList: ", list.panelAttribs, list.panelLabelAttribs);
  });

  it("should allow (plugins) to add panel before config", () => {
    list.addPanel("testpanelId", "Test panel");
    list.addPanel("testpanelId2", "Test panel2", [
      ["some_id2", { text: "test2" }],
    ]);
    list.addPanel(
      "testpanelId3",
      "Test panel3",
      [["some_id3", { text: "test3" }]],
      [() => [], () => false]
    );
    list.addPanel(
      "testpanelId4",
      "Test panel3",
      [["some_id4", { text: "test4" }]],
      [() => [], () => false]
    );
    list.addPanel("testpanelId5", "Test panel5");
    list.addPanel("testpanelId6", "Test panel6");

    expect(list.statistic.hasPanel("testpanelId3")).toBe(true);
    list.removePanel("testpanelId3");
    expect(list.statistic.hasPanel("testpanelId3")).toBe(false);

    list.removePanel("testpanelId5");
    expect(list.statistic.hasPanel("testpanelId4")).toBe(true);
    expect(list.settings).toBe(undefined);
    list.config({
      ...listSettings,
      "webui.selected_labels.last": {
        testpanelId: ["testLabelId"],
      },
    });
    expect(onConfigChangeFn).toHaveBeenCalled();
    onConfigChangeFn.mockClear();

    list.removePanel("testpanelId4");
    expect(list.statistic.hasPanel("testpanelId4")).toBe(false);

    expect(onConfigChangeFn).not.toHaveBeenCalled();
    expect(list.sortedPanelIds).toEqual([
      "pview",
      "pstate",
      "plabel",
      "psearch",
      "testpanelId",
      "testpanelId2",
      "testpanelId6",
    ]);
    expect(list.selection.ids("testpanelId")).toEqual(["testLabelId"]);
    expect(list.panelLabelAttribs.testpanelId.size).toBe(0);
    expect(list.panelLabelAttribs.testpanelId2.size).toBe(1);
  });

  it("should notify selection change", () => {
    list.config(listSettings);
    expect(list.selection.count("plabel")).toBe(2);
    expect(list.selection.count("pstate")).toBe(0);

    list.switchLabel("pstate", null);
    expect(onSelectionChangeFn).not.toHaveBeenCalled();
    expect(onConfigChangeFn).not.toHaveBeenCalled();

    list.switchLabel("pstate", "-_-_-act-_-_-");
    expect(onSelectionChangeFn).toHaveBeenCalled();
    expect(onConfigChangeFn).toHaveBeenCalled();
  });

  it("should show torrent statistic", () => {
    list.config(listSettings);
    const torrents = borrowTorrentsFn();
    for (const [hash, torrent] of Object.entries(torrents)) {
      list.statistic.scan(hash, torrent);
    }
    list.syncAfterScan();
    const actAttribs = list.panelLabelAttribs.pstate.get("-_-_-act-_-_-");
    expect(actAttribs.count).toBe("3");
    expect(actAttribs.size).toBe(
      list.byteSizeToStringFn((500 << 20) + (12 << 20) + (1 << 30))
    );
    const nlbAttribs = list.panelLabelAttribs.plabel.get("-_-_-nlb-_-_-");
    expect(nlbAttribs.count).toBe("1");
    expect(nlbAttribs.size).toBe(list.byteSizeToStringFn(100 << 20));
    expect(nlbAttribs.prefix).toBe(undefined);

    const labelAttribs = list.panelLabelAttribs.plabel.get(
      "clabel__Image/Banana"
    );
    expect(labelAttribs.count).toBe("1");
    expect(labelAttribs.size).toBe(list.byteSizeToStringFn(1 << 30));
    expect(labelAttribs.prefix.length).toBe(1);

    const search1Attribs = list.panelLabelAttribs.psearch.get("psearch_1");
    expect(search1Attribs.count).toBe("3");
    expect(search1Attribs.size).toBeNull(); // since show_statelabelsize is false
    expect(search1Attribs.prefix).toBe(undefined);
  });
});
