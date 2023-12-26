/**
 * A panel label selection consisting of a set of selected labels for each panel.
 */
class PanelLabelSelection {
  static fromConfig(actLbls, panelIds) {
    const selection = new PanelLabelSelection();
    for (const panelId of panelIds) {
      selection.panelAdd(panelId);
      if (panelId in actLbls) {
        const lbl = actLbls[panelId];
        // Consider legacy single-label selection
        const labelIds = Array.isArray(lbl) ? lbl : lbl ? [lbl] : [];
        selection.select(panelId, labelIds);
        // Expect range anchor at first position
        selection.rangeAnchor(panelId, labelIds[0] ?? null);
      }
    }
    return selection;
  }

  constructor(activeLabelIds, rangeAnchors) {
    this.activeLabelIds = activeLabelIds ?? {};
    this.rangeAnchors = rangeAnchors ?? {};
  }

  toConfig() {
    return Object.fromEntries(
      Object.entries(this.activeLabelIds)
        .filter(([_, labelIds]) => labelIds.size)
        .map(([panelId, labelIds]) => [
          panelId,
          [...labelIds].sort(),
          this.rangeAnchors[panelId],
        ])
        .map(([panelId, labelIds, anchor]) => [
          panelId,
          // Move anchor to first position
          anchor
            ? [anchor, ...labelIds.filter((labelId) => labelId !== anchor)]
            : labelIds,
        ])
    );
  }

  rangeAnchor(panelId, anchor) {
    if (anchor !== undefined) {
      this.rangeAnchors[panelId] = anchor;
    }
    return this.rangeAnchors[panelId];
  }
  count(panelId) {
    return this.activeLabelIds[panelId].size;
  }
  ids(panelId) {
    return [...this.activeLabelIds[panelId]];
  }

  select(panelId, selectedIds) {
    this.activeLabelIds[panelId] = new Set(selectedIds);
    return this;
  }
  active(panelId, labelId) {
    return this.activeLabelIds[panelId].has(labelId);
  }
  isEmpty() {
    return Object.values(this.activeLabelIds).every(
      (selection) => selection.size === 0
    );
  }
  clear() {
    for (const selection of Object.values(this.activeLabelIds)) {
      selection.clear();
    }
    return this;
  }

  toggle(panelId, labelId, force) {
    const labelIds = this.activeLabelIds[panelId];
    const enable = force != null ? force : !labelIds.has(labelId);
    return enable ? labelIds.add(labelId) : labelIds.delete(labelId);
  }

  equal(selection) {
    return Object.entries(this.activeLabelIds).every(([panelId, labelIds]) => {
      const otherLabelIds = selection.activeLabelIds[panelId];
      return (
        labelIds.size === otherLabelIds.size &&
        [...labelIds].every((labelId) => otherLabelIds.has(labelId))
      );
    });
  }

  subset(greaterSelection) {
    return Object.entries(this.activeLabelIds).every(
      ([panelId, smallerLabelIds]) => {
        const greaterLabelIds = greaterSelection.activeLabelIds[panelId];
        return (
          !greaterLabelIds.size ||
          (smallerLabelIds.size &&
            greaterLabelIds.size >= smallerLabelIds.size &&
            [...smallerLabelIds].every((labelId) =>
              greaterLabelIds.has(labelId)
            ))
        );
      }
    );
  }

