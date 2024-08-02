plugin.loadLang();

if(plugin.canChangeMenu())
{
	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled && plugin.canChangeMenu())
		{
			var el = theContextMenu.get( theUILang.Force_recheck );
			if( el )
			{
				theContextMenu.add( el, [theUILang.checkTorrent, 
					((this.getTable("trt").selCount>1) && this.getHashes('checktorrent')) ||
					this.isTorrentCommandEnabled("checktorrent",id) ? "theWebUI.perform( 'checktorrent' )" : null] );
			}
		}
	}
		
	rTorrentStub.prototype.checktorrent = function()
	{
		this.content = "cmd=check";
		for( var i = 0; i < this.hashes.length; i++ )
			this.content += ("&hash="+this.hashes[i]);
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/rutracker_check/action.php";
		this.dataType = "json";
	}
}

plugin.config = theWebUI.config;
theWebUI.config = function()
{
	plugin.reqId1 = theRequestManager.addRequest("trt", theRequestManager.map("d.get_custom=")+"chk-state",function(hash,torrent,value)
	{
		torrent.chkstate = value;
		if(torrent.chkstate==4)	// STE_DELETED
			torrent.state |= dStatus.error;
	});
	plugin.reqId2 = theRequestManager.addRequest("trt", theRequestManager.map("d.get_custom=")+"chk-time",function(hash,torrent,value)
	{
		torrent.chktime = value;
	});
	plugin.config.call(this);
}

plugin.isTorrentCommandEnabled = theWebUI.isTorrentCommandEnabled;
theWebUI.isTorrentCommandEnabled = function(act,hash) 
{
	if(act=="checktorrent")
	{
		var regex = '^(http|https)://';
		return(hash && (hash.length==40) && !(hash.match(regex)));
	}

	return(plugin.isTorrentCommandEnabled.call(this,act,hash));
}

plugin.getStatusIcon = theWebUI.getStatusIcon;
theWebUI.getStatusIcon = function(torrent) 
{
	if(plugin.allStuffLoaded && (torrent.chkstate==4))	// STE_DELETED
		return(["Status_Error", theUILang.chkResults[torrent.chkstate-1]]);
	return(	plugin.getStatusIcon.call(theWebUI,torrent) );
}

plugin.updateDetails = theWebUI.updateDetails;
theWebUI.updateDetails = function()
{
	plugin.updateDetails.call(this);
	if(plugin.enabled && (this.dID != "") && this.torrents[this.dID])
	{
		var torrent = this.torrents[this.dID];
		$("#chktime").text( torrent.chktime ? theConverter.time(new Date().getTime()/1000-(iv(torrent.chktime)+theWebUI.deltaTime/1000),true) : '' );
		$("#chkresult").text( torrent.chkstate ? theUILang.chkResults[iv(torrent.chkstate-1)] : '' );
	}
}

plugin.onLangLoaded = function()
{
	$("#mainlayout").append( 
		$("<div>").addClass("row Header").append(
			$("<div>").attr({id:"chkinfo1"}).addClass("col-12").text(theUILang.chkHdr),
		),
		$("<div>").addClass("row").append(
			$("<div>").attr({id:"chkinfo2"}).addClass("col-12 col-md-2").append(
				$("<span>").addClass("det-hdr").text(theUILang.checkedAt + ": "),
			),
			$("<div>").addClass("col-12 col-md-10").append(
				$("<span>").attr({id:"chktime"}).addClass("det"),
			),
			$("<div>").addClass("col-12 col-md-2").append(
				$("<span>").addClass("det-hdr").text(theUILang.checkedResult + ": "),
			),
			$("<div>").addClass("col-12 col-md-10").append(
				$("<span>").attr({id:"chkresult"}).addClass("det"),
			),
		),
	);
}

plugin.onRemove = function()
{
	$("#chkinfo1,#chkinfo2").remove();
	theRequestManager.removeRequest( "trt", plugin.reqId1 );
	theRequestManager.removeRequest( "trt", plugin.reqId2 );
	$.each(theWebUI.torrents,function(hash,torrent)
	{
		delete torrent.chkstate;
		delete torrent.chktime;
	});
}