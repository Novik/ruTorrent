import { readFileSync } from "fs";
window.$ = require("jquery");
window.theWebUI = {
  settings: {
    "webui.needmessage": true,
  },

  showFlags: 0xffff,
  systemInfo: {
    rTorrent: {
      apiVersion: 10,
      iVersion: 0x908,
      started: true,
    },
  },
};
window.GetActiveLanguage = function () {
  return "en";
};

for (const src of [
  "../js/sanitize.js",
  "../js/sanitize.config.js",
  "../lang/en.js",
  "../js/common.js",
  "../js/content.js",
  "../js/rtorrent.js",
  "../js/plugins.js",
  "../plugins/rss/init.js",
]) {
  const scriptEl = document.createElement("script");
  let code = readFileSync(src, { encoding: "utf-8" });
  if (src.endsWith("rss/init.js")) {
    code = `(function () { var plugin = new rPlugin('rss', 4.0, 'a', 'b', 'c', 'd'); plugin.path="../plugins/rss/"; ${code}})();`;
  }
  scriptEl.setAttribute("type", "text/javascript");
  scriptEl.textContent = code;
  document.head.appendChild(scriptEl);
}
correctContent();
document.body.appendChild($("<div>").attr("id", "rsslayout")[0]);

describe("rss details", () => {
  beforeEach(() => {
    $("#rsslayout").text("");
  });

  it("should handle incomplete or faulty bbcode", () => {
    expect(theWebUI.mapBBCodeToHTML("[i]ABC[/i]abc[b]CDE")).toEqual(
      "<i>ABC</i>abc<b>CDE</b>"
    );
    expect(theWebUI.mapBBCodeToHTML("[i]ABC[/i]abc[b]CDE[/c]some")).toEqual(
      "<i>ABC</i>abc<b>CDE[/c]some</b>"
    );
  });

  it("should map emoticons to html", () => {
    const bbcode =
      "[center  ][color=red ]Important :smile::shifty:[/color][/center]";
    const htmlCode = theWebUI.mapBBCodeToHTML(bbcode);
    expect(htmlCode).toEqual(
      '<span class="bbcode-align-center"><span class="bbcode-color-red">Important &#128578;&#128530;</span></span>'
    );
  });

  it("should map styles to html", () => {
    expect(theWebUI.mapBBCodeToHTML("[style font=caps  ]Text[/style]")).toEqual(
      '<span class="bbcode-font-caps">Text</span>'
    );
    expect(
      theWebUI.mapBBCodeToHTML("[style color=??  font=caps]Text[/style]")
    ).toEqual('<span class="bbcode-color-?? bbcode-font-caps">Text</span>');
  });

  it("should map bbcode tables to html", () => {
    expect(
      theWebUI.mapBBCodeToHTML(
        '[table] [tr] [td][i]table[/i] [large][b]cell[/b][/large] 1[/td] [td][right]table[/right] cell 2[/td] [/tr] [tr] [td][url]https://google.com[/url]table cell 3[/td] [td][quote="The Devil"]Mmmmh, merely mortal mollusc mating maggots may molest my malevolent magnificence![/quote][/td] [/tr] [/table]\n[spoiler=All truth is...]true[/spoiler]'
      )
    ).toEqual(
      '<table> <tbody><tr> <td><i>table</i> <span class="bbcode-size-large"><b>cell</b></span> 1</td> <td><span class="bbcode-align-right">table</span> cell 2</td> </tr> <tr> <td><a href="https://google.com">https://google.com</a>table cell 3</td> <td><blockquote><p>Mmmmh, merely mortal mollusc mating maggots may molest my malevolent magnificence!</p><span class="bbcode-quote">-- <cite>The Devil</cite></span></blockquote></td> </tr> </tbody></table>\n<details><summary>All truth is...</summary>true</details>'
    );
  });

  it("should map bbcode lists to html code", () => {
    expect(
      theWebUI.mapBBCodeToHTML(
        "[list]\n * One\n [*]Two \n <b>list</b>with[i]more[/i] elements[li][*] blank \n * blank2[/li]* Three[/list]"
      )
    ).toEqual(
      "<ul>\n<li>One\n </li><li>Two \n <b>list</b>with<i>more</i> elements</li><li>[*] blank \n * blank2</li><li>Three</li></ul>"
    );
  });

  it("should map bbcode img to html code", () => {
    expect(
      theWebUI.mapBBCodeToHTML(
        "[img width=150 height=160]https://linkto.img[/img]"
      )
    ).toEqual('<img src="https://linkto.img" width="150" height="160">');
    expect(
      theWebUI.mapBBCodeToHTML("[img=150x160]https://linkto.img[/img]")
    ).toEqual('<img src="https://linkto.img" width="150" height="160">');
    expect(
      theWebUI.mapBBCodeToHTML("[img=150]https://linkto.img[/img]")
    ).toEqual('<img src="https://linkto.img" width="150">');
    expect(theWebUI.mapBBCodeToHTML("[img]https://linkto.img[/img]")).toEqual(
      '<img src="https://linkto.img">'
    );
  });

  it("should sanitize html code", () => {
    theWebUI.rssItems = { rssid: "rsslink" };
    const stub = new rTorrentStub("?action=getrssdetails&s=rssid");
    stub.getrssdetailsResponse(
      '[b][color=#556677]<ins>Important</ins> :smile:[/color]<img src="https://linkto.img" onerror=alert("hax2")/><script>alert(\'hax\');</script>[/b]'
    );
    // Sanitize.RESTRICTED does not allow img
    expect($("#rsslayout div").html()).toEqual(
      '<b><span class="bbcode-color" style="color: #556677"><ins>Important</ins> 🙂</span>alert(\'hax\');</b>'
    );
    stub.getrssdetailsResponse(
      '[style color=0000FF font="times" size=18 wild=attr align=center]Text[/style]'
    );
    expect($("#rsslayout div").html()).toEqual(
      '<span class="bbcode-color bbcode-font-times bbcode-size bbcode-align-center" style="color: #0000FF; font-size: 18px">Text</span>'
    );
    stub.getrssdetailsResponse("[style color=bad font=caps]Text[/style]");
    expect($("#rsslayout div").html()).toEqual(
      '<span class="bbcode-font-caps">Text</span>'
    );
  });
});
