plugin.loadLang();
plugin.loadMainCSS();
if(browser.isKonqueror)
	plugin.loadCSS("konqueror");

theWebUI.checkCreate = function()
{
	if(!$.trim($("#path_edit").val()).length) 
		alert(theUILang.BadTorrentData);
	else
	{
	        plugin.enableFormControls(false);
		theWebUI.requestWithoutTimeout("?action=startcreate",[plugin.start,plugin]);
	}
}

theWebUI.showCreate = function()
{
	theDialogManager.toggle(plugin.task ? 'tconsole' : 'tcreate');
}

theWebUI.saveTorrent = function()
{
	plugin.shutdownTask(true);
	$('#createfile').val( plugin.out );
	$('#saveform').submit();
}

theWebUI.killTorrent = function()
{
	if(plugin.timeout)
	{
		window.clearTimeout(plugin.timeout);
		plugin.timeout = null;
	}
	plugin.pid = 0;
	plugin.setConsoleControls();
        theWebUI.requestWithoutTimeout("?action=killcreate",[plugin.killed,plugin]);
}

plugin.start = function(data)
{
        plugin.enableFormControls(true);
	plugin.shutdownTask();
	if(data.errors.length)
		noty(data.errors[0],"error");
	else
	{
		plugin.task = data.no;
		theDialogManager.hide("tcreate");
		$('#createlog').empty();
		$('#createerrors').empty();
		theDialogManager.show("tconsole");
		plugin.update();	
	}
}

plugin.update = function()
{
	theWebUI.requestWithoutTimeout("?action=checkcreate",[plugin.check,plugin]);
}

plugin.shutdownTask = function(maySave)
{
	if(plugin.timeout)
	{
		window.clearTimeout(plugin.timeout);
		plugin.timeout = null;
	}
	plugin.task = 0;
	plugin.pid = 0;
	plugin["createerrors"] = 0;
	plugin["createlog"] = 0;
	if(!maySave)
		plugin.out = '';
	plugin.status = -1;
	plugin.setConsoleControls();
}

plugin.killed = function(data)
{
	plugin.shutdownTask();
	$('#createerrors').append(theUILang.torrentKilled);
	$('#createerrors')[0].scrollTop = $('#createerrors')[0].scrollHeight;
}

plugin.fillConsole = function(id,arr)
{
        if(arr)
        {
		var s = ''
		for(var i = 0; i<arr.length; i++)
		{
			s += escapeHTML(arr[i]);
			s+='<br>';
		}
		var crc = getCRC( s, 0 );
		if(plugin[id]!=crc)
		{
			plugin[id] = crc;
			$('#'+id).html(s);
			$('#'+id)[0].scrollTop = $('#'+id)[0].scrollHeight;
		}
	}
}

plugin.check = function(data)
{
        if(data.pid)
        {
	        plugin.pid = data.pid;
		plugin.out = data.out;
		plugin.fillConsole('createlog',data.log);
		plugin.fillConsole('createerrors',data.errors);
	}
	plugin.status = data.status;
	plugin.setConsoleControls();
	if((!$type(data.status) || (data.status<0)) && plugin.task)
		plugin.timeout = window.setTimeout(plugin.update,1000);
}

