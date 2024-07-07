plugin.loadLang();
plugin.loadMainCSS();

plugin.init = function()
{
	theWebUI.request("?action=initportcheck", [plugin.getPortStatus, plugin]);
}

plugin.update = function()
{
	$("#port-status-icon").get(0).classList = "pstatus0";
	theWebUI.request("?action=updateportcheck", [plugin.getPortStatus, plugin]);
}

plugin.getPortStatus = function(d)
{
	$("#port-status-icon").get(0).className = "pstatus"+d.status;
	$("#port-ip-text").data({ipAddr: d.ip + ":" + d.port, status: d.status});
	this.diaplayText();
}

plugin.diaplayText = function()
{
	const ipAddr = $("#port-ip-text");
	if(ipAddr.hasClass("censored")) {
		ipAddr.text("*.*.*.*:*");
		$("#port-holder").prop("title", `*.*.*.*:* - ${theUILang.portStatus[ipAddr.data("status")]} (${theUILang.clickReveal})`);
	} else {
		ipAddr.text(ipAddr.data("ipAddr"));
		$("#port-holder").prop("title", `${ipAddr.text()} - ${theUILang.portStatus[ipAddr.data("status")]} (${theUILang.clickHide})`);
	}
}

plugin.toggleIPAddress = function()
{
	$("#port-ip-text").toggleClass("censored");
	this.diaplayText();
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
		$("<div>").addClass("status-cell").attr({id: "port-holder"}).append(
			$("<div>").attr({id: "port-status-icon"}),
			$("<span>").addClass("stval censored").attr({id: "port-ip-text"}).on("click", () => plugin.toggleIPAddress()),
		),
		"st_fd",
	);
	if(plugin.canChangeMenu())
		$("#port-status-icon").addClass("pstatus0").mouseclick(plugin.createPortMenu);
	plugin.init();
}

plugin.onRemove = function()
{
	plugin.removePaneFromStatusbar("port-holder");
}
