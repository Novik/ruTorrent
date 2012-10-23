plugin.loadLang();
plugin.loadMainCSS();

plugin.tasks = { length: 0 };

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
        	if(plugin.enabled)
	        {
			$$('unpack_enabled').checked = ( theWebUI.unpackData.enabled == 1 );
			$$('unpack_label').checked = ( theWebUI.unpackData.addLabel == 1 );
			$$('unpack_name').checked = ( theWebUI.unpackData.addName == 1 );
			$$('edit_unpack1').value = theWebUI.unpackData.path;
			$$('edit_filter').value = theWebUI.unpackData.filter;
			if(plugin.btn)
				plugin.btn.hide();
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.unpackWasChanged = function()
	{
		return(	($$('unpack_enabled').checked != ( theWebUI.unpackData.enabled == 1 )) ||
			($$('unpack_label').checked != ( theWebUI.unpackData.addLabel == 1 )) ||
			($$('unpack_name').checked != ( theWebUI.unpackData.addName == 1 )) ||
			($$('edit_unpack1').value != theWebUI.unpackData.path) ||
			($$('edit_filter').value != theWebUI.unpackData.filter)
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
		this.content = "cmd=set&unpack_enabled=" + ( $$('unpack_enabled').checked ? '1' : '0' ) +
			"&unpack_name=" + ( $$('unpack_name').checked  ? '1' : '0' ) +
			"&unpack_label=" + ( $$('unpack_label').checked  ? '1' : '0' ) +
			"&unpack_filter=" + encodeURIComponent($$('edit_filter').value) +
			"&unpack_path=" + encodeURIComponent($$('edit_unpack1').value);
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/unpack/action.php";
		this.dataType = "script";
	}
}

theWebUI.unpack = function()
{
	theDialogManager.hide('dlg_unpack');
	this.request("?action=unpack",[theWebUI.startUnpackTask,this]);
}

theWebUI.startUnpackTask = function(info)
{
        if(info.no>0)
        {
		plugin.tasks[info.no] = info;
		plugin.tasks.length++;
		noty(theUILang.unpackTaskStarted+' ('+info.name+'=>'+info.out+')', "alert");
	}
	else
		noty((info.no<0) ? theUILang.unpackTaskFailed : theUILang.unpackNoFiles, "error");
}

theWebUI.finishUnpackTask = function(task,info)
{
	for( var i in info.errors )
		try { log(info.errors[i],true,'mono'); } catch(e) {};
	if(info.status==0)
		noty(theUILang.unpackTaskOK+' ('+task.name+'=>'+task.out+')', "success");
	else
		noty(theUILang.unpackTaskFailed+' ('+task.name+'=>'+task.out+')', "error");
}

theWebUI.checkUnpackTask = function(info)
{
	for( var i in info )
	{
	        var task = plugin.tasks[info[i].no];
		if($type(task))
		{
			this.finishUnpackTask(task,info[i]);
			delete plugin.tasks[info[i].no];
			plugin.tasks.length--;
		}
	}
}

plugin.checkTasks = function()
{
	if(plugin.enabled)
	{
	        if(plugin.tasks.length)
			theWebUI.request("?action=checkunpack",[theWebUI.checkUnpackTask,theWebUI]);
	}
	else
		if(plugin.interval)
			window.clearInterval(plugin.interval);
}

rTorrentStub.prototype.checkunpack = function()
{
        this.content = "cmd=check";
	for(var i in plugin.tasks)
		if(i!="length")
			this.content+=("&no="+i);
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/unpack/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.unpack = function()
{
	this.content = "cmd=start&hash="+theWebUI.uID+"&dir="+encodeURIComponent($('#edit_unpack').val());
	if(plugin.mode!==null)
		this.content+=("&mode="+plugin.mode);
	if(plugin.fno!==null)
		this.content+=("&no="+plugin.fno);
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/unpack/action.php";
	this.dataType = "json";
}

plugin.onLangLoaded = function()
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

	plugin.interval = window.setInterval( plugin.checkTasks, 3000 );
	this.attachPageToOptions( $("<div>").attr("id","st_unpack").html(
		"<div>"+
			"<input id=\"unpack_enabled\" type=\"checkbox\"/>"+
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
	$$('edit_unpack').value = theWebUI.unpackData.path;
	if(thePlugins.isInstalled("_getdir"))
	{
		var btn = new theWebUI.rDirBrowser( 'dlg_unpack', 'edit_unpack', 'btn_unpack' );
		theDialogManager.setHandler('dlg_unpack','afterHide',function()
		{
			btn.hide();
		});
		if(this.canChangeOptions())
			this.btn = new theWebUI.rDirBrowser( 'st_unpack', 'edit_unpack1', 'btn_unpack1' );
	}
	else
	{
		$('#btn_unpack').remove();
		$('#btn_unpack1').remove();
	}
}

plugin.onRemove = function()
{
	theDialogManager.hide("dlg_unpack");
	plugin.removePageFromOptions("st_unpack");
	if(plugin.interval)
		window.clearInterval(plugin.interval);
}
