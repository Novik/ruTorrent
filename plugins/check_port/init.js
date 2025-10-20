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
	// Reset icons to the "unknown" state (pstatus0)
	a.iconIPv4.removeClass().addClass("icon port-icon-ipv4 pstatus0");
	a.iconIPv6.removeClass().addClass("icon port-icon-ipv6 pstatus0");
	// Hide IP address text and the separator
	a.textIPv4.text("").hide();
	a.separator.text("").hide();
	a.textIPv6.text("").hide();

	// Set a tooltip to indicate that a check is in progress
	let title = theUILang.checkingPort || "Checking port status...";
	if (isUpdate) {
		title += "..."; // Append ellipsis for manual updates
	}
	a.pane.prop("title", title);
};

// Initial check when the plugin is first loaded
plugin.init = function() {
	plugin.resetStatus(false);
	// Request the initial port status from the backend
	theWebUI.request("?action=initportcheck", [plugin.getPortStatus, plugin]);
};

// Function to manually trigger an update of the port status
plugin.update = function() {
	plugin.resetStatus(true);
	// Request a port status update from the backend
	theWebUI.request("?action=updateportcheck", [plugin.getPortStatus, plugin]);
};

/**
 * Updates the UI for a specific IP protocol (IPv4 or IPv6) based on data from the backend
 * @param {object} data - The response data containing status for both protocols
 * @param {string} proto - The protocol to update, either "ipv4" or "ipv6"
 * @param {function} getStatusText - A function to retrieve the localized status string
 * @returns {string} The formatted title line for this protocol's status
 */
function updateProtocolStatus(data, proto, getStatusText) {
	const status = parseInt(data[proto + '_status']);
	const address = data[proto];
	const port = data[proto + '_port'];
	const isAvailable = address && address !== "-"; // Check if an IP address was returned

	// Select the correct UI elements for the given protocol
	const icon = (proto === 'ipv4') ? a.iconIPv4 : a.iconIPv6;
	const textEl = (proto === 'ipv4') ? a.textIPv4 : a.textIPv6;

	icon.removeClass("pstatus0 pstatus1 pstatus2").addClass("pstatus" + status);

	let displayText = "";
	let titleText = "";

	if (isAvailable) {
		// Format display text as IP:PORT, with brackets for IPv6
		displayText = (proto === 'ipv6') ? `[${address}]:${port}` : `${address}:${port}`;
		textEl.text(displayText).show();
		// Create a detailed title for the tooltip
		titleText = `${proto.toUpperCase()}: ${displayText} (${getStatusText(status)})`;
	} else {
		// If IP is not available, hide the text element
		textEl.text("").hide();
		titleText = `${proto.toUpperCase()}: ${(theUILang.notAvailable || "N/A")} (${getStatusText(status)})`;
	}
	return titleText; // Return the generated title string
}

/**
 * Main callback to process the port status response from the backend and update the UI
 * @param {object} d - The JSON object received from the backend response
 */
plugin.getPortStatus = function(d) {
	// Helper function to get the localized text for a status code
	const getStatusText = (statusCode) => theUILang.portStatus[statusCode] || theUILang.portStatus[0] || "Unknown";

	// Update the status for both IPv4 and IPv6 and collect their title lines
	const titleLines = [
		updateProtocolStatus(d, 'ipv4', getStatusText),
		updateProtocolStatus(d, 'ipv6', getStatusText)
	];

	// Check if both IPv4 and IPv6 addresses are available
	const ipv4Available = d.ipv4 && d.ipv4 !== "-";
	const ipv6Available = d.ipv6 && d.ipv6 !== "-";

	// Show a separator only if both protocols have an IP address to display
	if (ipv4Available && ipv6Available) {
		a.separator.text("|").show();
	} else {
		a.separator.text("").hide();
	}

	// Set the combined tooltip for the entire status pane
	a.pane.prop("title", titleLines.join(" | "));
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
	// Create status bar elements in a more readable way
	const container = $("<div>").addClass("port-status-container");

	const ipv4Icon = $("<div>").attr("id", "port-icon-ipv4").addClass("icon");
	const ipv4Text = $("<span>").attr("id", "port-ip-text-ipv4").addClass("d-none d-lg-block port-ip-text-segment");
	const separator = $("<span>").attr("id", "port-ip-separator").addClass("d-none d-lg-block").css({"margin-left": "3px", "margin-right": "3px"});
	const ipv6Icon = $("<div>").attr("id", "port-icon-ipv6").addClass("icon");
	const ipv6Text = $("<span>").attr("id", "port-ip-text-ipv6").addClass("d-none d-lg-block port-ip-text-segment");

	// Assemble the elements into the container
	container.append(ipv4Icon, ipv4Text, separator, ipv6Icon, ipv6Text);

	// Add the newly created pane to the ruTorrent status bar
	plugin.addPaneToStatusbar("port-pane", container, -1, true);

	// Now that the pane is in the DOM, cache all the jQuery elements for future use
	a.pane = $("#port-pane");
	a.iconIPv4 = $("#port-icon-ipv4");
	a.textIPv4 = $("#port-ip-text-ipv4");
	a.separator = $("#port-ip-separator");
	a.iconIPv6 = $("#port-icon-ipv6");
	a.textIPv6 = $("#port-ip-text-ipv6");

	// If the user has permissions, attach the right-click context menu
	if (plugin.canChangeMenu()) {
		a.pane.on("mousedown", plugin.createPortMenu);
	}

	// Trigger the initial port check
	plugin.init();
};

// This function is called when the plugin is removed/unloaded
plugin.onRemove = function() {
	// Remove the pane from the status bar to clean up the UI
	plugin.removePaneFromStatusbar("port-pane");
};
