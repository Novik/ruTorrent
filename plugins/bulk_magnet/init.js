plugin.loadLang();

if(plugin.canChangeMenu())
{
	plugin.copyToClipboard = function(str)
	{
		var el = document.createElement("textarea");
		document.body.appendChild(el); 
		el.style.opacity=0; 
		el.style.height="0px";
		el.value = str;
		el.select();
		document.execCommand("copy");
		document.body.removeChild(el);
	}

	plugin.getTorrentValue = function(k,type)
	{
		var ret = null;
		switch(type)
		{
			case 'hash':
			{
				ret = k;	
				break;	
			}
			case 'name':
			{
				if( theWebUI.torrents[k] )
				{
					ret = theWebUI.torrents[k].name;
				}
				break;	
			}
			case 'magnet':
			{
				if( theWebUI.torrents[k] )
				{
					ret = "magnet:?xt=urn:btih:"+k+
						"&dn="+encodeURIComponent(theWebUI.torrents[k].name);
					if( theWebUI.trackers[k] )
					{
						theWebUI.trackers[k].forEach( function(tracker)
						{
							if(tracker.name != "dht://")
							{
								ret += "&tr="+encodeURIComponent(tracker.name);
							}
						});
					}
				}
				break;	
			}
		}
		return(ret);
	}

	plugin.copyProperty = function(type)
	{
		var sr = theWebUI.getTable("trt").rowSel;
		var result = '';
		for( var k in sr )
		{
			if( sr[k] && (k.length == 40) )
			{
				var value = plugin.getTorrentValue(k,type)
				if(value !== null)
				{
					if( result )
					{
						result += "\n" + value;
					}
					else
					{
						result = value;
					}
				}
			}
		}
		plugin.copyToClipboard(result);
	}

	plugin.copyName = function()
	{
		plugin.copyProperty('name');
	}

	plugin.copyHash = function()
	{
		plugin.copyProperty('hash');
	}

	plugin.copyMagnet = function()
	{
		plugin.copyProperty('magnet');
	}

	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled)
		{
			theContextMenu.add([CMENU_CHILD, theUILang.bulkCopy, 
			[ 
				[theUILang.Name, plugin.copyName],
				[theUILang.Hash, plugin.copyHash],
				[theUILang.Magnet, plugin.copyMagnet]
			]] );
		}
	}
}