import {
  PanelLabelSelection,
  CategoryListStatistic,
  TextSearch,
  TorrentLabelTree,
} from "./panel.js";

/**
 * The CategoryList controls which torrents should be shown in the torrent table.
 *
 */
export class CategoryList {
  constructor(props) {
    Object.assign(this, props);

    this.selection = null;
    this.searches = [];
    this.views = [];
    this.quickSearch = {
      search: { val: "" },
      debounce: { timeoutId: 0, delayMs: 220 },
    };
    this.statistic = CategoryListStatistic.from("pview", this.viewSelections, {
      pstate: [
        (_, torrent) => [
          // Main group (Completed / Downloading / Stopped)
          (torrent.done >= 1000)
            ? "-_-_-com-_-_-" // Completed (even if error)
            : (torrent.state & this.dStatus.paused)
            ? "-_-_-wfa-_-_-" // Truly paused (Stopped)
            : (torrent.state & this.dStatus.started)
            ? "-_-_-dls-_-_-" // Actively downloading (Started, not Paused)
            : "-_-_-wfa-_-_-", // Otherwise fallback to Stopped

          // Activity group (Active / Inactive)
          (torrent.state & this.dStatus.started)
            ? ((torrent.dl >= 1024 || torrent.ul >= 1024)
              ? "-_-_-act-_-_-" // Active: started + upload/download happening
              : "-_-_-iac-_-_-") // Inactive: started but idle (no transfer)
            : null, // No activity group for completed or stopped
        ],

        // Special case for Errors (only if not completed)
        {
          "-_-_-err-_-_-": (_, torrent) => torrent.state & this.dStatus.error,
        },
      ],
      plabel: [
        (_, torrent) => [
          torrent.label ? "clabel__" + torrent.label : "-_-_-nlb-_-_-",
        ],
      ],

      psearch: [
        (_, torrent) =>
          this.searches
            .map((_, searchIndex) => [_, searchIndex])
            .filter(([search]) => search.match(torrent.name))
            .map(([_, searchIndex]) => `psearch_${searchIndex}`),
        {
          quick_search: (_, torrent) => this.matchQuickSearch(torrent.name),
        },
      ],
    });
    this.refreshPanel = {
      pview: () => [
        this.updatedStatisticEntry("pview", "pview_all"),
        ...this.views.map((view, i) =>
          this.updatedStatisticEntry("pview", this.statistic.indexToViewId(i), {
            text: view.name,
            icon: view.name.charAt(0),
          })
        ),
      ],
      pstate: (attribs) =>
        [...attribs.keys()].map((labelId) =>
          this.updatedStatisticEntry("pstate", labelId)
        ),
      plabel: (attribs) => [
        ...[...attribs.keys()]
          .filter((labelId) => !labelId.startsWith("clabel__"))
          .map((labelId) => this.updatedStatisticEntry("plabel", labelId)),
        ...[...this.torrentLabelTree.torrentLabels.keys()]
          .map((torrentLabel) => ["clabel__" + torrentLabel, torrentLabel])
          .map(([labelId, torrentLabel]) =>
            this.updatedStatisticEntry(
              "plabel",
              labelId,
              this.torrentLabelTree.prefixSuffix(
                torrentLabel,
                !this.settings["webui.show_label_path_tree"],
                !this.settings["webui.show_empty_path_labels"]
              ) ?? {},
              torrentLabel
            )
          ),
      ],
      psearch: () => [
        this.updatedStatisticEntry("psearch", "psearch_all"),
        ...this.searches.map((search, i) =>
          this.updatedStatisticEntry("psearch", `psearch_${i}`, {
            text: search.text,
            icon: "search",
          })
        ),
      ],
    };
    this.configured = false;
  }

  config(settings) {
    if (this.configured) {
      console.warn("Category list is only configured once");
      return;
    }
    this.settings = settings;
    console.assert(
      [
        "webui.closed_panels",
        "webui.category_panels",
        "webui.open_tegs.last",
        "webui.selected_labels.keep",
        "webui.selected_labels.last",
        "webui.selected_labels.views",
      ].every((key) => key in settings),
      "should find required setting values"
    );
    if (this.settings["webui.open_tegs.keep"]) {
      // Restore text searches
      for (const text of this.settings["webui.open_tegs.last"]) {
        this.addTextSearch(text, true);
      }
    }
    const configChanged = this.updatePanels();
    const mapLegacyActLbls = (actLbls) =>
      Object.fromEntries(
        Object.entries(actLbls).map(([panelId, labelIds]) => [
          mapLegacyPanelId(panelId),
          labelIds.map(mapLegacyLabelId),
        ])
      );

    // Restore selection from settings
    this.selection = PanelLabelSelection.fromConfig(
      this.settings["webui.selected_labels.keep"]
        ? mapLegacyActLbls(this.settings["webui.selected_labels.last"])
        : {},
      this.sortedPanelIds
    );
    // Init views
    this.views = this.settings["webui.selected_labels.views"].map((view) => ({
      name: view.name,
      selection: PanelLabelSelection.fromConfig(
        mapLegacyActLbls(view.labels),
        this.statistic.panelIds
      ),
    }));
    this.selection.adjustViewToCurrent(
      this.viewSelections,
      this.statistic.panelIds
    );
    this.statistic.viewSelections = this.viewSelections;

    this.configured = true;
    if (configChanged) {
      this.onConfigChangeFn();
    }
    this.syncFn();
  }

