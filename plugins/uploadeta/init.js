plugin.loadLang();

if(plugin.canChangeColumns()) {
	plugin.config = theWebUI.config;
	theWebUI.config = function() {
		// Add the columns to the torrent list
		theWebUI.tables.trt.columns.push({text: "UL target", width: "100px", id: "upload_target", type: TYPE_NUMBER});
		theWebUI.tables.trt.columns.push({text: "UL remaining", width: "100px", id: "upload_remaining", type: TYPE_NUMBER});
		theWebUI.tables.trt.columns.push({text: "UL ETA", width: "100px", id: "upload_eta", type: TYPE_NUMBER});

		// Format the data
		plugin.webui_tables_trt_format = theWebUI.tables.trt.format;
		theWebUI.tables.trt.format = function(table, arr) {
			for(var i in arr) {
				switch(table.getIdByCol(i)){
					case "upload_target":
						arr[i] = theConverter.bytes(arr[i], 'table');
						break;
					case "upload_remaining":
						arr[i] = theConverter.bytes(arr[i]>0 ? arr[i] : 0, 'table');
						break;
					case "upload_eta":
						arr[i] = (arr[i]>0) ? theConverter.time(arr[i]) : "\u221e";
						break;
				}
			}
			return(plugin.webui_tables_trt_format(table, arr));
		}

		// Add the data to the torrent
		theWebUI.webui_addTorrents = theWebUI.addTorrents;
		theWebUI.addTorrents = function(data){
			$.each(data.torrents, function(hash, torrent){
				torrent.upload_target = parseInt(torrent.size) * (theWebUI.uploadtarget/100);
				torrent.upload_remaining = torrent.upload_target - torrent.uploaded;
				torrent.upload_eta = Math.floor(torrent.upload_remaining/torrent.ul);
			});
			theWebUI.webui_addTorrents(data);
		}
		plugin.config.call(this);
		plugin.trtRenameColumn();
	}
}

plugin.trtRenameColumn = function() {
	if(plugin.allStuffLoaded) {
		theWebUI.getTable("trt").renameColumnById("upload_target",theUILang.ULtarget);
		theWebUI.getTable("trt").renameColumnById("upload_remaining",theUILang.ULremaining);
		theWebUI.getTable("trt").renameColumnById("upload_eta",theUILang.ULETA);
		if(thePlugins.isInstalled("rss"))
			plugin.rssRenameColumn();
		if(thePlugins.isInstalled("extsearch"))
			plugin.tegRenameColumn();
	}
	else
		setTimeout(arguments.callee,1000);
}

plugin.rssRenameColumn = function() {
	if(theWebUI.getTable("rss").created) {
		theWebUI.getTable("rss").renameColumnById("upload_target",theUILang.ULtarget);
		theWebUI.getTable("rss").renameColumnById("upload_remaining",theUILang.ULremaining);
		theWebUI.getTable("rss").renameColumnById("upload_eta",theUILang.ULETA);
	}
	else
		setTimeout(arguments.callee,1000);
}

plugin.tegRenameColumn = function() {
	if(theWebUI.getTable("teg").created) {
		theWebUI.getTable("teg").renameColumnById("upload_target",theUILang.ULtarget);
		theWebUI.getTable("teg").renameColumnById("upload_remaining",theUILang.ULremaining);
		theWebUI.getTable("teg").renameColumnById("upload_eta",theUILang.ULETA);
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
