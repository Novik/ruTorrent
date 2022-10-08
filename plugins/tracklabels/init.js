
theWebUI.trackersLabels = {};
plugin.injectedStyles = {};

plugin.config = theWebUI.config;
theWebUI.config = function()
{
	if(plugin.canChangeColumns())
	{
		theWebUI.tables.trt.columns.push({ text: theUILang.Tracker, width: '100px', id: 'tracker', type: TYPE_STRING});
		plugin.config.call(this);
		plugin.reqId = theRequestManager.addRequest("trk", null, function(hash,tracker,value)
		{
			var domain = theWebUI.getTrackerName( tracker.name );
			tracker.icon = "trk"+domain.replace(/\./g, "_");
			if(!plugin.injectedStyles[tracker.icon])
			{
				plugin.injectedStyles[tracker.icon] = true;
				injectCSSText( "."+tracker.icon+" {background-image: url(./plugins/tracklabels/action.php?tracker="+domain+"); background-repeat: no-repeat; background-size: 16px 16px; }\n" );
			}
		});
	}
}

plugin.isTorrentRowShown = theWebUI.isTorrentRowShown;
theWebUI.isTorrentRowShown = function(table, hash)
{
	return plugin.isTorrentRowShown.call(theWebUI, table, hash) && (
		!plugin.enabled || !(theWebUI.actLbls['ptrackers_cont'] ?? []).length || (
		// check if tracker of hash is selected
		hash in this.trackers
		&& this.trackers[hash]
			.filter(t => Number(t.group) === 0)
			.map(t => theWebUI.getTrackerName( t.name ))
			.some(name => name && plugin.isActiveLabel(name))
	));
}

plugin.isActiveLabel = function(lbl)
{
	return (theWebUI.actLbls['ptrackers_cont'] ?? []).includes('i'+lbl);
}

plugin.addTrackers = theWebUI.addTrackers;
theWebUI.addTrackers = function(data)
{
	plugin.addTrackers.call(theWebUI,data);
	if(plugin.enabled)
		theWebUI.rebuildTrackersLabels();
}

if(!$type(theWebUI.getTrackerName))
{
	theWebUI.getTrackerName = function(announce)
	{
	        var domain = '';
		if(announce)
		{
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
		}
		return(domain);
	}
}

plugin.contextMenuEntries = theWebUI.contextMenuEntries;
theWebUI.contextMenuEntries = function(labelType, el) {
	if (labelType === 'ptrackers_cont') {
		return plugin.canChangeMenu() ? [] : false;
	}
	return plugin.contextMenuEntries.call(theWebUI, labelType, el);
}

plugin.updateLabel = theWebUI.updateLabel;
theWebUI.updateLabel = function(label, ...args)
{
	plugin.updateLabel.call(this, label, ...args);
	var icon = $(label).children('.label-icon');
	var id = icon.parent().attr('id');
	if (id && icon.parents('#plabel_cont')[0] && !icon.children('img')[0])
	{
		var lbl = theWebUI.idToLbl(id);
		icon.append($("<img>")
			.attr({ id: 'lbl_'+lbl, src: 'plugins/tracklabels/action.php?label='+lbl}))
			.css({ background: 'none' });
	}
}

plugin.updateLabels = theWebUI.updateLabels;
theWebUI.updateLabels = function(wasRemoved)
{
	if(plugin.enabled)
	{
		if(wasRemoved)
			theWebUI.rebuildTrackersLabels();
		theWebUI.updateAllFilterLabel('torrl', this.settings["webui.show_labelsize"]);
	}
	plugin.updateLabels.call(theWebUI,wasRemoved);
}

theWebUI.rebuildTrackersLabels = function()
{
	if(!plugin.allStuffLoaded)
		setTimeout('theWebUI.rebuildTrackersLabels()',1000);
	else
	{
		var table = this.getTable('trt');
		var trackersLabels = new Object();
		var trackersSizes = new Object();
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

								if(!$type(trackersSizes[tracker]))
									trackersSizes[tracker] = 0;
								trackersSizes[tracker] += parseInt(this.torrents[hash].size);

								counted[hash].push(tracker);
							}
						}
					}
				}
			}
		}
		var ul = $("#torrl");

		var lbls = Object.keys(trackersLabels);
		lbls.sort();

		let needTableFilter = false;
		for(var lbl of lbls)
		{
			if(!(lbl in this.trackersLabels))
			{
				ul.append(theWebUI.createSelectableLabelElement('i'+lbl, lbl, theWebUI.labelContextMenu)
					.addClass("tracker"));
				$($$('i'+lbl)).children('.label-icon')
					.append($("<img>").attr("src","plugins/tracklabels/action.php?tracker="+lbl))
					.css({ background: 'none' });
			}
			theWebUI.updateLabel($$('i'+lbl), trackersLabels[lbl], trackersSizes[lbl], theWebUI.settings["webui.show_labelsize"]);
			if(plugin.isActiveLabel(lbl)) {
				const actLabel = $($$('i'+lbl));
				if (!actLabel.hasClass('sel')) {
					needTableFilter = true;
					$('#ptrackers_cont').find('.sel').removeClass('sel');
					$(actLabel).addClass("sel");
				}
			}
		}
		if (needTableFilter)
			theWebUI.filterTorrentTable();
		var needSwitch = false;
		for(var lbl in this.trackersLabels)
			if(!(lbl in trackersLabels))
			{
				$($$('i'+lbl)).remove();
				if(plugin.isActiveLabel(lbl))
					needSwitch = true;
			}
		this.trackersLabels = trackersLabels;
		if(needSwitch)
			theWebUI.resetLabels();
		
		setTimeout(plugin.refreshTrackerRows, 0);
	}
}

plugin.refreshTrackerRows = async function()
{
	if(plugin.canChangeColumns())
	{
		var table = theWebUI.getTable('trt');
		table.refreshRows();
		if(table.sIndex !=- 1)
			table.Sort();
	}
}

theWebUI.initTrackersLabels = function()
{
	plugin.addPaneToCategory("ptrackers",theUILang.Trackers).
		append($("<ul></ul>").attr("id","torrl"));

	var ul = $("#torrl");
	ul.append(theWebUI.createSelectableLabelElement(undefined, theUILang.All, theWebUI.labelContextMenu).addClass('-_-_-all-_-_- sel'));

	plugin.markLoaded();
};

plugin.onRemove = function()
{
	plugin.removePaneFromCategory('ptrackers');
	theWebUI.resetLabels();
	if(plugin.canChangeColumns())
	{
		theWebUI.getTable("trt").removeColumnById("tracker");
		if(thePlugins.isInstalled("rss"))
			theWebUI.getTable("rss").removeColumnById("tracker");
		theRequestManager.removeRequest('trk',plugin.reqId);
	}
}

theWebUI.initTrackersLabels();
