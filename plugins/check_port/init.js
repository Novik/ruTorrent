plugin.loadLang();
plugin.loadMainCSS();

plugin.init = function()
{
	$$("port-td").className = "statuscell pstatus0";
	theWebUI.request("?action=initportcheck", [plugin.getPortStatus, plugin]);
}

plugin.update = function()
{
	$$("port-td").className = "statuscell pstatus0";
	theWebUI.request("?action=updateportcheck", [plugin.getPortStatus, plugin]);
}

plugin.getPortStatus = function(d)
{
	$("#port-td").prop("title",d.ip+":"+d.port+": "+theUILang.portStatus[d.status]).get(0).className = "statuscell pstatus"+d.status;
	$("#port-ip-text").text(d.ip+':'+d.port);
}

rTorrentStub.prototype.initportcheck = function()
{
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/check_port/action.php?init";
	this.dataType = "json";
}

rTorrentStub.prototype.updateportcheck = function()
{
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/check_port/action.php";
	this.dataType = "json";
}

plugin.createPortMenu = function(e)
{
        if(e.which==3)
        {
		theContextMenu.clear();
		theContextMenu.add([ theUILang.checkPort, plugin.update ]);
		theContextMenu.show();
	}
	return(false);
}

plugin.onLangLoaded = function()
{
	plugin.addPaneToStatusbar("port-td",$("<div>").attr("id","port-holder")
		.append( $("<span></span>").attr("id","port-ip-text").css({overflow: "visible"}) ).get(0),2);
	if(plugin.canChangeMenu())
		$("#port-td").addClass("pstatus0").mouseclick( plugin.createPortMenu );
	plugin.init();
}

plugin.onRemove = function()
{
	plugin.removePaneFromStatusbar("port-td");
}