  updatedStatisticEntry(panelId, labelId, attrs, titleText) {
    attrs = { ...this.panelLabelAttribs[panelId].get(labelId), ...attrs };
    const bucket =
      labelId === `${panelId}_all`
        ? this.statistic
        : this.statistic.lookup(panelId, labelId);
    const sizeString = this.byteSizeToStringFn(bucket.size);
    return [
      labelId,
      {
        ...attrs,
        count: String(bucket.count),
        size: this.showSize(panelId) && bucket.size > 0 ? sizeString : null,
        title: `${titleText || attrs.text} (${bucket.count} ; ${sizeString})`,
        selected: this.isLabelIdSelected(panelId, labelId),
      },
    ];
  }

  get viewSelections() {
    return this.views.map((view) => view.selection);
  }

  onViewsChange() {
    this.settings["webui.selected_labels.views"] = this.views.map((view) => ({
      name: view.name,
      labels: view.selection.toConfig(),
    }));
    this.selection.adjustCurrentToView(
      this.viewSelections,
      this.statistic.panelIds
    );
    this.statistic.viewSelections = this.viewSelections;
    this.rescan("pview");
    this.refresh("pview");
    this.onSelectionChange("pview");
  }

  selectionActive(panelId, labelId) {
    return this.selection.active(panelId, labelId);
  }

  showSize(panelId) {
    return (
      {
        pview: this.settings["webui.show_viewlabelsize"],
        pstate: this.settings["webui.show_statelabelsize"],
        psearch: this.settings["webui.show_searchlabelsize"],
      }[panelId] ?? this.settings["webui.show_labelsize"]
    );
  }

  get sortedPanelIds() {
    return this.settings["webui.category_panels"]
      .map(mapLegacyPanelId)
      .filter((panelId) => panelId in this.panelAttribs);
  }

  updatePanels() {
    // Expand 'webui.category_panels' to include all panelIds (in panelAttribs)
    const oldPanelIds = new Set(this.sortedPanelIds);
    const newPanelIds = Object.keys(this.panelAttribs).filter(
      (panelId) => !oldPanelIds.has(panelId)
    );
    if (newPanelIds.length) {
      this.settings["webui.category_panels"] =
        this.sortedPanelIds.concat(newPanelIds);
    }
    // Sort panelAttribs and panelLabelAttribs by 'webui.category_panels'
    this.panelAttribs = Object.fromEntries(
      this.sortedPanelIds.map((panelId) => [
        panelId,
        this.panelAttribs[panelId],
      ])
    );
    this.panelLabelAttribs = Object.fromEntries(
      this.sortedPanelIds.map((panelId) => [
        panelId,
        this.panelLabelAttribs[panelId],
      ])
    );
    // Read panel closed from config
    for (const [panelId, attribs] of Object.entries(this.panelAttribs)) {
      attribs.closed = this.panelClosed(panelId);
    }
    return newPanelIds.length > 0;
  }

  addPanel(panelId, name, labelAttribs, statisticInit) {
    if (panelId in this.panelAttribs) {
      throw Error(`Panel '${panelId}' has already been added!`);
    }
    this.panelAttribs[panelId] = { text: name };
    this.panelLabelAttribs[panelId] = new Map(labelAttribs);

    if (statisticInit) {
      this.statistic.panelAdd(panelId, ...statisticInit);
    }
    if (this.configured) {
      this.selection.panelAdd(panelId);
      if (this.statistic.hasPanel(panelId)) {
        // New view controlled panel
        for (const view of this.views) {
          view.selection.panelAdd(panelId);
        }
      }
      this.updatePanels();
      this.syncFn();
    }
  }

  removePanel(panelId) {
    if (!(panelId in this.panelAttribs)) {
      return;
    }
    delete this.panelAttribs[panelId];
    delete this.panelLabelAttribs[panelId];
    this.statistic.panelRemove(panelId);

    if (this.configured) {
      this.selection.panelRemove(panelId);
      for (const view of this.views) {
        view.selection.panelRemove(panelId);
      }
      this.updatePanels();
      this.syncFn();
    }
  }

