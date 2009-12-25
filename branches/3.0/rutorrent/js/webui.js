var theWebUI = 
{
        version: "3.0 (beta)",
	tables:
	{
		trt: 
		{
			obj: new dxSTable(),
			columns:
			[
				{ text: WUILang.Name, 			width: "200px", id: "name",		type: TYPE_STRING }, 
		      		{ text: WUILang.Status, 		width: "100px",	id: "status",		type: TYPE_STRING },
		   		{ text: WUILang.Size, 			width: "60px",	id: "size", 		type: TYPE_NUMBER },
	   			{ text: WUILang.Done, 			width: "80px",	id: "done",		type: TYPE_NUMBER },
				{ text: WUILang.Downloaded, 		width: "100px",	id: "downloaded",	type: TYPE_NUMBER },
				{ text: WUILang.Uploaded, 		width: "100px",	id: "uploaded",		type: TYPE_NUMBER },
				{ text: WUILang.Ratio, 			width: "60px",	id: "ratio",		type: TYPE_NUMBER },
				{ text: WUILang.DL, 			width: "60px", 	id: "dl",		type: TYPE_NUMBER },
				{ text: WUILang.UL, 			width: "60px", 	id: "ul",		type: TYPE_NUMBER },
				{ text: WUILang.ETA, 			width: "60px", 	id: "eta",		type: TYPE_NUMBER },
				{ text: WUILang.Label, 			width: "60px", 	id: "label",		type: TYPE_STRING },
				{ text: WUILang.Peers, 			width: "60px", 	id: "peers",		type: TYPE_NUMBER },
				{ text: WUILang.Seeds, 			width: "60px", 	id: "seeds",		type: TYPE_NUMBER },
				{ text: WUILang.Priority, 		width: "80px", 	id: "priority",		type: TYPE_NUMBER },
				{ text: WUILang.Created_on,		width: "100px", id: "created",		type: TYPE_NUMBER },
				{ text: WUILang.Remaining, 		width: "90px", 	id: "remaining",	type: TYPE_NUMBER }
			],
			container:	"List",
			format:		theFormatter.torrents,
			ondelete:	function() { theWebUI.remove(); },
  	                onselect:	function(e,id) { theWebUI.trtSelect(e,id) },
			ondblclick:	function(obj) { theWebUI.showDetails(obj.id); return(false); }
		},
		fls:
		{
			obj: new dxSTable(),
			columns:
			[
				{ text: WUILang.Name, 			width: "200px",	id: "name",		type: TYPE_STRING },
				{ text: WUILang.Size, 			width: "60px", 	id: "size",		type: TYPE_NUMBER,	"align" : ALIGN_RIGHT},
				{ text: WUILang.Done, 			width: "80px", 	id: "done",		type: TYPE_NUMBER },
				{ text: "%", 				width: "100px",	id: "percent",		type: TYPE_NUMBER },
				{ text: WUILang.Priority, 		width: "80px", 	id: "priority",		type: TYPE_NUMBER }
			],
			container:	"FileList",
			format:		theFormatter.files,
			onselect:	function(e,id) { theWebUI.flsSelect(e,id) },
			ondblclick:	function(obj) 
			{
				if(!theWebUI.settings["webui.fls.view"] && (theWebUI.dID!=""))
				{
					var lnk = this.getAttr(obj.id, "link");
		                	if(lnk!=null)
		                	{
		                		theWebUI.dirs[theWebUI.dID].setDirectory(lnk);
						this.clearRows();
				    		theWebUI.redrawFiles(theWebUI.dID);
					}
				}
				return(false);
			}
		},
		trk:
		{
			obj: new dxSTable(),
			columns:
			[
				{ text: WUILang.Name,			width: "200px", id: "name",		type: TYPE_STRING },
				{ text: WUILang.Type, 			width: "60px", 	id: "type",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT},
				{ text: WUILang.Enabled, 		width: "60px", 	id: "enabled",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT},
				{ text: WUILang.Group, 			width: "60px", 	id: "group",		type: TYPE_NUMBER },
				{ text: WUILang.Seeds, 			width: "60px", 	id: "seeds",		type: TYPE_NUMBER },
				{ text: WUILang.Peers, 			width: "60px", 	id: "peers",		type: TYPE_NUMBER },
				{ text: WUILang.scrapeDownloaded,	width: "80px", 	id: "downloaded",	type: TYPE_NUMBER }
			],
			container:	"TrackerList",
			format:		theFormatter.trackers,
			onselect:	function(e,id) { theWebUI.trkSelect(e,id) }
		},
		prs:
		{
			obj: new dxSTable(),
			columns:
			[
				{ text: WUILang.Name, 			width: "100px", id: "name",		type: TYPE_STRING },
				{ text: WUILang.ClientVersion,		width: "120px", id: "version",		type: TYPE_STRING },
				{ text: WUILang.Flags, 			width: "60px", 	id: "flags",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT},
				{ text: WUILang.Done, 			width: "80px", 	id: "done",		type: TYPE_NUMBER },
				{ text: WUILang.Downloaded, 		width: "100px", id: "downloaded",	type: TYPE_NUMBER },
				{ text: WUILang.Uploaded, 		width: "100px", id: "uploaded",		type: TYPE_NUMBER },
				{ text: WUILang.DL, 			width: "60px", 	id: "dl",		type: TYPE_NUMBER },
				{ text: WUILang.UL, 			width: "60px", 	id: "ul",		type: TYPE_NUMBER }
			],
			container:	"PeerList",
			format:		theFormatter.peers,
			onselect:	function(e,id) { theWebUI.prsSelect(e,id) },
			ondblclick:	function(obj) 
			{ 
				if(obj.id && theWebUI.peers[obj.id])
					window.open(WUIResorces.RIPEURL + theWebUI.peers[obj.id].ip, "_blank");
				return(false);
			}
		},
		plg:
		{
			obj: new dxSTable(),
			columns:
			[
				{ text: WUILang.plgName,			width: "150px", id: "name",		type: TYPE_STRING },
				{ text: WUILang.plgVersion,			width: "60px",	id: "version",		type: TYPE_NUMBER },
				{ text: WUILang.plgStatus, 			width: "80px", 	id: "status",		type: TYPE_STRING, 	"align" : ALIGN_RIGHT},
				{ text: WUILang.plgAuthor,			width: "80px", 	id: "author",		type: TYPE_STRING },
				{ text: WUILang.plgDescr,			width: "500px",	id: "descr",		type: TYPE_STRING }
			],
			container:	"PluginList",
			format:		theFormatter.plugins
		}
	},
	settings:
	{
		"webui.fls.view":		0, 
		"webui.show_cats":		1, 
		"webui.show_dets":		1, 
		"webui.needmessage":		0, 
		"webui.reqtimeout":		60000,
		"webui.confirm_when_deleting":	1,
		"webui.alternate_color":	0,
		"webui.speed_display":		0,
		"webui.update_interval":	3000,
		"webui.hsplit":			0.88,
		"webui.vsplit":			0.5,
		"webui.effects":		0,
		"webui.minrows":		100
	},
	showFlags: 0,
	total:
	{
		rateDL: 	0,
		rateUL: 	0,
		speedDL: 	0,
		speedUL: 	0,
		DL: 		0,
		UL: 		0
	},
	sTimer: 	null,
	updTimer: 	null,
	configured:	false,
	firstLoad:	true,
	interval:	-1,
	torrents:	{},
	files:		{},
	dirs:		{},
	trackers:	{},
	props:		{},
	peers:		{},
	labels:
	{
		"-_-_-all-_-_-":	0,
		"-_-_-dls-_-_-":	0,
		"-_-_-com-_-_-":	0,
		"-_-_-act-_-_-":	0,
		"-_-_-iac-_-_-":	0,
		"-_-_-nlb-_-_-":	0,
		"-_-_-err-_-_-":	0
	},
	actLbl:		"-_-_-all-_-_-",
	cLabels:	{},
	dID:		"",
	pID:		"",
	speedGraph:	new rSpeedGraph(),
	url:		window.location.href.substr(0,window.location.href.lastIndexOf("/")+1),
	timer:		new Timer(),
	activeView:	null,
	delmode:	"remove",

//
// init
//

	init: function()
	{
       		log("WebUI started.");
		this.setStatusUpdate();
		if(browser.isOldIE)
			this.msg(WUILang.Doesnt_support);
		else
		{
			this.catchErrors(false);
			this.assignEvents();
			this.getPlugins();
   			this.getUISettings();
			if(this.configured)
			{
			        this.catchErrors(true);
				this.resize();
				this.update();
			}
			else
				this.msg(WUILang.PHPDoesnt_enabled);
		}
		return(this.configured);
	},

	assignEvents: function()
	{
		window.onresize = theWebUI.resize;
		$(document).bind("dragstart",function(e) { return(false); } );
		$(document).bind("selectstart",function(e) { return(e.fromTextCtrl); });
		$(document).bind("contextmenu",function(e)
		{
			if(e.fromTextCtrl)
				theContextMenu.hide();
			else
				return(false);
		});
		$(document).keydown(function(e)
		{
			switch(e.which) 
			{
		   		case 27 : 				// Esc
		   		{
		   			if(theContextMenu.hide() || theDialogManager.hideTopmost())
						return(false);
		   			break;
		   		}
		   		case 79 : 				// ^O
   				{
					if(e.ctrlKey && !theDialogManager.isModalState()) 
   					{	
      						theWebUI.showAdd();	
						return(false);
      					}
		   			break;
				}
				case 80 :                               // ^P
				{
					if(e.ctrlKey && !theDialogManager.isModalState())
					{	
      						theWebUI.showSettings();	
						return(false);
      					}
		   			break;
				}
		  		case 112:				// F1
   				{
   				        if(!theDialogManager.isModalState())
   				        {
			   		        theDialogManager.show(e.ctrlKey ? "dlgAbout" : "dlgHelp");
						return(false);
					}
		   		}
				case 115 : 				// F4
				{
					theWebUI.toggleMenu();
					return(false);
				}
				case 117 :                      	// F6
				{
					theWebUI.toggleDetails();
					return(false);
				}
				case 118 :                      	// F7
				{
					theWebUI.toggleCategories();
					return(false);
				}
			}
		});
	},

	getPlugins: function()
	{
		this.request("?action=getplugins", null, false);
		if(thePlugins.isInstalled("_getdir"))
		{
			$('#dir_edit').after($("<input type=button>").addClass("Button").width(30).attr("id","dir_btn").focus( function() { this.blur(); } ));
			var btn = new this.rDirBrowser( 'tadd', 'dir_edit', 'dir_btn' );
			theDialogManager.setHandler('tadd','afterHide',function()
			{
				btn.hide();
			});
		}
		correctContent();
	},

	getUISettings: function()
	{
		this.request("?action=getuisettings", [this.config, this], false);
	},

	config: function(data)
	{
		this.addSettings(data);
		$.each(this.tables, function(ndx,table)
		{
		        var width = theWebUI.settings["webui."+ndx+".colwidth"];
		        var enabled = theWebUI.settings["webui."+ndx+".colenabled"];
			$.each(table.columns, function(i,col)
			{
				if(width && i<width.length)
					col.width = width[i];
				if(enabled && i<enabled.length)
					col.enabled = enabled[i];
			});
			table.obj.format = table.format;
			table.obj.onresize = theWebUI.save;
			table.obj.oncoltoggled = theWebUI.save;
			table.obj.onmove = theWebUI.save;
			table.obj.ondblclick = table.ondblclick;
			table.obj.onselect = table.onselect;
			table.obj.ondelete = table.ondelete;
			table.obj.colorEvenRows = theWebUI.settings["webui.alternate_color"];
			table.obj.maxRows = theWebUI.settings["webui.minrows"];
			if($type(theWebUI.settings["webui."+ndx+".sindex"]))
				table.obj.sIndex = theWebUI.settings["webui."+ndx+".sindex"];
			if($type(theWebUI.settings["webui."+ndx+".rev"]))
				table.obj.reverse = theWebUI.settings["webui."+ndx+".rev"];
			if($type(theWebUI.settings["webui."+ndx+".colorder"]))
				table.obj.colOrder = theWebUI.settings["webui."+ndx+".colorder"];
			table.obj.onsort = function()
			{
   				if( ((this.sIndex != theWebUI.settings["webui."+this.prefix+".sindex"]) || 
		   			(this.reverse != theWebUI.settings["webui."+this.prefix+".rev"])) ) 
		      			theWebUI.save();
			}
		});
		var table = this.getTable("fls");
		table.oldFilesSortAlphaNumeric = table.sortAlphaNumeric;
		table.sortAlphaNumeric = function(x, y) 
		{
			if(!theWebUI.settings["webui.fls.view"] && theWebUI.dID)
			{
			        var dir = theWebUI.dirs[theWebUI.dID];
			        var a = dir.dirs[dir.current][x.key];
			        var b = dir.dirs[dir.current][y.key];
		        	if((a.data[0]=="..") ||
				   ((a.link!=null) && (b.link==null)))
					return(this.reverse ? 1 : -1);
				if((b.data[0]=="..") ||
				   ((b.link!=null) && (a.link==null)))
					return(this.reverse ? -1 : 1);
			}
			return(this.oldFilesSortAlphaNumeric(x,y));
		}
		table.oldFilesSortNumeric = table.sortNumeric;
		table.sortNumeric = function(x, y)
		{
			if(!theWebUI.settings["webui.fls.view"] && theWebUI.dID)
			{
			        var dir = theWebUI.dirs[theWebUI.dID];
			        var a = dir.dirs[dir.current][x.key];
			        var b = dir.dirs[dir.current][y.key];
		        	if((a.data[0]=="..") ||
				   ((a.link!=null) && (b.link==null)))
					return(this.reverse ? 1 : -1);
				if((b.data[0]=="..") ||
				   ((b.link!=null) && (a.link==null)))
					return(this.reverse ? -1 : 1);
			}
			return(this.oldFilesSortNumeric(x,y));
		}
		this.speedGraph.create($("#Speed"));
		if(!this.settings["webui.show_cats"])
			$("#CatList").hide();
		if(!this.settings["webui.show_dets"])
			$("#tdetails").hide();
		theDialogManager.setEffects( iv(this.settings["webui.effects"])*200 );
		this.setStatusUpdate();
		$.each(this.tables, function(ndx,table)
		{
			table.obj.create($$(table.container), table.columns, ndx);
		});
		this.configured = true;
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
					status: plugin.enabled ? 1 : 0
				}, "_plg_"+plugin.name);
			});
