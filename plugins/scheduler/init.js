plugin.loadLang();

if(plugin.canChangeMenu() && (theWebUI.systemInfo.rTorrent.iVersion >= 0x805))
{
	plugin.config = theWebUI.config;
	theWebUI.config = function()
	{
		plugin.config.call(this);
		plugin.reqId = theRequestManager.addRequest("trt", theRequestManager.map("d.get_custom=")+"sch_ignore",function(hash,torrent,value)
		{
			torrent.sch_ignore = iv(value);
		});
	}

	theWebUI.toggleSchIgnore = function()
	{
		var h = "";
		var sr = theWebUI.getTable("trt").rowSel;
		for(var k in sr) 
			if((sr[k] == true) && (k.length==40))
			{
				var state = theWebUI.torrents[k].sch_ignore ? '' : 1;
				h += "&hash="+k+"&s="+state;
			}
		theWebUI.request("?action=schignore" + h + "&list=1");
	}

	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled && !theContextMenu.get(theUILang.rssMenuLoad))
		{
			var table = this.getTable("trt");
			if(table.selCount == 1)
			{
				var hash = table.getFirstSelected();
				if(hash.length==40)
				{
					if(this.torrents[hash].sch_ignore)
						theContextMenu.add( [CMENU_SEL, theUILang.shcIgnore, theWebUI.toggleSchIgnore] );
					else
						theContextMenu.add( [theUILang.shcIgnore, theWebUI.toggleSchIgnore] );
				}
				else
					theContextMenu.add( [theUILang.shcIgnore, null] );
			}
			else
				theContextMenu.add( [theUILang.shcIgnore, theWebUI.toggleSchIgnore] );
		}
	}

	rTorrentStub.prototype.schignore = function()
	{
		for(var i=0; i<this.hashes.length; i++)
		{
			var needRestart = (theWebUI.torrents[this.hashes[i]].status==theUILang.Seeding) || (theWebUI.torrents[this.hashes[i]].status==theUILang.Downloading);
			if(needRestart)
			{
				var cmd = new rXMLRPCCommand('d.stop');
				cmd.addParameter("string",this.hashes[i]);
				this.commands.push( cmd );
			}
			cmd = new rXMLRPCCommand('d.set_throttle_name');
			cmd.addParameter("string",this.hashes[i]);
			cmd.addParameter("string",this.ss[i]=='' ? "" : "NULL");
			this.commands.push( cmd );
			if(needRestart)
			{
				cmd = new rXMLRPCCommand('d.start');
				cmd.addParameter("string",this.hashes[i]);
				this.commands.push( cmd );
			}
			cmd = new rXMLRPCCommand( "d.set_custom" );
			cmd.addParameter("string",this.hashes[i]);
			cmd.addParameter("string","sch_ignore");
			cmd.addParameter("string",this.ss[i]);
			this.commands.push( cmd );
		}
	}
}

