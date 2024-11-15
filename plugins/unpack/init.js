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
		if(plugin.enabled && !theContextMenu.get(theUILang.rssMenuLoad))
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

if (plugin.canChangeOptions()) {
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) {
		if (plugin.enabled && plugin.allStuffLoaded) {
			$('#unpack_enabled').prop('checked',(theWebUI.unpackData.enabled == 1));
			$('#unpack_label').prop('checked',(theWebUI.unpackData.addLabel == 1));
			$('#unpack_name').prop('checked',(theWebUI.unpackData.addName == 1));
			$('#edit_unpack1').val( theWebUI.unpackData.path );
			$('#edit_filter').val( theWebUI.unpackData.filter );
			linked($$('unpack_enabled'), 0, ['edit_filter', 'edit_unpack1', 'edit_unpack1_btn'] );
			if (plugin.btn)
				plugin.btn.hide();
		}
		plugin.addAndShowSettings.call(theWebUI, arg);
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

plugin.onLangLoaded = function() {
	var plg = thePlugins.get("_task");
	if(!plg.allStuffLoaded)
		setTimeout(arguments.callee,1000);
	else {
		const dlgUnpackContent = $("<div>").addClass("cont").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.unpackPath),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-12 d-flex align-items-center").append(
						$("<input>").attr({type:"text", id:"edit_unpack", maxlength:200}),
					),
				),
			),
		);
		const dlgUnpackButtons = $("<div>").addClass("buttons-list").append(
			$("<button>").attr({type:"button"}).addClass("OK").on("click", () => {theWebUI.unpack(); return false;}).text(theUILang.ok),
			$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
		);
		theDialogManager.make('dlg_unpack', theUILang.unpack,
			[dlgUnpackContent, dlgUnpackButtons],
			true,
		);

		plugin.attachPageToOptions(
			$("<div>").attr({id:"st_unpack"}).append(
				$("<fieldset>").append(
					$("<legend>").text(theUILang.unpack),
					$("<div>").addClass("row").append(
						$("<div>").addClass("col-12").append(
							$("<input>").attr({type:"checkbox", id:"unpack_enabled", onchange:"linked(this, 0, ['edit_filter', 'edit_unpack1', 'edit_unpack1_btn']);"}),
							$("<label>").attr({for:"unpack_enabled"}).text(theUILang.unpackEnabled),
						),
						$("<div>").addClass("col-12").append(
							$("<input>").attr({type:"text", id:"edit_filter", maxlength:200}),
						),
						$("<div>").addClass("col-12").append(
							$("<label>").attr({id:"lbl_edit_unpack1", for:"edit_unpack1"}).addClass("disabled").text(theUILang.unpackPath),
						),
						$("<div>").addClass("col-12").append(
							$("<input>").attr({type:"text", id:"edit_unpack1", maxlength:200}),
						),
					),
				),
				$("<fieldset>").append(
					$("<legend>").text(theUILang.unpackTorrents),
					$("<div>").addClass("row").append(
						...[
							["unpack_label", theUILang.unpackAddLabel],
							["unpack_name", theUILang.unpackAddName],
						].map(([id, text]) => $("<div>").addClass("col-12 col-md-6").append(
							$("<input>").attr({type:"checkbox", id:id}),
							$("<label>").attr({for:id}).text(text),
						)),
					),
				),
			)[0],
			theUILang.unpack,
		);
		$('#edit_unpack').val( theWebUI.unpackData.path );
		if (thePlugins.isInstalled("_getdir")) {
			new theWebUI.rDirBrowser("edit_unpack");
			if (plugin.canChangeOptions()) {
				new theWebUI.rDirBrowser("edit_unpack1");
			}
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