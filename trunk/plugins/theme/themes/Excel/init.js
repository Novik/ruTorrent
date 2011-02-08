plugin.oldAllDone = plugin.allDone;
plugin.allDone = function()
{
	plugin.oldAllDone.call(this);

	$(".catpanel").prepend(
		$("<img></img>").attr( { src: "plugins/theme/themes/Excel/images/pnl_open.png", hspace: 10 } ).
			css( { "margin-left": -31, "margin-top": 2, "vertical-align": -3 } ) );
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

theWebUI.togglePanel = function(pnl)
{
	var cont = $('#'+pnl.id+"_cont");
	cont.toggle();
	if(cont.is(":visible"))
		$('#'+pnl.id+" img").attr("src","plugins/theme/themes/Excel/images/pnl_open.png");
	else
		$('#'+pnl.id+" img").attr("src","plugins/theme/themes/Excel/images/pnl_close.png");
}

plugin.speedCreate = rSpeedGraph.prototype.create;
rSpeedGraph.prototype.create = function( aOwner )
{
	plugin.speedCreate.call(this,aOwner);
	this.gridColor = "#034084";
	this.backgroundColor = "#ffffff";
}