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
 * lt:'Lithuanian', ms:'Malay', mn:'Mongolian', no:'Norwegian', fa:'Persian',
 * pl:'Polish', pt:'Portuguese', ro:'Romanian', ru:'Russian', sr:'Serbian', sk:'Slovak',
 * sl:'Slovenian', es:'Spanish', sv:'Swedish', th:'Thai', tr:'Turkish', uk:'Ukrainian',
 * vi:'Vietnamese'
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
	pl:'Polski',
	pt:'Português',
	ru:'Русский',
	sk:'Slovenský',
	sr:'Српски',
	sv:'Svenska',
	tr:'Türk',
	uk:'Українська',
	vi:'Tiếng Việt',
	"zh-cn":'简体中文',
	"zh-tw":'繁體中文'
};

function GetActiveLanguage()
{
	var DefaultLanguage = 'en';
	var LC = readLangCookie();
	if(LC != null)
		return LC;
	var A;
	if(navigator.userLanguage)
		A = navigator.userLanguage.toLowerCase();
	else
		if(navigator.language)
			A = navigator.language.toLowerCase();
	if(A.length >= 5)
	{
		A = A.substr(0,5);
		if(AvailableLanguages[A])
			return A;
	}
	if(A.length >= 2)
	{
		A = A.substr(0,2);
		if (AvailableLanguages[A])
			return A;
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

document.write("<script type=\"text/javascript\" src=\"./lang/"+GetActiveLanguage()+".js\"></script>");
