plugin.loadLang();

if(plugin.canChangeMenu())
{
	theWebUI.dumpTorrent = function()
	{
		var table = this.getTable("trt");
		var hash = table.getFirstSelected();
		theWebUI.startConsoleTask( "dumptorrent", plugin.name, { "hash": hash }, { noclose: true } );
	}

	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled)
		{
			var el = theContextMenu.get( theUILang.Properties );
			if (el) theContextMenu.add( el, [theUILang.dumpTorrent, "theWebUI.dumpTorrent()"] );
		}
	}
}
