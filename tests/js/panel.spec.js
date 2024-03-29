import { log } from "console";
import {
  PanelLabelSelection,
  CategoryListStatistic,
  TorrentLabelTree,
} from "../../js/panel";

let sel,
  plabelLabelIds,
  pstateLabelIds = [
    "-_-_-dls-_-_-",
    "-_-_-com-_-_-",
    "-_-_-act-_-_-",
    "-_-_-iac-_-_-",
    "-_-_-err-_-_-",
  ];
const statisticPanelIds = ["pstate", "plabel", "psearch"];
const viewSelections = [
  {
    pstate: ["-_-_-act-_-_-"],
  },
  {
    pstate: ["-_-_-iac-_-_-"],
    plabel: ["-_-_-nlb-_-_-"],
  },
  {
    pstate: ["-_-_-act-_-_-"],
    psearch: ["psearch_1"],
  },
].map((obj) => PanelLabelSelection.fromConfig(obj, statisticPanelIds));
describe("Panel label selection", () => {
  beforeEach(() => {
    sel = new PanelLabelSelection();
    sel.panelAdd("plabel");
    plabelLabelIds = [
      "clabel__path/for/torrent/label",
      "clabel__path/for/torrent/secondlabel",
      "-_-_-nlb-_-_-",
    ];
    sel.select("plabel", ["clabel__path/for/torrent/label", "-_-_-nlb-_-_-"]);
    sel.panelAdd("pstate");
  });
  it("should switch", () => {
    expect(sel.count("plabel")).toBe(2);
    sel.switch("plabel", "-_-_-nlb-_-_-");
    expect(sel.ids("plabel")).toEqual(["-_-_-nlb-_-_-"]);
    sel.switch("plabel", null);
    expect(sel.count("plabel")).toBe(0);
  });
  it("should toggle", () => {
    expect(sel.count("plabel")).toBe(2);
    sel.switch("plabel", "-_-_-nlb-_-_-", plabelLabelIds, true);
    expect(sel.ids("plabel")).toEqual(["clabel__path/for/torrent/label"]);
    // swap current selection
    sel.switch("plabel", null, plabelLabelIds, true);
    expect(new Set(sel.ids("plabel"))).toEqual(
      new Set(["-_-_-nlb-_-_-", "clabel__path/for/torrent/secondlabel"])
    );
    sel.switch("plabel", "-_-_-nlb-_-_-", plabelLabelIds, true);
    expect(sel.ids("plabel")).toEqual(["clabel__path/for/torrent/secondlabel"]);
  });
  it("should range select", () => {
    expect(sel.count("pstate")).toBe(0);
    sel.switch("pstate", "-_-_-iac-_-_-", pstateLabelIds, false, true);
    expect(sel.count("pstate")).toBe(4);
    sel.switch("pstate", "-_-_-act-_-_-", pstateLabelIds, false, true);
    expect(sel.count("pstate")).toBe(3);
  });

  it("should adjust view to current", () => {
    const adjusted = () =>
      new Set(
        sel.adjustViewToCurrent(viewSelections, statisticPanelIds).ids("pview")
      );

    expect(sel.count("plabel")).toBe(2);
    expect(adjusted()).toEqual(adjusted());
    expect(adjusted()).toEqual(new Set(["pview_1", "pview_isnew"]));

    sel.select("pstate", ["-_-_-iac-_-_-"]);
    expect(adjusted()).toEqual(new Set(["pview_1", "pview_isnew"]));

    sel.select("pstate", ["-_-_-act-_-_-"]);
    expect(adjusted()).toEqual(new Set(["pview_isnew"]));

    sel.select("plabel", []);
    expect(adjusted()).toEqual(new Set(["pview_0", "pview_2"]));

    sel.select("plabel", ["-_-_-nlb-_-_-"]);
    expect(adjusted()).toEqual(new Set(["pview_isnew"]));

    sel.select("pstate", ["-_-_-iac-_-_-"]);
    expect(adjusted()).toEqual(new Set(["pview_1"]));

    sel.clear();
    expect(adjusted()).toEqual(new Set());
  });

  it("should adjust current to view", () => {
    const adjusted = () =>
      sel.adjustCurrentToView(viewSelections, statisticPanelIds).toConfig();
    expect(sel.count("plabel")).toBe(2);
    sel.select("pview", ["pview_isnew", "pview_1"]);
    expect(adjusted()).toMatchObject(adjusted());
    expect(adjusted()).toMatchObject({
      pstate: ["-_-_-iac-_-_-"],
      plabel: ["-_-_-nlb-_-_-"],
    });
    expect(new Set(sel.ids("pview"))).toEqual(new Set(["pview_1"]));

    sel.select("pview", ["pview_0", "pview_1", "pview_2"]);
    expect(adjusted()).toMatchObject({
      pstate: ["-_-_-act-_-_-", "-_-_-iac-_-_-"],
    });
    expect(new Set(sel.ids("pview"))).toEqual(
      new Set(["pview_0", "pview_1", "pview_2", "pview_isnew"])
    );

    sel.select("pview", ["pview_1", "pview_2"]);
    expect(adjusted()).toMatchObject({
      pstate: ["-_-_-act-_-_-", "-_-_-iac-_-_-"],
    });
    expect(new Set(sel.ids("pview"))).toEqual(
      new Set([
        // Not "view_0", i.e. no susequent adjustViewToCurrent call,
        // in order to avoid disruption during view selection
        "pview_1",
        "pview_2",
        "pview_isnew",
      ])
    );

    sel.clear();
    expect(adjusted()).toMatchObject({});
    expect(new Set(sel.ids("pview"))).toEqual(new Set());
  });
});

