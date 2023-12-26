CSSStyleSheet.prototype.replaceSync = function (text) {
  this.textContent = text;
};
global.structuredClone = (v) => JSON.parse(JSON.stringify(v));
