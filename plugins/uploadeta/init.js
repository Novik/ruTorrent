
if(plugin.canChangeColumns())
{
	/* Add the columns to the torrent list */
	theWebUI.tables.trt.columns.push({text: "UL target", width: "60px", id: "upload_target", type: TYPE_NUMBER});
	theWebUI.tables.trt.columns.push({text: "UL remaining", width: "60px", id: "upload_remaining", type: TYPE_NUMBER});
	theWebUI.tables.trt.columns.push({text: "UL ETA", width: "60px", id: "upload_eta", type: TYPE_NUMBER});

	/* Use the formatter to add data to the columns */
	plugin.trtFormat = theWebUI.tables.trt.format;
	theWebUI.tables.trt.format = function(table, arr) {
		for(var i in arr) {
			switch(table.getIdByCol(i)){
				case "upload_target":
					plugin.upload_target = parseInt(arr[2]) * (theWebUI.uploadtarget/100);
					arr[i] = theConverter.bytes(plugin.upload_target, 2);
					break;
				case "upload_remaining":
					plugin.upload_remaining = plugin.upload_target - arr[5];
					arr[i] = theConverter.bytes(plugin.upload_remaining, 2);
					break;
				case "upload_eta":
					arr[i] = (arr[8]>0) ? theConverter.time(Math.floor(plugin.upload_remaining/arr[8])) : "\u221e";
					break;
			}
		}
		return(plugin.trtFormat(table, arr));
	}
}

/* Create option page */
var input = "<fieldset><legend>Upload target</legend><div><input id='uploadtarget' type='text' size=1/>%</div></fieldset>";
var description = "<div>This plugin only will show the amount of data and time that is left to an upload ratio target. It will not automatically remove a torrent for you. Look at ratio.min.set in your rtorrent configuration file to remove a torrent when a upload target has been reached.</div>";
rPlugin.prototype.attachPageToOptions($("<div>").attr("id","st_uploadeta").html(input + description)[0], 'Upload ETA');

/* Field shoud be numbers only */
$('#uploadtarget').keypress(function(event){
	if((event.which < 48 || event.which > 57) && event.which != 8 && event.which != 0)
		return false;
});

/* Show the current value in the option page */
plugin.addAndShowSettings = theWebUI.addAndShowSettings;
theWebUI.addAndShowSettings = function( arg ){
	if(plugin.enabled){
		$('#uploadtarget').val(theWebUI.uploadtarget);
	}
	plugin.addAndShowSettings.call(theWebUI, arg);
}

/* Only update if it has changed */
theWebUI.uploadetaWasChanged = function() {
	return(theWebUI.uploadtarget != $('#uploadtarget').val());
}

/* Call setuploadtarget to store a new value */
plugin.setSettings = theWebUI.setSettings;
theWebUI.setSettings = function() {
	plugin.setSettings.call(this);
	if(plugin.enabled && this.uploadetaWasChanged())
		this.request("?action=setuploadtarget");
}

/* Use the action.php script to save the new value */
rTorrentStub.prototype.setuploadtarget = function(){
	theWebUI.uploadtarget = $('#uploadtarget').val();
	
	this.content = "uploadtarget=" + theWebUI.uploadtarget;
    this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/uploadeta/action.php";
	this.dataType = "script";
}

/* Remove our stuff from the ui if the plugin is removed */
plugin.onRemove = function() {
	this.removePageFromOptions("st_uploadeta");
	theWebUI.getTable("trt").removeColumnById("upload_target");
	theWebUI.getTable("trt").removeColumnById("upload_remaining");
	theWebUI.getTable("trt").removeColumnById("upload_eta");
}