if(plugin.canChangeOptions())
{
	plugin.loadMainCSS();

	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) 
	{
		if(plugin.enabled)
		{
			var tbl = $$('sch_graph');
			for(var i=0; i<7; i++)
			{
				for(var j=1; j<25; j++)
				{
					cell = tbl.rows[i].cells[j];
					cell.setAttribute("clr",theWebUI.scheduleTable.week[i][j-1]);
				}
			}
			$$('sch_enable').checked = theWebUI.scheduleTable.enabled;
			for(var i=0; i<3; i++)
			{
				$$('restrictedUL'+(i+1)).value = theWebUI.scheduleTable.UL[i];
				$$('restrictedDL'+(i+1)).value = theWebUI.scheduleTable.DL[i];
			}
			theWebUI.linkedSch($$('sch_enable'), ['restrictedUL1', 'restrictedDL1', 'restrictedUL2', 'restrictedDL2', 'restrictedUL3', 'restrictedDL3']);
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.schedulerWasChanged = function() 
	{
		if($$('sch_enable').checked != theWebUI.scheduleTable.enabled)
			return(true);
		for(var i=0; i<3; i++)
			if(($$('restrictedUL'+(i+1)).value!=theWebUI.scheduleTable.UL[i]) ||
				($$('restrictedDL'+(i+1)).value!=theWebUI.scheduleTable.DL[i]))
					return(true);
		var tbl = $$('sch_graph');
		for(var i=0; i<7; i++)
			for(var j=1; j<25; j++)
				if(tbl.rows[i].cells[j].getAttribute("clr")!=theWebUI.scheduleTable.week[i][j-1])
					return(true);
		return(false);
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() 
	{
		plugin.setSettings.call(this);
		if(plugin.enabled && this.schedulerWasChanged())
			this.request("?action=setschedule");
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
		this.content += ('&enabled='+($$('sch_enable').checked ? '1' : '0'));
	        this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/scheduler/action.php";
		this.dataType = "script";
	}

	var schClasses = [ "sch_fast", "sch_stop", "sch_seed", "sch_res1", "sch_res2", "sch_res3" ];

	theWebUI.schMouseOver = function(i,j)
	{
		var from = (j < 10) ? ("0" + j) : j;
		$('#sch_desc').text( theUILang.schFullWeek[i]+', '+from+':00 - '+from+':59' );
	}

	theWebUI.schLegendMouseOver = function(i)
	{
		var schDesc = [ theUILang.schUnlimitedDesc, theUILang.schTurnOffDesc, theUILang.schSeedingOnlyDesc, theUILang.schLimitedDesc+"1", theUILang.schLimitedDesc+"2", theUILang.schLimitedDesc+"3" ];
		$('#sch_desc').text( schDesc[i] );
	}

	theWebUI.schMouseOut = function()
	{
		$('#sch_desc').html('&nbsp;');
	}

	theWebUI.schClick = function(obj,i,j)
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

	theWebUI.linkedSch = function(obj, lst) 
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
		isChecked ? $("#sch_desc").removeClass("disabled") : $("#sch_desc").addClass("disabled");
	}
}

plugin.onLangLoaded = function() {
	if (this.canChangeOptions()) {
		const s = $("<div>").attr({id:"st_scheduler"}).append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.schedulerGraph),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-12").append(
						$("<input>").attr({
							type: "checkbox",
							id: "sch_enable",
							onchange:"theWebUI.linkedSch(this, ['restrictedUL1', 'restrictedDL1', 'restrictedUL2', 'restrictedDL2', 'restrictedUL3', 'restrictedDL3']);",
						}),
						$("<label>").attr({for:"sch_enable"}).text(theUILang.schedulerOn),
					),
					$("<div>").addClass("col-12 overflow-x-auto").append(
						$("<table>").attr({id:"sch_graph"}).append(
							...Array.from(Array(7).keys()).map(week => $("<tr>").append(
								$("<td>").addClass("sch_week disabled").text(theUILang.schShortWeek[week]),
								...Array.from(Array(24).keys()).map(hour => {
									const day = theWebUI.scheduleTable.week[week][hour];
									return $("<td>").attr({
										clr: day,
										onmouseover: `theWebUI.schMouseOver("${week}","${hour}")`,
										onmouseout: "theWebUI.schMouseOut();",
										onclick: `theWebUI.schClick(this, "${week}","${hour}");`,
									}).addClass(schClasses[day] + "dis");
								}),
							)),
						),
					),
					$("<div>").addClass("col-12").append(
						$("<div>").attr({id:"sch_desc"}).addClass("p-2 flex-grow-1 disabled").html("&nbsp;"),
					),
					$("<div>").addClass("col-12").append(
						$("<table>").attr({id:"sch_legend"}).html(
							"<tr>"+
								"<td clr='0' class='sch_fastdis' onmouseover='theWebUI.schLegendMouseOver(0);' onmouseout='theWebUI.schMouseOut();'></td><td class='disabled'>"+theUILang.schUnlimited+"</td>"+
								"<td clr='1' class='sch_stopdis' onmouseover='theWebUI.schLegendMouseOver(1);' onmouseout='theWebUI.schMouseOut();'></td><td class='disabled'>"+theUILang.schTurnOff+"</td>"+
								"<td clr='2' class='sch_seeddis' onmouseover='theWebUI.schLegendMouseOver(2);' onmouseout='theWebUI.schMouseOut();'></td><td class='disabled'>"+theUILang.schSeedingOnly+"</td>"+
							"</tr>"+
							"<tr>"+
								"<td clr='3' class='sch_res1dis' onmouseover='theWebUI.schLegendMouseOver(3);' onmouseout='theWebUI.schMouseOut();'></td><td class='disabled'>"+theUILang.schLimited+"1</td>"+
								"<td clr='4' class='sch_res2dis' onmouseover='theWebUI.schLegendMouseOver(4);' onmouseout='theWebUI.schMouseOut();'></td><td class='disabled'>"+theUILang.schLimited+"2</td>"+
								"<td clr='5' class='sch_res3dis' onmouseover='theWebUI.schLegendMouseOver(5);' onmouseout='theWebUI.schMouseOut();'></td><td class='disabled'>"+theUILang.schLimited+"3</td>"+
							"</tr>"
						),
					),
				),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.schLimited + " 1"),
				$("<div>").addClass("row").append(
					...[
						["restrictedUL1", theUILang.schLimitedUL],
						["restrictedDL1", theUILang.schLimitedDL],
					].flatMap(([id, text]) => [
						$("<div>").addClass("col-12 col-md-4").append(
							$("<label>").attr({id:`lbl_${id}`, for:id}).addClass("disabled").text(text + " ("+theUILang.KB + "/" + theUILang.s+"):"),
						),
						$("<div>").addClass("col-12 col-md-8").append(
							$("<input>").attr({type:"text", id:id, maxlength:6}).addClass("TextboxNum").prop("disabled", true),
						),
					]),
				),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.schLimited + " 2"),
				$("<div>").addClass("row").append(
					...[
						["restrictedUL2", theUILang.schLimitedUL],
						["restrictedDL2", theUILang.schLimitedDL],
					].flatMap(([id, text]) => [
						$("<div>").addClass("col-12 col-md-4").append(
							$("<label>").attr({id:`lbl_${id}`, for:id}).addClass("disabled").text(text + " ("+theUILang.KB + "/" + theUILang.s+"):"),
						),
						$("<div>").addClass("col-12 col-md-8").append(
							$("<input>").attr({type:"text", id:id, maxlength:6}).addClass("TextboxNum").prop("disabled", true),
						),
					]),
				),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.schLimited + " 3"),
				$("<div>").addClass("row").append(
					...[
						["restrictedUL3", theUILang.schLimitedUL],
						["restrictedDL3", theUILang.schLimitedDL],
					].flatMap(([id, text]) => [
						$("<div>").addClass("col-12 col-md-4").append(
							$("<label>").attr({id:`lbl_${id}`, for:id}).addClass("disabled").text(text + " ("+theUILang.KB + "/" + theUILang.s+"):"),
						),
						$("<div>").addClass("col-12 col-md-8").append(
							$("<input>").attr({type:"text", id:id, maxlength:6}).addClass("TextboxNum").prop("disabled", true),
						),
					]),
				),
			),
		);
		this.attachPageToOptions(
			s[0],
			theUILang.scheduler,
		);
	}
}

plugin.onRemove = function() 
{
	plugin.removePageFromOptions("st_scheduler");
	if($type(plugin.reqId))
		theRequestManager.removeRequest( "trt", plugin.reqId );
}
