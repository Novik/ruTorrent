plugin.loadLang();
plugin.loadMainCSS();
if(browser.isKonqueror)
	plugin.loadCSS("konqueror");

plugin.recentTrackers = {};

theWebUI.checkCreate = function()
{
	theDialogManager.hide('tcreate');
       	var arr = $('#trackers').val().split("\n");
	var trk = '';
	for( var i in arr )
		trk+=(arr[i].trim()+'\r');
        this.startConsoleTask( "create", plugin.name,
	{
		"piece_size" : $('#piece_size').val(),
		"trackers" : trk,
		"path_edit" : $("#path_edit").val().trim(),
		"comment" : $("#comment").val().trim(),
		"source" : $("#source").val().trim(),
		"private" : $('#private').prop('checked') ? 1 : 0,
		"start_seeding" : $('#start_seeding').prop('checked') ? 1 : 0,
		"hybrid" : $('#hybrid').prop('checked') ? 1 : 0
	},
	{
	       	noclose: true
	});
}

plugin.onTaskFinished = function(task,fromBackground)
{
	if(!fromBackground)
	{
		$("#xtaskno").val(task.no);
		if(!task.status)
			$('#xcsave').show();
	}
	theWebUI.request('?action=rtget',[plugin.getRecentTrackers, plugin]);
}

