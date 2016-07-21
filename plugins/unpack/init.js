plugin.loadLang();
plugin.loadMainCSS();

if(plugin.canChangeMenu())
{
	plugin.createFileMenu = theWebUI.createFileMenu;
	theWebUI.createFileMenu = function( e, id )
	{
		if(plugin.createFileMenu.call(this, e, id))
		{
		        if(plugin.enabled)
		        {
				plugin.fno = null;
				plugin.mode = null;
				var table = this.getTable("fls");
				if((table.selCount == 1) && this.dID && (this.dID.length==40))
				{
		        		var fid = table.getFirstSelected();
					if(this.settings["webui.fls.view"])
					{
						var arr = fid.split('_f_');
						plugin.fno = arr[1];
					}
					else
						if(!this.dirs[this.dID].isDirectory(fid))
							plugin.fno = fid.substr(3);
					if(plugin.fno!=null)
					{
						if(this.files[this.dID][plugin.fno].percent!=100)
							plugin.fno=null;
						else
						if(plugin.useUnrar && (/.*\.(rar|r\d\d|\d\d\d)$/i).test(this.files[this.dID][plugin.fno].name))
							plugin.mode = 'rar';
						else
						if(plugin.useUnzip && (/.*\.zip$/i).test(this.files[this.dID][plugin.fno].name))
							plugin.mode = 'zip';
						else
							plugin.fno=null;
					}
				}
				if(!thePlugins.isInstalled("data"))
					theContextMenu.add([CMENU_SEP]);
				if(thePlugins.isInstalled("quotaspace") && theWebUI.quotaAlreadyWarn)
					plugin.fno=null;
				this.uID = (plugin.fno==null) ? null : this.dID;
				theContextMenu.add( [theUILang.unpack+'...',  (plugin.fno==null) ? null : "theDialogManager.show('dlg_unpack')"] );
			}
			return(true);
		}
		return(false);
	}

	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled)
		{
			plugin.fno = null;
			plugin.mode = null;
		        var rarPresent = false;
		        var zipPresent = false;
		        var checked = false;
		        this.uID = ((this.getTable("trt").selCount == 1) && !(thePlugins.isInstalled("quotaspace") && theWebUI.quotaAlreadyWarn)) ? (id || this.dID) : null;
			if(this.uID && (this.torrents[this.uID].done==1000) && $type(this.files[this.uID]))
			{
				for(var i in this.files[this.uID]) 
				{
					var file = this.files[this.uID][i];
					if(plugin.useUnrar && (/.*\.(rar|r\d\d|\d\d\d)$/i).test(file.name))
						rarPresent = true;
					else
					if(plugin.useUnzip && (/.*\.zip$/i).test(file.name))
						zipPresent = true;
					checked = true;
				}
			}
			theContextMenu.add( [theUILang.unpack+'...',  
				(this.uID && (this.uID.length==40) && (this.torrents[this.uID].done==1000) && (!checked || rarPresent || zipPresent)) ? 
				"theDialogManager.show('dlg_unpack')" : null] );
		}
	}
}

