plugin.loadMainCSS();
if(browser.isIE && browser.versionMajor < 8)
	plugin.loadCSS("ie");
plugin.loadLang();

theWebUI.rssShowErrorsDelayed = true;
theWebUI.delayedRSSErrors = {};

if(plugin.canChangeOptions())
{
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function( arg )
	{
		if(plugin.enabled)
		{
			$('#rss_interval').val(theWebUI.updateRSSInterval/60000);
			$('#rss_show_errors_delayed').prop('checked', theWebUI.rssShowErrorsDelayed);
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.rssWasChanged = function()
	{
		return(	$('#rss_interval').val()!=theWebUI.updateRSSInterval/60000 ||
		$('#rss_show_errors_delayed').prop('checked') != theWebUI.rssShowErrorsDelayed);
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function()
	{
		plugin.setSettings.call(this);
		if( plugin.enabled && this.rssWasChanged() ) {
			theWebUI.RSSSetSettings(
				$('#rss_interval').val(),
				$('#rss_show_errors_delayed').prop('checked')
			);
		}
	}
}

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
			$($$(theWebUI.actRSSLbl)).removeClass('sel');
		theWebUI.actRSSLbl = null;
		$("#List").show();
		lst.hide();
		theWebUI.switchLayout(false);
	}

	if( $(el).hasClass('RSS') ||
		$(el).hasClass('disRSS') ||
		$(el).hasClass('RSSGroup'))
		theWebUI.switchRSSLabel(el);

	plugin.switchLabel.call(theWebUI,el);
}

theWebUI.isActiveRSSEnabled = function()
{
	return((theWebUI.actRSSLbl == "_rssAll_") || 
		(theWebUI.isGroupSelected() && (theWebUI.rssGroups[theWebUI.actRSSLbl].enabled==1)) ||
		(!theWebUI.isGroupSelected() && theWebUI.rssLabels[theWebUI.actRSSLbl].enabled==1));
}

theWebUI.updateRSSDetails = function(id)
{
	if(id)
		this.request("?action=getrssdetails&s="+encodeURIComponent(id));
	else
		$("#rsslayout").text('');
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

theWebUI.switchRSSLabel = function(el)
{
	if((el.id == theWebUI.actRSSLbl) && $(el).hasClass('sel'))
		return;
	if(theWebUI.actRSSLbl)
		$($$(theWebUI.actRSSLbl)).removeClass('sel')
	theWebUI.actRSSLbl = el.id;
	$(el).addClass('sel');

	var table = theWebUI.getTable("rss");
	table.scrollTo(0);
	for(var k in theWebUI.rssItems)
	{
		if((theWebUI.actRSSLbl == "_rssAll_") || 
			(theWebUI.isGroupSelected() &&
				theWebUI.isGroupContain( theWebUI.rssGroups[theWebUI.actRSSLbl], theWebUI.rssItems[k] )) ||
			theWebUI.rssItems[k].rss[theWebUI.actRSSLbl])
			table.unhideRow(k);
		else
			table.hideRow(k);
	}
	table.clearSelection();
	var lst = $("#List");
	var rss = $("#RSSList");
	if(lst.is(":visible"))
	{
		theWebUI.dID = "";
		theWebUI.clearDetails();
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
	theWebUI.showDelayedRSSErrros();
}

theWebUI.showDelayedRSSErrros = function() {
	// show delayed notifications
	$('#tab_lcont').removeClass('notification');
	const notyArgss = Object.values(theWebUI.delayedRSSErrors);
	for (const eid in theWebUI.delayedRSSErrors) {
		$('#'+eid).removeClass('notification');
	}
	theWebUI.delayedRSSErrors = {};
	for (const argss of notyArgss) {
		for (const args of argss) {
			noty(...args);
		}
	}
};

plugin.theTabsOnShow = theTabs.onShow;
theTabs.onShow = function(id) {
	if(id=="lcont") {
		theWebUI.showDelayedRSSErrros();
	}
	plugin.theTabsOnShow.call(this,id);
};

plugin.config = theWebUI.config;
theWebUI.config = function()
{
	this.rssLabels = {};
	this.rssItems = {};
	this.rssGroups = {};
	this.actRSSLbl = null;
	this.updateRSSTimer = null;
	this.updateRSSInterval = 5*60*1000;
	this.rssUpdateInProgress = false;
	this.rssID = "";
	this.cssCorrected = false;
	this.rssArray = [];
	this.filters = [];	
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
	plugin.config.call(this);
	plugin.start();
}

plugin.start = function()
{
	if(plugin.allStuffLoaded)
		theWebUI.request("?action=getrsssettings",[theWebUI.getRSSSettings, theWebUI]);
	else
		setTimeout(arguments.callee,1000);
}

theWebUI.rssDblClick = function( obj )
{
	if($type(theWebUI.torrents[theWebUI.rssItems[obj.id].hash]))
	{
		var tmp = {};
                tmp.id = theWebUI.rssItems[obj.id].hash
        	theWebUI.getTable("trt").ondblclick( tmp );
        	delete tmp;
	}
	else
		window.open(theWebUI.rssItems[obj.id].guid,"_blank");
}

theWebUI.showRSSTimer = function( tm )
{
	$("#rsstimer").text( theConverter.time( tm ) ).prop( "row", tm );
	if(plugin.rssShowInterval)
		window.clearInterval( plugin.rssShowInterval );
	plugin.rssShowInterval = window.setInterval( function()
	{
		var tm = $("#rsstimer").prop("row")-1;
		if(!tm)
		{
			$("#rsstimer").text('*');
			window.clearInterval( plugin.rssShowInterval );
		}
		$("#rsstimer").text( theConverter.time( tm ) ).prop( "row", tm );
	}, 1000 );
}

theWebUI.getRSSSettings = function( d )
{
	if(theWebUI.updateRSSTimer) 
		window.clearTimeout(theWebUI.updateRSSTimer);
        theWebUI.loadRSS();
	theWebUI.updateRSSInterval = d.interval*60000;	
	theWebUI.updateRSSTimer = window.setTimeout("theWebUI.updateRSS()", d.next*1000);
	theWebUI.showRSSTimer(d.next);

	theWebUI.rssShowErrorsDelayed = Boolean(d.delayerrui);
	if (!theWebUI.rssShowErrorsDelayed) {
		theWebUI.showDelayedRSSErrros();
	}
}

theWebUI.RSSSetSettings = function( interval, delayErrorsUI )
{
	this.request("?action=setrsssettings&s="+interval+"&s="+(delayErrorsUI ? 1 : 0),[this.getRSSSettings, this]);
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
        if(e.which==3)
        {
		if(plugin.canChangeMenu())
		{
			theWebUI.getTable("trt").clearSelection();
			theWebUI.getTable("rss").clearSelection();
			theWebUI.switchLabel(this);
			theWebUI.getTable("rss").fillSelection();
			theWebUI.createRSSMenu(null, null);
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
}

theWebUI.fillRSSGroups = function()
{
	var content = $("#rssGroupSet");
	content.children().remove();
	var s = '';
	for(var lbl in this.rssLabels)
		s += ("<input type=checkbox id='grp_"+lbl+"'><label for='grp_"+lbl+"' id='lbl_grp_"+lbl+"'>"+this.rssLabels[lbl].name+"</label><br/>");
	content.html(s);
}

theWebUI.RSSEditGroup = function()
{
	theWebUI.fillRSSGroups();
	var grp = theWebUI.rssGroups[this.actRSSLbl];
	for(var i=0; i<grp.lst.length; i++)
		$('#grp_'+grp.lst[i]).prop('checked',true);
	$("#rssGroupLabel").val(grp.name);
	$("#dlgAddRSSGroup-header").html(theUILang.editRSSGroup);
	$("#rssGroupHash").val(this.actRSSLbl);
	theDialogManager.show("dlgAddRSSGroup");
}

theWebUI.RSSAddGroup = function()
{
	theWebUI.fillRSSGroups();
	$("#dlgAddRSSGroup-header").html(theUILang.addRSSGroup);
        $("#rssGroupHash").val('');
	$("#rssGroupLabel").val('');
	theDialogManager.show("dlgAddRSSGroup");
}

theWebUI.addRSSGroup = function()
{
	theDialogManager.hide("dlgAddRSSGroup");
	this.requestWithTimeout("?action=addrssgroup",[this.addRSSItems, this],theWebUI.retryRSSRequest);
}

theWebUI.RSSGroupSetStatus = function(enable)
{
	theWebUI.request("?action=rssgroupstatus&s="+enable,[this.addRSSItems, this]);
}

theWebUI.RSSGroupRefresh = function()
{
	this.requestWithTimeout("?action=rssgrouprefresh",[this.addRSSItems, this],theWebUI.retryRSSRequest);
}

theWebUI.doRSSGroupDelete = function()
{
	theWebUI.request("?action=rssgroupremove",[this.addRSSItems, this]);
}

theWebUI.RSSGroupDelete = function()
{
	if(theWebUI.settings["webui.confirm_when_deleting"])
		askYesNo( theUILang.rssMenuGroupDelete, theUILang.rssDeleteGroupPrompt, "theWebUI.doRSSGroupDelete()" );
	else
		theWebUI.doRSSGroupDelete();
}

theWebUI.doRSSGroupContentsDelete = function()
{
	theWebUI.request("?action=rssgroupremovecontents",[this.addRSSItems, this]);
}

theWebUI.RSSGroupDeleteContents = function()
{
	if(theWebUI.settings["webui.confirm_when_deleting"])
		askYesNo( theUILang.rssMenuGroupDeleteContents, theUILang.rssDeleteGroupContentsPrompt, "theWebUI.doRSSGroupContentsDelete()" );
	else
		theWebUI.doRSSGroupContentsDelete();
}

theWebUI.createRSSMenuPrim = function()
{
        if(plugin.canChangeMenu())
        {
		theContextMenu.add([ theUILang.rssMenuClearHistory, "theWebUI.RSSClearHistory()"]);
		theContextMenu.add([ theUILang.addRSS, "theDialogManager.toggle('dlgAddRSS')"]);
		theContextMenu.add([ theUILang.addRSSGroup, "theWebUI.RSSAddGroup()"]);
		theContextMenu.add([ theUILang.rssMenuManager, "theWebUI.RSSManager()"]);
		if(theWebUI.actRSSLbl) 
		{
			theContextMenu.add([CMENU_SEP]);			
			if(this.actRSSLbl == "_rssAll_")
			{
				theContextMenu.add([ theUILang.rssMenuDisable ]);
				theContextMenu.add([ theUILang.rssMenuEdit ]);
				theContextMenu.add([ theUILang.rssMenuRefresh, "theWebUI.RSSRefresh()"]);
				theContextMenu.add([ theUILang.rssMenuDelete ]);
			}
			else
			{
				if(this.isGroupSelected())
				{
					if(this.rssGroups[this.actRSSLbl].enabled==1)
					{
						theContextMenu.add([ theUILang.rssMenuGroupDisable, "theWebUI.RSSGroupSetStatus(0)"]);
						theContextMenu.add([ theUILang.rssMenuGroupRefresh, "theWebUI.RSSGroupRefresh()"]);
					}
					else
					{
						theContextMenu.add([ theUILang.rssMenuGroupEnable, (this.rssGroups[this.actRSSLbl].cnt==0) ? null : "theWebUI.RSSGroupSetStatus(1)"]);
						theContextMenu.add([ theUILang.rssMenuGroupRefresh ]);
					}
					theContextMenu.add([ theUILang.rssMenuGroupEdit, "theWebUI.RSSEditGroup()"]);
					theContextMenu.add([ theUILang.rssMenuGroupDelete, "theWebUI.RSSGroupDelete()"]);
					theContextMenu.add([ theUILang.rssMenuGroupContentsDelete, "theWebUI.RSSGroupDeleteContents()"]);
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
	var trtArray = [];
	this.rssArray = [];
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
	var trtArray = [];
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
		if((e.which==3) && plugin.canChangeMenu())
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
					label: item.label,
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
	theWebUI.showRSSTimer( theWebUI.updateRSSInterval/1000 );
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
	var url = elURL.val().trim();
	var lbl = elLbl.val().trim();
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

theWebUI.isGroupContain = function( rssGroup, rssItem )
{
	return rssGroup && rssGroup.lst.some(href => href in rssItem.rss);
}

theWebUI.isGroupSelected = function()
{
	return(this.actRSSLbl && this.actRSSLbl.length && (this.actRSSLbl[0]=='g'));
}

theWebUI.updateRSSLabels = function(rssLabels,rssGroups)
{
	// remove elements
	const removedGroups = Object.keys(this.rssGroups).filter(lbl => !(lbl in rssGroups));
	const removedLabels = Object.keys(this.rssLabels).filter(lbl => !(lbl in rssLabels));
	const removedLbls = removedGroups.concat(removedLabels);
	for (const lbl of removedLbls) {
		$($$(lbl)).remove();
	}

	this.rssLabels = rssLabels;
	this.rssGroups = rssGroups;

	const allItems = Object.values(this.rssItems);

	// update group values
	for (const group of Object.values(this.rssGroups)) {
		group.cnt = allItems
			.filter(item => theWebUI.isGroupContain(group, item))
			.length;
		group.enabled = group.lst
			.some(l => l in rssLabels && rssLabels[l].enabled);
	}
	// update total item count
	theWebUI.updateLabel('#_rssAll_', allItems.length, 0, false);

	// add, update (and resort) rss categories
	const ul = $("#rssl");
	for ( const [rssClass, rssCategory] of [
		['RSSGroup', this.rssGroups],
		['RSS', this.rssLabels]
	]) {
		const labels = Object.entries(rssCategory);
		labels.sort( ([_,a], [__, b]) => a.name.localeCompare(b.name));

		for( const [lbl, category] of labels ) {
			let li = $($$(lbl));
			if(li.length === 0) {
				li = theWebUI.createSelectableLabelElement(lbl, category.name, theWebUI.rssLabelContextMenu);
			}
			li.addClass([rssClass, 'disRSS']);
			li.removeClass(category.enabled == 1 ? 'disRSS' : rssClass);
			theWebUI.updateLabel(li, category.cnt, 0, false);
			if(lbl==this.actRSSLbl)
				li.addClass('sel');
			else
				li.removeClass('sel');
				li.appendTo(ul);
		}
	}

	// refresh label
	const curRSSLbl = theWebUI.actRSSLbl;
	theWebUI.actRSSLbl = null;
	if (removedLbls.includes(curRSSLbl)) {
		this.switchLabel($$("_rssAll_"));
	} else if(curRSSLbl) {
		this.switchLabel($$(curRSSLbl));
	}
}

theWebUI.showRSS = function()
{
	plugin.correctCSS();
        if($('#rssl').children().length)
        	theWebUI.RSSManager();
        else
		theDialogManager.toggle("dlgAddRSS");
}

theWebUI.showErrors = function(errors)
{
	for( const err of errors)
	{
		const idHash = err.prm && Object.entries(theWebUI.rssLabels).find(([_,l]) => l.url === err.prm)?.[0];
		const name = idHash && theWebUI.rssLabels[idHash].name;
		const args = [
			'['+theConverter.date('time' in err ? iv(err.time)+theWebUI.deltaTime : new Date().getTime()/1000)+'] '
			+ (name ? '<'+name+'> ' : '')
			+ eval(err.desc)
			+ (err.prm ? ' ('+err.prm+')' : ''),
			'error',
			true
		];
		if (!theWebUI.rssShowErrorsDelayed || $('#RSSList').is(':visible') || $('#lcont').is(':visible')) {
			noty(...args);
		} else {
			const id = idHash || '_rssAll_';
			$('#'+id).addClass('notification');
			$('#tab_lcont').addClass('notification');
			const delayed = theWebUI.delayedRSSErrors;
			delayed[id] = id in delayed ? delayed[id].concat([args]) : [args];
		}
	}
}

theWebUI.addRSSItems = function(d)
{
	if(!this.rssUpdateInProgress)
	{
		for(var href in this.rssItems)
			this.rssItems[href].rss = {};
		var updated = false;
		this.rssUpdateInProgress = true;
		var rssLabels = {};
		var table = this.getTable("rss");
		for( var i=0; i<d.list.length; i++)
		{
			var rss = d.list[i];
			rssLabels[rss.hash] = { name: rss.label, cnt: rss.items.length, enabled: rss.enabled, url: rss.url };
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
	                        	item.rss = theWebUI.rssItems[item.href].rss;
				}
				else
				{
					if((item.hash!="") && $type(this.torrents[item.hash]))
					{
						table.addRow(this.getTable("trt").getValues(item.hash),
							item.href, this.getTable("trt").getIcon(item.hash));
					}
					else
					{
						table.addRowById(
						{
							name: item.title,
						 	status: (item.hash=="") ? theUILang.rssStatus : (item.hash=="Failed") ? theUILang.rssStatusError+" ("+item.errcount+")" : theUILang.rssStatusLoaded, 
							label: rss.label,
							created: item.time
						}, item.href, "Status_RSS");
					}
					updated = true;
					item.rss = {};
				}
				item.rss[rss.hash] = true;
				item.label = rss.label;
				theWebUI.rssItems[item.href] = item;
			}
		}
		var deleted = false;
		for(var href in this.rssItems)
		{
			if(!plugin.getFirstRSS(this.rssItems[href]))
			{
				updated = true;
				delete this.rssItems[href];
				table.removeRow(href);
				deleted = true;
			}
		}
		if(updated)
		{
			if(deleted)
			{
				table.correctSelection();
			}
			table.Sort();
		}
		this.updateRSSLabels(rssLabels,d.groups);
		this.showErrors(d.errors);
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

		this.filters[no].add_path = $('#FLTnot_add_path').prop("checked") ? 0 : 1;
		this.filters[no].start = $('#FLTtorrents_start_stopped').prop("checked") ? 0 : 1;
		this.filters[no].label = $('#FLT_label').val();
		this.filters[no].chktitle = $('#FLTchktitle').prop("checked") ? 1 : 0;
		this.filters[no].chkdesc = $('#FLTchkdesc').prop("checked") ? 1 : 0;
		this.filters[no].chklink = $('#FLTchklink').prop("checked") ? 1 : 0;
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
		this.setDisableControls(false);
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
		$('#FLTnot_add_path').prop("checked",(flt.add_path==0));
		$('#FLTtorrents_start_stopped').prop("checked",(flt.start==0));
		$('#FLTchktitle').prop("checked",(flt.chktitle==1));
		$('#FLTchkdesc').prop("checked",(flt.chkdesc==1));
		$('#FLTchklink').prop("checked",(flt.chklink==1));
		$('#FLT_label').val(flt.label);
		$('#FLT_rss').val(flt.hash);
		$('#FLT_interval').val(flt.interval);
		$('#FLT_throttle').val(flt.throttle);
		$('#FLT_ratio').val(flt.ratio);
		if(plugin.editFilersBtn)
			plugin.editFilersBtn.hide();
	}
}

theWebUI.setDisableControls = function( val )
{
	$('#FLT_body').prop('disabled', val);
	$('#FLT_exclude').prop('disabled', val);
	$('#FLTdir_edit').prop('disabled', val);
	$('#FLTnot_add_path').prop('disabled', val);
	$('#FLTtorrents_start_stopped').prop('disabled', val);
	$('#FLTchktitle').prop('disabled', val);
	$('#FLTchkdesc').prop('disabled', val);
	$('#FLTchklink').prop('disabled', val);
	$('#FLT_label').prop('disabled', val);
	$('#FLT_rss').prop('disabled', val);
	$('#FLT_interval').prop('disabled', val);
	$('#FLT_throttle').prop('disabled', val);
	$('#FLT_ratio').prop('disabled', val);
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

	var additions = [];
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
	for(var lbl in this.rssGroups)
		$('#FLT_rss').append("<option value='"+lbl+"'>"+this.rssGroups[lbl].name+"</option>");
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
			$("#_fe"+i).prop("checked",true);
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
	var elem = $("#_fn0");
	if (elem.length) {
		elem.trigger('focus');
	} else {
		this.setDisableControls(true);
	}
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
		$("#_fe"+i).prop("checked",true);
	$("#_fn"+i).trigger('focus');
}

theWebUI.deleteCurrentFilter = function()
{
	if (this.curFilter === null)
		return;
	var no = parseInt(this.curFilter.id.substr(3));
	this.filters.splice(no,1);
	$(this.curFilter).parent().remove();
	this.curFilter = null;
	if(this.filters.length)
	{
		for(var i=no+1; i<this.filters.length+1; i++)
		{
			$("#_fn"+i).prop("id", "_fn"+(i-1));
			$("#_fe"+i).prop("id", "_fe"+(i-1));
		}
		if(no>=this.filters.length)
			no = no - 1;
		$("#_fn"+no).trigger('focus');	
	}
	else
	{
		this.setDisableControls(true);
		if(plugin.editFilersBtn)
			plugin.editFilersBtn.hide();
		$('#FLT_body,#FLT_exclude,#FLTdir_edit,#FLT_label,#FLT_rss,#FLT_throttle,#FLT_ratio').val('');
		$('#FLTnot_add_path,#FLTchkdesc,#FLTchklink,#FLTtorrents_start_stopped').prop("checked",false);
		$('#FLTchktitle').prop("checked",true);
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
	this.showErrors(d.errors);
	if(d.rss && d.rss.length)
		this.switchLabel($$(d.rss));
	else
		this.switchLabel($$('_rssAll_'));
	var table = this.getTable("rss");
	for(var k in table.rowSel)
		table.rowSel[k] = false;
	this.getTable("trt").selCount = d.count;
	var labels = [];
	var dirs = [];
	for(var i in d.list)
	{
		table.rowSel[i] = true;
		if(d.list[i].dir.length)
		{
			if(dirs.length<3)
				dirs.push(d.list[i].dir);
			else
			if(dirs.length==3)
				dirs.push('...');
		}
		if(d.list[i].label.length)
		{
			if(labels.length<3)
				labels.push(d.list[i].label);
			else
			if(labels.length==3)
				labels.push('...');
		}			
	}
	table.refreshSelection();
	var s = theUILang.foundedByFilter+" : "+d.count;
	if(labels.length)
		s+=('\n'+theUILang.Labels+" : "+labels.join(", "));
	if(dirs.length)
		s+=('\n'+theUILang.Directories+" : "+dirs.join(", "));
	alert(s);
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

plugin.getFirstRSS = function(item)
{
	var ret = '';
	for(var k in item.rss)
	{
		ret = k;
		break;
	}
	return(ret);
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

rTorrentStub.prototype.rssCommon = function(content) {
	this.content = content;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/rss/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.clearfiltertime = function()
{
	this.rssCommon("mode=clearfiltertime&no="+this.vs[0]);
}

rTorrentStub.prototype.getrssdetails = function()
{
	var ndx = decodeURIComponent(this.ss[0]);
	this.rssCommon("mode=getdesc&href="+this.ss[0]+"&rss="+plugin.getFirstRSS(theWebUI.rssItems[ndx]));
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.setrsssettings = function()
{
	this.rssCommon("mode=setsettings&interval="+this.ss[0]+"&delayerrui="+this.ss[1]);
}

rTorrentStub.prototype.getrssdetailsResponse = function(data)
{
    	const doc = new DOMParser().parseFromString(String(data), 'text/html');
    	var s = new Sanitize(Sanitize.Config.RESTRICTED);
    	$("#rsslayout").html(s.clean_node(doc.body));
    	return(false);
}


rTorrentStub.prototype.setfilters = function()
{
	let content = "mode=setfilters";
	theWebUI.storeFilterParams();
	for(let i=0; i<theWebUI.filters.length; i++)
	{
		const flt = theWebUI.filters[i];
		const enabled = $("#_fe"+i).prop("checked") ? 1 : 0;
		const name = $("#_fn"+i).val();
		content = content+"&name="+encodeURIComponent(name)+"&pattern="+encodeURIComponent(flt.pattern)+"&enabled="+enabled+
			"&chktitle="+flt.chktitle+
			"&chklink="+flt.chklink+
			"&chkdesc="+flt.chkdesc+
		        "&exclude="+encodeURIComponent(flt.exclude)+
			"&hash="+flt.hash+"&start="+flt.start+"&addPath="+flt.add_path+
			"&dir="+encodeURIComponent(flt.dir)+"&label="+encodeURIComponent(flt.label)+"&interval="+flt.interval+"&no="+flt.no;
		if($type(flt.throttle))
			content+=("&throttle="+flt.throttle);
		if($type(flt.ratio))
			content+=("&ratio="+flt.ratio);
	}
	this.rssCommon(content);
}

rTorrentStub.prototype.checkfilter = function()
{
	const no = theWebUI.storeFilterParams();
	const flt = theWebUI.filters[no];
	let content = "mode=checkfilter&pattern="+encodeURIComponent(flt.pattern)+"&exclude="+encodeURIComponent(flt.exclude)+
		"&label="+encodeURIComponent(flt.label)+"&directory="+encodeURIComponent(flt.dir)+
		"&chktitle="+flt.chktitle+"&chklink="+flt.chklink+"&chkdesc="+flt.chkdesc;
	if(flt.hash.length)
		content = content+"&rss="+flt.hash;
	this.rssCommon(content);
}

rTorrentStub.prototype.addrss = function()
{
	this.rssCommon("mode=add&url="+this.vs[0]+"&label="+this.ss[0]);
}

rTorrentStub.prototype.addrssgroup = function()
{
	let content = "mode=addgroup&label="+encodeURIComponent( $('#rssGroupLabel').val() )+"&hash="+$("#rssGroupHash").val();
	for(const lbl in theWebUI.rssLabels)
		if($('#grp_'+lbl).prop('checked'))
			content += ('&rss='+lbl);
	this.rssCommon(content);
}

rTorrentStub.prototype.editrss = function()
{
	let content = "mode=edit&url="+this.vs[0]+"&label="+this.ss[0];
	if(theWebUI.actRSSLbl && (theWebUI.actRSSLbl != "_rssAll_"))
		content = content + "&rss=" + theWebUI.actRSSLbl;
	this.rssCommon(content);
}

rTorrentStub.prototype.loadrss = function()
{
	this.rssCommon("mode=get");
}

rTorrentStub.prototype.loadrsstorrents = function()
{
	let content = "mode=loadtorrents";
	if($("#RSStorrents_start_stopped").prop("checked"))
		content = content + '&torrents_start_stopped=1';
	if($("#RSSnot_add_path").prop("checked"))
		content = content + '&not_add_path=1';
	const dir = $("#RSSdir_edit").val().trim();
	if(dir.length)
		content = content + '&dir_edit='+encodeURIComponent(dir);
	const lbl = $("#RSS_label").val().trim();
	if(lbl.length)
		content = content + '&label='+encodeURIComponent(lbl);
	for(let i = 0; i<theWebUI.rssArray.length; i++)
	{
		const item = theWebUI.rssItems[theWebUI.rssArray[i]];
		content = content + '&rss='+plugin.getFirstRSS(item)+'&url='+encodeURIComponent(item.href);
	}
	this.rssCommon(content);
}

rTorrentStub.prototype.loadrsstorrentsResponse = function(data)
{
	theWebUI.getTorrents("list=1");
	return(data);
}

rTorrentStub.prototype.clearhistory = function()
{
	this.rssCommon("mode=clearhistory");
}

rTorrentStub.prototype.rssrefresh = function()
{
	let content = "mode=refresh";
	if(theWebUI.actRSSLbl && (theWebUI.actRSSLbl != "_rssAll_"))
		content = content + "&rss=" + theWebUI.actRSSLbl;
	this.rssCommon(content);
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.rssgrouprefresh = function()
{
	this.rssCommon("mode=refreshgroup&rss=" + theWebUI.actRSSLbl);
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.rsstoggle = function()
{
	let content = "mode=toggle";
	if(theWebUI.actRSSLbl && (theWebUI.actRSSLbl != "_rssAll_"))
		content += "&rss=" + theWebUI.actRSSLbl;
	this.rssCommon(content);
}

rTorrentStub.prototype.rssmarkstate = function()
{
	let content = "mode=mark&state="+this.ss[0];
	for( let i=0; i<theWebUI.rssArray.length; i++)
	{
		const href = theWebUI.rssArray[i];
		content+=("&url="+encodeURIComponent(href));
		content+=("&time="+theWebUI.rssItems[href].time);
	}
	this.rssCommon(content);
}

rTorrentStub.prototype.rssgroupstatus = function()
{
	this.rssCommon("mode=setgroupstate&state="+this.ss[0]+"&rss=" + theWebUI.actRSSLbl);
}

rTorrentStub.prototype.rssremove = function()
{
	let content = "mode=remove";
	if(theWebUI.actRSSLbl && (theWebUI.actRSSLbl != "_rssAll_"))
		content += "&rss=" + theWebUI.actRSSLbl;
	this.rssCommon(content);
}

rTorrentStub.prototype.rssgroupremove = function()
{
	this.rssCommon("mode=removegroup&rss=" + theWebUI.actRSSLbl);
}

rTorrentStub.prototype.rssgroupremovecontents = function()
{
	this.rssCommon( "mode=removegroupcontents&rss=" + theWebUI.actRSSLbl);
}

rTorrentStub.prototype.getfilters = function()
{
	this.rssCommon("mode=getfilters");
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.getrsssettings = function()
{
	this.rssCommon("mode=getsettings");
}

plugin.correctRatioFilterDialog = function()
{
	var rule = getCSSRule(".rf fieldset");
	if(rule && thePlugins.get('ratio').allStuffLoaded)
	{
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

	plugin.addPaneToCategory("prss",theUILang.rssFeeds)
		.append( $('<ul>').prop('id', 'rssl')
			.append(
				theWebUI.createSelectableLabelElement(
					'_rssAll_',
					theUILang.allFeeds,
					theWebUI.rssLabelContextMenu
				).addClass('RSS')
		));
	$("#prss").append( $("<span></span>").attr("id", "rsstimer") );

	this.attachPageToOptions( $("<div>").attr("id","st_rss").html(
		"<fieldset>"+
			"<legend>"+theUILang.rssFeeds+"</legend>"+
			"<label for='rss_interval'>"+ theUILang.rssUpdateInterval + ' (' + theUILang.time_m.trim() +")</label>"+
			"<input type='text' maxlength=4 id='rss_interval' class='TextboxShort'/><br/>"+
			"<input type='checkbox' class='chk' id='rss_show_errors_delayed'/>"+
			"<label for='rss_show_errors_delayed'>"+ theUILang.rssShowErrorsDelayed +"</label>"+
		"</fieldset>"
		)[0], theUILang.rssFeeds );
	
	theDialogManager.make( "dlgAddRSS", theUILang.addRSS,
		"<div class='content'>"+
			"<label>"+theUILang.feedURL+": </label>"+
			"<input type='text' id='rssURL' class='TextboxLarge'/><br/>"+
			"<label>"+theUILang.alias+": </label>"+
			"<input type='text' id='rssLabel' class='TextboxLarge'/>"+
		"</div>"+
		"<div class='aright buttons-list'><input type='button' class='OK Button' value="+theUILang.ok+" onclick='theDialogManager.hide(\"dlgAddRSS\");theWebUI.addRSS();return(false);'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",
		true);
	theDialogManager.make( "dlgAddRSSGroup", theUILang.addRSSGroup,
		"<div class='content'>"+
			"<label>"+theUILang.alias+": </label>"+
			"<input type='hidden' id='rssGroupHash' value=''/>"+
			"<input type='text' id='rssGroupLabel' class='TextboxLarge'/>"+
			"<fieldset><legend>"+theUILang.addRSSGroupContent+"</legend>"+
				"<div id='rssGroupSet'>"+
				"</div>"+
			"</fieldset>"+
		"</div>"+
		"<div class='aright buttons-list'><input type='button' class='OK Button' value="+theUILang.ok+" onclick='theWebUI.addRSSGroup();return(false);'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",
		true);
	theDialogManager.make( "dlgEditRSS", theUILang.rssMenuEdit,
		"<div class='content'>"+
			"<label>"+theUILang.feedURL+": </label>"+
			"<input type='text' id='editrssURL' class='TextboxLarge'/><br/>"+
			"<label>"+theUILang.alias+": </label>"+
			"<input type='text' id='editrssLabel' class='TextboxLarge'/>"+
		"</div>"+
		"<div class='aright buttons-list'><input type='button' class='OK Button' value="+theUILang.ok+" onclick='theDialogManager.hide(\"dlgEditRSS\");theWebUI.editRSS();return(false);'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",
		true);
	theDialogManager.make( "dlgLoadTorrents", theUILang.torrent_add,
		"<div class='content'>"+
			"<label>"+theUILang.Base_directory+":</label><input type='text' id='RSSdir_edit' class='TextboxLarge'/><br/>"+
			"<label></label><input type='checkbox' id='RSSnot_add_path'/>"+theUILang.Dont_add_tname+"<br/>"+
			"<label></label><input type='checkbox' id='RSStorrents_start_stopped'/>"+theUILang.Dnt_start_down_auto+"<br/>"+
			"<label>"+theUILang.Label+":</label><input type='text' id='RSS_label' class='TextboxLarge'/>"+
		"</div>"+
		"<div id='buttons' class='aright buttons-list'><input type='button' class='OK Button' value="+theUILang.ok+" onclick='theDialogManager.hide(\"dlgLoadTorrents\");theWebUI.RSSLoadTorrents();return(false);'/><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>",
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
					"<label></label><input type='checkbox' class='chk' id='FLTchkdesc'/>"+theUILang.rssCheckDescription+"<br/>"+
					"<label></label><input type='checkbox' class='chk' id='FLTchklink'/>"+theUILang.rssCheckLink+"<br/>"+
					"<label>"+theUILang.rssStatus+":</label><select id='FLT_rss'><option value=''>"+theUILang.allFeeds+"</option></select><br/>"+
					"<label>"+theUILang.Base_directory+":</label><input type='text' id='FLTdir_edit' class='TextboxLarge'/><br/>"+
					"<label></label><input type='checkbox' class='chk' id='FLTnot_add_path'/>"+theUILang.Dont_add_tname+"<br/>"+
					"<label></label><input type='checkbox' class='chk' id='FLTtorrents_start_stopped'/>"+theUILang.Dnt_start_down_auto+"<br/>"+
					"<label>"+theUILang.rssMinInterval+":</label><select id='FLT_interval'>"+
					        "<option value='-1'>"+theUILang.rssIntervalAlways+"</option>"+
					        "<option value='0'>"+theUILang.rssIntervalOnce+"</option>"+
					        "<option value='12'>"+theUILang.rssInterval12h+"</option>"+
					        "<option value='24'>"+theUILang.rssInterval1d+"</option>"+
					        "<option value='48'>"+theUILang.rssInterval2d+"</option>"+
					        "<option value='72'>"+theUILang.rssInterval3d+"</option>"+
					        "<option value='96'>"+theUILang.rssInterval4d+"</option>"+
						"<option value='144'>"+theUILang.rssInterval6d+"</option>"+
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
			"<input type='button' class='OK Button' value='"+theUILang.ok+"' onclick='theDialogManager.hide(\"dlgEditFilters\");theWebUI.setFilters();return(false);'/>"+
			"<input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/>"+
		"</div>");
	$("#gcont").append( $("<div>").attr("id","rsslayout").css( "display", "none" ));

	if(thePlugins.isInstalled("_getdir"))
	{
		$('#RSSdir_edit').after($("<input type=button>").addClass("Button").attr("id","RSSBtn").on('focus', function() { this.blur(); } ));
		var btn = new theWebUI.rDirBrowser( 'dlgLoadTorrents', 'RSSdir_edit', 'RSSBtn' );
		theDialogManager.setHandler('dlgLoadTorrents','afterHide',function()
		{
			btn.hide();
		});
		$('#FLTdir_edit').after($("<input type=button>").addClass("Button").attr("id","FLTBtn").on('focus', function() { this.blur(); } ));
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
	theWebUI.resetLabels();
	$("#RSSList").remove();
	plugin.removePaneFromCategory("prss");
	$("#rsslayout").remove();
	theDialogManager.hide("dlgAddRSS");
	theDialogManager.hide("dlgEditRSS");
	theDialogManager.hide("dlgLoadTorrents");
	theDialogManager.hide("dlgEditFilters");
	this.removeButtonFromToolbar("rss");
	plugin.removePageFromOptions("st_rss");
}
