utWebUI.trackersLabels = new Object();
utWebUI.actTrackersLbl = null;
utWebUI.allTrackersStuffLoaded = false;

utWebUI.trkFilterByLabel = utWebUI.filterByLabel;
utWebUI.filterByLabel = function(hash)
{
	if(utWebUI.actTrackersLbl)
		utWebUI.filterByTracker(hash);
	else
		utWebUI.trkFilterByLabel(hash);
}

utWebUI.isTrackerInActualLabel = function(hash)
{
        var ret = false;
	if((typeof (this.torrents[hash]) != "undefined"))
	{
		for( var i=0; i<this.trackers[hash].length; i++)
		{
			if(this.trackers[hash][i][3]==0)
			{
				var tracker = utWebUI.getTrackerName( this.trackers[hash][i][0] );
				if(tracker && (tracker==utWebUI.actTrackersLbl))
				{
					ret = true;
					break;
				}
			}
		}
	}
	return(ret);
}

utWebUI.filterByTracker = function(hash)
{
        if(utWebUI.isTrackerInActualLabel(hash))
		this.trtTable.unhideRow(hash);
	else
		this.trtTable.hideRow(hash);
}

utWebUI.trkSwitchRSSLabel = utWebUI.switchRSSLabel;
utWebUI.switchRSSLabel = function(el,force)
{
	if(utWebUI.actTrackersLbl)
	{
		$$(utWebUI.actTrackersLbl).className = "";
		utWebUI.actTrackersLbl = null;
	}
	utWebUI.trkSwitchRSSLabel(el,force);
}

utWebUI.trkSwitchLabel = utWebUI.switchLabel;
utWebUI.switchLabel = function(el)
{
	if(utWebUI.actTrackersLbl)
	{
		$$(utWebUI.actTrackersLbl).className = "";
		utWebUI.actTrackersLbl = null;
	}
	utWebUI.trkSwitchLabel(el);
}

utWebUI.trkAddTrackers = utWebUI.addTrackers;
utWebUI.addTrackers = function(_db)
{
	utWebUI.trkAddTrackers(_db);
	utWebUI.rebuildTrackersLabels();
}

utWebUI.getTrackerName = function(announce)
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
				if((parts[parts.length-2] in ["co", "com", "net", "org"]) ||
					(parts[parts.length-1] in ["uk"]))
					parts = parts.slice(parts.length-3);
				else
					parts = parts.slice(parts.length-2);
				domain = parts.join(".");
			}
		}
	}
	return(domain);
}

utWebUI.switchTrackersLabel = function(el,force)
{
	if((el.id == utWebUI.actTrackersLbl) && !force)
		return;

        var lst = $$("RSSList");
	if(lst && isVisible(lst))
	{
		utWebUI.trtTable.clearSelection();
		utWebUI.dID = "";
		utWebUI.clearDetails();
		utWebUI.rssTable.clearSelection();
		if(utWebUI.actRSSLbl)
			$$(utWebUI.actRSSLbl).className = "RSS";
		utWebUI.actRSSLbl = null;
		show($$("List"));
		hide(lst);
		utWebUI.switchLayout(false);
	}

	if(utWebUI.actTrackersLbl)
		$$(utWebUI.actTrackersLbl).className = "";
	utWebUI.actTrackersLbl = el.id;
	el.className = "sel";
	if((this.actLbl != "") && ($$(this.actLbl) != null))
		$$(this.actLbl).className = "";
	this.actLbl = "";
	for(var hash in this.torrents)
	        utWebUI.filterByTracker(hash);
	if(!force)
	{
		this.trtTable.clearSelection();
		if(this.dID != "")
		{       	
			this.dID = "";
			this.clearDetails();
		}
	}
	this.trtTable.refreshRows();
}

function trackersLabelContextMenu(obj)
{
	utWebUI.trtTable.clearSelection();
	utWebUI.switchTrackersLabel(obj);
	utWebUI.trtTable.fillSelection();
	var sr = utWebUI.trtTable.rowSel;
	var id = null;
	for(var k in sr) 
	{
		if(sr[k] == true) 
		{
			id = k;
			break;
      		}
	}
	if(id)
	{
		utWebUI.createMenu(null, id);
		ContextMenu.show();
	}
	else
		ContextMenu.hide();
}

utWebUI.trkUpdateLabels = utWebUI.updateLabels;
utWebUI.updateLabels = function(wasRemoved)
{
	utWebUI.trkUpdateLabels();
	if(wasRemoved)
		utWebUI.rebuildTrackersLabels();
}

utWebUI.rebuildTrackersLabels = function()
{
	if(!utWebUI.allTrackersStuffLoaded)
		setTimeout('utWebUI.rebuildTrackersLabels()',1000);
	else
	{
		trackersLabels = new Object();
		for(var hash in this.trackers)
		{
			if((typeof (this.torrents[hash]) != "undefined"))
			{
				for( var i=0; i<this.trackers[hash].length; i++)
				{
					if(this.trackers[hash][i][3]==0)
					{
						var tracker = utWebUI.getTrackerName( this.trackers[hash][i][0] );
						if(tracker)
						{
							if(typeof (trackersLabels[tracker]) != "undefined")
								trackersLabels[tracker]++;
							else
								trackersLabels[tracker] = 1;
						}
					}
				}
			}
		}
		var ul = $$("torrl");
		for(var lbl in trackersLabels)
		{
			var li = null;
			if(lbl in this.trackersLabels)
			{
				li = $$(lbl);
	                	li.innerHTML = escapeHTML(lbl)+'&nbsp;(<span id="'+lbl+'_c">'+trackersLabels[lbl]+'</span>)';
			}
			else
			{
				li = document.createElement('LI');
				li.id = lbl;
				li.onclick = function() { utWebUI.switchTrackersLabel(this); return(false);};
				li.innerHTML = escapeHTML(lbl)+'&nbsp;(<span id="'+lbl+'_c">'+trackersLabels[lbl]+'</span>)';
				ul.appendChild(li);
				addRightClickHandler( li, trackersLabelContextMenu );
			}
			li.className = (lbl==utWebUI.actTrackersLbl) ? "sel" : "";
		}
		var needSwitch = false;
		for(var lbl in this.trackersLabels)
			if(!(lbl in trackersLabels))
			{
				ul.removeChild( $$(lbl) );
				if(utWebUI.actTrackersLbl == lbl)
				{
					needSwitch = true;
					utWebUI.actTrackersLbl = null;
				}       	
			}
		this.trackersLabels = trackersLabels;
		if(needSwitch)
			utWebUI.switchLabel($$("-_-_-all-_-_-"));
		else
		if(utWebUI.actTrackersLbl)
		{
			utWebUI.switchTrackersLabel($$(utWebUI.actTrackersLbl),true);
		}
	}
}

utWebUI.initTrackersLabels = function()
{
	if((typeof (utWebUI.getRSSIntervals) != "undefined") &&
		!utWebUI.allRSSStuffLoaded)
		setTimeout('utWebUI.initTrackersLabels()',1000);
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
			pnl.innerHTML = WUILang.Trackers;
			pnl.onclick = function() { utWebUI.togglePanel(pnl); };
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
	        utWebUI.allTrackersStuffLoaded = true;
	}
};

utWebUI.initTrackersLabels();