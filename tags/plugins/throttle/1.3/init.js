var plugin = new rPlugin("throttle");
plugin.loadMainCSS();
plugin.loadLanguages();

utWebUI.allThrottleStuffLoaded = false;
utWebUI.channelColumnNo = utWebUI.trtColumns.length;
utWebUI.throttleNo = 27;
utWebUI.throttleSupported = true;

utWebUI.throttleAddAndShowSettings = utWebUI.addAndShowSettings;
utWebUI.addAndShowSettings = function(arg) 
{
        if(utWebUI.throttleSupported)
	        for(var i=0; i<utWebUI.maxThrottle; i++)
		{
	        	if(utWebUI.isCorrectThrottle(i))
			{
				$$('thr_up'+i).value = utWebUI.throttles[i].up;
				$$('thr_down'+i).value = utWebUI.throttles[i].down;
				$$('thr_name'+i).value = utWebUI.throttles[i].name;
			}
			else
			{
				$$('thr_up'+i).value = '';
				$$('thr_down'+i).value = '';
				$$('thr_name'+i).value = '';
			}
		}
	utWebUI.throttleAddAndShowSettings(arg);
}

utWebUI.throttleWasChanged = function() 
{
	for(var i=0; i<utWebUI.maxThrottle; i++)
	{
		if( 	(utWebUI.throttles[i].up!==$$('thr_up'+i).value) ||
			(utWebUI.throttles[i].down!==$$('thr_down'+i).value) ||
			(utWebUI.throttles[i].name!==$$('thr_name'+i).value))
			return(true);
	}
	return(false);
}

utWebUI.throttleSetSettings = utWebUI.setSettings;
utWebUI.setSettings = function() 
{
	this.throttleSetSettings();
	if(utWebUI.throttleSupported && this.throttleWasChanged())
		this.Request("?action=setthrottleprm");
}

utWebUI.setThrottle = function(throttle)
{
	var sr = this.trtTable.rowSel;
	var req = '';
	for(var k in sr)
	       	if(sr[k])
			req += ("&hash=" + k + "&v="+throttle);
	if(req.length>0)
		this.Request("?action=setthrottle"+req+"&list=1",[this.addTorrents, this]);
}

utWebUI.getThrottleData = function(id)
{
	var curNo = -1;
	var s = this.torrents[id][utWebUI.throttleNo];
	var arr = s.match(/^thr_(\d{1,2})$/);
	if(arr && (arr.length>1))
		curNo = arr[1];
	return(curNo);
}

utWebUI.isCorrectThrottle = function(i)
{
	return( ((i>=0) && (i<utWebUI.throttles.length)) &&
	        (utWebUI.throttles[i].name!="") &&
		(utWebUI.throttles[i].up>=0) &&
		(utWebUI.throttles[i].down>=0));
}

utWebUI.throttleCreateMenu = utWebUI.createMenu;
utWebUI.createMenu = function(e, id)
{
	this.throttleCreateMenu(e, id);
	if(!utWebUI.throttleSupported)
		return;
	var el = ContextMenu.get(WUILang.Priority);
	if(el)
	{
		var curNo = null;
		if(this.trtTable.selCount==1)
			curNo = utWebUI.getThrottleData(id);
		var down = [];
		if(curNo==-1)
			down.push([WUILang.mnuUnlimited]);
		else
			down.push([WUILang.mnuUnlimited,"utWebUI.setThrottle('-1')"]);
		down.push([CMENU_SEP]);
		for(var i=0; i<utWebUI.maxThrottle; i++)
		{
			if(utWebUI.isCorrectThrottle(i))
			{
			        var s = utWebUI.throttles[i].name;
				if(i!=curNo)
					down.push([s,"utWebUI.setThrottle('"+i+"')"]);
				else
					down.push([s]);
			}
		}
		ContextMenu.add(el,[CMENU_CHILD, WUILang.mnuThrottle, down]);
	}
}

function formatChannel(s)
{
	var arr = s.match(/^thr_(\d{1,2})$/);
	return( arr && (arr.length>1) && utWebUI.isCorrectThrottle(arr[1]) ? utWebUI.throttles[arr[1]].name : "" );
}

utWebUI.throttlefillAdditionalTorrentsCols = utWebUI.fillAdditionalTorrentsCols;
utWebUI.fillAdditionalTorrentsCols = function(id,row)
{
	row = utWebUI.throttlefillAdditionalTorrentsCols(id,row);
	if(utWebUI.throttleSupported)
		row[utWebUI.channelColumnNo] = formatChannel(this.torrents[id][utWebUI.throttleNo]);
	return(row);
}

