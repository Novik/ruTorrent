customElements.define(
  "category-panel",
  class extends CustomHTMLElement {
    static template = document.getElementById("category-panel-template");
    static stringAttributeNames = ["text"];
    static booleanAttributeNames = ["closed"];
    static attributeChangeCallbacks = {
      closed: function () {
        this.dispatchEvent(new Event("close"));
      },
      text: function (_, newValue) {
        this.textEl.textContent = newValue;
      },
    };

    constructor() {
      super();
      const shadow = this.customAttachShadow();
      this.parts.heading.addEventListener("click", () => {
        this.closed = !this.closed;
        return false;
      });
      this.textEl = shadow.querySelector(".text");
      this.customDefineAttributes();
    }
  }
);

customElements.define(
  "panel-label",
  class extends CustomHTMLElement {
    static template = document.getElementById("panel-label-template");
    static stringAttributeNames = ["prefix", "icon", "text", "count", "separator", "size"];
    static booleanAttributeNames = ["selected"];
    static attributeChangeCallbacks = {
      prefix: function (_, newValue) {
        const el = this.parts.prefix;
        if (newValue != null) {
          el.hidden = false;
          const divs = el.children;
          el.replaceChildren(
            ...newValue.split("").map((c, i) => {
              const e =
                i < divs.length ? divs[i] : document.createElement("div");
              e.textContent = c;
              return e;
            })
          );
        } else {
          el.hidden = true;
        }
      },
      icon: function (oldValue, newValue) {
        const el = this.parts.icon;
        if (oldValue) {
          if (oldValue.startsWith("url:")) {
            el.style.backgroundImage = null;
            el.classList.remove("icon-by-url");
          } else if (oldValue.length === 1) {
            el.classList.remove("icon-letter");
            el.replaceChildren();
          }
        }
        if (newValue) {
          if (newValue.startsWith("url:")) {
            el.style.backgroundImage = `url("${newValue.slice(4)}")`;
            el.classList.add("icon-by-url");
          } else if (newValue.length == 1) {
            const span = document.createElement("span");
            el.classList.add("icon-letter");
            span.textContent = newValue;
            el.appendChild(span);
          }
        }
      },
      text: function (_, newValue) {
        this.parts.text.textContent = newValue ?? "";
      },
      size: function (_, newValue) {
        this._hideOrSetText(this.parts.size, newValue);
        this._hideOrSetText(this.parts.separator, newValue, true);
      },
      count: function (_, newValue) {
        this._hideOrSetText(this.parts.count, newValue);
      },
    };

    constructor() {
      super();
      this.customAttachShadow();

      this.customDefineAttributes();
    }

    _hideOrSetText(el, newValue, skipText = null) {
      if (newValue != null) {
        el.style.display = "block";
        if (!skipText) {
          el.textContent = newValue;
        }
      } else {
        el.style.display = "none";
      }
    }
  }
);

customElements.define(
  "category-list",
  class extends HTMLElement {
    constructor() {
      super();
      this._expectedStyleLoads = 0;
      for (const panel of this.querySelectorAll(":scope > category-panel")) {
        this._registerPanel(panel);
        for (const label of panel.querySelectorAll(":scope > panel-label")) {
          this._registerLabel(panel, label);
        }
      }
    }
    _expectStyleLoad(element) {
      if (element.pendingStyleLoad) {
        this._expectedStyleLoads += 1;
        element.addEventListener(
          "style-load",
          () => {
            this._expectedStyleLoads -= 1;
            if (this._expectedStyleLoads === 0) {
              this.dispatchEvent(new CustomEvent("style-load"));
            }
          },
          { once: true }
        );
      }
    }

    get pendingStyleLoad() {
      return this._expectedStyleLoads > 0;
    }

    _registerPanel(panel) {
      this._expectStyleLoad(panel);
      panel.addEventListener("close", () => {
        const event = new Event("panel-close");
        event.panelId = panel.id;
        event.closed = panel.closed;
        this.dispatchEvent(event);
      });
    }

    _registerLabel(panel, label) {
      this._expectStyleLoad(label);
      label.slot = "content";
      const onClickEvent = ({ which, button, metaKey, ctrlKey, shiftKey }) => {
        this.dispatchEvent(
          Object.assign(new Event("label-click"), {
            labelElement: label,
            labelId: label.id,
            panelId: panel.id,
            rightClick: which === 3,
            metaKey: metaKey || ctrlKey || false,
            shiftKey,
            button,
            which,
          })
        );
      };
      label.addEventListener("mousedown", (e) => onClickEvent(e));
      label.addEventListener("contextmenu", () =>
        onClickEvent({
          which: 3,
          button: 2,
          metaKey: false,
          shiftKey: false,
        })
      );
    }

    panelClosed(panelId) {
      return document.getElementById(panelId).closed;
    }

    labelSelected(labelId) {
      return document.getElementById(labelId).selected;
    }

    get panelAttribs() {
      return Object.fromEntries(
        [...this.querySelectorAll(":scope > category-panel")].map((panel) => [
          panel.id,
          Object.fromEntries(
            panel.constructor.mutObservedAttributes
              .filter((attr) => panel.hasAttribute(attr))
              .map((attr) => [attr, panel[attr]])
          ),
        ])
      );
    }

    get panelLabelAttribs() {
      return Object.fromEntries(
        [...this.querySelectorAll(":scope > category-panel")].map((panel) => [
          panel.id,
          new Map(
            [...panel.querySelectorAll(":scope > panel-label")].map((label) => [
              label.id,
              Object.fromEntries(
                label.constructor.mutObservedAttributes
                  .filter((attr) => label.hasAttribute(attr))
                  .map((attr) => [attr, label[attr]])
              ),
            ])
          ),
        ])
      );
    }

    /**
     * Sync the category list attributes to the DOM.
     */
    sync(panelLabelAttribs, panelAttribs) {
      const arraysEqual = (a, b) =>
        a?.length === b?.length && a.every((aa, i) => aa === b[i]);
      for (const [panelId, labelAttribs] of Object.entries(panelLabelAttribs)) {
        // Get or create category-panel element
        let panel = document.getElementById(panelId);
        if (!panel) {
          panel = document.createElement("category-panel");
          panel.id = panelId;
          this._registerPanel(panel);
          this.appendChild(panel);
        }
        Object.assign(panel, panelAttribs[panelId]);

        for (const [labelId, attribs] of labelAttribs) {
          // Get or create panel-label element
          let label = document.getElementById(labelId);
          if (!label) {
            label = document.createElement("panel-label");
            label.id = labelId;
            this._registerLabel(panel, label);
            panel.appendChild(label);
          }
          Object.assign(label, attribs);
        }
        const previousLabelIds = [
          ...panel.querySelectorAll(":scope > panel-label"),
        ].map((label) => label.id);
        const sortedLabelIds = [...labelAttribs.keys()];
        if (!arraysEqual(previousLabelIds, sortedLabelIds)) {
          // Sort labelids
          panel.replaceChildren(
            ...panel.querySelectorAll(":scope > :not(panel-label)"),
            ...sortedLabelIds.map((labelId) => document.getElementById(labelId))
          );
        }
      }
      const previousPanelIds = [
        ...this.querySelectorAll(":scope > category-panel"),
      ].map((panel) => panel.id);
      const sortedPanelIds = Object.keys(panelAttribs);
      if (!arraysEqual(previousPanelIds, sortedPanelIds)) {
        // Sort panels
        this.replaceChildren(
          ...this.querySelectorAll(":scope > :not(category-panel)"),
          ...sortedPanelIds.map((panelId) => document.getElementById(panelId))
        );
      }
    }
  }
);
