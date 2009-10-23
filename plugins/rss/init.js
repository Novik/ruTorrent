var plugin = new rPlugin("rss");
plugin.loadMainCSS();
if(browser.isIE && browser.versionMajor < 8)
{
	plugin.loadCSS("ie");
	if(browser.isOldIE)
		plugin.loadCSS("ie6");
}
if(browser.isKonqueror)
	plugin.loadCSS("konqueror");


function isVisible(obj) 
{
	return((obj.style.display == "block") || (obj.style.display == ""));
}

function show(obj) 
{
	if(!isVisible(obj))
	{
		obj.style.display = "block";
		return(true);
	}
	return(false);
}

function hide(obj) 
{
	if(isVisible(obj))
	{
		obj.style.display = "none";
		return(true);
	}
	return(false);
}

utWebUI.oldSwitchLabel = utWebUI.switchLabel;
utWebUI.switchLabel = function(el)
{
        var lst = $$("RSSList");
	if(isVisible(lst))
	{
		utWebUI.trtTable.clearSelection();
		utWebUI.dID = "";
		utWebUI.clearDetails();
		utWebUI.rssTable.clearSelection();
		if(utWebUI.actRSSLbl)
			$$(utWebUI.actRSSLbl).className = utWebUI.isActiveRSSEnabled() ? "RSS" : "disRSS";
		utWebUI.actRSSLbl = null;
		utWebUI.actLbl = "";
		show($$("List"));
		hide(lst);
		utWebUI.switchLayout(false);
	}
	utWebUI.oldSwitchLabel(el);
}

utWebUI.isActiveRSSEnabled = function()
{
	return((utWebUI.actRSSLbl == "_rssAll_") || (utWebUI.rssLabels[utWebUI.actRSSLbl].enabled==1));
}

utWebUI.currentRSSDetailsID = null;
utWebUI.updateRSSDetails = function(id)
{
	utWebUI.currentRSSDetailsID = id;
	if(id)
		this.Request("?action=getrssdetails");
	else
		$$("rsslayout").innerHTML = '';
}

utWebUI.switchLayout = function(toRSS,id)
{
	if(toRSS)
	{
		show($$("rsslayout"));
		hide($$("mainlayout"));
		utWebUI.updateRSSDetails(id);
	}
	else
	{
		show($$("mainlayout"));
		hide($$("rsslayout"));
	}
}

utWebUI.switchRSSLabel = function(el,force)
{
	if((el.id == utWebUI.actRSSLbl) && !force)
		return;
	if(utWebUI.actRSSLbl)
		$$(utWebUI.actRSSLbl).className = utWebUI.isActiveRSSEnabled() ? "RSS" : "disRSS";
	utWebUI.actRSSLbl = el.id;
	el.className = utWebUI.isActiveRSSEnabled() ? "selRSS" : "selDisRSS";
	for(var k in utWebUI.rssItems)
	{
		if((utWebUI.actRSSLbl == "_rssAll_") || (utWebUI.rssTable.getAttr(k, "rss")==utWebUI.actRSSLbl))
			utWebUI.rssTable.unhideRow(k);
		else
			utWebUI.rssTable.hideRow(k);
	}
	utWebUI.rssTable.clearSelection();
	var lst = $$("List");
	if(isVisible(lst))
	{
		utWebUI.trtTable.clearSelection();
		utWebUI.dID = "";
		utWebUI.clearDetails();
		if((this.actLbl != "") && ($$(this.actLbl) != null))
			$$(utWebUI.actLbl).className = "";
		var rss = $$("RSSList");
		if(!utWebUI.cssCorrected)
		{
			correctCSS();
			utWebUI.cssCorrected = true;
		}
		rss.style.width = lst.style.width;
		rss.style.height = lst.style.height;
		utWebUI.rssTable.resize(iv(rss.style.width),iv(rss.style.height));
		utWebUI.rssTable.calcSize();
		show(rss);
		hide(lst);
	}
	utWebUI.switchLayout(true);
	utWebUI.rssTable.refreshRows();
}

function correctCSS()
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
		rule2.style.backgroundColor = rule.style.backgroundColor;
		rule3.style.backgroundColor = rule.style.backgroundColor;
		rule3.style.color = ruleMain.style.color;
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
	{
		rule1.style.backgroundColor = rule.style.backgroundColor;
	}
}

utWebUI.allRSSStuffLoaded = false;
utWebUI.allRSSStuffInited = false;
utWebUI.oldConfig = utWebUI.config;
utWebUI.newConfig = function()
{
	if(!utWebUI.allRSSStuffLoaded)
	{
		setTimeout(utWebUI.newConfig,1000);
		return;
	}
	if(typeof $_COOKIE["webui.rss.colorder"] != "undefined") 
	{
		utWebUI.rssTable.colOrder = $_COOKIE["webui.rss.colorder"];
	}
	if(typeof $_COOKIE["webui.rss.sindex"] != "undefined") 
	{
		utWebUI.rssTable.sIndex = iv($_COOKIE["webui.rss.sindex"]);
	}
	if(typeof $_COOKIE["webui.rss.rev"] != "undefined") 
	{
		utWebUI.rssTable.reverse = iv($_COOKIE["webui.rss.rev"]);
	}
	utWebUI.rssTable.colorEvenRows = (utWebUI.bAltCol) ? true : false;
	utWebUI.rssSortR = 0;
	if(typeof $_COOKIE["webui.rss.sortrev"] != "undefined") 
	{
		utWebUI.rssSortR = iv($_COOKIE["webui.rss.sortrev"]);
	}
	utWebUI.rssTable.maxRows = utWebUI.minRows;
	var _3 = new Array();
	utWebUI.rssColumns = new Array();

	for( var i in utWebUI.trtColumns )
		utWebUI.rssColumns.push( cloneObject(utWebUI.trtColumns[i]) );

	if($_COOKIE["webui.rss.colwidth"] != null) 
	{
		_3 = $_COOKIE["webui.rss.colwidth"];
		for(var i in _3)
		{
			if(!browser.isAppleWebKit && !browser.isKonqueror && (_3[i]>4))
				_3[i]-=4;
			if(i<utWebUI.rssColumns.length)
				utWebUI.rssColumns[i].width = _3[i] + "px";
		}
	}
	if($_COOKIE["webui.rss.colenabled"] != null) 
	{
		_3 = $_COOKIE["webui.rss.colenabled"];
		for(var i in _3)
			if(i<utWebUI.rssColumns.length)
				utWebUI.rssColumns[i].enabled = _3[i];
	}
	utWebUI.rssTable.create($$("RSSList"), utWebUI.rssColumns, "utWebUI.rssTable");
	utWebUI.rssTable.onresize = utWebUI.Save;
	utWebUI.rssTable.oncoltoggled = utWebUI.Save;
	utWebUI.rssTable.reverse = utWebUI.rssSortR;
	utWebUI.allRSSStuffInited = true;
	utWebUI.Request("?action=getintervals",[utWebUI.getRSSIntervals, this]);
}

