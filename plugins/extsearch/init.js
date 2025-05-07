plugin.loadMainCSS();
plugin.loadLang();

plugin.categories = [ 'all', 'movies', 'tv', 'music', 'games', 'anime', 'software', 'pictures', 'books' ];
const catlist = theWebUI.categoryList;

plugin.set = theSearchEngines.set;
theSearchEngines.set = function(val, noSave)
{
	plugin.set.call(theSearchEngines,val,noSave);
        theSearchEngines.checkForIncorrectCurrent(!noSave);
}

theSearchEngines.getEngName = function(eng)
{
	return(eng=="all" ? theUILang.All : 
		eng=="public" ? theUILang.extAllPublic : 
		eng=="private" ? theUILang.extAllPrivate : 
		eng);
}

theSearchEngines.isPublicPresent = function(enable)
{
	var ret = false;
	$.each(this.sites,function(ndx,val)
	{
		if((val.public==enable) && val.enabled && val.global)
		{
			ret = true;
			return(false);
		}
	});
	return(ret);
}

plugin.sitesShow = theSearchEngines.show;
theSearchEngines.show = function()
{
	if(plugin.enabled)
	{
		theContextMenu.clear();
		theSearchEngines.checkForIncorrectCurrent(false);
		if(theSearchEngines.current=='all')
			theContextMenu.add([CMENU_SEL, theUILang.All, "theSearchEngines.set('all')"]);
		else
			theContextMenu.add([theUILang.All, "theSearchEngines.set('all')"]);

		if(this.isPublicPresent(true) && this.isPublicPresent(false))
		{
			if(theSearchEngines.current=='public')
				theContextMenu.add([CMENU_SEL, theUILang.extAllPublic, "theSearchEngines.set('public')"]);
			else
				theContextMenu.add([theUILang.extAllPublic, "theSearchEngines.set('public')"]);
			if(theSearchEngines.current=='private')
				theContextMenu.add([CMENU_SEL, theUILang.extAllPrivate, "theSearchEngines.set('private')"]);
			else
				theContextMenu.add([theUILang.extAllPrivate, "theSearchEngines.set('private')"]);
		}
		theContextMenu.add([CMENU_SEP]);

		var public = [];
		var private = [];
		var publicPresent = false;
		var privatePresent = false;
		$.each(this.sites,function(ndx,val)
		{
			if(val.enabled)
			{
				if(val.public)
					publicPresent = true;
				else
					privatePresent = true;
				if(publicPresent && privatePresent)
					return(false);
			}
		});

		$.each(this.sites,function(ndx,val)
		{
			if(val.enabled)
			{
				if(publicPresent && privatePresent)
				{
				        if(val.public)
				        {
						if(theSearchEngines.current==ndx)
							public.push([CMENU_SEL, ndx, "theSearchEngines.set('"+ndx+"')"]);
						else
							public.push([ndx, "theSearchEngines.set('"+ndx+"')"]);
					}
					else
				        {
						if(theSearchEngines.current==ndx)
							private.push([CMENU_SEL, ndx, "theSearchEngines.set('"+ndx+"')"]);
						else
							private.push([ndx, "theSearchEngines.set('"+ndx+"')"]);
					}
				}
				else
				{
					if(theSearchEngines.current==ndx)
						theContextMenu.add([CMENU_SEL, ndx, "theSearchEngines.set('"+ndx+"')"]);
					else
						theContextMenu.add([ndx, "theSearchEngines.set('"+ndx+"')"]);
				}
			}
			else
				if(theSearchEngines.current==ndx)
					theSearchEngines.current=-1;
		});
		if(public.length)
			theContextMenu.add([CMENU_CHILD, theUILang.extPublic, public]);
		if(private.length)
			theContextMenu.add([CMENU_CHILD, theUILang.extPrivate, private]);
		if(publicPresent || privatePresent)
			theContextMenu.add([CMENU_SEP]);
		if(theSearchEngines.current==-1)
			theContextMenu.add([CMENU_SEL, theUILang.innerSearch, "theSearchEngines.set(-1)"]);
		else
			theContextMenu.add([theUILang.innerSearch, "theSearchEngines.set(-1)"]);
		var offs = $("#search").offset();
		theContextMenu.show(offs.left-5,offs.top+5+$("#search").height());
	}
	else
		plugin.sitesShow.call(theSearchEngines);
}

