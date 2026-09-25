plugin.loadLang();

// Highlight the torrents whose "Finished" duration (the seedingtime column)
// has reached a limit set in the settings, e.g. "1d" or "2w". The limit is a
// webui.* setting, so the core saves and restores it per user.
plugin.highlightAttr = "data-seedingtime-highlight";
plugin.highlightSetting = "webui.seedingtime.highlight";
plugin.highlightUnits = { s: 1, m: 60, h: 3600, d: 86400, w: 604800 };

// "30m", "12h", "1d", "2w" -> seconds. A bare number counts days, and
// anything unparsable disables the highlight (0).
plugin.parseDuration = function(value)
{
	const match = /^\s*(\d+)\s*([smhdw]?)\s*$/i.exec(value || "");
	return match ? parseInt(match[1], 10) * plugin.highlightUnits[(match[2] || "d").toLowerCase()] : 0;
};

plugin.highlightLimit = function()
{
	return plugin.parseDuration(theWebUI.settings[plugin.highlightSetting]);
};

// A row attribute, never the class attribute: the table sets the former with
// setAttribute, which would drop the "selected" class of a selected row. The
// value stays defined so that the attribute is also reset when the highlight
// no longer applies.
plugin.torrentRowAttr = function(torrent)
{
	const limit = plugin.highlightLimit();
	const seedingtime = torrent ? iv(torrent.seedingtime) : 0;
	return { [plugin.highlightAttr]: (limit && (seedingtime >= limit)) ? "1" : "0" };
};

// The core only saves a webui.* setting when its element exists, so the key
// has to be in the read-back model before the settings are collected.
if($type(theWebUI.settings[plugin.highlightSetting]) != "string")
	theWebUI.settings[plugin.highlightSetting] = "";

if(plugin.canChangeColumns())
{
	plugin.loadMainCSS();

	plugin.applyHighlight = function()
	{
		const table = theWebUI.getTable("trt");
		if(table && theWebUI.torrents)
			for(const hash in theWebUI.torrents)
				table.setAttr(hash, plugin.torrentRowAttr(theWebUI.torrents[hash]));
	};

	// setRowById is where the torrent list hands its rows to the table, so it
	// is the one place that covers freshly added and already drawn rows alike.
	plugin.setRowById = dxSTable.prototype.setRowById;
	plugin.wrappedSetRowById = function(ids, sId, icon, attr)
	{
		if(this.prefix === "trt")
			attr = $.extend({}, attr, plugin.torrentRowAttr(ids));
		return plugin.setRowById.call(this, ids, sId, icon, attr);
	};
	dxSTable.prototype.setRowById = plugin.wrappedSetRowById;

	plugin.config = theWebUI.config;
	theWebUI.config = function()
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
					arr[i] = arr[i] != -1 ? theConverter.time(arr[i],true) : "";
				else
				if(s=="addtime")
					arr[i] = arr[i] != -1 ? theConverter.date(arr[i]) : "";
		        }
			return(plugin.trtFormat(table,arr));
		}
		plugin.config.call(this);
		plugin.reqId1 = theRequestManager.addRequest("trt", theRequestManager.map("d.get_custom=")+"seedingtime",function(hash,torrent,value)
		{
			const epochSeconds = iv(value);
			torrent.seedingtime = (epochSeconds > 3600*24*365) ? Math.max(0, new Date().getTime()/1000-(epochSeconds+theWebUI.deltaTime/1000)) : -1;
		});
		plugin.reqId2 = theRequestManager.addRequest("trt", theRequestManager.map("d.get_custom=")+"addtime",function(hash,torrent,value)
		{
			const epochSeconds = iv(value);
			torrent.addtime = (epochSeconds > 3600*24*365) ? epochSeconds : -1;
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

// The field lives on the General settings page next to the other UI options.
// Its id is the setting key, so the core collects and restores the value like
// any other webui.* input.
plugin.onLangLoaded = function()
{
	if(plugin.canChangeOptions() && plugin.canChangeColumns())
	{
		const id = plugin.highlightSetting;
		const row = $("#st_gl").find(".row").first();
		if(row.length && !$$(id))
		{
			row.append(
				$("<div>").addClass("col-md-6").append(
					$("<label>").attr({for: id}).text(theUILang.seedingTimeHighlight),
				),
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type:"text", id:id, maxlength:12, placeholder:"1d"})
						.addClass("TextboxNormal").val(theWebUI.settings[id]),
				),
			);
			theDialogManager.setHandler('stg','beforeShow',function()
			{
				$($$(id)).val(theWebUI.settings[id]);
			});
		}
		if(!plugin.setSettings)
		{
			plugin.setSettings = theWebUI.setSettings;
			theWebUI.setSettings = function()
			{
				plugin.setSettings.call(this);
				plugin.applyHighlight();
			};
		}
	}
};

plugin.onRemove = function()
{
	if(plugin.wrappedSetRowById && (dxSTable.prototype.setRowById === plugin.wrappedSetRowById))
		dxSTable.prototype.setRowById = plugin.setRowById;
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
