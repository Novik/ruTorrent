plugin.loadLang();

if(plugin.canChangeColumns())
{
	plugin.config = theWebUI.config;
	theWebUI.config = function()
	{
		this.tables.trt.columns.push({text: 'Tracker Status', width: '200px', id: 'msg', type: TYPE_STRING, titled: true});
		plugin.config.call(this);
		plugin.trtRenameColumn();
	}

	plugin.trtRenameColumn = function()
	{
		if(plugin.allStuffLoaded)
		{
			theWebUI.getTable("trt").renameColumnById("msg",theUILang.Tracker_status);
			if(thePlugins.isInstalled("rss"))
				plugin.rssRenameColumn();
			if(thePlugins.isInstalled("extsearch"))
				plugin.tegRenameColumn();
		}
		else
			setTimeout(arguments.callee,1000);
	}

	plugin.rssRenameColumn = function()
	{
		if(theWebUI.getTable("rss").created)
			theWebUI.getTable("rss").renameColumnById("msg",theUILang.Tracker_status);
		else
			setTimeout(arguments.callee,1000);
	}

	plugin.tegRenameColumn = function()
	{
		if(theWebUI.getTable("teg").created)
			theWebUI.getTable("teg").renameColumnById("msg",theUILang.Tracker_status);
		else
			setTimeout(arguments.callee,1000);
	}
}

plugin.onRemove = function()
{
	theWebUI.getTable("trt").removeColumnById("msg");
	if(thePlugins.isInstalled("rss"))
		theWebUI.getTable("rss").removeColumnById("msg");
	if(thePlugins.isInstalled("extsearch"))
		theWebUI.getTable("teg").removeColumnById("msg");
}
