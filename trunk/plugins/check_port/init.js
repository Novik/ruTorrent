plugin.loadLang();
plugin.loadMainCSS();

plugin.update = function()
{
	$$("port-td").className = "statuscell pstatus0";
	theWebUI.request("?action=portcheck", [plugin.getPortStatus, plugin]);
}

plugin.getPortStatus = function(d)
{
	$("#port-td").prop("title",d.port+": "+theUILang.portStatus[d.status]).get(0).className = "statuscell pstatus"+d.status;
}

rTorrentStub.prototype.portcheck = function()
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
		theContextMenu.add([ theUILang.checkPort,  plugin.update ]);
		theContextMenu.show();
	}
	return(false);
}

plugin.onLangLoaded = function()
{
	plugin.addPaneToStatusbar("port-td",$("<div>").attr("id","port-holder").get(0),2);
	if(plugin.canChangeMenu())
		$("#port-td").addClass("pstatus0").mouseclick( plugin.createPortMenu );
	plugin.update();
}

plugin.onRemove = function()
{
	plugin.removePaneFromStatusbar("port-td");
}
