plugin.loadMainCSS();
if(browser.isIE && browser.versionMajor < 8)
	plugin.loadCSS("ie");
plugin.loadLang();

plugin.switchLabel = theWebUI.switchLabel;
theWebUI.switchLabel = function(el)
{
        var lst = $("#RSSList");
	if(lst.is(":visible"))
	{
		theWebUI.getTable("trt").clearSelection();
		theWebUI.dID = "";
		theWebUI.clearDetails();
		theWebUI.getTable("rss").clearSelection();
		if(theWebUI.actRSSLbl)
			$$(theWebUI.actRSSLbl).className = theWebUI.isActiveRSSEnabled() ? "RSS cat" : "disRSS cat";
		theWebUI.actRSSLbl = null;
		theWebUI.actLbl = "";
		$("#List").show();
		lst.hide();
		theWebUI.switchLayout(false);
	}
	plugin.switchLabel.call(theWebUI,el);
}

theWebUI.isActiveRSSEnabled = function()
{
	return((theWebUI.actRSSLbl == "_rssAll_") || (theWebUI.rssLabels[theWebUI.actRSSLbl].enabled==1));
}

theWebUI.currentRSSDetailsID = null;
theWebUI.updateRSSDetails = function(id)
{
	theWebUI.currentRSSDetailsID = id;
	if(id)
		this.request("?action=getrssdetails");
	else
		$("#rsslayout").html('');
}

theWebUI.switchLayout = function(toRSS,id)
{
	if(toRSS)
	{
		$("#rsslayout").show();
		$("#mainlayout").hide();
		theWebUI.updateRSSDetails(id);
	}
	else
	{
		$("#mainlayout").show();
		$("#rsslayout").hide();
	}
}

theWebUI.switchRSSLabel = function(el,force)
{
	if((el.id == theWebUI.actRSSLbl) && !force)
		return;
	if(theWebUI.actRSSLbl)
		$$(theWebUI.actRSSLbl).className = theWebUI.isActiveRSSEnabled() ? "RSS cat" : "disRSS cat";
	theWebUI.actRSSLbl = el.id;
	el.className = theWebUI.isActiveRSSEnabled() ? "sel RSS cat" : "sel disRSS cat";
	var table = theWebUI.getTable("rss");
	table.scrollTo(0);
	for(var k in theWebUI.rssItems)
	{
		if((theWebUI.actRSSLbl == "_rssAll_") || (table.getAttr(k, "rss")==theWebUI.actRSSLbl))
			table.unhideRow(k);
		else
			table.hideRow(k);
	}
	table.clearSelection();
	var lst = $("#List");
	if(lst.is(":visible"))
	{
		theWebUI.dID = "";
		theWebUI.clearDetails();
		if((this.actLbl != "") && ($$(this.actLbl) != null))
			$($$(theWebUI.actLbl)).removeClass("sel");
		var rss = $("#RSSList");
		plugin.correctCSS();
		rss.css( { width: lst.width(), height: lst.height() } );
		table.resize(lst.width(), lst.height());
		table.calcSize();
		rss.show();
		lst.hide();
		table.scrollTo(0);
	}
	theWebUI.switchLayout(true);
	table.refreshRows();
}

plugin.config = theWebUI.config;
theWebUI.config = function(data)
{
	this.rssLabels = new Object();
	this.rssItems = new Object();
	this.actRSSLbl = null;
	this.updateRSSTimer = null;
	this.updateRSSInterval = 5*60*1000;
	this.rssUpdateInProgress = false;
	this.rssID = "";
	this.cssCorrected = false;
	this.rssArray = new Array();
	$("#List").after($("<div>").attr("id","RSSList").css("display","none"));
	this.tables["rss"] =  
	{
	        obj:		new dxSTable(),
		container:	"RSSList",
		columns:	cloneObject(theWebUI.tables["trt"].columns),
		format:		this.tables.trt.format,
                onselect:	function(e,id) { theWebUI.rssSelect(e,id) },
		ondblclick:	function(obj) { theWebUI.rssDblClick(obj); return(false); },
		ondelete:	function() { theWebUI.remove(); }
	};
	plugin.config.call(this,data);
	plugin.start();
}

plugin.start = function()
{
	if(plugin.allStuffLoaded)
		theWebUI.request("?action=getintervals",[theWebUI.getRSSIntervals, theWebUI]);
	else
		setTimeout(arguments.callee,1000);
}

theWebUI.rssDblClick = function( obj )
{
	if($type(theWebUI.torrents[theWebUI.rssItems[obj.id].hash]))
	{
		var tmp = new Object();
                tmp.id = theWebUI.rssItems[obj.id].hash
        	theWebUI.getTable("trt").ondblclick( tmp );
        	delete tmp;
	}
	else
		window.open(obj.id,"_blank");
}

theWebUI.getRSSIntervals = function( d )
{
        theWebUI.loadRSS();
	theWebUI.updateRSSInterval = d.interval*60000;	
	theWebUI.updateRSSTimer = window.setTimeout("theWebUI.updateRSS()", d.next*1000);
}

theWebUI.RSSMarkState = function( state )
{
	this.request("?action=rssmarkstate&s="+state,[this.addRSSItems, this]);
}

theWebUI.RSSOpen = function()
{
	for(var i = 0; i<this.rssArray.length; i++)
		window.open(this.rssArray[i],"_blank");
}

theWebUI.RSSLoad = function()
{
	theDialogManager.show("dlgLoadTorrents");
}

theWebUI.RSSLoadTorrents = function()
{
	this.request("?action=loadrsstorrents",[this.addRSSItems, this]);
}

theWebUI.RSSClearHistory = function()
{
	this.request("?action=clearhistory",[this.addRSSItems, this]);
}

theWebUI.RSSRefresh = function()
{
	this.requestWithTimeout("?action=rssrefresh",[this.addRSSItems, this],theWebUI.retryRSSRequest);
}

theWebUI.RSSToggleStatus = function()
{
	this.request("?action=rsstoggle",[this.addRSSItems, this]);
}

theWebUI.doRSSDelete = function()
{
	theWebUI.request("?action=rssremove",[this.addRSSItems, this]);
}

