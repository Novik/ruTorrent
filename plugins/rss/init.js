plugin.loadMainCSS();
if(browser.isIE && browser.versionMajor < 8)
	plugin.loadCSS("ie");
plugin.loadLang();

// Dynamically import module for transforming bbcode to html
var bbcode = null;
import(`./${plugin.path}bbcode.js`)
	.then(bbcodeModule => {
		bbcode = bbcodeModule;
	})
	.catch((err) => {
		if (!bbcode) console.error(err);
		else console.log('"bbcode" initialized without dynamic module import!');
	});

theWebUI.rssListVisible = false;
theWebUI.rssShowErrorsDelayed = true;
theWebUI.delayedRSSErrors = {};
const catlist = theWebUI.categoryList;

injectCustomElementCSS('panel-label', plugin.path + 'panel-label.css');
injectCustomElementAttribute('panel-label', 'alert', function(oldValue, newValue) {
	if (oldValue) {
		const element = this.shadowRoot.querySelector('.alert');
		if (element) element.remove();
	}
	if (newValue) {
		const div = document.createElement('div');
		div.classList.add('alert');
		div.textContent = newValue;
		this.shadowRoot.appendChild(div);
	}
});

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
plugin.actRSSLbl = () => catlist.selection.ids('prss')[0] ?? null;

const origSwitchLabel = catlist.switchLabel.bind(catlist);
catlist.switchLabel = function(panelId, targetId, toggle=false, range=false)
{
	const rssListVisible = theWebUI.rssListVisible;
	let change;
	if(panelId === 'prss') {
		change = origSwitchLabel(panelId, targetId) || !rssListVisible;
		if (change) {
			theWebUI.switchRSSLabel();
		}
	} else {
		if(rssListVisible)
		{
			theWebUI.rssListVisible = false;
			catlist.refresh('prss');

			const rss = $("#RSSList");
			const trt = theWebUI.getTable("trt");
			trt.resize(rss.width(), rss.height());
			trt.clearSelection();
			theWebUI.dID = "";
			theWebUI.clearDetails();
			theWebUI.getTable("rss").clearSelection();
			rss.hide();
			$("#List").show();
			theWebUI.switchLayout(false);
		}
		if (!origSwitchLabel(panelId, targetId, toggle, range) && rssListVisible) {
			catlist.syncFn();
		}
	}
	return change;
}

