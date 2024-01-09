loadUILang(() => {
  // Scripts which depend on theUILang to be loaded
  const scripts = [
    "common",
    "objects",
    "content",
    "stable",
    "graph",
    "plugins",
    "rtorrent",
    "webui",
  ];
  document.head.append(
    ...scripts.map((name) => {
      const script = document.createElement("script");
      script.src = `./js/${name}.js?v=4210`;
      script.async = false;
      return script;
    })
  );
});
