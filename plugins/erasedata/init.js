plugin.loadLang();

if(plugin.canChangeMenu())
{
	theWebUI.removeWithData = function(force)
	{
		plugin.force_delete = force;
		if( theWebUI.settings["webui.confirm_when_deleting"] )
		{
			this.delmode = "removewithdata";
			askYesNo( theUILang.Remove_torrents, (plugin.force_delete && plugin.enableForceDeletion ? theUILang.Rem_torrents_with_path_prompt : theUILang.Rem_torrents_content_prompt), "theWebUI.doRemove()" );
		}
		else
			theWebUI.perform( "removewithdata" );
	}

    if ( plugin.replaceRemoveTorrent )
	{
	    if (plugin.enabled)
	    {
	        theWebUI.removeTorrent = function()
	        {
	            theWebUI.removeWithData( plugin.enableForceDeletion );
	        }
	    }
	}
	else
	{
	    plugin.createMenu = theWebUI.createMenu;
	    theWebUI.createMenu = function( e, id )
	    {
		    plugin.createMenu.call(this, e, id);
		    if(plugin.enabled)
		    {
			    var el = theContextMenu.get( theUILang.Remove );
			    if( el )
			    {
				    var _c0 = [];
				    _c0.push( [theUILang.Delete_data,
					    (this.getTable("trt").selCount>1) ||
					    this.isTorrentCommandEnabled("remove",id) ? "theWebUI.removeWithData(false)" : null] );
				    if( plugin.enableForceDeletion )
				    {
					    _c0.push( [theUILang.Delete_data_with_path,
						    (this.getTable("trt").selCount>1) ||
						    this.isTorrentCommandEnabled("remove",id) ? "theWebUI.removeWithData(true)" : null] );
				    }						
				    theContextMenu.add( el, [CMENU_CHILD, theUILang.Remove_and, _c0] );
			    }
		    }
	    }
	}

	rTorrentStub.prototype.removewithdata = function()
	{
		this.content = "mode=removewithdata&v=" + (plugin.force_delete && plugin.enableForceDeletion ? "2" : "1");
		for( var i = 0; i < this.hashes.length; i++ )
			this.content += "&hash=" + this.hashes[i];
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/httprpc/action.php";
		this.dataType = "json";
		this.commands = [];
	}
}