//			if(table.sIndex !=- 1)
//				table.Sort();
//			table.calcSize().resizeHack();
		}
	},

	setStatusUpdate: function()
	{
        	window.status = "";
		window.defaultStatus = "";
		document.title = "ruTorrent v" + this.version;
		if(this.sTimer)
		{
			window.clearInterval(this.sTimer);
			this.sTimer = null;
		}
		if(this.settings["webui.speed_display"])
			this.sTimer = window.setInterval(this.updateStatus, 1000);
	},

//
// settings
//

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
					o.attr('checked',v);
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
      				if($type(o.attr("onchange"))=="function")
        	 			o.get(0).onchange.apply(o.get(0));
			}
		});
   	},

	setSettings: function() 
	{
	        var req = '';
	        var needSave = false;
		var needResize = false;
		var reply = null;
		$.each(this.settings, function(i,v)
		{
		        var o = $$(i);
			if(o)
			{
				o = $(o);
				var nv = o.is("input:checkbox") ? (o.attr('checked') ? 1 : 0) : o.val();
				switch(i)
				{
				        case "max_memory_usage":
						nv *= 1024;
					case "upload_rate":
					case "download_rate":
						nv *= 1024;
				}
				if(nv!=v)
				{
					if((/^webui\./).test(i))
					{
						needSave = true;
						switch(i)
						{
						        case "webui.effects":
						        {
								theDialogManager.setEffects( iv(nv)*200 );
								break;
							}
							case "webui.speed_display":
							{
								theWebUI.settings[i] = nv;
								theWebUI.setStatusUpdate();
								break;
							}
							case "webui.alternate_color":
							{
								$.each(theWebUI.tables, function(ndx,table)	
								{
							  		table.obj.colorEvenRows = nv;
						     			table.obj.refreshSelection();
								});
								break;
							}
							case "webui.show_cats":
							{
								$("#CatList").toggle();
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
							case "webui.minrows":
							{
								$.each(theWebUI.tables, function(ndx,table)	
								{
						      			table.obj.maxRows = nv;
						      			table.obj.refreshRows();
								});
								break;
							}
						}
					}
					else
					{
						var k_type = o.is("input:checkbox") || o.is("select") || o.hasClass("num") ? "n" : "s";
						req+=("&s="+k_type+i+"&v="+nv);
					}
					theWebUI.settings[i] = nv;
				}
			}
		});
		if(needResize)
			this.resize();
		if(req.length>0)
	      		this.request("?action=setsettings" + req,null,true);
		if(needSave)
			this.save(reply);
   	},

   	reload: function()
   	{
		window.location.reload( true );
   	},

   	showSettings: function() 
	{
		if($("#stg").is(":visible"))
			theDialogManager.hide("stg");
		else
	   		this.request("?action=getsettings", [this.addAndShowSettings, this], true);
   	},

	addAndShowSettings: function(data) 
	{
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
			theWebUI.settings["webui."+ndx+".sindex"] = table.obj.sIndex;
			theWebUI.settings["webui."+ndx+".rev"] = table.obj.reverse;
		});
	        var cookie = {};
	        $.each(theWebUI.settings, function(i,v)
		{
			if((/^webui\./).test(i))
				cookie[i] = v;
		});

		var json_encode = function(obj)
		{
			switch($type(obj))
			{
				case "number":
					return(String(obj));
				case "boolean":
					return(obj ? "1" : "0");
				case "string":
					return('"'+obj+'"');
				case "array":
				{
				        var s = '';
				        $.each(obj,function(key,item)
				        {
				                if(s.length)
		                			s+=",";
				        	s += json_encode(item);
				        });
					return("["+s+"]");
				}
				case "object":
				{
				        var s = '';
				        $.each(obj,function(key,item)
				        {
				                if(s.length)
		                			s+=",";
				        	s += ('"'+key+'":'+json_encode(item));
				        });
					return("{"+s+"}");
				}
			}
			return("null");
		}
		theWebUI.request("?action=setuisettings&v=" + json_encode(cookie),reply);
	},

