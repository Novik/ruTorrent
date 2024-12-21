plugin.loadLang();
plugin.loadMainCSS();

plugin.init = function()
{
	$("#port-pane .icon").removeClass().addClass("icon pstatus0");
	theWebUI.request("?action=initportcheck", [plugin.getPortStatus, plugin]);
}

plugin.update = function()
{
	$("#port-pane .icon").removeClass().addClass("icon pstatus0");
	theWebUI.request("?action=updateportcheck", [plugin.getPortStatus, plugin]);
}

plugin.getPortStatus = function(d)
{
	$("#port-pane").prop("title", d.ip+":"+d.port+": "+theUILang.portStatus[d.status]);
	$("#port-pane .icon").removeClass().addClass("icon pstatus" + d.status)
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
	plugin.addPaneToStatusbar(
		"port-pane",
		$("<div>").append(
			$("<div>").addClass("icon"),
			$("<span>").attr("id","port-ip-text").addClass("d-none d-lg-block"),
		),
		-1, true,
	);
	if(plugin.canChangeMenu()) {
		$("#port-pane .icon").addClass("pstatus0");
		$("#port-pane").mouseclick( plugin.createPortMenu );
	}
	plugin.init();
}

plugin.onRemove = function()
{
	plugin.removePaneFromStatusbar("port-pane");
}
