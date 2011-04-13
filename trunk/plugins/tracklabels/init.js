plugin.loadMainCSS();

theWebUI.trackersLabels = new Object();
theWebUI.actTrackersLbl = null;

plugin.config = theWebUI.config;
theWebUI.config = function(data)
{
	if(plugin.canChangeColumns())
	{
		theWebUI.tables.trt.columns.push({ text: theUILang.Tracker, width: '100px', id: 'tracker', type: TYPE_STRING});
		plugin.config.call(this,data);
	}
}

plugin.filterByLabel = theWebUI.filterByLabel;
theWebUI.filterByLabel = function(hash)
{
	if(plugin.enabled && theWebUI.actLbl && $($$(theWebUI.actLbl)).hasClass('tracker'))
		theWebUI.filterByTracker(hash);
	else
		plugin.filterByLabel.call(theWebUI,hash);
}

theWebUI.filterByTracker = function(hash)
{
        if(theWebUI.isTrackerInActualLabel(hash))
		this.getTable("trt").unhideRow(hash);
	else
		this.getTable("trt").hideRow(hash);
}

theWebUI.isTrackerInActualLabel = function(hash)
{
        var ret = false;
	if($type(this.torrents[hash]) && $type(this.trackers) && $type(this.trackers[hash]))
	{
		for( var i=0; i<this.trackers[hash].length; i++)
		{
			if(this.trackers[hash][i].group==0)
			{
				var tracker = theWebUI.getTrackerName( this.trackers[hash][i].name );
				if(tracker && (('i'+tracker)==theWebUI.actLbl))
				{
					ret = true;
					break;
				}
			}
		}
	}
	return(ret);
}

plugin.switchLabel = theWebUI.switchLabel;
theWebUI.switchLabel = function(el)
{
	if(plugin.enabled && theWebUI.actTrackersLbl && !theWebUI.actLbl)
		theWebUI.actLbl = theWebUI.actTrackersLbl;
	plugin.switchLabel.call(theWebUI,el);
	if(plugin.enabled && theWebUI.actLbl && $($$(theWebUI.actLbl)).hasClass('tracker'))
	{
		theWebUI.actTrackersLbl = theWebUI.actLbl;
		theWebUI.actLbl = null;
	}
	else
		theWebUI.actTrackersLbl = null;
}

plugin.addTrackers = theWebUI.addTrackers;
theWebUI.addTrackers = function(data)
{
	plugin.addTrackers.call(theWebUI,data);
	if(plugin.enabled)
		theWebUI.rebuildTrackersLabels();
}

theWebUI.getTrackerName = function(announce)
{
        var domain = null;
	var parts = announce.match(/^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/);
	if(parts && (parts.length>6))
	{
		domain = parts[6];
		if(!domain.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/))
		{
			parts = domain.split(".");
			if(parts.length>2)
			{
				if($.inArray(parts[parts.length-2]+"", ["co", "com", "net", "org"])>=0 ||
					$.inArray(parts[parts.length-1]+"", ["uk"])>=0)
					parts = parts.slice(parts.length-3);
				else
					parts = parts.slice(parts.length-2);
				domain = parts.join(".");
			}
		}
	}
	return(domain);
}

theWebUI.trackersLabelContextMenu = function(e)
{
        if(e.button==2)
        {
	        var table = theWebUI.getTable("trt");
		table.clearSelection();
		theWebUI.switchLabel(this);
		table.fillSelection();
		var id = table.getFirstSelected();
		if(id && plugin.canChangeMenu())
		{
			theWebUI.createMenu(null, id);
			theContextMenu.show();
		}
		else
			theContextMenu.hide();
	}
	else
		theWebUI.switchLabel(this);
	return(false);
}

plugin.updateLabels = theWebUI.updateLabels;
theWebUI.updateLabels = function(wasRemoved)
{
	plugin.updateLabels.call(theWebUI,wasRemoved);
	if(plugin.enabled && wasRemoved)
		theWebUI.rebuildTrackersLabels();
}

theWebUI.rebuildTrackersLabels = function()
{
	if(!plugin.allStuffLoaded)
		setTimeout('theWebUI.rebuildTrackersLabels()',1000);
	else
	{
		var table = this.getTable('trt');
		var trackersLabels = new Object();
		var counted = new Object();
		for(var hash in this.trackers)
		{
			if($type(this.torrents[hash]))
			{
			        this.torrents[hash].tracker = null;
				counted[hash] = new Array();
				for( var i=0; i<this.trackers[hash].length; i++)
				{
					if(this.trackers[hash][i].group==0)
					{
						var tracker = theWebUI.getTrackerName( this.trackers[hash][i].name );
						if(tracker)
						{
							if(!this.torrents[hash].tracker)
							{
								this.torrents[hash].tracker = tracker;
								if(plugin.canChangeColumns())
									table.setValueById(hash, 'tracker', tracker);
							}
							if($.inArray(tracker, counted[hash]) == -1)
							{
								if($type(trackersLabels[tracker]))
									trackersLabels[tracker]++;
								else
									trackersLabels[tracker] = 1;
								counted[hash].push(tracker);
							}
						}
					}
				}
			}
		}
		if(plugin.canChangeColumns())
			table.Sort();
		var ul = $("#torrl");

		var keys = new Array();
		for(var lbl in trackersLabels)
			keys.push(lbl);
		keys.sort();

		for(var i=0; i<keys.length; i++) 
		{
			var lbl = keys[i];
			var li = null;
			if(lbl in this.trackersLabels)
			{
				li = $($$('i'+lbl));
	                	li.children("span").text(trackersLabels[lbl]);
			}
			else
			{
			        li = $('<li>').attr("id",'i'+lbl).
			        	html(escapeHTML(lbl)+'&nbsp;(<span id="-'+lbl+'_c">'+trackersLabels[lbl]+'</span>)').
			        	mouseclick(theWebUI.trackersLabelContextMenu).addClass("cat tracker").attr("title",lbl+" ("+trackersLabels[lbl]+")");
				var rule = getCSSRule("#i"+lbl);
				if(!rule)
					li.prepend( $("<img>").attr("src","http://"+lbl+"/favicon.ico").addClass("tfavicon") ).css({ padding: "2px 4px" });
				ul.append(li);
			}
			if(lbl==theWebUI.actTrackersLbl)
				li.addClass("sel");
		}
		var needSwitch = false;
		for(var lbl in this.trackersLabels)
			if(!(lbl in trackersLabels))
			{
				$($$('i'+lbl)).remove();
				if(theWebUI.actTrackersLbl == 'i'+lbl)
				{
					needSwitch = true;
					theWebUI.actTrackersLbl = null;
				}       	
			}
		this.trackersLabels = trackersLabels;
		if(needSwitch)
			theWebUI.switchLabel($$("-_-_-all-_-_-"));
	}
}

theWebUI.initTrackersLabels = function()
{
	plugin.addPaneToCategory("ptrackers",theUILang.Trackers).
		append($("<ul></ul>").attr("id","torrl"));
        plugin.markLoaded();
};

plugin.onRemove = function()
{
	plugin.removePaneFromCategory('ptrackers');
	theWebUI.switchLabel($$("-_-_-all-_-_-"));
	if(plugin.canChangeColumns())
	{
		theWebUI.getTable("trt").removeColumnById("tracker");
		if(thePlugins.isInstalled("rss"))
			theWebUI.getTable("rss").removeColumnById("tracker");
	}
}

theWebUI.initTrackersLabels();