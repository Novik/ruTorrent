var plugin = new rPlugin("ratio");
plugin.loadMainCSS();
plugin.loadLanguages();

utWebUI.allRatioStuffLoaded = false;
utWebUI.ratioColumnNo = utWebUI.trtColumns.length;
utWebUI.ratioNo = 27;
utWebUI.ratioSupported = true;

utWebUI.setRatioSelect = function( obj, value )
{
	obj.selectedIndex = 0;
	for(var i = 0; i<obj.options.length; i++)
	{
		if(obj.options[i].value==value)
		{	
			obj.selectedIndex = i;
			break;
		}
	}
}

utWebUI.ratioAddAndShowSettings = utWebUI.addAndShowSettings;
utWebUI.addAndShowSettings = function(arg) 
{
        if(utWebUI.ratioSupported)
	        for(var i=0; i<utWebUI.maxRatio; i++)
		{
		        utWebUI.setRatioSelect( $$('rat_min'+i), utWebUI.ratios[i].min );
		        utWebUI.setRatioSelect( $$('rat_max'+i), utWebUI.ratios[i].max );
		        utWebUI.setRatioSelect( $$('rat_action'+i), utWebUI.ratios[i].action );
			$$('rat_upload'+i).value = utWebUI.ratios[i].upload;
			$$('rat_name'+i).value = utWebUI.ratios[i].name;
		}
	utWebUI.ratioAddAndShowSettings(arg);
}

utWebUI.ratioWasChanged = function() 
{
	for(var i=0; i<utWebUI.maxRatio; i++)
	{
		if( 	(utWebUI.ratios[i].min!==$$('rat_min'+i).options[$$('rat_min'+i).selectedIndex].value) ||
			(utWebUI.ratios[i].max!==$$('rat_max'+i).options[$$('rat_max'+i).selectedIndex].value) ||
			(utWebUI.ratios[i].max!==$$('rat_action'+i).options[$$('rat_action'+i).selectedIndex].value) ||
			($$('rat_upload'+i).value != utWebUI.ratios[i].upload) ||
			($$('rat_name'+i).value != utWebUI.ratios[i].name))
			return(true);
	}
	return(false);
}

utWebUI.ratioSetSettings = utWebUI.setSettings;
utWebUI.setSettings = function() 
{
	this.ratioSetSettings();
	if(utWebUI.ratioSupported && this.ratioWasChanged())
		this.Request("?action=setratioprm");
}

utWebUI.setRatio = function(ratio)
{
	var sr = this.trtTable.rowSel;
	var req = '';
	for(var k in sr)
	       	if(sr[k])
			req += ("&hash=" + k + "&v="+ratio);
	if(req.length>0)
		this.Request("?action=setratio"+req+"&list=1",[this.addTorrents, this]);
}

utWebUI.getRatioData = function(id)
{
	var curNo = -1;
	var s = this.torrents[id][utWebUI.ratioNo];
	var arr = s.match(/rat_(\d{1,2})/);
	if(arr && (arr.length>1))
		curNo = arr[1];
	return(curNo);
}

utWebUI.isCorrectRatio = function(i)
{
	return( ((i>=0) && (i<utWebUI.ratios.length)) &&
	        (utWebUI.ratios[i].name!=""));
}

utWebUI.ratioCreateMenu = utWebUI.createMenu;
utWebUI.createMenu = function(e, id)
{
	this.ratioCreateMenu(e, id);
	if(!utWebUI.ratioSupported)
		return;
	var el = ContextMenu.get(WUILang.Priority);
	if(el)
	{
		var curNo = null;
		if(this.trtTable.selCount==1)
			curNo = utWebUI.getRatioData(id);
		var down = [];
		if(curNo==-1)
			down.push([WUILang.mnuRatioUnlimited]);
		else
			down.push([WUILang.mnuRatioUnlimited,"utWebUI.setRatio('-1')"]);
		down.push([CMENU_SEP]);
		for(var i=0; i<utWebUI.maxRatio; i++)
		{
			if(utWebUI.isCorrectRatio(i))
			{
			        var s = utWebUI.ratios[i].name;
				if(i!=curNo)
					down.push([s,"utWebUI.setRatio('"+i+"')"]);
				else
					down.push([s]);
			}
		}
		ContextMenu.add(el,[CMENU_CHILD, WUILang.mnuRatio, down]);
	}
}

function formatRatio(s)
{
	var arr = s.match(/rat_(\d{1,2})/);
	return( arr && (arr.length>1) && utWebUI.isCorrectRatio(arr[1]) ? utWebUI.ratios[arr[1]].name : "" );
}

utWebUI.ratiofillAdditionalTorrentsCols = utWebUI.fillAdditionalTorrentsCols;
utWebUI.fillAdditionalTorrentsCols = function(id,row)
{
	row = utWebUI.ratiofillAdditionalTorrentsCols(id,row);
	if(utWebUI.ratioSupported)
		row[utWebUI.ratioColumnNo] = formatRatio(this.torrents[id][utWebUI.ratioNo]);
	return(row);
}

utWebUI.ratioupdateAdditionalTorrentsCols = utWebUI.updateAdditionalTorrentsCols;
utWebUI.updateAdditionalTorrentsCols = function(id)
{
	utWebUI.ratioupdateAdditionalTorrentsCols(id);
	if(utWebUI.ratioSupported && this.trtTable.setValue(id, utWebUI.ratioColumnNo, formatRatio(this.torrents[id][utWebUI.ratioNo])))
		this.noUpdate = false;
}

