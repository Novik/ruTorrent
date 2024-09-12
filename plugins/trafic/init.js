theWebUI.ratiosStat = {};

class rTraficGraph extends rGraph {
  create(aOwner, webuiView = "traf") {
    this.down = {
      label: "trafic_downloaded",
      labelTranslation: theUILang.Downloaded,
      lines: { show: false },
      bars: { show: true },
      data: [],
      color: "#1C8DFF",
    };
    this.up = {
      label: "trafic_uploaded",
      labelTranslation: theUILang.Uploaded,
      lines: { show: false },
      bars: { show: true },
      data: [],
      color: "#009900",
    };

    this.oldDown = {
      ...this.down,
      label: "trafic_downloaded_old",
      color: "#0849BB",
      data: [],
    };
    this.oldUp = {
      ...this.up,
      label: "trafic_uploaded_old",
      color: "#005500",
      data: [],
    };

    this.datasets = [this.down, this.up, this.oldDown, this.oldUp];

    this.xticks = [];

    super.create(aOwner, webuiView);
  }

  yTickFormatter(n) {
    return theConverter.bytes(n);
  }

  onHoverItemChanged(item) {
    // show tooltip when hovering over datapoint
    if (item)
      rGraph.showTooltip(
        item.pageX - 20,
        item.pageY - 20,
        (rGraph.legendLabelTranslations[item.series.label] ??
          item.series.label) +
        " = " +
        theConverter.bytes(item.datapoint[1])
      );
    else rGraph.hideTooltip();
  }

  get options() {
    const opts = super.options;
    return {
      ...opts,
      xaxis: {
        ...opts.xaxis,
        ticks: this.xticks,
      },
    };
  }

  setData(trafData) {
    this.down.data = [];
    this.up.data = [];
    this.oldDown.data = [];
    this.oldUp.data = [];
    this.xticks = [];
    trafData.labels.forEach((label, index) => {
      let xtick = "";
      const data = {};
      let actualDay = false;
      if (label != 0) {
        actualDay = trafData.mode === "day";

        // const dt = new Date(trafData.labels[i]*1000-theWebUI.serverDeltaTime);
        const dt = new Date(label * 1000);
        const now = new Date(new Date().getTime() - theWebUI.deltaTime);
        const [year, month, day, hours] = [
          dt.getFullYear(),
          dt.getMonth(),
          dt.getDate(),
          dt.getHours(),
        ];
        const [monthStr, dayStr, hourStr] = [month + 1, day, hours].map((num) =>
          String(num).padStart(2, "0")
        );
        xtick = {
          day: `${hourStr}:00`,
          month: `${dayStr}.${monthStr}`,
          year: `${monthStr}.${year}`,
        }[trafData.mode];
        const actual = {
          day: day === now.getDate(),
          month: month === now.getMonth(),
          year: year === now.getFullYear(),
        }[trafData.mode];

        data[actual ? "up" : "oldUp"] = trafData.up[index];
        data[actual ? "down" : "oldDown"] = trafData.down[index];
      }
      for (const prop of ["up", "down", "oldDown", "oldUp"]) {
        this[prop].data.push([index, data[prop] ?? null]);
      }
      this.xticks.push([index + (actualDay ? 0 : 0.5), xtick]);
    });
    this.draw();
  }

  resize(newWidth, newHeight) {
    if (newWidth) newWidth -= 8;
    if (this.plot && newHeight)
      newHeight -=
        iv($$(this.plot.getPlaceholder().attr("id") + "_ctrl").style.height) +
        $("#tabbar").outerHeight();
    super.resize(newWidth, newHeight);
  }
}

if(plugin.canChangeTabs())
{
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

	plugin.resizeLeft = theWebUI.resizeLeft;
	theWebUI.resizeLeft = function(w) {
		if (plugin.enabled) {
			if (plugin.allStuffLoaded) {
				const tdcont = $("#tdcont");
				this.trafGraph.resize(tdcont.width(), tdcont.height());
			} else
				setTimeout('theWebUI.resize()', 1000);
		}
		plugin.resizeLeft.call(this, w);
	}

	plugin.resizeTop = theWebUI.resizeTop;
	theWebUI.resizeTop = function(w, h) {
		if (plugin.enabled) {
			if (plugin.allStuffLoaded) {
				const tdcont = $("#tdcont");
				this.trafGraph.resize(tdcont.width(), tdcont.height());
			} else
				setTimeout('theWebUI.resize()', 1000);
		}
		plugin.resizeTop.call(this, w, h);
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
			$('#traf_graph').show();
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
		plugin.onShow = theTabs.onShow;
		theTabs.onShow = function(id)
		{
			if(id=="traf")
			{
				if(theWebUI.activeView!="traf" || !theWebUI.trafGraph.xticks.length)
					theWebUI.reqForTraficGraph();
				else
					theWebUI.trafGraph.resize();
			}
			else
				plugin.onShow.call(this,id);
		};
	 	this.attachPageToTabs(
			$('<div>').attr("id","traf").append(
				$("<div>").attr({id:"traf_graph_ctrl"}).addClass("graph_tab d-flex flex-row").append(
					plugin.disableClearButton ? $() : $("<button>").attr({type:"button", onclick: "theWebUI.clearStats();return(false);"}).text(theUILang.ClearButton),
					$("<select>").attr({
						name:"tracker_mode",
						id:"tracker_mode",
						onchange:"theWebUI.reqForTraficGraph()",
					}).addClass("ms-auto w-auto flex-grow-0").append(
						$("<option>").prop("selected", true).val("global").text(theUILang.allTrackers),
					),
					$("<select>").attr({
						name:"traf_mode",
						id:"traf_mode",
						onchange:"theWebUI.reqForTraficGraph()",
					}).addClass("w-auto flex-grow-0").append(
						$("<option>").val("day").text(theUILang.perDay),
						$("<option>").val("month").text(theUILang.perMonth),
						$("<option>").val("year").text(theUILang.perYear),
					),
				),
				$("<div>").attr({id:"traf_graph"}).addClass("graph_tab"),
			)[0],
			theUILang.traf,
			"lcont",
		);
		theWebUI.trafGraph = new rTraficGraph();
		theWebUI.trafGraph.create($("#traf_graph"));
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
