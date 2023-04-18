/*
 *     Base class for Speed Graph and Trafic Graph.
 *
 */
class rGraph {
  static legendLabels = {};
  static legendLabelTranslations = {};

  static showTooltip(x, y, contents) {
    let t = $("#tooltip");
    if (!t.length) {
      t = $("<div>")
        .attr("id", "tooltip")
        .addClass("graph_tab_tooltip")
        .hide()
        .appendTo("body")
        .fadeIn(200);
    }
    t.text(contents).css({
      top: y,
      left: x,
    });
  }

  static hideTooltip() {
    $("#tooltip").remove();
  }

  static legendCheckboxChanged(label, checkbox) {
    const graph = rGraph.legendLabels[label];
    if (graph) {
      graph.datasets.forEach((d, index) => {
        if (d.label === label) {
          graph.checked[index] = checkbox.checked;
        }
      });
      graph.draw();
    }
  }

  labelIsChecked(label) {
    return this.datasets.some(
      (d, index) => d.label === label && this.checked[index]
    );
  }

  create(aOwner, webuiView) {
    console.assert("datasets" in this, "datasets should be defined");
    this.webuiView = webuiView;

    // add checkboxes to upload/download legend
    for (const d of this.datasets) {
      console.assert(
        !(d.label in rGraph.legendLabels),
        `rGraph legend label "${d.label}" should be unique`
      );
      rGraph.legendLabels[d.label] = this;
      rGraph.legendLabelTranslations[d.label] = d.labelTranslation;
    }
    this.checked = this.datasets.map(() => true);

    // resize to same size as parent
    aOwner.width(aOwner.parent().width());
    aOwner.height(aOwner.parent().height());
    this.width = aOwner.width();
    this.height = aOwner.height();
    this._animationRequestId = 0;

    this._hoverItem = null;
    const onPlotHover = (event, pos, item) => {
      if (this._hoverItem !== item) {
        this._hoverItem = item;
        this.onHoverItemChanged(this._hoverItem);
      }
    };
    aOwner.on("plothover", onPlotHover.bind(this));

    this.legendFormatter = function (label) {
      return (
        '<input type="checkbox"' +
          ' style="position: absolute; left: -20px; transform: translate(0, -2px);"' +
          ` onchange="rGraph.legendCheckboxChanged('${label}', event.target)" ${
            rGraph.legendLabels[label]?.labelIsChecked(label) ? "checked" : ""
          }/>` +
          rGraph.legendLabelTranslations[label] ?? label
      );
    };
    this.badTextCache = true;
    this.plot = $.plot(aOwner, this.data, this.options);
  }

  onHoverItemChanged(item) {}

  update() {
    if (
      this.badTextCache &&
      (this.badTextCache = $($$("cover")).is(":visible"))
    ) {
      // avoid that flot caches incorrect text width/height
      this.draw();
      return;
    }
    const plot = this.plot;
    const opts = this.options;
    for (const [name, axis] of Object.entries(plot.getAxes())) {
      if (name in opts) {
        Object.assign(axis.options, opts[name]);
      }
    }

    const options = plot.getOptions();
    for (const [secName, sec] of Object.entries(opts)) {
      const secOpts = options[secName];
      for (const [k, v] of Object.entries(sec)) {
        secOpts[k] = v;
      }
    }
    const ph = plot.getPlaceholder();
    if (ph.width() !== this.width || ph.height() !== this.height) {
      ph.width(this.width);
      ph.height(this.height);
      plot.resize();
    }
    plot.setData(this.data);
    plot.setupGrid();
    plot.draw();
  }

  draw(force = false) {
    if (
      (force || !this.webuiView || theWebUI.activeView === this.webuiView) &&
      !this._animationRequestId
    ) {
      this._animationRequestId = window.requestAnimationFrame(() => {
        this._animationRequestId = 0;
        this.update();
      });
    }
  }

  get data() {
    return this.datasets.map((dataset, index) => ({
      ...dataset,
      data: this.checked[index] ? dataset.data : [],
    }));
  }

  get options() {
    const gridSel = $(".graph_tab_grid");
    const legendSel = $(".graph_tab_legend");
    return {
      colors: this.datasets.map((d) => d.color),
      lines: {
        show: true,
      },
      grid: {
        color: gridSel.css("color"),
        backgroundColor: gridSel.css("background-color"),
        borderWidth: parseInt(gridSel.css("border-width")),
        borderColor: gridSel.css("border-color"),
        hoverable: true,
      },
      legend: {
        labelFormatter: this.legendFormatter,
        color: legendSel.css("color"),
        borderColor: legendSel.css("border-color"),
        backgroundColor: legendSel.css("background-color"),
      },
      xaxis: {
        show: !this.badTextCache,
      },
      yaxis: {
        show: !this.badTextCache,
        min: 0,
        autoScale: "loose",
        minTickSize: 5 * 1024,
        tickFormatter: this.yTickFormatter.bind(this),
      },
    };
  }