rTorrentStub.prototype.startcreate = function()
{
	var arr = $('#trackers').val().split("\n");
	var trk = '';
	for( var i in arr )
		trk+=($.trim(arr[i])+'\r');

	this.content = "cmd=start&path_edit="+encodeURIComponent($.trim($("#path_edit").val()))+
		"&comment="+encodeURIComponent($.trim($("#comment").val()))+
		"&trackers="+encodeURIComponent(trk);
	if($("#piece_size").length)
		this.content+=("&piece_size="+$("#piece_size").val());
	if($("#private").prop("checked"))
		this.content+=("&private=1");
	if($("#start_seeding").prop("checked"))
		this.content+=("&start_seeding=1");
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/create/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.checkcreate = function()
{
	this.content = "cmd=check&no="+plugin.task;
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/create/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.killcreate = function()
{
	this.content = "cmd=kill&no="+plugin.task;
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/create/action.php";
	this.dataType = "json";
}

plugin.enableFormControls = function( enable )
{
        if(plugin.btn)
		plugin.btn.hide();
	$('#path_edit').attr('disabled',!enable);
	$('#browse_path').attr('disabled',!enable);
	$('#trackers').attr('disabled',!enable);
	$('#start_seeding').attr('disabled',!enable);
	$('#private').attr('disabled',!enable);
	$('#piece_size').attr('disabled',!enable);
	$('#torrentCreate').attr('disabled',!enable);
}

plugin.setConsoleControls = function()
{
	$('#torrentSave').attr('disabled',!plugin.out);
	$('#torrentKill').attr('disabled',!plugin.pid || (plugin.status>=0));
	if((plugin.status>=0) || !plugin.task)
		$('#create_btns').css( "background", "none" );
	else
		$('#create_btns').css( "background", "transparent url(./plugins/create/images/ajax-loader.gif) no-repeat 5px 7px" );
}

plugin.onLangLoaded = function()
{
	this.addButtonToToolbar("create",theUILang.mnu_create,"theWebUI.showCreate()","remove");
	this.addSeparatorToToolbar("remove");

	var pieceSize = 
		"<label>"+theUILang.PieceSize+": </label>"+
		"<select id='piece_size' name='piece_size'>"+
			"<option value=\"32\">32"+theUILang.KB+"</option>"+
			"<option value=\"64\">64"+theUILang.KB+"</option>"+
			"<option value=\"128\">128"+theUILang.KB+"</option>"+
			"<option value=\"256\" selected=\"selected\">256"+theUILang.KB+"</option>"+
			"<option value=\"512\">512"+theUILang.KB+"</option>"+
			"<option value=\"1024\">1"+theUILang.MB+"</option>"+
			"<option value=\"2048\">2"+theUILang.MB+"</option>"+
			"<option value=\"4096\">4"+theUILang.MB+"</option>"+
			"<option value=\"8192\">8"+theUILang.MB+"</option>"+
			"<option value=\"16384\">16"+theUILang.MB+"</option>"+
			"</select>";
	if(this.hidePieceSize)
		pieceSize = "";	
	theDialogManager.make("tcreate",theUILang.CreateNewTorrent,
		"<div class='cont fxcaret'>"+
			"<fieldset>"+
				"<legend>"+theUILang.SelectSource+"</legend>"+
				"<input type='text' id='path_edit' name='path_edit' class='TextboxLarge' autocomplete='off'/>"+
				"<input type=button value='...' id='browse_path' class='Button'><br/>"+
			"</fieldset>"+
			"<fieldset>"+
				"<legend>"+theUILang.TorrentProperties+"</legend>"+
                       	               "<label>"+theUILang.Trackers+": </label>"+
				"<textarea id='trackers' name='trackers'></textarea><br/>"+
                               	       "<label>"+theUILang.Comment+": </label>"+
        	               	"<input type='text' id='comment' name='comment' class='TextboxLarge'/><br/>"+
				pieceSize+	
			"</fieldset>"+
			"<fieldset>"+
				"<legend>"+theUILang.Other+"</legend>"+
				"<label id='nomargin'><input type='checkbox' name='start_seeding' id='start_seeding'/>"+theUILang.StartSeeding+"</label>"+
				"<label id='nomargin'><input type='checkbox' name='private' id='private'/>"+theUILang.PrivateTorrent+"</label><br/>"+
			"</fieldset>"+
		"</div>"+
		"<div class='aright buttons-list'><input type='button' id='torrentCreate' value='"+theUILang.torrentCreate+"' class='Button' onclick='theWebUI.checkCreate()'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>");
	theDialogManager.make("tconsole",theUILang.CreateNewTorrent,
		"<div class='fxcaret'>"+
			"<form action='plugins/create/action.php' method='post' id='saveform' target='_blank'>"+
				"<input type='hidden' id='createfile' name='tname'/>"+
				"<input type='hidden' name='cmd' value='get'/>"+
			"</form>"+
			"<fieldset>"+
				"<legend>"+theUILang.createConsole+"</legend>"+
				"<div class='console' id='createlog'></div>"+
			"</fieldset>"+
			"<fieldset>"+
				"<legend>"+theUILang.createErrors+"</legend>"+
				"<div class='console' id='createerrors'></div>"+
			"</fieldset>"+
		"</div>"+
		"<div class='aright buttons-list' id='create_btns'>"+
			"<input type='button' id='torrentSave' value='"+theUILang.torrentSave+"' class='OK Button' onclick='theWebUI.saveTorrent()'/>"+
			"<input type='button' id='torrentKill' value='"+theUILang.torrentKill+"' class='Button' onclick='theWebUI.killTorrent()'/>"+
			"<input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/>"+
		"</div>",true);
	if(thePlugins.isInstalled("_getdir"))
	{
		plugin.btn = new theWebUI.rDirBrowser( 'tcreate', 'path_edit', 'browse_path', null, true );
		theDialogManager.setHandler('tcreate','afterHide',function()
		{
			plugin.btn.hide();
		});
	}
	else
		$('#browse_path').remove();
	theDialogManager.setHandler('tconsole','afterHide',function()
	{
		if(!plugin.pid || (plugin.status>=0))
			plugin.task = 0;
	});
};

plugin.onRemove = function()
{
	theDialogManager.hide("tcreate");
	theDialogManager.hide("tconsole");
	this.removeSeparatorFromToolbar("remove");
	this.removeButtonFromToolbar("create");
	if(plugin.timeout)
	{
		window.clearTimeout(plugin.timeout);
		plugin.timeout = null;
	}
}