theWebUI.RSSDelete = function()
{
	if(theWebUI.settings["webui.confirm_when_deleting"])
		askYesNo( theUILang.rssMenuDelete, theUILang.rssDeletePrompt, "theWebUI.doRSSDelete()" );
	else
		theWebUI.doRSSDelete();
}

theWebUI.RSSEdit = function()
{
	if(theWebUI.actRSSLbl && theWebUI.rssLabels[this.actRSSLbl])
	{
		$('#editrssURL').val( theWebUI.rssLabels[this.actRSSLbl].url ); 
		$('#editrssLabel').val( theWebUI.rssLabels[this.actRSSLbl].name );
		theDialogManager.show("dlgEditRSS");
	}
}

theWebUI.RSSManager = function()
{
	theWebUI.request("?action=getfilters",[this.loadFilters, this]);
}

theWebUI.rssLabelContextMenu = function(e)
{
        if(e.button==2)
        {
		if(plugin.canChangeMenu())
		{
			theWebUI.getTable("trt").clearSelection();
			theWebUI.getTable("rss").clearSelection();
			theWebUI.switchRSSLabel(this);
			theWebUI.getTable("rss").fillSelection();
			theWebUI.createRSSMenu(null, null);
			theContextMenu.show();
		}
		else
		{
			theContextMenu.hide();
			theWebUI.switchRSSLabel(this);
		}
	}
	else
		theWebUI.switchRSSLabel(this);
	return(false);
}

theWebUI.createRSSMenuPrim = function()
{
        if(plugin.canChangeMenu())
        {
		theContextMenu.add([ theUILang.rssMenuClearHistory, "theWebUI.RSSClearHistory()"]);
		theContextMenu.add([ theUILang.addRSS, "theDialogManager.toggle('dlgAddRSS')"]);
		theContextMenu.add([ theUILang.rssMenuManager, "theWebUI.RSSManager()"]);
		if(theWebUI.actRSSLbl) 
		{
			if(this.actRSSLbl == "_rssAll_")
			{
				theContextMenu.add([ theUILang.rssMenuDisable ]);
				theContextMenu.add([ theUILang.rssMenuEdit ]);
				theContextMenu.add([ theUILang.rssMenuRefresh, "theWebUI.RSSRefresh()"]);
				theContextMenu.add([ theUILang.rssMenuDelete ]);
			}
			else
			{
				if(this.rssLabels[this.actRSSLbl].enabled==1)
				{
					theContextMenu.add([ theUILang.rssMenuDisable, "theWebUI.RSSToggleStatus()"]);
					theContextMenu.add([ theUILang.rssMenuRefresh, "theWebUI.RSSRefresh()"]);
				}
				else
				{
					theContextMenu.add([ theUILang.rssMenuEnable, "theWebUI.RSSToggleStatus()"]);
					theContextMenu.add([ theUILang.rssMenuRefresh ]);
				}
				theContextMenu.add([ theUILang.rssMenuEdit, "theWebUI.RSSEdit()"]);
				theContextMenu.add([ theUILang.rssMenuDelete, "theWebUI.RSSDelete()"]);
			}
		}
	}
	else
		theContextMenu.hide();
	theWebUI.dID = "";
	theWebUI.clearDetails();
}

theWebUI.RSSAddToFilter = function()
{
	theWebUI.request("?action=getfilters",[this.loadFiltersWithAdditions, this]);
}

theWebUI.createRSSMenu = function(e, id) 
{
	var trtArray = new Array();
	this.rssArray = new Array();
	var sr = this.getTable("rss").rowSel;
	for(var k in sr) 
	{
		if(sr[k] == true)
		{
			var hash = this.rssItems[k].hash;
			if(hash && $type(theWebUI.torrents[hash]))
				trtArray.push(hash);
			else
				this.rssArray.push(k);
		}
	}
	theContextMenu.clear();
	if(this.rssArray.length)
	{
	        if(plugin.canChangeMenu())
	        {
			theContextMenu.add([ theUILang.rssMenuLoad, "theWebUI.RSSLoad()"]);
			theContextMenu.add([ theUILang.rssMenuOpen, "theWebUI.RSSOpen()"]);
			theContextMenu.add([ theUILang.rssMenuAddToFilter, "theWebUI.RSSAddToFilter()"]);
			theContextMenu.add([CMENU_CHILD, theUILang.rssMarkAs, [ [ theUILang.rssAsLoaded, "theWebUI.RSSMarkState(1)"], [ theUILang.rssAsUnloaded, "theWebUI.RSSMarkState(0)"] ]]);
			theContextMenu.add([CMENU_SEP]);
			theWebUI.createRSSMenuPrim();
		}
		else
			theContextMenu.hide();
	}
	else
	if(trtArray.length)
	{
	        var table = this.getTable("trt");
		for(var k in table.rowSel)
			table.rowSel[k] = false;
		table.selCount = trtArray.length;
		for(var i = 0; i<trtArray.length; i++)
			table.rowSel[trtArray[i]] = true;
		table.refreshSelection();
		this.dID = trtArray[0];
		theWebUI.createMenu(e, trtArray[0]);
		theContextMenu.add([CMENU_SEP]);
		theWebUI.createRSSMenuPrim();
	}
	else
		theWebUI.createRSSMenuPrim();
}

theWebUI.rssSelect = function(e, id)
{
	var sr = theWebUI.getTable("rss").rowSel;
	var trtArray = new Array();
	for(var k in sr) 
	{
		if(sr[k] == true)
		{
			var hash = theWebUI.rssItems[k].hash;
			if(hash && $type(theWebUI.torrents[hash]))
				trtArray.push(hash);
		}
	}
	var table = theWebUI.getTable("trt");
	for(var k in table.rowSel)
		table.rowSel[k] = false;
	table.selCount = trtArray.length;
	for(var i = 0; i<trtArray.length; i++)
		table.rowSel[trtArray[i]] = true;
	table.refreshSelection();
	if(id && $type(theWebUI.torrents[theWebUI.rssItems[id].hash]))
		theWebUI.trtSelect(e, theWebUI.rssItems[id].hash);
	else
	{
		theWebUI.dID = "";
		theWebUI.clearDetails();
		if((e.button == 2) && plugin.canChangeMenu())
		{
			theWebUI.createRSSMenu(e, id);
			theContextMenu.show();
		}
		else
			theContextMenu.hide();
	}
	theWebUI.switchLayout(!(id && $type(theWebUI.torrents[theWebUI.rssItems[id].hash])),id);
}

