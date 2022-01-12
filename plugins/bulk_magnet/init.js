plugin.loadMainCSS();
plugin.loadLang();

if(plugin.canChangeMenu())
{
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
		copyToClipboard(result);
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

plugin.showBulkAdd = function()
{
	theDialogManager.show("dlgBulkAdd");
}

plugin.createPluginMenu = function()
{
	if(this.enabled)
	{
		theContextMenu.add([theUILang.bulkAdd, plugin.showBulkAdd]);
	}
}

plugin.bulkAdd = function()
{
	theWebUI.request("?action=bulkadd",[plugin.wasAdded, plugin]);
}

rTorrentStub.prototype.bulkadd = function()
{
	this.content = '';
	var arr = $('#bulkadd').val().split("\n");
	for(var i = 0; i<arr.length; i++)
	{
		var s = arr[i].trim();
		if(s != '')
		{
			this.content = 	this.content+"&torrent="+encodeURIComponent(s);
		}
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/bulk_magnet/action.php";
	this.dataType = "json";
}

plugin.wasAdded = function(data)
{
	if(data['error'])
	{
		noty( theUILang.addTorrentFailed + " ("+data['error']+')', "error" );
	}
	if(data['success'])
	{
		noty( theUILang.addTorrentSuccess + " ("+data['success']+')', "success" );
		theWebUI.getTorrents("list=1");
	}
}

plugin.onLangLoaded = function()
{
	this.registerTopMenu(9);
	theDialogManager.make( "dlgBulkAdd", theUILang.bulkAdd,
		"<div class='container'>"+
			"<textarea id='bulkadd'></textarea>"+
			theUILang.bulkAddDescription+
		"</div>"+
		"<div class='aright buttons-list'>"+
			"<input type='button' class='OK Button' disabled='disabled' value='"+theUILang.ok+"'/>"+
			"<input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/>"+
		"</div>");
	var text = $$('bulkadd');
	theDialogManager.setHandler('dlgBulkAdd','beforeShow',function()
	{
		text.value = '';
	});
	$('#dlgBulkAdd .OK').on('click', function()
	{
		theDialogManager.hide("dlgBulkAdd");
		plugin.bulkAdd();
		return(false);
	});
	text.onupdate = text.onkeyup = function()
	{
		$('#dlgBulkAdd .OK').prop('disabled', text.value.trim()=='');
	};
	text.onpaste = function() { setTimeout( text.onupdate, 10 ) };
};

plugin.onRemove = function()
{
	theDialogManager.hide("dlgBulkAdd");
}
