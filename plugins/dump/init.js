plugin.loadLang();

// The errors plugins/dump/action.php answers with name a key in theUILang and
// carry the values to substitute into it, so the user reads them in their own
// language instead of the server's. Anything else -- the dumper's own output,
// and the task layer's own messages -- is shown as it arrived.
plugin.onTaskShowLog = function(task,line,id,ndx,displayLine)
{
	if(($type(line)=="object") && line.reason)
	{
		var text = theUILang[line.reason];
		text = ($type(text)=="string")
			? text.replace('{file}',line.file || '').replace('{user}',line.user || '')
			: line.reason;
		return(escapeHTML(text)+'<br>');
	}
	return(escapeHTML(displayLine === undefined ? line : displayLine)+'<br>');
}

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
			if (el) theContextMenu.add( el, [theUILang.dumpTorrent, () => theWebUI.dumpTorrent()] );
		}
	}
}
