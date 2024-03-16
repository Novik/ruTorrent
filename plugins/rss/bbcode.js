const colorNamePattern =
  /^(?:aliceblue|antiquewhite|aqua|aquamarine|azure|beige|bisque|black|blanchedalmond|blue|blueviolet|brown|burlywood|cadetblue|chartreuse|chocolate|coral|cornflowerblue|cornsilk|crimson|cyan|darkblue|darkcyan|darkgoldenrod|darkgray|darkgreen|darkkhaki|darkmagenta|darkolivegreen|darkorange|darkorchid|darkred|darksalmon|darkseagreen|darkslateblue|darkslategray|darkturquoise|darkviolet|deeppink|deepskyblue|dimgray|dodgerblue|firebrick|floralwhite|forestgreen|fuchsia|gainsboro|ghostwhite|gold|goldenrod|gray|green|greenyellow|honeydew|hotpink|indianred|indigo|ivory|khaki|lavender|lavenderblush|lawngreen|lemonchiffon|lightblue|lightcoral|lightcyan|lightgoldenrodyellow|lightgray|lightgreen|lightpink|lightsalmon|lightseagreen|lightskyblue|lightslategray|lightsteelblue|lightyellow|lime|limegreen|linen|magenta|maroon|mediumaquamarine|mediumblue|mediumorchid|mediumpurple|mediumseagreen|mediumslateblue|mediumspringgreen|mediumturquoise|mediumvioletred|midnightblue|mintcream|mistyrose|moccasin|navajowhite|navy|oldlace|olive|olivedrab|orange|orangered|orchid|palegoldenrod|palegreen|paleturquoise|palevioletred|papayawhip|peachpuff|peru|pink|plum|powderblue|purple|red|rosybrown|royalblue|saddlebrown|salmon|sandybrown|seagreen|seashell|sienna|silver|skyblue|slateblue|slategray|snow|springgreen|steelblue|tan|teal|thistle|tomato|turquoise|violet|wheat|white|whitesmoke|yellow|yellowgreen)$/;
const colorCodePattern = /^#?[a-f0-9]{6}$/i;
const restirctedFontSize = (value) => {
  const size = Math.max(4, Math.min(40, Number.parseInt(value)));
  return Number.isNaN(size) ? "normal" : `${size}px`;
};
const bbNodeAttr = {
  color: (value) =>
    colorNamePattern.test(value)
      ? { color: value }
      : colorCodePattern.test(value)
      ? { color: value.startsWith("#") ? value : `#${value}` }
      : null,
  size: (value) =>
    ["small", "large", "normal"].includes(value)
      ? "class"
      : { "font-size": restirctedFontSize(value) },
  align: (value) =>
    ["left", "right", "center"].includes(value) ? "class" : null,
  font: (value) =>
    [
      "times",
      "courier",
      "arial",
      "serif",
      "sans",
      "fantasy",
      "monospace",
      "caps",
    ].includes(value)
      ? "class"
      : null,
};

export function bbclassTransform(cfg) {
  const node = cfg.node;
  if (!["pre", "span"].includes(node.nodeName.toLowerCase())) {
    return null;
  }
  let styles = {};
  let classes = [];
  for (const bbClass of (node.attributes.class?.value || "").split(" ")) {
    const [bbcode, key, value] = bbClass.split("-");
    if (bbcode === "bbcode") {
      const style = key in bbNodeAttr ? bbNodeAttr[key](value || "") : null;
      if (style !== null) {
        if (style !== "class") {
          styles = { ...styles, ...style };
        }
        classes.push(
          `${bbcode}-${key}` + (style === "class" ? `-${value}` : "")
        );
      }
    }
  }
  // replace existing attributes with style and class
  [...node.attributes].forEach((attr) => node.removeAttribute(attr.name));
  for (const [name, value] of [
    [
      "style",
      Object.entries(styles)
        .map((e) => e.join(": "))
        .join("; "),
    ],
    ["class", classes.join(" ")],
  ]) {
    if (value) {
      const attr = cfg.dom.createAttribute(name);
      attr.value = value;
      node.attributes[name] = attr;
    }
  }
  return {
    whitelist: Boolean(classes.length),
    attr_whitelist: ["class", "style"],
    node,
  };
}

