plugin.loadLang();

if(plugin.canChangeColumns()) {
	plugin.config = theWebUI.config;
	theWebUI.config = function(data) {
		/* Add the columns to the torrent list */
		theWebUI.tables.trt.columns.push({text: "UL target", width: "100px", id: "upload_target", type: TYPE_NUMBER});
		theWebUI.tables.trt.columns.push({text: "UL remaining", width: "100px", id: "upload_remaining", type: TYPE_NUMBER});
		theWebUI.tables.trt.columns.push({text: "UL ETA", width: "100px", id: "upload_eta", type: TYPE_NUMBER});

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
						if (plugin.upload_remaining<=0)
							arr[i] = theConverter.bytes(0, 2);
						else
							arr[i] = theConverter.bytes(plugin.upload_remaining, 2);
						break;
					case "upload_eta":
						if (plugin.upload_remaining<=0)
							arr[i] = "";
						else
							arr[i] = (arr[8]>0) ? theConverter.time(Math.floor(plugin.upload_remaining/arr[8])) : "\u221e";
						break;
				}
			}
			return(plugin.trtFormat(table, arr));
		}
		plugin.config.call(this,data);
		plugin.trtRenameColumn();
	}
}

plugin.trtRenameColumn = function() {
	if(plugin.allStuffLoaded) {
		theWebUI.getTable("trt").renameColumnById("upload_target",theUILang.ULtarget);
		theWebUI.getTable("trt").renameColumnById("upload_remaining",theUILang.ULremaining);
		theWebUI.getTable("trt").renameColumnById("upload_eta",theUILang.ULETA);
	}
	else
		setTimeout(arguments.callee,1000);
}

/* Create option page */
plugin.onLangLoaded = function() {
var input = "<fieldset><legend>"+theUILang.uploadtarget+"</legend><div><input id='uploadtarget' type='text' size='2' onkeypress='return isNumberKey(event);'/>%</div></fieldset>";
var description = "<div>"+theUILang.ULdescription+"</div>";
this.attachPageToOptions($("<div>").attr("id","st_uploadeta").html(input + description)[0], theUILang.uploadeta);
}

/* Field shoud be numbers only */
isNumberKey = function (evt) {
	var charCode = (evt.which) ? evt.which : evt.keyCode

	var removeKey = [9, 37, 39, 46, 8];
	if (removeKey.indexOf(charCode) > -1) {
		return true;
	}

	if (charCode > 31 && (charCode < 48 || charCode > 57) && charCode != 46) {
		return false;
	}
	return true;
}

/* Show the current value in the option page */
plugin.addAndShowSettings = theWebUI.addAndShowSettings;
theWebUI.addAndShowSettings = function( arg ){
	if(plugin.enabled)
		$('#uploadtarget').val(theWebUI.uploadtarget);

	plugin.addAndShowSettings.call(theWebUI, arg);
}

/* Only update if it has changed */
theWebUI.uploadetaWasChanged = function() {
	if(!isNaN($('#uploadtarget').val()) && $('#uploadtarget').val()!="")
		if($('#uploadtarget').val()>=0)
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
	theWebUI.uploadtarget = parseInt($('#uploadtarget').val());

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