  setPanelClosed(panelId, closed, fromAtrribs) {
    if (this.panelClosed(panelId, closed) !== closed) {
      this.settings["webui.closed_panels"][panelId] = closed;
      this.panelAttribs[panelId].closed = closed;
      if (!fromAtrribs) {
        this.syncFn();
      }
      this.onConfigChangeFn();
    }
  }

  panelClosed(panelId) {
    return this.settings["webui.closed_panels"][panelId];
  }

  //
  // Views
  //

  saveNewView() {
    this.views.push({
      name: this.theUILang.NewView,
      selection: this.selection
        .withReducedPanels(this.statistic.panelIds)
        .toggled("psearch", "quick_search", false),
    });
    this.onViewsChange();
  }

  removeActiveViews() {
    // Remove previously selected view rows
    this.views = this.views.filter(
      (_, i) => !this.selection.active("pview", this.statistic.indexToViewId(i))
    );
    this.selection.select("pview", []);
    this.onViewsChange();
  }

  renameView(viewId, viewName) {
    const view = this.views[this.statistic.viewIdToIndex(viewId)];
    if (viewName !== view.name) {
      view.name = viewName;
      this.onViewsChange();
    }
  }

  moveView(viewId, action) {
    // Move view according to action (to targetIndex)
    const viewIndex = this.statistic.viewIdToIndex(viewId);
    const viewCount = this.views.length;
    if (viewIndex < viewCount) {
      const targetIndex =
        {
          top: 0,
          up: Math.max(viewIndex - 1, 0),
          down: Math.min(viewIndex + 1, viewCount),
          bottom: viewCount - 1,
        }[action] ?? viewIndex;
      this.views.splice(targetIndex, 0, this.views.splice(viewIndex, 1)[0]);
      this.selection.select("pview", [
        this.statistic.indexToViewId(targetIndex),
      ]);
      this.onViewsChange();
    }
  }

  //
  // Labels
  //

  selected(hash) {
    return this.statistic.selected(hash, this.selection);
  }

  rescan(panelId, labelId) {
    const torrents = this.borrowTorrentsFn();
    this.statistic.rescan(torrents, panelId, labelId);
  }

  switchLabel(panelId, targetId, toggle = false, range = false) {
    const change = this.selection.switch(
      panelId,
      targetId !== `${panelId}_all` ? targetId : null,
      [...this.panelLabelAttribs[panelId].keys()].filter(
        (labelId) => labelId !== panelId + "_all"
      ),
      toggle,
      range
    );
    if (change) {
      this.onSelectionChange(panelId);
    }
    return change;
  }

  refreshAndSyncPanel(panelId, prunedSelection) {
    this.refresh(panelId);
    if (prunedSelection) {
      this.syncWithPrunedSelection(panelId);
    } else {
      this.syncFn();
    }
  }

  syncAfterScan() {
    // Rebuild torrent label tree
    const torrentLabels = this.statistic
      .ids("plabel")
      .filter((labelId) => labelId.startsWith("clabel__"))
      .map((labelId) => labelId.substring(8));
    this.torrentLabelTree = new TorrentLabelTree(torrentLabels);

    // Refresh all panels affected by the statistic scan
    this.refresh("pview", ...this.statistic.panelIds);
    this.syncWithPrunedSelection("plabel");
  }

  syncWithPrunedSelection(...pruneSelectionPanelIds) {
    const prunedPanelIds = [];
    for (const panelId of pruneSelectionPanelIds) {
      // Remove non-existent selected labels (to potentially show 'All' label as selected)
      const count = this.selection.count(panelId);
      const limitedSelection = this.selection
        .ids(panelId)
        .filter((labelId) => this.panelLabelAttribs[panelId].has(labelId));
      if (limitedSelection.length < count) {
        this.selection.select(panelId, limitedSelection);
        prunedPanelIds.push(panelId);
      }
    }
    if (prunedPanelIds.length) {
      this.onSelectionChange(...prunedPanelIds);
    } else {
      this.syncFn();
    }
  }

  isLabelIdSelected(panelId, labelId) {
    return labelId === `${panelId}_all`
      ? this.selection.count(panelId) === 0
      : this.selection.active(panelId, labelId);
  }

  onSelectionChange(...panelIds) {
    if (panelIds.every((panelId) => panelId === "pview")) {
      this.selection.adjustCurrentToView(
        this.viewSelections,
        this.statistic.panelIds
      );
      this.refresh(...this.statistic.panelIds);
    } else if (panelIds.some((panelId) => this.statistic.hasPanel(panelId))) {
      this.selection.adjustViewToCurrent(
        this.viewSelections,
        this.statistic.panelIds
      );
      if (!panelIds.includes("pview")) {
        panelIds.push("pview");
      }
    }

    this.refresh(...panelIds);
    this.syncFn();
    if (panelIds.some((panelId) => panelId === "pview")) {
      // Current selection is only saved for this.statistic.panelIds
      this.settings["webui.selected_labels.last"] = this.selection
        .withReducedPanels(this.statistic.panelIds)
        .toggled("psearch", "quick_search", false)
        .toConfig();
      this.onSelectionChangeFn();
      this.onConfigChangeFn();
    }
  }

