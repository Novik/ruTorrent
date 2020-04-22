plugin.loadMainCSS();
plugin.loadLang();

plugin.categories = [ 'all', 'movies', 'tv', 'music', 'games', 'anime', 'software', 'pictures', 'books' ];

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
		var s = $.trim($("#query").val());
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
			theWebUI.resetLabels();
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
	var dir = $.trim($("#tegdir_edit").val());
	if(dir.length)
		this.content += ('&dir_edit='+encodeURIComponent(dir));
	var lbl = $.trim($("#teglabel").val());
	if(lbl.length)
		this.content += '&label='+encodeURIComponent(lbl);
	if($("#tegfast_resume").prop("checked"))
		this.content += 'fast_resume=1&';
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
		var count = 0;
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
				count++;
			}
		}
		if(table.sIndex !=- 1)
			table.Sort();
		plugin.correctCounter(id,count);
		table.noSort = false;
	}
}

plugin.enterTeg = function()
{
	plugin.reloadData(theWebUI.actLbls["flabel_cont"]);
	var lst = $("#List");
	var table = theWebUI.getTable("teg");
	if(lst.is(":visible"))	
	{
		theWebUI.getTable("trt").clearSelection();
		theWebUI.dID = "";
		theWebUI.clearDetails();
		var teg = $("#TegList");
		teg.css( { width: lst.width(), height: lst.height() } );
		table.resize(lst.width(), lst.height());
		lst.hide();
		teg.show();
		table.scrollTo(0);
	}
	table.calcSize().resizeHack();
}

plugin.leaveTeg = function()
{
	$("#TegList").hide();
	$("#List").show();
	if(theWebUI.actLbls["flabel_cont"] && $($$(theWebUI.actLbls["flabel_cont"])).hasClass("exteg")) 
	{
		plugin.switchLabel.call(theWebUI,$("#flabel_cont .-_-_-all-_-_-").get(0));
	}
}

plugin.correctCounter = function(id,count)
{
	if($type(plugin.tegs[id]))
	{
		if(count===null)
		{
			count = 0;
			var data = plugin.tegs[id].data;
			for( var i = 0; i<data.length; i++ )
				if(!data[i].deleted)			
					count++;
		}
		$("#"+id+"-c").text(count);
		$("#"+id).prop("title",plugin.tegs[id].val+" ("+count+")");
	}
}

plugin.switchLabel = theWebUI.switchLabel;
theWebUI.switchLabel = function(obj)
{
	if(plugin.enabled && theWebUI.actLbls["flabel_cont"] && $type(plugin.tegs[theWebUI.actLbls["flabel_cont"]]))
		plugin.leaveTeg();
	plugin.switchLabel.call(theWebUI,obj);
	if(plugin.enabled && theWebUI.actLbls["flabel_cont"] && $type(plugin.tegs[theWebUI.actLbls["flabel_cont"]]))
		plugin.enterTeg();
}

plugin.filterByLabel = theWebUI.filterByLabel;
theWebUI.filterByLabel = function(hash)
{
        if(!$($$(this.actLbls["flabel_cont"])).hasClass("exteg"))
		plugin.filterByLabel.call(theWebUI,hash);
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
	for(var i = 0; i<plugin.tegArray.length; i++)
	{
		plugin.tegs[theWebUI.actLbls["flabel_cont"]].data[plugin.tegArray[i].ndx].deleted = true;
		table.removeRow( theWebUI.actLbls["flabel_cont"]+"$"+plugin.tegArray[i].ndx );
	}
	table.correctSelection();
	plugin.correctCounter(theWebUI.actLbls["flabel_cont"],null);
	table.refreshRows();
}

theWebUI.showTegURLInfo = function()
{
	var table = theWebUI.getTable("teg");
	for(var i = 0; i<plugin.tegArray.length; i++)
	{
		log(theUILang.exsURLGUID+": "+plugin.tegs[theWebUI.actLbls["flabel_cont"]].data[plugin.tegArray[i].ndx].desc);
		log(theUILang.exsURLHref+": "+plugin.tegs[theWebUI.actLbls["flabel_cont"]].data[plugin.tegArray[i].ndx].link);
	}
}

