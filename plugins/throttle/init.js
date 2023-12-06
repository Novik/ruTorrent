plugin.loadLang();
plugin.loadMainCSS();

plugin.config = theWebUI.config;
theWebUI.config = function()
{
	if(plugin.canChangeColumns())
	{
		this.tables.trt.columns.push({ text: 'Channel', width: '60px', id: "throttle", type: TYPE_STRING});
		plugin.trtFormat = this.tables.trt.format;
		this.tables.trt.format = function(table,arr)
		{
			for(var i in arr)
			{
				if((table.getIdByCol(i)=="throttle") && arr[i])
  				{
	   				var thr = arr[i].match(/^thr_(\d{1,2})$/);
					arr[i] = ( thr && (thr.length>1) && theWebUI.isCorrectThrottle(thr[1]) ? theWebUI.throttles[thr[1]].name : "" );
					break;
				}
		        }
			return(plugin.trtFormat(table,arr));
		};
	}
	plugin.reqId = theRequestManager.addRequest("trt", "d.get_throttle_name=",function(hash,torrent,value)
	{
		torrent.throttle = value;
	});
	plugin.config.call(this);
	if(plugin.canChangeColumns())
		plugin.trtRenameColumn();
}

if(plugin.canChangeColumns())
{
	plugin.trtRenameColumn = function()
	{
		if(plugin.allStuffLoaded)
		{
			theWebUI.getTable("trt").renameColumnById("throttle",theUILang.throttle);
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
			theWebUI.getTable("rss").renameColumnById("throttle",theUILang.throttle);
		else
			setTimeout(arguments.callee,1000);
	}

	plugin.tegRenameColumn = function()
	{
		if(theWebUI.getTable("teg").created)
			theWebUI.getTable("teg").renameColumnById("throttle",theUILang.throttle);
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
		        for(var i=0; i<theWebUI.maxThrottle; i++)
			{
        			if(theWebUI.isCorrectThrottle(i))
				{
					$('#thr_up'+i).val( theWebUI.throttles[i].up );
					$('#thr_down'+i).val( theWebUI.throttles[i].down );
					$('#thr_name'+i).val( theWebUI.throttles[i].name );
				}
				else
					$('#thr_up'+i+',#thr_down'+i+',#thr_name'+i).val('');
			}
			$('#chDefault').val(theWebUI.defaultThrottle);
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.throttleWasChanged = function() 
	{
		for(var i=0; i<theWebUI.maxThrottle; i++)
		{
			if( 	(theWebUI.throttles[i].up!=$('#thr_up'+i).val()) ||
				(theWebUI.throttles[i].down!=$('#thr_down'+i).val()) ||
				(theWebUI.throttles[i].name!=$('#thr_name'+i).val()))
				return(true);
		}
		return($('#chDefault').val()!=theWebUI.defaultThrottle);
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() 
	{
		plugin.setSettings.call(this);
		if(plugin.enabled && this.throttleWasChanged())
			this.request("?action=setthrottleprm");
	}

	rTorrentStub.prototype.setthrottleprm = function()
	{
		this.content = "default="+iv($('#chDefault').val());
		for(var i=0; i<theWebUI.maxThrottle; i++)
		{
			var name = $('#thr_name'+i).val().trim();
			var up = iv($('#thr_up'+i).val());
			var down = iv($('#thr_down'+i).val());
        		if(name.length)
				this.content += ('&thr_up'+i+'='+up+'&thr_down'+i+'='+down+'&thr_name'+i+'='+encodeURIComponent(name));
		}
        	this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/throttle/action.php";
		this.dataType = "script";
	}
}

if(plugin.canChangeMenu())
{
	theWebUI.getThrottleData = function(id)
	{
		var curNo = -1;
		var s = this.torrents[id].throttle;
		var arr = s.match(/^thr_(\d{1,2})$/);
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
			if((table.selCount==1) && (table.getFirstSelected().length>40))
				theContextMenu.add(el,[theUILang.mnuThrottle, null]);
			else
			{
				if(table.selCount==1)
					curNo = theWebUI.getThrottleData(id);
				var down = [];
				down.push([theUILang.mnuUnlimited, (curNo==-1) ? null : "theWebUI.setThrottle('-1')"]);
				down.push([CMENU_SEP]);
				for(var i=0; i<theWebUI.maxThrottle; i++)
				{
					if(theWebUI.isCorrectThrottle(i))
						down.push([theWebUI.throttles[i].name,(i!=curNo) ? "theWebUI.setThrottle('"+i+"')" : null]);
				}
				theContextMenu.add(el,[CMENU_CHILD, theUILang.mnuThrottle, down]);
			}
		}
	}

	theWebUI.setThrottle = function(throttle)
	{
		var sr = this.getTable("trt").rowSel;
		var req = '';
		for(var k in sr)
       			if(sr[k] && (k.length==40))
				req += ("&hash=" + k + "&v="+throttle);
		if(req.length>0)
			this.request("?action=setthrottle"+req+"&list=1",[this.addTorrents, this]);
	}

	rTorrentStub.prototype.setthrottle = function()
	{
		for(var i=0; i<this.vs.length; i++)
		{
			var needRestart = (theWebUI.torrents[this.hashes[i]].status==theUILang.Seeding) || (theWebUI.torrents[this.hashes[i]].status==theUILang.Downloading);
			var name = (this.vs[i]>=0) ? "thr_"+this.vs[i] : "";
			if(needRestart)
			{
				cmd = new rXMLRPCCommand('d.stop');
				cmd.addParameter("string",this.hashes[i]);
				this.commands.push( cmd );
			}
			cmd = new rXMLRPCCommand('d.set_throttle_name');
			cmd.addParameter("string",this.hashes[i]);
			cmd.addParameter("string",name);
			this.commands.push( cmd );
			if(needRestart)
			{
				cmd = new rXMLRPCCommand('d.start');
				cmd.addParameter("string",this.hashes[i]);
				this.commands.push( cmd );
			}
		}
	}
}

theWebUI.isCorrectThrottle = function(i)
{
	return( ((i>=0) && (i<theWebUI.throttles.length)) &&
        	(theWebUI.throttles[i].name!="") &&
		(theWebUI.throttles[i].up>=0) &&
		(theWebUI.throttles[i].down>=0));
}

plugin.onLangLoaded = function() 
{
	var s = 
		"<fieldset>"+
			"<legend>"+theUILang.throttles+"</legend>"+
			"<div id='st_throttle_h'>"+
			"<table>"+
				"<tr>"+
					"<td><b>"+theUILang.Num_No+"</b></td>"+
					"<td><b>"+theUILang.channelName+"</b></td>"+
					"<td><b>"+theUILang.UL+" ("+theUILang.KB+"/"+theUILang.s+")</b></td>"+
					"<td><b>"+theUILang.DL+" ("+theUILang.KB+"/"+theUILang.s+")</b></td>"+
				"</tr>";
	for(var i=0; i<theWebUI.maxThrottle; i++)
		s +=
			"<tr>"+
			        "<td class='alr'><b>"+(i+1)+".</b></td>"+
				"<td><input type='text' id='thr_name"+i+"' class='TextboxLarge'/></td>"+
				"<td><input type='text' id='thr_up"+i+"' class='Textbox num' maxlength='6'/></td>"+
				"<td><input type='text' id='thr_down"+i+"' class='Textbox num' maxlength='6'/></td>"+
			"</tr>";
	s+="</table></div></fieldset>";
	s+="<div class='aright'><label>"+theUILang.channelDefault+":</label><select id='chDefault'><option value='0'>"+theUILang.dontSet+"</option>";
	for(var i=1; i<=theWebUI.maxThrottle; i++)
		s+="<option value='"+i+"'>"+i+"</option>";
	s+="</select></div>";
	this.attachPageToOptions($("<div>").attr("id","st_throttle").html(s).get(0),theUILang.throttles);
}

plugin.onRemove = function()
{
	this.removePageFromOptions("st_throttle");
	theWebUI.getTable("trt").removeColumnById("throttle");
	if(thePlugins.isInstalled("rss"))
		theWebUI.getTable("rss").removeColumnById("throttle");
	theRequestManager.removeRequest( "trt", plugin.reqId );
}