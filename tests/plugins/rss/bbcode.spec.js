import { mapBBCodeToHTML } from "../../../plugins/rss/bbcode";
window.$ = require("jquery");

describe("bbcode mapping", () => {
  it("should handle incomplete or faulty bbcode", () => {
    expect(mapBBCodeToHTML("[i]ABC[/i]abc[b]CDE")).toEqual(
      "<i>ABC</i>abc<b>CDE</b>"
    );
    expect(mapBBCodeToHTML("[i]ABC[/i]abc[b]CDE[/c]some")).toEqual(
      "<i>ABC</i>abc<b>CDE[/c]some</b>"
    );
  });

  it("should map emoticons to html", () => {
    const bbcode =
      "[center  ][color=red ]Important :smile::shifty:[/color][/center]";
    const htmlCode = mapBBCodeToHTML(bbcode);
    expect(htmlCode).toEqual(
      '<span class="bbcode-align-center"><span class="bbcode-color-red">Important &#128578;&#128530;</span></span>'
    );
  });

  it("should map styles to html", () => {
    expect(mapBBCodeToHTML("[style font=caps  ]Text[/style]")).toEqual(
      '<span class="bbcode-font-caps">Text</span>'
    );
    expect(mapBBCodeToHTML("[style color=??  font=caps]Text[/style]")).toEqual(
      '<span class="bbcode-color-?? bbcode-font-caps">Text</span>'
    );
  });

  it("should map bbcode tables to html", () => {
    expect(
      mapBBCodeToHTML(
        '[table] [tr] [td][i]table[/i] [large][b]cell[/b][/large] 1[/td] [td][right]table[/right] cell 2[/td] [/tr] [tr] [td][url]https://google.com[/url]table cell 3[/td] [td][quote="The Devil"]Mmmmh, merely mortal mollusc mating maggots may molest my malevolent magnificence![/quote][/td] [/tr] [/table]\n[spoiler=All truth is...]true[/spoiler]'
      )
    ).toEqual(
      '<table> <tbody><tr> <td><i>table</i> <span class="bbcode-size-large"><b>cell</b></span> 1</td> <td><span class="bbcode-align-right">table</span> cell 2</td> </tr> <tr> <td><a href="https://google.com">https://google.com</a>table cell 3</td> <td><blockquote><p>Mmmmh, merely mortal mollusc mating maggots may molest my malevolent magnificence!</p><span class="bbcode-quote">-- <cite>The Devil</cite></span></blockquote></td> </tr> </tbody></table>\n<details><summary>All truth is...</summary>true</details>'
    );
  });

  it("should map bbcode lists to html code", () => {
    expect(
      mapBBCodeToHTML(
        "[list]\n * One\n [*]Two \n <b>list</b>with[i]more[/i] elements[li][*] blank \n * blank2[/li]* Three[/list]"
      )
    ).toEqual(
      "<ul>\n<li>One\n </li><li>Two \n <b>list</b>with<i>more</i> elements</li><li>[*] blank \n * blank2</li><li>Three</li></ul>"
    );
  });

  it("should map bbcode img to html code", () => {
    expect(
      mapBBCodeToHTML("[img width=150 height=160]https://linkto.img[/img]")
    ).toEqual('<img src="https://linkto.img" width="150" height="160">');
    expect(mapBBCodeToHTML("[img=150x160]https://linkto.img[/img]")).toEqual(
      '<img src="https://linkto.img" width="150" height="160">'
    );
    expect(mapBBCodeToHTML("[img=150]https://linkto.img[/img]")).toEqual(
      '<img src="https://linkto.img" width="150">'
    );
    expect(mapBBCodeToHTML("[img]https://linkto.img[/img]")).toEqual(
      '<img src="https://linkto.img">'
    );
  });
});
