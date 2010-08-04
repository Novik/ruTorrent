plugin.loadLang();

if(plugin.enabled)
{
	plugin.loadMainCSS();

	plugin.config = theWebUI.config;
	theWebUI.config = function(data)
	{
		if(plugin.canChangeColumns())
		{
			theWebUI.tables.trt.columns.push({ text: 'RatioGroup', width: '60px', id: 'ratiogroup', type: TYPE_STRING});
			plugin.trtFormat = this.tables.trt.format;
			theWebUI.tables.trt.format = function(table,arr)
			{
				for(var i in arr)
				{
   					if((table.getIdByCol(i)=="ratiogroup") && arr[i])
   					{
	   					var rat = arr[i].match(/rat_(\d{1,2})/);
						arr[i] = ( rat && (rat.length>1) && theWebUI.isCorrectRatio(rat[1]) ? theWebUI.ratios[rat[1]].name : "" );
						break;
					}
	        		}
				return(plugin.trtFormat(table,arr));
			};
		}
		plugin.config.call(this,data);
		plugin.reqId = theRequestManager.addRequest("trt", theRequestManager.map("cat=")+'$'+theRequestManager.map("d.views="),function(hash,torrent,value)
		{
			torrent.ratiogroup = value;
		});
		if(plugin.canChangeColumns())
			plugin.trtRenameColumn();
	}

	if(plugin.canChangeColumns())
	{
		plugin.trtRenameColumn = function()
		{
			if(plugin.allStuffLoaded)
			{
				theWebUI.getTable("trt").renameColumnById("ratiogroup",theUILang.ratio);
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
				theWebUI.getTable("rss").renameColumnById("ratiogroup",theUILang.ratio);
			else
				setTimeout(arguments.callee,1000);
		}

		plugin.tegRenameColumn = function()
		{
			if(theWebUI.getTable("teg").created)
				theWebUI.getTable("teg").renameColumnById("ratiogroup",theUILang.ratio);
			else
				setTimeout(arguments.callee,1000);
		}
	}
	if(plugin.canChangeOptions())
	{
		plugin.addAndShowSettings = theWebUI.addAndShowSettings;
		theWebUI.addAndShowSettings = function(arg) 
		{
			if(plugin.enabled)
			{
			        for(var i=0; i<theWebUI.maxRatio; i++)
				{
					$('#rat_min'+i).val( theWebUI.ratios[i].min );
				        $('#rat_max'+i).val( theWebUI.ratios[i].max );
			        	$('#rat_action'+i).val( theWebUI.ratios[i].action );
					$('#rat_upload'+i).val( theWebUI.ratios[i].upload );
					$('#rat_name'+i).val( theWebUI.ratios[i].name );
				}
			}
			plugin.addAndShowSettings.call(theWebUI,arg);
		}

		theWebUI.ratioWasChanged = function() 
		{
			for(var i=0; i<theWebUI.maxRatio; i++)
			{
				if( 	(theWebUI.ratios[i].min != $('#rat_min'+i).val()) ||
					(theWebUI.ratios[i].max != $('#rat_max'+i).val()) ||
					(theWebUI.ratios[i].action != $('#rat_action'+i).val()) ||
					($('#rat_upload'+i).val() != theWebUI.ratios[i].upload) ||
					($('#rat_name'+i).val() != theWebUI.ratios[i].name))
					return(true);
			}
			return(false);
		}

		plugin.setSettings = theWebUI.setSettings;
		theWebUI.setSettings = function() 
		{
			plugin.setSettings.call(this);
			if(plugin.enabled && this.ratioWasChanged())
				this.request("?action=setratioprm");
		}

		rTorrentStub.prototype.setratioprm = function()
		{
			this.content = "dummy=1";
			for(var i=0; i<theWebUI.maxRatio; i++)
			{
				var name = $.trim($('#rat_name'+i).val());
				var upload = iv($('#rat_upload'+i).val());
				var min = $('#rat_min'+i).val();
				var max = $('#rat_max'+i).val();
				var action = $('#rat_action'+i).val();
        	        	if(name.length)
					this.content += ('&rat_upload'+i+'='+upload+'&rat_action'+i+'='+action+'&rat_min'+i+'='+min+'&rat_max'+i+'='+max+'&rat_name'+i+'='+encodeURIComponent(name));
			}
        		this.contentType = "application/x-www-form-urlencoded";
			this.mountPoint = "plugins/ratio/action.php";
			this.dataType = "script";
		}
	}
	if(plugin.canChangeMenu())
	{
		theWebUI.getRatioData = function(id)
		{
			var curNo = -1;
			var s = this.torrents[id].ratiogroup;
			var arr = s.match(/rat_(\d{1,2})/);
			if(arr && (arr.length>1))
				curNo = arr[1];
			return(curNo);
		}

		plugin.createMenu = theWebUI.createMenu;
		theWebUI.createMenu = function(e, id)
		{
			plugin.createMenu.call(this, e, id);
			if(plugin.enabled && plugin.allStuffLoaded)
			{
				var el = theContextMenu.get(theUILang.Priority);
				var curNo = null;
				if(this.getTable("trt").selCount==1)
					curNo = theWebUI.getRatioData(id);
				var down = [];
				down.push([theUILang.mnuRatioUnlimited,(curNo==-1) ? null : "theWebUI.setRatio('-1')"]);
				down.push([CMENU_SEP]);
				for(var i=0; i<theWebUI.maxRatio; i++)
					if(theWebUI.isCorrectRatio(i))
						down.push([theWebUI.ratios[i].name,(i!=curNo) ? "theWebUI.setRatio('"+i+"')" : null]);
				theContextMenu.add(el,[CMENU_CHILD, theUILang.mnuRatio, down]);
			}
		}

		theWebUI.setRatio = function(ratio)
		{
			var sr = this.getTable("trt").rowSel;
			var req = '';
			for(var k in sr)
		       		if(sr[k])
					req += ("&hash=" + k + "&v="+ratio);
			if(req.length>0)
				this.request("?action=setratio"+req+"&list=1",[this.addTorrents, this]);
		}

		rTorrentStub.prototype.setratio = function()
		{
			for(var i=0; i<this.vs.length; i++)
			{
				var wasNo = theWebUI.getRatioData(this.hashes[i]);
				if(wasNo!=this.vs[i])
				{
					if(wasNo>=0)
					{
						cmd = new rXMLRPCCommand('view.set_not_visible');
						cmd.addParameter("string",this.hashes[i]);
						cmd.addParameter("string","rat_"+wasNo);
						this.commands.push( cmd );
						cmd = new rXMLRPCCommand('d.views.remove');
						cmd.addParameter("string",this.hashes[i]);
						cmd.addParameter("string","rat_"+wasNo);
						this.commands.push( cmd );
					}
					if(this.vs[i]>=0)
					{
						cmd = new rXMLRPCCommand('d.views.push_back_unique');
						cmd.addParameter("string",this.hashes[i]);
						cmd.addParameter("string","rat_"+this.vs[i]);
						this.commands.push( cmd );
						cmd = new rXMLRPCCommand('view.set_visible');
						cmd.addParameter("string",this.hashes[i]);
						cmd.addParameter("string","rat_"+this.vs[i]);
						this.commands.push( cmd );
					}
				}
			}
		}
	}

	theWebUI.isCorrectRatio = function(i)
	{
		return( ((i>=0) && (i<theWebUI.ratios.length)) &&
	        	(theWebUI.ratios[i].name!=""));
	}
}

plugin.onLangLoaded = function() 
{
	if(this.enabled)
	{
		var s = 
			"<fieldset>"+
				"<legend>"+theUILang.ratios+"</legend>"+
				"<div id='st_ratio_h'>"+
				"<table>"+
					"<tr>"+
						"<td><b>"+theUILang.ratioName+"</b></td>"+
						"<td><b>"+theUILang.minRatio+" (%)</b></td>"+
						"<td><b>"+theUILang.maxRatio+" (%)</b></td>"+
						"<td><b>"+theUILang.ratioUpload+" ("+theUILang.MB+")</b></td>"+
						"<td><b>"+theUILang.ratioAction+"</b></td>"+
					"</tr>";
		for(var i=0; i<theWebUI.maxRatio; i++)
			s +=
				"<tr>"+
					"<td><input type='text' id='rat_name"+i+"' class='TextboxShort'/></td>"+
					"<td><input type='text' id='rat_min"+i+"' class='Textbox num'/></td>"+
					"<td><input type='text' id='rat_max"+i+"' class='Textbox num'/></td>"+
					"<td><input type='text' id='rat_upload"+i+"' class='Textbox num' maxlength='6'/></td>"+
					"<td><select id='rat_action"+i+"'><option value='0'>"+theUILang.ratioStop+"</option><option value='1'>"+theUILang.ratioStopAndRemove+"</option><option value='2'>"+theUILang.ratioErase+"</option><option value='3'>"+theUILang.ratioEraseData+"</option></select></td>"+
				"</tr>";
		s+="</table></div></fieldset>";
		this.attachPageToOptions($("<div>").attr("id","st_ratio").html(s).get(0),theUILang.ratios);
	}
	else
		log(theUILang.ratioUnsupported);
}

plugin.onRemove = function()
{
	this.removePageFromOptions("st_ratio");
	theWebUI.getTable("trt").removeColumnById("ratiogroup");
	if(thePlugins.isInstalled("rss"))
		theWebUI.getTable("rss").removeColumnById("ratiogroup");
	theRequestManager.removeRequest( "trt", plugin.reqId );
}