utWebUI.config = function()
{
	utWebUI.rssLabels = new Object();
	utWebUI.rssItems = new Object();
	utWebUI.actRSSLbl = null;
	utWebUI.updateRSSTimer = null;
	utWebUI.updateRSSInterval = 5*60*1000;
	utWebUI.rssUpdateInProgress = false;
	utWebUI.rssID = "";
	utWebUI.cssCorrected = false;
	utWebUI.rssArray = new Array();

	var rssList = document.createElement('DIV');
	rssList.id = "RSSList";
	$$("List").parentNode.appendChild(rssList);
	Hide("RSSList");

	utWebUI.rssTable = new dxSTable();
	utWebUI.rssTable.onmove = utWebUI.Save;
	utWebUI.rssTable.onsort = utWebUI.rssSort;
	utWebUI.rssTable.onselect = utWebUI.rssSelect;
	utWebUI.rssTable.ondblclick = utWebUI.rssDblClick;
        utWebUI.rssTable.format = FormatTL;
	this.oldConfig();
	this.newConfig();
}

utWebUI.rssDblClick = function( obj )
{
	if(typeof utWebUI.torrents[utWebUI.rssItems[obj.id].hash] != "undefined")
	{
		var tmp = new Object();
                tmp.id = utWebUI.rssItems[obj.id].hash
        	utWebUI.trtTable.ondblclick( tmp );
        	delete tmp;
	}
	else
		window.open(obj.id,"_blank");
}

utWebUI.getRSSIntervals = function( data )
{
        utWebUI.loadRSS();
	var d = eval("(" + data + ")");
	utWebUI.updateRSSInterval = d.interval*60000;	
	utWebUI.updateRSSTimer = window.setTimeout("updateRSS()", d.next*1000);
}

utWebUI.rssSort = function() 
{
	if((utWebUI.rssTable.sIndex != iv($_COOKIE["webui.rss.sindex"])) || 
		(utWebUI.rssTable.reverse != iv($_COOKIE["webui.rss.sortrev"]))) 
	{
		utWebUI.Save();
	}
}

utWebUI.RSSOpen = function()
{
	for(var i = 0; i<this.rssArray.length; i++)
		window.open(this.rssArray[i],"_blank");
}

utWebUI.RSSLoad = function()
{
	ShowModal("dlgLoadTorrents");
}

utWebUI.RSSLoadTorrents = function()
{
	this.Request("?action=loadrsstorrents",[this.addRSSItems, this]);
}

utWebUI.RSSClearHistory = function()
{
	this.Request("?action=clearhistory",[this.addRSSItems, this]);
}

utWebUI.RSSRefresh = function()
{
	this.RequestWithTimeout("?action=rssrefresh",[this.addRSSItems, this],retryRequest);
}

utWebUI.RSSToggleStatus = function()
{
	this.Request("?action=rsstoggle",[this.addRSSItems, this]);
}

utWebUI.doRSSDelete = function()
{
	utWebUI.Request("?action=rssremove",[this.addRSSItems, this]);
}

utWebUI.RSSDelete = function()
{
	if(utWebUI.bConfDel)
		askYesNo( WUILang.rssMenuDelete, WUILang.rssDeletePrompt, "utWebUI.doRSSDelete()" );
	else
		utWebUI.doRSSDelete();
}

utWebUI.RSSEdit = function()
{
	if(utWebUI.actRSSLbl && utWebUI.rssLabels[this.actRSSLbl])
	{
		$$('editrssURL').value = utWebUI.rssLabels[this.actRSSLbl].url; 
		$$('editrssLabel').value = utWebUI.rssLabels[this.actRSSLbl].name;
		ShowModal("dlgEditRSS");
	}
}

utWebUI.RSSManager = function()
{
	utWebUI.Request("?action=getfilters",[this.loadFilters, this]);
}

function rssLabelContextMenu(obj)
{
	utWebUI.trtTable.clearSelection();
	utWebUI.rssTable.clearSelection();
	utWebUI.switchRSSLabel(obj);
	utWebUI.rssTable.fillSelection();
	utWebUI.createRSSMenu(null, null);
	ContextMenu.show();
}

utWebUI.createRSSMenuPrim = function()
{
	ContextMenu.add([ WUILang.rssMenuClearHistory, "utWebUI.RSSClearHistory()"]);
	ContextMenu.add([ WUILang.addRSS, "utWebUI.showRSS()"]);
	ContextMenu.add([ WUILang.rssMenuManager, "utWebUI.RSSManager()"]);
	if(utWebUI.actRSSLbl) 
	{
		if(this.actRSSLbl == "_rssAll_")
		{
			ContextMenu.add([ WUILang.rssMenuDisable ]);
			ContextMenu.add([ WUILang.rssMenuEdit ]);
			ContextMenu.add([ WUILang.rssMenuRefresh, "utWebUI.RSSRefresh()"]);
			ContextMenu.add([ WUILang.rssMenuDelete ]);
		}
		else
		{
			if(this.rssLabels[this.actRSSLbl].enabled==1)
			{
				ContextMenu.add([ WUILang.rssMenuDisable, "utWebUI.RSSToggleStatus()"]);
				ContextMenu.add([ WUILang.rssMenuRefresh, "utWebUI.RSSRefresh()"]);
			}
			else
			{
				ContextMenu.add([ WUILang.rssMenuEnable, "utWebUI.RSSToggleStatus()"]);
				ContextMenu.add([ WUILang.rssMenuRefresh ]);
			}
			ContextMenu.add([ WUILang.rssMenuEdit, "utWebUI.RSSEdit()"]);
			ContextMenu.add([ WUILang.rssMenuDelete, "utWebUI.RSSDelete()"]);
		}
	}
	utWebUI.dID = "";
	utWebUI.clearDetails();
}

utWebUI.RSSAddToFilter = function()
{
	utWebUI.Request("?action=getfilters",[this.loadFiltersWithAdditions, this]);
}

