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

utWebUI.filterByTracker = function(hash)
{
	if((typeof (this.trackers[hash]) != "undefined") &&
		this.trackers[hash].length &&
		this.trackers[hash][0].length)
	{
		var tracker = utWebUI.getTrackerName( this.trackers[hash][0][0] );
		if(tracker && (tracker==utWebUI.actTrackersLbl))
			this.trtTable.unhideRow(hash);
		else
		         this.trtTable.hideRow(hash);
       	}
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
		var pos = domain.indexOf(".");
		if(pos>=0)
		{
			var tmp = domain.substring(pos+1);
			if(tmp.indexOf(".")>=0)
				domain = tmp;
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

utWebUI.rebuildTrackersLabels = function()
{
	if(!utWebUI.allTrackersStuffLoaded)
		setTimeout('utWebUI.rebuildTrackersLabels()',1000);
	else
	{
		trackersLabels = new Object();
		for(var hash in this.trackers)
		{
			if((typeof (this.torrents[hash]) != "undefined") &&
				this.trackers[hash].length &&
				this.trackers[hash][0].length)
			{
				var tracker = utWebUI.getTrackerName( this.trackers[hash][0][0] );
				if(tracker)
				{
					if(typeof (trackersLabels[tracker]) != "undefined")
						trackersLabels[tracker]++;
					else
						trackersLabels[tracker] = 1;
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
			}
			li.className = (lbl==utWebUI.actTrackersLbl) ? "sel" : "";
			addRightClickHandler( li, trackersLabelContextMenu );
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
		var ul = document.createElement('UL');
		ul.innerHTML = '<li id="_hr_"><hr /></li>';
		var el = $$('CatList');
		el.appendChild(ul);
		var div = document.createElement('DIV');
		div.innerHTML = '<ul id="torrl"></ul>';
	        el.appendChild(div);
	        utWebUI.allTrackersStuffLoaded = true;
	}
};

utWebUI.initTrackersLabels();