theSearchEngines.checkForIncorrectCurrent = function( refreshCats )
{
	if(plugin.enabled)
	{
		if(($type(theSearchEngines.current)=="number") && (theSearchEngines.current>=0))
		{
			theSearchEngines.current = -1;
			theWebUI.save();
		}
		else
		{
			if((    (theSearchEngines.current!='all') && 
				(theSearchEngines.current!='private') && 
				(theSearchEngines.current!='public') && 
				(!$type(theSearchEngines.sites[theSearchEngines.current]) || !theSearchEngines.sites[theSearchEngines.current].enabled) 
			   ) 
				||
			   (
				!(this.isPublicPresent(true) && this.isPublicPresent(false)) && 
				((theSearchEngines.current=='private') || (theSearchEngines.current=='public'))
			   ))
			{
				theSearchEngines.current = -1;
				theWebUI.save();
			};
		}
		if(refreshCats)
			plugin.refreshCategories();
	}
}

plugin.sitesRun = theSearchEngines.run;
theSearchEngines.run = function()
{
	if(plugin.enabled)
	{
		var s = $("#query").val().trim();
		if(s.length)
		{
			this.checkForIncorrectCurrent(false);
		        if(theSearchEngines.current==-1)
			        theWebUI.setTeg(s);
			else
			{		
				$("#query").prop("readonly",true);
				theWebUI.requestWithoutTimeout("?action=extsearch&s="+theSearchEngines.current+"&v="+encodeURIComponent(s)+"&v="+encodeURIComponent($("#exscategory").val()),[theWebUI.setExtSearchTag, theWebUI]);
			}
		}
		else
		{
			theWebUI.categoryList.resetSelection();
		}
	}
	else
		plugin.sitesRun.call(theSearchEngines);
}

