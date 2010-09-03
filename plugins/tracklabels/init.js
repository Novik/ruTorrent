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
	if(plugin.enabled && theWebUI.actTrackersLbl)
		theWebUI.filterByTracker(hash);
	else
		plugin.filterByLabel.call(theWebUI,hash);
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
				if(tracker && (tracker==theWebUI.actTrackersLbl))
				{
					ret = true;
					break;
				}
			}
		}
	}
	return(ret);
}

theWebUI.filterByTracker = function(hash)
{
        if(theWebUI.isTrackerInActualLabel(hash))
		this.getTable("trt").unhideRow(hash);
	else
		this.getTable("trt").hideRow(hash);
}

plugin.switchRSSLabel = theWebUI.switchRSSLabel;
theWebUI.switchRSSLabel = function(el,force)
{
	if(plugin.enabled && theWebUI.actTrackersLbl)
	{
		$$(theWebUI.actTrackersLbl).className = "cat";
		theWebUI.actTrackersLbl = null;
	}
	plugin.switchRSSLabel.call(theWebUI,el,force);
}

plugin.switchLabel = theWebUI.switchLabel;
theWebUI.switchLabel = function(el)
{
	if(plugin.enabled && theWebUI.actTrackersLbl)
	{
		$($$(theWebUI.actTrackersLbl)).removeClass("sel");
		theWebUI.actTrackersLbl = null;
	}
	plugin.switchLabel.call(theWebUI,el);
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

theWebUI.switchTrackersLabel = function(el,force)
{
	if((el.id == this.actTrackersLbl) && !force)
		return;
        var table = this.getTable("trt");
        var lst = $("#RSSList");
	if(lst.length && lst.is(":visible"))
	{
		table.clearSelection();
		this.dID = "";
		this.clearDetails();
		this.getTable("rss").clearSelection();
		if(this.actRSSLbl)
			$$(theWebUI.actRSSLbl).className = theWebUI.isActiveRSSEnabled() ? "RSS cat" : "disRSS cat";
		this.actRSSLbl = null;
		$("#List").show();
		lst.hide();
		this.switchLayout(false);
	}
	if(this.actTrackersLbl)
		$($$(this.actTrackersLbl)).removeClass("sel");
	this.actTrackersLbl = el.id;
	$(el).addClass("sel");
	if((this.actLbl != "") && ($$(this.actLbl) != null))
		$($$(this.actLbl)).removeClass("sel");
	this.actLbl = "";
	table.scrollTo(0);
	for(var hash in this.torrents)
	        this.filterByTracker(hash);
	if(!force)
	{
		table.clearSelection();
		if(this.dID != "")
		{       	
			this.dID = "";
			this.clearDetails();
		}
	}
	table.refreshRows();
}

theWebUI.trackersLabelContextMenu = function(e)
{
        if(e.button==2)
        {
	        var table = theWebUI.getTable("trt");
		table.clearSelection();
		theWebUI.switchTrackersLabel(this);
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
		theWebUI.switchTrackersLabel(this);
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
		trackersLabels = new Object();
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
				li = $($$(lbl));
	                	li.children("span").text(trackersLabels[lbl]);
			}
			else
			{
			        li = $('<li>').attr("id",lbl).
			        	html(escapeHTML(lbl)+'&nbsp;(<span id="'+lbl+'_c">'+trackersLabels[lbl]+'</span>)').
			        	mouseclick(theWebUI.trackersLabelContextMenu)
				var rule = getCSSRule("#"+lbl);
				if(!rule)
					li.prepend( $("<img>").attr("src","http://"+lbl+"/favicon.ico").width(16).height(16).css({ "margin-right": 5, "float" : "left" }) ).css({ padding: "2px 4px"});
				li.addClass("cat").attr("title",lbl+" ("+trackersLabels[lbl]+")");
				ul.append(li);
			}
			if(lbl==theWebUI.actTrackersLbl)
				li.addClass("sel");
		}
		var needSwitch = false;
		for(var lbl in this.trackersLabels)
			if(!(lbl in trackersLabels))
			{
				$($$(lbl)).remove();
				if(theWebUI.actTrackersLbl == lbl)
				{
					needSwitch = true;
					theWebUI.actTrackersLbl = null;
				}       	
			}
		this.trackersLabels = trackersLabels;
		if(needSwitch)
			theWebUI.switchLabel($$("-_-_-all-_-_-"));
		else
		if(theWebUI.actTrackersLbl)
			theWebUI.switchTrackersLabel($$(theWebUI.actTrackersLbl),true);
	}
}

theWebUI.initTrackersLabels = function()
{
	if( thePlugins.isInstalled("rss") &&
		!thePlugins.get("rss").allStuffLoaded)
		setTimeout('theWebUI.initTrackersLabels()',1000);
	else
	{
		var el = $$('CatList');
		var lbl = $$('lbll').parentNode.nextSibling;
		var div = document.createElement('DIV');
		var ul = document.createElement('UL');
		div.id = "ptrackers_cont";
		if($$("pstate"))
		{
		        var pnl = document.createElement('DIV');
		        pnl.className = "catpanel";
	        	pnl.id = "ptrackers";
			pnl.innerHTML = theUILang.Trackers;
			pnl.onclick = function() { theWebUI.togglePanel(pnl); };
			el.insertBefore(ul,lbl);
			el.insertBefore(pnl,ul);
		}
		else
		{
			ul.innerHTML = '<li id="_hr_"><hr /></li>';
  			el.insertBefore(ul,lbl);
		}
		div.innerHTML = '<ul id="torrl"></ul>';
  		el.insertBefore(div,ul.nextSibling);
	        plugin.markLoaded();
	}
};

plugin.onRemove = function()
{
	$('#ptrackers_cont').remove();
	$('#ptrackers').remove();
	theWebUI.switchLabel($$("-_-_-all-_-_-"));
	if(plugin.canChangeColumns())
	{
		theWebUI.getTable("trt").removeColumnById("tracker");
		if(thePlugins.isInstalled("rss"))
			theWebUI.getTable("rss").removeColumnById("tracker");
	}
}

theWebUI.initTrackersLabels();