rTorrentStub.prototype.rtget = function()
{
	this.content = "cmd=rtget";
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/create/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.rtdelete = function()
{
	this.content = "cmd=rtdelete&trackers="+plugin.deleteFromRecentTrackers;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/create/action.php";
	this.dataType = "json";
}

theWebUI.showCreate = function()
{
	if( $("#trackers").val().trim().length < 1 )
		$("#deleteFromRecentTrackers").addClass("disabled");
	else
		$("#deleteFromRecentTrackers").removeClass("disabled");
	$('#start_seeding').prop('disabled',!theWebUI.systemInfo.rTorrent.started);
	if(theWebUI.systemInfo.rTorrent.started)
		$('#lbl_start_seeding').removeClass('disabled');
	else
		$('#lbl_start_seeding').addClass('disabled');
	theDialogManager.show('tcreate');
}

plugin.getRecentTrackers = function( data )
{
	plugin.recentTrackers = data;
	if(propsCount(plugin.recentTrackers))
		$("#recentTrackers").removeClass("disabled");
        else
	        $("#recentTrackers").addClass("disabled");
}

theWebUI.addTrackerToBox = function(ann)
{
	$("#deleteFromRecentTrackers").removeClass("disabled");
	var val = $('#trackers').val();
	if(val.length)
		val+='\r\n';
	$('#trackers').val( val+ann );
	$('#trackers').trigger('focus');
}

theWebUI.showRecentTrackers = function()
{
	if(propsCount(plugin.recentTrackers))
	{
		theContextMenu.clear();
		for( var domain in plugin.recentTrackers )
			theContextMenu.add([domain,"theWebUI.addTrackerToBox('"+addslashes(plugin.recentTrackers[domain])+"')"]);
		var offs = $("#recentTrackers").offset();
		theContextMenu.show(offs.left,offs.top-theContextMenu.obj.height()-5);
	}
}

theWebUI.deleteFromRecentTrackers = function()
{
	$("#deleteFromRecentTrackers").addClass("disabled");
	var trklist = $('#trackers').val();
	if(!trklist)
		return(false);
       	var arr = trklist.split("\n");
	$('#trackers').val('');
	var trk = '';
	for( var i in arr )
		trk+=(arr[i].trim()+'\r');
	plugin.deleteFromRecentTrackers = trk;
	theWebUI.request('?action=rtdelete');
	theWebUI.request('?action=rtget',[plugin.getRecentTrackers, plugin]);
}

plugin.onLangLoaded = function()
{
	var plg = thePlugins.get("_task");
	if(!plg.allStuffLoaded)
		setTimeout(arguments.callee,1000);
	else
	{
		theWebUI.request('?action=rtget',[plugin.getRecentTrackers, plugin]);
		$('#tsk_btns').prepend(
			"<input type='button' class='Button' id='xcsave' value='"+theUILang.torrentSave+"'>"
			 );
		plugin.addButtonToToolbar("create",theUILang.mnu_create,"theWebUI.showCreate()","remove");
		plugin.addSeparatorToToolbar("remove");
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
				"<option value=\"32768\">32"+theUILang.MB+"</option>"+
				"<option value=\"65536\">64"+theUILang.MB+"</option>"+
				"</select>";
		if(plugin.hidePieceSize)
			pieceSize = "";

		var hybridTorrent =
				"<label for='hybrid' id='lbl_hybrid' class='nomargin'>"+
				"<input type='checkbox' name='hybrid' id='hybrid'/>"+theUILang.HybridTorrent+"</label>";

		if(plugin.hideHybrid)
			hybridTorrent = "";

		theDialogManager.make("tcreate",theUILang.CreateNewTorrent,
			"<div class='cont fxcaret'>"+
				"<fieldset>"+
					"<legend>"+theUILang.SelectSource+"</legend>"+
					"<input type='text' id='path_edit' name='path_edit' class='TextboxLarge' autocomplete='off'/>"+
					"<input type=button value='...' id='browse_path' class='Button'><br/>"+
				"</fieldset>"+
				"<fieldset>"+
					"<legend>"+theUILang.TorrentProperties+"</legend>"+
                       	               "	<label>"+theUILang.Trackers+": </label>"+
					"<textarea id='trackers' name='trackers'></textarea><br/>"+
        	                       	       "<label>"+theUILang.Comment+": </label>"+
        		               	"<input type='text' id='comment' name='comment' class='TextboxLarge'/><br/>"+
        	                       	       "<label>" + theUILang.source + ": </label>"+
        		               	"<input type='text' id='source' name='source' class='TextboxLarge'/><br/>"+
					pieceSize+
				"</fieldset>"+
				"<fieldset>"+
					"<legend>"+theUILang.Other+"</legend>"+
					"<label for='start_seeding' id='lbl_start_seeding' class='nomargin'><input type='checkbox' name='start_seeding' id='start_seeding'/>"+theUILang.StartSeeding+"</label>"+
					"<label class='nomargin'><input type='checkbox' name='private' id='private'/>"+theUILang.PrivateTorrent+"</label>"+
					hybridTorrent+"<br/>"+
				"</fieldset>"+
			"</div>"+
			"<div class='aright buttons-list'><input type='button' id='recentTrackers' value='"+theUILang.recentTrackers+"...' class='Button menuitem' onclick='theWebUI.showRecentTrackers()'/><input type='button' id='deleteFromRecentTrackers' value='"+theUILang.deleteFromRecentTrackers+"' class='Button' onclick='theWebUI.deleteFromRecentTrackers()'/><input type='button' id='torrentCreate' value='"+theUILang.torrentCreate+"' class='OK Button' onclick='theWebUI.checkCreate()'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",true);
		$(document.body).append($("<iframe name='xcreatefrm'/>").css({visibility: "hidden"}).attr( { name: "xcreatefrm", id: "xcreatefrm" } ).width(0).height(0));
		$(document.body).append(
			$('<form action="plugins/create/action.php" id="xgetfile" method="post" target="xcreatefrm">'+
				'<input type="hidden" name="cmd" value="getfile">'+
				'<input type="hidden" name="no" id="xtaskno" value="0">'+
			'</form>').width(0).height(0));
		$("#xcsave").on('click', function()
		{
			$('#xgetfile').submit();
		});
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
		theDialogManager.setHandler('tskConsole','beforeShow',function()
		{
			$('#xcsave').hide();
		});
		plugin.markLoaded();
	}
};

plugin.onRemove = function()
{
	plugin.removeSeparatorFromToolbar("remove");
	plugin.removeButtonFromToolbar("create");
}

plugin.langLoaded = function()
{
	if(plugin.enabled)
		plugin.onLangLoaded();
}
