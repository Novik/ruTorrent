plugin.oldAllDone = plugin.allDone;
plugin.allDone = function()
{
	plugin.oldAllDone.call(this);
	$(".catpanel").prepend(
		$("<img></img>").attr( { src: "plugins/theme/themes/Excel/images/pnl_open.png", hspace: 10 } ).
			css( { "margin-left": -31, "margin-top": 2, "vertical-align": -3 } ) );
	$(".catpanel img").each( function()
	{
		var owner = $(this).parent()[0];
		theWebUI.updatePanel(owner.id);
	});
	$(".tabbar li:last-child a").after( $("<img></img>").attr( { src: $(".tabbar li:last-child").hasClass("selected") ? 
		"plugins/theme/themes/Excel/images/tabbghfin.png" : 
		"plugins/theme/themes/Excel/images/tabbgfin.png" } )
//		.css( { "vertical-align": -3 } ) 
	);
//	$(".tabbar li").css( { "vertical-align": -4 } );
	$("#clear_log").css( { "position": "relative", "bottom": 4 } );
}

plugin.tabsShow = theTabs.show;
theTabs.show = function(id)
{
	plugin.tabsShow.call(this,id);
	$(".tabbar img").prop( { src: $(".tabbar li:last-child").hasClass("selected") ? 
		"plugins/theme/themes/Excel/images/tabbghfin.png" : 
		"plugins/theme/themes/Excel/images/tabbgfin.png" } );
}

plugin.updatePanel = theWebUI.updatePanel;
theWebUI.updatePanel = function(panelId)
{
	const enable = !theWebUI.settings['webui.closed_panels'][panelId];
	$(`#${panelId}_cont`).toggle(enable);
	$(`#${panelId} img`).attr(
		"src", "plugins/theme/themes/Excel/images/" + (enable ? "pnl_open.png" : "pnl_close.png")
	);
}
