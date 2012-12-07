plugin.loadLang();

plugin.onLangLoaded = function()
{
	var lang =  GetActiveLanguage();
	$("head").append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedAll, href: "plugins/feeds/action.php?mode=all&lang="+lang }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedDownloading, href: "plugins/feeds/action.php?mode=downloading&lang="+lang }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedCompleted, href: "plugins/feeds/action.php?mode=completed&lang="+lang }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedActive, href: "plugins/feeds/action.php?mode=active&lang="+lang }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedInactive, href: "plugins/feeds/action.php?mode=inactive&lang="+lang }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedError, href: "plugins/feeds/action.php?mode=error&lang="+lang }) );
}