  switch(panelId, targetId, sortedLabelIds, toggle, range) {
    const labelIds = this.activeLabelIds[panelId];
    const anchor = this.rangeAnchors[panelId];
    let nextLabelIds = targetId ? [targetId] : [];

    if (targetId) {
      if (!range) {
        this.rangeAnchors[panelId] = targetId;
      }
      if (range && targetId !== anchor) {
        // Range selection: Select labelIds between anchor and target
        const a = anchor
          ? sortedLabelIds.findIndex((labelId) => labelId === anchor)
          : 0;
        const t = sortedLabelIds.findIndex((labelId) => labelId === targetId);
        const tt = t === -1 ? sortedLabelIds.length - 1 : t;
        const [start, end] = [Math.min(a, tt), Math.max(a, tt)];
        const rangeLabelIds = sortedLabelIds.slice(start, end + 1);

        if (toggle) {
          // Range toggle
          nextLabelIds = new Set(labelIds);
          const enable = labelIds.has(anchor);
          for (const labelId of rangeLabelIds) {
            if (enable) {
              nextLabelIds.add(labelId);
            } else {
              nextLabelIds.delete(labelId);
            }
          }
          nextLabelIds = [...nextLabelIds];
        } else {
          // Switch to range
          nextLabelIds = rangeLabelIds;
        }
      } else if (toggle) {
        // Toggle one label
        nextLabelIds = labelIds.has(targetId)
          ? [...labelIds].filter((labelId) => labelId !== targetId)
          : [...labelIds, targetId];
      }
    } else if (toggle) {
      // Toggling the 'All' label swaps the selection in the panel
      nextLabelIds = sortedLabelIds.filter((labelId) => !labelIds.has(labelId));
    } else {
      this.rangeAnchors[panelId] = null;
    }

    const change =
      labelIds.size !== nextLabelIds.length ||
      !nextLabelIds.every((labelId) => labelIds.has(labelId));
    if (change) {
      this.activeLabelIds[panelId] = new Set(nextLabelIds);
    }
    return change;
  }
  get panelIds() {
    return Object.keys(this.activeLabelIds);
  }
  panelAdd(...panelIds) {
    for (const panelId of panelIds) {
      if (!(panelId in this.activeLabelIds)) {
        this.activeLabelIds[panelId] = new Set();
        this.rangeAnchors[panelId] = null;
      }
    }
    return this;
  }
  panelRemove(...panelIds) {
    for (const panelId of panelIds) {
      delete this.activeLabelIds[panelId];
      delete this.rangeAnchors[panelId];
    }
    return this;
  }

  //
  // View selection <--adjust--> Current Selection
  //

  withReducedPanels(panelIds) {
    return new PanelLabelSelection(
      Object.fromEntries(
        panelIds.map((panelId) => [
          panelId,
          new Set(this.activeLabelIds[panelId]),
        ])
      ),
      Object.fromEntries(
        panelIds.map((panelId) => [panelId, this.rangeAnchors[panelId]])
      )
    );
  }

  toggled(panelId, labelId, force) {
    this.toggle(panelId, labelId, force);
    return this;
  }

  adjustCurrentToView(viewSelections, viewControlledPanelIds) {
    for (const panelId of viewControlledPanelIds) {
      const viewLabelIds = this.ids("pview")
        .filter((viewId) => viewId !== "pview_isnew")
        .map((viewId) => viewSelections[Number(viewId.split("_").at(-1))])
        .filter((viewSelection) => viewSelection)
        .map((viewSelection) =>
          // View selection is allowed to have missing panel ids
          panelId in viewSelection.activeLabelIds
            ? viewSelection.ids(panelId)
            : []
        );
      this.select(
        panelId,
        // Select 'All' if there is a view which selects 'All'
        viewLabelIds.some((labelIds) => !labelIds.length)
          ? []
          : viewLabelIds.flat()
      );
      this.rangeAnchor(panelId, null);
    }
    const currentSelection = this.withReducedPanels(viewControlledPanelIds);
    this.toggle(
      "pview",
      "pview_isnew",
      currentSelection.isNewView(viewSelections)
    );
    return this;
  }

  adjustViewToCurrent(viewSelections, viewControlledPanelIds) {
    // A view is active if it is a subset of the current selection.
    // The 'All' view is only active with a empty current selection.
    const currentSelection = this.withReducedPanels(viewControlledPanelIds);
    this.select(
      "pview",
      currentSelection.isEmpty()
        ? []
        : viewSelections
            .map((viewSelection, viewIndex) => [viewSelection, viewIndex])
            .filter(([viewSelection]) => viewSelection.subset(currentSelection))
            .map(([_, viewIndex]) => `pview_${viewIndex}`)
            .concat(
              currentSelection.isNewView(viewSelections) ? ["pview_isnew"] : []
            )
    );
    this.rangeAnchor("pview", null);
    return this;
  }

  isNewView(viewSelections) {
    // If the current selection does not equal an existing view, it is new.
    // (A new view may be saved as a 'New View')
    return (
      !this.isEmpty() &&
      !viewSelections.some((viewSelection) => viewSelection.equal(this))
    );
  }
}

class TorrentStatisticBucket {
  constructor() {
    this.hashes = new Set();
    this.size = 0;
    this.download = 0;
    this.upload = 0;
  }

  get count() {
    return this.hashes.size;
  }