utWebUI.createRSSMenu = function(e, id) 
{
	var sr = this.rssTable.rowSel;
	var trtArray = new Array();
	this.rssArray = new Array();
	for(var k in sr) 
	{
		if(sr[k] == true)
		{
			var hash = this.rssItems[k].hash;
			if(hash && (typeof utWebUI.torrents[hash] != "undefined"))
				trtArray.push(hash);
			else
				this.rssArray.push(k);
		}
	}
	ContextMenu.clear();
	if(this.rssArray.length)
	{
		ContextMenu.add([ WUILang.rssMenuLoad, "utWebUI.RSSLoad()"]);
		ContextMenu.add([ WUILang.rssMenuOpen, "utWebUI.RSSOpen()"]);
		ContextMenu.add([ WUILang.rssMenuAddToFilter, "utWebUI.RSSAddToFilter()"]);
		ContextMenu.add([CMENU_SEP]);
		utWebUI.createRSSMenuPrim();
	}
	else
	if(trtArray.length)
	{
		for(var k in this.trtTable.rowSel)
			this.trtTable.rowSel[k] = false;
		this.trtTable.selCount = trtArray.length;
		for(var i = 0; i<trtArray.length; i++)
			this.trtTable.rowSel[trtArray[i]] = true;
		this.trtTable.refreshSelection();
		this.dID = trtArray[0];
		utWebUI.createMenu(e, trtArray[0]);
		ContextMenu.add([CMENU_SEP]);
		utWebUI.createRSSMenuPrim();
	}
	else
		utWebUI.createRSSMenuPrim();
}

utWebUI.rssSelect = function(e, id)
{
	var sr = utWebUI.rssTable.rowSel;
	var trtArray = new Array();
	for(var k in sr) 
	{
		if(sr[k] == true)
		{
			var hash = utWebUI.rssItems[k].hash;
			if(hash && (typeof utWebUI.torrents[hash] != "undefined"))
				trtArray.push(hash);
		}
	}
	for(var k in utWebUI.trtTable.rowSel)
		utWebUI.trtTable.rowSel[k] = false;
	utWebUI.trtTable.selCount = trtArray.length;
	for(var i = 0; i<trtArray.length; i++)
		utWebUI.trtTable.rowSel[trtArray[i]] = true;
	utWebUI.trtTable.refreshSelection();
	if(e.button == 2) 
	{
		if(typeof utWebUI.torrents[utWebUI.rssItems[id].hash] != "undefined")
			utWebUI.trtSelect(e, utWebUI.rssItems[id].hash);
 		else
		{
			utWebUI.dID = "";
			utWebUI.clearDetails();
			utWebUI.createRSSMenu(e, id);
			ContextMenu.show();
		}
	}
	else
	{
		if((utWebUI.rssTable.selCount==1) && (trtArray.length>0))
			utWebUI.showDetails(trtArray[0], false);
		else
		{
			utWebUI.dID = "";
			utWebUI.clearDetails();
		}
	}
	utWebUI.switchLayout(typeof utWebUI.torrents[utWebUI.rssItems[id].hash] == "undefined",id);
}

utWebUI.oldSave = utWebUI.Save;
utWebUI.Save = function()
{
	if(utWebUI.allRSSStuffInited)
	{
		var i, l = utWebUI.rssTable.cols, aWidth = new Array(l), ind, col;
		var aEnabled = new Array(l);
		for(i = 0; i < l; i++) 
		{
			aWidth[i] = utWebUI.rssTable.getColWidth(i);
			aEnabled[i] = utWebUI.rssTable.isColumnEnabled(i);
		}
		cookies.setCookie("webui.rss.colwidth", aWidth);
		cookies.setCookie("webui.rss.colenabled", aEnabled);
		cookies.setCookie("webui.rss.colorder", utWebUI.rssTable.colOrder);
		cookies.setCookie("webui.rss.sindex", utWebUI.rssTable.sIndex);
		cookies.setCookie("webui.rss.rev", utWebUI.rssTable.reverse);
		cookies.setCookie("webui.rss.sortrev", utWebUI.rssTable.reverse);
	}
	utWebUI.oldSave();
}

utWebUI.oldSetSettings = utWebUI.setSettings;
utWebUI.setSettings = function()
{
	utWebUI.oldSetSettings();
	if(utWebUI.allRSSStuffInited)
	{
		utWebUI.rssTable.colorEvenRows = (this.bAltCol) ? true : false;
		utWebUI.rssTable.refreshSelection();
		utWebUI.rssTable.maxRows = this.minRows;
		utWebUI.rssTable.refreshRows();
	}
}

utWebUI.oldLoadTorrents = utWebUI.loadTorrents;
utWebUI.loadTorrents = function()
{
	utWebUI.oldLoadTorrents();
	if(utWebUI.allRSSStuffInited)
	{
		var updated = false;
		for(var href in this.rssItems)
		{
			var item = this.rssItems[href];
			if((item.hash!="") && (typeof this.torrents[item.hash] != "undefined"))
			{
				updated = this.rssTable.updateRowFrom(this.trtTable,item.hash,href) || updated;
			}
			else
			{
				var label = this.rssTable.getAttr(href, "label");
				var arr = [item.title,(item.hash=="") ? WUILang.rssStatus : (item.hash=="Failed") ? WUILang.rssStatusError+" ("+item.errcount+")" : WUILang.rssStatusLoaded,
					null,null,null,null,null,null,null,null,label,null,null,null,item.time,null];
				while(arr.length<this.rssTable.cols)
					arr.push(null);
				updated = this.rssTable.setValues(href,arr) || updated; 
				updated = this.rssTable.setIcon(href,"Status_RSS") || updated;
			}
		}
		if(updated)
		{
			if(this.rssTable.sIndex !=- 1)
				this.rssTable.Sort();
		}
	}
}

oldSepMove = SepMove;
SepMove = function(e, dir) 
{
	oldSepMove(e, dir);
	utWebUI.rssTable.isResizing = true;
	$$("RSSList").style.width = $$("List").style.width;
	$$("RSSList").style.height = $$("List").style.height;
}

oldSepUp = SepUp;
SepUp = function(e, dir) 
{
	oldSepUp(e, dir);
	_isResizing = dir;
	utWebUI.rssTable.isResizing = false;
	utWebUI.rssTable.resize(iv($$("RSSList").style.width),iv($$("RSSList").style.height));
	utWebUI.Save();
	_isResizing = 0;
}

oldResizeUI = resizeUI;
resizeUI = function()
{
	if(_isResizing>0) 
	{
		return;
   	}
        oldResizeUI();
	if(utWebUI.allRSSStuffInited)
	{
		resizing = true;
		utWebUI.rssTable.resize(iv(utWebUI.trtTable.dCont.style.width),iv(utWebUI.trtTable.dCont.style.height));
		resizing = false;
	}
}

updateRSS = function()
{
	if(utWebUI.updateRSSTimer) 
		window.clearTimeout(utWebUI.updateRSSTimer);
	utWebUI.loadRSS();
	utWebUI.updateRSSTimer = window.setTimeout("updateRSS()", utWebUI.updateRSSInterval);
}

function retryRequest()
{
	utWebUI.TimeoutLog(); 
	window.setTimeout("utWebUI.loadRSS()", utWebUI.reqTimeout);
}