plugin.loadTorrents = theWebUI.loadTorrents;
theWebUI.loadTorrents = function(needSort)
{
	plugin.loadTorrents.call(this,needSort);
	if(plugin.enabled && plugin.allStuffLoaded)
	{
		var updated = false;
		var table = this.getTable("rss");
		for(var href in this.rssItems)
		{
			var item = this.rssItems[href];
			if((item.hash!="") && $type(this.torrents[item.hash]))
				updated = table.updateRowFrom(this.getTable("trt"),item.hash,href) || updated;
			else
			{
				updated = table.setValuesById(href,
				{
				 	name: item.title,
				 	status: (item.hash=="") ? theUILang.rssStatus : (item.hash=="Failed") ? theUILang.rssStatusError+" ("+item.errcount+")" : theUILang.rssStatusLoaded, 
					label: table.getAttr(href, "label"),
					created: item.time
				},true) || updated; 
				updated = table.setIcon(href,"Status_RSS") || updated;
			}
		}
		if(updated && (table.sIndex !=- 1))
			table.Sort();
	}
}

theWebUI.updateRSS = function()
{
	if(theWebUI.updateRSSTimer) 
		window.clearTimeout(theWebUI.updateRSSTimer);
	theWebUI.loadRSS();
	theWebUI.updateRSSTimer = window.setTimeout("theWebUI.updateRSS()", theWebUI.updateRSSInterval);
}

theWebUI.retryRSSRequest = function()
{
	theWebUI.timeout(); 
	window.setTimeout("theWebUI.loadRSS()", theWebUI.settings["webui.reqtimeout"]);
}

theWebUI.loadRSS = function()
{
	this.requestWithTimeout("?action=loadrss",[this.addRSSItems, this], theWebUI.retryRSSRequest);
}

theWebUI.processRSS = function(action,elURL,elLbl)
{
	var url = $.trim(elURL.val());
	var lbl = $.trim(elLbl.val());
	var re = new RegExp();
	re.compile("^[A-Za-z]+://[A-Za-z0-9-]+\.[A-Za-z0-9]+"); 
	if(!re.test(url))
		alert(theUILang.incorrectURL);
	else
	{
		elURL.val('');
		elLbl.val('');
		this.requestWithTimeout("?action="+action+"&v="+encodeURIComponent(url)+"&s="+encodeURIComponent(lbl),[this.addRSSItems, this],theWebUI.retryRSSRequest);
	}
}

theWebUI.addRSS = function()
{
	theWebUI.processRSS("addrss",$("#rssURL"),$("#rssLabel"));
}


theWebUI.editRSS = function()
{
	theWebUI.processRSS("editrss",$("#editrssURL"),$("#editrssLabel"));
}

theWebUI.updateRSSLabels = function(rssLabels,allCnt)
{
	var keys = new Array();
	for(var lbl in rssLabels)
		keys.push(lbl);
	keys.sort( function(a,b) {  return((rssLabels[a].name>rssLabels[b].name) ? 1 : (rssLabels[a].name<rssLabels[b].name) ? -1 : 0); } );

	$("#_rssAll_c").text(allCnt);
	$("#_rssAll_").attr("title",theUILang.allFeeds+" ("+allCnt+")");

	var ul = $("#rssl");
	for(var i=0; i<keys.length; i++) 
	{
		var lbl = keys[i];
		var li = null;
		if(lbl in this.rssLabels)
		{
			li = $($$(lbl));
	                li.html( escapeHTML(rssLabels[lbl].name)+'&nbsp;(<span id="'+lbl+'_c">'+rssLabels[lbl].cnt+'</span>)' );
		}
		else
		{
			li = $("<li>").attr("id",lbl).
				html( escapeHTML(rssLabels[lbl].name)+'&nbsp;(<span id="'+lbl+'_c">'+rssLabels[lbl].cnt+'</span>)');
			ul.append(li);
		}
		li.attr("title",rssLabels[lbl].name+" ("+rssLabels[lbl].cnt+")");
		if(lbl==this.actRSSLbl)
			li[0].className = (rssLabels[lbl].enabled==1) ?  "sel RSS cat" : "sel disRSS cat";
		else
			li[0].className = (rssLabels[lbl].enabled==1) ?  "RSS cat" : "disRSS cat";
		li.mouseclick( this.rssLabelContextMenu );
	}
	var needSwitch = false;
	for(var lbl in this.rssLabels)
		if(!(lbl in rssLabels))
		{
			$($$(lbl)).remove();
			if(this.actRSSLbl == lbl)
			{
				needSwitch = true;
				this.actRSSLbl = null;
			}
		}
	this.rssLabels = rssLabels;
	if(needSwitch)
		this.switchRSSLabel($$("_rssAll_"));
	else
	if(this.actRSSLbl)
		this.switchRSSLabel($$(theWebUI.actRSSLbl),true);
}

theWebUI.showRSS = function()
{
	plugin.correctCSS();
        if($('#rssl').children().length)
        	theWebUI.RSSManager();
        else
		theDialogManager.toggle("dlgAddRSS");
}

theWebUI.showErrors = function(d)
{
	for( var i=0; i<d.errors.length; i++)
	{
		var s = d.errors[i].time ? "["+theConverter.date(iv(d.errors[i].time)+theWebUI.deltaTime/1000)+"] "+d.errors[i].desc :
			d.errors[i].desc;
		if(d.errors[i].prm)
			s = s + " ("+d.errors[i].prm+")";
		log(s,true);
	}
}

