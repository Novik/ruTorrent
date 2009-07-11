var plugin = new rPlugin("scheduler");
plugin.loadMainCSS();
plugin.loadLanguages();

utWebUI.allSchedulerStuffLoaded = false;

utWebUI.schedulerAddAndShowSettings = utWebUI.addAndShowSettings;
utWebUI.addAndShowSettings = function(arg) 
{
	var tbl = $$('sch_graph');
	for(var i=0; i<7; i++)
	{
		for(var j=1; j<25; j++)
		{
			cell = tbl.rows[i].cells[j];
			cell.setAttribute("clr",utWebUI.scheduleTable.week[i][j-1]);
		}
	}
	$$('sch_enable').checked = utWebUI.scheduleTable.enabled;
	for(var i=0; i<3; i++)
	{
		$$('restrictedUL'+(i+1)).value = utWebUI.scheduleTable.UL[i];
		$$('restrictedDL'+(i+1)).value = utWebUI.scheduleTable.DL[i];
	}
	utWebUI.linkedSch($$('sch_enable'), ['restrictedUL1', 'restrictedDL1', 'restrictedUL2', 'restrictedDL2', 'restrictedUL3', 'restrictedDL3']);
	utWebUI.schedulerAddAndShowSettings(arg);
}

utWebUI.schedulerWasChanged = function() 
{
	var ret = false;
	if($$('sch_enable').checked != utWebUI.scheduleTable.enabled)
		ret = true;
	for(var i=0; i<3 && !ret; i++)
		if(($$('restrictedUL'+(i+1)).value!=utWebUI.scheduleTable.UL[i]) ||
			($$('restrictedDL'+(i+1)).value!=utWebUI.scheduleTable.DL[i]))
			ret = true;
	var tbl = $$('sch_graph');
	for(var i=0; i<7 && !ret; i++)
		for(var j=1; j<25 && !ret; j++)
			if(tbl.rows[i].cells[j].getAttribute("clr")!=utWebUI.scheduleTable.week[i][j-1])
				ret = true;
	return(ret);
}

utWebUI.schedulerSetSettings = utWebUI.setSettings;
utWebUI.setSettings = function() 
{
	this.schedulerSetSettings();
	if(this.schedulerWasChanged())
		this.Request("?action=setschedule");
}

rTorrentStub.prototype.setschedule = function()
{
	this.content = "dummy=1";
	var tbl = $$('sch_graph');
	for(var i=0; i<7; i++)
	{
		for(var j=1; j<25; j++)
		{
			var cell = tbl.rows[i].cells[j];
			this.content += ('&day_'+i+'_'+(j-1)+'='+cell.getAttribute("clr"));
		}
	}
	for(var i=0; i<3; i++)
	{
		this.content += ('&UL'+i+'='+$$('restrictedUL'+(i+1)).value);
		this.content += ('&DL'+i+'='+$$('restrictedDL'+(i+1)).value);
	}
	this.content += ('&enabled='+$$('sch_enable').checked);
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/scheduler/action.php";
}

rTorrentStub.prototype.setscheduleResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	return(datas[0].childNodes[0].data);
}

var schClasses = [ "sch_fast", "sch_stop", "sch_seed", "sch_res1", "sch_res2", "sch_res3" ];

utWebUI.schMouseOver = function(i,j)
{
	var from = (j < 10) ? ("0" + j) : j;
	$$('sch_desc').innerHTML = WUILang.schFullWeek[i]+', '+from+':00 - '+from+':59';
}

utWebUI.schLegendMouseOver = function(i)
{
	var schDesc = [ WUILang.schUnlimitedDesc, WUILang.schTurnOffDesc, WUILang.schSeedingOnlyDesc, WUILang.schLimitedDesc+"1", WUILang.schLimitedDesc+"2", WUILang.schLimitedDesc+"3" ];
	$$('sch_desc').innerHTML = schDesc[i];
}

