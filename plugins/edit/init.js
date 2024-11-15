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
		trackers: s.trim(), 
		comment: theWebUI.torrents[id].comment.trim(),
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
			var regex = '^(http|https)://';
			return(hash && (hash.length==40) && !(hash.match(regex)));
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

plugin.onLangLoaded = function() {
	const teditContent = $("<div>").addClass("cont").append(
		$("<fieldset>").append(
			$("<legend>").addClass("d-none d-md-block").text(theUILang.EditTorrentProperties),
			$("<div>").addClass("m-0 row align-items-center").append(
				$("<div>").addClass("col-md-3 d-flex flex-row align-items-center align-self-start").append(
					$("<input>").attr({type: "checkbox", name: "eset_trackers", id: "eset_trackers"}),
					$("<label>").attr({for: "eset_trackers"}).text(theUILang.Trackers + ": "),
				),
				$("<div>").addClass("col-md-9").append(
					$("<textarea>").attr({id: "etrackers"}),
				),
				$("<div>").addClass("col-md-3 d-flex flex-row align-items-center").append(
					$("<input>").attr({type: "checkbox", name: "eset_comment", id: "eset_comment"}),
					$("<label>").attr({for: "eset_comment"}).text(theUILang.Comment + ": "),
				),
				$("<div>").addClass("col-md-9").append(
					$("<input>").attr({type: "text", name: "ecomment", id: "ecomment"}),
				),
				$("<div>").addClass("col-3 d-flex flex-row align-items-center").append(
					$("<input>").attr({type: "checkbox", name: "eset_private", id: "eset_private"}),
					$("<label>").attr({for: "eset_private"}).text(theUILang.trkPrivate + ": "),
				),
				$("<div>").addClass("col-3").append(
					$("<select>").attr({id: "eprivate"}).append(
						$("<option>").val(0).text(theUILang.no),
						$("<option>").val(1).text(theUILang.yes),
					),
				),
			),
		),
	);
	const teditButtons = $("<div>").addClass("buttons-list").append(
		$("<button>").attr({id: "editok"}).addClass("OK").on("click", () => {theWebUI.sendEdit(); return false;}).text(theUILang.ok),
		$("<button>").addClass("Cancel").text(theUILang.Cancel),
	);
	theDialogManager.make("tedit", theUILang.EditTorrentProperties,
		[teditContent, teditButtons],
		true,
	);
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