  yTickFormatter(n) {
    return n;
  }

  resize(newWidth, newHeight) {
    if (!newWidth && !newHeight) {
      this.draw(true);
    } else if (
      (newWidth && this.width != newWidth) ||
      (newHeight && this.height != newHeight)
    ) {
      if (newWidth) this.width = Math.max(1, newWidth);
      if (newHeight) this.height = Math.max(1, newHeight);
      this.draw();
    }
  }
}

class rSpeedGraph extends rGraph {
  #xDragAnchor;
  #xDragDelta;

  create(aOwner, webuiView = "Speed") {
    this.maxZoomSeconds = 24 * 60 * 60;
    this.minZoomSeconds = 10;
    this.startSeconds = new Date().getTime() / 1000;
    this.seconds = this.startSeconds;
    this.viewSeconds = this.startSeconds;

    this.reset();

    this.down = {
      label: "speedgraph_dl",
      labelTranslation: theUILang.DL,
      data: [],
      color: "#1C8DFF",
    };
    this.up = {
      label: "speedgraph_ul",
      labelTranslation: theUILang.UL,
      data: [],
      color: "#009900",
    };
    this.datasets = [this.up, this.down];

    // zoom with mouse wheel
    const el = document.getElementById(aOwner.attr("id"));
    el.addEventListener("wheel", (event) => {
      const pointerX =
        event.clientX -
        this.plot.getCanvas().getClientRects()[0].x -
        this.plot.getPlotOffset().left;
      const plotWidth = this.plot.width();
      const pointerDelay =
        (this.maxSeconds * (plotWidth - pointerX)) / plotWidth;
      const oldMaxSeconds = this.maxSeconds;
      const oldMax = this.options.xaxis.max;
      this.setMaxSeconds(this.maxSeconds + this.xDeltaToSeconds(-event.deltaY));

      if (this.maxSeconds != oldMaxSeconds) {
        // zoom towards mouse pointer (meaning constant pointerDelay)
        const zoomFactor = this.maxSeconds / oldMaxSeconds;
        this.viewSeconds =
          oldMax +
          pointerDelay * (zoomFactor - 1) -
          this.xDeltaToSeconds(this.#xDragDelta);
      }

      this.draw();
    });

    // pan with mouse drag
    this.#xDragAnchor = null;
    this.#xDragDelta = 0;
    el.addEventListener("mousedown", (event) => {
      if (event.button == 0) this.#xDragAnchor = event.clientX;
    });
    el.addEventListener("mousemove", (event) => {
      if (this.#xDragAnchor !== null) {
        this.#xDragDelta = event.clientX - this.#xDragAnchor;
        this.draw();
      }
    });
    const dragComplete = () => {
      const newViewSeconds = Math.max(
        this.startSeconds + this.maxSeconds,
        this.viewSeconds + this.xDeltaToSeconds(this.#xDragDelta)
      );
      this.#xDragAnchor = null;
      this.#xDragDelta = 0;
      if (this.viewSeconds !== newViewSeconds) {
        this.viewSeconds = newViewSeconds;
        this.draw();
      }
    };
    el.addEventListener("mouseleave", dragComplete.bind(this));
    el.addEventListener("mouseup", (event) => {
      if (event.button === 0) {
        dragComplete();
      }
    });
    // reset pan with double click
    el.addEventListener("dblclick", (event) => {
      if (event.button === 0) {
        this.reset();
        this.draw();
      }
    });

    super.create(aOwner, webuiView);
    // this.#testData();
  }

  #testData() {
    this.setMaxSeconds(10);
    setInterval(() => {
      const t = new Date().getTime() / 3000;
      const mb = (Math.sin(t / 100) + 1) * (1 << 20);
      this.addData(
        (Math.sin(t) + 1) * mb,
        ([10, 9, 8, 7, 6, 5, 4, 3, 2, 1]
          .map((k) => Math.cos(k * t) / k)
          .reduce((a, b) => a + b, 0) +
          4) *
          mb
      );
    }, 100);
  }

  yTickFormatter(n) {
    return theConverter.speed(n);
  }

  reset() {
    this.viewSeconds = this.seconds;
    this.setMaxSeconds(
      Number.parseFloat(theWebUI.settings["webui.speedgraph.max_seconds"])
    );
  }

  onHoverItemChanged(item) {
    // show tooltip when hovering over datapoint
    const speed = (bt) =>
      theConverter.speed(bt) || theConverter.bytes(bt) + "/" + theUILang.s;
    if (item)
      rGraph.showTooltip(
        item.pageX,
        item.pageY - 28,
        (rGraph.legendLabelTranslations[item.series.label] ??
          item.series.label) +
          " " +
          this.timeFormat(item.datapoint[0]) +
          " = " +
          speed(item.datapoint[1])
      );
    else rGraph.hideTooltip();
  }

  timeFormat(n) {
    const dt = new Date(n * 1000);
    return [dt.getHours(), dt.getMinutes(), dt.getSeconds()]
      .map((num) => String(num).padStart(2, "0"))
      .join(":");
  }

  update() {
    const pointCount = this.up.data.length;
    if (pointCount > 0) {
      const now = this.up.data[pointCount - 1][0];
      if (
        this.#xDragDelta === 0 &&
        (this.seconds === this.viewSeconds ||
          // graph has caught up with view range
          (this.seconds < this.viewSeconds && now > this.viewSeconds))
      )
        this.viewSeconds = Math.max(now, this.startSeconds + this.maxSeconds);
      this.seconds = now;
    }
    super.update();
  }

  setMaxSeconds(maxSeconds) {
    this.maxSeconds = isNaN(maxSeconds)
      ? minSeconds
      : Math.min(
          this.maxZoomSeconds,
          Math.max(this.minZoomSeconds, maxSeconds)
        );
    this.tickSize = Math.round(this.maxSeconds / 10);
    this.viewSeconds = Math.min(
      this.seconds,
      Math.max(this.viewSeconds, this.startSeconds + this.maxSeconds)
    );
  }

  xDeltaToSeconds(xDelta) {
    return (
      (-xDelta * this.maxSeconds) / (this.plot ? this.plot.width() : this.width)
    );
  }

  get data() {
    const bSearch = (array, value) => {
      // search the leftmost value
      let l = 0;
      let r = array.length;
      while (l < r) {
        let m = Math.floor((l + r) / 2);
        if (array[m] < value) l = m + 1;
        else r = m;
      }
      return l;
    };

    const opts = this.options;
    const xpoints = this.datasets[0].data.map(([x]) => x);
    // assume points are x-sorted
    // find boundary points of [xmin, xmax]
    const maxIndex = Math.max(0, bSearch(xpoints, opts.xaxis.min - 1) - 1);
    const minIndex = bSearch(xpoints, opts.xaxis.max + 1);
    // assume ymin == 0 and no large changes to ymax
    const yNear = this.plot
      ? (1.5 * this.plot.getYAxes()[0].datamax) / this.plot.height()
      : 1;
    const xNear = this.plot ? (1.5 * this.maxSeconds) / this.plot.width() : 1;
    // we reduce the number of points to draw
    const reduceToVisiblePoints = (allPoints) => {
      if (allPoints.length === 0) return [];
      const points = allPoints.slice(maxIndex, minIndex + 1);
      const visiblePoints = [points[0]];
      let [px, py] = points[0];
      for (const point of points.slice(1)) {
        const [x, y] = point;
        if (Math.abs(x - px) > xNear || Math.abs(y - py) > yNear) {
          // keep next point if it is far enough from the previous point
          visiblePoints.push(point);
          [px, py] = point;
        }
      }
      return visiblePoints;
    };
    return this.datasets.map((dataset, index) => {
      const firstPoint = dataset.data[0];
      return {
        ...dataset,
        data: this.checked[index]
          ? dataset.data.length === 1
            ? // help visualize the first datapoint
              dataset.data.concat([[firstPoint[0] + 1, firstPoint[1]]])
            : reduceToVisiblePoints(dataset.data)
          : [],
      };
    });
  }

  get options() {
    const secondOffset = this.xDeltaToSeconds(this.#xDragDelta);
    const opts = super.options;
    return {
      ...opts,
      xaxis: {
        ...opts.xaxis,
        min: Math.max(
          this.startSeconds,
          this.viewSeconds + secondOffset - this.maxSeconds
        ),
        max: Math.max(
          this.viewSeconds + secondOffset,
          this.maxSeconds + this.startSeconds
        ),
        tickSize: this.tickSize,
        tickFormatter: this.timeFormat,
      },
    };
  }

  addData(upSpeed, downSpeed) {
    const now = new Date().getTime() / 1000;

    if (this.up && this.down) {
      this.up.data.push([now, upSpeed]);
      this.down.data.push([now, downSpeed]);
      if (this.up.data[0][0] + this.maxZoomSeconds < now) {
        this.up.data.splice(0, 1);
        this.down.data.splice(0, 1);
      }
      this.draw();
    }
  }
}