  refresh(...panelIds) {
    for (const panelId of panelIds) {
      this.panelLabelAttribs[panelId] = new Map(
        this.refreshPanel[panelId](this.panelLabelAttribs[panelId])
      );
    }
  }

  quickSearchActive() {
    return this.selection.active("psearch", "quick_search");
  }

  currentViewIsNew() {
    return (
      !this.quickSearchActive() && this.selection.active("pview", "pview_isnew")
    );
  }

  resetSelection() {
    this.selection.clear();
    this.onSelectionChange(...this.selection.panelIds);
  }

  //
  // Quick Search
  //
  setQuickSearch(searchString) {
    if (!this.configured) return;
    const qsd = this.quickSearch.debounce;
    if (qsd.timeoutId) {
      clearTimeout(qsd.timeoutId);
      qsd.timeoutId = 0;
    }
    if (searchString) {
      this.quickSearch.search = new TextSearch(searchString);
      qsd.timeoutId = setTimeout(() => {
        this.rescan("psearch", "quick_search");
        // Reset search selection when using quick search
        if (!this.switchLabel("psearch", "quick_search")) {
          this.onSelectionChangeFn();
        }
      }, qsd.delayMs);
    } else if (this.selection.toggle("psearch", "quick_search", false)) {
      this.onSelectionChange("psearch");
    }
  }

  matchQuickSearch(torrentName) {
    return (
      this.quickSearch.search.text && this.quickSearch.search.match(torrentName)
    );
  }

  //
  // Text Search
  //
  onSearchesChanged() {
    this.settings["webui.open_tegs.last"] = this.searches.map(
      (search) => search.text
    );
    this.rescan("psearch");
    this.refresh("psearch");
    this.onSelectionChange("psearch");
  }

  addTextSearch(text, noPanelChange) {
    const change =
      text && this.searches.every((search) => search.text !== text);
    if (change) {
      this.searches.push(new TextSearch(text));
      if (!noPanelChange) {
        this.selection.toggle("psearch", "quick_search", false);
        this.selection.toggle(
          "psearch",
          `psearch_${this.searches.length - 1}`,
          true
        );
        this.onSearchesChanged();
      }
    }
    return change;
  }

  removeActiveTextSearches() {
    const removedSearchIndices = new Set(
      this.selection.ids("psearch").map((searchId) => {
        const [_, searchIndex] = searchId.split("_", 2);
        return Number(searchIndex);
      })
    );

    this.searches = this.searches.filter(
      (_, i) => !removedSearchIndices.has(i)
    );
    this.selection.select("psearch", []);
    this.onSearchesChanged();
  }

  removeAllTextSearches() {
    this.searches = [];
    this.selection.select("psearch", []);
    this.onSearchesChanged();
  }

  //
  // Context Menu
  //
  contextMenuEntries(panelId, labelId) {
    return (
      {
        psearch: (this.selection.count("psearch") > 0
          ? [[this.theUILang.removeTeg, () => this.removeActiveTextSearches()]]
          : []
        ).concat([
          [this.theUILang.removeAllTegs, () => this.removeAllTextSearches()],
        ]),
        pview:
          this.selection.count("pview") > 0
            ? [
                [
                  this.theUILang.RenameView,
                  () =>
                    this.renameViewDialogFn(
                      labelId,
                      this.views[this.statistic.viewIdToIndex(labelId)]?.name ??
                        ""
                    ),
                ],
                [
                  CMENU_CHILD,
                  this.theUILang.MoveView.base,
                  ["top", "up", "down", "bottom"].map((action) => [
                    this.theUILang.MoveView[action],
                    () => this.moveView(labelId, action),
                  ]),
                ],
                [
                  this.theUILang.RemoveActiveViews,
                  () => this.removeActiveViews(),
                ],
              ]
            : [],
      }[panelId] ?? []
    );
  }
}

//
// Legacy Mappings
//

function mapLegacyPanelId(panelId) {
  return panelId.endsWith("_cont")
    ? panelId === "flabel_cont"
      ? "psearch"
      : // Remove legacy _cont suffix
        panelId.slice(0, -5)
    : panelId;
}

function mapLegacyLabelId(labelId) {
  return labelId.startsWith("teg_") ? "psearch_" + labelId.slice(4) : labelId;
}
