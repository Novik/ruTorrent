/*
 *      Main object.
 *
 */

var theWebUI = {
	version: "5.2.0",
	tables: {
		trt: {
			obj: new dxSTable(),
			columns: [
				{ text: theUILang.Name, 		width: "200px", id: "name",		type: TYPE_STRING },
				{ text: theUILang.Status, 		width: "100px",	id: "status",		type: TYPE_STRING },
				{ text: theUILang.Size, 		width: "70px",	id: "size", 		type: TYPE_NUMBER },
				{ text: theUILang.Done, 		width: "100px",	id: "done",		type: TYPE_PROGRESS },
				{ text: theUILang.Downloaded, 		width: "100px",	id: "downloaded",	type: TYPE_NUMBER },
				{ text: theUILang.Uploaded, 		width: "100px",	id: "uploaded",		type: TYPE_NUMBER },
				{ text: theUILang.Ratio, 		width: "60px",	id: "ratio",		type: TYPE_NUMBER },
				{ text: theUILang.DL, 			width: "70px", 	id: "dl",		type: TYPE_NUMBER },
				{ text: theUILang.UL, 			width: "70px", 	id: "ul",		type: TYPE_NUMBER },
				{ text: theUILang.ETA, 			width: "60px", 	id: "eta",		type: TYPE_NUMBER },
				{ text: theUILang.Label, 		width: "60px", 	id: "label",		type: TYPE_STRING },
				{ text: theUILang.Peers, 		width: "60px", 	id: "peers",		type: TYPE_PEERS },
				{ text: theUILang.Seeds, 		width: "60px", 	id: "seeds",		type: TYPE_SEEDS },
				{ text: theUILang.Priority, 		width: "80px", 	id: "priority",		type: TYPE_NUMBER },
				{ text: theUILang.Created_on,		width: "110px", id: "created",		type: TYPE_NUMBER },
				{ text: theUILang.Remaining, 		width: "90px", 	id: "remaining",	type: TYPE_NUMBER },
				{ text: theUILang.Save_path,		width: "200px", id: "save_path",	type: TYPE_STRING }
			],
			container:	"List",
			format:		theFormatter.torrents,
			ondelete:	function() { theWebUI.removeTorrent(); },
			onselect:	function(e,id) { theWebUI.trtSelect(e,id) },
			ondblclick:	function(obj) { theWebUI.showDetails(obj.id); return false; },
		},
		fls: {
			obj: new dxSTable(),
			columns: [
				{ text: theUILang.Name, 		width: "200px",	id: "name",		type: TYPE_STRING },
				{ text: theUILang.Size, 		width: "70px", 	id: "size",		type: TYPE_NUMBER,	"align" : ALIGN_RIGHT},
				{ text: theUILang.Done, 		width: "100px", id: "done",		type: TYPE_NUMBER },
				{ text: "%", 				width: "100px",	id: "percent",		type: TYPE_PROGRESS },
				{ text: theUILang.Priority, 		width: "80px", 	id: "priority",		type: TYPE_NUMBER }
			],
			container:	"FileList",
			format:		theFormatter.files,
			onselect:	function(e,id) { theWebUI.flsSelect(e,id) },
			ondblclick:	function(obj) {
				if (!theWebUI.settings["webui.fls.view"] && (theWebUI.dID!="")) {
					var lnk = this.getAttr(obj.id, "link");
					if(lnk!=null) {
						theWebUI.dirs[theWebUI.dID].setDirectory(lnk);
						this.clearRows();
						theWebUI.redrawFiles(theWebUI.dID);
					}
				}
				return false;
			}
		},
		trk: {
			obj: new dxSTable(),
			columns: [
				{ text: theUILang.Name,			width: "200px", id: "name",		type: TYPE_STRING },
				{ text: theUILang.Type, 		width: "60px", 	id: "type",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT},
				{ text: theUILang.Enabled, 		width: "60px", 	id: "enabled",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT},
				{ text: theUILang.Group, 		width: "60px", 	id: "group",		type: TYPE_NUMBER },
				{ text: theUILang.Seeds, 		width: "60px", 	id: "seeds",		type: TYPE_NUMBER },
				{ text: theUILang.Peers, 		width: "60px", 	id: "peers",		type: TYPE_NUMBER },
				{ text: theUILang.scrapeDownloaded,	width: "80px", 	id: "downloaded",	type: TYPE_NUMBER },
				{ text: theUILang.scrapeUpdate,		width: "85px", 	id: "last",		type: TYPE_NUMBER },
				{ text: theUILang.trkInterval,		width: "80px", 	id: "interval",		type: TYPE_NUMBER },
				{ text: theUILang.trkPrivate, 		width: "60px", 	id: "private",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT}
			],
			container:	"TrackerList",
			format:		theFormatter.trackers,
			onselect:	function(e,id) { theWebUI.trkSelect(e,id) }
		},
		prs: {
			obj: new dxSTable(),
			columns: [
				{ text: theUILang.Address,		width: "100px", id: "name",		type: TYPE_STRING },
				{ text: theUILang.ClientVersion,	width: "120px", id: "version",		type: TYPE_STRING },
				{ text: theUILang.Flags, 		width: "60px", 	id: "flags",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT},
				{ text: theUILang.Done, 		width: "100px", id: "done",		type: TYPE_PROGRESS },
				{ text: theUILang.Downloaded, 		width: "100px", id: "downloaded",	type: TYPE_NUMBER },
				{ text: theUILang.Uploaded, 		width: "100px", id: "uploaded",		type: TYPE_NUMBER },
				{ text: theUILang.DL, 			width: "70px", 	id: "dl",		type: TYPE_NUMBER },
				{ text: theUILang.UL, 			width: "70px", 	id: "ul",		type: TYPE_NUMBER },
				{ text: theUILang.PeerDL, 		width: "70px", 	id: "peerdl",		type: TYPE_NUMBER },
				{ text: theUILang.PeerDownloaded, 	width: "100px", id: "peerdownloaded",	type: TYPE_NUMBER }
			],
			container:	"PeerList",
			format:		theFormatter.peers,
			onselect:	function(e,id) { theWebUI.prsSelect(e,id) },
			ondblclick:	function(obj) {
				const queryUrl = theWebUI.getPeerIpQueryUrl(obj.id);
				if (queryUrl !== '#')
					window.open(queryUrl, "_blank");
				return false;
			}
		},
		plg: {
			obj: new dxSTable(),
			columns: [
				{ text: theUILang.plgName,		width: "150px", id: "name",		type: TYPE_STRING },
				{ text: theUILang.plgVersion,		width: "60px",	id: "version",		type: TYPE_NUMBER },
				{ text: theUILang.plgStatus, 		width: "80px", 	id: "status",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT},
				{ text: theUILang.plgLaunch,		width: "80px", 	id: "launch",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT},
				{ text: theUILang.plgAuthor,		width: "80px", 	id: "author",		type: TYPE_STRING },
				{ text: theUILang.plgDescr,		width: "500px",	id: "descr",		type: TYPE_STRING }
			],
			container:	"PluginList",
			format:		theFormatter.plugins,
			onselect:	function(e,id) { theWebUI.plgSelect(e,id) },
		}
	},
	settings: {
		"webui.fls.view":		0,
		"webui.show_cats":		1,
		"webui.show_dets":		1,
		"webui.needmessage":		1,
		"webui.reqtimeout":		10000,
		"webui.confirm_when_deleting":	1,
		"webui.alternate_color":	0,
		"webui.side_panel_min_width": 	200,
		"webui.side_panel_max_width_percent": 35,
		"webui.list_table_min_height": 	300,
		"webui.update_interval":	2500,
		"webui.hsplit":			0.88,
		"webui.vsplit":			0.5,
		"webui.effects":		0,
		"webui.fullrows":		0,
		"webui.no_delaying_draw":	1,
		"webui.search":			-1,
		"webui.speedlistdl":		"128,512,1024,2048,3072,4096,5120,6144,7168,8192,9216,10240",
		"webui.speedlistul":		"128,512,1024,2048,3072,4096,5120,6144,7168,8192,9216,10240",
		"webui.ignore_timeouts":	0,
		"webui.retry_on_error":		120,
		"webui.category_panels":	['pview', 'pstate', 'plabel', 'flabel', 'ptrackers', 'prss'],
		"webui.closed_panels":		{},
		"webui.open_tegs.last": [],
		"webui.open_tegs.keep": 0,
		"webui.selected_labels.last": {},
		"webui.selected_labels.keep": 0,
		"webui.selected_labels.views": [],
		"webui.selected_tab.last": {},
		"webui.selected_tab.keep": 0,
		"webui.timeformat":		0,
		"webui.dateformat":		0,
		"webui.speedintitle":		0,
		"webui.speedgraph.max_seconds": 600,
		"webui.log_autoswitch":		1,
		"webui.labelsize_rightalign":		0,
		"webui.show_labelsize":		1,
		"webui.show_searchlabelsize":	0,
		"webui.show_statelabelsize":	0,
		"webui.show_viewlabelsize":		1,
		"webui.show_label_path_tree":	1,
		"webui.show_empty_path_labels":	0,
		"webui.show_label_text_overflow": 0,
		"webui.show_open_status":	1,
		"webui.show_view_panel": 1,
		"webui.register_magnet":	0,
		"webui.not_add_path":		0,
		"webui.torrents_start_stopped":	0,
		"webui.fast_resume":		0,
		"webui.randomize_hash":	0,
		...(() => {
			const defaults = {};
			const units = ['default', 'kb', 'mb', 'gb', 'tb', 'pb'];
			for(const [context, udef] of Object.entries({
				catlist: [2, 0,1,1], table: [2], details: [2], other: [1]
			})) {
				for (let u = 0; u < units.length; u++) {
					defaults['webui.size_decimal_places.' + context + '.' + units[u]] = udef[u] == null ? '' : udef[u].toString();
				}
			}
			return defaults;
		})(),
	},
	showFlags: 0,
	total: {
		rateDL: 	0,
		rateUL: 	0,
		speedDL: 	0,
		speedUL: 	0,
		DL: 		0,
		UL: 		0
	},
	stopen: {
		http: 	-1,
		sock: 	-1,
		fd: 	-1,
	},
	sTimer: 	null,
	updTimer: 	null,
	configured:	false,
	jsonLoaded:	false,
	pluginsLoaded: false,
	firstLoad:	true,
	interval:	-1,
	torrents:	{},
	files:		{},
	dirs:		{},
	trackers:	{},
	props:		{},
	peers:		{},
	dID:		"",
	pID:		"",
	speedGraph:	new rSpeedGraph(),
	url:		window.location.href.substr(0,window.location.href.lastIndexOf("/")+1),
	timer:		new Timer(),
	activeView:	null,
	delmode:	"remove",
	deltaTime:	0,
	serverDeltaTime:0,
	taskAddTorrents: null,
	categoryList: null,
	taskAddTorrentsAnimateHandleId: 0,

//
// init
//

	init: function() {
		log("WebUI started.");
		this.setStatusUpdate();
		if (browser.isOldIE)
			this.msg(theUILang.Doesnt_support);
		else {
			this.catchErrors(false);
			this.getUISettings();
			this.getPlugins();
		}
	},

	assignEvents: function() {
		window.addEventListener("resize", theWebUI.resize);
		window.addEventListener("orientationchange", theWebUI.resize);
		$(document).on("dragstart", function(e) { return false; } );
		$(document).on("selectstart", function(e) { return(e.fromTextCtrl); });
		$(document).on("contextmenu", function(e) {
			if (e.fromTextCtrl)
				theContextMenu.hide();
			else
				return false;
		});
		const keyEvent = function (e) {
			switch (e.which) {
				case 27: // Esc
				{
					if (theContextMenu.hide() || theDialogManager.hideTopmost())
						return(false);
					if ($$("query").value === "") {
						theWebUI.endSearch();
					} else {
						theWebUI.clearSearch();
					}
					break;
				}
				case 79: // ^O
   			{
					if (e.metaKey && !theDialogManager.isModalState()) {
						theWebUI.showAdd();
						return false;
					}
		   		break;
				}
				case 80: // ^P
				{
					if (e.metaKey && !theDialogManager.isModalState()) {
						theWebUI.showSettings();
						return false;
					}
					break;
				}
				case 70: // ^F
				{
					if (e.metaKey && !theDialogManager.isModalState()) {
						theWebUI.startSearch(e);
					}
					break;
				}
				case 112: // F1
				{
					if ((!browser.isOpera || !e.fromTextCtrl) && !theDialogManager.isModalState()) {
						theDialogManager.show(e.metaKey ? "dlgAbout" : "dlgHelp");
						return false;
					}
					break;
				}
				case 115: // F4
				{
					if (!browser.isOpera || !e.fromTextCtrl) {
						theWebUI.toggleMenu();
						return false;
					}
					break;
				}
				case 117: // F6
				{
					if (!browser.isOpera || !e.fromTextCtrl) {
						theWebUI.toggleDetails();
						return false;
					}
					break;
				}
				case 118: // F7
				{
					if (!browser.isOpera || !e.fromTextCtrl) {
						theWebUI.toggleCategories();
						return(false);
					}
					break;
				}
			}
		};
		$("#query").on("keydown", (e) => {
			if (e.keyCode === 13) {
				theSearchEngines.run()
			}
		});
		$("#clear_log").on("click", () => $("#clear_log").siblings().remove());

		$(document).on( browser.isOpera ? 'keypress' : 'keydown', keyEvent);
	},

	updateServerTime: function()
	{
		$('#servertime').text(theConverter.date( (new Date().getTime()-theWebUI.serverDeltaTime)/1000, true ));
	},

	getPlugins: function()
	{
		this.requestWithoutTimeout("?action=getplugins", [this.loadPlugins, this]);
	},

	getUISettings: function()
	{
		this.requestWithoutTimeout("?action=getuisettings", [this.initSettings, this], true);
	},

	loadPlugins: function() {
		if (thePlugins.isInstalled("_getdir")) {
			new this.rDirBrowser("dir_edit");
		}
		correctContent();
		this.updateServerTime();
		window.setInterval( this.updateServerTime, 1000 );
		
		// Mark plugins as done loading. Initialize UI if JSON file is loaded
		this.pluginsLoaded = true;
		this.initFinish();
	},

	initFinish: function()
	{
		// Loading JSON settings and plugins are done in an asynchronous fashion
		// We must wait until both of these are completed before preceding
		// Otherwise, the WebUI and plugins will not initialize properly
		if (this.jsonLoaded && this.pluginsLoaded)
		{
			this.config();
			this.catchErrors(true);
			this.assignEvents();
			this.resize();
			this.update();
		}
	},
	
	createSpeedGraph: function()
	{
		const speedTab = $("#Speed");
		if (speedTab.length)
			this.speedGraph.create(speedTab);
	},

	config: function() {
		$.each(this.tables, function(ndx,table) {
			var width = theWebUI.settings["webui."+ndx+".colwidth"];
			var enabled = theWebUI.settings["webui."+ndx+".colenabled"];
			$.each(table.columns, function(i,col) {
				if(width && i<width.length && width[i]>4)
					col.width = width[i];
				if(enabled && i<enabled.length)
					col.enabled = enabled[i];
			});
			table.obj.format = table.format;
			table.obj.onresize = theWebUI.save;
			table.obj.onmove = theWebUI.save;
			table.obj.ondblclick = table.ondblclick;
			table.obj.onselect = table.onselect;
			table.obj.ondelete = table.ondelete;
			table.obj.maxRows = iv(theWebUI.settings["webui.fullrows"]);
			table.obj.noDelayingDraw = iv(theWebUI.settings["webui.no_delaying_draw"]);
			if($type(theWebUI.settings["webui."+ndx+".sindex"]))
				table.obj.sortId = theWebUI.settings["webui."+ndx+".sindex"];
			if($type(theWebUI.settings["webui."+ndx+".rev"]))
				table.obj.reverse = iv(theWebUI.settings["webui."+ndx+".rev"]);
			if($type(theWebUI.settings["webui."+ndx+".sindex2"]))
				table.obj.sortId2 = theWebUI.settings["webui."+ndx+".sindex2"];
			if($type(theWebUI.settings["webui."+ndx+".rev2"]))
				table.obj.secRev = iv(theWebUI.settings["webui."+ndx+".rev2"]);
			if($type(theWebUI.settings["webui."+ndx+".colorder"]))
				table.obj.colOrder = theWebUI.settings["webui."+ndx+".colorder"];
			table.obj.onsort = function() {
				if (
					(this.sortId != theWebUI.settings["webui."+this.prefix+".sindex"]) ||
					(this.reverse != theWebUI.settings["webui."+this.prefix+".rev"]) ||
					(this.sortId2 != theWebUI.settings["webui."+this.prefix+".sindex2"]) ||
					(this.secRev != theWebUI.settings["webui."+this.prefix+".rev2"])
				)
					theWebUI.save();
			}
		});
		var table = this.getTable("fls");
		table.oldGetSortFunc = table.getSortFunc;
		table.getSortFunc = function(id, reverse, valMapping)
		{
			const oldSorter = this.oldGetSortFunc(id, reverse, valMapping);
			const dID = theWebUI.dID;
			let sorter = oldSorter;
			if(!theWebUI.settings["webui.fls.view"] && dID && this.sortId === id)
			{
				const dir = theWebUI.dirs[dID];
				if (dir && dir.dirs) {
					// sort dir and links to top
					const curDir = dir.dirs[dir.current];
					const fixedDirPos = (a,b) => (a.data.name=="..") || ((a.link!=null) && (b.link==null))
					const dirSort = (x,y) => {
						const [a,b] = [curDir[x], curDir[y]]
						return fixedDirPos(a,b) ? -1 : (fixedDirPos(b,a) ? 1 : 0);
					};
					sorter = (x,y) => dirSort(x,y) || oldSorter(x,y)
				}
			}
			return sorter;
		}
		this.createSpeedGraph();
		const tab = theWebUI.settings['webui.selected_tab.keep'] ?
					theWebUI.settings['webui.selected_tab.last'] : 'lcont';
		theTabs.show(tab);
		this.activeView = tab;

		if (!this.settings["webui.show_cats"]) {
			$("#offcanvas-sidepanel, #HDivider").hide();
		} else {
			$("#offcanvas-sidepanel, #HDivider").show();
		}
		if (!this.settings["webui.show_dets"] || !theWebUI.systemInfo.rTorrent.started) {
			$("#tdetails, #VDivider").hide();
		} else {
			$("#tdetails, #VDivider").show();
		}
		theDialogManager.setEffects( iv(this.settings["webui.effects"])*200 );
//		this.setStatusUpdate();
		$.each(this.tables, function(ndx,table)
		{
			table.obj.create($$(table.container), table.columns, ndx);
			// legacy support of numeric sortId, sortId2
			for(const name of ['sortId', 'sortId2']) {
				const col = Number.parseInt(table.obj[name]);
				if(Number.isInteger(col)) {
					table.obj[name] = table.obj.getIdByCol(col);
				}
			}
		});
		$(".stable").toggleClass("alternate", !!theWebUI.settings["webui.alternate_color"]);
		table = this.getTable("plg");
		if(table)
		{
			$.each( thePlugins.list, function(ndx,plugin)
			{
				table.addRowById(
				{
					name: plugin.name,
					version: plugin.version,
					author: plugin.author,
					descr: plugin.descr,
					status: plugin.enabled ? 1 : 0,
					launch: plugin.launched ? (plugin.canBeLaunched() ? 1 : 2) : 0
				}, "_plg_"+plugin.name);
			});
		}
		if(!theWebUI.systemInfo.rTorrent.started)
			$(theWebUI.getTable("trt").scp).text(theUILang.noTorrentList).show();

		theWebUI.categoryList.config(this.settings);
		// Setup quick search
		const searchField = $$("query");
		searchField.addEventListener("focus", theWebUI.updateQuickSearch);
		searchField.addEventListener("input", theWebUI.updateQuickSearch);
		theWebUI.updateQuickSearch();

		// user must be able add peer when peers are empty
		$("#PeerList .stable-body").mouseclick(function(e)
		{
			if(e.which==3 && $(e.target).hasClass('stable-body'))
			{
				theWebUI.prsSelect(e,null);
				return(true);
			}
			return(false);
		});

		this.registerMagnetHandler();
		this.configured = true;
	},

	registerMagnetHandler: function()
	{
		if(typeof navigator.registerProtocolHandler == 'function')
		{
			var url = window.location.href.substr(0,window.location.href.lastIndexOf("/")) + "/php/addtorrent.php?url=%s";
			if( ((typeof navigator.isProtocolHandlerRegistered != 'function') ||
				!navigator.isProtocolHandlerRegistered('magnet', url)) &&
				theWebUI.settings["webui.register_magnet"])
			{
				navigator.registerProtocolHandler("magnet", url, "RuTorrent");
			}
		}
		else
		{
			$($$('webui.register_magnet')).attr('disabled',true);
			$($$('lbl_webui.register_magnet')).addClass('disabled');
		}
	},

	setStatusUpdate: function()
	{
		if(this.sTimer)
		{
			window.clearInterval(this.sTimer);
			this.sTimer = null;
		}
		this.sTimer = window.setInterval(this.updateStatus, 1000);
	},

//
// plugins
//

	plgSelect: function(e, id)
	{
		if($type(id) && (e.which==3))
		{
		        theContextMenu.clear();
		        if(this.getTable("plg").selCount > 1)
		        {
				theContextMenu.add([theUILang.plgShutdown, "theWebUI.plgShutdown()"]);
				theContextMenu.add([CMENU_CHILD, theUILang.plgLaunch,
					[
						[theUILang.EnableTracker, "theWebUI.plgLaunch(true)"],
						[theUILang.DisableTracker, "theWebUI.plgLaunch(false)"]
					]]);
			}
			else
			{
				var plugin = thePlugins.get(id.substr(5));
				theContextMenu.add([theUILang.plgShutdown, (plugin.enabled && plugin.canShutdown()) ? "theWebUI.plgShutdown()" : null]);
				theContextMenu.add([CMENU_CHILD, theUILang.plgLaunch,
					[
						[theUILang.EnableTracker, !plugin.launched && plugin.canBeLaunched() ? "theWebUI.plgLaunch(true)" : null],
						[theUILang.DisableTracker, plugin.launched && plugin.canBeLaunched() ? "theWebUI.plgLaunch(false)" : null]
					]]);
				if(plugin.help)
				{
					theContextMenu.add([CMENU_SEP]);
					theContextMenu.add([theUILang.Help, "window.open('"+plugin.help+"', '_blank')" ]);
				}
			}
	   		theContextMenu.show();
			return(true);
		}
		return(false);
   	},

	plgShutdown : function()
	{
		var table = this.getTable("plg");
   		var sr = table.rowSel;
   		var str = "";
   		for(var k in sr)
   		{
      			if(sr[k])
      			{
      				var name = k.substr(5);
	      			var plugin = thePlugins.get(name);
      			        if(plugin.enabled && plugin.canShutdown())
            				str += "&hash=" + name;
         		}
      		}
		if(str.length>0)
	      		this.request("?action=doneplugins&s=done" + str, [this.plgRefresh, this]);
        },

	plgLaunch : function(enable)
	{
		var table = this.getTable("plg");
   		var sr = table.rowSel;
   		var str = "";
   		for(var k in sr)
   		{
      			if(sr[k])
      			{
      				var name = k.substr(5);
	      			var plugin = thePlugins.get(name);
      			        if( (enable ^ plugin.launched) && plugin.canBeLaunched())
            				str += "&hash=" + name;
         		}
      		}
		if(str.length>0)
	      		this.request("?action=doneplugins&s="+(enable ? "launch" : "unlaunch") + str, [this.plgRefresh, this]);
        },

        plgRefresh : function()
        {
        	table = this.getTable("plg");
		$.each( thePlugins.list, function(ndx,plugin)
		{
			table.setValueById( "_plg_"+plugin.name, "status", plugin.enabled ? 1 : 0 );
			table.setValueById( "_plg_"+plugin.name, "launch", plugin.launched ? (plugin.canBeLaunched() ? 1 : 2) : 0 );
		});
        },

//
// settings
//

	initSettings: function(newSettings)
	{
		// Add webui settings for the first time
		this.addSettings(newSettings);
		
		// Mark JSON file as loaded. Initialize UI if plugins are loaded
		this.jsonLoaded = true;
		this.initFinish();
	},
	
	addSettings: function(newSettings)
	{
		$.each(newSettings, function(i,v)
		{
			switch(v)
			{
				case "true":
				case "auto":
				case "on":
				{
					newSettings[i] = 1;
					break;
				}
				case "false":
				{
					newSettings[i] = 0;
					break;
				}
         		}
		});
		newSettings["webui.lang"] = GetActiveLanguage();
		$.extend(this.settings,newSettings);
   		this.loadSettings();
   	},

	loadSettings: function()
	{
		$.each(this.settings, function(i,v)
		{
		        var o = $$(i);
			if(o)
			{
				o = $(o);
				if(o.is("input:checkbox"))
					o.prop('checked',(v!=0));
				else
				{
					switch(i)
					{
					        case "max_memory_usage":
              						v /= 1024;
						case "upload_rate":
						case "download_rate":
              						v /= 1024;
	          					v = Math.ceil(v);
					}
					o.val(v);
				}
				o.trigger('change');
			}
		});
		if($type(this.settings["webui.search"]))
			theSearchEngines.set(this.settings["webui.search"],true);
   	},

	setSettings: function() {
		var req = '';
		var needSave = false;
		var needResize = false;
		let needCatListSync = false;
		var reply = null;
		$.each(this.settings, function(i,v) {
			var o = $$(i);
			if (o) {
				o = $(o);
				var nv = o.is("input:checkbox") ? (o.prop('checked') ? 1 : 0) : o.val();
				switch(i) {
					case "max_memory_usage":
						nv *= 1024;
					case "upload_rate":
					case "download_rate":
						nv *= 1024;
				}
				if(nv!=v)
				{
					theWebUI.settings[i] = nv;
					if((/^webui\./).test(i))
					{
						needSave = true;
						switch(i) {
						        case "webui.effects":
						        {
								theDialogManager.setEffects( iv(nv)*200 );
								break;
							}
							case "webui.alternate_color": {
								$(".stable").toggleClass("alternate", !!nv);
								break;
							}
							case "webui.show_cats": {
								$("#offcanvas-sidepanel").toggle();
								needResize = true;
								break;
							}
							case "webui.show_dets":
							{
								$("#tdetails").toggle();
								needResize = true;
								break;
							}
							case "webui.lang":
							{
								SetActiveLanguage(nv);
								reply = theWebUI.reload;
								break;
							}
							case "webui.register_magnet":
							{
								reply = theWebUI.reload;
								break;
							}
							case "webui.fullrows":
							{
								$.each(theWebUI.tables, function(ndx,table)
								{
						      			table.obj.maxRows = iv(nv);
						      			table.obj.refreshRows();
								});
								break;
							}
							case "webui.no_delaying_draw":
							{
								$.each(theWebUI.tables, function(ndx,table)
								{
						      			table.obj.noDelayingDraw = iv(nv);
								});
								break;
							}
							case "webui.update_interval":
							{
								if(theWebUI.systemInfo.rTorrent.started)
									theWebUI.resetInterval();
								break;
							}
							case "webui.speedgraph.max_seconds":
							{
								theWebUI.speedGraph.setMaxSeconds(parseInt(theWebUI.settings['webui.speedgraph.max_seconds']))
								theWebUI.speedGraph.draw();
								break;
							}
							case "webui.show_viewlabelsize":
							case "webui.show_statelabelsize":
							case "webui.show_labelsize":
							case "webui.show_searchlabelsize":
							case "webui.labelsize_rightalign":
							case "webui.show_label_text_overflow":
							case "webui.show_view_panel":
							{
								needCatListSync = true;
								break;
							}
						}
					}
					else
					{
						var k_type = o.is("input:checkbox") || o.is("select") || o.hasClass("num") ? "n" : "s";
						req+=("&s="+k_type+i+"&v="+nv);
					}
				}
			}
		});
		if (needCatListSync)
		{
			const c = this.categoryList;
			c.refresh(...c.statistic.panelIds);
			c.syncFn();
		}
		if(needResize)
			this.resize();
		if((req.length>0) && theWebUI.systemInfo.rTorrent.started)
			this.request("?action=setsettings" + req,null,true);
		if(needSave)
			this.save(reply);
	},

   	reload: function()
   	{
		window.location.reload();
   	},

   	showSettings: function()
	{
		if($("#stg").is(":visible"))
			theDialogManager.hide("stg");
		else
		{
			if(this.systemInfo.rTorrent.started)
		   		this.request("?action=getsettings", [this.addAndShowSettings, this], true);
			else
				this.addAndShowSettings();
		}
   	},

	addAndShowSettings: function(data)
	{
	        if(data)
			this.addSettings(data);
		theDialogManager.show("stg");
	},

        save: function(reply)
	{
	        if(!theWebUI.configured)
			return;
	        $.each(theWebUI.tables, function(ndx,table)
		{
	   		var width = [];
	   		var enabled = [];
	   		for(i = 0; i < table.obj.cols; i++)
   			{
      				width.push( table.obj.getColWidth(i) );
	      			enabled.push( table.obj.isColumnEnabled(i) );
			}
			theWebUI.settings["webui."+ndx+".colwidth"] = width;
			theWebUI.settings["webui."+ndx+".colenabled"] = enabled;
			theWebUI.settings["webui."+ndx+".colorder"] = table.obj.colOrder;
			theWebUI.settings["webui."+ndx+".sindex"] = table.obj.sortId;
			theWebUI.settings["webui."+ndx+".rev"] = table.obj.reverse;
			theWebUI.settings["webui."+ndx+".sindex2"] = table.obj.sortId2;
			theWebUI.settings["webui."+ndx+".rev2"] = table.obj.secRev;
		});

		theWebUI.settings['webui.selected_tab.last'] = theWebUI.activeView;
		var cookie = {};
		theWebUI.settings["webui.search"] = theSearchEngines.current;
		for(const [i,v] of Object.entries(theWebUI.settings))
		{
			if((/^webui\./).test(i))
				cookie[i] = v;
		}
		// We must encode the URL here to avoid injection with the "&" symbol from search results
		theWebUI.request("?action=setuisettings&v=" + encodeURIComponent(JSON.stringify(cookie),reply));
	},

//
// peers
//

	updatePeers: function(hash)
	{
		if (this.dID && this.dID == hash)
			this.getPeers(hash, true);
	},

	getPeers: function(hash, isUpdate)
	{
		if (!isUpdate)
		{
			const table = this.getTable("prs");
			table.clearRows();
			table.updateRows(this.peers[hash] || {});
		}
		this.request("?action=getpeers&hash=" + hash, [this.addPeers, this, hash]);
	},

	addPeers: function(data, hash)
	{
		const table = this.getTable("prs");
		for (const peer of Object.values(data))
		{
			peer.name = peer.name+':'+peer.port;
			peer.attr =
			{
				...peer.attr,
				ip: peer.ip,
				snubbed: peer.snubbed ? 'on' : 'off'
			};
		}
		this.peers[hash] = data;
		if (this.dID == hash)
			table.updateRows(this.peers[hash]);
		else
			table.clearRows();
	},

	prsSelect: function(e, id)
	{
		if(theWebUI.createPeerMenu(e, id))
	   		theContextMenu.show();
   	},

	getPeerIds: function(cmd)
	{
		const table = this.getTable("prs");
		const selected = table.getSelected();
		const peerIds = ['kick', 'ban'].includes(cmd) ? selected : selected
			.filter((row) => cmd === (table.getAttr(row, 'snubbed') == 'on' ? 'unsnub' : 'snub'));
		return peerIds.map((sId) => `&f=${sId}`).join('');
   	},

	getPeerIpQueryUrl: function(peerId)
	{
		const ip = this.getTable("prs").getAttr(peerId, 'ip');
		return ip ? (theURLs.IPQUERYURL + ip.replace(/^\[?(.+?)\]?$/, '$1')) : '#';
	},

	addNewPeer: function()
	{
		this.request("?action=addpeer&hash="+this.dID+"&f="+encodeURIComponent($("#peerIP").val()), [this.updatePeers,this]);
	},

	setPeerState: function(cmd)
	{
   		var prs = this.getPeerIds(cmd);
		if(prs.length)
	   		this.request("?action="+cmd+"&hash="+this.dID+prs, [this.updatePeers,this]);
	},

   	createPeerMenu : function(e, id)
	{
   		var ret = false;
   		if(e.which == 3)
		{
	   		theContextMenu.clear();
			const table = theWebUI.getTable("prs");
			const selCount = table.selCount;
	   		if(this.dID && $type(this.torrents[this.dID]))
   			{
				theContextMenu.add([theUILang.peerAdd,
					(this.torrents[this.dID].private==0) &&
					this.isTorrentCommandEnabled('addpeer',this.dID) &&
					(theWebUI.systemInfo.rTorrent.iVersion>=0x804) ?
					"theDialogManager.show('padd')"	: null]);
				ret = true;
				if(selCount && theWebUI.systemInfo.rTorrent.iVersion>=0x807)
				{
					theContextMenu.add([theUILang.peerBan, this.isTorrentCommandEnabled('ban',this.dID) ? "theWebUI.setPeerState('ban')" : null]);
					theContextMenu.add([theUILang.peerKick, this.isTorrentCommandEnabled('kick',this.dID) ? "theWebUI.setPeerState('kick')" : null]);
		   			if(selCount > 1)
	   				{
						theContextMenu.add([theUILang.peerSnub, this.isTorrentCommandEnabled('snub',this.dID) ? "theWebUI.setPeerState('snub')" : null]);
						theContextMenu.add([theUILang.peerUnsnub, this.isTorrentCommandEnabled('unsnub',this.dID) ? "theWebUI.setPeerState('unsnub')" : null]);
					}
					else if (id in table.rowdata)
	                {
					if(table.getAttr(id, 'snubbed') != 'on')
      						theContextMenu.add([theUILang.peerSnub, this.isTorrentCommandEnabled('snub',this.dID) ? "theWebUI.setPeerState('snub')" : null]);
						else
							theContextMenu.add([theUILang.peerUnsnub, this.isTorrentCommandEnabled('unsnub',this.dID) ? "theWebUI.setPeerState('unsnub')" : null]);
	      			}
		        	theContextMenu.add([CMENU_SEP]);
				}
			}
			if(selCount === 1)
			{
				$(theContextMenu.obj)
					.append(
						$('<li>')
						.addClass('menuitem')
						.append(
							$('<a>')
							.addClass(['menu-cmd'])
							.attr({
								'rel': 'noreferrer noopener',
								'target': '_blank',
								'href': this.getPeerIpQueryUrl(id)})
							.text(theUILang.peerDetails)));
			}
		}
		return(ret);
   	},

//
// trackers
//

	trkIsPrivate: function(url)
	{
		return(
			(/(http|https|udp):\/\/(?:[0-9]{1,3}\.){3}[0-9]{1,3}((:(\d){2,5})|).*(\/a.*(\?.+=.+|\/.+)|\?.+=.+)/i).test(url) ||
			(/(http|https|udp):\/\/(?:[0-9]{1,3}\.){3}[0-9]{1,3}((:(\d){2,5})|)\/.*[0-9a-z]{8,32}\/a/i).test(url) ||
			(/(http|https|udp):\/\/[a-z0-9-\.]+\.[a-z]{2,253}((:(\d){2,5})|).*(\/a.*(\?.+=.+|\/.+)|\?.+=.+)/i).test(url) ||
			(/(http|https|udp):\/\/[a-z0-9-\.]+\.[a-z]{2,253}((:(\d){2,5})|)\/.*[0-9a-z]{8,32}\/a/i).test(url) ? 1 : 0 );
	},

   	trkSelect: function(e, id)
	{
		if($type(id))
		{
			var arr = id.split('_t_');
	   		var ind = iv(arr[1]);
   			if(theWebUI.createTrackerMenu(e, ind))
		   		theContextMenu.show();
		}
   	},

	updateTrackers: function(hash)
	{
		if (this.dID && this.dID == hash)
			this.getTrackers(hash, true);
	},

	getTrackers: function(hash, isUpdate)
	{
		if (!isUpdate) {
			const table = this.getTable('trk');
			table.clearRows();
			table.updateRows(this.trackers[hash] || {});
		}
		this.request("?action=gettrackers&hash=" + hash, [this.addTrackers, this]);
	},

	getAllTrackers: function(arr)
	{
		var req = "?action=getalltrackers";
		for(var i=0; i<arr.length; i++)
			req+=("&hash=" + arr[i]);
		if(arr.length)
	      		this.request(req, [this.addTrackers, this]);
   	},

	addTrackers: function(data)
	{
		const table = this.getTable("trk");
		for (const [hash, trackers] of Object.entries(data)) {
			for (const [i, trk] of Object.entries(trackers)) {
				trk.private = this.trkIsPrivate(trk.name);
				trk.last = iv(trk.last) ? $.now()/1000 - iv(trk.last) - theWebUI.deltaTime/1000 : -1;
				trk.attr = { ...trk.attr, id: hash + "_t_" + i };
			}
		}
		Object.assign(this.trackers, data);
		table.updateRows(this.trackers[this.dID] || {});
		this.updateDetails();
	},

   	createTrackerMenu : function(e, ind)
	{
   		if(e.which!=3)
      			return(false);
   		theContextMenu.clear();
   		if( this.dID && $type(this.trackers[this.dID]) )
   		{
	   		if(this.getTable("trk").selCount > 1)
   			{
      				theContextMenu.add([theUILang.EnableTracker, this.isTorrentCommandEnabled('trkstate',this.dID) ? "theWebUI.setTrackerState('" + this.dID + "',1)" : null]);
      				theContextMenu.add([theUILang.DisableTracker, this.isTorrentCommandEnabled('trkstate',this.dID) ? "theWebUI.setTrackerState('" + this.dID + "',0)" : null]);
	      		}
   			else
   			{
      				if(this.trackers[this.dID][ind].enabled == 0)
	      			{
      					theContextMenu.add([theUILang.EnableTracker, this.isTorrentCommandEnabled('trkstate',this.dID) ? "theWebUI.setTrackerState('" + this.dID + "',1)" : null]);
	      				theContextMenu.add([theUILang.DisableTracker]);
         			}
	      			else
      				{
	      				theContextMenu.add([theUILang.EnableTracker]);
      					theContextMenu.add([theUILang.DisableTracker, this.isTorrentCommandEnabled('trkstate',this.dID) ? "theWebUI.setTrackerState('" + this.dID + "',0)" : null]);
	         		}
      			}
			if(theWebUI.systemInfo.rTorrent.iVersion>=0x809)
			{
		   		theContextMenu.add([CMENU_SEP]);
				theContextMenu.add([theUILang.updateTracker, this.isTorrentCommandEnabled("updateTracker",this.dID) ? "theWebUI.updateTracker()" : null]);
			}
		}
		return(true);
   	},

	setTrackerState: function(id, swtch)
	{
   		var trk = this.getTrackerIds(id, swtch);
   		this.request("?action=settrackerstate&hash=" + id + "&p=" + swtch + trk);
   	},

	getTrackerIds: function(id, swtch)
	{
		var table = this.getTable("trk");
   		var sr = table.rowSel;
   		var str = "";
   		for(var k in sr)
   		{
      			if(sr[k])
      			{
				var arr = k.split('_t_');
         			var i = iv(arr[1]);
         			if(this.trackers[id][i].enabled != swtch)
         			{
            				str += "&f=" + i;
            				this.trackers[id][i].enabled = swtch;
            				table.setValueById(id + "_t_" + i, "enabled", swtch);
            			}
         		}
      		}
   		return(str);
   	},

//
// files
//

	updateFiles: function(hash)
	{
	        if(this.dID && this.dID == hash)
      			this.getFiles(hash, true);
   	},

	redrawFiles: function(hash)
	{
		if(this.dID == hash)
      		{
	      		var table = this.getTable("fls");
	      		for(var i in this.files[hash])
      			{
       				var sId = hash + "_f_" + i;
       				var file = this.files[hash][i];
       				file.percent = (file.size > 0) ? theConverter.round((file.done/file.size)*100,1): "100.0";
         			if(this.settings["webui.fls.view"])
         			{
					table.setRowById(file, sId, file.icon, file.attr);
				}
				else
				{
					if(!$type(this.dirs[hash]))
						this.dirs[hash] = new rDirectory();
					this.dirs[hash].addFile(file, i);
				}
			}
			if(!this.settings["webui.fls.view"] && this.dirs[hash])
			{
				var dir = this.dirs[hash].getDirectory();
				for(var i in dir)
				{
					var entry = dir[i];
					if(entry.link!=null)
						table.setRowById(entry.data, i, entry.icon, {link: entry.link});
				}
				for(var i in dir)
				{
					var entry = dir[i];
					if(entry.link==null)
						table.setRowById(entry.data, i, entry.icon, {link: undefined});
				}
			}
		}
	},

	getFiles: function(hash, isUpdate)
	{
		var table = this.getTable("fls");
		if(!isUpdate)
			table.clearRows();
   		if($type(this.files[hash]) && !isUpdate)
      			this.redrawFiles(hash);
   		else
   		{
      			if(!$type(this.files[hash]))
         			this.files[hash] = new Array(0);
      			this.request("?action=getfiles&hash=" + hash, [this.addFiles, this]);
      		}
   	},

	addFiles: function(data)
	{
		$.extend(this.files,data);
		for( var hash in data )
	      		this.redrawFiles(hash);
   	},

	flsSelect: function(e, id)
	{
		if($type(id))
		{
	   		var p = null;
	   		if(theWebUI.settings["webui.fls.view"])
			{
				var arr = id.split('_f_');
		   		p = theWebUI.files[theWebUI.dID][iv(arr[1])];
			}
			else
				p = theWebUI.dirs[theWebUI.dID].getEntry(id);
   			if(theWebUI.createFileMenu(e, p))
				theContextMenu.show();
		}
   	},

	createFileMenu: function(e, p)
	{
   		if(e.which!=3)
      			return(false);
   		var id = this.dID;
   		theContextMenu.clear();
		var _bf = [];
		var table = this.getTable("fls");
		if(table.selCount > 1)
   		{
      			_bf.push([theUILang.High_priority, "theWebUI.setPriority('" + id + "',2)"]);
			_bf.push([theUILang.Normal_priority, "theWebUI.setPriority('" + id + "',1)"]);
			_bf.push([CMENU_SEP]);
			_bf.push([theUILang.Dont_download, "theWebUI.setPriority('" + id + "',0)"]);
		}
   		else
	   		if(p!=null)
   			{
   			       	_bf.push([theUILang.High_priority, (p.priority == 2) ? null : "theWebUI.setPriority('" + id + "',2)"]);
	   	        	_bf.push([theUILang.Normal_priority, (p.priority == 1) ? null : "theWebUI.setPriority('" + id + "',1)"]);
	   		        _bf.push([CMENU_SEP]);
   			        _bf.push([theUILang.Dont_download, (p.priority == 0) ? null : "theWebUI.setPriority('" + id + "',0)"]);
	      		}
	      	if(_bf.length && this.isTorrentCommandEnabled('setprio',this.dID) )
	      		theContextMenu.add([CMENU_CHILD, theUILang.Priority, _bf]);
		else
			theContextMenu.add([theUILang.Priority]);

		if(theWebUI.systemInfo.rTorrent.iVersion>=0x809)
		{
			_bf = [];
			if(table.selCount > 1)
   			{
				_bf.push([theUILang.prioritizeNormal, "theWebUI.setPrioritize('" + id + "',0)"]);
				_bf.push([CMENU_SEP]);
      				_bf.push([theUILang.prioritizeFirst, "theWebUI.setPrioritize('" + id + "',1)"]);
				_bf.push([theUILang.prioritizeLast, "theWebUI.setPrioritize('" + id + "',2)"]);
			}
   			else
	   			if(p!=null)
	   			{
					_bf.push([theUILang.prioritizeNormal, !p.prioritize ? null : "theWebUI.setPrioritize('" + id + "',0)"]);
					_bf.push([CMENU_SEP]);
      					_bf.push([theUILang.prioritizeFirst, (p.prioritize == 1) ? null : "theWebUI.setPrioritize('" + id + "',1)"]);
					_bf.push([theUILang.prioritizeLast, (p.prioritize == 2) ? null : "theWebUI.setPrioritize('" + id + "',2)"]);
		      		}
		      	if(_bf.length && this.isTorrentCommandEnabled('setprioritize',this.dID) )
	      			theContextMenu.add([CMENU_CHILD, theUILang.DLStrategy, _bf]);
			else
				theContextMenu.add([theUILang.DLStrategy]);
		}
		var _bf1 = [];
		if(this.settings["webui.fls.view"])
		{
			_bf1.push([theUILang.AsList]);
			_bf1.push([theUILang.AsTree, "theWebUI.toggleFileView()"]);
		}
		else
		{
			_bf1.push([theUILang.AsList, "theWebUI.toggleFileView()"]);
			_bf1.push([theUILang.AsTree]);
		}
		theContextMenu.add([CMENU_CHILD, theUILang.View, _bf1]);
		return(true);
   	},

	toggleFileView: function()
	{
		this.settings["webui.fls.view"] = !this.settings["webui.fls.view"];
		this.getTable("fls").clearRows();
		if(this.dID!="")
			this.redrawFiles(this.dID);
		this.save();
	},

	getFileIds: function(id, p, property)
	{
   		var sr = this.getTable("fls").rowSel;
   		var str = "";
   		var needSort = false;
   		for(var k in sr)
   		{
      			if(sr[k] == true)
      			{
      				if(this.settings["webui.fls.view"])
      				{
					var arr = k.split('_f_');
	         			var i = iv(arr[1]);
        	 			if(!property || (this.files[id][i][property] != p))
         				{
						str += "&f=" + i;
	            				needSort = true;
        	    			}
        			}
        			else
        			{
				        var dir = theWebUI.dirs[id];
				        var ids = new Array();
				        dir.getFilesIds(ids,dir.current,k,p);
				        for(var i in ids)
						str += "&f=" + ids[i];
					needSort = true;
        			}
         		}
      		}
      		if(needSort)
			this.getTable("fls").Sort();
   		return(str);
   	},

	setPriority: function(id, p)
	{
   		var fls = this.getFileIds(id, p, "priority");
   		this.request("?action=setprio&hash=" + id + "&p=" + p + fls, [this.updateFiles, this]);
   	},

   	setPrioritize: function(id, p)
	{
   		var fls = this.getFileIds(id, p, null);
   		this.request("?action=setprioritize&hash=" + id + "&p=" + p + fls, [this.updateFiles, this]);
   	},

//
// torrents
//

	trtSelect: function(e, id)
	{
		var table = theWebUI.getTable("trt");
		var hash = table.getFirstSelected();
		if((table.selCount==1) && hash)
			theWebUI.showDetails(hash, true);
		else
		{
			theWebUI.dID = "";
			theWebUI.clearDetails();
		}
		if(e.which==3)
		{
			theWebUI.createMenu(e, id);
			theContextMenu.show(e.clientX,e.clientY);
		}
	},

	trtDeselect: function()
	{
		this.getTable("trt").clearSelection();
	},

	createMenu: function(e, id)
	{
   		var table = this.getTable("trt");
   		theContextMenu.clear();
   		if(table.selCount > 1)
   		{
			theContextMenu.add([CMENU_SEL, theUILang.Torrents + ": " + table.selCount, "theWebUI.trtDeselect()"]);
			theContextMenu.add([CMENU_SEP]);
      			theContextMenu.add([theUILang.Start, "theWebUI.start()"]);
      			theContextMenu.add([theUILang.Pause, "theWebUI.pause()"]);
      			theContextMenu.add([theUILang.Stop, "theWebUI.stop()"]);
      			theContextMenu.add([theUILang.Force_recheck, "theWebUI.recheck()"]);
			theContextMenu.add([theUILang.peerAdd]);
   		}
   		else
   		{
			theContextMenu.add([CMENU_SEL, theUILang.Torrents + ": 1", "theWebUI.trtDeselect()"]);
			theContextMenu.add([CMENU_SEP]);
   			theContextMenu.add([theUILang.Start, this.isTorrentCommandEnabled("start",id) ? "theWebUI.start()" : null]);
   			theContextMenu.add([theUILang.Pause, (this.isTorrentCommandEnabled("pause",id) || this.isTorrentCommandEnabled("unpause",id)) ? "theWebUI.pause()" : null]);
   			theContextMenu.add([theUILang.Stop, this.isTorrentCommandEnabled("stop",id) ? "theWebUI.stop()" : null]);
			theContextMenu.add([theUILang.Force_recheck, this.isTorrentCommandEnabled("recheck",id) ? "theWebUI.recheck()" : null]);
			theContextMenu.add([theUILang.peerAdd,
				(!this.isTorrentCommandEnabled("start",id) &&
				this.isTorrentCommandEnabled("addpeer",id) &&
				$type(this.torrents[id]) &&
				(this.torrents[id].private==0) && (theWebUI.systemInfo.rTorrent.iVersion>=0x804)) ?
				"theDialogManager.show('padd')"	: null]);
		}
		theContextMenu.add([theUILang.Recreate_files, this.isTorrentCommandEnabled("recreate",id) ? "theWebUI.recreate()" : null]);
		if(theWebUI.systemInfo.rTorrent.iVersion>=0x809)
			theContextMenu.add([theUILang.updateTracker, this.isTorrentCommandEnabled("updateTracker",id) ? "theWebUI.updateTracker()" : null]);
   		theContextMenu.add([CMENU_SEP]);
   		var _bf = [];
		_bf.push([theUILang.New_label, (table.selCount > 1) || this.isTorrentCommandEnabled("setlabel",id) ? "theWebUI.newLabel()" : null]);
   		_bf.push([theUILang.Remove_label, (table.selCount > 1) || this.isTorrentCommandEnabled("setlabel",id) ? "theWebUI.removeLabel()" : null]);
   		_bf.push([CMENU_SEP]);
		for(const [torrentLabel, info]  of theWebUI.categoryList.torrentLabelTree.torrentLabels)
		{
			const lblText = this.settings["webui.show_label_path_tree"]
				? ('│'.repeat(info.level) + info.path.slice(info.level).join("/"))
				: torrentLabel;
			if((table.selCount == 1) && (this.torrents[id].label == torrentLabel))
				_bf.push([CMENU_SEL, lblText]);
			else
				_bf.push([lblText, (table.selCount > 1) || this.isTorrentCommandEnabled("setlabel",id) ? "theWebUI.setLabel('" + addslashes(torrentLabel) + "')" : null]);
		}
   		theContextMenu.add([CMENU_CHILD, theUILang.Labels, _bf]);
   		theContextMenu.add([CMENU_SEP]);
   		var _c0 = [];
		if (table.selCount > 1) {
			_c0.push([theUILang.High_priority, "theWebUI.perform('dsetprio&v=3')"]);
			_c0.push([theUILang.Normal_priority, "theWebUI.perform('dsetprio&v=2')"]);
			_c0.push([theUILang.Low_priority,  "theWebUI.perform('dsetprio&v=1')"]);
			_c0.push([theUILang.Dont_download,  "theWebUI.perform('dsetprio&v=0')"]);
		} else {
			var p = this.torrents[id].priority;
			_c0.push([theUILang.High_priority, (p==3) || !this.isTorrentCommandEnabled("dsetprio",id) ? null : "theWebUI.perform('dsetprio&v=3')"]);
			_c0.push([theUILang.Normal_priority, (p==2 || !this.isTorrentCommandEnabled("dsetprio",id)) ? null : "theWebUI.perform('dsetprio&v=2')"]);
			_c0.push([theUILang.Low_priority,  (p==1) || !this.isTorrentCommandEnabled("dsetprio",id) ? null : "theWebUI.perform('dsetprio&v=1')"]);
			_c0.push([theUILang.Dont_download, (p==0) || !this.isTorrentCommandEnabled("dsetprio",id) ? null : "theWebUI.perform('dsetprio&v=0')"]);
		}
		theContextMenu.add([CMENU_CHILD, theUILang.Priority, _c0]);
		theContextMenu.add([CMENU_SEP]);
		theContextMenu.add([
			theUILang.Remove,
			(table.selCount > 1) || this.isTorrentCommandEnabled("remove", id)
				? () => theWebUI.removeTorrent()
				: null,
		]);
		theContextMenu.add([CMENU_SEP]);
		if ((table.selCount > 1) || !this.isTorrentCommandEnabled("setprops",id))
			theContextMenu.add([theUILang.Properties]);
		else
			theContextMenu.add([theUILang.Properties, "theWebUI.showProperties('" + id + "')"]);
	},

	/**
	 * Perform a command on the backend.
	 * @param {string} cmd Command to be performed.
	 */
	perform: function(cmd) {
		if (cmd === "pause") {
			var hp = this.getHashes("unpause");
			if (hp != "")
				this.request("?action=unpause" + hp);
		}
		var h = this.getHashes(cmd);
		if (h !== "") {
			if ((cmd.indexOf("remove") === 0) && (h.indexOf(this.dID) > -1)) {
				this.dID = "";
				this.clearDetails();
			}
			this.getTorrents(cmd + h + "&list=1");
		}
	},

	/**
	 * Check if an action is enabled to be performed on 
	 * a torrent specified by the torrent's hash.
	 * @param {string} act Action to be checked.
	 * @param {string} hash Hash of the torrent to be checked.
	 * @returns {boolean}
	 */
	isTorrentCommandEnabled: function(act, hash) {
		const status = this.torrents[hash].state;
		switch (act) {
			case "start": {
				return (!(status & dStatus.started) || (status & dStatus.paused) && !(status & dStatus.checking) && !(status & dStatus.hashing));
			}
			case "pause": {
				return ((status & dStatus.started) && !(status & dStatus.paused) && !(status & dStatus.checking) && !(status & dStatus.hashing));
			}
			case "unpause": {
				return ((status & dStatus.paused) && !(status & dStatus.checking) && !(status & dStatus.hashing));
			}
			case "stop": {
				return ((status & dStatus.started) || (status & dStatus.hashing) || (status & dStatus.checking));
			}
			case "recheck": {
				return !(status & dStatus.checking) && !(status & dStatus.hashing);
			}
			default: {
				return true;
			}
		}
	},

	/**
	 * 
	 * @param {string} act 
	 * @returns {string} Query string of hashes of selected torrents.
	 */
	getHashes: function(act) {
		const pos = act.indexOf('&');
		if (pos >= 0)
			act = act.substring(0, pos);
		return Object.entries(this.getTable("trt").rowSel)
			.filter(([hash, isSelected]) => (isSelected && this.isTorrentCommandEnabled(act, hash)))
			.reduce((acc, curr) => (acc + "&hash=" + curr[0]), "");
	},

	start: function()
	{
		this.perform("start");
	},

	pause: function()
	{
   		this.perform("pause");
   	},

	stop: function()
	{
   		this.perform("stop");
   	},

   	updateTracker: function()
	{
   		this.perform("updateTracker");
   	},

	/**
	 * Remove selected torrent(s), **without** confirmation.
	 */
	remove: function() {		
		this.perform("remove");
	},

	/**
	 * Remove selected torrent(s). Will confirm before taking action
	 * if the option is enabled in the settings.
	 */
	removeTorrent: function() {
		const table = this.getTable("trt");
		if ((table.selCount > 1) ||
			((table.selCount === 1) && this.isTorrentCommandEnabled("remove", table.getFirstSelected()))) {
			if (this.settings["webui.confirm_when_deleting"]) {
				askYesNo(
					theUILang.Remove_torrents,
					theUILang.Rem_torrents_prompt,
					() => this.perform("remove"),
				);
			} else
				this.perform("remove");
		}
	},

	doRemove: function() {
		this.perform(this.delmode);
	},

	recheck: function()
	{
		this.perform("recheck");
   	},

   	recreate: function()
	{
		this.perform("createqueued");
   	},

	getTorrents: function(qs)
	{
		if(this.updTimer)
			window.clearTimeout(this.updTimer);
		this.timer.start();
		if(qs != "list=1")
			qs = "action=" + qs;
		this.requestWithTimeout("?" + qs + "&getmsg=1", [this.addTorrents, this],
			function()
			{
	   			theWebUI.timeout();
				theWebUI.setInterval();
		   	},
			function(status,text)
			{
				theWebUI.systemInfo.rTorrent.started = false;
	   			theWebUI.error(status,text);
				if(theWebUI.settings["webui.retry_on_error"])
					theWebUI.setInterval( iv(theWebUI.settings["webui.retry_on_error"])*1000 );
		   	});
   	},

	fillAdditionalTorrentsCols: function(hash,cols)
	{
		return(cols);
	},

	updateAdditionalTorrentsCols: function(hash)
	{
	},

	/**
	 * @typedef {Object} WebUITorrent
	 * @property {string} name
	 * @property {string} label
	 * @property {number} dl
	 * @property {number} ul
	 * @property {StatusIcon} status
	 * @property {StatusMask} state
	 * @property {number} done - number between 0..1000
	 * @property {number} size
	 */

	/**
	 * @param {Object} data
	 * @param {Object.<string, WebUITorrent>} data.torrents
	 */
	addTorrents: function(data)
	{
		if(!theWebUI.systemInfo.rTorrent.started)
		{
			noty(theUILang.linkTorTorrentRestored,'success');
			theWebUI.systemInfo.rTorrent.started = true;
		}

		const table = this.getTable("trt");
		// be lazy with DOM updates while adding torrents in the background
		table.setLazy(true);

		const dataTorrents = data.torrents;
		data = null;

		let statistic = this.categoryList.statistic.empty();

		this.taskAddTorrents
			.reset()
			.map(Object.entries(dataTorrents), ([hash, torrent]) => {
				const sInfo = this.getStatusIcon(torrent);
				torrent.status = sInfo[1];
				// Accumulate into view category list
				statistic.scan(hash, torrent);
				// Update torrent table
				table.setRowById(torrent, hash, sInfo[0], {})
			})
			.enqueueFunc(() => {
				// update details page
				const detailsTorrent = dataTorrents[theWebUI.dID];
				const oldDetailsTorrent = this.torrents[theWebUI.dID];
				if(theWebUI.activeView === 'FileList' &&
				   detailsTorrent &&
				   detailsTorrent.downloaded !== oldDetailsTorrent?.downloaded)
					theWebUI.updateFiles(theWebUI.dID);

				if(theWebUI.activeView === 'TrackerList')
					theWebUI.updateTrackers(theWebUI.dID);

				if(theWebUI.activeView === 'PeerList')
					theWebUI.updatePeers(theWebUI.dID);

				// Cleanup removed torrents
				for (const hash in this.torrents) {
					if (!(hash in dataTorrents)) {
						delete theWebUI.files[hash];
						delete theWebUI.dirs[hash];
						delete theWebUI.peers[hash];
						table.removeRow(hash);
					}
				}
				const newHashes = Object.keys(dataTorrents)
					.filter(hash => !(hash in this.torrents));
				// Assign new torrent data
				this.torrents = dataTorrents;
				this.categoryList.statistic = statistic;
				this.setSpeedValues(statistic.upload, statistic.download);

				// Filter torrent table
				for (const hash in this.torrents)
					this.filterByLabel(table, hash);

				// Fetch additional data
				this.getAllTrackers(newHashes);
				this.getTotal();
				if (this.settings['webui.show_open_status'])
					this.getOpenStatus();

				// set timeout for next update
				this.setInterval();

				table.setLazy(false);
				if (this.taskAddTorrentsAnimateHandleId)
					cancelAnimationFrame(this.taskAddTorrentsAnimateHandleId);
				const nextAFrame = () => new Promise((resolve) =>
					this.taskAddTorrentsAnimateHandleId = requestAnimationFrame(() => {
						this.taskAddTorrentsAnimateHandleId = 0;
						resolve();
					}));
				const domUpdates = async () => {
					// update state, custom, and teg labels
					await nextAFrame();
					this.categoryList.syncAfterScan();
					if (!this.firstLoad)
						await nextAFrame();
					this.updateViewRows(table);
					this.loadTorrents();
				};
				domUpdates();
			})
			.run(this.firstLoad)
			.catch(reason => {
				if (reason !== 'reset') {
					console.error(reason);
				}
			});
	},


	updateViewRows: function(table)
	{
		const viewSize = table
			.getAllEnabledValuesById('size')
			.reduce((total, size) => total + iv(size), 0);
		$('#viewrows').text(table.viewRows + '/' + table.rows);
		$('#viewrows_size').text(theConverter.bytes(viewSize, 'table'));
	},

	setSpeedValues: function(tul,tdl)
	{
		this.speedGraph.addData(tul,tdl);
		this.total.speedDL = tdl;
		this.total.speedUL = tul;
	},

	loadTorrents: function()
	{
		if(this.firstLoad)
		{
			this.firstLoad = false;
			const catList = $$('CatList');
			if (catList.pendingStyleLoad) {
				catList.addEventListener(
					'style-load',
					() => this.show(),
					{ once: true }
				);
			} else {
				this.show();
			}
		}
		this.updateDetails();
   	},

   	getTotal: function()
	{
	        this.request("?action=gettotal",[this.addTotal, this]);
	},

   	addTotal: function( d )
	{
	        $.extend(this.total,d);
	},

	getOpenStatus: function()
	{
		if (this.systemInfo.rTorrent.iVersion >= 0x907)
			this.request("?action=getopen", [this.addOpenStatus, this]);
	},

	addOpenStatus: function(stopen)
	{
		Object.assign(this.stopen, stopen);
	},

	/**
	 * @typedef {array.<string>} StatusIcon
	 * first element: icon nameDevelop
	 * second element: localized status
	 */

	/**
	 * @param {WebUITorrent} torrent
	 * @returns {StatusIcon}
	 */
	getStatusIcon: function(torrent)
	{
		var state = torrent.state;
		var completed = torrent.done;
		var icon = "", status = "";
		if(state & dStatus.checking)
		{
			icon = "Status_Checking";
			status = theUILang.Checking;
		}
		else
		if(state & dStatus.hashing)
		{
			icon = "Status_Queued_Up";
			status = theUILang.Queued;
		}
		else
		{
			if(state & dStatus.started)
			{
				if(state & dStatus.paused)
				{
					icon = "Status_Paused";
					status = theUILang.Pausing;
				}
				else
				{
					icon = (completed == 1000) ? "Status_Up" : "Status_Down";
					status = (completed == 1000) ? theUILang.Seeding : theUILang.Downloading;
				}
			}
		}
		if(state & dStatus.error)
		{
			if(icon=="Status_Down")
				icon = "Status_Error_Down";
			else
			if(icon=="Status_Up")
				icon = "Status_Error_Up";
			else
				icon = "Status_Error";
		}
		if((completed == 1000) && (status == ""))
		{
			if(icon=="")
				icon = "Status_Completed";
			status = theUILang.Finished;
		}
		if((completed < 1000) && (status == ""))
		{
			if(icon=="")
				icon = "Status_Incompleted";
			status = theUILang.Stopped;
		}
		return([icon, status]);
	},

//
// labels
//
	labelContextMenu: function(e)
	{
		const table = theWebUI.contextMenuTable(e.panelId, e.labelId);
		if (e.rightClick)
		{
			table.clearSelection();
		}
		if (!(e.rightClick && theWebUI.categoryList.selectionActive(e.panelId, e.labelId))) {
			theWebUI.categoryList.switchLabel(e.panelId, e.labelId, e.metaKey, e.shiftKey);
		}
		if (e.rightClick)
		{
			table.fillSelection();
			const id = table.getFirstSelected();
			const entries = theWebUI.categoryList.contextMenuEntries(e.panelId, e.labelId);
			if (!(entries && (id || entries.length)))
			{
				theContextMenu.hide();
				return false;
			}
			// show a context menu
			if (id) {
				theWebUI.createMenu(null, id);
				if (entries.length) {
					theContextMenu.add([CMENU_SEP]);
				}
			} else {
				theContextMenu.clear();
			}
			for (const entry of entries) {
				theContextMenu.add(entry);
			}
			theContextMenu.show(e.clientX,e.clientY);
		}
		return(e.fromTextCtrl);
	},

	contextMenuTable: function(panelId, labelId) {
		return theWebUI.getTable('trt');
	},

	setTeg: function(str) {
		this.categoryList.addTextSearch(str);
	},

	sizeDecimalPlaces: function(context, unit) {
		let n = parseInt(this.settings['webui.size_decimal_places.'+context+'.'+unit]);
		n = isNaN(n) ? parseInt(this.settings['webui.size_decimal_places.'+context+'.default']) : n;
		return isNaN(n) ? (context === 'other' ? 0 : this.sizeDecimalPlaces('other', unit)) : n;
	},

	/**
	 *
	 * @param {string} lbl - label
	 */
	setLabel: function(lbl)
	{
		var req = '';
   		var sr = this.getTable("trt").rowSel;
   		for(var k in sr)
   		{
      			if(sr[k] && (this.torrents[k].label != lbl))
      				req += ("&hash=" + k + "&s=label&v=" + encodeURIComponent(lbl));
		}
		if(req.length>0)
			this.request("?action=setlabel"+req+"&list=1",[this.addTorrents, this]);
	},

	removeLabel: function()
	{
	        this.setLabel("");
   	},

	newLabel: function()
	{
		var table = this.getTable("trt");
		var s = theUILang.newLabel;
		if(table.selCount == 1)
      		{
      		        var k = table.getFirstSelected();
			var lbl = this.torrents[k].label;
			if(lbl != "")
				s = this.torrents[k].label;
		}
		$("#txtLabel").val(s);
		theDialogManager.show("dlgLabel");
	},

	createLabel: function()
	{
   		var lbl = $("#txtLabel").val().trim();
		lbl = lbl.replace(/\"/g, "'");
   		if(lbl != "")
		{
	   		var sr = this.getTable("trt").rowSel;
			var req = "";
			for(var k in sr)
			{
      				if(sr[k] && (this.torrents[k].label != lbl))
	         			req+=("&hash=" + k + "&s=label&v=" + encodeURIComponent(lbl));
	         	}
			if(req.length>0)
				this.request("?action=setlabel"+req+"&list=1",[this.addTorrents, this]);
		}
	},


	filterTorrentTable: function() {
		var table = this.getTable("trt");
		table.scrollTo(0);
		for(const hash of Object.keys(this.torrents))
			this.filterByLabel(table, hash);
		table.clearSelection();
		table.syncDOM();
		if(this.dID != "")
		{
			this.dID = "";
			this.clearDetails();
		}
		this.updateViewRows(table);
	},

	filterByLabel: function(table, sId)
	{
		if(this.categoryList.selected(sId))
			table.unhideRow(sId);
		else
			table.hideRow(sId);
	},

//
// properties
//

   	showProperties: function(k)
	{
   		this.pID = k;
   		this.request("?action=getprops&hash=" + k, [this.loadProperties, this]);
   	},

	loadProperties: function(data)
	{
		$.extend(this.props, data);
   		this.updateProperties();
   	},

	updateProperties: function()
	{
   		var props = this.props[this.pID];
   		$("#prop-ulslots").val( props.ulslots );
   		$("#prop-peers_min").val( props.peers_min );
   		$("#prop-peers_max").val( props.peers_max );
   		$("#prop-tracker_numwant").val( props.tracker_numwant );
   		if(props.pex ==- 1)
		{
   		        $("#prop-pex").prop("checked",false).prop("disabled",true);
			$("#lbl_prop-pex").addClass("disabled");
		}
   		else
   		{
   			$("#prop-pex").prop("checked",(props.pex==1)).prop("disabled",false).removeClass("disabled");
			$("#lbl_prop-pex").removeClass("disabled");
		}
		o = $$("prop-superseed");
		if(this.torrents[this.pID].done==1000)
		{
		        $("#prop-superseed").prop("disabled",false);
      			$("#lbl_prop-superseed").removeClass("disabled");
		}
		else
		{
		        $("#prop-superseed").prop("disabled",true);
      			$("#lbl_prop-superseed").addClass("disabled");
     		}
     		$("#prop-superseed").prop("checked",(props.superseed==1));
   		theDialogManager.show("dlgProps");
   	},

	setProperties: function()
	{
   		theDialogManager.hide("dlgProps");
   		var req = '';
   		for(var k in this.props[this.pID])
   		{
      			var v = this.props[this.pID][k];
			var o = $("#prop-" + k);
      			var nv = o.is("input:checkbox") ? o.is(":checked")+0 : o.val()
      			if((k == "hash") || ((k == "pex") && (v ==- 1)))
         			continue;
      			if(v != nv)
      			{
				this.props[this.pID][k] = nv;
      				req+=("&s=" + k + "&v=" + nv);
   			}
		}
         	if(req.length>0)
	       		this.request("?action=setprops&hash=" + this.pID + req);
        },

//
// details
//

	showDetails: function(hash, noSwitch)
	{
   		if(!noSwitch)
      			theTabs.show("gcont");
   		this.dID = hash;
   		this.getFiles(hash);
 		this.getTrackers(hash);
		this.getPeers(hash);
   		if(!noSwitch && !theWebUI.settings["webui.show_dets"])
   		{
			$("#tdetails").show();
      			theWebUI.settings["webui.show_dets"] = true;
      			theWebUI.resize();
      			theWebUI.save();
      		}
   		this.updateDetails();
   	},

        clearDetails: function()
	{
		$(".det").text("");
		this.getTable("fls").clearRows();
		this.getTable("trk").clearRows();
		this.getTable("prs").clearRows();
	},

	updateDetails: function()
	{
   		if((this.dID != "") && this.torrents[this.dID])
   		{
	   		var d = this.torrents[this.dID];
                        $("#dl").text(theConverter.bytes(d.downloaded,'details'));
			$("#ul").text(theConverter.bytes(d.uploaded,'details'));
			$("#ra").html( (d.ratio ==- 1) ? "&#8734;" : theConverter.round(d.ratio/1000,3));
			$("#us").text(theConverter.speed(d.ul));
			$("#ds").text(theConverter.speed(d.dl));
			$("#rm").html((d.eta ==- 1) ? "&#8734;" : theConverter.time(d.eta));
			$("#se").text(d.seeds_actual + " " + theUILang.of + " " + d.seeds_all + " " + theUILang.connected);
			$("#pe").text(d.peers_actual + " " + theUILang.of + " " + d.peers_all + " " + theUILang.connected);
			$("#et").text(theConverter.time(Math.floor((new Date().getTime()-theWebUI.deltaTime)/1000-iv(d.state_changed)),true));
			$("#wa").text(theConverter.bytes(d.skip_total, 'details'));
	        	$("#bf").text(d.base_path);
	        	$("#co").text(theConverter.date(iv(d.created)+theWebUI.deltaTime/1000));
			const trackers = this.trackers[this.dID] ?? [];
			$("#tu").text(trackers.length ? (trackers[0].name  + (trackers.length > 1 ? ` ${theUILang.of} ${d.tracker_size}` : '')) : `${d.tracker_size}`);
	        	$("#hs").text(this.dID.substring(0,40));
			$("#ts").text(d.msg);
			var url = d.comment.trim();
			if(!url.match(/<a href/i))
			{
				var start = url.indexOf("http://");
				if(start<0)
					start = url.indexOf("https://");
				if(start>=0)
				{
					var end = url.indexOf(" ",start);
 					if(end<0)
						end = url.length;
					var prefix = url.substring(0,start);
					var postfix = url.substring(end);
					url = url.substring(start,end);
					url = prefix+"<a href='"+url+"' target=_blank>"+url+"</a>"+postfix;
				}
			}
			$("#cmt").html( strip_tags(url,'<a><b><strong>') );
			$("#dsk").text((d.free_diskspace=='0') ? '' : theConverter.bytes(d.free_diskspace,'details'));
		}
	},

//
// service
//

	getTable: function(prefix)
	{
		return($type(this.tables[prefix]) ? this.tables[prefix].obj : null);
	},

	updateStatus: function()
	{
	        var self = theWebUI;
		var ul = theConverter.speed(self.total.speedUL);
		var dl = theConverter.speed(self.total.speedDL);
		var newTitle = '';
		if(theWebUI.settings["webui.speedintitle"])
		{
			if(ul.length)
				newTitle+=('↑'+ul+' ');
			if(dl.length)
				newTitle+=('↓'+dl+' ');
		}
		newTitle+="ruTorrent v"+self.version;
		if(document.title!=newTitle)
			document.title = newTitle;
	        $("#stup_speed").text(ul);
	        $("#stup_limit").text((self.total.rateUL>0 && self.total.rateUL<327625*1024) ? theConverter.speed(self.total.rateUL) : theUILang.no);
	        $("#stup_total").text(theConverter.bytes(self.total.UL));
	        $("#stdown_speed").text(dl);
	        $("#stdown_limit").text((self.total.rateDL>0 && self.total.rateDL<327625*1024) ? theConverter.speed(self.total.rateDL) : theUILang.no);
	        $("#stdown_total").text(theConverter.bytes(self.total.DL));

		if (self.settings['webui.show_open_status']
			&& 'systemInfo' in self
			&& self.systemInfo.rTorrent.iVersion >= 0x907) {
			for (const opnType of ['http', 'sock', 'fd']) {
				const ele = $($$('stopen_'+opnType+'_count'));
				if (self.stopen[opnType] > -1)
					ele.text(self.stopen[opnType] + ' ' + opnType).show();
				else
					ele.hide()
			}
			$("#st_fd").show();
		} else {
			$("#st_fd").hide();
		}
	},

	setDLRate: function(spd)
	{
		this.request("?action=setdlrate&s="+spd,[this.getTotal, this]);
	},

	setULRate: function(spd)
	{
		this.request("?action=setulrate&s="+spd,[this.getTotal, this]);
	},

	downRateMenu: function(e)
	{
	        if(e.which==3)
	        {
	                theContextMenu.clear();
	                var speeds=theWebUI.settings["webui.speedlistdl"].split(",");
	                if(theWebUI.total.rateDL<=0 || theWebUI.total.rateDL>=327625*1024)
	                	theContextMenu.add([CMENU_SEL,theUILang.unlimited,"theWebUI.setDLRate(327625*1024)"]);
			else
		                theContextMenu.add([theUILang.unlimited,"theWebUI.setDLRate(327625*1024)"]);
			theContextMenu.add([CMENU_SEP]);
	                for(var i=0; i<speeds.length; i++)
	                {
	                	var spd = iv(speeds[i])*1024;
		                if(theWebUI.total.rateDL==spd)
					theContextMenu.add([CMENU_SEL,theConverter.speed(spd),"theWebUI.setDLRate("+spd+")"]);
				else
					theContextMenu.add([theConverter.speed(spd),"theWebUI.setDLRate("+spd+")"]);
			}
			theContextMenu.show(e.clientX,e.clientY);
		}
		return(false);
	},

	upRateMenu: function(e)
	{
	        if(e.which==3)
	        {
	                theContextMenu.clear();
	                var speeds=theWebUI.settings["webui.speedlistul"].split(",");
	                if(theWebUI.total.rateUL<=0 || theWebUI.total.rateUL>=327625*1024)
	                	theContextMenu.add([CMENU_SEL,theUILang.unlimited,"theWebUI.setULRate(327625*1024)"]);
			else
		                theContextMenu.add([theUILang.unlimited,"theWebUI.setULRate(327625*1024)"]);
			theContextMenu.add([CMENU_SEP]);
	                for(var i=0; i<speeds.length; i++)
	                {
	                	var spd = iv(speeds[i])*1024;
		                if(theWebUI.total.rateUL==spd)
					theContextMenu.add([CMENU_SEL,theConverter.speed(spd),"theWebUI.setULRate("+spd+")"]);
				else
					theContextMenu.add([theConverter.speed(spd),"theWebUI.setULRate("+spd+")"]);
			}
			theContextMenu.show(e.clientX,e.clientY);
		}
		return(false);
	},

	resizeLeft: function(w) {
		if (!w)
			return;
		const offcanvas = $("#offcanvas-sidepanel");
		if (theWebUI.settings["webui.side_panel_min_width"]) {
			w = Math.max(w, ir(theWebUI.settings["webui.side_panel_min_width"]));
		}
		if (theWebUI.settings["webui.side_panel_max_width_percent"]) {
			w = Math.min(w, $(window).width() * ir(theWebUI.settings["webui.side_panel_max_width_percent"]) / 100);
		}
		if (offcanvas.css("display") === "none") {
			// Senerio 1: when side panel is toggled off
			$("#HDivider").hide();
		} else {
			// When side panel is toggled on
			if ($(window).width() < 768) {
				// Senerio 2: small screens and below
				offcanvas.css("flex-basis", "");
				$("#HDivider").hide();
			} else {
				// Senerio 3: medium screens and above
				offcanvas.css("flex-basis", w);
				$("#HDivider").show();
			}
		}
		this.resizeGraph();
	},

	resizeTop: function(w, h) {  // TODO: rename function to `function(h)` in v6
		// TODO: remove below in v6
		if (w && h) {
			// backward compatibility for those calling this function name with two parameters
			noty("`theWebUI.resizeTop(w, h)` is deprecated. Please use `theWebUI.resizeTop(h)` instead.");
		}
		if (!w && !h)
			return
		if (w && !h)
			h = w;
		// TODO: remove above in v6
		if (theWebUI.settings["webui.list_table_min_height"]) {
			h = Math.max(h, ir(theWebUI.settings["webui.list_table_min_height"]));
		}
		h = Math.min(h, $("#main-info").height() - 5);
		if ($("#tdetails").css("display") !== "none") {
			$("#list-table").css("flex-basis", h);
			this.resizeGraph();
		} else {
			$("#list-table").css("flex-basis", "100%");
		}
	},

	resizeGraph: function() {
		// Resize graphs in #tdetails
		const tdcont = $("#tdcont");
		this.speedGraph.resize(tdcont.width(), tdcont.height());
	},

	resize: function() {
		if ($("#t").css("display") === "none") {
			$("#maincont").height($(window).height() - 30); // 25px for #StatusBar, 5px for margin top
		} else {
			$("#maincont").css({height:""});
		}
		const w = $("#maincont").width() * (1 - theWebUI.settings["webui.hsplit"]) - 5;
		theWebUI.resizeLeft(w);
		const h = $("#maincont").height() * theWebUI.settings["webui.vsplit"];
		theWebUI.resizeTop(null, h);
		// center any open dialog
		theDialogManager.visible.forEach(id => theDialogManager.center(id));
		// close collapsible top menu
		bootstrap.Collapse.getInstance("#top-menu")?.hide();
	},

	update: function() {
		if(theWebUI.systemInfo.rTorrent.started || !this.firstLoad)
			theWebUI.getTorrents("list=1");
		else
			theWebUI.show();
	},

	setVSplitter: function() {
		let r = 1 - ($("#tdetails").outerHeight() + 5) / $("#maincont").height();
		r = Math.floor(r * 1000) / 1000;
		if ((theWebUI.settings["webui.vsplit"] !== r) && (r > 0) && (r < 1)) {
			theWebUI.settings["webui.vsplit"] = r;
			this.save();
		}
	},

	setHSplitter: function() {
		let r = 1 - $("#offcanvas-sidepanel").outerWidth() / $("#maincont").width();
		r = Math.floor(r * 1000) / 1000;
		if ((theWebUI.settings["webui.hsplit"] !== r) && (r > 0) && (r < 1)) {
			theWebUI.settings["webui.hsplit"] = r;
			this.save();
		}
	},

	toggleMenu: function() {
		$("#t").toggle();
		this.resize();
	},

	toggleDetails: function() {
		this.settings["webui.show_dets"] = !this.settings["webui.show_dets"];
		$("#tdetails, #VDivider").toggle(this.settings["webui.show_dets"]);
		this.resize();
		this.save();
	},

	toggleCategories: function() {
		if ($(window).width() < 768) {
			// do nothing on mobile displays
			return;
		}
		this.settings["webui.show_cats"] = !this.settings["webui.show_cats"];
		$("#offcanvas-sidepanel, #HDivider").toggle(this.settings["webui.show_cats"]);
		this.resize();
		this.save();
	},

	showAdd: function()
	{
   		theDialogManager.toggle("tadd");
   	},

	startSearch: function(e)
	{
	e.preventDefault();
	$$("query").focus();
	},

	clearSearch: function()
	{
		$$("query").value = "";
		theWebUI.updateQuickSearch();
	},

	endSearch: function()
	{
		$$("query").blur();
	},

	updateQuickSearch: function()
	{
		theWebUI.categoryList.setQuickSearch(
			theSearchEngines.current === -1 ? $$("query").value : null
		);
	},

	resetInterval: function()
	{
		this.timer.stop();
		if(this.updTimer)
			window.clearTimeout(this.updTimer);
		this.interval = iv(this.settings["webui.update_interval"]);
		this.updTimer = window.setTimeout(this.update, this.interval);
	},

	setInterval: function( force )
	{
		this.timer.stop();
		if(force)
			this.interval = force;
		else
		if(this.interval ==- 1)
			this.interval = iv(this.settings["webui.update_interval"]) + this.timer.interval * 4;
		else
			this.interval = iv((this.interval + iv(this.settings["webui.update_interval"]) + this.timer.interval * 4) / 2);
		this.updTimer = window.setTimeout(this.update, this.interval);
   	},

	setActiveView: function(id) {
		$("#tooltip").remove();
		this.activeView = id;
		if (this.settings['webui.selected_tab.keep'])
			this.save();
	},

	request: function(qs, onComplite, isASync) {
		this.requestWithTimeout(qs, onComplite, this.timeout, this.error, isASync);
	},

	requestWithTimeout: function(qs, onComplite, onTimeout, onError, isASync) {
		Ajax(this.url + qs, isASync, onComplite, onTimeout, onError, this.settings["webui.reqtimeout"]);
	},

	requestWithoutTimeout: function(qs, onComplite, isASync) {
		Ajax(this.url + qs, isASync, onComplite, null, this.error, -1);
	},

	show: function()
	{
		if($("#cover").is(":visible"))
		{
			$("#cover").hide();
			setTimeout(theWebUI.resize, 0);
		}
	},

   	msg: function(s)
   	{
		$('#loadimg').hide();
    		$("#msg").text(s);
   	},

   	catchErrors: function(toLog)
   	{
		if(toLog)
	   		window.onerror = function(msg, url, line, col, error)
			{
				theWebUI.show();
				noty("JS error: [" + url + " : " + line + "] " + msg,"error");

				if (error != null)
					console.log(msg, "from", error.stack);

				return true;
			}
		else
			window.onerror = function(msg, url, line, col, error)
			{
				msg = "JS error: [" + url + " : " + line + "] " + msg;
				theWebUI.msg(msg);
				log(msg);
				
				if (error != null)
					console.log(msg, "from", error.stack);
				
				return true;
			}
   	},

	error: function(status,text)
	{
		theWebUI.show();
		noty("Bad response from server: ("+status+") "+(text ? text : ""),"error");
	},

	timeout: function()
	{
		theWebUI.show();
		if(!theWebUI.settings["webui.ignore_timeouts"])
			noty(theUILang.Request_timed_out,"alert");
	}

};


function createCategoryList(catModule) {
	const catList = $$('CatList');
	const saveViewButton = $$('pview_save_view_button');
	theDialogManager.make(
		"dlgRenameView",
		theUILang.RenameView,
		[
			$("<div>").addClass("cont").append(
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-12").append(
						$("<input>").attr({type:"text"}).on("focus", (ev) => ev.target.select()),
					),
				),
			),
			$("<div>").addClass("buttons-list").append(
				$("<button>").val("confirm").on("click", () => {
					const inputEl = $("#dlgRenameView input[type=text]");
					categoryList.renameView(inputEl.data("view-id"), inputEl.val())
					theDialogManager.hide('dlgRenameView');
				}).text(theUILang.ok),
				$("<button>").addClass("Cancel").text(theUILang.Cancel),
			),
		],
		true,
	);
	const categoryList = new catModule.CategoryList({
		panelAttribs: catList.panelAttribs,
		panelLabelAttribs: catList.panelLabelAttribs,
		syncFn: () => {
			catList.classList.toggle(
				"rightalign-labelsize",
				theWebUI.settings["webui.labelsize_rightalign"]
			);
			catList.classList.toggle(
				"hide-textoverflow",
				!theWebUI.settings["webui.show_label_text_overflow"]
			);
			categoryList.panelAttribs.pview.hidden = !theWebUI.settings["webui.show_view_panel"];
			catList.sync(categoryList.panelLabelAttribs, categoryList.panelAttribs);
			// Enable view save button if view selection is new
			saveViewButton.toggleAttribute('disabled', !categoryList.currentViewIsNew());
		},
		onSelectionChangeFn: () => {
			if (theSearchEngines.current === -1 && !categoryList.quickSearchActive()) {
				const searchField = $$('query');
				if (searchField.value) {
					searchField.blur();
				}
			}
			theWebUI.filterTorrentTable();
		},
		borrowTorrentsFn: () => theWebUI.torrents,
		onConfigChangeFn: theWebUI.save.bind(theWebUI),
		byteSizeToStringFn: (size) => theConverter.bytes(size, 'catlist'),
		renameViewDialogFn: (viewId, viewName) => {
			theDialogManager.show("dlgRenameView");
			$("#dlgRenameView input[type=text]").val(viewName).data({"view-id":viewId}).trigger("focus");
		},
		dStatus,
		theUILang
	});
	catList.addEventListener('panel-close', ({panelId, closed}) => {
		categoryList.setPanelClosed(panelId, closed, true);
	});
	catList.addEventListener('label-click', theWebUI.labelContextMenu.bind(theWebUI));
	// Bind button to save a new view
	saveViewButton.addEventListener("click", (event) => {
		categoryList.saveNewView();
		event.stopPropagation();
		return false;
	});
	return categoryList;
}

$(document).ready(function()
{
	makeContent();
	theContextMenu.init();
	theTabs.init();
	Promise.all([
		import('./backgroundtask.js'),
		import('./category-list.js'),
		import('./log_history.js'),
	]).then(([bgModule, catModule]) => {
		theWebUI.taskAddTorrents = new bgModule.BackgroundTask();
		theWebUI.categoryList = createCategoryList(catModule);
		theWebUI.init();
	});
});