  add(hash, torrent) {
    const added = this.hashes.add(hash);
    if (added) {
      this.size += torrent.size;
      this.upload += torrent.ul;
      this.download += torrent.dl;
    }
    return added;
  }
  contains(hash) {
    return this.hashes.has(hash);
  }
}

class CategoryPanelStatistic {
  constructor(getLabelIdsFn, labelIdPredicates) {
    this.buckets = new Map();
    this.getLabelIdsFn = getLabelIdsFn;
    this.labelIdPredicates = labelIdPredicates ?? {};
  }

  getLabelIds(hash, torrent) {
    return this.getLabelIdsFn(hash, torrent).concat(
      Object.entries(this.labelIdPredicates)
        .filter(([_, predicate]) => predicate(hash, torrent))
        .map(([labelId]) => labelId)
    );
  }

  empty() {
    return new this.constructor(this.getLabelIdsFn, this.labelIdPredicates);
  }

  add(hash, torrent, labelId) {
    const bucket = this.buckets.get(labelId) ?? new TorrentStatisticBucket();
    const added = bucket.add(hash, torrent);
    this.buckets.set(labelId, bucket);
    return added;
  }

  rescan(torrents, labelId) {
    let getLabelIds = this.getLabelIds.bind(this);
    if (labelId && labelId in this.labelIdPredicates) {
      const predicate = this.labelIdPredicates[labelId];
      this.buckets.set(labelId, new TorrentStatisticBucket());
      getLabelIds = (hash, torrent) =>
        predicate(hash, torrent) ? [labelId] : [];
    } else {
      this.buckets = new Map();
    }
    for (const [hash, torrent] of Object.entries(torrents)) {
      for (const lId of getLabelIds(hash, torrent)) {
        this.add(hash, torrent, lId);
      }
    }
    return this;
  }

  scan(hash, torrent) {
    for (const labelId of this.getLabelIds(hash, torrent)) {
      this.add(hash, torrent, labelId);
    }
    return this;
  }

  lookup(labelId) {
    return this.buckets.get(labelId) ?? new TorrentStatisticBucket();
  }

  selected(hash, selectedLabelIds) {
    return (
      !selectedLabelIds.length ||
      selectedLabelIds.some((labelId) =>
        this.buckets.get(labelId)?.contains(hash)
      )
    );
  }
}

class CategoryListStatistic extends TorrentStatisticBucket {
  static from(viewPanelId, viewSelections, panelsObj) {
    return new this(
      viewPanelId,
      viewSelections,
      Object.fromEntries(
        Object.entries(panelsObj).map(
          ([panelId, [getLabelIdsFn, labelIdPredicates]]) => [
            panelId,
            new CategoryPanelStatistic(getLabelIdsFn, labelIdPredicates),
          ]
        )
      )
    );
  }

  panelAdd(panelId, getLabelIdsFn, labelIdPredicates) {
    this._panels[panelId] = new CategoryPanelStatistic(
      getLabelIdsFn,
      labelIdPredicates
    );
  }

  panelRemove(panelId) {
    delete this._panels[panelId];
  }

  constructor(viewPanelId, viewSelections, panels) {
    super();
    this._viewPanelId = viewPanelId;
    this._viewSelections = viewSelections;
    this._viewPanel = new CategoryPanelStatistic((hash) =>
      this._viewSelections
        .map((viewSelection, viewIndex) => [viewSelection, viewIndex])
        .filter(([viewSelection]) => this.selected(hash, viewSelection))
        .map(([_, viewIndex]) => this.indexToViewId(viewIndex))
    );
    this._panels = panels;
  }

  /**
   * @param {PanelLabelSelection[]} viewSelections
   */
  set viewSelections(viewSelections) {
    this._viewSelections = viewSelections;
  }

  indexToViewId(index) {
    return `${this._viewPanelId}_${index}`;
  }

  viewIdToIndex(viewId) {
    return Number(viewId.split("_").at(-1));
  }

  /**
   * Rescan torrents.
   * @param {object} torrents - Same torrents that were used for `scan`
   * @param {string} panelId - panelId with a change in its filtering
   * @param {string?} labelId - optional labelId with a change in its filtering
   */
  rescan(torrents, panelId, labelId) {
    if (panelId !== this._viewPanelId) {
      this._panels[panelId].rescan(torrents, labelId);
    }
    this._viewPanel.rescan(torrents);
  }