rTorrentStub.prototype.setratio = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		var wasNo = utWebUI.getRatioData(this.hashes[i]);
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

rTorrentStub.prototype.setratioprm = function()
{
	this.content = "dummy=1";
	for(var i=0; i<utWebUI.maxRatio; i++)
	{
		var name = $$('rat_name'+i).value;
		var upload = parseInt($$('rat_upload'+i).value);
		var min = $$('rat_min'+i).options[$$('rat_min'+i).selectedIndex].value;
		var max = $$('rat_max'+i).options[$$('rat_max'+i).selectedIndex].value;
		var action = $$('rat_action'+i).options[$$('rat_action'+i).selectedIndex].value;
                if(name.length)
			this.content += ('&rat_upload'+i+'='+upload+'&rat_action'+i+'='+action+'&rat_min'+i+'='+min+'&rat_max'+i+'='+max+'&rat_name'+i+'='+encodeURIComponent(name));
	}
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/ratio/action.php";
}

rTorrentStub.prototype.setratioprmResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	return(datas[0].childNodes[0].data);
}

rTorrentStub.prototype.ratiolist = rTorrentStub.prototype.list;
rTorrentStub.prototype.list = function()
{
	this.ratiolist();
	if(utWebUI.ratioSupported)
	{
		if(utWebUI.allRatioStuffLoaded)
			utWebUI.ratioNo = this.commands[this.commands.length-1].params.length-7;
		this.commands[this.commands.length-1].addParameter("string","cat=$d.views=");
	}
}

rTorrentStub.prototype.ratiogetAdditionalResponseForListItem = rTorrentStub.prototype.getAdditionalResponseForListItem;
rTorrentStub.prototype.getAdditionalResponseForListItem = function(values)
{
	var ret = this.ratiogetAdditionalResponseForListItem(values);
	if(utWebUI.ratioSupported)
	       ret+=(',"'+this.getValue(values,utWebUI.ratioNo+6)+'"');
	return(ret);
}

utWebUI.ratioCreate = function() 
{
	if(utWebUI.ratioSupported)
	{
		var dlg = document.createElement("DIV");
		dlg.className = "stg_con";
		dlg.id = "st_ratio";
		var s = 
			"<fieldset>"+
				"<legend>"+WUILang.ratios+"</legend>"+
				"<div id='st_ratio_h'>"+
				"<table>"+
					"<tr>"+
						"<td><b>"+WUILang.ratioName+"</b></td>"+
						"<td><b>"+WUILang.minRatio+" (%)</b></td>"+
						"<td><b>"+WUILang.maxRatio+" (%)</b></td>"+
						"<td><b>"+WUILang.ratioUpload+" ("+WUILang.MB+")</b></td>"+
						"<td><b>"+WUILang.ratioAction+"</b></td>"+
					"</tr>";
		for(var i=0; i<utWebUI.maxRatio; i++)
			s +=
				"<tr>"+
					"<td><input type='text' id='rat_name"+i+"' class='TextboxShort'/></td>"+
					"<td><select id='rat_min"+i+"'><option value='100'>100</option><option value='200'>200</option><option value='300'>300</option><option value='400'>400</option><option value='500'>500</option><option value='600'>600</option><option value='700'>700</option><option value='800'>800</option><option value='900'>900</option></select></td>"+
					"<td><select id='rat_max"+i+"'><option value='0'>"+WUILang.mnuRatioUnlimited+"</option><option value='100'>100</option><option value='200'>200</option><option value='300'>300</option><option value='400'>400</option><option value='500'>500</option><option value='600'>600</option><option value='700'>700</option><option value='800'>800</option><option value='900'>900</option></select></td>"+
					"<td><input type='text' id='rat_upload"+i+"' class='Textbox num' maxlength='6'/></td>"+
					"<td><select id='rat_action"+i+"'><option value='0'>"+WUILang.ratioStop+"</option><option value='1'>"+WUILang.ratioStopAndRemove+"</option><option value='2'>"+WUILang.ratioErase+"</option></select></td>"+
				"</tr>";
		s+="</table></div></fieldset>";
		dlg.innerHTML = s;
		plugin.attachPageToOptions(dlg,WUILang.ratios);
	}
	utWebUI.allRatioStuffLoaded = true;
}

utWebUI.initDoneRatio = function()
{
	if(utWebUI.allRatioStuffLoaded)
	{
		utWebUI.trtTable.renameColumn(utWebUI.ratioColumnNo,WUILang.ratio);
		if( utWebUI.rssTable )
			utWebUI.rssRatioRenameColumn();
	}
	else
		setTimeout('utWebUI.initDoneRatio()',1000);
}

utWebUI.ratioinitDone = utWebUI.initDone;
utWebUI.initDone = function()
{
	utWebUI.ratioinitDone();
	if(utWebUI.ratioSupported)
		utWebUI.initDoneRatio();
}

utWebUI.rssRatioRenameColumn = function()
{
	if(utWebUI.rssTable.created)
		utWebUI.rssTable.renameColumn(utWebUI.ratioColumnNo,WUILang.ratio);
	else
		setTimeout('utWebUI.rssRatioRenameColumn()',1000);
}

utWebUI.showRatioError = function(err)
{
	if(utWebUI.allRatioStuffLoaded)
		log(err);
	else
		setTimeout('utWebUI.showRatioError('+err+')',1000);
}