theWebUI.addRSSItems = function(d)
{
	if(!this.rssUpdateInProgress)
	{
		var updated = false;
		this.rssUpdateInProgress = true;
		this.showErrors(d);
		var rssLabels = new Object();
		var allCnt = 0;
		var table = this.getTable("rss");
		for( var i=0; i<d.list.length; i++)
		{
			var rss = d.list[i];
			rssLabels[rss.hash] = { name: rss.label, cnt: rss.items.length, enabled: rss.enabled, url: rss.url };
			allCnt += rss.items.length;
			for( var j=0; j<rss.items.length; j++)
			{
				var item = rss.items[j];
				if($type(theWebUI.rssItems[item.href]))
				{
					if($type(this.torrents[item.hash]))
						updated = table.updateRowFrom(this.getTable("trt"),item.hash,item.href);
					else
					{
						updated = table.setValuesById(item.href,
						{
						 	name: item.title,
						 	status: (item.hash=="") ? theUILang.rssStatus : (item.hash=="Failed") ? theUILang.rssStatusError+" ("+item.errcount+")" : theUILang.rssStatusLoaded, 
							label: rss.label,
							created: item.time
						},true) || updated; 
  				                updated = table.setIcon(item.href,"Status_RSS") || updated;
					}
					table.setAttr(item.href, { label: rss.label, rss: rss.hash });
				}
				else
				{
					if((item.hash!="") && $type(this.torrents[item.hash]))
					{
						table.addRow(this.getTable("trt").getValues(item.hash),
							item.href, this.getTable("trt").getIcon(item.hash), {rss : rss.hash, label : rss.label});
					}
					else
					{
						table.addRowById(
						{
							name: item.title,
						 	status: (item.hash=="") ? theUILang.rssStatus : (item.hash=="Failed") ? theUILang.rssStatusError+" ("+item.errcount+")" : theUILang.rssStatusLoaded, 
							label: rss.label,
							created: item.time
						}, item.href, "Status_RSS", {"rss" : rss.hash, "label" : rss.label});
					}
					updated = true;
				}
				item._updated = true;
				item.rss = rss.hash;
				theWebUI.rssItems[item.href] = item;
			}
		}
		for(var href in this.rssItems)
		{
			if(this.rssItems[href]._updated)
				this.rssItems[href]._updated = false;
			else
			{
				updated = true;
				delete this.rssItems[href];
				table.removeRow(href);
			}
		}
		if(updated)
			table.Sort();
		this.updateRSSLabels(rssLabels,allCnt);
		this.rssUpdateInProgress = false;
	}
}

theWebUI.storeFilterParams = function()
{
	var no = 0;
	if(this.curFilter)
	{
		no = parseInt(this.curFilter.id.substr(3));
		this.filters[no].pattern = $('#FLT_body').val();
		this.filters[no].exclude = $('#FLT_exclude').val();
		this.filters[no].dir = $('#FLTdir_edit').val();

		this.filters[no].add_path = $('#FLTnot_add_path').attr("checked") ? 0 : 1;
		this.filters[no].start = $('#FLTtorrents_start_stopped').attr("checked") ? 0 : 1;
		this.filters[no].label = $('#FLT_label').val();
		this.filters[no].chktitle = $('#FLTchktitle').attr("checked") ? 1 : 0;
		this.filters[no].chkdesc = $('#FLTchkdesc').attr("checked") ? 1 : 0;
		this.filters[no].chklink = $('#FLTchklink').attr("checked") ? 1 : 0;
		this.filters[no].hash = $('#FLT_rss').val();
		this.filters[no].interval = $('#FLT_interval').val();
		this.filters[no].throttle = $('#FLT_throttle').val();
		this.filters[no].ratio = $('#FLT_ratio').val();
	}
	return(no);
}

plugin.editFilersBtn = null;
theWebUI.selectFilter = function( el )
{
	if(this.curFilter!=el)
	{
		if(this.curFilter)
			this.curFilter.className = 'TextboxNormal';
		this.storeFilterParams();
		this.curFilter = el;
		this.curFilter.className = 'TextboxFocus';
		var no = parseInt(this.curFilter.id.substr(3));
		var flt = this.filters[no];
		$('#FLT_body').val(flt.pattern);
		$('#FLT_exclude').val(flt.exclude);
		$('#FLTdir_edit').val(flt.dir);
		$('#FLTnot_add_path').attr("checked",(flt.add_path==0));
		$('#FLTtorrents_start_stopped').attr("checked",(flt.start==0));
		$('#FLTchktitle').attr("checked",(flt.chktitle==1));
		$('#FLTchkdesc').attr("checked",(flt.chkdesc==1));
		$('#FLTchklink').attr("checked",(flt.chklink==1));
		$('#FLT_label').val(flt.label);
		$('#FLT_rss').val(flt.hash);
		$('#FLT_interval').val(flt.interval);
		$('#FLT_throttle').val(flt.throttle);
		$('#FLT_ratio').val(flt.ratio);
		if(plugin.editFilersBtn)
			plugin.editFilersBtn.hide();
	}
}

theWebUI.loadFiltersWithAdditions = function( flt )
{
	function makePatternString(s)
	{
		var ret = "/^";
		var specChars = "?*+#\^$.[]|(){}/";
		for(var i = 0; i<s.length; i++)
		{
			var c = s.charAt(i);
			if(specChars.indexOf(c)>=0)
				ret = ret + "\\";
			ret+=c;
		}
		return(ret+"$/");
	}

	var additions = new Array();
	for(var i = 0; i<this.rssArray.length; i++)
	{
		var s = this.rssItems[this.rssArray[i]].title;
		additions.push( { name: s, enabled: 1, 
			pattern: makePatternString(s), exclude: "", label: "", hash: "", start: 1, add_path: 1, dir: "", throttle: "", ratio: "", chktitle: 1, chkdesc: 0, chklink: 0, interval: -1, no: -1 } );
	}
	this.loadFilters( flt, additions );
}