if(plugin.canChangeOptions())
{
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function( arg )
	{
        	if(plugin.enabled && plugin.allStuffLoaded)
	        {
			$('#unpack_enabled').prop('checked',(theWebUI.unpackData.enabled == 1));
			$('#unpack_label').prop('checked',(theWebUI.unpackData.addLabel == 1));
			$('#unpack_name').prop('checked',(theWebUI.unpackData.addName == 1));
			$('#edit_unpack1').val( theWebUI.unpackData.path );
			$('#edit_filter').val( theWebUI.unpackData.filter );
			linked( $$('unpack_enabled'), 0, ['edit_filter'] );
			if(plugin.btn)
				plugin.btn.hide();
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.unpackWasChanged = function()
	{
		return(	plugin.allStuffLoaded &&
			(($('#unpack_enabled').prop('checked') != (theWebUI.unpackData.enabled == 1 )) ||
			($('#unpack_label').prop('checked') != ( theWebUI.unpackData.addLabel == 1 )) ||
			($('#unpack_name').prop('checked') != ( theWebUI.unpackData.addName == 1 )) ||
			($('#edit_unpack1').val() != theWebUI.unpackData.path) ||
			($('#edit_filter').val() != theWebUI.unpackData.filter))
			);
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function()
	{
		plugin.setSettings.call(this);
		if( plugin.enabled && this.unpackWasChanged() )
			this.request( "?action=setunpack" );
	}

	rTorrentStub.prototype.setunpack = function()
	{
		this.content = "cmd=set&unpack_enabled=" + ( $('#unpack_enabled').prop('checked') ? '1' : '0' ) +
			"&unpack_name=" + ( $('#unpack_name').prop('checked')  ? '1' : '0' ) +
			"&unpack_label=" + ( $('#unpack_label').prop('checked')  ? '1' : '0' ) +
			"&unpack_filter=" + encodeURIComponent($('#edit_filter').val()) +
			"&unpack_path=" + encodeURIComponent($('#edit_unpack1').val());
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/unpack/action.php";
		this.dataType = "script";
	}
}

theWebUI.unpack = function()
{
	theDialogManager.hide('dlg_unpack');
        theWebUI.startConsoleTask( "unpack", plugin.name, 
        { 
        	hash: theWebUI.uID, 
        	dir: $('#edit_unpack').val(),
        	mode: plugin.mode || '',
        	no: plugin.fno || ''
        },
        {
        	noclose: true
        });
}

plugin.onLangLoaded = function()
{
	var plg = thePlugins.get("_task");
	if(!plg.allStuffLoaded)
		setTimeout(arguments.callee,1000);
	else
	{
		theDialogManager.make( 'dlg_unpack', theUILang.unpack,
			"<div class='cont fxcaret'>" +
				"<fieldset>" +
					"<div>" + theUILang.unpackPath + "</div>" +
					"<input type='text' id='edit_unpack' class='TextboxLarge' maxlength='200'/>" +
					"<input type='button' id='btn_unpack' class='Button' value='...' />" +
				"</fieldset>" +
			"</div>"+
			"<div class='aright buttons-list'>" +
				"<input type='button' value='" + theUILang.ok + "' class='OK Button' " +
					" onclick='theWebUI.unpack(); return(false);' />" +
				"<input type='button' value='"+ theUILang.Cancel + "' class='Cancel Button'/>" +
			"</div>", true);

		plugin.attachPageToOptions( $("<div>").attr("id","st_unpack").html(
			"<div>"+
				"<input id=\"unpack_enabled\" type=\"checkbox\""+
					" onchange='linked(this, 0, [\"edit_filter\"]);' />"+
				"<label for=\"unpack_enabled\">"+
					theUILang.unpackEnabled+
				"</label>"+
				"<input type='text' id='edit_filter' class='TextboxMid' maxlength='200'/>" +
			"</div>"+
			"<fieldset>"+
				"<legend>"+theUILang.unpackPath+"</legend>"+
				"<input type='text' id='edit_unpack1' class='TextboxLarge' maxlength='200'/>" +
				"<input type='button' id='btn_unpack1' class='Button' value='...' />" +
			"</fieldset>"+
			"<fieldset>"+
				"<legend>"+theUILang.unpackTorrents+"</legend>"+
				"<div class='checkbox'>" +
					"<input type='checkbox' id='unpack_label'/>"+
					"<label for='unpack_label'>"+ theUILang.unpackAddLabel +"</label>"+
				"</div>" +
				"<div class='checkbox'>" +
					"<input type='checkbox' id='unpack_name'/>"+
					"<label for='unpack_name'>"+ theUILang.unpackAddName +"</label>"+
				"</div>"+
			"</fieldset>"
			)[0], theUILang.unpack );
		$('#edit_unpack').val( theWebUI.unpackData.path );
		if(thePlugins.isInstalled("_getdir"))
		{
			var btn = new theWebUI.rDirBrowser( 'dlg_unpack', 'edit_unpack', 'btn_unpack' );
			theDialogManager.setHandler('dlg_unpack','afterHide',function()
			{
				btn.hide();
			});
			if(plugin.canChangeOptions())
				plugin.btn = new theWebUI.rDirBrowser( 'st_unpack', 'edit_unpack1', 'btn_unpack1' );
		}
		else
		{
			$('#btn_unpack').remove();
			$('#btn_unpack1').remove();
		}
		plugin.markLoaded();
	}		
}

plugin.onRemove = function()
{
	theDialogManager.hide("dlg_unpack");
	plugin.removePageFromOptions("st_unpack");
}

plugin.langLoaded = function() 
{
	if(plugin.enabled)
		plugin.onLangLoaded();
}