plugin.resizeTop = theWebUI.resizeTop.bind(theWebUI);
theWebUI.resizeTop = function (w, h) {
	theWebUI.getTable("rss").resize(w, h);
	plugin.resizeTop(w, h);
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

theWebUI.shouldShowRSSRow = function(href) {
	const actLabelId = plugin.actRSSLbl();
	const item = this.rssItems[href];
	return !actLabelId ||
		this.isGroupContain( this.rssGroups[actLabelId], item) ||
		item.rss.has(actLabelId);
};

theWebUI.switchRSSLabel = function()
{
	var table = theWebUI.getTable("rss");
	table.scrollTo(0);
	for(const href in theWebUI.rssItems)
	{
		if(this.shouldShowRSSRow(href))
			table.unhideRow(href);
		else
			table.hideRow(href);
	}
	table.clearSelection();
	if(!theWebUI.rssListVisible)
	{
		theWebUI.rssListVisible = true;
		catlist.refresh('prss');

		const rss = $("#RSSList");
		const lst = $("#List");
		theWebUI.dID = "";
		theWebUI.clearDetails();
		table.resize(lst.width(), lst.height());
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
	theWebUI.delayedRSSErrors = {};
	for (const argss of notyArgss) {
		for (const args of argss) {
			noty(...args);
		}
	}
	if (plugin.allStuffLoaded) {
		catlist.refreshAndSyncPanel('prss');
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
	this.updateRSSTimer = 0;
	this.rsstimerUpdateIntervalId = 0;
	this.updateRSSInterval = 5*60*1000;
	this.rssUpdateTimestamp = Date.now() + this.updateRSSInterval;
	this.rssID = "";
	this.rssArray = [];
	this.filters = [];	
	$("#List").after(
		$("<div>").attr("id","RSSList").css("display","none"),
	);
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
	{
		theWebUI.request("?action=getrsssettings",[theWebUI.getRSSSettings, theWebUI]);
		theWebUI.rsstimerUpdateIntervalId = setInterval(
			() => $("#rsstimer").text(
				theConverter.time(Math.floor(
					theWebUI.rssTimeUntilNextUpdate() / 1000
			))), 1000);
	}
	else
		setTimeout(arguments.callee,1000);
}

theWebUI.rssTimeUntilNextUpdate = function () {
	const timeSinceUpdate = Date.now() - theWebUI.rssUpdateTimestamp;
	// The frontend reads the cached feeds from the backend.
	// Expect that the backend at most needs 45 seconds to fetch feeds
	const expectedFetchDelay = 45 * 1000;
	return (
		theWebUI.updateRSSInterval -
			((theWebUI.updateRSSInterval + timeSinceUpdate - expectedFetchDelay) %
			theWebUI.updateRSSInterval)
		);
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

theWebUI.getRSSSettings = function( d )
{
	theWebUI.updateRSSInterval = d.interval*60000;	
	theWebUI.rssUpdateTimestamp = d.updatedAt * 1000;
	if(theWebUI.updateRSSTimer)
		clearTimeout(theWebUI.updateRSSTimer);
	const loadRSSLoop = () => {
		theWebUI.loadRSS();
		theWebUI.updateRSSTimer = setTimeout(
			loadRSSLoop,
			theWebUI.rssTimeUntilNextUpdate()
		);
	};
	loadRSSLoop();

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
	const rssLabel = theWebUI.rssLabels[plugin.actRSSLbl()]
	if(rssLabel)
	{
		$('#editrssURL').val( rssLabel.url );
		$('#editrssLabel').val( rssLabel.name );
		theDialogManager.show("dlgEditRSS");
	}
}

theWebUI.RSSManager = function()
{
	theWebUI.request("?action=getfilters",[this.loadFilters, this]);
}

plugin.contextMenuTable = theWebUI.contextMenuTable;
theWebUI.contextMenuTable = function(panelId, labelId) {
	return panelId === 'prss' ?
		theWebUI.getTable('rss')
		: plugin.contextMenuTable.call(theWebUI, panelId, labelId);
},

plugin.contextMenuEntries = catlist.contextMenuEntries.bind(catlist);
catlist.contextMenuEntries = function(panelId, labelId) {
	return panelId === 'prss' ?
		theWebUI.createRSSMenuPrim()
		: plugin.contextMenuEntries(panelId, labelId);
}

theWebUI.fillRSSGroups = function() {
	const content = $("#rssGroupSet");
	content.children().remove();
	content.append(
		Object.entries(this.rssLabels).map(([rssHash, rssObj]) => $("<div>").addClass("col-12").append(
			$("<input>").attr({type:"checkbox", id:`grp_${rssHash}`}),
			$("<label>").attr({for:`grp_${rssHash}`, id:`lbl_grp_${rssHash}`}).text(rssObj["name"]),
		),
	));
}

theWebUI.RSSEditGroup = function()
{
	theWebUI.fillRSSGroups();
	var grp = theWebUI.rssGroups[plugin.actRSSLbl()];
	for(var i=0; i<grp.lst.length; i++)
		$('#grp_'+grp.lst[i]).prop('checked',true);
	$("#rssGroupLabel").val(grp.name);
	$("#dlgAddRSSGroup-header").html(theUILang.editRSSGroup);
	$("#rssGroupHash").val(plugin.actRSSLbl());
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
	theWebUI.getTable('trt').clearSelection();
	theWebUI.dID = "";
	theWebUI.clearDetails();
	if(!plugin.canChangeMenu()) {
		return false;
	}
	let entries = [];
	entries = [
		[ theUILang.rssMenuClearHistory, "theWebUI.RSSClearHistory()"],
		[ theUILang.addRSS, "theDialogManager.toggle('dlgAddRSS')"],
		[ theUILang.addRSSGroup, "theWebUI.RSSAddGroup()"],
		[ theUILang.rssMenuManager, "theWebUI.RSSManager()"]
	];
	const actLabelId = plugin.actRSSLbl();
	if(actLabelId)
	{
		entries.push([CMENU_SEP]);
		if(!actLabelId)
		{
			entries = entries.concat([
				[ theUILang.rssMenuDisable ],
				[ theUILang.rssMenuEdit ],
				[ theUILang.rssMenuRefresh, "theWebUI.RSSRefresh()"],
				[ theUILang.rssMenuDelete ]
			]);
		}
		else
		{
			if(actLabelId in this.rssGroups)
			{
				entries = entries.concat(this.rssGroups[actLabelId].enabled==1 ? [
					[ theUILang.rssMenuGroupDisable, "theWebUI.RSSGroupSetStatus(0)"],
					[ theUILang.rssMenuGroupRefresh, "theWebUI.RSSGroupRefresh()"]
				] : [
					[ theUILang.rssMenuGroupEnable, (this.rssGroups[actLabelId].cnt==0) ? null : "theWebUI.RSSGroupSetStatus(1)"],
					[ theUILang.rssMenuGroupRefresh ]
				]).concat([
					[ theUILang.rssMenuGroupEdit, "theWebUI.RSSEditGroup()"],
					[ theUILang.rssMenuGroupDelete, "theWebUI.RSSGroupDelete()"],
					[ theUILang.rssMenuGroupContentsDelete, "theWebUI.RSSGroupDeleteContents()"]
				]);
			}
			else
			{
				entries = entries.concat(this.rssLabels[actLabelId].enabled==1 ? [
					[ theUILang.rssMenuDisable, "theWebUI.RSSToggleStatus()"],
					[ theUILang.rssMenuRefresh, "theWebUI.RSSRefresh()"]
				] : [
					[ theUILang.rssMenuEnable, "theWebUI.RSSToggleStatus()"],
					[ theUILang.rssMenuRefresh ]
				]).concat([
					[ theUILang.rssMenuEdit, "theWebUI.RSSEdit()"],
					[ theUILang.rssMenuDelete, "theWebUI.RSSDelete()"]
				]);
			}
		}
	}
	return entries;
}

theWebUI.RSSAddToFilter = function()
{
	theWebUI.request("?action=getfilters",[this.loadFiltersWithAdditions, this]);
}

plugin.createMenu = theWebUI.createMenu;
theWebUI.createMenu = function(e, id)
{
	if (id in this.rssItems) {
		// context menu for rss item
		theWebUI.createRSSMenu(e, id);
	} else {
		plugin.createMenu.call(theWebUI, e, id);
	}
}

theWebUI.createRSSMenu = function(e, id)
{
	const [rssArray, trtArray] = Object.entries(this.getTable("rss").rowSel)
	.filter(([_, sel]) => sel)
	.map(([link,_]) => [link, this.rssItems[link].hash])
	.reduce((rss_trt, [l,h]) => {
		const isTorrent = h && h in theWebUI.torrents;
		rss_trt[isTorrent ? 1 : 0].push(isTorrent ? h : l);
		return rss_trt;
	}, [[],[]]);

	this.rssArray = rssArray;

	theContextMenu.clear();
	if(this.rssArray.length)
	{
		if(plugin.canChangeMenu())
		{
			theContextMenu.add([ theUILang.rssMenuLoad, "theWebUI.RSSLoad()"]);
			theContextMenu.add([ theUILang.rssMenuOpen, "theWebUI.RSSOpen()"]);
			theContextMenu.add([ theUILang.rssMenuAddToFilter, "theWebUI.RSSAddToFilter()"]);
			theContextMenu.add([CMENU_CHILD, theUILang.rssMarkAs, [ [ theUILang.rssAsLoaded, "theWebUI.RSSMarkState(1)"], [ theUILang.rssAsUnloaded, "theWebUI.RSSMarkState(0)"] ]]);
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
	}
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
		if(updated && table.sortId)
			table.Sort();
	}
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
	return rssGroup && rssGroup.lst.some(href => rssItem.rss.has(href));
}

plugin.updatedRSSEntry = (labelId, attrs) => {
	attrs = { ...catlist.panelLabelAttribs.prss.get(labelId), ...attrs };
	let icon = labelId in theWebUI.rssGroups ? 'rss-group' : 'rss';
	const count = String(
		labelId === 'prss_all'
			? Object.keys(theWebUI.rssItems).length
			: (theWebUI.rssLabels[labelId] ?? theWebUI.rssGroups[labelId]).cnt);
	const title = `${attrs.text} (${count})`;
	const selected = theWebUI.rssListVisible && catlist.isLabelIdSelected('prss', labelId);
	const alert = labelId in theWebUI.delayedRSSErrors ? '⚠' : null;
	// Check if enabled is 0, then change icon
        if (theWebUI.rssLabels[labelId]?.enabled === 0) {
                icon = 'rss-dis';
        }
	return [labelId, {...attrs, icon, count, title, selected, alert}];
}

// Update feed and group RSS labels
catlist.refreshPanel.prss = () => [
	plugin.updatedRSSEntry('prss_all'),
	...[theWebUI.rssGroups, theWebUI.rssLabels]
	.flatMap((rssLabels) => Object.entries(rssLabels)
			.map(([_, feed]) => [_, feed.name])
			.sort( ([_,a], [__, b]) => a.localeCompare(b))
			.map(([labelId, text]) => plugin.updatedRSSEntry(labelId, { text }))
)];

theWebUI.showRSS = function()
{
        if(Object.keys(theWebUI.rssLabels).length > 0)
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
			const id = idHash || 'prss_all';
			$('#tab_lcont').addClass('notification');
			const delayed = theWebUI.delayedRSSErrors;
			delayed[id] = id in delayed ? delayed[id].concat([args]) : [args];
			catlist.refreshAndSyncPanel('prss');
		}
	}
}

theWebUI.addRSSItems = function(d)
{
	const rssItems = {};
	const rssLabels = {};
	const table = this.getTable("rss");
	const trtTable = this.getTable('trt');
	// Insert items in rss table
	for (const {hash, label, items, enabled, url} of d.list) {
		rssLabels[hash] = { name: label, cnt: items.length, enabled, url };
		for (const item of items) {
			if (item.hash in this.torrents) {
				// Copy torrent data of item from trt table
				table.setRowById(
					Object.fromEntries(
						trtTable.getValues(item.hash)
						.map((v,i) => [trtTable.ids[i], v])
					),
					item.href,
					trtTable.getIcon(item.hash),
					{}
				);
			} else {
				// Insert item as rss row
				table.setRowById(
					{
						name: item.title,
						status: (item.hash=="")
							? theUILang.rssStatus
							: (item.hash=="Failed")
							? theUILang.rssStatusError+" ("+item.errcount+")"
							: theUILang.rssStatusLoaded,
						label,
						created: item.time
					},
					item.href,
					"Status_RSS",
					{}
				);
			}
			const ritem = rssItems[item.href] ?? { label, rssFirst: hash, rss: new Set(), ...item };
			ritem.rss.add(hash);
			rssItems[item.href] = ritem;
		}
	}

	// Remove non-existent rss items from table
	for(const href in this.rssItems)
	{
		if (!(href in rssItems)) {
			table.removeRow(href);
		}
	}
	// Update rss group item counts and enabled state
	const allItems = Object.values(rssItems);
	for (const group of Object.values(d.groups)) {
		group.cnt = allItems
			.filter(item => theWebUI.isGroupContain(group, item))
			.length;
		group.enabled = group.lst
			.some(l => rssLabels[l]?.enabled);
	}

	this.rssItems = rssItems;
	this.rssLabels = rssLabels;
	this.rssGroups = Array.isArray(d.groups) ? {} : d.groups;

	catlist.refresh('prss');
	catlist.syncWithPrunedSelection('prss');

	this.showErrors(d.errors);
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
		return(ret+"$/i");
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
	var f = { name: theUILang.rssNewFilter, enabled: 1, pattern: "/^"+theUILang.rssPatternExample+"$/i", exclude: "", label: "", hash: "", start: 1, add_path: 1, dir: "", throttle: "", ratio: "", chktitle: 1, chkdesc: 0, chklink: 0, interval: -1, no: theWebUI.maxFilterNo };
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
	catlist.switchLabel('prss', d.rss && d.rss.length ? d.rss : null);
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

rTorrentStub.prototype.rssCommon = function(content, rssLabelId) {
	if (rssLabelId) {
		content += "&rss=" + rssLabelId;
	}
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
	this.rssCommon("mode=getdesc&href="+this.ss[0], theWebUI.rssItems[ndx].rssFirst);
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.setrsssettings = function()
{
	this.rssCommon("mode=setsettings&interval="+this.ss[0]+"&delayerrui="+this.ss[1]);
}

rTorrentStub.prototype.getrssdetailsResponse = function (data) {
	if (!bbcode) return false;
	const cfg = Sanitize.Config.RESTRICTED;
	const s = new Sanitize({
		elements: [...cfg.elements, "ins", "details", "summary"],
		transformers: [bbcode.bbclassTransform],
	});
	const rawHTML = String(data);
	const dirtyHTML = bbcode.mapBBCodeToHTML(rawHTML);
	const doc = new DOMParser().parseFromString(dirtyHTML, "text/html");
	$("#rsslayout")
		.empty()
		.append(
		$("<details>")
			.addClass('raw-details')
			.text(rawHTML)
			.append($("<hr>"))
			.append($("<summary>").text("Raw")),
		$('<div>').html(s.clean_node(doc.body)));
	return false;
};

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
	this.rssCommon("mode=edit&url="+this.vs[0]+"&label="+this.ss[0], plugin.actRSSLbl());
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
		content = content + '&rss='+item.rssFirst+'&url='+encodeURIComponent(item.href);
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
	this.rssCommon("mode=refresh", plugin.actRSSLbl());
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.rssgrouprefresh = function()
{
	this.rssCommon("mode=refreshgroup", plugin.actRSSLbl());
	this.method = 'GET';
	this.cache = true;
}

rTorrentStub.prototype.rsstoggle = function()
{
	this.rssCommon("mode=toggle", plugin.actRSSLbl());
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
	this.rssCommon("mode=setgroupstate&state="+this.ss[0], plugin.actRSSLbl());
}

rTorrentStub.prototype.rssremove = function()
{
	this.rssCommon("mode=remove", plugin.actRSSLbl());
}

rTorrentStub.prototype.rssgroupremove = function()
{
	this.rssCommon("mode=removegroup", plugin.actRSSLbl());
}

rTorrentStub.prototype.rssgroupremovecontents = function()
{
	this.rssCommon( "mode=removegroupcontents", plugin.actRSSLbl());
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

plugin.correctRatioFilterDialog = function() {
	if ($(".rf fieldset").length > 0 && thePlugins.get('ratio').allStuffLoaded) {
		$("#FLT_label").parent().after(
			$("<div>").addClass("col-12 col-md-3").append(
				$("<label>").attr({for:"FLT_ratio"}).text(theUILang.ratio),
			),
			$("<div>").addClass("col-12 col-md-3").append(
				$("<select>").attr({id:"FLT_ratio"}).append(
					$("<option>").val("").text(theUILang.mnuRatioUnlimited),
				),
			),
		);
	} else
		setTimeout(plugin.correctRatioFilterDialog,1000);
}

plugin.correctFilterDialog = function() {
	if ($(".rf fieldset").length > 0 && thePlugins.get('throttle').allStuffLoaded) {
		$("#FLT_label").parent().after(
			$("<div>").addClass("col-12 col-md-3").append(
				$("<label>").attr({for:"FLT_throttle"}).text(theUILang.throttle),
			),
			$("<div>").addClass("col-12 col-md-3").append(
				$("<select>").attr({id:"FLT_throttle"}).append(
					$("<option>").val("").text(theUILang.mnuUnlimited),
				),
			),
		);
	}
	else
		setTimeout(plugin.correctFilterDialog,1000);
}

plugin.onLangLoaded = function()
{
        this.addButtonToToolbar("rss",theUILang.mnu_rss,"theWebUI.showRSS()","settings");

	plugin.addPaneToCategory(
		"prss",
		theUILang.rssFeeds,
		[['prss_all', { text: theUILang.allFeeds, icon: 'rss' }]]
	);
	$("#prss").prepend( $("<span>").attr({ id: "rsstimer", slot: "decorator" }));

	this.attachPageToOptions(
		$("<div>").attr("id","st_rss").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.rssFeeds),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-12 col-md-3").append(
						$("<label>").attr({for:"rss_interval"}).text(`${theUILang.rssUpdateInterval} (${theUILang.time_m.trim()})`),
					),
					$("<div>").addClass("col-12 col-md-9").append(
						$("<input>").attr({type:"text", id:"rss_interval", maxlength:4}),
					),
					$("<div>").addClass("col-12").append(
						$("<input>").attr({type:"checkbox", id:"rss_show_errors_delayed"}),
						$("<label>").attr({for:"rss_show_errors_delayed"}).text(theUILang.rssShowErrorsDelayed),
					),
				),
			),
		)[0],
		theUILang.rssFeeds,
	);
	
	const dlgAddRSSContent = $("<div>").addClass("cont").append(
		$("<div>").addClass("row").append(
			...[
				["rssURL", theUILang.feedURL],
				["rssLabel", theUILang.alias],
			].flatMap(([id, text]) => [
				$("<div>").addClass("col-12 col-md-3").append(
					$("<label>").attr({for:id}).text(text),
				),
				$("<div>").addClass("col-12 col-md-9").append(
					$("<input>").attr({type:"text", id:id}),
				),
			]),
		),
	);
	const dlgAddRSSButtons = $("<div>").addClass("buttons-list").append(
		$("<button>").attr({type:"button"}).addClass("OK").on("click", () => {theDialogManager.hide('dlgAddRSS'); theWebUI.addRSS(); return false;}).text(theUILang.ok),
		$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
	);
	theDialogManager.make("dlgAddRSS", theUILang.addRSS,
		[dlgAddRSSContent, dlgAddRSSButtons],
		true,
	);

	const dlgAddRSSGroupContent = $("<div>").addClass("cont").append(
		$("<div>").addClass("row").append(
			$("<div>").addClass("col-12 col-md-3 text-nowrap").append(
				$("<label>").attr({for:"rssGroupLabel"}).text(theUILang.alias + ": "),
			),
			$("<div>").addClass("col-12 col-md-9").append(
				$("<input>").attr({type:"hidden", id:"rssGroupHash"}),
				$("<input>").attr({type:"text", id:"rssGroupLabel"}),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.addRSSGroupContent),
				$("<div>").attr({id:"rssGroupSet"}).addClass("d-flex flex-column align-items-start overflow-y-auto"),
			),
		),
	);
	const dlgAddRSSGroupButtons = $("<div>").addClass("buttons-list").append(
		$("<button>").attr({type:"button"}).addClass("OK").text(theUILang.ok).on("click", () => {theWebUI.addRSSGroup(); return false;}),
		$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
	);
	theDialogManager.make("dlgAddRSSGroup", theUILang.addRSSGroup,
		[dlgAddRSSGroupContent, dlgAddRSSGroupButtons],
		true,
	);

	const dlgEditRSSContent = $("<div>").addClass("cont").append(
		$("<div>").addClass("row").append(
			...[
				["editrssURL", theUILang.feedURL],
				["editrssLabel", theUILang.alias],
			].flatMap(([id, text]) => [
				$("<div>").addClass("col-12 col-md-3").append(
					$("<label>").attr({for:id}).text(text),
				),
				$("<div>").addClass("col-12 col-md-9").append(
					$("<input>").attr({type:"text", id:id}),
				),
			]),
		),
	);
	const dlgEditRSSButtons = $("<div>").addClass("buttons-list").append(
		$("<button>").attr({type:"button"}).addClass("OK").text(theUILang.ok).on("click", () => {theDialogManager.hide("dlgEditRSS"); theWebUI.editRSS(); return false;}
		),
		$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
	);
	theDialogManager.make("dlgEditRSS", theUILang.rssMenuEdit,
		[dlgEditRSSContent, dlgEditRSSButtons],
		true,
	);

	const dlgLoadTorrentsContent = $("<div>").addClass("cont fxcaret").append(
		$("<div>").addClass("row").append(
			$("<div>").addClass("col-12 col-md-3").append(
				$("<label>").attr({for:"RSSdir_edit"}).text(theUILang.Base_directory),
			),
			$("<div>").addClass("col-12 col-md-9").append(
				$("<input>").attr({type:"text", id:"RSSdir_edit"}),
			),
			$("<div>").addClass("col-12 col-md-9 offset-md-3").append(
				$("<input>").attr({type:"checkbox", id:"RSSnot_add_path"}),
				$("<label>").attr({for:"RSSnot_add_path"}).text(theUILang.Dont_add_tname),
			),
			$("<div>").addClass("col-12 col-md-9 offset-md-3").append(
				$("<input>").attr({type:"checkbox", id:"RSStorrents_start_stopped"}),
				$("<label>").attr({for:"RSStorrents_start_stopped"}).text(theUILang.Dnt_start_down_auto),
			),
			$("<div>").addClass("col-12 col-md-3").append(
				$("<label>").attr({for:"RSS_label"}).text(theUILang.Label),
			),
			$("<div>").addClass("col-12 col-md-9").append(
				$("<input>").attr({type:"text", id:"RSS_label"})
			),
		),
	);
	const dlgLoadTorrentsButtons = $("<div>").attr({id:"buttons"}).addClass("buttons-list").append(
		$("<button>").attr({type:"button"}).addClass("OK").on("click", () => {theDialogManager.hide('dlgLoadTorrents'); theWebUI.RSSLoadTorrents(); return false;}).text(theUILang.ok),
		$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
	);
	theDialogManager.make("dlgLoadTorrents", theUILang.torrent_add,
		[dlgLoadTorrentsContent, dlgLoadTorrentsButtons],
		true,
	);

	const dlgEditFiltersContent = $("<div>").addClass("cont d-flex flex-column flex-md-row align-items-stretch align-items-md-start").append(
		$("<div>").addClass("lfc flex-grow-0 flex-shrink-0 d-flex flex-column align-items-center").append(
			$("<div>").attr({id:"filterList"}).addClass("lf p-2 m-2 align-self-stretch").append(
				$("<ul>").attr({id:"fltlist"}).addClass("p-0 m-0"),
			),
			$("<div>").attr({id:"FLTchk_buttons"}).append(
				...[
					[theUILang.rssAddFilter, , "theWebUI.addNewFilter(); return false;"],
					[theUILang.rssDelFilter, , "theWebUI.deleteCurrentFilter(); return false;"],
					[theUILang.rssCheckFilter, "chkFilterBtn", "theWebUI.checkCurrentFilter(); return false;"],
				].map(([text, id, onClick]) => $("<button>").attr(
					{type:"button", id:id, onClick:onClick}
				).text(text)),
			),
		),
		$("<div>").attr({id:"filterProps"}).addClass("rf flex-grow-1 flex-shrink-1").append(
			$("<fieldset>").attr({id:"filterPropsFieldSet"}).append(
				$("<legend>").text(theUILang.rssFiltersLegend),
				$("<div>").addClass("row").append(
					...[
						["text", "FLT_body", theUILang.rssFilter],
						["text", "FLT_exclude", theUILang.rssExclude],
						["checkbox", "FLTchktitle", theUILang.rssCheckTitle],
						["checkbox", "FLTchkdesc", theUILang.rssCheckDescription],
						["checkbox", "FLTchklink", theUILang.rssCheckLink],
						["select", "FLT_rss", theUILang.rssStatus, ["", theUILang.allFeeds]],
						["text", "FLTdir_edit", theUILang.Base_directory],
						["checkbox", "FLTnot_add_path", theUILang.Dont_add_tname],
						["checkbox", "FLTtorrents_start_stopped", theUILang.Dnt_start_down_auto],
						["select", "FLT_interval", theUILang.rssMinInterval, [
							[-1, theUILang.rssIntervalAlways],
							[0, theUILang.rssIntervalOnce],
							[12, theUILang.rssInterval12h],
							[24, theUILang.rssInterval1d],
							[48, theUILang.rssInterval2d],
							[72, theUILang.rssInterval3d],
							[96, theUILang.rssInterval4d],
							[144, theUILang.rssInterval6d],
							[168, theUILang.rssInterval1w],
							[336, theUILang.rssInterval2w],
							[504, theUILang.rssInterval3w],
							[720, theUILang.rssInterval1m],
						]],
					].flatMap(([type, id, text, options]) => {
						switch (type) {
							case "text": {
								return [
									$("<div>").addClass("col-12 col-md-3").append(
										$("<label>").attr({for:id}).text(text),
									),
									$("<div>").addClass("col-12 col-md-9").append(
										$("<input>").attr({id:id, type:"text"})
									),
								];
							};
							case "checkbox": {
								return [
									$("<div>").addClass("col-12 col-md-9 offset-md-3").append(
										$("<input>").attr({type:"checkbox", id:id}),
										$("<label>").attr({for:id}).text(text),
									),
								];
							}
							case "select": {
								return [
									$("<div>").addClass("col-12 col-md-3").append(
										$("<label>").attr({for:id}).text(text),
									),
									$("<div>").addClass("col-12 col-md-9").append(
										$("<select>").attr({id:id}).append(
											...options.map(([value, text]) => $("<option>").val(value).text(text)),
										),
									),
								];
							}
							default: {
								return;
							}
						}
					}),
					$("<div>").addClass("col-12 col-md-3").append(
						$("<label>").attr({for:"FLT_label"}).text(theUILang.Label),
					),
					$("<div>").addClass("col-12 col-md-9").append(
						$("<input>").attr({id:"FLT_label", type:"text"}),
					),
				)
			),
		),
	);
	const dlgEditFiltersButtons = $("<div>").attr({id:"FLT_buttons"}).addClass("buttons-list").append(
		$("<button>").attr({type:"button"}).on("click", () => {theWebUI.rssClearFilter(); return false;}).text(theUILang.rssClearFilter),
		$("<button>").attr({type:"button"}).addClass("OK").on("click", () => {theDialogManager.hide('dlgEditFilters'); theWebUI.setFilters(); return false;}).text(theUILang.ok),
		$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
	);
	theDialogManager.make("dlgEditFilters", theUILang.rssMenuManager,
		[dlgEditFiltersContent, dlgEditFiltersButtons],
	);
	$("#gcont").append( $("<div>").attr("id","rsslayout").css( "display", "none" ));

	if (thePlugins.isInstalled("_getdir")) {
		new theWebUI.rDirBrowser("RSSdir_edit");
		new theWebUI.rDirBrowser("FLTdir_edit");
	}

	thePlugins.isInstalled('throttle') && this.correctFilterDialog();
	thePlugins.isInstalled('ratio') && this.correctRatioFilterDialog();
	theDialogManager.center("dlgEditFilters");
};

plugin.onRemove = function()
{
        if(theWebUI.updateRSSTimer)
	        clearTimeout(theWebUI.updateRSSTimer);
	theWebUI.updateRSSTimer = 0;
        if(theWebUI.rsstimerUpdateIntervalId)
	        clearInterval(theWebUI.rsstimerUpdateIntervalId);
	theWebUI.rsstimerUpdateIntervalId = 0;
	theWebUI.switchLayout(false);
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
