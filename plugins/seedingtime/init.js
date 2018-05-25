plugin.loadLang();

if(plugin.canChangeColumns())
{
	plugin.config = theWebUI.config;
	theWebUI.config = function(data)
	{
		this.tables.trt.columns.push({text: 'SeedingTime', width: '100px', id: 'seedingtime', type: TYPE_NUMBER});
		this.tables.trt.columns.push({text: 'AddTime', width: '110px', id: 'addtime', type: TYPE_NUMBER});
		plugin.trtFormat = this.tables.trt.format;
		this.tables.trt.format = function(table,arr)
		{
			for(var i in arr)
			{
			        var s = table.getIdByCol(i);
				if(s=="seedingtime")
					arr[i] = (arr[i]>3600*24*365) ? theConverter.time(new Date().getTime()/1000-(iv(arr[i])+theWebUI.deltaTime/1000),true) : "";
				else
				if(s=="addtime")
					arr[i] = (arr[i]>3600*24*365) ? theConverter.date(iv(arr[i])+theWebUI.deltaTime/1000) : "";
		        }
			return(plugin.trtFormat(table,arr));
		}
		plugin.config.call(this,data);
		plugin.reqId1 = theRequestManager.addRequest("trt", theRequestManager.map("d.get_custom=")+"seedingtime",function(hash,torrent,value)
		{
			torrent.seedingtime = value;
		});
		plugin.reqId2 = theRequestManager.addRequest("trt", theRequestManager.map("d.get_custom=")+"addtime",function(hash,torrent,value)
		{
			torrent.addtime = value;
		});
		plugin.trtRenameColumn();
	}

	plugin.trtRenameColumn = function()
	{
		if(plugin.allStuffLoaded)
		{
			theWebUI.getTable("trt").renameColumnById("seedingtime",theUILang.seedingTime);
			theWebUI.getTable("trt").renameColumnById("addtime",theUILang.addTime);
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
		{
			theWebUI.getTable("rss").renameColumnById("seedingtime",theUILang.seedingTime);
			theWebUI.getTable("rss").renameColumnById("addtime",theUILang.addTime);
		}
		else
			setTimeout(arguments.callee,1000);
	}

	plugin.tegRenameColumn = function()
	{
		if(theWebUI.getTable("teg").created)
		{
			theWebUI.getTable("teg").renameColumnById("seedingtime",theUILang.seedingTime);
			theWebUI.getTable("teg").renameColumnById("addtime",theUILang.addTime);
		}
		else
			setTimeout(arguments.callee,1000);
	}
}

plugin.onRemove = function()
{
	theWebUI.getTable("trt").removeColumnById("seedingtime");
	theWebUI.getTable("trt").removeColumnById("addtime");
	if(thePlugins.isInstalled("rss"))
	{
		theWebUI.getTable("rss").removeColumnById("seedingtime");
		theWebUI.getTable("rss").removeColumnById("addtime");
	}
	theRequestManager.removeRequest( "trt", plugin.reqId1 );
	theRequestManager.removeRequest( "trt", plugin.reqId2 );
}