utWebUI.schMouseOut = function()
{
	$$('sch_desc').innerHTML = '&nbsp;';
}

utWebUI.schClick = function(obj,i,j)
{
	if($$('sch_enable').checked)
	{
		var clr = parseInt(obj.getAttribute("clr"))+1;
		if(clr>=schClasses.length)
			clr = 0;
		obj.className = schClasses[clr];
		obj.setAttribute("clr",clr);
	}
}

utWebUI.linkedSch = function(obj, lst) 
{
	linked(obj,0,lst);
	var tbl = $$('sch_graph');
	var isChecked = $$('sch_enable').checked;
	for(var i=0; i<7; i++)
	{
		var cell = tbl.rows[i].cells[0];
		cell.className = isChecked ? 'sch_week' : 'sch_week disabled';
		for(var j=1; j<25; j++)
		{
			cell = tbl.rows[i].cells[j];
			var clr = schClasses[cell.getAttribute("clr")];
			cell.className = isChecked ? clr : clr+"dis";
		}
	}
	tbl = $$('sch_legend');
	for(var i=0; i<2; i++)
	{
	        for(var j=0; j<6; j++)
	        {
		        var cell = tbl.rows[i].cells[j];
			var clr = schClasses[cell.getAttribute("clr")];
			if(clr!=null)
				cell.className = isChecked ? clr : clr+"dis";
			else
				cell.className = isChecked ? '' : "disabled";
	        }
	}
	$$('sch_desc').className = isChecked ? '' : "disabled";
}