theWebUI.maxFilterNo = 0;
theWebUI.loadFilters = function( flt, additions )
{
	this.curFilter = null;
	var list = $("#fltlist");
	list.empty();
	$('#FLT_rss option').remove();	
	$('#FLT_rss').append("<option value=''>"+theUILang.allFeeds+"</option>");
	for(var lbl in this.rssLabels)
		$('#FLT_rss').append("<option value='"+lbl+"'>"+this.rssLabels[lbl].name+"</option>");
	var fltThrottle = $('#FLT_throttle');
	if(fltThrottle.length)
	{
		$('#FLT_throttle option').remove();
		fltThrottle.append("<option value=''>"+theUILang.mnuUnlimited+"</option>");
		for(var i=0; i<theWebUI.maxThrottle; i++)
			if(theWebUI.isCorrectThrottle(i))
				fltThrottle.append("<option value='thr_"+i+"'>"+theWebUI.throttles[i].name+"</option>");
	}
	var fltRatio = $('#FLT_ratio');
	if(fltRatio.length)
	{
		$('#FLT_ratio option').remove();
		fltRatio.append("<option value=''>"+theUILang.mnuRatioUnlimited+"</option>");
		for(var i=0; i<theWebUI.maxRatio; i++)
			if(theWebUI.isCorrectRatio(i))
				fltRatio.append("<option value='rat_"+i+"'>"+theWebUI.ratios[i].name+"</option>");
	}
	this.filters = flt;
	if(additions)
		this.filters = additions.concat(this.filters);
	theWebUI.maxFilterNo = 0;
	for(var i=0; i<this.filters.length; i++)
	{
		var f = this.filters[i];
		if(theWebUI.maxFilterNo<f.no)
			theWebUI.maxFilterNo = f.no;
		list.append( $("<li>").html("<input type='checkbox' id='_fe"+i+"'/><input type='text' class='TextboxNormal' onfocus=\"theWebUI.selectFilter(this);\" id='_fn"+i+"'/>"));
		$("#_fn"+i).val(f.name);
		if(f.enabled)
			$("#_fe"+i).attr("checked",true);
	}
	for(var i=0; i<this.filters.length; i++)
	{
		var f = this.filters[i];
		if(f.no<0)
		{
			theWebUI.maxFilterNo++;
			f.no = theWebUI.maxFilterNo;
		}
	}
	theDialogManager.show("dlgEditFilters");
	$("#_fn0").focus();
}

theWebUI.addNewFilter = function()
{
	var list = $("#fltlist");
	theWebUI.maxFilterNo++;
	var f = { name: theUILang.rssNewFilter, enabled: 1, pattern: "", exclude: "", label: "", hash: "", start: 1, add_path: 1, dir: "", throttle: "", ratio: "", chktitle: 1, chkdesc: 0, chklink: 0, interval: -1, no: theWebUI.maxFilterNo };
	var i = this.filters.length;
	list.append( $("<li>").html("<input type='checkbox' id='_fe"+i+"'/><input type='text' class='TextboxNormal' onfocus=\"theWebUI.selectFilter(this);\" id='_fn"+i+"'/>"));
	this.filters.push(f);
	$("#_fn"+i).val( f.name );
	if(f.enabled)
		$("#_fe"+i).attr("checked",true);
	$("#_fn"+i).focus();
}

theWebUI.deleteCurrentFilter = function()
{
	var no = parseInt(this.curFilter.id.substr(3));
	this.filters.splice(no,1);
	$(this.curFilter).parent().remove();
	this.curFilter = null;
	if(this.filters.length)
	{
		for(var i=no+1; i<this.filters.length+1; i++)
		{
			$("#_fn"+i).attr("id", "_fn"+(i-1));
			$("#_fe"+i).attr("id", "_fe"+(i-1));
		}
		if(no>=this.filters.length)
			no = no - 1;
		$("#_fn"+no).focus();	
	}
	else
	{
		if(plugin.editFilersBtn)
			plugin.editFilersBtn.hide();
		$('#FLT_body,#FLT_exclude,#FLTdir_edit,#FLT_label,#FLT_rss,#FLT_throttle,#FLT_ratio').val('');
		$('#FLTnot_add_path,#FLTchkdesc,#FLTchklink,#FLTtorrents_start_stopped').attr("checked",false);
		$('#FLTchktitle').attr("checked",true);
		$('#FLT_interval').val(-1);
	}
}

theWebUI.checkCurrentFilter = function()
{
	if(this.curFilter)
		this.request("?action=checkfilter",[this.showFilterResults, this]);
}

theWebUI.showFilterResults = function( d )
{
	this.showErrors(d);
	if(d.rss.length)
		this.switchRSSLabel($$(d.rss));
	else
		this.switchRSSLabel($$('_rssAll_'));
	var table = this.getTable("rss");
	for(var k in table.rowSel)
		table.rowSel[k] = false;
	this.getTable("trt").selCount = d.list.length;
	for(var i = 0; i<d.list.length; i++)
		table.rowSel[d.list[i]] = true;
	table.refreshSelection();
	alert(theUILang.foundedByFilter+" : "+d.list.length);
}

theWebUI.setFilters = function()
{
	this.request("?action=setfilters",[this.addRSSItems, this]);
}

theWebUI.rssClearFilter = function()
{
        if(this.curFilter)
        {
		var no = theWebUI.storeFilterParams();
		var flt = theWebUI.filters[no];
		if(flt.interval>=0)
			this.request("?action=clearfiltertime&v="+flt.no);
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
			$("#RSSList").width( w );
			if(theWebUI.configured)
		       	       	this.getTable("rss").resize( w );
		}
        	if(h!==null)
		{
			$("#RSSList").height( h );
			if(theWebUI.configured)
				this.getTable("rss").resize(null,h); 
	       	}
	}
}

