plugin.loadLang();

plugin.onLangLoaded = function()
{
	$("head").append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedAll, href: "plugins/feeds/action.php?mode=all&title="+encodeURIComponent(theUILang.feedAll) }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedDownloading, href: "plugins/feeds/action.php?mode=downloading&title="+encodeURIComponent(theUILang.feedDownloading) }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedCompleted, href: "plugins/feeds/action.php?mode=completed&title="+encodeURIComponent(theUILang.feedCompleted) }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedActive, href: "plugins/feeds/action.php?mode=active&title="+encodeURIComponent(theUILang.feedActive) }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedInactive, href: "plugins/feeds/action.php?mode=inactive&title="+encodeURIComponent(theUILang.feedInactive) }) ).
		append( $("<link>").attr( { rel: "alternate", type: "application/rss+xml", title: theUILang.feedError, href: "plugins/feeds/action.php?mode=error&title="+encodeURIComponent(theUILang.feedError) }) );
}
