plugin.loadLang();

if(plugin.enabled)
{
        if(plugin.canChangeColumns())
        {
		plugin.config = theWebUI.config;
		theWebUI.config = function(data)
		{
			this.tables.trt.columns.push({text: 'SeedingTime', width: '100px', id: 'seedingtime', type: TYPE_NUMBER});
			this.tables.trt.columns.push({text: 'AddTime', width: '100px', id: 'addtime', type: TYPE_NUMBER});
			plugin.trtFormat = this.tables.trt.format;
			this.tables.trt.format = function(table,arr)
			{
				for(var i in arr)
				{
				        var s = table.getIdByCol(i);
   					if((s=="seedingtime") || (s=="addtime"))
						arr[i] = arr[i] ? theConverter.date(arr[i]) : "";
			        }
				return(plugin.trtFormat(table,arr));
			}
			theRequestManager.addRequest("trt", "d.get_custom=seedingtime",function(hash,torrent,value)
			{
				torrent.seedingtime = value;
			});
			theRequestManager.addRequest("trt", "d.get_custom=addtime",function(hash,torrent,value)
			{
				torrent.addtime = value;
			});
			plugin.config.call(this,data);
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
	}
}
