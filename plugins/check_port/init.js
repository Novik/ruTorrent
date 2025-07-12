// Load language file and CSS for the plugin
plugin.loadLang();
plugin.loadMainCSS();

// Cache jQuery elements for performance and readability
const a = {};

/**
 * Resets the UI elements to a neutral/unknown state, typically while checking
 * @param {boolean} isUpdate - If true, indicates a manual refresh, adding "..." to the title
 */
plugin.resetStatus = function(isUpdate) {
	// Set a tooltip to indicate that a check is in progress
	let title = theUILang.checkingPort || "Checking port status...";
	if (isUpdate) {
		title += "..."; // Append ellipsis for manual updates
	}
	if (a.pane) {
		a.pane.prop("title", title);
	}
};

// Function to manually trigger an update of the port status
plugin.update = function() {
	plugin.resetStatus(true);
	// Request a port status update from the backend
	theWebUI.request("?action=updateportcheck", [plugin.getPortStatus, plugin]);
};

/**
 * Main callback to process the port status response from the backend and update the UI
 * @param {object} d - The JSON object received from the backend response
 */
plugin.getPortStatus = function(d) {
	// Always clear the pane first to rebuild the UI dynamically
	a.pane.empty();

	const getStatusText = (statusCode) => theUILang.portStatus[statusCode] || theUILang.portStatus[0] || "Unknown";
	const isIPv4Available = d.ipv4 && d.ipv4 !== "-";
	const isIPv6Available = d.ipv6 && d.ipv6 !== "-";
	const titleLines = [];

	// Conditionally create and append the IPv4 group only if the IP is available
	if (isIPv4Available) {
		const status = parseInt(d.ipv4_status);
		const displayText = `${d.ipv4}:${d.ipv4_port}`;
		const ipv4Group = $("<div>").attr("id", "port-group-ipv4").addClass("port-group");
		ipv4Group.append($("<div>").attr("id", "port-icon-ipv4").addClass("icon pstatus" + status));
		ipv4Group.append($("<span>").attr("id", "port-ip-text-ipv4").addClass("d-none d-lg-block port-ip-text-segment").text(displayText));
		a.pane.append(ipv4Group);
		titleLines.push(`IPV4: ${displayText} (${getStatusText(status)})`);
	} else if (d.use_ipv4) {
		titleLines.push(`IPV4: ${(theUILang.notAvailable || "N/A")}`);
	}

	// Conditionally create and append the separator
	if (isIPv4Available && isIPv6Available) {
		a.pane.append($("<span>").attr("id", "port-ip-separator").addClass("d-none d-lg-block").text("|"));
	}

	// Conditionally create and append the IPv6 group only if the IP is available
	if (isIPv6Available) {
		const status = parseInt(d.ipv6_status);
		const displayText = `[${d.ipv6}]:${d.ipv6_port}`;
		const ipv6Group = $("<div>").attr("id", "port-group-ipv6").addClass("port-group");
		ipv6Group.append($("<div>").attr("id", "port-icon-ipv6").addClass("icon pstatus" + status));
		ipv6Group.append($("<span>").attr("id", "port-ip-text-ipv6").addClass("d-none d-lg-block port-ip-text-segment").text(displayText));
		a.pane.append(ipv6Group);
		titleLines.push(`IPV6: ${displayText} (${getStatusText(status)})`);
	} else if (d.use_ipv6) {
		titleLines.push(`IPV6: ${(theUILang.notAvailable || "N/A")}`);
	}

	// Set the final combined tooltip for the entire status pane
	a.pane.prop("title", titleLines.join(" | "));

	// Re-attach the context menu handler since we cleared the pane
	if (plugin.canChangeMenu()) {
		a.pane.off("mousedown", plugin.createPortMenu).on("mousedown", plugin.createPortMenu);
	}
};

// Defines the AJAX request for the initial port check
rTorrentStub.prototype.initportcheck = function() {
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/check_port/action.php?init";
	this.dataType = "json";
};

// Defines the AJAX request for subsequent port status updates
rTorrentStub.prototype.updateportcheck = function() {
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/check_port/action.php";
	this.dataType = "json";
};

// Creates and shows the context menu (right-click menu) for the status pane
plugin.createPortMenu = function(e) {
	if (e.which === 3) { // Right mouse button
		theContextMenu.clear();
		// Add a "Refresh" option to the context menu
		theContextMenu.add([(theUILang.checkPort || "Refresh Port Status"), plugin.update]);
		theContextMenu.show();
	}
	return false; // Prevent the default browser context menu from appearing
};

plugin.onLangLoaded = function() {
	// Create a temporary loading state immediately
	const container = $("<div>").addClass("port-status-container")
		.append($("<div>").addClass("icon pstatus0")); // Add a single "unknown" icon as a placeholder

	plugin.addPaneToStatusbar("port-pane", container, -1, true);
	a.pane = $("#port-pane");
	a.pane.prop("title", theUILang.checkingPort || "Checking port status...");

	// Trigger the initial port check to get the configuration and build the final UI
	theWebUI.request("?action=initportcheck", [plugin.getPortStatus, plugin]);
};

// This function is called when the plugin is removed/unloaded
plugin.onRemove = function() {
	// Remove the pane from the status bar to clean up the UI
	plugin.removePaneFromStatusbar("port-pane");
};
