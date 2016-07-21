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

	rTorrentStub.prototype.removewithdata = function()
	{
		for( var i = 0; i < this.hashes.length; i++ )
		{
			var cmd = new rXMLRPCCommand( "d.set_custom5" );
			cmd.addParameter( "string", this.hashes[i] );
			cmd.addParameter( "string", (plugin.force_delete && plugin.enableForceDeletion ? "2" : "1") );
			this.commands.push( cmd );
			cmd = new rXMLRPCCommand( "d.delete_tied" );
			cmd.addParameter( "string", this.hashes[i] );
			this.commands.push( cmd );
			cmd = new rXMLRPCCommand( "d.erase" );
			cmd.addParameter( "string", this.hashes[i] );
			this.commands.push( cmd );
		}
	}
}