utWebUI.loadRSS = function()
{
	this.RequestWithTimeout("?action=loadrss",[this.addRSSItems, this], retryRequest);
}

utWebUI.processRSS = function(action,elURL,elLbl)
{
	var url = elURL.value;
	url = url.replace(/(^\s+)|(\s+$)/g, "");
	var lbl = elLbl.value;
	lbl = lbl.replace(/(^\s+)|(\s+$)/g, "");
	var re = new RegExp();
	re.compile("^[A-Za-z]+://[A-Za-z0-9-]+\.[A-Za-z0-9]+"); 
	if(!re.test(url))
		alert(WUILang.incorrectURL);
	else
	{
		elURL.value = '';
		elLbl.value = '';
		this.RequestWithTimeout("?action="+action+"&v="+encodeURIComponent(url)+"&s="+encodeURIComponent(lbl),[this.addRSSItems, this],retryRequest);
	}
}

utWebUI.addRSS = function()
{
	utWebUI.processRSS("addrss",$$("rssURL"),$$("rssLabel"));
}


utWebUI.editRSS = function()
{
	utWebUI.processRSS("editrss",$$("editrssURL"),$$("editrssLabel"));
}

utWebUI.updateRSSLabels = function(rssLabels,allCnt)
{
	$$("_rssAll_c").innerHTML = allCnt;
	var ul = $$("rssl");
	for(var lbl in rssLabels)
	{
		var li = null;
		if(lbl in this.rssLabels)
		{
			li = $$(lbl);
	                li.innerHTML = escapeHTML(rssLabels[lbl].name)+'&nbsp;(<span id="'+lbl+'_c">'+rssLabels[lbl].cnt+'</span>)';
		}
		else
		{
			li = document.createElement('LI');
			li.id = lbl;
			li.onclick = function() { utWebUI.switchRSSLabel(this); return(false);};
			li.innerHTML = escapeHTML(rssLabels[lbl].name)+'&nbsp;(<span id="'+lbl+'_c">'+rssLabels[lbl].cnt+'</span>)';
			ul.appendChild(li);
		}
		if(lbl==utWebUI.actRSSLbl)
			li.className = (rssLabels[lbl].enabled==1) ?  "selRSS" : "selDisRSS";
		else
			li.className = (rssLabels[lbl].enabled==1) ?  "RSS" : "disRSS";
		addRightClickHandler( li, rssLabelContextMenu );
	}
	var needSwitch = false;
	for(var lbl in this.rssLabels)
		if(!(lbl in rssLabels))
		{
			ul.removeChild( $$(lbl) );
			if(utWebUI.actRSSLbl == lbl)
			{
				needSwitch = true;
				utWebUI.actRSSLbl = null;
			}
		}
	this.rssLabels = rssLabels;
	if(needSwitch)
		utWebUI.switchRSSLabel($$("_rssAll_"));
	else
	if(utWebUI.actRSSLbl)
	{
		utWebUI.switchRSSLabel($$(utWebUI.actRSSLbl),true);
	}
}

utWebUI.showRSS = function()
{
	Toggle($$("dlgAddRSS"));
}

utWebUI.showErrors = function(d)
{
	for( var i=0; i<d.errors.length; i++)
	{
		var s = d.errors[i].time ? "["+formatDate(d.errors[i].time)+"] "+d.errors[i].desc :
			d.errors[i].desc;
		if(d.errors[i].prm)
			s = s + " ("+d.errors[i].prm+")";
		log(s,true);
	}
}

utWebUI.addRSSItems = function(data)
{
	if(!this.rssUpdateInProgress)
	{
		var updated = false;
		this.rssUpdateInProgress = true;
		var d = eval("(" + data + ")");
		this.showErrors(d);
		var rssLabels = new Object();
		var allCnt = 0;
		var sl = this.rssTable.dBody.scrollLeft;
		for( var i=0; i<d.list.length; i++)
		{
			var rss = d.list[i];
			rssLabels[rss.hash] = { name: rss.label, cnt: rss.items.length, enabled: rss.enabled, url: rss.url };
			allCnt += rss.items.length;
			for( var j=0; j<rss.items.length; j++)
			{
				var item = rss.items[j];
				if(typeof utWebUI.rssItems[item.href] != "undefined")
				{
					if(typeof this.torrents[item.hash] != "undefined")
						updated = this.rssTable.updateRowFrom(this.trtTable,item.hash,item.href);
					else
					{
						var arr = [item.title,(item.hash=="") ? WUILang.rssStatus : (item.hash=="Failed") ? WUILang.rssStatusError+" ("+item.errcount+")" : WUILang.rssStatusLoaded,
							null,null,null,null,null,null,null,null,rss.label,null,null,null,item.time,null];
						while(arr.length<this.rssTable.cols)
							arr.push(null);
						updated = this.rssTable.setValues(item.href,arr) || updated; 
						updated = this.rssTable.setIcon(item.href,"Status_RSS") || updated;
					}
					this.rssTable.setAttr(item.href, "label", rss.label);
					this.rssTable.setAttr(item.href, "rss", rss.hash);
				}
				else
				{
					if((item.hash!="") && (typeof this.torrents[item.hash] != "undefined"))
					{
						this.rssTable.addRow(this.trtTable.getValues(item.hash),
							item.href, this.trtTable.getIcon(item.hash), {"rss" : rss.hash, "label" : rss.label});
					}
					else
					{
						var arr = [item.title,(item.hash=="") ? WUILang.rssStatus : (item.hash=="Failed") ? WUILang.rssStatusError+" ("+item.errcount+")" : WUILang.rssStatusLoaded,
							null,null,null,null,null,null,null,null,rss.label,null,null,null,item.time,null];
						while(arr.length<this.rssTable.cols)
							arr.push(null);
						this.rssTable.addRow(arr, 
							item.href, "Status_RSS", {"rss" : rss.hash, "label" : rss.label});
					}
					updated = true;
				}
				item.updated = true;
				item.rss = rss.hash;
				utWebUI.rssItems[item.href] = item;
			}
		}
		for(var href in this.rssItems)
		{
			if(this.rssItems[href].updated)
				this.rssItems[href].updated = false;
			else
			{
				updated = true;
				delete this.rssItems[href];
				this.rssTable.removeRow(href);
			}
		}
		if(updated)
		{
			if(this.rssTable.sIndex !=- 1)
				this.rssTable.Sort();
			this.rssTable.dBody.scrollLeft = sl;
		}
		this.updateRSSLabels(rssLabels,allCnt);
		this.rssUpdateInProgress = false;
	}
}