rTorrentStub.prototype.clearfiltertime = function()
{
	this.content = "mode=clearfiltertime&no="+this.vs[0];
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.getrssdetails = function()
{
	this.content = "mode=getdesc&href="+encodeURIComponent(theWebUI.currentRSSDetailsID)+"&rss="+theWebUI.rssItems[theWebUI.currentRSSDetailsID].rss;
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.getrssdetailsResponse = function(xml)
{
	var datas = xml.getElementsByTagName('data');
        $("#rsslayout").html(datas[0].childNodes[0].data);
	$("a","#rsslayout").each( function(ndx,val) 
	{ 
		val.target = "_blank";
	});
	$("img","#rsslayout").each( function(ndx,val) 
	{ 
		val.onload = null;
	});
	return(false);
}

rTorrentStub.prototype.setfilters = function()
{
	this.content = "mode=setfilters";
	theWebUI.storeFilterParams();
	for(var i=0; i<theWebUI.filters.length; i++)
	{
		var flt = theWebUI.filters[i];
		var enabled = $("#_fe"+i).attr("checked") ? 1 : 0;
		var name = $("#_fn"+i).val();
		this.content = this.content+"&name="+encodeURIComponent(name)+"&pattern="+encodeURIComponent(flt.pattern)+"&enabled="+enabled+
			"&chktitle="+flt.chktitle+
			"&chklink="+flt.chklink+
			"&chkdesc="+flt.chkdesc+
		        "&exclude="+encodeURIComponent(flt.exclude)+
			"&hash="+flt.hash+"&start="+flt.start+"&addPath="+flt.add_path+
			"&dir="+encodeURIComponent(flt.dir)+"&label="+encodeURIComponent(flt.label)+"&interval="+flt.interval+"&no="+flt.no;
		if($type(flt.throttle))
			this.content+=("&throttle="+flt.throttle);
		if($type(flt.ratio))
			this.content+=("&ratio="+flt.ratio);
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.checkfilter = function()
{
	var no = theWebUI.storeFilterParams();
	var flt = theWebUI.filters[no];
	this.content = "mode=checkfilter&pattern="+encodeURIComponent(flt.pattern)+"&exclude="+encodeURIComponent(flt.exclude)+
		"&chktitle="+flt.chktitle+"&chklink="+flt.chklink+"&chkdesc="+flt.chkdesc;
	if(flt.hash.length)
		this.content = this.content+"&rss="+flt.hash;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.addrss = function()
{
	this.content = "mode=add&url="+this.vs[0]+"&label="+this.ss[0];
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.editrss = function()
{
	this.content = "mode=edit&url="+this.vs[0]+"&label="+this.ss[0];
	if(theWebUI.actRSSLbl && (theWebUI.actRSSLbl != "_rssAll_"))
		this.content = this.content + "&rss=" + theWebUI.actRSSLbl;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.loadrss = function()
{
	this.content = "mode=get";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.loadrsstorrents = function()
{
	this.content = "mode=loadtorrents";
	if($("#RSStorrents_start_stopped").attr("checked"))
		this.content = this.content + '&torrents_start_stopped=1';
	if($("#RSSnot_add_path").attr("checked"))
		this.content = this.content + '&not_add_path=1';
	var dir = $.trim($("#RSSdir_edit").val());
	if(dir.length)
		this.content = this.content + '&dir_edit='+encodeURIComponent(dir);
	var lbl = $.trim($("#RSS_label").val());
	if(lbl.length)
		this.content = this.content + '&label='+encodeURIComponent(lbl);
	for(var i = 0; i<theWebUI.rssArray.length; i++)
	{
		var item = theWebUI.rssItems[theWebUI.rssArray[i]];
		this.content = this.content + '&rss='+item.rss+'&url='+encodeURIComponent(item.href);
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.loadrsstorrentsResponse = function(data)
{
	theWebUI.getTorrents("list=1");
	return(data);
}

rTorrentStub.prototype.clearhistory = function()
{
	this.content = "mode=clearhistory";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.rssrefresh = function()
{
	this.content = "mode=refresh";
	if(theWebUI.actRSSLbl && (theWebUI.actRSSLbl != "_rssAll_"))
		this.content = this.content + "&rss=" + theWebUI.actRSSLbl;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.rsstoggle = function()
{
	this.content = "mode=toggle";
	if(theWebUI.actRSSLbl && (theWebUI.actRSSLbl != "_rssAll_"))
		this.content = this.content + "&rss=" + theWebUI.actRSSLbl;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.rssmarkstate = function()
{
	this.content = "mode=mark&state="+this.ss[0];
	for( var i=0; i<theWebUI.rssArray.length; i++)
		this.content+=("&url="+encodeURIComponent(theWebUI.rssArray[i]));
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.rssremove = function()
{
	this.content = "mode=remove";
	if(theWebUI.actRSSLbl && (theWebUI.actRSSLbl != "_rssAll_"))
		this.content = this.content + "&rss=" + theWebUI.actRSSLbl;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.getfilters = function()
{
	this.content = "mode=getfilters";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.getintervals = function()
{
	this.content = "mode=getintervals";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

plugin.correctRatioFilterDialog = function()
{
	var rule = getCSSRule(".rf fieldset");
	if(rule && thePlugins.get('ratio').allStuffLoaded)
	{
/*
		var addition = (browser.isIE) ? 40 : 32;
		rule.style.height = (iv(rule.style.height)+addition)+"px";
		$$('filterPropsFieldSet').style.height = rule.style.height;
		rule = getCSSRule(".rf");
		if(rule)
		{
			var delta = (browser.isKonqueror) ? 5 : 0;
			rule.style.height= (iv(rule.style.height)+addition+delta)+"px";
			$$('filterProps').style.height = rule.style.height;
		}
		rule = getCSSRule("div#dlgEditFilters");
		if(rule)
		{
			rule.style.height= (iv(rule.style.height)+addition)+"px";
			$$('dlgEditFilters').style.height = rule.style.height;
		}
		rule = getCSSRule(".lf");
		if(rule)
		{
			rule.style.height= (iv(rule.style.height)+addition)+"px";
			$$('filterList').style.height = rule.style.height;
		}
*/
		$("#FLT_label").after( $("<div></div>").css({ padding: 0 }).
			html("<label>"+theUILang.ratio+":</label><select id='FLT_ratio'><option value=''>"+theUILang.mnuRatioUnlimited+"</option></select>") );

		$$('filterProps').style.height = "auto";
		$("#FLT_label").parent().get(0).style.height = "auto";
		$$('dlgEditFilters').style.height = "auto";	

		theDialogManager.center("dlgEditFilters");
	}
	else
		setTimeout(plugin.correctRatioFilterDialog,1000);
}

plugin.correctFilterDialog = function()
{
	var rule = getCSSRule(".rf fieldset");
	if(rule && thePlugins.get('throttle').allStuffLoaded)
	{
/*
		var addition = (browser.isIE) ? 40 : 32;
		rule.style.height = (iv(rule.style.height)+addition)+"px";
		$$('filterPropsFieldSet').style.height = rule.style.height;
		rule = getCSSRule(".rf");
		if(rule)
		{
			var delta = (browser.isKonqueror) ? 5 : 0;
			rule.style.height= (iv(rule.style.height)+addition+delta)+"px";
			$$('filterProps').style.height = rule.style.height;
		}
		rule = getCSSRule("div#dlgEditFilters");
		if(rule)
		{
			rule.style.height= (iv(rule.style.height)+addition)+"px";
			$$('dlgEditFilters').style.height = rule.style.height;
		}
		rule = getCSSRule(".lf");
		if(rule)
		{
			rule.style.height= (iv(rule.style.height)+addition)+"px";
			$$('filterList').style.height = rule.style.height;
		}
*/
		$("#FLT_label").after( $("<div></div>").css({ padding: 0 }).
			html(  "<label>"+theUILang.throttle+":</label><select id='FLT_throttle'><option value=''>"+theUILang.mnuUnlimited+"</option></select><br/>" ) );
		if(thePlugins.isInstalled('ratio'))
			plugin.correctRatioFilterDialog();
		else
		{
			$$('filterProps').style.height = "auto";
			$("#FLT_label").parent().get(0).style.height = "auto";
			$$('dlgEditFilters').style.height = "auto";	
			theDialogManager.center("dlgEditFilters");
		}
	}
	else
		setTimeout(plugin.correctFilterDialog,1000);
}

plugin.correctCSS = function()
{
        if(!this.cssCorrected)
        {
		var rule = getCSSRule("div#List");
        	var rule1 = getCSSRule("div#RSSList");
	        var ruleMain = getCSSRule("html, body");
        	if(!ruleMain)
        		ruleMain = getCSSRule("html");
		if(rule && rule1)
		{
			rule1.style.borderColor = rule.style.borderColor;
			rule1.style.backgroundColor = rule.style.backgroundColor;
		}
		rule = getCSSRule("div#CatList ul li.sel");
		rule1 = getCSSRule("div#CatList ul li.selRSS");
		rule2 = getCSSRule("div#CatList ul li.selDisRSS");
		rule3 = getCSSRule(".lf li input.TextboxFocus");
		if(rule && rule1 && rule2 && rule3 && ruleMain)
		{
			rule1.style.backgroundColor = rule.style.backgroundColor;
			rule1.style.color = rule.style.color;
			rule2.style.backgroundColor = rule.style.backgroundColor;
			rule2.style.color = rule.style.color;
			rule3.style.backgroundColor = rule.style.backgroundColor;
			rule3.style.color = rule.style.color;
		}
		rule = getCSSRule("div#stg .lm");
	        rule1 = getCSSRule(".lf");
        	rule2 = getCSSRule(".lf li input.TextboxNormal");
		if(rule && rule1 && rule2 && ruleMain)
		{
			rule1.style.borderColor = rule.style.borderColor;
			rule1.style.backgroundColor = rule.style.backgroundColor;
			rule2.style.backgroundColor = rule.style.backgroundColor;
			rule2.style.color = ruleMain.style.color;
		}
		rule = getCSSRule(".stg_con");
	        rule1 = getCSSRule(".rf");
        	if(rule && rule1)
			rule1.style.backgroundColor = rule.style.backgroundColor;
		this.cssCorrected = true;
	}
}

plugin.onLangLoaded = function()
{
        this.addButtonToToolbar("rss",theUILang.mnu_rss,"theWebUI.showRSS()","settings");

        var el = $('#CatList');
	var ul = $("<ul>");
	if($$("pstate"))
	{
	        el.append($("<div>").addClass("catpanel").attr("id","prss").text(theUILang.rssFeeds).click(function() { theWebUI.togglePanel(this); }));
		var div = $("<div>").attr("id","prss_cont");
		el.append(div);
		el = div;
		ul.html('<li id="_rssAll_" class="RSS cat">'+theUILang.allFeeds+'&nbsp;(<span id="_rssAll_c">0</span>)</li>');
	}
	else
		ul.html('<li id="_hr_"><hr /></li><li id="_rssAll_" class="RSS cat">'+theUILang.allFeeds+'&nbsp;(<span id="_rssAll_c">0</span>)</li>');
	el.append(ul).append( $("<div>").html('<ul id="rssl"></ul>') );
	$("#_rssAll_").mouseclick( theWebUI.rssLabelContextMenu );

	theDialogManager.make( "dlgAddRSS", theUILang.addRSS,
		"<div class='content'>"+
			"<label>"+theUILang.feedURL+": </label>"+
			"<input type='text' id='rssURL' class='TextboxLarge'/><br/>"+
			"<label>"+theUILang.alias+": </label>"+
			"<input type='text' id='rssLabel' class='TextboxLarge'/>"+
		"</div>"+
		"<div class='aright buttons-list'><input type='button' class='Button' value="+theUILang.ok+" onclick='theDialogManager.hide(\"dlgAddRSS\");theWebUI.addRSS();return(false);'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",
		true);
	theDialogManager.make( "dlgEditRSS", theUILang.rssMenuEdit,
		"<div class='content'>"+
			"<label>"+theUILang.feedURL+": </label>"+
			"<input type='text' id='editrssURL' class='TextboxLarge'/><br/>"+
			"<label>"+theUILang.alias+": </label>"+
			"<input type='text' id='editrssLabel' class='TextboxLarge'/>"+
		"</div>"+
		"<div class='aright buttons-list'><input type='button' class='Button' value="+theUILang.ok+" onclick='theDialogManager.hide(\"dlgEditRSS\");theWebUI.editRSS();return(false);'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",
		true);
	theDialogManager.make( "dlgLoadTorrents", theUILang.torrent_add,
		"<div class='content'>"+
			"<label>"+theUILang.Base_directory+":</label><input type='text' id='RSSdir_edit' class='TextboxLarge'/><br/>"+
			"<label></label><input type='checkbox' id='RSSnot_add_path'/>"+theUILang.Dont_add_tname+"<br/>"+
			"<label></label><input type='checkbox' id='RSStorrents_start_stopped'/>"+theUILang.Dnt_start_down_auto+"<br/>"+
			"<label>"+theUILang.Label+":</label><input type='text' id='RSS_label' class='TextboxLarge'/>"+
		"</div>"+
		"<div id='buttons' class='aright buttons-list'><input type='button' class='Button' value="+theUILang.ok+" onclick='theDialogManager.hide(\"dlgLoadTorrents\");theWebUI.RSSLoadTorrents();return(false);'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",
		true);
	theDialogManager.make( "dlgEditFilters", theUILang.rssMenuManager,
		"<div class='fxcaret'>"+
			"<div class='lfc'>"+
				"<div class='lf' id='filterList'>"+
					"<ul id='fltlist'></ul>"+
				"</div>"+
				"<div id='FLTchk_buttons'>"+
					"<input type='button' class='Button' value='"+theUILang.rssAddFilter+"' onclick='theWebUI.addNewFilter();return(false);'/>"+
					"<input type='button' class='Button' value='"+theUILang.rssDelFilter+"' onclick='theWebUI.deleteCurrentFilter();return(false);'/>"+
					"<input type='button' id='chkFilterBtn' class='Button' value='"+theUILang.rssCheckFilter+"' onclick='theWebUI.checkCurrentFilter();return(false);'/>"+
				"</div>"+
			"</div>"+
			"<div class='rf' id='filterProps'>"+
				"<fieldset id='filterPropsFieldSet'>"+
					"<legend>"+theUILang.rssFiltersLegend+"</legend>"+
					"<label>"+theUILang.rssFilter+":</label><input type='text' id='FLT_body' class='TextboxLarge'/><br/>"+
					"<label>"+theUILang.rssExclude+":</label><input type='text' id='FLT_exclude' class='TextboxLarge'/><br/>"+
					"<label></label><input type='checkbox' class='chk' id='FLTchktitle'/>"+theUILang.rssCheckTitle+"<br/>"+
					"<label></label><input type='checkbox' class='chk'id='FLTchkdesc'/>"+theUILang.rssCheckDescription+"<br/>"+
					"<label></label><input type='checkbox' class='chk' id='FLTchklink'/>"+theUILang.rssCheckLink+"<br/>"+
					"<label>"+theUILang.rssStatus+":</label><select id='FLT_rss'><option value=''>"+theUILang.allFeeds+"</option></select><br/>"+
					"<label>"+theUILang.Base_directory+":</label><input type='text' id='FLTdir_edit' class='TextboxLarge'/><br/>"+
					"<label></label><input type='checkbox' class='chk'id='FLTnot_add_path'/>"+theUILang.Dont_add_tname+"<br/>"+
					"<label></label><input type='checkbox' class='chk'id='FLTtorrents_start_stopped'/>"+theUILang.Dnt_start_down_auto+"<br/>"+
					"<label>"+theUILang.rssMinInterval+":</label><select id='FLT_interval'>"+
					        "<option value='-1'>"+theUILang.rssIntervalAlways+"</option>"+
					        "<option value='0'>"+theUILang.rssIntervalOnce+"</option>"+
					        "<option value='12'>"+theUILang.rssInterval12h+"</option>"+
					        "<option value='24'>"+theUILang.rssInterval1d+"</option>"+
					        "<option value='48'>"+theUILang.rssInterval2d+"</option>"+
					        "<option value='72'>"+theUILang.rssInterval3d+"</option>"+
					        "<option value='96'>"+theUILang.rssInterval4d+"</option>"+
					        "<option value='168'>"+theUILang.rssInterval1w+"</option>"+
					        "<option value='336'>"+theUILang.rssInterval2w+"</option>"+
					        "<option value='504'>"+theUILang.rssInterval3w+"</option>"+
					        "<option value='720'>"+theUILang.rssInterval1m+"</option>"+
						"</select>"+
						"<input type='button' class='Button' value='"+theUILang.rssClearFilter+"' onclick='theWebUI.rssClearFilter();return(false);'/><br/>"+
					"<label>"+theUILang.Label+":</label><input type='text' id='FLT_label' class='TextboxLarge'/><br/>"+
				"</fieldset>"+
			"</div>"+
		"</div>"+
		"<div id='FLT_buttons' class='aright buttons-list'>"+
			"<input type='button' class='Button' value='"+theUILang.ok+"' onclick='theDialogManager.hide(\"dlgEditFilters\");theWebUI.setFilters();return(false);'/>"+
			"<input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/>"+
		"</div>");
	$("#gcont").append( $("<div>").attr("id","rsslayout").css( "display", "none" ));

	if(thePlugins.isInstalled("_getdir"))
	{
		$('#RSSdir_edit').after($("<input type=button>").addClass("Button").width(30).attr("id","RSSBtn").focus( function() { this.blur(); } ));
		var btn = new theWebUI.rDirBrowser( 'dlgLoadTorrents', 'RSSdir_edit', 'RSSBtn' );
		theDialogManager.setHandler('dlgLoadTorrents','afterHide',function()
		{
			btn.hide();
		});
		$('#FLTdir_edit').after($("<input type=button>").addClass("Button").width(30).attr("id","FLTBtn").focus( function() { this.blur(); } ));
		plugin.editFilersBtn = new theWebUI.rDirBrowser( 'dlgEditFilters', 'FLTdir_edit', 'FLTBtn' );
	}

	if(thePlugins.isInstalled('throttle'))
		this.correctFilterDialog();
	else
	if(thePlugins.isInstalled('ratio'))
		this.correctRatioFilterDialog();
};

plugin.onRemove = function()
{
        if(theWebUI.updateRSSTimer)
	        window.clearTimeout(theWebUI.updateRSSTimer);
	theWebUI.switchLayout(false);
	theWebUI.switchLabel($$("-_-_-all-_-_-"));
	$("#RSSList").remove();
	$("#prss").remove();
	$("#prss_cont").remove();
	$("#rsslayout").remove();
	theDialogManager.hide("dlgAddRSS");
	theDialogManager.hide("dlgEditRSS");
	theDialogManager.hide("dlgLoadTorrents");
	theDialogManager.hide("dlgEditFilters");
	this.removeButtonFromToolbar("rss");
}