export function mapBBCodeToHTML(htmlText) {
  const tags = {
    ...Object.fromEntries(
      [
        "b",
        "i",
        "sup",
        "sub",
        "table",
        "thead",
        "tbody",
        "tfoot",
        "tr",
        "td",
        "th",
        "li",
      ].map((t) => [t, () => [t]])
    ),
    ...Object.fromEntries(
      ["ul", "ol", "list"].map((name) => [
        name,
        (_, content) => {
          const htmlTag = name === "list" ? "ul" : name;
          const ele = $(`<${htmlTag}>`).html(content);
          const list = $(`<${htmlTag}>`);
          let lastLiNode = $("<li>");
          for (const node of ele.contents()) {
            if (node.nodeName.toLowerCase() === "li") {
              // keep li nodes
              lastLiNode = $(node);
            } else {
              if (node.nodeType === 3) {
                // parse list items denoted by [*] and *
                const items = String(node.nodeValue)
                  .replaceAll(/(^|[\s\]])\*\s/g, "[*]")
                  .split(/\[\*\]/g);
                if (!list.children("li").length) {
                  // set text of empty list
                  list.text(items.shift());
                }
                const firstItem = items.shift();
                if (firstItem) {
                  // add textnode to lastLiNode
                  lastLiNode.append(document.createTextNode(firstItem));
                }
                for (const item of items) {
                  list.append(lastLiNode);
                  lastLiNode = $("<li>").text(item);
                }
              } else {
                // add some node to lastLiNode
                lastLiNode.append($(node));
              }
            }
            // add lastLiNode to list (if not added already)
            list.append(lastLiNode);
          }
          return [htmlTag, {}, list[0].innerHTML];
        },
      ])
    ),
    u: () => ["ins"],
    s: () => ["del"],
    ...Object.fromEntries(
      ["small", "normal", "large"].map((t) => [
        t,
        () => ["span", { class: `bbcode-size-${t}` }],
      ])
    ),
    size: (arg) => ["span", { class: `bbcode-size-${arg}` }],
    color: (arg) => ["span", { class: `bbcode-color-${arg}` }],
    ...Object.fromEntries(
      ["center", "left", "right"].map((t) => [
        t,
        () => ["span", { class: `bbcode-align-${t}` }],
      ])
    ),
    ...Object.fromEntries(
      ["font", "face"].map((t) => [
        t,
        (arg) => ["span", { class: (arg || "").toLowerCase() }],
      ])
    ),
    style: (_, __, args) => [
      "span",
      {
        class: Object.entries(args)
          .map(([k, v]) => `bbcode-${k}-${v}`)
          .join(" "),
      },
    ],
    img: (arg, content, args) => [
      "img",
      {
        src: content,
        ...Object.fromEntries(
          (arg || "")
            .split("x")
            .map((v, k) => [["width", "height"][k], Number.parseInt(v)])
            .filter(([_, v]) => !Number.isNaN(v))
        ),
        ...args,
      },
      "",
    ],
    url: (arg, content) => ["a", { href: arg == null ? content : arg }],
    email: (arg, content) => [
      "a",
      { href: `mailto:${arg == null ? content : arg}` },
    ],
    quote: (arg, content, args) => [
      "blockquote",
      {},
      $("<p>").html(content)[0].outerHTML +
        $("<span>")
          .addClass("bbcode-quote")
          .text("-- ")
          .append($("<cite>").text(arg || args["author"] || ""))[0].outerHTML,
    ],
    code: () => ["pre", { class: "bbcode-code" }],
    spoiler: (arg, content) => [
      "details",
      {},
      $("<summary>").html(arg)[0].outerHTML + content,
    ],
    "bbcode-root": () => ["div"],
  };

  const trimArg = (arg) =>
    arg == null
      ? null
      : arg.startsWith('"')
      ? arg.substring(1, arg.length - 1)
      : arg.trim();
  const argsToDict = (args) => {
    const dict = {};
    for (const match of args.matchAll(
      /\s+?(?<name>[a-z]+)=(?<arg>"(.*?)"|[^\s]*)/gi
    )) {
      const { name, arg } = match.groups;
      if (name && arg) {
        dict[name] = trimArg(arg);
      }
    }
    return dict;
  };

  const nodeToElement = (node) => {
    const htmlContent = node.children
      .map((n) => (n.name ? nodeToElement(n).outerHTML : n))
      .join("");
    const arg = trimArg(node.arg);
    const args = node.args ? argsToDict(node.args) : {};
    const [htmlTag, attribs, htmlContentProcessed] = tags[node.name](
      arg,
      htmlContent,
      args
    );
    const ele = $(`<${htmlTag}>`)
      .attr(attribs || {})
      .html(htmlContentProcessed || htmlContent)[0];
    return ele;
  };

  const simpleParamPattern = '\\s*?=\\s*?(?<arg>"(.*?)"|.*?)';
  const complexParamPattern = '(?<args>(\\s+?[a-z]+=("(.*?)"|[^\\s]*?))+)';
  const tagPattern = new RegExp(
    "\\[\\/?(?<name>" +
      Object.keys(tags).join("|") +
      ")(" +
      simpleParamPattern +
      "|" +
      complexParamPattern +
      ")?\\s*?\\]",
    "gsi"
  );
  let nodeStack = [{ name: "bbcode-root", children: [] }];
  let offset = 0;
  for (const match of htmlText.matchAll(tagPattern)) {
    const parent = nodeStack[nodeStack.length - 1];
    const { name, arg, args } = match.groups;
    const closing = match[0].startsWith("[/");
    // add textnode to parent
    const textnode = match.input.substring(offset, match.index);
    if (textnode) {
      parent.children.push(textnode);
    }
    if (closing) {
      if (parent.name === name && nodeStack.length > 1) {
        nodeStack.pop();
      } else {
        // encoutered unexpected close tag
        nodeStack = [nodeStack[0]];
      }
    } else {
      const node = { name, arg, args, children: [] };
      parent.children.push(node);
      // make curnode to parent node
      nodeStack.push(node);
    }
    offset = match.index + match[0].length;
  }
  nodeStack[nodeStack.length - 1].children.push(
    htmlText.substring(offset, htmlText.length)
  );
  const htmlContent = nodeToElement(nodeStack[0]).innerHTML;

  // Support for some emoticons from WhatCD/Gazelle (https://github.com/WhatCD/Gazelle/tree/master/static/common/smileys)
  // :code: => utf8 emoticon (https://utf8-icons.com/subset/emoticons)
  const emoticons = {
    smile: "&#128578;",
    blank: "&#128528;",
    biggrin: "&#128513;",
    angry: "&#128545;",
    blush: "&#128522;",
    cool: "&#128526;",
    crying: "&#128546;",
    frown: "&#128577;",
    unsure: "&#128533;",
    lol: "&#128516;",
    ninja: "&#129399;",
    no: "&#128581;",
    ohno: "&#128552;",
    ohnoes: "&#128552;",
    omg: "&#128576;",
    shifty: "&#128530;",
    sick: "&#128567;",
    wink: "&#128521;",
    creepy: "&#128520;",
    tongue: "&#128540;",
    thumbsup: "&#128077;",
    "+1": "&#128077;",
    thumbsdown: "&#128078;",
    "-1": "&#128078;",
  };
  const emoticonRegExp = new RegExp(
    ":(" +
      Object.keys(emoticons)
        .map((e) => e.replaceAll(/\+/g, "\\+"))
        .join("|") +
      "):",
    "g"
  );
  return htmlContent.replace(
    emoticonRegExp,
    (_, iconName) => emoticons[iconName]
  );
}
