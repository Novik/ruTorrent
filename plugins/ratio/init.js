plugin.loadLang();

plugin.loadMainCSS();

plugin.allDone = function()
{
	if(!thePlugins.isInstalled("seedingtime"))
	{
		$('.ratio_time').remove();
	}
	if(thePlugins.isInstalled("throttle"))
	{
		for(var i=0; i<theWebUI.maxThrottle; i++)
			if(theWebUI.isCorrectThrottle(i))
			{
				for(var j=0; j<theWebUI.maxRatio; j++)
					$('#rat_action'+j).append("<option value='"+(i+10)+"'>"+theUILang.setThrottleTo+" "+theWebUI.throttles[i].name+"</option>");
			}
	}
}

plugin.config = theWebUI.config;
theWebUI.config = function()
{
	if(plugin.canChangeColumns())
	{
		theWebUI.tables.trt.columns.push({ text: 'RatioGroup', width: '80px', id: 'ratiogroup', type: TYPE_STRING});
		plugin.trtFormat = this.tables.trt.format;
		theWebUI.tables.trt.format = function(table,arr)
		{
			for(var i in arr)
			{
				if((table.getIdByCol(i)=="ratiogroup") && arr[i])
				{
   					var rat = arr[i].match(/rat_(\d+)/);
					arr[i] = ( rat && (rat.length>1) && theWebUI.isCorrectRatio(rat[1]) ? theWebUI.ratios[rat[1]].name : "" );
					break;
				}
        		}
			return(plugin.trtFormat(table,arr));
		};
	}
	plugin.config.call(this);
	plugin.reqId = theRequestManager.addRequest("trt", theRequestManager.map("cat=")+'$'+theRequestManager.map("d.views="),function(hash,torrent,value)
	{
		torrent.ratiogroup = value;
	});
	if(plugin.canChangeColumns())
		plugin.trtRenameColumn();
	thePlugins.waitLoad( "thePlugins.get('ratio').allDone" );
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
				$('#rat_time'+i).val( theWebUI.ratios[i].time );
				$('#rat_name'+i).val( theWebUI.ratios[i].name );
			}
			$('#ratDefault').val(theWebUI.defaultRatio);
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
				($('#rat_time'+i).val() != theWebUI.ratios[i].time) ||
				($('#rat_name'+i).val() != theWebUI.ratios[i].name))
				return(true);
		}
		return($('#ratDefault').val()!=theWebUI.defaultRatio);
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
		this.content = "default="+iv($('#ratDefault').val());
		for(var i=0; i<theWebUI.maxRatio; i++)
		{
			var name = $('#rat_name'+i).val().trim();
			var upload = $('#rat_upload'+i).val();
			var min = $('#rat_min'+i).val();
			var time = $('#rat_time'+i).val();
			var max = $('#rat_max'+i).val();
			var action = $('#rat_action'+i).val();
	        	if(name.length)
				this.content += ('&rat_upload'+i+'='+upload+'&rat_action'+i+'='+action+'&rat_min'+i+'='+min+'&rat_max'+i+'='+max+'&rat_time'+i+'='+time+'&rat_name'+i+'='+encodeURIComponent(name));
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
			var table = this.getTable("trt");
			if((table.selCount==1) && (table.getFirstSelected().length!=40))
				theContextMenu.add(el,[theUILang.mnuRatio, null]);
			else
			{
				if(table.selCount==1)
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
	}

	theWebUI.setRatio = function(ratio)
	{
		var sr = this.getTable("trt").rowSel;
		var req = '';
		for(var k in sr)
	       		if(sr[k] && (k.length==40))
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

plugin.onLangLoaded = function() 
{
	var s = 
		"<fieldset>"+
			"<legend>"+theUILang.ratios+"</legend>"+
			"<div id='st_ratio_h'>"+
			"<table>"+
				"<tr>"+
					"<td><b>"+theUILang.Num_No+"</b></td>"+
					"<td align=center><b>"+theUILang.ratioName+"</b></td>"+
					"<td align=center><b>"+theUILang.minRatio+",%</b></td>"+
					"<td align=center><b>"+theUILang.maxRatio+",%</b></td>"+
					"<td align=center><b>"+theUILang.ratioUpload+","+theUILang.GB+"</b></td>"+
					"<td class='ratio_time' align=center><b>"+theUILang.maxTime+","+theUILang.time_h.substr(0,theUILang.time_h.length-1)+"</b></td>"+
					"<td align=center><b>"+theUILang.ratioAction+"</b></td>"+
				"</tr>";
	for(var i=0; i<theWebUI.maxRatio; i++)
		s +=
			"<tr>"+
				"<td class='alr'><b>"+(i+1)+".</b></td>"+
				"<td><input type='text' id='rat_name"+i+"' class='TextboxShort'/></td>"+
				"<td><input type='text' id='rat_min"+i+"' class='Textbox num1'/></td>"+
				"<td><input type='text' id='rat_max"+i+"' class='Textbox num1'/></td>"+
				"<td><input type='text' id='rat_upload"+i+"' class='Textbox num1' maxlength='6'/></td>"+
				"<td class='ratio_time'><input type='text' id='rat_time"+i+"' class='Textbox num1' maxlength='6'/></td>"+
				"<td><select id='rat_action"+i+"'><option value='0'>"+theUILang.ratioStop+"</option><option value='1'>"+theUILang.ratioStopAndRemove+"</option><option value='2'>"+theUILang.ratioErase+"</option><option value='3'>"+theUILang.ratioEraseData+"</option><option value='4'>"+theUILang.ratioEraseData+" ("+theUILang.All+")</option></select></td>"+
			"</tr>";
	s+="</table></div></fieldset>";	
	// Table to put the default ratio group selector beside the UL Legend
	s+="<table><tr><td>";	
	// Legend explaining how UL column works
	s+="<div id='st_legend'><fieldset><legend>"+theUILang.ratioUpload+","+theUILang.GB+"</legend><table>";
	s+="<tr><label>"+theUILang.minRatio+": 0.01"+theUILang.GB+" = 10"+theUILang.MB+"</label><br></tr>";
	s+="<tr><label>"+theUILang.maxRatio+": 999999"+theUILang.GB+" = 1"+theUILang.PB+"</label></tr>";
	s+="</table></fieldset></div></td>";
	// Default ratio group selector
	s+="<td><div class='aright'><label>"+theUILang.ratioDefault+":</label><select id='ratDefault'><option value='0'>"+theUILang.dontSet+"</option>";
	for(var i=1; i<=theWebUI.maxRatio; i++)
		s+="<option value='"+i+"'>"+i+"</option>";
	s+="</select></div></td></tr></table>";
	this.attachPageToOptions($("<div>").attr("id","st_ratio").html(s).get(0),theUILang.ratios);
}

plugin.onRemove = function()
{
	this.removePageFromOptions("st_ratio");
	theWebUI.getTable("trt").removeColumnById("ratiogroup");
	if(thePlugins.isInstalled("rss"))
		theWebUI.getTable("rss").removeColumnById("ratiogroup");
	if(thePlugins.isInstalled("extsearch"))
		theWebUI.getTable("teg").removeColumnById("ratiogroup");
	theRequestManager.removeRequest( "trt", plugin.reqId );
}