//
// peers
//

	updatePeers: function()
	{
		if(this.activeView=='PeerList')
		{
			if(this.dID != "") 
				this.request("?action=getpeers&hash="+this.dID,[this.addPeers, this]);
			else
				this.clearPeers();
		}
	},

	clearPeers: function()
	{
		this.getTable("prs").clearRows();
		for(var k in this.peers) 
      			delete this.peers[k];
	},

	addPeers: function(data) 
	{
   		var table = this.getTable("prs");
   		$.extend(this.peers,data);
   		$.each(data,function(id,peer)
		{
			if(!$type(table.rowdata[id])) 
				table.addRowById(peer, id, peer.icon, peer.attr);
        		else 
			{
				for(var i in peer) 
        	       			table.setValueById(id, i, peer[i]);
				table.setIcon(id,peer.icon);
				table.setAttr(id,peer.attr);
			}
			peer._updated = true;
		});
		for(var k in this.peers) 
		{
			if(!this.peers[k]._updated)
			{
        			delete this.peers[k];
	        	 	table.removeRow(k);
	        	}
			else
				this.peers[k]._updated = false;
		}
//		if(table.sIndex !=- 1)
			table.Sort();
//		else
//			table.resizeHack();
	},

	prsSelect: function(e, id) 
	{
   	},

