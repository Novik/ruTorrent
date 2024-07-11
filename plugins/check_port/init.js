plugin.loadLang();
plugin.loadMainCSS();

plugin.init = function()
{
	$("port-pane .icon").addClass("pstatus0");
	theWebUI.request("?action=initportcheck", [plugin.getPortStatus, plugin]);
}

plugin.update = function()
{
	$("port-pane .icon").addClass("pstatus0");
	theWebUI.request("?action=updateportcheck", [plugin.getPortStatus, plugin]);
}

plugin.getPortStatus = function(d)
{
	$("#port-pane").data(d);
	$("#port-pane").prop("title", "*.*.*.*:* - " + theUILang.portStatus[d.status]);
	$("#port-pane .icon").removeClass().addClass("icon pstatus" + d.status)
	$("#port-ip-text").addClass("censored").text("*.*.*.*:*");
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

plugin.toggleIpAddress = function()
{
	const ipAddr = $("#port-ip-text");
	const ipPane = $("#port-pane");
	if (ipAddr.hasClass("censored")) {
		ipAddr.text("*.*.*.*:*");
		ipPane.prop(
			"title", `${ipAddr.text()} - ${theUILang.portStatus[ipPane.data("status")]} (${theUILang.clickReveal})`
		);
	} else {
		ipAddr.text(ipPane.data("ip") + ":" + ipPane.data("port"));
		ipPane.prop(
			"title", `${ipAddr.text()} - ${theUILang.portStatus[ipPane.data("status")]} (${theUILang.clickHide})`
		);
	}
}

plugin.onLangLoaded = function()
{
	plugin.addPaneToStatusbar(
		"port-pane",
		$("<div>").append(
			$("<div>").addClass("icon"),
			$("<span>").attr("id","port-ip-text"),
		),
		-1, false,
	);
	if(plugin.canChangeMenu()) {
		$("#port-pane .icon").addClass("pstatus0");
		$("#port-pane").mouseclick( plugin.createPortMenu );
	}
	$("#port-ip-text").on("click", () => {
		$("#port-ip-text").toggleClass("censored");
		this.toggleIpAddress();
	});
	plugin.init();
}

plugin.onRemove = function()
{
	plugin.removePaneFromStatusbar("port-pane");
}
