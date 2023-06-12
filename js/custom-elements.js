class CustomHTMLElement extends HTMLElement {
  static stringAttributeNames = [];
  static booleanAttributeNames = [];
  static attributeChangeCallbacks = {};

  static get mutObservedAttributes() {
    return this.booleanAttributeNames.concat(this.stringAttributeNames);
  }

  pendingStyleLoad = false;

  customAttachShadow() {
    const shadow = this.attachShadow({ mode: "open" });
    shadow.appendChild(this.constructor.template.content.cloneNode(true));
    const styleLinkEl = shadow.querySelector('link[rel="stylesheet"]');
    if (styleLinkEl && !styleLinkEl.sheet) {
      this.pendingStyleLoad = true;
      styleLinkEl.addEventListener(
        "load",
        () => {
          this.pendingStyleLoad = false;
          this.dispatchEvent(new CustomEvent("style-load"));
        },
        { once: true }
      );
    }
    const partEls = [...shadow.querySelectorAll("[part]")];
    if (partEls.length) {
      this.parts = Object.fromEntries(
        partEls.map((e) => [e.getAttribute("part"), e])
      );
    }
    return shadow;
  }

  customDefineAttributes() {
    this._restartMutationObserver((pendingMutations) => {
      for (const attrName of this.constructor.stringAttributeNames) {
        if (!this.hasOwnProperty(attrName)) {
          Object.defineProperty(this, attrName, {
            set(value) {
              if (value !== this.getAttribute(attrName)) {
                if (value !== null) {
                  this.setAttribute(attrName, value);
                } else {
                  this.removeAttribute(attrName);
                }
              }
            },
            get() {
              return this.getAttribute(attrName);
            },
          });
          pendingMutations.push({ attributeName: attrName, oldValue: null });
        }
      }
      for (const attrName of this.constructor.booleanAttributeNames) {
        if (!this.hasOwnProperty(attrName)) {
          Object.defineProperty(this, attrName, {
            set(value) {
              this.toggleAttribute(attrName, value);
            },
            get() {
              return this.hasAttribute(attrName);
            },
          });
          pendingMutations.push({ attributeName: attrName, oldValue: null });
        }
      }
    });
  }
  _restartMutationObserver(withoutObserver) {
    // Changing observedAttributes after calling customElements.define is not allowed.
    // Thus, instead of observedAttributes we use a MutationObserver
    // to support plugin-defined attributes.
    if (!this._mutationObserver) {
      this._mutationObserver = new MutationObserver(
        this._attributeMutationCallback.bind(this)
      );
    }
    // Process pending mutations now
    this._mutationObserver.disconnect();
    const pendingMutations = this._mutationObserver.takeRecords();
    withoutObserver(pendingMutations);
    this._attributeMutationCallback(pendingMutations);

    // Restart mutation observer
    this._mutationObserver.observe(this, {
      attributeFilter: this.constructor.mutObservedAttributes,
      attributeOldValue: true,
    });
  }

  _attributeMutationCallback(records) {
    for (const record of records) {
      const fn =
        this.constructor.attributeChangeCallbacks[record.attributeName];
      if (fn) {
        fn.call(this, record.oldValue, this.getAttribute(record.attributeName));
      }
    }
  }
}

//
// Plugin Helpers
//
function injectCustomElementAttribute(
  elementTag,
  attributeName,
  attributeChangedCallbackFn,
  isBooleanAttrib = false
) {
  const clazz = customElements.get(elementTag);
  if (!clazz) {
    throw new Error(`Custom element not defined: ${elementTag}!`);
  }
  (isBooleanAttrib
    ? clazz.booleanAttributeNames
    : clazz.stringAttributeNames
  ).push(attributeName);
  clazz.attributeChangeCallbacks[attributeName] = attributeChangedCallbackFn;
  for (const element of document.getElementsByTagName(elementTag)) {
    element.customDefineAttributes();
  }
}

function queryCustomElementShadowRoots(elementTag) {
  const template = customElements.get(elementTag).template;
  if (!template) {
    throw new Error(`Template not defined for custom element: ${elementTag}!`);
  }
  return [...document.getElementsByTagName(elementTag)]
    .map((element) => element.shadowRoot)
    .concat([template.content]);
}

function injectCustomElementCSS(elementTag, styleSheetURL) {
  for (const e of queryCustomElementShadowRoots(elementTag)) {
    const lastStyleEl = e.querySelector('link[rel="stylesheet"]:last-of-type');
    const injectStyleEl = Object.assign(document.createElement("link"), {
      rel: "stylesheet",
      href: styleSheetURL,
      type: "text/css",
    });
    if (lastStyleEl) {
      lastStyleEl.after(injectStyleEl);
    } else {
      e.prepend(injectStyleEl);
    }
  }
}