//
// trackers
//

   	trkSelect: function(e, id) 
	{
		if($type(id))
		{
	   		var ind = iv(id.substr(43));
   			if(theWebUI.createTrackerMenu(e, ind))
		   		theContextMenu.show();
		}
   	},

   	getTrackers: function(hash) 
	{
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
   		var table = this.getTable("trk");
   		$.extend(this.trackers,data);
		$.each(data,function(hash,trk)
		{
			if(theWebUI.dID == hash)
			{
				for(var i = 0; i < trk.length; i++)
				{
					var sId = hash + "_t_" + i;
        	 			if(!$type(table.rowdata[sId]) )
            					table.addRowById(trk[i], sId, trk[i].icon, trk[i].attr);
        	 			else 
         				{
            					for(var j in trk[i]) 
        	       					table.setValueById(sId, j, trk[i][j]);
						table.setIcon(sId,trk[i].icon);
						table.setAttr(sId,trk[i].attr);
	            			}
					trk[i]._updated = true;
        	 		}
        	 		return(false);
			}
	   	});
	   	var rowIDs = table.rowIDs.slice(0);
		for(var i in rowIDs) 
		{
		        var hash = rowIDs[i].substr(0, 40);
			if(this.dID != hash) 
         			table.removeRow(rowIDs[i]);
         		else
         		{
         			var no = rowIDs[i].substr(43);
				if(!$type(this.trackers[hash][no]))
	         			table.removeRow(rowIDs[i]);
				else
         			if(!this.trackers[hash][no]._updated)
         			{
	         			table.removeRow(rowIDs[i]);
	         			delete this.trackers[hash][no];
				}
         			else
	         			this.trackers[hash][no]._updated = false;
			}
      		}
//		if(table.sIndex !=- 1)
			table.Sort();
//		else
//			table.resizeHack();
      		this.updateDetails();
   	},

   	createTrackerMenu : function(e, ind)
	{
   		if(e.button != 2) 
      			return(false);
   		theContextMenu.clear();
   		if(this.getTable("trk").selCount > 1) 
   		{
      			theContextMenu.add([WUILang.EnableTracker, "theWebUI.setTrackerState('" + this.dID + "',1)"]);
      			theContextMenu.add([WUILang.DisableTracker, "theWebUI.setTrackerState('" + this.dID + "',0)"]);
      		}
   		else 
   		{
      			if(this.trackers[this.dID][ind].enabled == 0) 
      			{
      				theContextMenu.add([WUILang.EnableTracker, "theWebUI.setTrackerState('" + this.dID + "',1)"]);
	      			theContextMenu.add([WUILang.DisableTracker]);
         		}
      			else 
      			{
	      			theContextMenu.add([WUILang.EnableTracker]);
      				theContextMenu.add([WUILang.DisableTracker, "theWebUI.setTrackerState('" + this.dID + "',0)"]);
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
   		var needSort = false;
   		for(var k in sr) 
   		{
      			if(sr[k]) 
      			{
         			var i = iv(k.substr(43));
         			if(this.trackers[id][i].enabled != swtch) 
         			{
            				str += "&f=" + i;
            				this.trackers[id][i].enabled = swtch;
            				needSort = true;
            				table.setValueById(id + "_t_" + i, "enabled", swtch);
            			}
         		}
      		}
      		if(needSort)
			table.Sort();
   		return(str);
   	},

//
// files
//

	updateFiles: function(hash) 
	{
		if((this.dID == hash) && $type(this.files[hash]))
		{
      			this.getFiles(hash, true);
      			this.updateDetails();
      		}
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
					if(!$type(table.rowdata[sId])) 
        	          			table.addRowById(file, sId, file.icon, file.attr);
            				else
            				{
	            				for(var j in file) 
               						table.setValueById(sId, j, file[j]);
						table.setIcon(sId,file.icon);
						table.setAttr(sId,file.attr);
					}
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
					{
						if(!$type(table.rowdata[i])) 
						        table.addRowById(entry.data, i, entry.icon, {link : entry.link});
						else
	            					for(var j in entry.data) 
               							table.setValueById(i, j, entry.data[j]);
					}
				}
				for(var i in dir) 
				{
					var entry = dir[i];
					if(entry.link==null)
					{
						if(!$type(table.rowdata[i])) 
						        table.addRowById(entry.data, i, entry.icon, {link : null});
						else
	            					for(var j in entry.data) 
               							table.setValueById(i, j, entry.data[j]);
					}
				}
			}
//     			table.calcSize().resizeHack();
       	 	}
	},

	getFiles: function(hash, isUpdate) 
	{
		var table = this.getTable("fls");
   		if(!isUpdate)
   		{
      			table.dBody.scrollTop = 0;
      			$(table.tpad).height(0);
      			$(table.bpad).height(0);
      		}
   		if($type(this.files[hash]) && !isUpdate) 
   		{
      			table.clearRows();
      			this.redrawFiles(hash);
      		}
   		else 
   		{
      			if(!isUpdate) 
         			table.clearRows();
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
	   		var p = -1;
	   		if(theWebUI.settings["webui.fls.view"])
		   		p = theWebUI.files[theWebUI.dID][id.substr(43)].priority;
			else
				p = theWebUI.dirs[theWebUI.dID].getEntryPriority(id);
   			if(theWebUI.createFileMenu(e, p))
				theContextMenu.show();
		}
   	},

	createFileMenu: function(e, p) 
	{
   		if(e.button != 2) 
      			return(false);
   		var id = this.dID;
   		theContextMenu.clear();
		var _bf = [];
		if(this.getTable("fls").selCount > 1) 
   		{
      			_bf.push([WUILang.High_priority, "theWebUI.setPriority('" + id + "',2)"]);
			_bf.push([WUILang.Normal_priority, "theWebUI.setPriority('" + id + "',1)"]);
			_bf.push([CMENU_SEP]);
			_bf.push([WUILang.Dont_download, "theWebUI.setPriority('" + id + "',0)"]);
		}
   		else 
	   		if(p!=null)
   			{
   			       	_bf.push([WUILang.High_priority, (p == 2) ? null : "theWebUI.setPriority('" + id + "',2)"]);
	   	        	_bf.push([WUILang.Normal_priority, (p == 1) ? null : "theWebUI.setPriority('" + id + "',1)"]);
	   		        _bf.push([CMENU_SEP]);
   			        _bf.push([WUILang.Dont_download, (p == 0) ? null : "theWebUI.setPriority('" + id + "',0)"]);
	      		}
	      	if(_bf.length)
	      		theContextMenu.add([CMENU_CHILD, WUILang.Priority, _bf]);
		var _bf1 = [];
		if(this.settings["webui.fls.view"])
		{
			_bf1.push([WUILang.AsList]);
			_bf1.push([WUILang.AsTree, "theWebUI.toggleFileView()"]);
		}
		else
		{
			_bf1.push([WUILang.AsList, "theWebUI.toggleFileView()"]);
			_bf1.push([WUILang.AsTree]);
		}
		theContextMenu.add([CMENU_CHILD, WUILang.View, _bf1]);
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

	getFileIds: function(id, p) 
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
	         			var i = iv(k.substr(43));
        	 			if(this.files[id][i].priority != p) 
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
   		var fls = this.getFileIds(id, p);
   		this.request("?action=setprio&hash=" + id + "&p=" + p + fls, [this.updateFiles, this]);
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
   		if(e.button == 2) 
   		{
      			theWebUI.createMenu(e, id);
			theContextMenu.show(e.clientX,e.clientY);
      		}
   	},

   	createMenu: function(e, id) 
	{
   		var status = this.torrents[id].state;
   		var table = this.getTable("trt");
   		theContextMenu.clear();
   		if(table.selCount > 1) 
   		{
      			theContextMenu.add([WUILang.Start, "theWebUI.start()"]);
      			theContextMenu.add([WUILang.Pause, "theWebUI.pause()"]);
      			theContextMenu.add([WUILang.Stop, "theWebUI.stop()"]);
      			theContextMenu.add([WUILang.Force_recheck, "theWebUI.recheck()"]);
   		}
   		else 
   		{
   			if(this.isCommandEnabled("start",status))
	   			theContextMenu.add([WUILang.Start, "theWebUI.start()"]);
			else
				theContextMenu.add([WUILang.Start]);
   			if(this.isCommandEnabled("pause",status) || this.isCommandEnabled("unpause",status))
	   			theContextMenu.add([WUILang.Pause, "theWebUI.pause()"]);
			else
				theContextMenu.add([WUILang.Pause]);
   			if(this.isCommandEnabled("stop",status))
	   			theContextMenu.add([WUILang.Stop, "theWebUI.stop()"]);
			else
				theContextMenu.add([WUILang.Stop]);
			if(this.isCommandEnabled("recheck",status))
				theContextMenu.add([WUILang.Force_recheck, "theWebUI.recheck()"]);
			else
				theContextMenu.add([WUILang.Force_recheck]);
		}
   		theContextMenu.add([CMENU_SEP]);
   		var _bf = [];
   		for(var lbl in this.cLabels) 
   		{
      			if((table.selCount == 1) && (this.torrents[id].label == lbl)) 
         			_bf.push([CMENU_SEL, lbl]);
      			else 
         			_bf.push([lbl, "theWebUI.setLabel('" + addslashes(lbl) + "')"]);
      		}
      		if(_bf.length>0)
	   		_bf.push([CMENU_SEP]);
   		_bf.push([WUILang.New_label, "theWebUI.newLabel()"]);
   		_bf.push([WUILang.Remove_label, "theWebUI.removeLabel()"]);
   		ContextMenu.add([CMENU_CHILD, WUILang.Labels, _bf]);
   		ContextMenu.add([CMENU_SEP]);
   		var _c0 = [];
		if(table.selCount > 1) 
		{
			_c0.push([WUILang.High_priority, "theWebUI.perform('dsetprio&v=3')"]);
			_c0.push([WUILang.Normal_priority, "theWebUI.perform('dsetprio&v=2')"]);
			_c0.push([WUILang.Low_priority,  "theWebUI.perform('dsetprio&v=1')"]);
			_c0.push([WUILang.Dont_download,  "theWebUI.perform('dsetprio&v=0')"]);
		}
		else
		{
			var p = this.torrents[id].priority;
			_c0.push([WUILang.High_priority, (p==3) ? null : "theWebUI.perform('dsetprio&v=3')"]);
			_c0.push([WUILang.Normal_priority, (p==2) ? null : "theWebUI.perform('dsetprio&v=2')"]);
			_c0.push([WUILang.Low_priority,  (p==1) ? null : "theWebUI.perform('dsetprio&v=1')"]);
			_c0.push([WUILang.Dont_download, (p==0) ? null : "theWebUI.perform('dsetprio&v=0')"]);
		}
		theContextMenu.add([CMENU_CHILD, WUILang.Priority, _c0]);
   		theContextMenu.add([CMENU_SEP]);
   		theContextMenu.add([WUILang.Remove, "theWebUI.remove()"]);
   		theContextMenu.add([CMENU_SEP]);
   		theContextMenu.add([WUILang.Details, "theWebUI.showDetails('" + id + "')"]);
   		if(table.selCount > 1) 
      			theContextMenu.add([WUILang.Properties]);
   		else 
      			theContextMenu.add([WUILang.Properties, "theWebUI.showProperties('" + id + "')"]);
   	},

   	perform: function(cmd) 
	{
		if(cmd == "pause") 
		{
			var hp = this.getHashes("unpause");
			if(hp != "") 
				this.request("?action=unpause" + hp);
		}
		var h = this.getHashes(cmd);
		if(h != "") 
		{
			if((cmd.indexOf("remove")==0) && (h.indexOf(this.dID) >- 1)) 
			{
				this.dID = "";
				this.clearDetails();
			}
			this.getTorrents(cmd + h + "&list=1");
		}
   	},

	isCommandEnabled: function(act,status) 
	{
		var ret = true;
		switch(act) 
		{
			case "start" : 
			{
				ret = (!(status & dStatus.started) || (status & dStatus.paused) && !(status & dStatus.checking) && !(status & dStatus.hashing));
				break;
			}
			case "pause" : 
			{
				ret = ((status & dStatus.started) && !(status & dStatus.paused) && !(status & dStatus.checking) && !(status & dStatus.hashing));
				break;
			}
			case "unpause" : 
			{
				ret = ((status & dStatus.paused) && !(status & dStatus.checking) && !(status & dStatus.hashing));
				break;
			}
			case "stop" : 
			{
				ret = ((status & dStatus.started) || (status & dStatus.hashing) || (status & dStatus.checking));
				break;
			}
			case "recheck" : 
			{
				ret = !(status & dStatus.checking) && !(status & dStatus.hashing);
				break;
			}
		}
		return(ret);
	},

	getHashes: function(act) 
	{
		var h = "";
		var sr = this.getTable("trt").rowSel;
		for(var k in sr) 
			if((sr[k] == true) && this.isCommandEnabled(act,this.torrents[k].state))
				h += "&hash=" + k;
		return(h);
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

	remove: function()
	{
		if(this.getTable("trt").selCount>0)
		{
			if(this.settings["webui.confirm_when_deleting"])
			{
				this.delmode = "remove";
				askYesNo( WUILang.Remove_torrents, WUILang.Rem_torrents_prompt, "theWebUI.doRemove()" );
	      		}
			else
				this.perform("remove");
		}
	},

	doRemove: function()
	{	
		this.perform(this.delmode);
   	},

	recheck: function()
	{	
		this.perform("recheck");
   	},

	getTorrents: function(qs)
	{	
		if(this.updTimer)
			window.clearTimeout(this.updTimer);
		this.timer.start();
		if(qs != "list=1")
			qs = "action=" + qs;
		this.requestWithTimeout("?" + qs + "&getmsg=1", [this.addTorrents, this], function() 
		{	 
	   		theWebUI.timeout(); 
			theWebUI.update();
	   	});
   	},

	fillAdditionalTorrentsCols: function(hash,cols)
	{
		return(cols);
	},

	updateAdditionalTorrentsCols: function(hash)
	{
	},

	addTorrents: function(data) 
	{
   		var needSort = false;
   		var table = this.getTable("trt");
   		var tul = 0;
		var tdl = 0;
		var tArray = [];
		$.each(data.torrents,function(hash,torrent)
		{
			tdl += torrent.dl;
			tul += torrent.ul;
			var sInfo = theWebUI.getStatusIcon(torrent.state, torrent.done);
			torrent.status = sInfo[1];
			var lbl = theWebUI.getLabels(hash, torrent.label, torrent.done, torrent.dl, torrent.ul, torrent.state);
			if(!$type(theWebUI.torrents[hash]))
			{
				theWebUI.labels[hash] = lbl;
				table.addRowById(torrent, hash, sInfo[0], {label : lbl});
				needSort = true;
				tArray.push(hash);
			}
			else
			{
				var oldTorrent = theWebUI.torrents[hash];
				if(lbl != theWebUI.labels[hash]) 
				{
					theWebUI.labels[hash] = lbl;
					table.setAttr(hash, { label: lbl });
					theWebUI.filterByLabel(hash);
				}
				if((oldTorrent.state!=torrent.state) ||
					(oldTorrent.size!=torrent.size) ||
					(oldTorrent.done!=torrent.done))
					table.setIcon(hash, sInfo[0]);
				if(theWebUI.dID == hash)
				{
					if((oldTorrent.seeds!=torrent.seeds) || (oldTorrent.peers!=torrent.peers))
						theWebUI.getTrackers(hash);
					if(oldTorrent.downloaded!=torrent.downloaded)
						theWebUI.updateFiles(hash);
				}
				for( var prop in torrent)
				        if(table.setValueById(hash, prop, torrent[prop]))
				        	needSort = true;
			}
			torrent._updated = true;
		});

		$.extend(this.torrents,data.torrents);

		theWebUI.speedGraph.addData(tul,tdl);
		this.total.speedDL = tdl;
		this.total.speedUL = tul;
		var wasRemoved = false;

		$.each(this.torrents,function(hash,torrent)
		{
			if(!torrent._updated)
			{
        			delete theWebUI.torrents[hash];
				if(theWebUI.labels[hash].indexOf("-_-_-nlb-_-_-") >- 1) 
					theWebUI.labels["-_-_-nlb-_-_-"]--;
	        	 	if(theWebUI.labels[hash].indexOf("-_-_-com-_-_-") >- 1) 
            				theWebUI.labels["-_-_-com-_-_-"]--;
	        	 	if(theWebUI.labels[hash].indexOf("-_-_-dls-_-_-") >- 1) 
	        	    		theWebUI.labels["-_-_-dls-_-_-"]--;
	         		if(theWebUI.labels[hash].indexOf("-_-_-act-_-_-") >- 1) 
        		    		theWebUI.labels["-_-_-act-_-_-"]--;
		         	if(theWebUI.labels[hash].indexOf("-_-_-iac-_-_-") >- 1) 
            				theWebUI.labels["-_-_-iac-_-_-"]--;
		         	if(theWebUI.labels[hash].indexOf("-_-_-err-_-_-") >- 1) 
            				theWebUI.labels["-_-_-err-_-_-"]--;
		         	theWebUI.labels["-_-_-all-_-_-"]--;
        		 	delete theWebUI.labels[hash];
	        	 	table.removeRow(hash);
	        	 	wasRemoved = true;
			}
			else
				torrent._updated = false;
		});
		this.getAllTrackers(tArray);
		this.loadLabels(data.labels);
		this.updateLabels(wasRemoved);
		this.loadTorrents(needSort);
		this.request("?action=gettotal",[this.getTotal, this]);
	},

	loadTorrents: function(needSort) 
	{
		var table = this.getTable("trt");
		if(this.firstLoad) 
		{
			this.firstLoad = false;
//			table.calcSize().resizeHack();
			this.show();
		}
		else 
		{
			if(this.actLbl != "-_-_-all-_-_-") 
				table.refreshRows();
      		}
		if(needSort) 
			table.Sort();
		this.setInterval();
		this.updateDetails();
   	},

   	getTotal: function( d )
	{
	        $.extend(this.total,d);
	},

	getStatusIcon: function(state, completed) 
	{
		var icon = "", status = "";
		if(state & dStatus.checking)
		{
			icon = "Status_Checking";
			status = WUILang.Checking;
		}
		else
		if(state & dStatus.hashing)
		{
			icon = "Status_Queued_Up";
			status = WUILang.Queued;
		}
		else
		{
			if(state & dStatus.started)
			{
				if(state & dStatus.paused)
				{       	
					icon = "Status_Paused";
					status = WUILang.Pausing;
				}
				else
				{
					icon = (completed == 1000) ? "Status_Up" : "Status_Down";
					status = (completed == 1000) ? WUILang.Seeding : WUILang.Downloading;
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
			status = WUILang.Finished;
		}
		if((completed < 1000) && (status == "")) 
		{
			if(icon=="")
				icon = "Status_Incompleted";
			status = WUILang.Stopped;
		}
		return([icon, status]);
	},

//
// labels
//

	labelContextMenu: function(e)
	{
	        if(e.button==2)
	        {
		        var table = theWebUI.getTable("trt");
			table.clearSelection();
			theWebUI.switchLabel(this);
			table.fillSelection();
			var id = table.getFirstSelected();
			if(id)
			{
				theWebUI.createMenu(null, id);
				theContextMenu.show(e.clientX,e.clientY);
			}
			else
				theContextMenu.hide();
		}
		else
			theWebUI.switchLabel(this);
		return(false);
	},

	loadLabels: function(d) 
	{
		var p = $("#lbll");
		var temp = new Array();
		for(var lbl in d) 
		{
			this.labels["-_-_-" + lbl + "-_-_-"] = d[lbl];
			this.cLabels[lbl] = 1;
			temp["-_-_-" + lbl + "-_-_-"] = true;
			if(!$$("-_-_-" + lbl + "-_-_-")) 
			{
				p.append( $("<LI>").
					attr("id","-_-_-" + lbl + "-_-_-").
					html(escapeHTML(lbl) + "&nbsp;(<span id=\"-_-_-" + lbl + "-_-_-c\">" + d[lbl] + "</span>)").
					mouseclick(theWebUI.labelContextMenu).addClass("cat") );
			}
		}
		var actDeleted = false;
		p.children().each(function(ndx,val)
		{
		        var id = val.id;
			if(id && !$type(temp[id]))
			{
				$(val).remove();
				delete theWebUI.labels[id];
				delete theWebUI.cLabels[id.substr(5, id.length - 10)];
				if(theWebUI.actLbl == id) 
					actDeleted = true;
			}
		});
		if(actDeleted) 
		{
			this.actLbl = "";
			this.switchLabel($$("-_-_-all-_-_-"));
		}
   	},

	getLabels : function(id, lbl, completed, dls, uls, status)
	{
		if(!$type(this.labels[id]))
			this.labels[id] = "";
		if(lbl == "")
      		{
			lbl += "-_-_-nlb-_-_-";
			if(this.labels[id].indexOf("-_-_-nlb-_-_-") ==- 1)
				this.labels["-_-_-nlb-_-_-"]++;
		}
		else
			if(this.labels[id].indexOf("-_-_-nlb-_-_-") >- 1)
				this.labels["-_-_-nlb-_-_-"]--;
		lbl = "-_-_-" + lbl + "-_-_-";
		if(completed < 1000)
      		{
			lbl += "-_-_-dls-_-_-";
			if(this.labels[id].indexOf("-_-_-dls-_-_-") ==- 1)
				this.labels["-_-_-dls-_-_-"]++;
			if(this.labels[id].indexOf("-_-_-com-_-_-") >- 1)
				this.labels["-_-_-com-_-_-"]--;
		}
		else
      		{
			lbl += "-_-_-com-_-_-";
			if(this.labels[id].indexOf("-_-_-com-_-_-") ==- 1)
				this.labels["-_-_-com-_-_-"]++;
			if(this.labels[id].indexOf("-_-_-dls-_-_-") >- 1)
				this.labels["-_-_-dls-_-_-"]--;
         	}
		if((dls >= 1024) || (uls >= 1024))
		{
			lbl += "-_-_-act-_-_-";
			if(this.labels[id].indexOf("-_-_-act-_-_-") ==- 1)
				this.labels["-_-_-act-_-_-"]++;
			if(this.labels[id].indexOf("-_-_-iac-_-_-") >- 1)
				this.labels["-_-_-iac-_-_-"]--;
		}
		else
		{
			lbl += "-_-_-iac-_-_-";
			if(this.labels[id].indexOf("-_-_-iac-_-_-") ==- 1)
				this.labels["-_-_-iac-_-_-"]++;
			if(this.labels[id].indexOf("-_-_-act-_-_-") >- 1)
				this.labels["-_-_-act-_-_-"]--;
		}
		if(status & dStatus.error)
		{
			lbl += "-_-_-err-_-_-";
			if(this.labels[id].indexOf("-_-_-err-_-_-") ==- 1)
				this.labels["-_-_-err-_-_-"]++;
		}
		else
  			if(this.labels[id].indexOf("-_-_-err-_-_-") >- 1)
				this.labels["-_-_-err-_-_-"]--;
		lbl += "-_-_-all-_-_-";
		if(this.labels[id] == "")
			this.labels["-_-_-all-_-_-"]++;
		return(lbl);
	},

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
		var s = WUILang.newLabel;
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
   		var lbl = $.trim($("#txtLabel").val());
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

	updateLabels: function(wasRemoved)
	{
		for(var k in this.labels)
			if(k.substr(0, 5) == "-_-_-")
				$($$(k+"c")).text(this.labels[k]);
	},

	switchLabel: function(obj)
	{
		if(obj.id != this.actLbl)
		{
			if((this.actLbl != "") && $$(this.actLbl))
				$$(this.actLbl).className = "";
			$(obj).addClass("sel");
			this.actLbl = obj.id;
			var table = this.getTable("trt");
			for(var k in this.torrents)
				if(table.getAttr(k, "label").indexOf(this.actLbl) >- 1)
					table.unhideRow(k);
				else
					table.hideRow(k);
			table.clearSelection();
			if(this.dID != "")
      			{
				this.dID = "";
				this.clearDetails();
      			}
   			table.refreshRows();
   		}
	},

	filterByLabel: function(sId)
	{
	        var table = this.getTable("trt");
		if(table.getAttr(sId, "label").indexOf(this.actLbl) >- 1)
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
   		        $("#prop-pex").attr("checked",false).attr("disabled",true);
			$("#lbl_prop-pex").addClass("disabled");
		}
   		else 
   		{
   			$("#prop-pex").attr("checked",props.pex).attr("disabled",false).removeClass("disabled");
			$("#lbl_prop-pex").removeClass("disabled");
		}
		o = $$("prop-superseed");
		if(this.torrents[this.pID].done==1000)
		{
		        $("#prop-superseed").attr("disabled",false);
      			$("#lbl_prop-superseed").removeClass("disabled");
		}
		else
		{
		        $("#prop-superseed").attr("disabled",true);
      			$("#lbl_prop-superseed").addClass("disabled");
     		}
     		$("#prop-superseed").attr("checked",props.superseed);
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
   		if(!noSwitch)
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
		this.clearPeers();
	},

	updateDetails: function() 
	{
   		if((this.dID != "") && this.torrents[this.dID])
   		{
	   		var d = this.torrents[this.dID];
                        $("#dl").text(theConverter.bytes(d.downloaded,2));
			$("#ul").text(theConverter.bytes(d.uploaded,2));
			$("#ra").html( (d.ratio ==- 1) ? "&#8734;" : theConverter.round(d.ratio/1000,3));
			$("#us").text(theConverter.speed(d.ul));
			$("#ds").text(theConverter.speed(d.dl));
			$("#rm").html((d.eta ==- 1) ? "&#8734;" : theConverter.time(d.eta));
			$("#se").text(d.seeds_actual + " " + WUILang.of + " " + d.seeds_all + " " + WUILang.connected);
			$("#pe").text(d.peers_actual + " " + WUILang.of + " " + d.peers_all + " " + WUILang.connected);
			$("#et").text(theConverter.time(Math.floor((new Date().getTime()-theWebUI.deltaTime)/1000-iv(d.state_changed)),true));
			$("#wa").text(theConverter.bytes(d.skip_total,2));
	        	$("#bf").text(d.base_path);
	        	$("#co").text(theConverter.date(d.created));
			$("#tu").text(	($type(this.trackers[this.dID]) && (d.tracker_focus<this.trackers[this.dID].length)) ? this.trackers[this.dID][d.tracker_focus].name : '');
	        	$("#hs").text(this.dID);
			$("#ts").text(d.msg);
			var url = $.trim(d.comment);
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
			$("#cmt").html(url);
			$("#dsk").text((d.free_diskspace=='0') ? '' : theConverter.bytes(d.free_diskspace,2));
	   		this.updatePeers();
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
		var s = WUILang.Download + ": " + theConverter.speed(self.total.speedDL);
		if(self.total.rateDL>0 && self.total.rateDL<100*1024*1024)
			s+="  ["+theConverter.speed(self.total.rateDL)+"]";
		s+="  " + WUILang.Total + ": " + theConverter.bytes(self.total.DL)+"  |  " + WUILang.Upload + ": " + theConverter.speed(self.total.speedUL);
		if(self.total.rateUL>0 && self.total.rateUL<100*1024*1024)
			s+="  ["+theConverter.speed(self.total.rateUL)+"]";
		s+="  " + WUILang.Total + ": " + theConverter.bytes(self.total.UL);
		if(self.settings["webui.speed_display"] == 1) 
		{
   			window.status = s;
	   		window.defaultStatus = s;
   		}
		else 
		{
   			if(self.settings["webui.speed_display"] == 2) 
   			{
   				s = "ruTorrent v" + self.version + " - " + s;
				if(document.title!=s)
      					document.title = s;
      			}
	   	}
	},

	resizeLeft: function( w, h )
	{
	        if(w!==null)
	        {
			$("#CatList").width( w );
			$("#VDivider").width( $(window).width()-w-5 );
		}
		if(h!==null)
			$("#CatList").height( h );
	},

	resizeTop : function( w, h )
	{
        	if(w!==null)
		{
			$("#List").width( w );
        	        this.getTable("trt").resize( w );
		}
	        if(h!==null)
        	{
			$("#List").height( h );
			this.getTable("trt").resize(null,h); 
        	}
	},

	resizeBottom : function( w, h )
	{
        	if(w!==null)
        	{
			$("#tdetails").width( w );
			w-=8;
		}
		if(h!==null)
        	{
			$("#tdetails").height( h );
			h-=($("#tabbar").height()+11);
			$("#tdcont").height( h );
			h-=2;
        	}
        	this.getTable("fls").resize(w,h);
		this.getTable("trk").resize(w,h);
		this.getTable("prs").resize(w,h);
		this.speedGraph.resize(w,h);
	},

	resize: function()
	{
		var ww = $(window).width();
		var wh = $(window).height();
	        var w = Math.floor(ww * (1 - theWebUI.settings["webui.hsplit"])) - 5;
	        var th = $("#t").is(":visible") ? $("#t").height()+14 : 13;
		if(theWebUI.settings["webui.show_cats"])
		{
			theWebUI.resizeLeft( w, wh-th );
			w = ww - w;
		}
		else
		{
			$("#VDivider").width( ww-w-5 );
			w = ww;
		}
		w-=11;
		theWebUI.resizeTop( w, Math.floor(wh * (theWebUI.settings["webui.show_dets"] ? theWebUI.settings["webui.vsplit"] : 1))-th+1 );
		if(theWebUI.settings["webui.show_dets"])
			theWebUI.resizeBottom( w, Math.floor(wh * (1 - theWebUI.settings["webui.vsplit"])) );
		$("#HDivider").height( wh-th+1 );
	},

	update: function()
   	{
		theWebUI.getTorrents("list=1");
   	},

	setVSplitter : function()
	{
		var r = 1 - ($("#tdetails").height() / $(window).height());
		r = Math.floor(r * Math.pow(10, 3)) / Math.pow(10, 3);
		if((theWebUI.settings["webui.vsplit"] != r) && (r>0) && (r<1)) 
		{
			theWebUI.settings["webui.vsplit"] = r;
			theWebUI.save();
		}
	},

	setHSplitter : function()
	{
		var r = 1 - ($("#CatList").width()+5)/$(window).width();
		r = Math.floor(r * Math.pow(10, 3)) / Math.pow(10, 3);
		if((theWebUI.settings["webui.hsplit"] != r) && (r>0) && (r<1))
		{
			theWebUI.settings["webui.hsplit"] = r;
			theWebUI.save();
		}
	},

	showRSS: function()
	{
		alert("RSS has not been implemented yet.");
	},

	toggleMenu: function()
	{
		$("#t").toggle();
  		theWebUI.resize();
	},

	toggleDetails: function()
	{
		theWebUI.settings["webui.show_dets"] = !theWebUI.settings["webui.show_dets"];
		$("#tdetails").toggle();
      		theWebUI.resize();
		theWebUI.save();
	},

	toggleCategories: function()
	{
	        theWebUI.settings["webui.show_cats"] = !theWebUI.settings["webui.show_cats"];
		$("#CatList").toggle();
      		theWebUI.resize();
		theWebUI.save();
	},

	togglePanel: function(pnl)
	{
		var cont = $('#'+pnl.id+"_cont");
		cont.toggle();
		if(cont.is(":visible"))
			pnl.style.backgroundImage="url("+this.getTable("trt").paletteURL+"/images/pnl_open.gif)";
		else
			pnl.style.backgroundImage="url("+this.getTable("trt").paletteURL+"/images/pnl_close.gif)";
	},

	showAdd: function() 
	{
   		theDialogManager.toggle("tadd");
   	},
	
	setInterval: function() 
	{
		this.timer.stop();
		if(this.interval ==- 1) 
			this.interval = this.settings["webui.update_interval"] + this.timer.interval * 4;
		else 
			this.interval = iv((this.interval + this.settings["webui.update_interval"] + this.timer.interval * 4) / 2);
		this.updTimer = window.setTimeout(this.update, this.interval);
   	},

   	setActiveView: function(id)
	{
		$("#tooltip").remove();
		this.activeView=id;
	},

	request: function(qs, onComplite, isASync) 
	{
		this.requestWithTimeout(qs, onComplite, this.timeout, isASync);
	},

	requestWithTimeout: function(qs, onComplite, onTimeout, isASync) 
	{
		new Ajax(this.url + qs, "GET", isASync, onComplite, onTimeout, this.error, this.settings["webui.reqtimeout"]);
   	},

   	show: function() 
   	{
   		if($("#cover").is(":visible"))
		{
			$("#cover").hide();
			theTabs.show("lcont");
			theWebUI.resize();
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
	   		window.onerror = function(msg, url, line) 
			{
			        theWebUI.show();
				log("JS error: [" + url + " : " + line + "] " + msg);
				return true;
			}
		else
			window.onerror = function(msg, url, line) 
			{
				msg = "JS error: [" + url + " : " + line + "] " + msg;
				theWebUI.msg(msg);
				log(msg);
				return true;
			}
   	},

	error: function(status,text) 
	{
		theWebUI.show();
		log("Bad response from server: ("+status+") "+(text ? text : ""));
	},

	timeout: function() 
	{
		theWebUI.show();
		log(WUILang.Request_timed_out);
	}
};

$(document).ready(function() 
{
	makeContent();
	theContextMenu.init();
	theTabs.init();
	theWebUI.init();
});
