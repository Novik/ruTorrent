/*
 * WebUI - The WEB interface for uTorrent - http://www.utorrent.com
 * NO COPYCATS of language update
 * 
 * == BEGIN LICENSE ==
 * 
 * Licensed under the terms of any of the following licenses at your
 * choice:
 * 
 *  - GNU General Public License Version 2 or later (the "GPL")
 *    http://www.gnu.org/licenses/gpl.html
 * 
 *  - GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 *    http://www.gnu.org/licenses/lgpl.html
 * 
 *  - Mozilla Public License Version 1.1 or later (the "MPL")
 *    http://www.mozilla.org/MPL/MPL-1.1.html
 * 
 * == END LICENSE ==
 * 
 * File Name: langs.js
 * 	Determine language file.
 * 
 * File Author:
 * 		Artem Lopata (mod-s@yandex.ru)
 * Description: this script detects user language.. if it not present in list - then default language en
 * languages:     sp:'Spanish',br:'Portuguese (Brazil)',af:'Afrikaans',ar:'Arabic',bg:'Bulgarian',bn:'Bengali/Bangla',bs:'Bosnian',ca:'Catalan',cs:'Czech',da:'Danish',de:'German',el:'Greek',en:'English','en-au':'English (Australia)','en-ca':'English (Canadian)','en-uk':'English (United Kingdom)',eo:'Esperanto',es:'Spanish',et:'Estonian',eu:'Basque',fa:'Persian',fi:'Finnish',fo:'Faroese',fr:'French',gl:'Galician',he:'Hebrew',hi:'Hindi',hr:'Croatian',hu:'Hungarian',it:'Italian',ja:'Japanese',km:'Khmer',ko:'Korean',lt:'Lithuanian',lv:'Latvian',mn:'Mongolian',ms:'Malay',nb:'Norwegian Bokmal',nl:'Dutch',no:'Norwegian',pl:'Polish',pt:'Portuguese (Portugal)','pt-br':'Portuguese (Brazil)',ro:'Romanian',ru:'Russian',sk:'Slovak',sl:'Slovenian',sr:'Serbian (Cyrillic)','sr-latn':'Serbian (Latin)',sv:'Swedish',th:'Thai',tr:'Turkish',uk:'Ukrainian',vi:'Vietnamese',zh-tw:'Chinese Traditional','zh-cn':'Chinese Simplified'
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
	cs:'Český',
	da:'Danske',
	de:'Deutsch',
	en:'English',
	es:'Español',
	el:'Ελληνικά',
	fr:'Français',
	it:'Italiano',
	lv:'Latvijas',
	hu:'Magyar',
	nl:'Nederlands',
	pl:'Polski',
	pt:'Português',
	sk:'Slovenský',
	fi:'Suomi',
	vi:'Tiếng Việt',
	tr:'Türk',
	ru:'Русский',
	sr:'Српски',
	uk:'Українська',
	"zh-cn":'简体中文',
	"zh-tw":'繁體中文'
};

DefaultLanguage = 'en';

function GetActiveLanguage()
{
	var LC = readLangCookie();
	if (LC != null)
		return LC;
	var A;
	if (navigator.userLanguage) 
		A=navigator.userLanguage.toLowerCase();
	else 
		if (navigator.language) 
			A=navigator.language.toLowerCase();
		else 
		{
			return FCKConfig.DefaultLanguage;
		}
	if (A.length>=5)
	{
		A=A.substr(0,5);
		if (AvailableLanguages[A]) 
			return A;
	}
	if (A.length>=2)
	{
		A=A.substr(0,2);
		if (AvailableLanguages[A]) 
			return A;
	}
	return DefaultLanguage;
}

function SetActiveLanguage(lang)
{
	var date = new Date();
	date.setTime( date.getTime() + (365*24*60*60*1000) );
	var expires = "; expires=" + date.toGMTString();
	document.cookie = "Language="+ lang + expires +"; path=/";
}

document.write("<script type=\"text/javascript\" src=\"./lang/"+GetActiveLanguage()+".js\"></script>");
