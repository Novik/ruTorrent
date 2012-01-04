if(plugin.canChangeMenu())
{
	theWebUI.removeWithData = function()
	{
		if( theWebUI.settings["webui.confirm_when_deleting"] )
		{
			this.delmode = "removewithdata";
			askYesNo( theUILang.Remove_torrents, theUILang.Rem_torrents_prompt, "theWebUI.doRemove()" );
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
					this.isTorrentCommandEnabled("remove",id) ? "theWebUI.removeWithData()" : null] );
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
			cmd.addParameter( "string", "1" );
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
