plugin.loadLang();
plugin.loadMainCSS();
plugin.mark = 0;
plugin.hstTimeout = null;

plugin.actionNames = ['', '', '', ''];

plugin.showNotification = function(item)
{
	if(plugin.allStuffLoaded)
	{
		var notification = notify.createNotification( plugin.actionNames[item.action], { body: item.name, icon: { x16: 'images/favicon.ico', x32: 'images/favicon-32x32.png' } } );
		setTimeout(function ()
		{
               		notification.close();
                }, theWebUI.history.closeinterval*1000);
	}
}

plugin.isNotificationsSupported = function()
{
	return( (plugin.allStuffLoaded && !notify.isSupported) ? false : notify.permissionLevel() );
}

plugin.rebuildNotificationsPage = function() {
	if (plugin.allStuffLoaded) {
		const state = plugin.isNotificationsSupported();
		$('#notifTip').text(theUILang.notifTip[state]);
		switch (state) {
			case notify.PERMISSION_DENIED:
			case false: {
				$('#notifPerms, #notifParam').hide();
				break;
			}
			case notify.PERMISSION_GRANTED: {
				$('#notifPerms').hide();
				break;
			}
		}
	}
}

if(plugin.canChangeOptions())
{
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function( arg )
	{
        	if(plugin.enabled)
	        {
			$$('history_addition').checked = ( theWebUI.history.addition != 0 );
			$$('history_finish').checked = ( theWebUI.history.finish != 0 );
			$$('history_deletion').checked = ( theWebUI.history.deletion != 0 );
			$$('not_autoclose').checked = ( theWebUI.history.autoclose != 0 );
			$('#not_closeinterval').val( theWebUI.history.closeinterval );
			$('#history_limit').val( theWebUI.history.limit );
			$('#pushbullet_key').val( theWebUI.history.pushbullet_key );
			$$('pushbullet_enabled').checked = ( theWebUI.history.pushbullet_enabled != 0 );
			$$('pushbullet_addition').checked = ( theWebUI.history.pushbullet_addition != 0 );
			$$('pushbullet_finish').checked = ( theWebUI.history.pushbullet_finish != 0 );
			$$('pushbullet_deletion').checked = ( theWebUI.history.pushbullet_deletion != 0 );

			$('#not_autoclose').trigger('change');
			$('#pushbullet_enabled').trigger('change');

			plugin.rebuildNotificationsPage();
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.historyWasChanged = function()
	{
		return(	($$('history_addition').checked != ( theWebUI.history.addition != 0 )) ||
			($$('history_finish').checked != ( theWebUI.history.finish != 0 )) ||
			($$('history_deletion').checked != ( theWebUI.history.deletion != 0 )) ||
			($$('not_autoclose').checked != ( theWebUI.history.autoclose != 0 )) ||
			($('#not_closeinterval').val() != theWebUI.history.closeinterval) ||
			($('#history_limit').val() != theWebUI.history.limit) ||
			($$('pushbullet_enabled').checked != ( theWebUI.history.pushbullet_enabled != 0 )) ||
			($$('pushbullet_addition').checked != ( theWebUI.history.pushbullet_addition != 0 )) ||
			($$('pushbullet_finish').checked != ( theWebUI.history.pushbullet_finish != 0 )) ||
			($$('pushbullet_deletion').checked != ( theWebUI.history.pushbullet_deletion != 0 )) ||
			($('#pushbullet_key').val() != theWebUI.history.pushbullet_key));
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function()
	{
		plugin.setSettings.call(this);
		if( plugin.enabled && this.historyWasChanged() )
			this.request( "?action=sethistory" );
	}

	rTorrentStub.prototype.sethistory = function()
	{
		this.content = "cmd=set&addition=" + ( $$('history_addition').checked ? '1' : '0' ) +
			"&deletion=" + ( $$('history_deletion').checked  ? '1' : '0' ) +
			"&finish=" + ( $$('history_finish').checked  ? '1' : '0' ) +
			"&closeinterval=" + $('#not_closeinterval').val() +
			"&autoclose=" + ( $$('not_autoclose').checked  ? '1' : '0' ) +
			"&limit=" + $('#history_limit').val() +
			"&pushbullet_addition=" + ( $$('pushbullet_addition').checked ? '1' : '0' ) +
			"&pushbullet_deletion=" + ( $$('pushbullet_deletion').checked  ? '1' : '0' ) +
			"&pushbullet_finish=" + ( $$('pushbullet_finish').checked  ? '1' : '0' ) +
			"&pushbullet_enabled=" + ( $$('pushbullet_enabled').checked  ? '1' : '0' ) +
			"&pushbullet_key=" + $('#pushbullet_key').val();

		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/history/action.php";
		this.dataType = "script";
	}
}

if(plugin.canChangeTabs() || plugin.canChangeColumns())
{
	plugin.config = theWebUI.config;
	theWebUI.config = function()
	{
		if(plugin.canChangeTabs())
		{
	        	plugin.attachPageToTabs($('<div>').attr("id","history").addClass("table_tab stable").get(0),"History","lcont");
			theWebUI.tables["hst"] =
			{
	        		obj:		new dxSTable(),
				container:	"history",
				columns:
				[
					{ text: theUILang.Name, 		width: "200px", id: "name",		type: TYPE_STRING },
		      			{ text: theUILang.Status, 		width: "100px",	id: "status",		type: TYPE_STRING },
					{ text: 'Time',	 			width: "110px", id: "time",		type: TYPE_NUMBER },
			   		{ text: theUILang.Size, 		width: "70px",	id: "size", 		type: TYPE_NUMBER },
					{ text: theUILang.Downloaded, 		width: "100px",	id: "downloaded",	type: TYPE_NUMBER },
					{ text: theUILang.Uploaded, 		width: "100px",	id: "uploaded",		type: TYPE_NUMBER },
					{ text: theUILang.Ratio, 		width: "60px",	id: "ratio",		type: TYPE_NUMBER },
					{ text: theUILang.Label, 		width: "60px", 	id: "label",		type: TYPE_STRING },
					{ text: theUILang.Created_on,		width: "110px", id: "created",		type: TYPE_NUMBER },
					{ text: 'SeedingTime', 			width: '110px', id: 'seedingtime', 	type: TYPE_NUMBER },
					{ text: 'AddTime', 			width: '110px', id: 'addtime', 		type: TYPE_NUMBER },
					{ text: 'Tracker', 			width: '100px', id: 'tracker', 		type: TYPE_STRING }
				],
				format:	function(table,arr)
				{
					for(var i in arr)
					{
						if(arr[i]==null)
							arr[i] = '';
						else
							switch(table.getIdByCol(i))
							{
								case "seedingtime" :
								case "time":
								case "addtime":
								case 'created' :
									arr[i] = arr[i] ? theConverter.date(iv(arr[i])+theWebUI.deltaTime/1000) : '';
									break;
								case 'downloaded' :
								case 'uploaded' :
								case 'size' :
	      								arr[i] = theConverter.bytes(arr[i], 'table');
									break;
								case 'ratio' :
									arr[i] = (arr[i] ==- 1) ? "\u221e" : theConverter.round(arr[i] / 1000, 3);
									break;
								case 'status' :
									arr[i] = plugin.actionNames[arr[i]];
									break;
      							}
					}
					return(arr);
				},
				ondelete:	function() { this.historyRemove(); },
	       	        	onselect:	function(e,id) { this.historySelect(e,id) }
			};
		}

		if(plugin.canChangeColumns())
		{
			this.tables.trt.columns.push({text: 'PushBullet', width: '80px', id: 'pushbullet', type: TYPE_NUMBER});
			plugin.trtFormat = this.tables.trt.format;
			this.tables.trt.format = function(table,arr)
			{
				for(var i in arr)
				{
				        var s = table.getIdByCol(i);
					if(s=="pushbullet")
					{
						switch(iv(arr[i]))
						{
							case 1:
								arr[i] = theUILang.no;
								break;
							case 0:
								arr[i] = theUILang.yes;
								break;
						}
					}
		        	}
				return(plugin.trtFormat(table,arr));
			}
		}

		plugin.config.call(this);

		if(plugin.canChangeColumns())
		{
			plugin.reqId1 = theRequestManager.addRequest("trt", theRequestManager.map("d.get_custom=")+"x-pushbullet",function(hash,torrent,value)
			{
				torrent.pushbullet = value;
			});
		}

		if(plugin.canChangeTabs())
			plugin.renameHistoryStuff();
	}

	plugin.renameHistoryStuff = function()
	{
		if(plugin.allStuffLoaded)
		{
			plugin.renameTab("history",theUILang.history);
			theWebUI.getTable("hst").renameColumnById("seedingtime",theUILang.seedingTime);
			theWebUI.getTable("hst").renameColumnById("addtime",theUILang.addTime);
			theWebUI.getTable("hst").renameColumnById("time",theUILang.Time);
			theWebUI.getTable("hst").renameColumnById("tracker",theUILang.Tracker);
			plugin.historyRefresh();
		}
		else
			setTimeout(arguments.callee,1000);
	}

	plugin.historyRefresh = function()
	{
		theWebUI.requestWithoutTimeout("?action=gethistory",[plugin.onGetHistory, plugin]);
	}

	rTorrentStub.prototype.gethistory = function()
	{
		this.content = "cmd=get&mark=" + plugin.mark;
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/history/action.php";
		this.dataType = "json";
	}

	dxSTable.prototype.historyRemove = function()
	{
		if(theWebUI.settings["webui.confirm_when_deleting"])
			askYesNo( theUILang.hstDelete, theUILang.hstDeletePrompt, "theWebUI.getTable('"+this.prefix+"').cmdHistory('delete')" );
		else
			this.cmdHistory('delete');
	}

	dxSTable.prototype.cmdHistory = function(cmd)
	{
		var req = '';
		for( var k in this.rowSel )
		{
			if( this.rowSel[k] )
				req+=("&hash=" + k);
		}
		if(req.length)
			theWebUI.request("?action=hst"+cmd+req,[plugin.onGetHistory, plugin]);
	}

	rTorrentStub.prototype.hstdelete = function()
	{
		this.content = "cmd=delete";
		for(var i=0; i<this.hashes.length; i++)
			this.content += ('&hash='+this.hashes[i]);
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/history/action.php";
		this.dataType = "json";
	}

	if(!$type(theWebUI.getTrackerName))
	{
		theWebUI.getTrackerName = function(announce)
		{
			var domain = '';
			if(announce)
			{
				var parts = announce.match(/^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/);
				if(parts && (parts.length>6))
				{
					domain = parts[6];
					if(!domain.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/))
					{
						parts = domain.split(".");
						if(parts.length>2)
						{
							if($.inArray(parts[parts.length-2]+"", ["co", "com", "net", "org"])>=0 ||
								$.inArray(parts[parts.length-1]+"", ["uk"])>=0)
								parts = parts.slice(parts.length-3);
							else
								parts = parts.slice(parts.length-2);
							domain = parts.join(".");
						}
					}
				}
			}
			return(domain);
		}
	}

	plugin.onGetHistory = function(d)
	{
		var updated = false;
		var table = theWebUI.getTable("hst");
		if($type(d))
		{
			if(d.mode)
				table.clearRows();
			for( var id in d.items )
			{
				var item = d.items[id];
				table.addRowById(
				{
					time: item.action_time,
					downloaded: item.downloaded,
					uploaded: item.uploaded,
					ratio: item.ratio,
					label: item.label,
					created: item.creation,
					seedingtime: item.finished,
					addtime: item.added,
					name: item.name,
					status: item.action,
					size: item.size,
					tracker: theWebUI.getTrackerName(item.tracker)
				}, item.hash, (item.action==1) ? "Status_Down" : (item.action==2) ? "Status_Completed" : "Status_Error" );
				updated = true;
				if( item.action_time > plugin.mark )
					plugin.mark = item.action_time;
				if(!d.mode && plugin.allStuffLoaded && (plugin.isNotificationsSupported()===notify.PERMISSION_GRANTED))
				{
	                        	plugin.showNotification(item);
				}
			}
		}
		if(updated)
		{
			table.refreshRows();
			if(table.sortId)
				table.Sort();
		}
		if((theWebUI.activeView=='history') || (plugin.allStuffLoaded && (plugin.isNotificationsSupported()===notify.PERMISSION_GRANTED)))
		{
			plugin.hstTimeout = window.setTimeout(plugin.historyRefresh,theWebUI.settings["webui.update_interval"]);
		}
		else
        		if(plugin.hstTimeout)
	        	{
        			window.clearTimeout(plugin.hstTimeout);
	        		plugin.hstTimeout = null;
	        	}
	}

	plugin.onShow = theTabs.onShow;
	theTabs.onShow = function(id)
	{
		if(id=="history")
		{
			var table = theWebUI.getTable("hst");
			if(table)
			{
				table.refreshRows();
				if(!plugin.hstTimeout)
					plugin.historyRefresh();
			}
		}
		else
			plugin.onShow.call(this,id);
	}

	if(plugin.canChangeMenu())
	{
		dxSTable.prototype.historySelect = function(e,id)
		{
			if(plugin.enabled && plugin.allStuffLoaded && (e.which==3))
			{
				var self = "theWebUI.getTable('"+this.prefix+"').";
				theContextMenu.clear();
				theContextMenu.add([theUILang.Remove, self+"cmdHistory('delete')"]);
				theContextMenu.show(e.clientX,e.clientY);
			}
		}
	}
}

if(plugin.canChangeMenu())
{
	rTorrentStub.prototype.setpushbullet = function()
	{
		for( var i = 0; i < this.hashes.length; i++ )
		{
			var cmd = new rXMLRPCCommand( "d.set_custom" );
			cmd.addParameter( "string", this.hashes[i] );
			cmd.addParameter( "string", 'x-pushbullet' );
			cmd.addParameter( "string", this.vs[i] );
			this.commands.push( cmd );
		}
	}

	theWebUI.setPushbullet = function(val)
	{
		theWebUI.perform('setpushbullet&v='+val);
	}

	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled && plugin.allStuffLoaded && theWebUI.history.pushbullet_enabled)
		{
			var table = this.getTable("trt");
			var el = theContextMenu.get(theUILang.peerAdd);
			if( el )
			{
				if(table.selCount==1)
				{
					theContextMenu.add(el,[CMENU_CHILD, 'Pushbullet',
					[
						[ theUILang.turnNotifyOn, theWebUI.torrents[id].pushbullet ? "theWebUI.setPushbullet('')" : null ],
					 	[ theUILang.turnNotifyOff, theWebUI.torrents[id].pushbullet ? null : "theWebUI.setPushbullet('1')" ]
					]]);
				}
				else
				{
					theContextMenu.add(el,[CMENU_CHILD, 'Pushbullet',
					[
						[ theUILang.turnNotifyOn, "theWebUI.setPushbullet('1')" ],
					 	[ theUILang.turnNotifyOff, "theWebUI.setPushbullet('')" ]
					]]);
				}
                        }
		}
	}
}

