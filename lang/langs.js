/*
 * Determine language file.
 *
 * Initial Author: Artem Lopata (mod-s@yandex.ru)
 *
 * Description: This script detects user language. If not in the list, then defaults to English.
 *
 * Languages:
 * af:'Afrikaans', ar:'Arabic', bn:'Bengali/Bangla', bs:'Bosnian', bg:'Bulgarian',
 * "zh-cn":'Chinese Simplified', "zh-tw":'Chinese Traditional', hr:'Croatian',
 * cs:'Czech', da:'Danish', nl:'Dutch', en:'English', et:'Estonian', fo:'Faroese',
 * fi:'Finnish', fr:'French', de:'German', el:'Greek', he:'Hebrew', hi:'Hindi',
 * hu:'Hungarian', it:'Italian', ja:'Japanese', km:'Khmer', ko:'Korean', lv:'Latvian',
 * lt:'Lithuanian', ms:'Malay', mn:'Mongolian', no:'Norwegian', nn:'Norwegian Nynorsk',
 * fa:'Persian', pl:'Polish', pt:'Portuguese', ro:'Romanian', ru:'Russian', sr:'Serbian',
 * sk:'Slovak', sl:'Slovenian', es:'Spanish', sv:'Swedish', th:'Thai', tr:'Turkish',
 * uk:'Ukrainian', vi:'Vietnamese'
 */

function readLangCookie() {
	var nameEQ = "Language=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

var AvailableLanguages =
{
	bn:'Bengali',
	cs:'Český',
	da:'Dansk',
	de:'Deutsch',
	el:'Ελληνικά',
	en:'English',
	es:'Español',
	fi:'Suomi',
	fr:'Français',
	hu:'Magyar',
	it:'Italiano',
	ko:'한국어',
	lv:'Latvijas',
	nl:'Nederlands',
	no:'Norsk',
	nn:'Norsk (nynorsk)',
	pl:'Polski',
	"pt-br":'Português (Brasil)',
	"pt-pt":'Português (Portugal)',
	ru:'Русский',
	sk:'Slovenský',
	sr:'Српски',
	sv:'Svenska',
	tr:'Türkçe',
	uk:'Українська',
	vi:'Tiếng Việt',
	"zh-cn":'简体中文',
	"zh-tw":'繁體中文'
};

/*
 * Tags a browser sends that name a language in AvailableLanguages under a
 * different code. "nb" is the ISO 639-1 code for Bokmal, which is what no.js
 * holds; the rest follow the regions and scripts CLDR resolves them to, so
 * zh and zh-SG are Simplified and zh-HK and zh-MO are Traditional.
 *
 * These are deliberately not entries in AvailableLanguages: that object is
 * also the list the settings dialog renders as the language selector, and an
 * entry here would be a second Norwegian in the dropdown.
 */
var LanguageAliases =
{
	nb:'no',
	pt:'pt-br',
	zh:'zh-cn',
	"zh-hans":'zh-cn',
	"zh-hant":'zh-tw',
	"zh-hk":'zh-tw',
	"zh-mo":'zh-tw',
	"zh-sg":'zh-cn'
};

function GetActiveLanguage()
{
	var DefaultLanguage = 'en';
	var LC = readLangCookie();
	if(LC != null)
		return LC;
	var A = '';
	if(navigator.userLanguage)
		A = navigator.userLanguage.toLowerCase();
	else
		if(navigator.language)
			A = navigator.language.toLowerCase();
	// Most specific first, dropping one subtag at a time: zh-hant-tw, then
	// zh-hant, then zh.
	var subtags = A.split('-');
	while(subtags.length)
	{
		var tag = subtags.join('-');
		if(AvailableLanguages[tag])
			return tag;
		if(LanguageAliases[tag])
			return LanguageAliases[tag];
		subtags.pop();
	}
	return(DefaultLanguage);
}

function SetActiveLanguage(lang)
{
	var date = new Date();
	date.setTime( date.getTime() + (365*24*60*60*1000) );
	var expires = "; expires=" + date.toGMTString();
	document.cookie = "Language="+ lang + expires +"; path=/";
}

function loadUILang(onLoadFunc)
{
	const lang = GetActiveLanguage();
	document.documentElement.setAttribute('lang', lang);

	const langScript = document.createElement("script");
	langScript.onload = () => {
		if (onLoadFunc) {
			onLoadFunc();
		}
		if (document.readyState === 'loading') {
			document.addEventListener("DOMContentLoaded", translateDOM);
		} else {
			translateDOM();
		}
	};
	langScript.src = `./lang/${lang}.js?v=5318`;
	document.head.appendChild(langScript);
}

function translateDOM() {
	// Translate uilang elements and uilangtitle/uilangvalue attributes
	for (const attr of ['', 'title', 'value', 'text']) {
		for (el of document.querySelectorAll(`[uilang${attr}]`)) {
			const translationId = attr.length ? el.getAttribute(`uilang${attr}`) : el.textContent;
			const translation = theUILang[translationId] ?? translationId;
			if (attr.length)
				el.setAttribute(attr, translation);
			else
				el.textContent = translation;
			el.removeAttribute(`uilang${attr}`);
		}
	}
}
