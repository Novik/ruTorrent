plugin.loadMainCSS();
plugin.loadLang();

theWebUI.editTrackers = function(id)
{
	var trk = this.trackers[id];
	var s = "";
	if(trk.length)
	{
		var lastGroup = trk[0].group;
		for(var i=0; i<trk.length; i++)
		{
		        if(trk[i].name!="dht://")
		        {
				if(lastGroup != trk[i].group)
				{
					s+='\r\n';
					lastGroup = trk[i].group;
				}
				s+=trk[i].name;
				s+='\r\n';
			}
		}
	}
	$('#etrackers').val($.trim(s));
	$('#ecomment').val($.trim(this.torrents[id].comment));
	$('#editok').prop("disabled",false);
	theDialogManager.show("tedit");
}

if(plugin.canChangeMenu())
{
	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function(e, id) 
	{
		plugin.createMenu.call(this,e,id);
		if(plugin.enabled && plugin.allStuffLoaded)
		{
			var el = theContextMenu.get(theUILang.Properties);
			if(el)
				theContextMenu.add(el,[theUILang.EditTrackers, (this.getTable("trt").selCount == 1) && (theWebUI.dID.length==40) ? "theWebUI.editTrackers('"+id+"')" : null]);
		}
	}

	plugin.createTrackerMenu = theWebUI.createTrackerMenu;
	theWebUI.createTrackerMenu = function(e, id) 
	{
		if(plugin.createTrackerMenu.call(theWebUI, e, id) && plugin.allStuffLoaded && plugin.enabled)
		{
			theContextMenu.add([CMENU_SEP]);
			theContextMenu.add([theUILang.EditTrackers,  (theWebUI.dID.length==40) ? "theWebUI.editTrackers('"+theWebUI.dID+"')" : null]);
			return(true);
		}
		return(false);
	}
}

theWebUI.sendEdit = function() 
{
	$('#editok').prop("disabled",true);
	this.requestWithTimeout("?action=edittorrent",[this.receiveEdit, this], function()
	{
		theWebUI.timeout();
		$('#editok').prop("disabled",true);
	});
}

theWebUI.receiveEdit = function(d)
{
	$('#editok').prop("disabled",false);
	if(!d.errors.length)
	{
		window.setTimeout( function() { theWebUI.getTrackers(d.hash) }, 1000 );
		theDialogManager.hide("tedit");
	}
	else
		for( var i=0; i<d.errors.length; i++)
		{
			var s = eval(d.errors[i].desc);
			if(d.errors[i].prm)
				s = s + " ("+d.errors[i].prm+")";
			noty(s,"error");
		}
}

plugin.onLangLoaded = function() 
{
	theDialogManager.make( "tedit", theUILang.EditTorrentProperties,
		"<div class='cont fxcaret'>"+
			"<fieldset>"+
				"<label>"+theUILang.Trackers+": </label>"+
				"<textarea id='etrackers'></textarea><br/>"+
				"<label>"+theUILang.Comment+": </label>"+
                               	"<input type='text' id='ecomment' name='ecomment' class='TextboxLarge'/><br/>"+
			"</fieldset>"+
		"</div>"+
		"<div class='aright buttons-list'><input type='button' value='"+theUILang.ok+"' class='OK Button' id='editok' onclick='theWebUI.sendEdit(); return(false);'/><input type='button' value='"+theUILang.Cancel+"' class='Cancel Button'/></div>",
		true);
}

rTorrentStub.prototype.edittorrent = function()
{
	this.content = "hash="+theWebUI.dID+"&comment="+encodeURIComponent($('#ecomment').val());
	var arr = $('#etrackers').val().split("\n");
	for(var i = 0; i<arr.length; i++)	
	{
		var s = arr[i].replace(/(^\s+)|(\s+$)/g, "");
		if(s.toLowerCase()!='dht://')
			this.content = 	this.content+"&tracker="+encodeURIComponent(s);
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/edit/action.php";
	this.dataType = "json";
}

plugin.onRemove = function()
{
	theDialogManager.hide("tedit");
}