utWebUI.storeFilterParams = function()
{
	var no = 0;
	if(this.curFilter)
	{
		no = parseInt(this.curFilter.id.substr(3));
		this.filters[no].pattern = $$('FLT_body').value;
		this.filters[no].exclude = $$('FLT_exclude').value;
		this.filters[no].dir = $$('FLTdir_edit').value;
		this.filters[no].add_path = $$('FLTnot_add_path').checked ? 0 : 1;
		this.filters[no].start = $$('FLTtorrents_start_stopped').checked ? 0 : 1;
		this.filters[no].label = $$('FLT_label').value;
		this.filters[no].chktitle = $$('FLTchktitle').checked ? 1 : 0;
		this.filters[no].chkdesc = $$('FLTchkdesc').checked ? 1 : 0;
		this.filters[no].chklink = $$('FLTchklink').checked ? 1 : 0;
		var fltRSS = $$('FLT_rss');
		this.filters[no].hash = fltRSS.options[fltRSS.selectedIndex].value;
		var fltInterval = $$('FLT_interval');
		this.filters[no].interval = fltInterval.options[fltInterval.selectedIndex].value;
		var fltThrottle = $$('FLT_throttle');
		if(fltThrottle)
			this.filters[no].throttle = fltThrottle.options[fltThrottle.selectedIndex].value;
		var fltRatio = $$('FLT_ratio');
		if(fltRatio)
			this.filters[no].ratio = fltRatio.options[fltRatio.selectedIndex].value;
	}
	return(no);
}

utWebUI.fillFiltersSelect = function( el, val )
{
	if(el)
	{
		el.selectedIndex = 0;
		for(var i = 0; i<el.options.length; i++)
		{
			if(el.options[i].value==val)
			{	
				el.selectedIndex = i;
				break;
			}
		}
	}
}

utWebUI.selectFilter = function( el )
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
		$$('FLT_body').value = flt.pattern;
		$$('FLT_exclude').value = flt.exclude;
		$$('FLTdir_edit').value = flt.dir;
		$$('FLTnot_add_path').checked = (flt.add_path==0);
		$$('FLTtorrents_start_stopped').checked = (flt.start==0);
		$$('FLTchktitle').checked = (flt.chktitle==1);
		$$('FLTchkdesc').checked = (flt.chkdesc==1);
		$$('FLTchklink').checked = (flt.chklink==1);
		$$('FLT_label').value = flt.label;
		this.fillFiltersSelect( $$('FLT_rss'), flt.hash );
		this.fillFiltersSelect( $$('FLT_throttle'), flt.throttle );
		this.fillFiltersSelect( $$('FLT_ratio'), flt.ratio );
		this.fillFiltersSelect( $$('FLT_interval'), flt.interval );
		utWebUI.editFilersBtn.hideFrame();
	}
}

utWebUI.loadFiltersWithAdditions = function( flt )
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

utWebUI.maxFilterNo = 0;
utWebUI.loadFilters = function( flt, additions )
{
	this.curFilter = null;
	var list = $$("fltlist");
	while(list.firstChild) 
		list.removeChild(list.firstChild);
	
	var fltRSS = $$('FLT_rss');
	for(var i=fltRSS.options.length-1;i>0;i--)
		fltRSS.remove(i);
	for(var lbl in this.rssLabels)
	{
		var elOptNew = document.createElement('option');
		elOptNew.text = this.rssLabels[lbl].name;
		elOptNew.value = lbl;
		if(browser.isIE)
			fltRSS.add(elOptNew);
		else
			fltRSS.add(elOptNew,null);
	}

	var fltThrottle = $$('FLT_throttle');
	if(fltThrottle)
	{
		for(var i=fltThrottle.options.length-1;i>0;i--)
			fltThrottle.remove(i);
		for(var i=0; i<utWebUI.maxThrottle; i++)
		{
			if(utWebUI.isCorrectThrottle(i))
			{
				var elOptNew = document.createElement('option');
				elOptNew.text = utWebUI.throttles[i].name;
				elOptNew.value = "thr_"+i;
				if(browser.isIE)
					fltThrottle.add(elOptNew);
				else
					fltThrottle.add(elOptNew,null);
			}
		}
	}

	var fltRatio = $$('FLT_ratio');
	if(fltRatio)
	{
		for(var i=fltRatio.options.length-1;i>0;i--)
			fltRatio.remove(i);
		for(var i=0; i<utWebUI.maxRatio; i++)
		{
			if(utWebUI.isCorrectRatio(i))
			{
				var elOptNew = document.createElement('option');
				elOptNew.text = utWebUI.ratios[i].name;
				elOptNew.value = "rat_"+i;
				if(browser.isIE)
					fltRatio.add(elOptNew);
				else
					fltRatio.add(elOptNew,null);
			}
		}
	}

	this.filters = eval( flt );
	if(additions)
	{
		this.filters = additions.concat(this.filters);
	}
	utWebUI.maxFilterNo = 0;
	for(var i=0; i<this.filters.length; i++)
	{
		var f = this.filters[i];
		if(utWebUI.maxFilterNo<f.no)
			utWebUI.maxFilterNo = f.no;
		var li = document.createElement("LI");
		li.innerHTML = "<input type='checkbox' id='_fe"+i+"'/><input type='text' class='TextboxNormal' onfocus=\"javascript:utWebUI.selectFilter(this);\" id='_fn"+i+"'/>";
		list.appendChild(li);
		$$("_fn"+i).value = f.name;
		if(f.enabled)
			$$("_fe"+i).checked = true;
	}
	for(var i=0; i<this.filters.length; i++)
	{
		var f = this.filters[i];
		if(f.no<0)
		{
			utWebUI.maxFilterNo++;
			f.no = utWebUI.maxFilterNo;
		}
	}
	Show("dlgEditFilters");
	var first = $$("_fn0");
	if(first)
		first.focus();
}

utWebUI.addNewFilter = function()
{
	var list = $$("fltlist");
	utWebUI.maxFilterNo++;
	var f = { name: WUILang.rssNewFilter, enabled: 1, pattern: "", exclude: "", label: "", hash: "", start: 1, add_path: 1, dir: "", throttle: "", ratio: "", chktitle: 1, chkdesc: 0, chklink: 0, interval: -1, no: utWebUI.maxFilterNo };
	var li = document.createElement("LI");
	var i = this.filters.length;
	li.innerHTML = "<input type='checkbox' id='_fe"+i+"'/><input type='text' class='TextboxNormal' onfocus=\"javascript:utWebUI.selectFilter(this);\" id='_fn"+i+"'/>";
	list.appendChild(li);
	this.filters.push(f);
	$$("_fn"+i).value = f.name;
	if(f.enabled)
		$$("_fe"+i).checked = true;
	$$("_fn"+i).focus();
}