  empty() {
    return new this.constructor(
      this._viewPanelId,
      this._viewSelections,
      Object.fromEntries(
        Object.entries(this._panels).map(([panelId, panel]) => [
          panelId,
          panel.empty(),
        ])
      )
    );
  }

  /**
   * Scan a single torrent and accumulate its statistical data.
   * @param {string} hash - torrent hash
   * @param {object} torrent - scanned torrent
   */
  scan(hash, torrent) {
    if (super.add(hash, torrent)) {
      for (const panel of Object.values(this._panels)) {
        panel.scan(hash, torrent);
      }
      this._viewPanel.scan(hash, torrent);
    }
  }
  ids(panelId) {
    return [...this._panels[panelId]?.buckets.keys()];
  }

  selected(hash, panelLabelSelection) {
    return Object.entries(this._panels).every(([panelId, panel]) =>
      panel.selected(hash, panelLabelSelection.ids(panelId))
    );
  }

  lookup(panelId, labelId) {
    const panelStat =
      panelId === this._viewPanelId ? this._viewPanel : this._panels[panelId];
    return panelStat.lookup(labelId);
  }

  get panelIds() {
    return Object.keys(this._panels);
  }
  hasPanel(panelId) {
    return panelId in this._panels;
  }
}
class TextSearch {
  constructor(text) {
    this.text = text;
    this.pattern = new RegExp(
      text.replace(/[-[\]{}()+?.,\\^$|#\s]/g, "\\$&").replace("*", ".+"),
      "i"
    );
  }

  match(name) {
    return this.pattern.test(name);
  }
}

class TorrentLabelTree {
  constructor(torrentLabels) {
    if (!(torrentLabels instanceof Set)) {
      torrentLabels = new Set(torrentLabels);
    }
    // Build tree from torrent labels/paths
    const labelTree = {};
    for (const label of torrentLabels) {
      let node = labelTree;
      for (const pathSegment of label.split("/")) {
        if (!(pathSegment in node)) {
          node[pathSegment] = {};
        }
        node = node[pathSegment];
      }
    }
    const traverse = (node, path = [], hasNext = [], level = 0) =>
      Object.keys(node)
        .sort()
        .flatMap((name, i) => {
          const nextPath = path.concat(name);
          const nextHasNext = hasNext.concat(
            i !== Object.keys(node).length - 1
          );
          const child = node[name];
          const childCount = Object.keys(child).length;

          return [
            [
              nextPath.join("/"),
              nextPath,
              nextHasNext,
              level,
              /* can be flattened if one child */ childCount === 1,
            ],
            ...traverse(
              node[name],
              nextPath,
              nextHasNext,
              childCount <= 1 ? level : nextPath.length
            ),
          ];
        });
    this.torrentLabels = new Map(
      traverse(labelTree).map(
        ([torrentLabel, path, hasNext, level, oneChild]) => [
          torrentLabel,
          {
            path,
            hasNext,
            level,
            oneChild,
            empty: !torrentLabels.has(torrentLabel),
          },
        ]
      )
    );
  }

  /**
   * Display torrent label as a tree.
   * @param {boolean} flat - Always show the complete label paths.
   * @param {boolean} hideEmpty - If possible, hide empty label paths.
   */
  prefixSuffix(torrentLabel, flat, hideEmpty) {
    const { empty, path, level, oneChild, hasNext } =
      this.torrentLabels.get(torrentLabel);
    const wantHide = empty && hideEmpty;
    const hidePossible = flat || oneChild;

    const shownLevel = flat ? 0 : hideEmpty ? level : path.length - 1;
    return wantHide && hidePossible
      ? null
      : {
          prefix: this.prefix({
            hasNext: hasNext.slice(0, shownLevel + 1),
            level: shownLevel,
          }),
          text: path.slice(shownLevel).join("/"),
        };
  }

  list(flat, hideEmpty) {
    return new Map(
      [...this.torrentLabels.keys()]
        .map((torrentLabel) => [
          torrentLabel,
          this.prefixSuffix(torrentLabel, flat, hideEmpty),
        ])
        .filter(([_, info]) => info != null)
    );
  }
  prefix({ level, hasNext }) {
    return hasNext
      .slice(1)
      .map((next, l) =>
        next ? (l + 1 === level ? "├" : "│") : l + 1 === level ? "└" : " "
      )
      .join("");
  }
}

export {
  PanelLabelSelection,
  CategoryListStatistic,
  TextSearch,
  TorrentLabelTree,
};
