if(browser.isKonqueror && (browser.versionMajor<4))
	plugin.disable();

theWebUI.ratiosStat = {};

if(plugin.canChangeTabs())
{

	function rTraficGraph()
	{
	}

	rTraficGraph.prototype.create = function( aOwner )
	{
		this.owner = aOwner;
		this.owner.parent().css('overflow', 'hidden');
		this.down = { label: theUILang.Downloaded, bars: {"show": "true"}, data: [], color: "#1C8DFF" };
		this.up = { label: theUILang.Uploaded, bars: {"show": "true"}, data: [], color: "#009900" };

		this.oldDown = { label: theUILang.Downloaded, bars: {"show": "true"}, data: [], color: "#0849BB" };
		this.oldUp = { label: theUILang.Uploaded, bars: {"show": "true"}, data: [], color: "#005500" };

		this.ticks = new Array();
		this.previousPoint = null;

		this.checked = [ true, true, true, true ];
		this.datasets = [ this.down, this.up, this.oldDown, this.oldUp ];
	}

	rTraficGraph.prototype.getDataSets = function()
	{
		var ret = new Array();		
		for( var i in this.checked )
		{
			if(this.checked[i])
				ret.push(this.datasets[i]);
			else
			{
				var arr = cloneObject( this.datasets[i] );
				arr.data = [];
				ret.push(arr);
			}
		}
		return(ret);
	}

	rTraficGraph.prototype.draw = function()
	{
		var self = this;
		var gridSel = $('.graph_tab_grid');
		var legendSel = $('.graph_tab_legend');
		$(function() 
		{
			if(self.owner.height() && self.owner.width())
			{
				clearCanvas( self.owner[0] );
				self.owner.empty();

				$.plot(self.owner,  self.getDataSets(),
				{ 
					colors: [ self.down.color, self.up.color, self.oldDown.color, self.oldUp.color ],
					xaxis: 
					{ 
						ticks: self.ticks
				 	},
					grid:
					{
						color: gridSel.css('color'),
						backgroundColor: gridSel.css('background-color'),
						borderWidth: parseInt(gridSel.css('border-width')),
						borderColor: gridSel.css('border-color'),
						hoverable: true
					},
					legend : {
						color: legendSel.css('color'),
						borderColor: legendSel.css('border-color'),
						backgroundColor: legendSel.css('background-color'),
					},
				  	yaxis: 
				  	{ 
				  		min: 0,
	  					tickFormatter: function(n) { return(theConverter.bytes(n)) } 
		  			}
				});
				function showTooltip(x, y, contents)
				{
					$('<div>').attr('id', 'tooltip')
						.addClass('graph_tab_tooltip')
						.text(contents)
						.css( {
							display: 'none',
							top: y + 5,
							left: x + 5,
					}).appendTo("body").fadeIn(200);
				}

				self.owner.off("plothover");
				self.owner.on("plothover", 
					function (event, pos, item) 
					{ 
						if(item)
						{
							if(self.previousPoint != item.datapoint)
							{
								self.previousPoint = item.datapoint;
								$("#tooltip").remove();
								var y = item.datapoint[1];
								showTooltip(item.pageX, item.pageY,
									item.series.label + " = " + theConverter.bytes(y));
							}
						}
						else
						{
							$("#tooltip").remove();
							self.previousPoint = null;
						}
					}
				);

				$('#'+self.owner.attr('id')+' .legendColorBox').before("<td class='legendCheckBox'><input type='checkbox'></td>");
				$.each($('#'+self.owner.attr('id')+' .legendCheckBox input'),function(ndx,element)
				{
					$(element).on('click', function() 
					{
						self.checked[ndx] = !self.checked[ndx];
						self.draw();
					}).prop("checked",self.checked[ndx]);
				});
			}
		});
	}

	rTraficGraph.prototype.resize = function( newWidth, newHeight )
	{
		if(newWidth)
			this.owner.width(newWidth-8);
		if(newHeight)
		{
			newHeight-=(iv($$(this.owner.attr("id")+'_ctrl').style.height)+$("#tabbar").outerHeight());
			if(newHeight>0)
				this.owner.height(newHeight);
		}
		this.draw();
	}

	rTraficGraph.prototype.setData = function( arr )
	{
		this.down.data = new Array();
		this.up.data = new Array();
		this.oldDown.data = new Array();
		this.oldUp.data = new Array();
		this.ticks = new Array();
		for(var i=0; i<arr.up.length; i++)
		{
			if(arr.labels[i]!=0)
			{       	
//				var dt = new Date(arr.labels[i]*1000-theWebUI.serverDeltaTime);
				var dt = new Date(arr.labels[i]*1000);
				var month = dt.getMonth()+1;
				month = (month < 10) ? ("0" + month) : month;
				var day = dt.getDate();
				day = (day < 10) ? ("0" + day) : day;
				var h = dt.getHours();
				h = (h < 10) ? ("0" + h) : h;
				var now = new Date();
				now.setTime(now.getTime()-theWebUI.deltaTime);
				var actualData = true;
			
			        switch(arr.mode)
			        {
					case 'day':
						this.ticks.push([i,h+":00"]);
						actualData = (now.getDate()==dt.getDate());
						break;
					case 'month':
						this.ticks.push([i+0.5,day+"."+month]);
						actualData = (now.getMonth()==dt.getMonth());
						break;
					case 'year':
						this.ticks.push([i+0.5,month+"."+dt.getFullYear()]);
						actualData = (now.getFullYear()==dt.getFullYear());
						break;
				}

				if(actualData)
				{
					this.down.data.push([i,arr.down[i]]);
					this.up.data.push([i,arr.up[i]]);
					this.oldDown.data.push([i,null]);
					this.oldUp.data.push([i,null]);
				}
				else
				{
					this.oldDown.data.push([i,arr.down[i]]);
					this.oldUp.data.push([i,arr.up[i]]);
					this.down.data.push([i,null]);
					this.up.data.push([i,null]);
				}
			}
			else
			{
				this.down.data.push([i,null]);
				this.up.data.push([i,null]);
				this.oldDown.data.push([i,null]);
				this.oldUp.data.push([i,null]);
				this.ticks.push([i+0.5,""]);
			}
		}
		this.draw();
	}

	theWebUI.clearStats = function()
	{
		if(theWebUI.settings["webui.confirm_when_deleting"])
			askYesNo( theUILang.ClearButton, theUILang.ClearQuest, "theWebUI.reqForTraficGraph(true)" );
		else
			theWebUI.reqForTraficGraph(true);
	}

	theWebUI.reqForTraficGraph = function(isClear)
	{
		var sel = $('#traf_mode');
		if(sel.length)
		{
			var v = isClear ? "clear" : sel.val();
			this.request("?action=gettrafic&v="+v+"&s="+$('#tracker_mode').val(),[this.showTrafic, this]);
		}
	}

	plugin.resizeBottom = theWebUI.resizeBottom;
	theWebUI.resizeBottom = function( w, h )
	{
		if(plugin.enabled) 
		{
		        if(plugin.allStuffLoaded)
				this.trafGraph.resize(w,h);
			else
				setTimeout( 'theWebUI.resize()', 1000 );
		}
		plugin.resizeBottom.call(this,w,h);
	}

	theWebUI.showTrafic = function(d)
	{
		if( $type(d) )
		{
			var s = $('#tracker_mode').val();
			$('#tracker_mode option').remove();
			var tMode = plugin.collectStatForTorrents ? "<option value='none'>"+theUILang.selectedTorrent+"</option>" : "";
			$('#tracker_mode').append(tMode+"<option value='global' selected>"+theUILang.allTrackers+"</option>");
			for(var i=0; i<d.trackers.length; i++)
				$('#tracker_mode').append("<option value='"+d.trackers[i]+"'>"+d.trackers[i]+"</option>");
			$('#tracker_mode').val(s);
			if(s!=$('#tracker_mode').val())
				$('#tracker_mode').val('global');
			$('#traf_mode').val(d.mode);
			this.trafGraph.setData(d);
		}			
	}

	rTorrentStub.prototype.gettrafic = function()
	{
		this.content = "mode="+this.vs[0]+"&tracker="+this.ss[0]+theWebUI.getHashes('');
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/trafic/getdata.php";
		this.dataType = "json";
	}

	if(plugin.collectStatForTorrents)
	{
		plugin.trtSelect = theWebUI.trtSelect;
		theWebUI.trtSelect = function(e, id) 
		{
			plugin.trtSelect.call(this,e,id);
			if( (this.activeView == 'traf') && ($('#tracker_mode').val()=='none'))
				theWebUI.reqForTraficGraph();
	   	}
	}
}