utWebUI.deleteCurrentFilter = function()
{
	var no = parseInt(this.curFilter.id.substr(3));
	this.filters.splice(no,1);
	var list = $$("fltlist");
	list.removeChild(this.curFilter.parentNode);
	this.curFilter = null;
	if(this.filters.length)
	{
		for(var i=no+1; i<this.filters.length+1; i++)
		{
		    $$("_fn"+i).id = "_fn"+(i-1);
		    $$("_fe"+i).id = "_fe"+(i-1);
		}
		if(no>=this.filters.length)
			no = no - 1;
		$$("_fn"+no).focus();	
	}
	else
	{
		utWebUI.editFilersBtn.hideFrame();
		$$('FLT_body').value = '';
		$$('FLT_exclude').value = '';
		$$('FLTdir_edit').value = '';
		$$('FLTnot_add_path').checked = false;
		$$('FLTchktitle').checked = true;
		$$('FLTchkdesc').checked = false;
		$$('FLTchklink').checked = false;
		$$('FLTtorrents_start_stopped').checked = false;
		$$('FLT_label').value = '';
	}
}

utWebUI.checkCurrentFilter = function()
{
	if(this.curFilter)
		this.Request("?action=checkfilter",[this.showFilterResults, this]);
}

utWebUI.showFilterResults = function( data )
{
	var d = eval("(" + data + ")");
	this.showErrors(d);
	if(d.rss.length)
		this.switchRSSLabel($$(d.rss));
	else
		this.switchRSSLabel($$('_rssAll_'));
	for(var k in this.rssTable.rowSel)
		this.rssTable.rowSel[k] = false;
	this.trtTable.selCount = d.list.length;
	for(var i = 0; i<d.list.length; i++)
		this.rssTable.rowSel[d.list[i]] = true;
	this.rssTable.refreshSelection();
	alert(WUILang.foundedByFilter+" : "+d.list.length);
}

utWebUI.setFilters = function()
{
	this.Request("?action=setfilters",[this.addRSSItems, this]);
}

utWebUI.rssClearFilter = function()
{
	var no = utWebUI.storeFilterParams();
	var flt = utWebUI.filters[no];
	if(flt.interval>=0)
		this.Request("?action=clearfiltertime&v="+flt.no);
}

