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

		$.each(this.sites,function(ndx,val)
		{
			if(val.enabled)
			{
				if(theSearchEngines.current==ndx)
					theContextMenu.add([CMENU_SEL, ndx, "theSearchEngines.set('"+ndx+"')"]);
				else
					theContextMenu.add([ndx, "theSearchEngines.set('"+ndx+"')"]);
			}
			else
				if(theSearchEngines.current==ndx)
					theSearchEngines.current=-1;
		});
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
				$("#query").attr("readonly",true);
				theWebUI.requestWithoutTimeout("?action=extsearch&s="+theSearchEngines.current+"&v="+encodeURIComponent(s)+"&v="+encodeURIComponent($("#exscategory").val()),[theWebUI.setExtSearchTag, theWebUI]);
			}
		}
	}
	else
		plugin.sitesRun.call(theSearchEngines);
}

rTorrentStub.prototype.extsearch = function()
{
	plugin.forceMode = true;
	this.content = "mode=get&eng="+this.ss[0]+"&what="+this.vs[0]+"&cat="+this.vs[1];
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/extsearch/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.loadtegtorrents = function()
{
	this.content = "mode=loadtorrents";
	if($("#tegtorrents_start_stopped").attr("checked"))
		this.content += '&torrents_start_stopped=1';
	if($("#tegnot_add_path").attr("checked"))
		this.content += '&not_add_path=1';
	var dir = $.trim($("#tegdir_edit").val());
	if(dir.length)
		this.content += ('&dir_edit='+encodeURIComponent(dir));
	var lbl = $.trim($("#teglabel").val());
	if(lbl.length)
		this.content += '&label='+encodeURIComponent(lbl);
	if($("#tegfast_resume").attr("checked"))
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

plugin.enterTeg = function(id)
{
	plugin.reloadData(id);
	var lst = $("#List");
	var table = theWebUI.getTable("teg");
	if(!lst.is(":visible"))
		lst = $("#RSSList");
	if(lst.is(":visible"))	
	{
		theWebUI.getTable("trt").clearSelection();
		theWebUI.dID = "";
		theWebUI.clearDetails();
		if(theWebUI.getTable("rss"))
		{
			theWebUI.getTable("rss").clearSelection();
			theWebUI.actRSSLbl = null;
		}
		var teg = $("#TegList");
		teg.css( { width: lst.width(), height: lst.height() } );
		table.resize(lst.width(), lst.height());
		lst.hide();
		teg.show();
		table.scrollTo(0);
	}
	table.calcSize().resizeHack();
}

plugin.leaveTeg = function(id)
{
	if(!$type(plugin.tegs[id]))
	{
		$("#TegList").hide();
		$("#List").show();
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
	}
}

plugin.switchRSSLabel = theWebUI.switchRSSLabel;
theWebUI.switchRSSLabel = function(el,force)
{
	if(plugin.enabled && this.actLbl && $type(plugin.tegs[this.actLbl]))
		plugin.leaveTeg(el.id);
	plugin.switchRSSLabel.call(theWebUI,el,force);
}

plugin.switchLabel = theWebUI.switchLabel;
theWebUI.switchLabel = function(obj)
{
	var actLbl = theWebUI.actLbl;
	var rssLbl = theWebUI.actRSSLbl;
	plugin.switchLabel.call(theWebUI,obj);
	if(plugin.enabled && ((actLbl!=theWebUI.actLbl) || rssLbl || plugin.forceMode))
	{
		if($type(plugin.tegs[obj.id]))
			plugin.enterTeg(obj.id);
		else
			if(actLbl && $type(plugin.tegs[actLbl]))
				plugin.leaveTeg(obj.id);
	}
}

plugin.filterByLabel = theWebUI.filterByLabel;
theWebUI.filterByLabel = function(hash)
{
        if(!$($$(this.actLbl)).hasClass("exteg"))
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
			log( (item.hash ? theUILang.addTorrentSuccess : theUILang.addTorrentFailed) +" ("+item.name+')' );
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
		plugin.tegs[theWebUI.actLbl].data[plugin.tegArray[i].ndx].deleted = true;
		table.removeRow( theWebUI.actLbl+"$"+plugin.tegArray[i].ndx );
	}
	plugin.correctCounter(theWebUI.actLbl,null);
	table.refreshRows();
}

theWebUI.extTegDelete = function()
{
	var lbl = theWebUI.actLbl;
	theWebUI.switchLabel($$("-_-_-all-_-_-"));
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
        if(e.button==2)
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
	var el = $("<LI>").attr("id",tegId).addClass("exteg").addClass('Engine'+d.eng).
		html(escapeHTML(str) + "&nbsp;(<span id=\"" + tegId + "-c\">0</span>)").
		mouseclick(plugin.extTegContextMenu).addClass("cat")
	$("#lblf").append( el );
	plugin.tegs[tegId] = { "val": str, "what": what, "cat": d.cat, "eng": d.eng, "data": d.data };
	theWebUI.switchLabel(el[0]);
	plugin.forceMode = false;
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
	if(plugin.enabled && this.actLbl && $type(plugin.tegs[this.actLbl]))
	{
		var updated = false;
		var tegItems = plugin.tegs[this.actLbl].data;
		for(var i=0; i<tegItems.length; i++)
		{
			var item = tegItems[i];
			var ndx = this.actLbl+'$'+i;
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
	if($type(plugin.tegs[theWebUI.actLbl]))
	{
		var item = plugin.tegs[theWebUI.actLbl];
		$("#query").val(item.what);
		$("#query").attr("readonly",true);
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
		if((e.button == 2) && plugin.canChangeMenu())
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
	plugin.switchTrackersLabel = theWebUI.switchTrackersLabel;
	theWebUI.switchTrackersLabel = function(el,force)
	{
		if(plugin.enabled && this.actLbl && $type(plugin.tegs[this.actLbl]))
			plugin.leaveTeg(el.id);
		plugin.switchTrackersLabel.call(theWebUI,el,force);
	}

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
				$('#'+ndx+'_enabled').attr("checked", (val.enabled==1));
				$('#'+ndx+'_global').attr("checked", (val.global==1));
				$('#'+ndx+'_limit').val(val.limit);
				$('#'+ndx+'_enabled').change();
				$('#'+ndx+'_global').change();
			});
		}
		plugin.andShowSettings.call(theWebUI,arg);
	}

	plugin.dataWasChanged = function() 
	{
		if(iv($('#exs_limit').val())!=theSearchEngines.globalLimit)
			return(true);
		var ret = false;
		$.each(theSearchEngines.sites,function(ndx,val)
		{
			if( 	(($('#'+ndx+'_enabled').attr("checked") ? 1 : 0) ^ val.enabled) ||
				(($('#'+ndx+'_global').attr("checked") ? 1 : 0) ^ val.global) ||
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
			req += ('&'+ndx+'_enabled='+($('#'+ndx+'_enabled').attr("checked") ? 1 : 0)+
				'&'+ndx+'_global='+($('#'+ndx+'_global').attr("checked") ? 1 : 0)+
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
	$("#exscategory").attr("disabled",(theSearchEngines.current == -1));
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
		"<div id='buttons' class='aright buttons-list'><input type='button' class='Button' value="+theUILang.ok+" onclick='theDialogManager.hide(\"tegLoadTorrents\");theWebUI.tegLoadTorrents();return(false);'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",
		true);
	if(thePlugins.isInstalled("_getdir"))
	{
		$('#tegdir_edit').after($("<input type=button>").addClass("Button").width(30).attr("id","tegBtn").focus( function() { this.blur(); } ));
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
	var styles = "";
	$.each(theSearchEngines.sites,function(ndx,val)
	{
		if(val.public)
			contPublic +=
				"<fieldset>"+
					"<legend>"+ndx+"</legend>"+
					"<div class='checkbox'><input type='checkbox' id='"+ndx+"_enabled' checked='true' onchange=\"linked(this, 0, ['"+ndx+"_global','"+ndx+"_limit']);\"/><label for='"+ndx+"_enabled' id='lbl_"+ndx+"_enabled'>"+theUILang.Enabled+"</label></div>"+
					"<div class='checkbox'><input type='checkbox' id='"+ndx+"_global' checked='true' onchange=\"linked(this, 0, ['"+ndx+"_limit']);\"/><label for='"+ndx+"_global' id='lbl_"+ndx+"_global'>"+theUILang.exsGlobal+"</label></div>"+
					"<div class='checkbox'><label for='"+ndx+"_limit' id='lbl_"+ndx+"_limit'>"+theUILang.exsLimit+":</label><input type='text' class='Textbox num' maxlength=6 id='"+ndx+"_limit'/></div>"+
				"</fieldset>";
		else
		{
			contPrivate +=  
				"<fieldset>"+
					"<legend>"+ndx+"</legend>"+
					"<div class='checkbox'><input type='checkbox' id='"+ndx+"_enabled' checked='true' onchange=\"linked(this, 0, ['"+ndx+"_global','"+ndx+"_limit']);\"/><label for='"+ndx+"_enabled' id='lbl_"+ndx+"_enabled'>"+theUILang.Enabled+"</label></div>"+
					"<div class='checkbox'><input type='checkbox' id='"+ndx+"_global' checked='true' onchange=\"linked(this, 0, ['"+ndx+"_limit']);\"/><label for='"+ndx+"_global' id='lbl_"+ndx+"_global'>"+theUILang.exsGlobal+"</label></div>"+
					"<div class='checkbox'><label for='"+ndx+"_limit' id='lbl_"+ndx+"_limit'>"+theUILang.exsLimit+":</label><input type='text' class='Textbox num' maxlength=6 id='"+ndx+"_limit'/></div>";
			if(thePlugins.isInstalled("cookies"))
				contPrivate+=		
					"<div class='checkbox'><a href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_cookies\'); return(false);\">"+theUILang.exsCookies+":</a><input type='text' class='TextboxLarge' readOnly=true id='"+ndx+"_cookies' value='"+val.cookies+"'/></div>";
			contPrivate+=
				"</fieldset>";
		}
		styles +=
			(".Engine"+ndx+" {background-image: url(./plugins/extsearch/images/"+ndx+".png); background-repeat: no-repeat}\n");
	});
	if(contPublic.length)
	{
		s+="<fieldset><legend>"+theUILang.exsEngines+" ("+theUILang.extPublic+")</legend>";
		s+=contPublic;
		s+="</fieldset>";
	}
	if(contPrivate.length)
	{
		s+="<fieldset><legend>"+theUILang.exsEngines+" ("+theUILang.extPrivate+")</legend>";
		s+=contPrivate;
		s+="</fieldset>";
	}
	if(styles.length)
		injectCSSText(styles);
	this.attachPageToOptions($("<div>").attr("id","st_extsearch").html(s)[0],theUILang.exsSearch);
	var td = $$('rrow').insertCell(2);
	s ="<select id='exscategory' title='"+theUILang.excat+"'></select>";
	$(td).attr("id","exscat").html(s); 
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
	theWebUI.switchLabel($$("-_-_-all-_-_-"));
	for( var teg in plugin.tegs )
		$("#"+teg).remove();
	plugin.tegs = {};
	$("#TegList").remove();
	this.removePageFromOptions("st_extsearch");
	$("#exscat").remove();
}