rTorrentStub.prototype.extsearch = function()
{
	this.content = "mode=get&eng="+this.ss[0]+"&what="+this.vs[0]+"&cat="+this.vs[1];
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/extsearch/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.loadtegtorrents = function()
{
	this.content = "mode=loadtorrents";
	if($("#tegtorrents_start_stopped").prop("checked"))
		this.content += '&torrents_start_stopped=1';
	if($("#tegnot_add_path").prop("checked"))
		this.content += '&not_add_path=1';
	var dir = $("#tegdir_edit").val().trim();
	if(dir.length)
		this.content += ('&dir_edit='+encodeURIComponent(dir));
	var lbl = $("#teglabel").val().trim();
	if(lbl.length)
		this.content += '&label='+encodeURIComponent(lbl);
	if($("#tegfast_resume").prop("checked"))
		this.content += '&fast_resume=1';
	for(var i = 0; i<plugin.tegArray.length; i++)
	{
		var item = plugin.tegArray[i];
		this.content += ('&url='+encodeURIComponent(item.data.link)+'&eng='+item.data.src+'&teg='+item.id+'&ndx='+item.ndx);
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/extsearch/action.php";
	this.dataType = "json";
}

plugin.lastTeg = 0;
plugin.tegs = {};
plugin.tegArray = new Array();

plugin.reloadData = function(id)
{
	if($type(plugin.tegs[id]))
	{
		var table = theWebUI.getTable("teg");
		table.noSort = true;
	        table.clearRows();
		table.scrollTo(0);
		var data = plugin.tegs[id].data;
		for( var i = 0; i<data.length; i++ )
		{
			var item = data[i];
			if(!item.deleted)
			{
				if(item.hash && $type(theWebUI.torrents[item.hash]))
				{
					table.addRow(theWebUI.getTable("trt").getValues(item.hash),
						id+'$'+i, theWebUI.getTable("trt").getIcon(item.hash));
				}
				else		
				table.addRowById(
				{
					name: item.name,
					created: item.time-theWebUI.deltaTime/1000,
					peers: item.peers,
					seeds: item.seeds,
					size: item.size,
					status: item.src,
					label: item.cat
				}, id+'$'+i, "Engine"+item.src);
			}
		}
		if(table.sortId)
			table.Sort();
		catlist.refreshAndSyncPanel('psearch');
		table.noSort = false;
	}
}


plugin.switchLabel = catlist.switchLabel.bind(catlist);
catlist.switchLabel = function(panelId, targetId, toggle=false, range=false)
{
	const tegList = $("#TegList");
	const list = $("#List");
	if (plugin.enabled 
		&& panelId == 'psearch'
		&& targetId in plugin.tegs 
	) {
		// no support for multi selection
		toggle = false;
		range = false;
		plugin.reloadData(targetId);
		const table = theWebUI.getTable("teg");
		if (!tegList.is(":visible"))
		{
			// switch to extsearch view
			theWebUI.getTable("trt").clearSelection();
			theWebUI.dID = "";
			theWebUI.clearDetails();
			tegList.css( { width: list.width(), height: list.height() } );
			table.resize(list.width(), list.height());
			tegList.show();
		}
		table.scrollTo(0);
		table.resizeColumn();
	} else if (tegList.is(":visible")) {
		// switch away from extsearch view
		tegList.hide();
		list.show();
	}
	const change = plugin.switchLabel(panelId, targetId, toggle, range);

	// finally hide list if teglist shown
	if (tegList.is(":visible")) {
		list.hide();
	}
	return change;
}

theWebUI.activeExtTegId = function() {
	return catlist.selection.ids('psearch').find(lid => lid in plugin.tegs);
}

plugin.filterTorrentTable = theWebUI.filterTorrentTable;
theWebUI.filterTorrentTable = function()
{
	if (!theWebUI.activeExtTegId()) {
		plugin.filterTorrentTable.call(theWebUI);
	}
}

theWebUI.setTagsHash = function(d)
{
	if($type(plugin.tegs[d.teg]))
	{
		for( var i=0; i<d.data.length; i++ )
		{
			var item = plugin.tegs[d.teg].data[ d.data[i].ndx ];
			item.hash = d.data[i].hash;
			noty( (item.hash ? theUILang.addTorrentSuccess : theUILang.addTorrentFailed) +" ("+item.name+')', (item.hash ? "success" : "error") );
		} 
		theWebUI.getTorrents("list=1");
	}
}

theWebUI.tegLoadTorrents = function()
{
	this.request("?action=loadtegtorrents",[theWebUI.setTagsHash, theWebUI]);
}

theWebUI.extTegLoad = function()
{
	theDialogManager.show("tegLoadTorrents");
}

theWebUI.extTegOpen = function()
{
	for(var i = 0; i<plugin.tegArray.length; i++)
		window.open(plugin.tegArray[i].data.desc,"_blank");
}

theWebUI.tegItemRemove = function()
{
	var table = theWebUI.getTable("teg");
	const tegId = theWebUI.activeExtTegId();
	for(var i = 0; i<plugin.tegArray.length; i++)
	{
		plugin.tegs[tegId].data[plugin.tegArray[i].ndx].deleted = true;
		table.removeRow( tegId+"$"+plugin.tegArray[i].ndx );
	}
	table.correctSelection();
	catlist.refreshAndSyncPanel("psearch");
	table.refreshRows();
}

theWebUI.showTegURLInfo = function()
{
	var table = theWebUI.getTable("teg");
	const tegId = theWebUI.activeExtTegId();
	for(var i = 0; i<plugin.tegArray.length; i++)
	{
		log(theUILang.exsURLGUID+": "+plugin.tegs[tegId].data[plugin.tegArray[i].ndx].desc);
		log(theUILang.exsURLHref+": "+plugin.tegs[tegId].data[plugin.tegArray[i].ndx].link);
	}
}

theWebUI.extTegDelete = function()
{
	const tegId = theWebUI.activeExtTegId();
	catlist.switchLabel('psearch', '');
	if (tegId) {
		delete plugin.tegs[tegId];
		catlist.refreshAndSyncPanel("psearch");
	}
}

plugin.createExtTegMenu = function(e, id)
{
	var trtArray = new Array();
	plugin.tegArray = new Array();

	var sr = theWebUI.getTable("teg").rowSel;
	for(var k in sr) 
	{
		if(sr[k] == true)
		{
			var nfo = plugin.getTegByRowId(k);
			if(nfo)
			{
				var hash = nfo.data.hash;
				if(hash && $type(theWebUI.torrents[hash]))
					trtArray.push(hash);
				else
					plugin.tegArray.push(nfo);
			}
		}
	}
	theContextMenu.clear();
	if(plugin.tegArray.length)
	{
	        if(plugin.canChangeMenu())
	        {
			theContextMenu.add([ theUILang.tegMenuLoad, "theWebUI.extTegLoad()"]);
			theContextMenu.add([ theUILang.tegMenuOpen, "theWebUI.extTegOpen()"]);
			theContextMenu.add([ theUILang.tegMenuDeleteItem, "theWebUI.tegItemRemove()"]);
			theContextMenu.add([ theUILang.exsURLInfo, "theWebUI.showTegURLInfo()"] );
		}
		else
			theContextMenu.hide();
	}
	else
	if(trtArray.length)
	{
	        var table = theWebUI.getTable("trt");
		for(var k in table.rowSel)
			table.rowSel[k] = false;
		table.selCount = trtArray.length;
		for(var i = 0; i<trtArray.length; i++)
			table.rowSel[trtArray[i]] = true;
		table.refreshSelection();
		theWebUI.dID = trtArray[0];
		theWebUI.createMenu(e, trtArray[0]);
	}
}
function idIsExTeg(labelId) {
	return labelId?.startsWith('extteg_') ?? false;
}

plugin.contextMenuTable = theWebUI.contextMenuTable;
theWebUI.contextMenuTable = function(panelId, labelId) {
	return panelId === 'psearch' && idIsExTeg(labelId) ?
		theWebUI.getTable('teg')
		: plugin.contextMenuTable.call(theWebUI, panelId, labelId);
},

plugin.contextMenuEntries = catlist.contextMenuEntries.bind(catlist);
catlist.contextMenuEntries = function(panelId, labelId) {
	if (panelId === 'psearch' && idIsExTeg(labelId)) {
		theWebUI.getTable('trt').clearSelection();
		return plugin.canChangeMenu() ? [
			[ theUILang.tegRefresh,  "theWebUI.tegRefresh()"],
			[ theUILang.tegMenuDelete, "theWebUI.extTegDelete()"]
		] : false;
	}
	return plugin.contextMenuEntries(panelId, labelId);
}

plugin.createMenu = theWebUI.createMenu;
theWebUI.createMenu = function(e,id) {
	if (id in this.getTable('teg').rowSel) {
		// context menu for ext search
		plugin.createExtTegMenu(e, id);
	} else {
		plugin.createMenu.call(theWebUI, e, id);
	}
}

const psearchEntries = catlist.refreshPanel.psearch.bind(catlist);
catlist.refreshPanel.psearch = (attribs) => psearchEntries(attribs).concat(Object.entries(plugin.tegs)
	.map(([tegId, teg]) => [
		tegId,
		{
			...attribs.get(tegId),
			text: teg.val,
			icon: `url:./plugins/extsearch/images/${teg.eng === 'all' ? 'search' : teg.eng}.png`,
			count: String(teg.data.filter(d => !d.deleted).length),
			selected: catlist.isLabelIdSelected("psearch", tegId),
		}
	]));

theWebUI.setExtSearchTag = function( d )
{
	$("#query").removeAttr("readonly");
	var what = $("#query").val().trim();
	var str = theSearchEngines.getEngName(d.eng)+"/"+($type(theUILang["excat"+d.cat]) ? theUILang["excat"+d.cat] : d.cat)+": "+what;
	for( var id in plugin.tegs )
		if(plugin.tegs[id].val==str)
		{
			plugin.tegs[id].data = d.data;
			catlist.switchLabel('psearch', id);
			return;
		}
	const tegId = "extteg_"+plugin.lastTeg;
	plugin.lastTeg++;
	plugin.tegs[tegId] = { "val": str, "what": what, "cat": d.cat, "eng": d.eng, "data": d.data };
	catlist.refresh('psearch');
	catlist.switchLabel('psearch', tegId);
}

plugin.getTegByRowId = function( rowId )
{
	var pos = rowId.indexOf('$');
	if(pos>0)
	{
		var tegId = rowId.substr(0,pos);
		var index = rowId.substr(pos+1);
		if($type(plugin.tegs[tegId]) && (plugin.tegs[tegId].data.length>index))
			return( { id: tegId, ndx: index, data: plugin.tegs[tegId].data[index] } );
	}
	return(null);
}

plugin.loadTorrents = theWebUI.loadTorrents;
theWebUI.loadTorrents = function(needSort)
{
	plugin.loadTorrents.call(this,needSort);
	var table = this.getTable("teg");
	const tegId = theWebUI.activeExtTegId();
	if(plugin.enabled && tegId) 
	{
		var updated = false;
		var tegItems = plugin.tegs[tegId].data;
		for(var i=0; i<tegItems.length; i++)
		{
			var item = tegItems[i];
			var ndx = tegId+'$'+i;
			if($type(table.rowdata[ndx]))
			{
				if((item.hash!="") && $type(this.torrents[item.hash]))
					updated = table.updateRowFrom(this.getTable("trt"),item.hash,ndx) || updated;
				else
				{
					updated = table.setValuesById(ndx,
					{
						name: item.name,
						created: item.time-theWebUI.deltaTime/1000,
						peers: item.peers,
						seeds: item.seeds,
						size: item.size,
						status: item.src,
						label: item.cat
					},true) || updated; 
					updated = table.setIcon(ndx,"Engine"+item.src) || updated;
				}
			}
		}
		if(updated && table.sortId)
			table.Sort();
	}
}

theWebUI.tegRefresh = function()
{
	const tegId = theWebUI.activeExtTegId();
	if (tegId) {
		const item = plugin.tegs[tegId];
		$("#query").val(item.what).prop("readonly",true);
		theWebUI.requestWithoutTimeout("?action=extsearch&s="+item.eng+"&v="+encodeURIComponent(item.what)+"&v="+encodeURIComponent(item.cat),[theWebUI.setExtSearchTag, theWebUI]);
	}
}

theWebUI.tegItemSelect = function(e,id)
{
	var sr = theWebUI.getTable("teg").rowSel;
	var trtArray = new Array();
	for(var k in sr) 
	{
		if(sr[k] == true)
		{
			var nfo = plugin.getTegByRowId(k);	
			if(nfo && nfo.data.hash && $type(theWebUI.torrents[nfo.data.hash]))
				trtArray.push(nfo.data.hash);
		}
	}
	var table = theWebUI.getTable("trt");
	for(var k in table.rowSel)
		table.rowSel[k] = false;
	table.selCount = trtArray.length;
	for(var i = 0; i<trtArray.length; i++)
		table.rowSel[trtArray[i]] = true;
	table.refreshSelection();
	if(id && (nfo = plugin.getTegByRowId(id)) &&
		nfo.data.hash && 
		$type(theWebUI.torrents[nfo.data.hash]))
		theWebUI.trtSelect(e, nfo.data.hash);
	else
	{
		theWebUI.dID = "";
		theWebUI.clearDetails();
		if((e.which==3) && plugin.canChangeMenu())
		{
			plugin.createExtTegMenu(e, id);
			theContextMenu.show();
		}
		else
			theContextMenu.hide();
	}
}

theWebUI.tegItemDblClick = function(obj)
{
	var nfo = plugin.getTegByRowId(obj.id);	
	if(nfo)
	{
		if(nfo.data.hash && $type(theWebUI.torrents[nfo.data.hash]))
		{
			var tmp = new Object();
	                tmp.id = nfo.data.hash;
        		theWebUI.getTable("trt").ondblclick( tmp );
        		delete tmp;
		}
		else
			window.open(nfo.data.desc,"_blank");
	}
}

plugin.resizeTop = theWebUI.resizeTop;
theWebUI.resizeTop = function( w, h )
{
	plugin.resizeTop.call(theWebUI,w,h);
	if(plugin.enabled)
	{
		if(w!==null)
		{
			$("#TegList").width( w );
			if(theWebUI.configured)
		       	       	this.getTable("teg").resize( w );
		}
        	if(h!==null)
		{
			$("#TegList").height( h );
			if(theWebUI.configured)
				this.getTable("teg").resize(null,h); 
	       	}
	}
}

plugin.config = theWebUI.config;
theWebUI.config = function()
{
	$("#List").after($("<div>").attr("id","TegList").css("display","none"));
	this.tables["teg"] =  
	{
	        obj:		new dxSTable(),
		container:	"TegList",
		columns:	cloneObject(theWebUI.tables["trt"].columns),
		format:		this.tables.trt.format,
                onselect:	function(e,id) { theWebUI.tegItemSelect(e,id); },
		ondblclick:	function(obj) { theWebUI.tegItemDblClick(obj); return(false); },
		ondelete:	function() { theWebUI.tegItemRemove(); }
	};
	plugin.config.call(this);
	theSearchEngines.checkForIncorrectCurrent(true);
}

if(plugin.enabled && plugin.canChangeOptions())
{
	plugin.andShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) 
	{
		if(plugin.enabled)
		{
			$('#exs_limit').val(theSearchEngines.globalLimit);
			$.each(theSearchEngines.sites,function(ndx,val)
			{
				$('#'+ndx+'_enabled').prop("checked", (val.enabled==1)).trigger('change');
				$('#'+ndx+'_global').prop("checked", (val.global==1)).trigger('change');
				$('#'+ndx+'_limit').val(val.limit);

			        if(val.enabled==1)
					$('#opt_'+ndx).addClass('bld');
				else
					$('#opt_'+ndx).removeClass('bld');

			});

		}
		$('#sel_public').trigger('change');
		$('#sel_private').trigger('change');
		plugin.andShowSettings.call(theWebUI,arg);
	}

	plugin.dataWasChanged = function() 
	{
		if(iv($('#exs_limit').val())!=theSearchEngines.globalLimit)
			return(true);
		var ret = false;
		$.each(theSearchEngines.sites,function(ndx,val)
		{
			if( 	(($('#'+ndx+'_enabled').prop("checked") ? 1 : 0) ^ val.enabled) ||
				(($('#'+ndx+'_global').prop("checked") ? 1 : 0) ^ val.global) ||
				iv($('#'+ndx+'_limit').val())!=val.limit )
			{
				ret = true;
				return(false);
			}
		});
		return(ret);
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() 
	{
		plugin.setSettings.call(this);
		if(plugin.enabled && plugin.dataWasChanged())
			this.request("?action=setexsearch",[theSearchEngines.checkForIncorrectCurrent,theSearchEngines]);
	}

	rTorrentStub.prototype.setexsearch = function()
	{
		var req = "mode=set&limit="+$('#exs_limit').val();
		$.each(theSearchEngines.sites,function(ndx,val)
		{
			req += ('&'+ndx+'_enabled='+($('#'+ndx+'_enabled').prop("checked") ? 1 : 0)+
				'&'+ndx+'_global='+($('#'+ndx+'_global').prop("checked") ? 1 : 0)+
				'&'+ndx+'_limit='+$('#'+ndx+'_limit').val());
		});
		this.content = req;
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/extsearch/action.php";
		this.dataType = "script";
	}
}

plugin.refreshCategories = function()
{
	$('#exscategory option').remove();
	if((theSearchEngines.current == 'all') ||
		(theSearchEngines.current == 'private') ||
		(theSearchEngines.current == 'public'))
	{
		if(plugin.allStuffLoaded)
		{
			for( var i=0; i<plugin.categories.length; i++)
				$('#exscategory').append("<option value='"+plugin.categories[i]+"'>"+theUILang["excat"+plugin.categories[i]]+"</option>");
		}
		else
			$('#exscategory').append("<option value='all'>"+theUILang.excatall+"</option>");
	}
	else
        if($type(theSearchEngines.sites[theSearchEngines.current]))
	{
		for( var i=0; i<theSearchEngines.sites[theSearchEngines.current].cats.length; i++)
			$('#exscategory').append("<option value='"+theSearchEngines.sites[theSearchEngines.current].cats[i]+"'>"+theSearchEngines.sites[theSearchEngines.current].cats[i]+"</option>");
	}
	$("#exscategory").prop("hidden", (theSearchEngines.current === -1));
}

plugin.shutdownOldVersion = function()
{
	if(thePlugins.get('search').allStuffLoaded)
	{
		thePlugins.get('search').remove();
		theWebUI.plgRefresh();
	}
	else
		setTimeout(arguments.callee,1000);
}

plugin.onLangLoaded = function() {
	const tegLoadTorrentsContent = $("<div>").addClass("cont").append(
		$("<div>").addClass("row").append(
			$("<div>").addClass("col-12 col-md-3 justify-content-md-end").append(
				$("<label>").attr({for:"tegdir_edit"}).text(theUILang.Base_directory + ":"),
			),
			$("<div>").addClass("col-12 col-md-9").append(
				$("<input>").attr({type:"text", id:"tegdir_edit"}),
			),
			...[
				["tegnot_add_path", theUILang.Dont_add_tname],
				["tegtorrents_start_stopped", theUILang.Dnt_start_down_auto],
				["tegfast_resume", theUILang.doFastResume],
			].map(([id, text]) => $("<div>").addClass("col-12 col-md-9 offset-md-3").append(
				$("<input>").attr({type:"checkbox", id:id}),
				$("<label>").attr({for:id}).text(text),
			)),
			$("<div>").addClass("col-12 col-md-3 justify-content-md-end").append(
				$("<label>").attr({for:"teglabel"}).text(theUILang.Label + ":"),
			),
			$("<div>").addClass("col-12 col-md-9").append(
				$("<input>").attr({type:"text", id:"teglabel"}),
			),
		),
	);
	const tegLoadTorrentsButtons = $("<div>").append(
		$("<div>").attr({id:"buttons"}).addClass("buttons-list").append(
			$("<button>").attr({type:"button"}).addClass("OK").on("click", () => {theDialogManager.hide('tegLoadTorrents'); theWebUI.tegLoadTorrents(); return(false);}).text(theUILang.ok),
			$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
		),
	);
	theDialogManager.make("tegLoadTorrents", theUILang.torrent_add,
		[tegLoadTorrentsContent, tegLoadTorrentsButtons],
		true,
	);
	if (thePlugins.isInstalled("_getdir")) {
		new theWebUI.rDirBrowser("tegdir_edit");
	}

	const commonSettings = $("<fieldset>").append(
		$("<legend>").text(theUILang.exsGlobalLimit),
		$("<div>").addClass("row").append(
			$("<div>").addClass("col-12 col-md-3").append(
				$("<label>").attr({for:"exs_limit", id:"lbl_exs_limit"}).text(theUILang.exsLimit + ":"),
			),
			$("<div>").addClass("col-12 col-md-9").append(
				$("<input>").attr({type:"text", id:"exs_limit", maxlength:6}).addClass("Textbox num"),
			),
		),
	);
	const publicTrackers = $("<fieldset>").append(
		$("<legend>").text(theUILang.exsEngines+" ("+theUILang.extPublic+")"),
		$("<div>").addClass("row").append(
			$("<div>").addClass("col-12").append(
				$("<select>").attr({id:"sel_public"}),
			),
		),
	);
	const privateTrackers = $("<fieldset>").append(
		$("<legend>").text(theUILang.exsEngines+" ("+theUILang.extPrivate+")"),
		$("<div>").addClass("row").append(
			$("<div>").addClass("col-12").append(
				$("<select>").attr({id:"sel_private"}),
			),
		),
	);
	var toDisable = [];
	$.each(theSearchEngines.sites, (ndx, val) => {
		if (val.public) {
			publicTrackers.append(
				$("<div>").attr({id:`cont_${ndx}`}).addClass("row seng_public").append(
					$("<div>").addClass("col-12").append(
						$("<input>").attr({
							type: "checkbox",
							id: `${ndx}_enabled`,
							onchange: "$('#opt_"+ndx+"').toggleClass('bld'); linked(this, 0, ['"+ndx+"_global','"+ndx+"_limit']);"
						}),
						$("<label>").attr({for:`${ndx}_enabled`, id:`lbl_${ndx}_enabled`}).text(theUILang.Enabled),
					),
					$("<div>").addClass("col-12").append(
						$("<input>").attr({type:"checkbox", id:`${ndx}_global`, onchange:"linked(this, 0, ['"+ndx+"_limit']);"}),
						$("<label>").attr({for:`${ndx}_global`, id:`lbl_${ndx}_global`}).text(theUILang.exsGlobal),
					),
					$("<div>").addClass("col-12 col-md-3").append(
						$("<label>").attr({for:`${ndx}_limit`, id:`lbl_${ndx}_limit`}).text(theUILang.exsLimit + ":"),
					),
					$("<div>").addClass("col-12 col-md-9").append(
						$("<input>").attr({type:"text", id:`${ndx}_limit`, maxlength:6}).addClass("Textbox num"),
					),
				),
			);
			publicTrackers.find("select").append(
				$("<option>").attr({id:`opt_${ndx}`}).val(ndx).text(ndx),
			);
		} else {
			privateTrackers.append(
				$("<div>").attr({id:`cont_${ndx}`}).addClass("row seng_private").append(
					$("<div>").addClass("col-12").append(
						$("<input>").attr({type:"checkbox", id:`${ndx}_enabled`, onchange:"$('#opt_"+ndx+"').toggleClass('bld'); linked(this, 0, ['"+ndx+"_global','"+ndx+"_limit']);"}),
						$("<label>").attr({for:`${ndx}_enabled`, id:`lbl_${ndx}_enabled`}).text(theUILang.Enabled),
					),
					$("<div>").addClass("col-12").append(
						$("<input>").attr({type:"checkbox", id:`${ndx}_global`, onchange:"linked(this, 0, ['"+ndx+"_limit']);"}),
						$("<label>").attr({for:`${ndx}_global`, id:`lbl_${ndx}_global`}).text(theUILang.exsGlobal),
					),
					$("<div>").addClass("col-12 col-md-3").append(
						$("<label>").attr({for:`${ndx}_limit`, id:`lbl_${ndx}_limit`}).text(theUILang.exsLimit + ":"),
					),
					$("<div>").addClass("col-12 col-md-9").append(
						$("<input>").attr({type:"text", id:`${ndx}_limit`, maxlength:6}).addClass("Textbox num"),
					),
				),
			);
			if (val.cookies) {
				if (thePlugins.isInstalled("cookies")) {
					privateTrackers.find(`#cont_${ndx}`).append(
						$("<div>").addClass("checkbox").append(
							$("<a>").attr({href:"#"}).on("click", () => theOptionsWindow.goToPage("st_cookies")).text(theUILang.exsCookies),
							$("<input>").attr({type:"text", id:`${ndx}_cookies`}).val(val.cookies).prop("readonly", true),
						),
					);
				} else {
					privateTrackers.find(`#cont_${ndx}`).append(
						$("<div>").addClass("checkbox").text(theUILang.exsMustInstallCookies),
					);
					toDisable.push(ndx);
				}
			} else if (val.auth) {
				if (thePlugins.isInstalled("loginmgr")) {
					privateTrackers.find(`#cont_${ndx}`).append(
						$("<div>").addClass("checkbox").append(
							$("<a>").attr({href:"#"}).on("click", () => theOptionsWindow.goToPage('st_loginmgr')).text(theUILang.exsLoginMgr),
						),
					);
				} else {
					privateTrackers.find(`#cont_${ndx}`).append(
						$("<div>").addClass("checkbox").text(theUILang.exsMustInstallLoginMgr),
					);
					toDisable.push(ndx);
				}
			}
			privateTrackers.find("select").append(
				$("<option>").attr({id:`opt_${ndx}`}).val(ndx).text(ndx),
			);
		}
	});
	if (publicTrackers.find("div.row.seng_public").length < 1) {
		publicTrackers.remove();
	}
	if (privateTrackers.find("div.row.seng_private").length < 1) {
		privateTrackers.remove();
	}
	this.attachPageToOptions(
		$("<div>").attr("id","st_extsearch").append(
			commonSettings, publicTrackers, privateTrackers,
		)[0],
		theUILang.exsSearch,
	);
	for (var i in toDisable) {
		$('#'+toDisable[i]+'_enabled').prop("disabled",true).prop("checked",false);
		$('#lbl_'+toDisable[i]+'_enabled').addClass("disabled");
	}
	$('#sel_public').on('change', function() {
		$(".seng_public").hide();
		$('#cont_'+$(this).val()).show();
	});
	$('#sel_private').on('change', function() {
		$(".seng_private").hide();
		$('#cont_'+$(this).val()).show();
	});
	$("<div>").attr({id:"exscat"}).append(
		$("<select>").attr({id:"exscategory", title:theUILang.excat})
	).insertBefore($("#mnu_go"));
	plugin.markLoaded();
	theSearchEngines.checkForIncorrectCurrent(true);
	if(thePlugins.isInstalled('search'))
		plugin.shutdownOldVersion();
}

plugin.onRemove = function()
{
	theSearchEngines.sites = plugin.sites;
	theSearchEngines.current = -1;
	theWebUI.save();
	catlist.resetSelection();
	for( var teg in plugin.tegs )
		$("#"+teg).remove();
	plugin.tegs = {};
	$("#TegList").remove();
	this.removePageFromOptions("st_extsearch");
	$("#exscat").remove();
}
