plugin.loadMainCSS();
plugin.loadLang();

plugin.getTorrentEditables = function(id)
{
	var trk = theWebUI.trackers[id];
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
	return( 
	{ 
		trackers: $.trim(s), 
		comment: $.trim(theWebUI.torrents[id].comment),
		private: theWebUI.torrents[id].private,
		set_trackers: true,
		set_comment: true
	});
}

theWebUI.editTrackers = function(id)
{
	var editable = null;
	if(!id)
	{
		var sr = this.getTable("trt").rowSel;
		for(var k in sr) 
		{
			if((sr[k] == true) && this.isTorrentCommandEnabled("edittorrent",k))
			{
				var e = plugin.getTorrentEditables(k);
				if(!editable)
					editable = e;
				else
				{
					if(e.trackers!=editable.trackers)
					{
						editable.trackers = '';
						editable.set_trackers = false;
					}
					if(e.comment!=editable.comment)
					{
						editable.comment = '';
						editable.set_comment = false;
					}
					if(!editable.set_trackers && !editable.set_comment)
					{
						break;
					}
				}
			}
		}
	}
	else
		editable = plugin.getTorrentEditables(id);
	$('#etrackers').val(editable.trackers);
	$('#ecomment').val(editable.comment);
	$('#eset_trackers').prop('checked',editable.set_trackers);
	$('#eset_comment').prop('checked',editable.set_comment);
	$('#eset_private').prop('checked',false);
	$('#eprivate').val(editable.private);
	$('#editok').prop("disabled",false);
	theDialogManager.show("tedit");
}

if(plugin.canChangeMenu())
{
	plugin.isTorrentCommandEnabled = theWebUI.isTorrentCommandEnabled;
	theWebUI.isTorrentCommandEnabled = function(act,hash) 
	{
		if(act=="edittorrent")
		{
			if(!plugin.isTorrentCommandEnabled.call(this,act,hash))
				return(false);
			else
				return(hash && (hash.length==40))
		}
		return(plugin.isTorrentCommandEnabled.call(this,act,hash));
	}

	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function(e, id) 
	{
		plugin.createMenu.call(this,e,id);
		if(plugin.enabled && plugin.allStuffLoaded)
		{
			var el = theContextMenu.get(theUILang.Properties);
			if(el)
			{
				theContextMenu.add([theUILang.EditTrackers,  
					((this.getTable("trt").selCount>1) && this.getHashes('edittorrent')) || this.isTorrentCommandEnabled("edittorrent",id) ? 
					"theWebUI.editTrackers('"+theWebUI.dID+"')" : null]);				
			}
		}
	}

	plugin.createTrackerMenu = theWebUI.createTrackerMenu;
	theWebUI.createTrackerMenu = function(e, id) 
	{
		if(plugin.createTrackerMenu.call(theWebUI, e, id))
		{
			if(plugin.enabled && plugin.allStuffLoaded)
			{
				theContextMenu.add([CMENU_SEP]);
				theContextMenu.add([theUILang.EditTrackers,  
					this.isTorrentCommandEnabled("edittorrent",theWebUI.dID) ? "theWebUI.editTrackers('"+theWebUI.dID+"')" : null]);
			}
			return(true);
		}
		return(false);
	}
}

theWebUI.sendEdit = function() 
{
	$('#editok').prop("disabled",true);
	this.requestWithTimeout("?action=edittorrent"+this.getHashes('edittorrent'),[this.receiveEdit, this], function()
	{
		theWebUI.timeout();
		$('#editok').prop("disabled",false);
	});
}

theWebUI.receiveEdit = function(d)
{
	$('#editok').prop("disabled",false);
	if(d.hash.length)
	{
		window.setTimeout( function() 
		{ 
			theWebUI.getTable("trt").clearSelection();
			theWebUI.dID = "";
			theWebUI.clearDetails();
			theWebUI.getTorrents("list=1"); 
		}, 1000 );
		theDialogManager.hide("tedit");
	}
	if(d.errors.length)
	{
		for( var i=0; i<d.errors.length; i++)
		{
			var s = eval(d.errors[i].desc);
			if(d.errors[i].prm)
				s = s + " ("+d.errors[i].prm+")";
			noty(s,"error");
		}
	}			
}

plugin.onLangLoaded = function() 
{
	theDialogManager.make( "tedit", theUILang.EditTorrentProperties,
		"<div class='cont fxcaret'>"+
			"<fieldset>"+
				"<input type='checkbox' name='eset_trackers' id='eset_trackers'/><label for='eset_trackers'>"+theUILang.Trackers+": </label>"+
				"<div class='text-wrapper'><textarea id='etrackers'></textarea></div>"+
				"<input type='checkbox' name='eset_comment' id='eset_comment'/><label for='eset_comment'>"+theUILang.Comment+": </label>"+
                               	"<input type='text' id='ecomment' name='ecomment' class='TextboxLarge'/>"+
                               	"<input type='checkbox' name='eset_private' id='eset_private'/><label for='eset_private'>"+theUILang.trkPrivate+": </label>"+
                               	"<select id='eprivate'>"+
	                               	"<option value='0'>"+theUILang.no+"</option>"+
	                               	"<option value='1'>"+theUILang.yes+"</option>"+	                               	
                               	"</select>"+
			"</fieldset>"+
		"</div>"+
		"<div class='aright buttons-list'><input type='button' value='"+theUILang.ok+"' class='OK Button' id='editok' onclick='theWebUI.sendEdit(); return(false);'/><input type='button' value='"+theUILang.Cancel+"' class='Cancel Button'/></div>",
		true);
}

rTorrentStub.prototype.edittorrent = function()
{
	this.content = "&comment="+encodeURIComponent($('#ecomment').val())+
		"&set_comment="+($('#eset_comment').is(":checked")+0)+
		"&set_trackers="+($('#eset_trackers').is(":checked")+0)+
		"&set_private="+($('#eset_private').is(":checked")+0)+
		"&private="+$('#eprivate').val();
	var arr = $('#etrackers').val().split("\n");
	for(var i = 0; i<arr.length; i++)	
	{
		var s = arr[i].replace(/(^\s+)|(\s+$)/g, "");
		if(s.toLowerCase()!='dht://')
			this.content += ("&tracker="+encodeURIComponent(s));
	}
	for( var i = 0; i < this.hashes.length; i++ )
		this.content += ("&hash="+this.hashes[i]);	
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/edit/action.php";
	this.dataType = "json";
}

plugin.onRemove = function()
{
	theDialogManager.hide("tedit");
}