rTorrentStub.prototype.clearfiltertime = function()
{
	this.content = "mode=clearfiltertime&no="+this.vs[0];
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.clearfiltertimeResponse = function(xmlDoc,docText)
{
	return(false);
}

rTorrentStub.prototype.getrssdetails = function()
{
	this.content = "mode=getdesc&href="+encodeURIComponent(utWebUI.currentRSSDetailsID)+"&rss="+utWebUI.rssItems[utWebUI.currentRSSDetailsID].rss;
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.getrssdetailsResponse = function(xmlDoc,docText)
{
        $$("rsslayout").innerHTML = this.addrssResponse(xmlDoc,docText);
        var as = $$("rsslayout").getElementsByTagName("A");
        if(as)
	        for(var i = 0; i<as.length; i++)
	        	if(as[i])
		       		as[i].target = "_blank";
	return(false);
}

rTorrentStub.prototype.setfilters = function()
{
	this.content = "mode=setfilters";
	utWebUI.storeFilterParams();
	for(var i=0; i<utWebUI.filters.length; i++)
	{
		var flt = utWebUI.filters[i];
		var enabled = $$("_fe"+i).checked ? 1 : 0;
		var name = $$("_fn"+i).value;
		this.content = this.content+"&name="+encodeURIComponent(name)+"&pattern="+encodeURIComponent(flt.pattern)+"&enabled="+enabled+
			"&chktitle="+flt.chktitle+
			"&chklink="+flt.chklink+
			"&chkdesc="+flt.chkdesc+
		        "&exclude="+encodeURIComponent(flt.exclude)+
			"&hash="+flt.hash+"&start="+flt.start+"&addPath="+flt.add_path+
			"&dir="+encodeURIComponent(flt.dir)+"&label="+encodeURIComponent(flt.label)+"&throttle="+flt.throttle+"&ratio="+flt.ratio+"&interval="+flt.interval+"&no="+flt.no;
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.setfiltersResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.checkfilter = function()
{
	var no = utWebUI.storeFilterParams();
	var flt = utWebUI.filters[no];
	this.content = "mode=checkfilter&pattern="+encodeURIComponent(flt.pattern)+"&exclude="+encodeURIComponent(flt.exclude)+
		"&chktitle="+flt.chktitle+"&chklink="+flt.chklink+"&chkdesc="+flt.chkdesc;
	if(flt.hash.length)
		this.content = this.content+"&rss="+flt.hash;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.checkfilterResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.addrss = function()
{
	this.content = "mode=add&url="+this.vs[0]+"&label="+this.ss[0];
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.addrssResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	return(datas[0].childNodes[0].data);
}

rTorrentStub.prototype.editrss = function()
{
	this.content = "mode=edit&url="+this.vs[0]+"&label="+this.ss[0];
	if(utWebUI.actRSSLbl && (utWebUI.actRSSLbl != "_rssAll_"))
		this.content = this.content + "&rss=" + utWebUI.actRSSLbl;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.editrssResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.loadrss = function()
{
	this.content = "mode=get";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.loadrssResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.loadrsstorrents = function()
{
	this.content = "mode=loadtorrents";
	if($$("RSStorrents_start_stopped").checked)
		this.content = this.content + '&torrents_start_stopped=1';
	if($$("RSSnot_add_path").checked)
		this.content = this.content + '&not_add_path=1';
	var dir = $$("RSSdir_edit").value;
	dir = dir.replace(/(^\s+)|(\s+$)/g, "");
	if(dir.length)
		this.content = this.content + '&dir_edit='+encodeURIComponent(dir);
	var lbl = $$("RSS_label").value;
	lbl = lbl.replace(/(^\s+)|(\s+$)/g, "");
	if(lbl.length)
		this.content = this.content + '&label='+encodeURIComponent(lbl);
	for(var i = 0; i<utWebUI.rssArray.length; i++)
	{
		var item = utWebUI.rssItems[utWebUI.rssArray[i]];
		this.content = this.content + '&rss='+item.rss+'&url='+encodeURIComponent(item.href);
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.loadrsstorrentsResponse = function(xmlDoc,docText)
{
	utWebUI.getTorrents("list=1");
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.clearhistory = function()
{
	this.content = "mode=clearhistory";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.clearhistoryResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.rssrefresh = function()
{
	this.content = "mode=refresh";
	if(utWebUI.actRSSLbl && (utWebUI.actRSSLbl != "_rssAll_"))
		this.content = this.content + "&rss=" + utWebUI.actRSSLbl;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.rssrefreshResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.rsstoggle = function()
{
	this.content = "mode=toggle";
	if(utWebUI.actRSSLbl && (utWebUI.actRSSLbl != "_rssAll_"))
		this.content = this.content + "&rss=" + utWebUI.actRSSLbl;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.rsstoggleResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.rssremove = function()
{
	this.content = "mode=remove";
	if(utWebUI.actRSSLbl && (utWebUI.actRSSLbl != "_rssAll_"))
		this.content = this.content + "&rss=" + utWebUI.actRSSLbl;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.rssremoveResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.getfilters = function()
{
	this.content = "mode=getfilters";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.getfiltersResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

rTorrentStub.prototype.getintervals = function()
{
	this.content = "mode=getintervals";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
}

rTorrentStub.prototype.getintervalsResponse = function(xmlDoc,docText)
{
	return(this.addrssResponse(xmlDoc,docText));
}

utWebUI.MarkRSSLoaded = function() 
{ 
	loadTorrentsBtn = new rBrowseButton($$("dlgLoadTorrents"),$$('RSSdir_edit'),$$('RSSBtn'),'rss'); 
	utWebUI.editFilersBtn = new rBrowseButton($$("dlgEditFilters"),$$('FLTdir_edit'),$$('FLTBtn'),'rss'); 
	var w = getWindowWidth();
	var h = getWindowHeight();
	Drag.init($$("dlgAddRSS-header"), $$("dlgAddRSS"), 0, w, 0, h, true);
	Drag.init($$("dlgEditFilters-header"), $$("dlgEditFilters"), 0, w, 0, h, true);
	Drag.init($$("dlgLoadTorrents-header"), $$("dlgLoadTorrents"), 0, w, 0, h, true);
	Drag.init($$("dlgEditRSS-header"), $$("dlgEditRSS"), 0, w, 0, h, true);
	utWebUI.allRSSStuffLoaded = true;
}

utWebUI.correctRatioFilterDialg = function()
{
	var rule = getCSSRule(".rf fieldset");
	if(rule && utWebUI.allRatioStuffLoaded)
	{
		var addition = (browser.isIE) ? 52 : 42;
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
		var parent = $$("FLT_label").parentNode;
		var el = document.createElement("SPAN");
		el.innerHTML = "<label>"+WUILang.ratio+":</label><select id='FLT_ratio'><option value=''>"+WUILang.mnuRatioUnlimited+"</option></select><br/>";
		parent.appendChild(el);
	}
	else
		setTimeout('utWebUI.correctRatioFilterDialg()',1000);
}

utWebUI.correctFilterDialg = function()
{
	var rule = getCSSRule(".rf fieldset");
	if(rule && utWebUI.allThrottleStuffLoaded)
	{
		var addition = (browser.isIE) ? 52 : 42;
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
		var parent = $$("FLT_label").parentNode;
		var el = document.createElement("SPAN");
		el.innerHTML = "<label>"+WUILang.throttle+":</label><select id='FLT_throttle'><option value=''>"+WUILang.mnuUnlimited+"</option></select><br/>";
		parent.appendChild(el);
		if(utWebUI.ratioSupported)
			utWebUI.correctRatioFilterDialg();
	}
	else
		setTimeout('utWebUI.correctFilterDialg()',1000);
}

utWebUI.initRSS = function()
{
        var el = $$('CatList');
	var ul = document.createElement('UL');
	if($$("pstate"))
	{
		var pnl = document.createElement('DIV');
	        pnl.className = "catpanel";
        	pnl.id = "prss";
		pnl.innerHTML = WUILang.rssFeeds;
		pnl.onclick = function() { utWebUI.togglePanel(pnl); };
		el.appendChild(pnl);
		var div = document.createElement('DIV');
		div.id = "prss_cont";
		el.appendChild(div);
		el = div;
		ul.innerHTML = '<li id="_rssAll_" class="RSS" onclick="javascript:utWebUI.switchRSSLabel(this);return(false);">'+WUILang.allFeeds+'&nbsp;(<span id="_rssAll_c">0</span>)</li>';
	}
	else
		ul.innerHTML = '<li id="_hr_"><hr /></li><li id="_rssAll_" class="RSS" onclick="javascript:utWebUI.switchRSSLabel(this);return(false);">'+WUILang.allFeeds+'&nbsp;(<span id="_rssAll_c">0</span>)</li>';
	el.appendChild(ul);

	addRightClickHandler( $$("_rssAll_"), rssLabelContextMenu );
	var div = document.createElement('DIV');
	div.innerHTML = '<ul id="rssl"></ul>';
        el.appendChild(div);
	var dlg = document.createElement("DIV");
	dlg.className = "dlg-window";
	dlg.id = "dlgAddRSS";
	dlg.innerHTML = "<a href='javascript:Hide(\"dlgAddRSS\");' class='dlg-close'></a>"+
		"<div id='dlgAddRSS-header' class='dlg-header'>"+
			WUILang.addRSS+
		"</div>"+
		"<div class='content'>"+
			"<label>"+WUILang.feedURL+": </label>"+
			"<input type='text' id='rssURL' class='TextboxLarge'/><br/>"+
			"<label>"+WUILang.alias+": </label>"+
			"<input type='text' id='rssLabel' class='TextboxLarge'/>"+
			"<div class='aright'><input type='button' class='Button' value="+WUILang.ok1+" onclick='javascript:Hide(\"dlgAddRSS\");utWebUI.addRSS();return(false);'/><input type='button' class='Button' value="+WUILang.Cancel1+" onclick='javascript:Hide(\"dlgAddRSS\");return(false);'/></div>"+
		"</div>";
	var b = document.getElementsByTagName("body").item(0);
	b.appendChild(dlg);

	dlg = document.createElement("DIV");
	dlg.className = "dlg-window";
	dlg.id = "dlgEditRSS";
	dlg.innerHTML = "<a href='javascript:HideModal(\"dlgEditRSS\");' class='dlg-close'></a>"+
		"<div id='dlgEditRSS-header' class='dlg-header'>"+
			WUILang.rssMenuEdit+
		"</div>"+
		"<div class='content'>"+
			"<label>"+WUILang.feedURL+": </label>"+
			"<input type='text' id='editrssURL' class='TextboxLarge'/><br/>"+
			"<label>"+WUILang.alias+": </label>"+
			"<input type='text' id='editrssLabel' class='TextboxLarge'/>"+
			"<div class='aright'><input type='button' class='Button' value="+WUILang.ok1+" onclick='javascript:HideModal(\"dlgEditRSS\");utWebUI.editRSS();return(false);'/><input type='button' class='Button' value="+WUILang.Cancel1+" onclick='javascript:HideModal(\"dlgEditRSS\");return(false);'/></div>"+
		"</div>";
	b.appendChild(dlg);

	dlg = document.createElement("DIV");
	dlg.className = "dlg-window";
	dlg.id = "dlgLoadTorrents";
	dlg.innerHTML = "<a href='javascript:HideModal(\"dlgLoadTorrents\");' class='dlg-close'></a>"+
		"<div id='dlgLoadTorrents-header' class='dlg-header'>"+
			WUILang.torrent_add+
		"</div>"+
		"<div class='content'>"+
			"<label>"+WUILang.Base_directory+":</label><input type='text' id='RSSdir_edit' class='TextboxLarge'/><input type=button id='RSSBtn'><br/>"+
			"<label></label><input type='checkbox' id='RSSnot_add_path'/>"+WUILang.Dont_add_tname+"<br/>"+
			"<label></label><input type='checkbox' id='RSStorrents_start_stopped'/>"+WUILang.Dnt_start_down_auto+"<br/>"+
			"<label>"+WUILang.Label+":</label><input type='text' id='RSS_label' class='TextboxLarge'/><br/>"+
			"<div id='buttons' class='aright'><input type='button' class='Button' value="+WUILang.ok1+" onfocus=\"javascript:this.blur();\" onclick='javascript:HideModal(\"dlgLoadTorrents\");utWebUI.RSSLoadTorrents();return(false);'/><input type='button' class='Button' value="+WUILang.Cancel1+" onfocus=\"javascript:this.blur();\" onclick='javascript:HideModal(\"dlgLoadTorrents\");return(false);'/></div>"+
		"</div>";
	b.appendChild(dlg);

	dlg = document.createElement("DIV");
	dlg.className = "dlg-window";
	dlg.id = "dlgEditFilters";
	dlg.innerHTML = "<a href='javascript:Hide(\"dlgEditFilters\");' class='dlg-close'></a>"+
		"<div id='dlgEditFilters-header' class='dlg-header'>"+WUILang.rssMenuManager+"</div>"+
		"<div class='fxcaret'>"+
			"<div class='lfc'>"+
				"<div class='lf' id='filterList'>"+
					"<ul id='fltlist'></ul>"+
				"</div>"+
				"<div id='FLTchk_buttons'>"+
					"<input type='button' class='Button' value='"+WUILang.rssAddFilter+"' onclick='javascript:utWebUI.addNewFilter();return(false);'/>"+
					"<input type='button' class='Button' value='"+WUILang.rssDelFilter+"' onclick='javascript:utWebUI.deleteCurrentFilter();return(false);'/>"+
					"<input type='button' id='chkFilterBtn' class='Button' value='"+WUILang.rssCheckFilter+"' onclick='javascript:utWebUI.checkCurrentFilter();return(false);'/>"+
				"</div>"+
			"</div>"+
			"<div class='rf' id='filterProps'>"+
				"<fieldset id='filterPropsFieldSet'>"+
					"<legend>"+WUILang.rssFiltersLegend+"</legend>"+
					"<label>"+WUILang.rssFilter+":</label><input type='text' id='FLT_body' class='TextboxLarge'/><br/>"+
					"<label>"+WUILang.rssExclude+":</label><input type='text' id='FLT_exclude' class='TextboxLarge'/><br/>"+
					"<label></label><input type='checkbox' class='chk' id='FLTchktitle'/>"+WUILang.rssCheckTitle+"<br/>"+
					"<label></label><input type='checkbox' class='chk'id='FLTchkdesc'/>"+WUILang.rssCheckDescription+"<br/>"+
					"<label></label><input type='checkbox' class='chk' id='FLTchklink'/>"+WUILang.rssCheckLink+"<br/>"+
					"<label>"+WUILang.rssStatus+":</label><select id='FLT_rss'><option value=''>"+WUILang.allFeeds+"</option></select><br/>"+
					"<label>"+WUILang.Base_directory+":</label><input type='text' id='FLTdir_edit' class='TextboxLarge'/><input type=button id='FLTBtn'><br/>"+
					"<label></label><input type='checkbox' class='chk'id='FLTnot_add_path'/>"+WUILang.Dont_add_tname+"<br/>"+
					"<label></label><input type='checkbox' class='chk'id='FLTtorrents_start_stopped'/>"+WUILang.Dnt_start_down_auto+"<br/>"+
					"<label>"+WUILang.rssMinInterval+":</label><select id='FLT_interval'>"+
					        "<option value='-1'>"+WUILang.rssIntervalAlways+"</option>"+
					        "<option value='0'>"+WUILang.rssIntervalOnce+"</option>"+
					        "<option value='12'>"+WUILang.rssInterval12h+"</option>"+
					        "<option value='24'>"+WUILang.rssInterval1d+"</option>"+
					        "<option value='48'>"+WUILang.rssInterval2d+"</option>"+
					        "<option value='72'>"+WUILang.rssInterval3d+"</option>"+
					        "<option value='96'>"+WUILang.rssInterval4d+"</option>"+
					        "<option value='168'>"+WUILang.rssInterval1w+"</option>"+
					        "<option value='336'>"+WUILang.rssInterval2w+"</option>"+
					        "<option value='504'>"+WUILang.rssInterval3w+"</option>"+
					        "<option value='720'>"+WUILang.rssInterval1m+"</option>"+
						"</select>"+
						"<input type='button' class='Button' value="+WUILang.rssClearFilter+" onclick='javascript:utWebUI.rssClearFilter();return(false);'/><br/>"+
					"<label>"+WUILang.Label+":</label><input type='text' id='FLT_label' class='TextboxLarge'/><br/>"+
				"</fieldset>"+
			"</div>"+
		"</div>"+
		"<div id='FLT_buttons' class='aright'>"+
			"<input type='button' class='Button' value="+WUILang.ok1+" onfocus='javascript:this.blur();' onclick='javascript:Hide(\"dlgEditFilters\");utWebUI.setFilters();return(false);'/>"+
			"<input type='button' class='Button' value="+WUILang.Cancel1+" onfocus='javascript:this.blur();' onclick='javascript:Hide(\"dlgEditFilters\");return(false);'/>"+
		"</div>";
	b.appendChild(dlg);

	dlg = document.createElement("DIV");
	dlg.id = "rsslayout";
	dlg.style.display = "none";
	$$("gcont").appendChild(dlg);

	if(utWebUI.throttleSupported)
		utWebUI.correctFilterDialg();
	else
	if(utWebUI.ratioSupported)
		utWebUI.correctRatioFilterDialg();
	injectScript("plugins/rss/browse.js");
};

utWebUI.showRSSError = function(err)
{
	if(utWebUI.allRSSStuffLoaded)
		log(err);
	else
		setTimeout('utWebUI.showRSSError('+err+')',1000);
}

plugin.loadLanguages();