plugin.onLangLoaded = function() {
	injectScript(plugin.path+"desktop-notify.js", function() {
		plugin.attachPageToOptions(
			$("<div>").attr({id:"st_history"}).append(
				$("<fieldset>").append(
					$("<legend>").text(theUILang.historyLog),
					$("<div>").addClass("row").append(
						$("<div>").addClass("col-12 col-md-6").append(
							$("<label>").attr({for:"history_limit"}).text(theUILang.historyLimit),
						),
						$("<div>").addClass("col-12 col-md-3").append(
							$("<input>").attr({type:"text", id:"history_limit", maxlength:4}),
						),
						...[
							["history_addition", theUILang.historyAddition],
							["history_deletion", theUILang.historyDeletion],
							["history_finish", theUILang.historyFinish],
						].map(([id, text]) => $("<div>").addClass("col-12 col-md-4").append(
							$("<input>").attr({type:"checkbox", id:id}),
							$("<label>").attr({for:id}).text(text),
						)),
					),
				),
				$("<fieldset>").append(
					$("<legend>").text(theUILang.historyNotification),
					$("<div>").addClass("row").append(
						$("<div>").addClass("col-12").append(
							$("<div>").attr({id:"notifTip"}),
						),
						$("<div>").addClass("col-12").append(
							$("<button>").attr({type:"button", id:"notifPerms"}).text(theUILang.enableNotifications),
						),
					),
					$("<div>").attr({id:"notifParam"}).addClass("row").append(
						$("<div>").addClass("col-12 col-md-6").append(
							$("<input>")
								.attr({type:"checkbox", id:"not_autoclose"})
								.on("change", (ev) => linked(ev.target, 0, ["not_closeinterval"])),
							$("<label>")
								.attr({for:"not_closeinterval", id:"lbl_not_closeinterval"})
								.text(theUILang.notifAutoClose),
						),
						$("<div>").addClass("col-6 col-md-3").append(
							$("<input>").attr({type:"text", id:"not_closeinterval", maxlength:3}).prop("disabled", true),
						),
						$("<div>").addClass("col-6 col-md-3").append(
							$("<span>").text(theUILang.s),
						),
					),
				),
				$("<fieldset>").append(
					$("<legend>").append(
						$("<a>").attr({href:"https://www.pushbullet.com/", target:"_blank"}).text(theUILang.pushbulletNotification),
					),
					$("<div>").addClass("row").append(
						$("<div>").addClass("col-12").append(
							$("<input>")
								.attr({type:"checkbox", id:"pushbullet_enabled", onchange:";"})
								.on("change", (ev) => linked(
									ev.target, 0,
									['pushbullet_key','pushbullet_addition','pushbullet_deletion','pushbullet_finish'],
								)),
							$("<label>").attr({for:"pushbullet_enabled"}).text(theUILang.Enabled),
						),
						$("<div>").addClass("col-12 col-md-6").append(
							$("<label>")
								.attr({for:"pushbullet_key", id:"lbl_pushbullet_key"})
								.text(theUILang.pushbulletKey),
						),
						$("<div>").addClass("col-12 col-md-6").append(
							$("<input>").attr({type:"text", id:"pushbullet_key"}).prop("disabled", true),
						),
						...[
							["pushbullet_addition", theUILang.historyAddition],
							["pushbullet_deletion", theUILang.historyDeletion],
							["pushbullet_finish", theUILang.historyFinish],
						].map(([id, text]) => $("<div>").addClass("col-12 col-md-4").append(
							$("<input>").attr({type:"checkbox", id:id}).prop("disabled", true),
							$("<label>").attr({for:id, id:`lbl_${id}`}).text(text),
						)),
					),
				),
			)[0],
			theUILang.history,
		);
		$('#notifPerms').on('click', () => notify.requestPermission(() => {
				plugin.rebuildNotificationsPage();
				plugin.historyRefresh();
			})
		);
		plugin.actionNames = ['', theUILang.Added, theUILang.Finished, theUILang.Deleted];
		plugin.markLoaded();
	});
}

plugin.onRemove = function()
{
	plugin.removePageFromOptions("st_history");
	theWebUI.getTable("trt").removeColumnById("pushbullet");
	theRequestManager.removeRequest( "trt", plugin.reqId1 );
}

plugin.langLoaded = function()
{
	if(plugin.enabled)
		plugin.onLangLoaded();
}
