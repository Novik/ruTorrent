utWebUI.RemoveWithData = function()
{
	if( utWebUI.bConfDel )
	{
		this.delmode = "removewithdata";
		askYesNo( WUILang.Remove_torrents, WUILang.Rem_torrents_prompt, "utWebUI.doRemove()" );
	}
	else
		utWebUI.Perform( "removewithdata" );
}

utWebUI.erasedataCreateMenu = utWebUI.createMenu;

utWebUI.createMenu = function( e, id )
{
	this.erasedataCreateMenu( e,id );
	var el = ContextMenu.get( WUILang.Remove );
	if( el )
	{
		var _c0 = [];
		_c0.push( [WUILang.Delete_data,"utWebUI.RemoveWithData()"] );
		ContextMenu.add( el, [CMENU_CHILD, WUILang.Remove_and, _c0] );
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
