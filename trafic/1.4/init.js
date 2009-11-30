var plugin = new rPlugin("trafic");
tdTabs.oldOnShow = tdTabs.onShow;
utWebUI.oldResizeBottomBar = utWebUI.resizeBottomBar;
utWebUI.allTrafStuffLoaded = false;

function rTraficGraph()
{
}

var previousPoint = null;

rTraficGraph.prototype.create = function( aOwner )
{
	this.owner = aOwner;
	this.down = { label: WUILang.DL, bars: {"show": "true"}, data: [], color: "#1C8DFF" };
	this.up = { label: WUILang.UL, bars: {"show": "true"}, data: [], color: "#009900" };

	this.oldDown = { label: WUILang.DL, bars: {"show": "true"}, data: [], color: "#0849BB" };
	this.oldUp = { label: WUILang.UL, bars: {"show": "true"}, data: [], color: "#005500" };

	this.ticks = new Array();
}

var previousPoint = null;

rTraficGraph.prototype.draw = function()
{
	var self = this;
	$(function() 
	{
		if($("#"+self.owner.id).height() && $("#"+self.owner.id).width())
		{
			clearCanvas( self.owner );
			$.plot($("#"+self.owner.id), [ self.down, self.up, self.oldDown, self.oldUp ],
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
	  				tickFormatter: function(n) { return(ffs(n)) } 
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

			$("#"+self.owner.id).bind("plothover", 
				function (event, pos, item) 
				{ 
					if(item)
					{
						if(previousPoint != item.datapoint)
						{
							previousPoint = item.datapoint;
							$("#tooltip").remove();
							var y = item.datapoint[1];
							showTooltip(item.pageX, item.pageY,
								item.series.label + " = " + ffs(y));
						}
					}
					else
					{
						$("#tooltip").remove();
						previousPoint = null;
					}
				}
			);
		}
	});
}

rTraficGraph.prototype.resize = function( newWidth, newHeight )
{
	if(newWidth)
		this.owner.style.width = newWidth+"px";
	if(newHeight)
	{
		newHeight-=parseInt($$(this.owner.id+'_ctrl').style.height);
		this.owner.style.height = newHeight+"px";
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
			var dt = new Date(arr.labels[i]*1000-utWebUI.deltaTime);
			var month = dt.getMonth()+1;
			month = (month < 10) ? ("0" + month) : month;
			var day = dt.getDate();
			day = (day < 10) ? ("0" + day) : day;
			var h = dt.getHours();
			h = (h < 10) ? ("0" + h) : h;
			var now = new Date();
			now.setTime(now.getTime()-utWebUI.deltaTime);
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

utWebUI.initCreateTrafic = function()
{
 	var logTab = $$("lcont");
	var trafTab = document.createElement("DIV");
	trafTab.id = "traf";
	trafTab.className = "tab";
	trafTab.innerHTML = "<div id='traf_graph_ctrl' align=right style='height:40px'>"+
		"<input type='button' value='"+WUILang.ClearButton+"' class='Button' onclick='utWebUI.clearStats();return(false);'>"+
		"<select name='tracker_mode' id='tracker_mode' onchange='utWebUI.reqForTraficGraph()'>"+
		"<option value='global'>"+WUILang.allTrackers+"</option>"+
		"</select>"+
		"<select name='traf_mode' id='traf_mode' onchange='utWebUI.reqForTraficGraph()'>"+
		"<option value='day'>"+WUILang.perDay+"</option>"+
		"<option value='month'>"+WUILang.perMonth+"</option>"+
		"<option value='year'>"+WUILang.perYear+"</option>"+
		"</select>"+
		"</form></div><div id='traf_graph'></div>"
	logTab.parentNode.insertBefore(trafTab,logTab);
	tdTabs.tabs["traf"] = WUILang.traf; 

	var logLbl = $$("tab_lcont");
	var trafLbl = document.createElement("li");
	trafLbl.id = "tab_traf";
	trafLbl.innerHTML = "<a href=\"javascript://utwebui/\" onmousedown=\"javascript:tdTabs.show('traf');\" onfocus=\"javascript:this.blur();\">" + WUILang.traf + "</a>";
	logLbl.parentNode.insertBefore(trafLbl,logLbl);
	utWebUI.trafGraph = new rTraficGraph();
	utWebUI.trafGraph.create($$("traf_graph"));

	tdTabs.onShow = 
		function(id)
		{
			if(id=="traf")
			{
				if(utWebUI.activeView!="traf")
				{
					utWebUI.reqForTraficGraph();
				}
				else
				{
					utWebUI.trafGraph.resize();
				}
			}
			else
				this.oldOnShow(id);
		}
	resizeUI();
	utWebUI.allTrafStuffLoaded = true;
};

utWebUI.clearStats = function()
{
	if(utWebUI.bConfDel)
		 askYesNo( WUILang.ClearButton, WUILang.ClearQuest, "utWebUI.reqForTraficGraph(true)" );
	else
		utWebUI.reqForTraficGraph(true);
}

utWebUI.reqForTraficGraph = function(isClear)
{
	var sel = $$('traf_mode');
	var trk = $$('tracker_mode');
	if(sel)
	{
		var v = isClear ? "clear" : sel.options[sel.selectedIndex].value;
		var s = trk.options[trk.selectedIndex].value;
		this.Request("?action=gettrafic&v="+v+"&s="+s,[this.showTrafic, this]);
	}
}

utWebUI.resizeBottomBar = function(w,h) 
{
	if(this.trafGraph)
		this.trafGraph.resize(w,h);
	this.oldResizeBottomBar(w,h);
}

utWebUI.showTrafic = function(data)
{
	var d = eval("(" + data + ")");
	var trk = $$('tracker_mode');
	var s = trk.options[trk.selectedIndex].value;
	for(var i=trk.options.length-1;i>0;i--)
		trk.remove(i);
	for(var i=0; i<d.trackers.length; i++)
	{
		var elOptNew = document.createElement('option');
		elOptNew.text = d.trackers[i];
		elOptNew.value = d.trackers[i];
		if(s==elOptNew.value)
			elOptNew.selected = true;
		if(browser.isIE)
			trk.add(elOptNew);
		else
			trk.add(elOptNew,null);
	}
	var sel = $$('traf_mode');
	for(var i=0; i<sel.options.length; i++)
	{
		if(sel.options[i].value==d.mode)
		{
			sel.selectedIndex = i;
			break;
		}
	}
	this.trafGraph.setData(d);
}

rTorrentStub.prototype.gettrafic = function()
{
	this.content = "mode="+this.vs[0]+"&tracker="+this.ss[0];
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/trafic/getdata.php";
}

rTorrentStub.prototype.gettraficResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	return(datas[0].childNodes[0].data);
}

utWebUI.showTrafError = function(err)
{
	if(utWebUI.allTrafStuffLoaded)
		log(err);
	else
		setTimeout('utWebUI.showTrafError('+err+')',1000);
}

plugin.loadLanguages(utWebUI.initCreateTrafic);