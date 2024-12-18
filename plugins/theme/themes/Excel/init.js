plugin.oldAllDone = plugin.allDone;
plugin.allDone = function()
{
	plugin.oldAllDone.call(this);
	$(".tabbar li:last-of-type a").after(
		$("<img>").attr({
			height: "17px",
			src: $(".tabbar li:last-of-type").hasClass("selected")
				? "plugins/theme/themes/Excel/images/tabbghfin.png"
				: "plugins/theme/themes/Excel/images/tabbgfin.png"},
		),
	);
	$(".tabbar li:last-of-type").addClass("d-flex");
}

plugin.tabsShow = theTabs.show;
theTabs.show = function(id) {
	plugin.tabsShow.call(this, id);
	$(".tabbar img").prop( { src: $(".tabbar li:last-child").hasClass("selected") ? 
		"plugins/theme/themes/Excel/images/tabbghfin.png" : 
		"plugins/theme/themes/Excel/images/tabbgfin.png" } );
}