const sampleTorrentByIndex = (index, count) => {
  const maxUpload = 1 /* MiB */ << 20;
  const uploadActive = 0.1;
  const maxDownload = 10 /* MiB */ << 20;
  const downloadActive = 0.05;
  const minSize = 50 /* KiB */ << 10;
  const maxSize = 500 /* GiB */ * (1 << 30);
  const [xName, xName2, xName3, xDl, xDlActive, xUl, xUlActive, xSize] = [
    7867, 7873, 7877, 7879, 7883, 7901, 7907, 7919,
  ].map((prime) => (prime * (index + prime)) % count);
  // Exponential scaling with mostly small values
  const scaleExp = (x, minValue, maxValue) =>
    Math.round(minValue + Math.expm1(x * Math.log1p(maxValue - minValue)));
  const speed = (xactivity, activity, xSpeed, maxSpeed) =>
    xactivity < activity * count ? scaleExp(xSpeed / count, 100, maxSpeed) : 0;
  const char = (x, a) => String.fromCharCode(a.charCodeAt() + (x % 26));
  const yName = Math.max(xName, xName2);
  // Move x in [0,1] closer to 0.5
  const concentrate = (x) => 0.5 + 4 * Math.pow(x - 0.5, 3);
  return {
    name:
      // Name '[X]-yyyyy Z' with mostly equal x,y,z
      `[${char(xName, "A")}]-${char(yName, "a").repeat(5)} ${char(
        Math.min(xName3, yName),
        "A"
      )}`,
    // Label 'aaa/bbb/ccc' with limited characters
    label: [xName + xName2 + xName3, xName + xName2, xName]
      .filter((x) => x > count / 2)
      .map((x, i) => char(x % (1 + (x % (3 + 2 * i))), "a").repeat(3))
      .join("/"),
    ul: speed(xUlActive, uploadActive, xUl, maxUpload),
    dl: speed(xDlActive, downloadActive, xDl, maxDownload),
    size: scaleExp(concentrate(xSize / count), minSize, maxSize),
  };
};

const generateTorrents = (count) =>
  Object.fromEntries(
    [...Array(count).keys()].map((index) => [
      `${index}`,
      sampleTorrentByIndex(index, count),
    ])
  );