utWebUI.throttleupdateAdditionalTorrentsCols = utWebUI.updateAdditionalTorrentsCols;
utWebUI.updateAdditionalTorrentsCols = function(id)
{
	utWebUI.throttleupdateAdditionalTorrentsCols(id);
	if(utWebUI.throttleSupported && this.trtTable.setValue(id, utWebUI.channelColumnNo, formatChannel(this.torrents[id][utWebUI.throttleNo])))
		this.noUpdate = false;
}

rTorrentStub.prototype.setthrottle = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		var needRestart = (utWebUI.torrents[this.hashes[i]][2]==WUILang.Seeding) || (utWebUI.torrents[this.hashes[i]][2]==WUILang.Downloading);
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

rTorrentStub.prototype.setthrottleprm = function()
{
	this.content = "dummy=1";
	for(var i=0; i<utWebUI.maxThrottle; i++)
	{
		var name = $$('thr_name'+i).value;
		var up = parseInt($$('thr_up'+i).value);
		var down = parseInt($$('thr_down'+i).value);
                if(name.length)
			this.content += ('&thr_up'+i+'='+up+'&thr_down'+i+'='+down+'&thr_name'+i+'='+encodeURIComponent(name));
	}
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/throttle/action.php";
}

rTorrentStub.prototype.setthrottleprmResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	return(datas[0].childNodes[0].data);
}

rTorrentStub.prototype.throttlelist = rTorrentStub.prototype.list;
rTorrentStub.prototype.list = function()
{
	this.throttlelist();
	if(utWebUI.throttleSupported)
	{
		if(utWebUI.allThrottleStuffLoaded)
			utWebUI.throttleNo = this.commands[this.commands.length-1].params.length-7;
		this.commands[this.commands.length-1].addParameter("string","d.get_throttle_name=");
	}
}

rTorrentStub.prototype.throttlegetAdditionalResponseForListItem = rTorrentStub.prototype.getAdditionalResponseForListItem;
rTorrentStub.prototype.getAdditionalResponseForListItem = function(values)
{
	var ret = this.throttlegetAdditionalResponseForListItem(values);
	if(utWebUI.throttleSupported)
	       ret+=(',"'+this.getValue(values,utWebUI.throttleNo+6)+'"');
	return(ret);
}

utWebUI.throttleCreate = function() 
{
	if(utWebUI.throttleSupported)
	{
		var dlg = document.createElement("DIV");
		dlg.className = "stg_con";
		dlg.id = "st_throttle";
		var s = 
			"<fieldset>"+
				"<legend>"+WUILang.throttles+"</legend>"+
				"<div id='st_throttle_h'>"+
				"<table>"+
					"<tr>"+
						"<td><b>No</b></td>"+
						"<td><b>"+WUILang.channelName+"</b></td>"+
						"<td><b>"+WUILang.UL+" ("+WUILang.KB+"/"+WUILang.s+")</b></td>"+
						"<td><b>"+WUILang.DL+" ("+WUILang.KB+"/"+WUILang.s+")</b></td>"+
					"</tr>";
		for(var i=0; i<utWebUI.maxThrottle; i++)
			s +=
				"<tr>"+
				        "<td class='alr'><b>"+(i+1)+".</b></td>"+
					"<td><input type='text' id='thr_name"+i+"' class='TextboxLarge'/></td>"+
					"<td><input type='text' id='thr_up"+i+"' class='Textbox num' maxlength='6'/></td>"+
					"<td><input type='text' id='thr_down"+i+"' class='Textbox num' maxlength='6'/></td>"+
				"</tr>";
		s+="</table></div></fieldset>";
		dlg.innerHTML = s;
		plugin.attachPageToOptions(dlg,WUILang.throttles);
	}
	utWebUI.allThrottleStuffLoaded = true;
}

utWebUI.throttleinitDone = utWebUI.initDone;

utWebUI.initDoneNew = function()
{
	if(utWebUI.allThrottleStuffLoaded)
	{
		utWebUI.trtTable.renameColumn(utWebUI.channelColumnNo,WUILang.throttle);
		if( utWebUI.rssTable )
			utWebUI.rssRenameColumn();
	}
	else
		setTimeout('utWebUI.initDoneNew()',1000);
}

utWebUI.initDone = function()
{
	utWebUI.throttleinitDone();
	if(utWebUI.throttleSupported)
		utWebUI.initDoneNew();
}

utWebUI.rssRenameColumn = function()
{
	if(utWebUI.rssTable.created)
		utWebUI.rssTable.renameColumn(utWebUI.channelColumnNo,WUILang.throttle);
	else
		setTimeout('utWebUI.rssRenameColumn()',1000);
}

utWebUI.showThrottleError = function(err)
{
	if(utWebUI.allThrottleStuffLoaded)
		log(err);
	else
		setTimeout('utWebUI.showThrottleError('+err+')',1000);
}