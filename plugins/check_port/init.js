// Load language file and CSS for the plugin
plugin.loadLang();
plugin.loadMainCSS();

// Cache jQuery elements for performance and readability
const a = {};

plugin.resetStatus = function(isUpdate) {
	a.iconIPv4.removeClass().addClass("icon port-icon-ipv4 pstatus0");
	a.iconIPv6.removeClass().addClass("icon port-icon-ipv6 pstatus0");
	a.textIPv4.text("").hide();
	a.separator.text("").hide();
	a.textIPv6.text("").hide();

	let title = theUILang.checkingPort || "Checking port status...";
	if (isUpdate) {
		title += "...";
	}
	a.pane.prop("title", title);
};

plugin.init = function() {
	plugin.resetStatus(false);
	theWebUI.request("?action=initportcheck", [plugin.getPortStatus, plugin]);
};

plugin.update = function() {
	plugin.resetStatus(true);
	theWebUI.request("?action=updateportcheck", [plugin.getPortStatus, plugin]);
};

function updateProtocolStatus(data, proto, getStatusText) {
	const status = parseInt(data[proto + '_status']);
	const address = data[proto];
	const port = data[proto + '_port'];
	const isAvailable = address && address !== "-";

	const icon = (proto === 'ipv4') ? a.iconIPv4 : a.iconIPv6;
	const textEl = (proto === 'ipv4') ? a.textIPv4 : a.textIPv6;

	// Status -1 means the protocol is not available on this server
	if (status === -1) {
		icon.removeClass("pstatus0 pstatus1 pstatus2").addClass("pstatus0");
		icon.hide();
		textEl.text("").hide();
		return proto.toUpperCase() + ": " + (theUILang.portNotConfigured || "Not available on this server");
	}

	icon.show();
	icon.removeClass("pstatus0 pstatus1 pstatus2").addClass("pstatus" + status);

	let displayText = "";
	let titleText = "";

	if (isAvailable) {
		displayText = (proto === 'ipv6') ? "[" + address + "]:" + port : address + ":" + port;
		textEl.text(displayText).show();
		titleText = proto.toUpperCase() + ": " + displayText + " (" + getStatusText(status) + ")";
	} else {
		textEl.text("").hide();
		titleText = proto.toUpperCase() + ": " + (theUILang.notAvailable || "N/A") + " (" + getStatusText(status) + ")";
	}
	return titleText;
}

plugin.getPortStatus = function(d) {
	const getStatusText = function(statusCode) {
		return theUILang.portStatus[statusCode] || theUILang.portStatus[0] || "Unknown";
	};

	const titleLines = [
		updateProtocolStatus(d, 'ipv4', getStatusText),
		updateProtocolStatus(d, 'ipv6', getStatusText)
	];

	const ipv4Available = d.ipv4 && d.ipv4 !== "-";
	const ipv6Available = d.ipv6 && d.ipv6 !== "-";
	const ipv4Status = parseInt(d.ipv4_status);
	const ipv6Status = parseInt(d.ipv6_status);

	// Show separator only if both protocols are available (not -1) and have IPs
	if (ipv4Available && ipv6Available && ipv4Status !== -1 && ipv6Status !== -1) {
		a.separator.text("|").show();
	} else {
		a.separator.text("").hide();
	}

	a.pane.prop("title", titleLines.join(" | "));
};

rTorrentStub.prototype.initportcheck = function() {
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/check_port/action.php?init";
	this.dataType = "json";
};

rTorrentStub.prototype.updateportcheck = function() {
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/check_port/action.php";
	this.dataType = "json";
};

plugin.createPortMenu = function(e) {
	if (e.which === 3) {
		theContextMenu.clear();
		theContextMenu.add([(theUILang.checkPort || "Refresh Port Status"), plugin.update]);
		theContextMenu.show();
	}
	return false;
};

plugin.onLangLoaded = function() {
	const container = $("<div>").addClass("port-status-container");

	const ipv4Icon = $("<div>").attr("id", "port-icon-ipv4").addClass("icon");
	const ipv4Text = $("<span>").attr("id", "port-ip-text-ipv4").addClass("d-none d-lg-block port-ip-text-segment");
	const separator = $("<span>").attr("id", "port-ip-separator").addClass("d-none d-lg-block").css({"margin-left": "3px", "margin-right": "3px"});
	const ipv6Icon = $("<div>").attr("id", "port-icon-ipv6").addClass("icon");
	const ipv6Text = $("<span>").attr("id", "port-ip-text-ipv6").addClass("d-none d-lg-block port-ip-text-segment");

	container.append(ipv4Icon, ipv4Text, separator, ipv6Icon, ipv6Text);

	plugin.addPaneToStatusbar("port-pane", container, -1, true);

	a.pane = $("#port-pane");
	a.iconIPv4 = $("#port-icon-ipv4");
	a.textIPv4 = $("#port-ip-text-ipv4");
	a.separator = $("#port-ip-separator");
	a.iconIPv6 = $("#port-icon-ipv6");
	a.textIPv6 = $("#port-ip-text-ipv6");

	if (plugin.canChangeMenu()) {
		a.pane.on("mousedown", plugin.createPortMenu);
	}

	plugin.init();
};

plugin.onRemove = function() {
	plugin.removePaneFromStatusbar("port-pane");
};
