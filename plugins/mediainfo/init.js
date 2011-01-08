plugin.loadLang();

if(plugin.enabled && plugin.canChangeMenu()) {
	theWebUI.mediaInfo = function( hash, no ) {
		$("#media_info").html("Fetching...");
		theDialogManager.show("dlg_info");

		var AjaxReq = jQuery.ajax({
			type: "GET",
			timeout: theWebUI.settings["webui.reqtimeout"],
			async : true,
			cache: false,
			data: "hash="+ hash +"&no="+ no,
			url : "plugins/mediainfo/action.php",
			success: function(data){
				if (data == '') {
					theDialogManager.hide("dlg_info");
					askYesNo( theUILang.mediaError, theUILang.badMediaData, "" );
					return;
				}
				$("#media_info").html(data);
				theDialogManager.center("dlg_info");
			}
		});
	}
	plugin.createFileMenu = theWebUI.createFileMenu;
	theWebUI.createFileMenu = function( e, id ) {
		if(plugin.createFileMenu.call(this, e, id)) {
			if(plugin.enabled) {
				theContextMenu.add([CMENU_SEP]);
				var fno = null;
				var table = this.getTable("fls");
				if(table.selCount == 1)
				{
					var fid = table.getFirstSelected();
					if(this.settings["webui.fls.view"])
						fno = fid.substr(43);
						else
						if(!this.dirs[this.dID].isDirectory(fid))
							fno = fid.substr(3);
					}
					theContextMenu.add( [theUILang.mediainfo,  (fno==null) ? null : "theWebUI.mediaInfo('" + theWebUI.dID + "',"+fno+")"] );
				}
				return(true);
			}
			return(false);
		}
	}

plugin.onLangLoaded = function()
{
	if(this.enabled)
	{
		theDialogManager.make( 'dlg_info', "MediaInfo", ''+
	                        '<pre style=" line-height: 10px; width: 600px; height: 450px; '+
	                        'overflow: auto; margin: 0px;" '+
	                        'id="media_info">Fetching...</pre>');
	}
}

plugin.onRemove = function()
{
	theDialogManager.hide("dlg_info");
}