let statistic;
let quickSearchIds = new Set();
describe("Category list statistic", () => {
  beforeEach(() => {
    statistic = CategoryListStatistic.from(
      "pview",
      [
        PanelLabelSelection.fromConfig({ pstate: ["-_-_-act-_-_-"] }, [
          "pstate",
          "plabel",
          "psearch",
        ]),
      ],
      {
        pstate: [
          (_, torrent) => [
            torrent.dl >= 1024 || torrent.ul >= 1024
              ? "-_-_-act-_-_-"
              : "-_-_-iac-_-_-",
          ],
        ],
        plabel: [
          (_, torrent) => [
            torrent.label ? "clabel__" + torrent.label : "-_-_-nlb-_-_-",
          ],
        ],
        psearch: [
          (_, torrent) =>
            ["[A]", "[B]", "[A]-aa"]
              .map((start, i) => [start, i])
              .filter(([start]) => torrent.name.startsWith(start))
              .map(([_, i]) => `psearch_${i}`),
          {
            quick_search: (hash) => quickSearchIds.has(hash),
          },
        ],
      }
    );
  });
  it("should accumulate torrent statistic", () => {
    const torrentCount = 100;
    const torrents = generateTorrents(torrentCount);

    for (const [hash, torrent] of Object.entries(torrents)) {
      statistic.scan(hash, torrent);
    }
    expect(statistic.count).toBe(torrentCount);
    const { count, size, upload, download } = statistic;
    const newTorrent = sampleTorrentByIndex(torrentCount, torrentCount + 1);
    //log(statistic.panels.psearch);
    const prevBucket = statistic.lookup("psearch", "quick_search");
    const [bucketCount, bucketSize, bucketUpload, bucketDownload] = [
      "count",
      "size",
      "upload",
      "download",
    ].map((n) => prevBucket[n]);

    // Confirm torrent is added to its label bucket
    torrents[torrentCount] = newTorrent;
    quickSearchIds.add(String(torrentCount));
    statistic.rescan(torrents, "psearch", "quick_search");
    const bucket = statistic.lookup("psearch", "quick_search");
    expect(bucket.count).toBe(bucketCount + 1);
    expect(bucket.size).toBe(bucketSize + newTorrent.size);
    expect(bucket.upload).toBe(bucketUpload + newTorrent.ul);
    expect(bucket.download).toBe(bucketDownload + newTorrent.dl);

    statistic = statistic.empty();
    for (const [hash, torrent] of Object.entries(torrents)) {
      statistic.scan(hash, torrent);
    }

    // Verify total statistic includes newTorrent
    const checkTotal = () => {
      expect(statistic.count).toBe(count + 1);
      expect(statistic.size).toBe(size + newTorrent.size);
      expect(statistic.upload).toBe(upload + newTorrent.ul);
      expect(statistic.download).toBe(download + newTorrent.dl);
    };

    checkTotal();

    statistic.rescan(torrents, "plabel");
    statistic.rescan(torrents, "psearch");
    statistic.rescan(torrents, "psearch", "quick_search");

    checkTotal();
  });

  it("should collect torrent label tree", () => {
    const torrentCount = 100;
    const torrents = generateTorrents(torrentCount);

    for (const [hash, torrent] of Object.entries(torrents)) {
      statistic.scan(hash, torrent);
    }

    const torrentLabels = statistic
      .ids("plabel")
      .filter((labelId) => labelId.startsWith("clabel__"))
      .map((labelId) => labelId.substring(8))
      .concat(["Misc/Other/More", "Misc/Other/Less", "Flattenable/Path/Label"]);
    //log(torrentLabels);
    const tree = new TorrentLabelTree(torrentLabels);
    //log(tree.torrentLabels);
    const t1 = tree.list(false, false);
    log(t1);
    expect(t1.size).toBeGreaterThan(10);
    expect(t1.size).toBeLessThan(torrentCount);
    const t2 = tree.list(false, true);
    log(t2);
    expect(t2.size).toBeGreaterThan(10);
    expect(t2.size).toBeLessThan(t1.size);
    const t3 = tree.list(true, true);
    log(t3);
    expect(t3.size).toBeGreaterThan(10);
    expect(t3.size).toBeLessThan(t2.size);
    const t4 = tree.list(true, false);
    log(t4);
    expect(t4.size).toBe(t1.size);
  });

  it("should accumulate torrent view", () => {
    const torrentCount = 100;
    const torrents = generateTorrents(torrentCount);

    for (const [hash, torrent] of Object.entries(torrents)) {
      statistic.scan(hash, torrent);
    }
    //log(statistic.viewPanel.buckets);
    const viewBucket = statistic.lookup("pview", "pview_0");
    const pstateBucket = statistic.lookup("pstate", "-_-_-act-_-_-");
    expect(viewBucket.count).toBe(pstateBucket.count);
    expect(viewBucket.size).toBe(pstateBucket.size);
    expect(viewBucket.upload).toBe(pstateBucket.upload);
    expect(viewBucket.download).toBe(pstateBucket.download);
  });
});