utWebUI.schedulerCreate = function() 
{
	var dlg = document.createElement("DIV");
	dlg.className = "stg_con";
	dlg.id = "st_scheduler";
	var s = 
		"<div>"+
			"<input id='sch_enable' type='checkbox' onchange=\"javascript:utWebUI.linkedSch(this, ['restrictedUL1', 'restrictedDL1', 'restrictedUL2', 'restrictedDL2', 'restrictedUL3', 'restrictedDL3']);\" />"+
			"<label for='sch_enable'>"+
				WUILang.schedulerOn+
			"</label>"+
		"</div>"+
		"<fieldset>"+
			"<legend>"+WUILang.schedulerGraph+"</legend>"+
			"<table id='sch_graph'>";
	for(var i=0; i<7; i++)
	{
		s += "<tr><td class='sch_week disabled'>"+WUILang.schShortWeek[i]+"</td>";
		for(var j=0; j<24; j++)
		{
			var day = utWebUI.scheduleTable.week[i][j];
			s+="<td class='"+schClasses[day]+"dis' clr='"+day+"' onmouseover='javascript:utWebUI.schMouseOver("+i+","+j+");' onmouseout='javascript:utWebUI.schMouseOut();' onclick='javascript:utWebUI.schClick(this,"+i+","+j+");'></td>";
		}
		s += "</tr>";
	}
	s+="</table><div id='sch_desc' class='disabled'>&nbsp;</div>";
	s+="<table id='sch_legend'>"+
		"<tr>"+
		"<td clr='0' class='sch_fastdis' onmouseover='javascript:utWebUI.schLegendMouseOver(0);' onmouseout='javascript:utWebUI.schMouseOut();'></td><td class='disabled'>"+WUILang.schUnlimited+"</td>"+
		"<td clr='1' class='sch_stopdis' onmouseover='javascript:utWebUI.schLegendMouseOver(1);' onmouseout='javascript:utWebUI.schMouseOut();'></td><td class='disabled'>"+WUILang.schTurnOff+"</td>"+
		"<td clr='2' class='sch_seeddis' onmouseover='javascript:utWebUI.schLegendMouseOver(2);' onmouseout='javascript:utWebUI.schMouseOut();'></td><td class='disabled'>"+WUILang.schSeedingOnly+"</td>"+
		"</tr>"+
		"<tr>"+
		"<td clr='3' class='sch_res1dis' onmouseover='javascript:utWebUI.schLegendMouseOver(3);' onmouseout='javascript:utWebUI.schMouseOut();'></td><td class='disabled'>"+WUILang.schLimited+"1</td>"+
		"<td clr='4' class='sch_res2dis' onmouseover='javascript:utWebUI.schLegendMouseOver(4);' onmouseout='javascript:utWebUI.schMouseOut();'></td><td class='disabled'>"+WUILang.schLimited+"2</td>"+
		"<td clr='5' class='sch_res3dis' onmouseover='javascript:utWebUI.schLegendMouseOver(5);' onmouseout='javascript:utWebUI.schMouseOut();'></td><td class='disabled'>"+WUILang.schLimited+"3</td>"+
		"</tr>"+
	"</table></fieldset><div id='st_scheduler_h'>";

	s+="<fieldset>"+
		"<legend>"+WUILang.schLimited+"1</legend>"+
		"<table>"+
			"<tr>"+
				"<td><label id='lbl_restrictedUL1' for='restrictedUL1' class='disabled'>"+WUILang.schLimitedUL+" ("+WUILang.kbs+"):</label></td>"+
				"<td class=\"alr\"><input type='text' id='restrictedUL1' class='TextboxNum' maxlength='6' disabled=1/></td>"+
			"</tr>"+
			"<tr>"+
				"<td><label id='lbl_restrictedDL1' for='restrictedDL1' class='disabled'>"+WUILang.schLimitedDL+" ("+WUILang.kbs+"):</label></td>"+
				"<td class=\"alr\"><input type='text' id='restrictedDL1' class='TextboxNum' maxlength='6' disabled=1/></td>"+
			"</tr>"+
		"</table>"+
	   "</fieldset>"+
	   "<fieldset>"+
		"<legend>"+WUILang.schLimited+"2</legend>"+
		"<table>"+
			"<tr>"+
				"<td><label id='lbl_restrictedUL2' for='restrictedUL2' class='disabled'>"+WUILang.schLimitedUL+" ("+WUILang.kbs+"):</label></td>"+
				"<td class=\"alr\"><input type='text' id='restrictedUL2' class='TextboxNum' maxlength='6' disabled=1/></td>"+
			"</tr>"+
			"<tr>"+
				"<td><label id='lbl_restrictedDL2' for='restrictedDL2' class='disabled'>"+WUILang.schLimitedDL+" ("+WUILang.kbs+"):</label></td>"+
				"<td class=\"alr\"><input type='text' id='restrictedDL2' class='TextboxNum' maxlength='6' disabled=1/></td>"+
			"</tr>"+
		"</table>"+
	   "</fieldset>"+
	   "<fieldset>"+
		"<legend>"+WUILang.schLimited+"3</legend>"+
		"<table>"+
			"<tr>"+
				"<td><label id='lbl_restrictedUL3' for='restrictedUL3' class='disabled'>"+WUILang.schLimitedUL+" ("+WUILang.kbs+"):</label></td>"+
				"<td class=\"alr\"><input type='text' id='restrictedUL3' class='TextboxNum' maxlength='6' disabled=1/></td>"+
			"</tr>"+
			"<tr>"+
				"<td><label id='lbl_restrictedDL3' for='restrictedDL3' class='disabled'>"+WUILang.schLimitedDL+" ("+WUILang.kbs+"):</label></td>"+
				"<td class=\"alr\"><input type='text' id='restrictedDL3' class='TextboxNum' maxlength='6' disabled=1/></td>"+
			"</tr>"+
		"</table>"+
	   "</fieldset>";
        dlg.innerHTML = s+"</div>";
	plugin.attachPageToOptions(dlg,WUILang.scheduler);
	utWebUI.allSchedulerStuffLoaded = true;
}

utWebUI.showSchedulerError = function(err)
{
	if(utWebUI.allSchedulerStuffLoaded)
		log(err);
	else
		setTimeout('utWebUI.showSchedulerError('+err+')',1000);
}