if(plugin.canChangeColumns() && plugin.collectStatForTorrents)
{
	plugin.ratioChanged = false;	
	plugin.config = theWebUI.config;
	theWebUI.config = function()
	{
		this.tables.trt.columns.push({ text: 'Ratio/day', width: '75x', id: "ratioday", type: TYPE_NUMBER});
		this.tables.trt.columns.push({ text: 'Ratio/week', width: '75px', id: "ratioweek", type: TYPE_NUMBER});
		this.tables.trt.columns.push({ text: 'Ratio/month', width: '75px', id: "ratiomonth", type: TYPE_NUMBER});
		plugin.trtFormat = this.tables.trt.format;
		this.tables.trt.format = function(table,arr)
		{
			for(var i in arr)
			{
			        var s = table.getIdByCol(i);
				if((s=="ratioday") || (s=="ratiomonth") || (s=="ratioweek"))
					arr[i] = (arr[i]!=null) ? theConverter.round(arr[i], 3) : "";
		        }
			return(plugin.trtFormat(table,arr));
		}
		plugin.config.call(this);
		plugin.trtRenameColumn();
	}

	plugin.trtRenameColumn = function()
	{
		if(plugin.allStuffLoaded)
		{
			theWebUI.getTable("trt").renameColumnById("ratioday",theUILang.ratioDay);
			theWebUI.getTable("trt").renameColumnById("ratioweek",theUILang.ratioWeek);
			theWebUI.getTable("trt").renameColumnById("ratiomonth",theUILang.ratioMonth);
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
		{
			theWebUI.getTable("rss").renameColumnById("ratioday",theUILang.ratioDay);
			theWebUI.getTable("rss").renameColumnById("ratioweek",theUILang.ratioWeek);
			theWebUI.getTable("rss").renameColumnById("ratiomonth",theUILang.ratioMonth);
		}
		else
			setTimeout(arguments.callee,1000);
	}

	plugin.tegRenameColumn = function()
	{
		if(theWebUI.getTable("teg").created)
		{
			theWebUI.getTable("teg").renameColumnById("ratioday",theUILang.ratioDay);
			theWebUI.getTable("teg").renameColumnById("ratioweek",theUILang.ratioWeek);
			theWebUI.getTable("teg").renameColumnById("ratiomonth",theUILang.ratioMonth);
		}
		else
			setTimeout(arguments.callee,1000);
	}

	plugin.startRatios = function()
	{
		theWebUI.request("?action=getratios",[plugin.updateRatios, this]);	
	}

	plugin.updateRatios = function( d )
	{
		plugin.ratioChanged = true;
		window.setTimeout( plugin.startRatios, plugin.updateInterval*60000 );
	}
	
	plugin.addTorrents = theWebUI.addTorrents;
	theWebUI.addTorrents = function(data)
	{
		if(plugin.ratioChanged)
		{
			$.each(data.torrents, function(hash,torrent)
			{
				if($type(theWebUI.ratiosStat[hash]) && torrent.size)
				{
					torrent.ratioday = theWebUI.ratiosStat[hash][0]/torrent.size;
					torrent.ratioweek = theWebUI.ratiosStat[hash][1]/torrent.size;
					torrent.ratiomonth = theWebUI.ratiosStat[hash][2]/torrent.size;
				}
			});
			plugin.addTorrents.call(this, data);
			plugin.ratioChanged = false;
		}
		else
			plugin.addTorrents.call(this, data);
	}

	rTorrentStub.prototype.getratios = function()
	{
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/trafic/action.php";
		this.dataType = "script";
	}

	plugin.startRatios();
}

plugin.onLangLoaded = function()
{
	if(this.canChangeTabs())
	{
	 	this.attachPageToTabs(
			$('<div>').attr("id","traf").html(
				"<div id='traf_graph_ctrl' class='graph_tab' align=right style='height:30px;'>"+
					(plugin.disableClearButton ? "" : "<input type='button' value='"+theUILang.ClearButton+"' class='Button' onclick='theWebUI.clearStats();return(false);'>")+
					"<select name='tracker_mode' id='tracker_mode' onchange='theWebUI.reqForTraficGraph()'>"+
						"<option value='global' selected>"+theUILang.allTrackers+"</option>"+
					"</select>"+
					"<select name='traf_mode' id='traf_mode' onchange='theWebUI.reqForTraficGraph()'>"+
						"<option value='day'>"+theUILang.perDay+"</option>"+
						"<option value='month'>"+theUILang.perMonth+"</option>"+
						"<option value='year'>"+theUILang.perYear+"</option>"+
					"</select>"+
				"</div><div id='traf_graph' class='graph_tab'></div>").get(0),theUILang.traf,"lcont");
		theWebUI.trafGraph = new rTraficGraph();
		theWebUI.trafGraph.create($("#traf_graph"));
		plugin.onShow = theTabs.onShow;
		theTabs.onShow = function(id)
		{
			if(id=="traf")
			{
				if(theWebUI.activeView!="traf")
					theWebUI.reqForTraficGraph();
				else
					theWebUI.trafGraph.resize();
			}
			else
				plugin.onShow.call(this,id);
		};
       		theWebUI.resize();
	}
};

plugin.onRemove = function()
{
	this.removePageFromTabs("traf");
	if(plugin.canChangeColumns() && plugin.collectStatForTorrents)
	{
		theRequestManager.removeRequest( "trt", plugin.reqId );
		theWebUI.getTable("trt").removeColumnById("ratioday");
		theWebUI.getTable("trt").removeColumnById("ratioweek");
		theWebUI.getTable("trt").removeColumnById("ratiomonth");

		if(thePlugins.isInstalled("rss"))
		{
			theWebUI.getTable("rss").removeColumnById("ratioday");
			theWebUI.getTable("rss").removeColumnById("ratioweek");
			theWebUI.getTable("rss").removeColumnById("ratiomonth");
		}		
	}
}

plugin.loadLang(true);