theWebUI.extTegDelete = function()
{
	var lbl = theWebUI.actLbls["flabel_cont"];
	theWebUI.switchLabel($("#flabel_cont .-_-_-all-_-_-").get(0))
	delete plugin.tegs[lbl];
	$($$(lbl)).remove();
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

plugin.extTegContextMenu = function(e)
{
        if(e.which==3)
        {
		if(plugin.canChangeMenu())
		{
			var table = theWebUI.getTable("teg");
			theWebUI.getTable("trt").clearSelection();
			table.clearSelection();
			theWebUI.switchLabel(this);
			table.fillSelection();
			var id = table.getFirstSelected();
			if(id)
			{
				plugin.createExtTegMenu(null, id);
		   		theContextMenu.add([CMENU_SEP]);
			}
			else
				theContextMenu.clear();
			theContextMenu.add([ theUILang.tegRefresh,  "theWebUI.tegRefresh()"]);
			theContextMenu.add([ theUILang.tegMenuDelete, "theWebUI.extTegDelete()"]);
			theContextMenu.show();
		}
		else
		{
			theContextMenu.hide();
			theWebUI.switchLabel(this);
		}
	}
	else
		theWebUI.switchLabel(this);
	return(false);
};

theWebUI.setExtSearchTag = function( d )
{
	$("#query").removeAttr("readonly");
	var what = $.trim($("#query").val());
	var str = theSearchEngines.getEngName(d.eng)+"/"+($type(theUILang["excat"+d.cat]) ? theUILang["excat"+d.cat] : d.cat)+": "+what;
	for( var id in plugin.tegs )
		if(plugin.tegs[id].val==str)
		{
			plugin.tegs[id].data = d.data;
			theWebUI.switchLabel($$(id));
			return;
		}
	var tegId = "extteg_"+plugin.lastTeg;
	plugin.lastTeg++;
	var el = $("<LI>").attr("id",tegId).addClass("exteg").addClass('Engine'+d.eng).attr("title",str+" (0)").
		html(escapeHTML(str) + "&nbsp;(<span id=\"" + tegId + "-c\">0</span>)").
		mouseclick(plugin.extTegContextMenu).addClass("cat")
	$("#lblf").append( el );
	plugin.tegs[tegId] = { "val": str, "what": what, "cat": d.cat, "eng": d.eng, "data": d.data };
	theWebUI.switchLabel(el[0]);
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
	if(plugin.enabled && this.actLbls["flabel_cont"] && $type(plugin.tegs[this.actLbls["flabel_cont"]]))
	{
		var updated = false;
		var tegItems = plugin.tegs[this.actLbls["flabel_cont"]].data;
		for(var i=0; i<tegItems.length; i++)
		{
			var item = tegItems[i];
			var ndx = this.actLbls["flabel_cont"]+'$'+i;
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
		if(updated && (table.sIndex !=- 1))
			table.Sort();
	}
}

theWebUI.tegRefresh = function()
{
	if($type(plugin.tegs[theWebUI.actLbls["flabel_cont"]]))
	{
		var item = plugin.tegs[theWebUI.actLbls["flabel_cont"]];
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
theWebUI.config = function(data)
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
	plugin.config.call(this,data);
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
				$('#'+ndx+'_enabled').prop("checked", (val.enabled==1)).change();
				$('#'+ndx+'_global').prop("checked", (val.global==1)).change();
				$('#'+ndx+'_limit').val(val.limit);

			        if(val.enabled==1)
					$('#opt_'+ndx).addClass('bld');
				else
					$('#opt_'+ndx).removeClass('bld');

			});

		}
		$('#sel_public').change();
		$('#sel_private').change();
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
	$("#exscategory").prop("disabled",(theSearchEngines.current == -1));
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

plugin.onLangLoaded = function()
{
	theDialogManager.make( "tegLoadTorrents", theUILang.torrent_add,
		"<div class='content'>"+
			"<label>"+theUILang.Base_directory+":</label><input type='text' id='tegdir_edit' class='TextboxLarge'/><br/>"+
			"<label></label><input type='checkbox' id='tegnot_add_path'/>"+theUILang.Dont_add_tname+"<br/>"+
			"<label></label><input type='checkbox' id='tegtorrents_start_stopped'/>"+theUILang.Dnt_start_down_auto+"<br/>"+
			'<label></label><input type="checkbox" id="tegfast_resume"/>'+theUILang.doFastResume+'<br/>'+
			"<label>"+theUILang.Label+":</label><input type='text' id='teglabel' class='TextboxLarge'/>"+
		"</div>"+
		"<div id='buttons' class='aright buttons-list'><input type='button' class='OK Button' value="+theUILang.ok+" onclick='theDialogManager.hide(\"tegLoadTorrents\");theWebUI.tegLoadTorrents();return(false);'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",
		true);
	if(thePlugins.isInstalled("_getdir"))
	{
		$('#tegdir_edit').after($("<input type=button>").addClass("Button").attr("id","tegBtn").focus( function() { this.blur(); } ));
		var btn = new theWebUI.rDirBrowser( 'tegLoadTorrents', 'tegdir_edit', 'tegBtn' );
		theDialogManager.setHandler('tegLoadTorrents','afterHide',function()
		{
			btn.hide();
		});
	}
	var s = "<fieldset>"+
			"<legend>"+theUILang.exsGlobalLimit+"</legend>"+
			"<div class='checkbox'><label for='exs_limit' id='lbl_exs_limit'>"+theUILang.exsLimit+":</label><input type='text' class='Textbox num' maxlength=6 id='exs_limit'/></div>"+
		"</fieldset>";
	var contPublic = "";
	var contPrivate = "";
	var optPublic = "";
	var optPrivate = "";
	var styles = "";
	var toDisable = [];
	$.each(theSearchEngines.sites,function(ndx,val)
	{
		if(val.public)
		{
			contPublic +=
				"<div id='cont_"+ndx+"' class='seng_public'>"+
					"<div class='checkbox'><input type='checkbox' id='"+ndx+"_enabled' onchange=\"$('#opt_"+ndx+"').toggleClass('bld'); linked(this, 0, ['"+ndx+"_global','"+ndx+"_limit']);\"/><label for='"+ndx+"_enabled' id='lbl_"+ndx+"_enabled'>"+theUILang.Enabled+"</label></div>"+
					"<div class='checkbox'><input type='checkbox' id='"+ndx+"_global' onchange=\"linked(this, 0, ['"+ndx+"_limit']);\"/><label for='"+ndx+"_global' id='lbl_"+ndx+"_global'>"+theUILang.exsGlobal+"</label></div>"+
					"<div class='checkbox'><label for='"+ndx+"_limit' id='lbl_"+ndx+"_limit'>"+theUILang.exsLimit+":</label><input type='text' class='Textbox num' maxlength=6 id='"+ndx+"_limit'/></div>"+
				"</div>";
			optPublic +=
				"<option value='"+ndx+"' id='opt_"+ndx+"'>"+ndx+"</option>";
		}
		else
		{
			contPrivate +=  
				"<div id='cont_"+ndx+"' class='seng_private'>"+
					"<div class='checkbox'><input type='checkbox' id='"+ndx+"_enabled' onchange=\"$('#opt_"+ndx+"').toggleClass('bld'); linked(this, 0, ['"+ndx+"_global','"+ndx+"_limit']);\"/><label for='"+ndx+"_enabled' id='lbl_"+ndx+"_enabled'>"+theUILang.Enabled+"</label></div>"+
					"<div class='checkbox'><input type='checkbox' id='"+ndx+"_global' onchange=\"linked(this, 0, ['"+ndx+"_limit']);\"/><label for='"+ndx+"_global' id='lbl_"+ndx+"_global'>"+theUILang.exsGlobal+"</label></div>"+
					"<div class='checkbox'><label for='"+ndx+"_limit' id='lbl_"+ndx+"_limit'>"+theUILang.exsLimit+":</label><input type='text' class='Textbox num' maxlength=6 id='"+ndx+"_limit'/></div>";
			if(val.cookies)
			{
				if(thePlugins.isInstalled("cookies"))
					contPrivate+=		
						"<div class='checkbox'><a href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_cookies\'); return(false);\">"+theUILang.exsCookies+":</a><input type='text' class='TextboxLarge' readOnly=true id='"+ndx+"_cookies' value='"+val.cookies+"'/></div>";
				else
				{
					contPrivate+="<div class='checkbox'>"+theUILang.exsMustInstallCookies+"</div>";
					toDisable.push(ndx);
				}
			}
			else
			if(val.auth)
			{
				if(thePlugins.isInstalled("loginmgr"))
					contPrivate+=		
						"<div class='checkbox'><a href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_loginmgr\'); return(false);\">"+theUILang.exsLoginMgr+"</a></div>";
				else
				{
					contPrivate+="<div class='checkbox'>"+theUILang.exsMustInstallLoginMgr+"</div>";
					toDisable.push(ndx);
				}
			}
			contPrivate+=
				"</div>";
			optPrivate +=
				"<option value='"+ndx+"' id='opt_"+ndx+"'>"+ndx+"</option>";
		}
		styles +=
			(".Engine"+ndx+" {background-image: url(./plugins/extsearch/images/"+ndx+".png); background-repeat: no-repeat}\n");
	});
	if(contPublic.length)
	{
		s+="<fieldset><legend>"+theUILang.exsEngines+" ("+theUILang.extPublic+")</legend>";
		s+="<select id='sel_public'>";
		s+=optPublic;
		s+="</select>";
		s+=contPublic;
		s+="</fieldset>";
	}
	if(contPrivate.length)
	{
		s+="<fieldset><legend>"+theUILang.exsEngines+" ("+theUILang.extPrivate+")</legend>";
		s+="<select id='sel_private'>";
		s+=optPrivate;
		s+="</select>";
		s+=contPrivate;
		s+="</fieldset>";
	}
	if(styles.length)
		injectCSSText(styles);
	this.attachPageToOptions($("<div>").attr("id","st_extsearch").html(s)[0],theUILang.exsSearch);
	for( var i in toDisable )
	{
		$('#'+toDisable[i]+'_enabled').prop("disabled",true).prop("checked",false);
		$('#lbl_'+toDisable[i]+'_enabled').addClass("disabled");
	}
	$('#sel_public').change( function()
	{
		$(".seng_public").hide();
		$('#cont_'+$(this).val()).show();
	});
	$('#sel_private').change( function()
	{
		$(".seng_private").hide();
		$('#cont_'+$(this).val()).show();
	});
	var td = $$('rrow').insertCell(2);
	s ="<select id='exscategory' title='"+theUILang.excat+"'></select>";
	$(td).prop("id","exscat").html(s); 
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
	theWebUI.resetLabels();
	for( var teg in plugin.tegs )
		$("#"+teg).remove();
	plugin.tegs = {};
	$("#TegList").remove();
	this.removePageFromOptions("st_extsearch");
	$("#exscat").remove();
}
