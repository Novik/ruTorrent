function rTraficGraph()
{
}

rTraficGraph.prototype.create = function( aOwner )
{
	this.owner = aOwner;
	this.down = { label: WUILang.DL, bars: {"show": "true"}, data: [], color: "#1C8DFF" };
	this.up = { label: WUILang.UL, bars: {"show": "true"}, data: [], color: "#009900" };

	this.oldDown = { label: WUILang.DL, bars: {"show": "true"}, data: [], color: "#0849BB" };
	this.oldUp = { label: WUILang.UL, bars: {"show": "true"}, data: [], color: "#005500" };

	this.ticks = new Array();
	this.previousPoint = null;
}

rTraficGraph.prototype.draw = function()
{
	var self = this;
	$(function() 
	{
		if(self.owner.height() && self.owner.width())
		{
			clearCanvas( self.owner[0] );
			$.plot(self.owner, [ self.down, self.up, self.oldDown, self.oldUp ],
			{ 
				colors:
				[
				 	self.down.color, self.up.color, self.oldDown.color, self.oldUp.color
				],
				xaxis: 
				{ 
					ticks: self.ticks
			 	},
				grid:
				{
					hoverable: true
				},
			  	yaxis: 
			  	{ 
			  		min: 0,
	  				tickFormatter: function(n) { return(theConverter.bytes(n)) } 
		  		}
			});
			function showTooltip(x, y, contents)
			{
        			$('<div id="tooltip">' + contents + '</div>').css( {
					position: 'absolute',
					display: 'none',
					top: y + 5,
					left: x + 5,
					border: '1px solid #fdd',
					padding: '2px',
					'background-color': '#fee',
					'color': 'black',
					'font-size': '11px',
					'font-weight': 'bold',
					'font-family': 'Tahoma, Verdana, Arial, Helvetica, sans-serif',
					opacity: 0.80
				}).appendTo("body").fadeIn(200);
			}

			self.owner.bind("plothover", 
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
		}
	});
}

rTraficGraph.prototype.resize = function( newWidth, newHeight )
{
	if(newWidth)
		this.owner.width(newWidth-8);
	if(newHeight)
	{
		newHeight-=(iv($$(this.owner.attr("id")+'_ctrl').style.height)+$("#tabbar").height()+11);
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
			var dt = new Date(arr.labels[i]*1000-theWebUI.deltaTime);
			var month = dt.getMonth()+1;
			month = (month < 10) ? ("0" + month) : month;
			var day = dt.getDate();
			day = (day < 10) ? ("0" + day) : day;
			var h = dt.getHours();
			h = (h < 10) ? ("0" + h) : h;
			var now = new Date();
			now.setTime(now.getTime()-theWebUI.deltaTime);
			var actualData = true;
			
			if(arr.mode=='day')
			{
				this.ticks.push([i,h+":00"]);
				actualData = (now.getDate()==dt.getDate());
			}
			else
			if(arr.mode=='month')
			{
				this.ticks.push([i+0.5,day+"."+month]);
				actualData = (now.getMonth()==dt.getMonth());
			}
			else
			if(arr.mode=='year')
			{
				this.ticks.push([i+0.5,month+"."+dt.getFullYear()]);
				actualData = (now.getFullYear()==dt.getFullYear());
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

plugin.onLangLoaded = function()
{
 	this.attachPageToTabs(
		$('<div>').attr("id","traf").html(
			"<div id='traf_graph_ctrl' align=right style='height:30px;'>"+
				"<input type='button' value='"+WUILang.ClearButton+"' class='Button' onclick='theWebUI.clearStats();return(false);'>"+
				"<select name='tracker_mode' id='tracker_mode' onchange='theWebUI.reqForTraficGraph()'>"+
					"<option value='global'>"+WUILang.allTrackers+"</option>"+
				"</select>"+
				"<select name='traf_mode' id='traf_mode' onchange='theWebUI.reqForTraficGraph()'>"+
					"<option value='day'>"+WUILang.perDay+"</option>"+
					"<option value='month'>"+WUILang.perMonth+"</option>"+
					"<option value='year'>"+WUILang.perYear+"</option>"+
				"</select>"+
			"</div><div id='traf_graph'></div>").get(0),WUILang.traf,"lcont");
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
};

theWebUI.clearStats = function()
{
	if(theWebUI.settings["webui.confirm_when_deleting"])
		 askYesNo( WUILang.ClearButton, WUILang.ClearQuest, "theWebUI.reqForTraficGraph(true)" );
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
	if(this.trafGraph)
		this.trafGraph.resize(w,h);
	plugin.resizeBottom.call(this,w,h);
}

theWebUI.showTrafic = function(d)
{
	var s = $('#tracker_mode').val();
	$('#tracker_mode option').remove();	
	$('#tracker_mode').append("<option value='global'>"+WUILang.allTrackers+"</option>");
	for(var i=0; i<d.trackers.length; i++)
		$('#tracker_mode').append("<option value='"+d.trackers[i]+"'>"+d.trackers[i]+"</option>");
	$('#tracker_mode').val(s);
	$('#traf_mode').val(d.mode);
	this.trafGraph.setData(d);
}

rTorrentStub.prototype.gettrafic = function()
{
	this.content = "mode="+this.vs[0]+"&tracker="+this.ss[0];
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/trafic/getdata.php";
	this.dataType = "json";
}

plugin.loadLang(true);