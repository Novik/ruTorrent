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
		theWebUI.showPanel(owner,!theWebUI.settings["webui.closed_panels"][owner.id]);
	});
	$(".tabbar li:last-child a").after( $("<img></img>").attr( { src: $(".tabbar li:last-child").hasClass("selected") ? 
		"plugins/theme/themes/Excel/images/tabbghfin.png" : 
		"plugins/theme/themes/Excel/images/tabbgfin.png" } ).
		css( { "vertical-align": -3 } ) );
	$(".tabbar li").css( { "vertical-align": -4 } );
}

plugin.tabsShow = theTabs.show;
theTabs.show = function(id)
{
	plugin.tabsShow.call(this,id);
	$(".tabbar img").attr( { src: $(".tabbar li:last-child").hasClass("selected") ? 
		"plugins/theme/themes/Excel/images/tabbghfin.png" : 
		"plugins/theme/themes/Excel/images/tabbgfin.png" } );
}

theWebUI.showPanel = function(pnl,enable)
{
	var cont = $('#'+pnl.id+"_cont");
	cont.toggle(enable);
	theWebUI.settings["webui.closed_panels"][pnl.id] = !enable;
	$('#'+pnl.id+" img").attr("src",enable ? "plugins/theme/themes/Excel/images/pnl_open.png" : "plugins/theme/themes/Excel/images/pnl_close.png");
},

plugin.speedCreate = rSpeedGraph.prototype.create;
rSpeedGraph.prototype.create = function( aOwner )
{
	plugin.speedCreate.call(this,aOwner);
	this.gridColor = "#034084";
	this.backgroundColor = "#ffffff";
}
