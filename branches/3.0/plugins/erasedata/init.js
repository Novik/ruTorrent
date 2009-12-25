theWebUI.removeWithData = function()
{
	if( theWebUI.settings["webui.confirm_when_deleting"] )
	{
		this.delmode = "removewithdata";
		askYesNo( WUILang.Remove_torrents, WUILang.Rem_torrents_prompt, "theWebUI.doRemove()" );
	}
	else
		theWebUI.perform( "removewithdata" );
}

if(plugin.canChangeMenu())
{
	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		var el = theContextMenu.get( WUILang.Remove );
		if( el )
		{
			var _c0 = [];
			_c0.push( [WUILang.Delete_data,"theWebUI.removeWithData()"] );
			theContextMenu.add( el, [CMENU_CHILD, WUILang.Remove_and, _c0] );
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
