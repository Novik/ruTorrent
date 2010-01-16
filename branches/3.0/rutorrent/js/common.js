/*
 *      Misc objects.
 *
 *	$Id$
 */

function $$(id)
{
	return((typeof id == 'string') ? document.getElementById(id) : id);
}

function $type(obj)
{
	return( (obj == undefined) ? false : (obj.constructor == Array) ? "array" : typeof obj );
}

function browserDetect()
{
	var ua = navigator.userAgent.toLowerCase();
	this.isGecko = (ua.indexOf("gecko") !=- 1 && ua.indexOf("safari") ==- 1);
	this.isAppleWebKit = (ua.indexOf("applewebkit") !=- 1);
	this.isKonqueror = (ua.indexOf("konqueror") !=- 1);
	this.isSafari = (ua.indexOf("safari") !=- 1);
	this.isOpera = (ua.indexOf("opera") !=- 1);
	this.isIE = (ua.indexOf("msie") !=- 1 && !this.isOpera && (ua.indexOf("webtv") ==- 1));
	this.isMozilla = (this.isGecko && ua.indexOf("gecko/") + 14 == ua.length);
	this.isFirefox = (ua.indexOf("firefox/") !=- 1);
	this.versionMinor = parseFloat(navigator.appVersion);
	if(this.isGecko && !this.isMozilla && !this.isKonqueror)
		this.versionMinor = parseFloat(ua.substring(ua.indexOf("/", ua.indexOf("gecko/") + 6) + 1));
	else
	if(this.isMozilla)
		this.versionMinor = parseFloat(ua.substring(ua.indexOf("rv:") + 3));
	else
	if(this.isIE && this.versionMinor >= 4)
		this.versionMinor = parseFloat(ua.substring(ua.indexOf("msie ") + 5));
	else
	if(this.isKonqueror)
		this.versionMinor = parseFloat(ua.substring(ua.indexOf("konqueror/") + 10));
	else
	if(this.isSafari)
		this.versionMinor = parseFloat(ua.substring(ua.lastIndexOf("safari/") + 7));
	else
	if(this.isOpera)
		this.versionMinor = parseFloat(ua.substring(ua.indexOf("opera") + 6));
	if(this.isIE && document.documentMode)
		this.versionMajor = document.documentMode;
	else
		this.versionMajor = parseInt(this.versionMinor);

	this.mode = document.compatMode ? document.compatMode : "BackCompat";
	this.isIE7x = (this.isIE && this.versionMajor == 7);
	this.isIE7up = (this.isIE && this.versionMajor >= 7);
	this.isIE8up = (this.isIE && this.versionMajor >= 8);
	this.isFirefox3x = (this.isFirefox && this.versionMajor == 3);

	var h = document.getElementsByTagName("html")[0];
	var c = h.className;
	if(this.isIE)
		h.className = "ie" + " ie" + this.versionMajor + " " + c;
	else
	if(this.isOpera)
		h.className = ("opera " + c);
	else
	if(this.isKonqueror)
		h.className = ("konqueror " + c);
	else
	if(this.isAppleWebKit)
		h.className = ("webkit safari " + c);
	else
	if(this.isGecko)
		h.className = ("gecko " + c);
}
var browser = new browserDetect();

$(document).ready(function() 
{
	var i = document.createElement('p');
	i.style.width = '100%';
        i.style.height = '200px';
        var o = document.createElement('div');
	o.style.position = 'absolute';
	o.style.top = '0px';
	o.style.left = '0px';
	o.style.visibility = 'hidden';
	o.style.width = '200px';
	o.style.height = '150px';
	o.style.overflow = 'hidden';
	o.appendChild(i);
	document.body.appendChild(o);
	var w1 = i.offsetWidth;
	var h1 = i.offsetHeight;
	o.style.overflow = 'scroll';
	var w2 = i.offsetWidth;
	var h2 = i.offsetHeight;
	if (w1 == w2) w2 = o.clientWidth;
	if (h1 == h2) h2 = o.clientWidth;
	document.body.removeChild(o);
	window.scrollbarWidth = w1-w2;
	window.scrollbarHeight = h1-h2;
});

if(browser.isKonqueror)
{
	$.fn.inheritedval = $.fn.val;
	$.fn.val = function( value )
	{
		if( this.length && $.nodeName( this[0], "textarea" ) && (value !== undefined))
		{
			var tarea = this[0];
			if(tarea.lastChild)
				tarea.removeChild(tarea.lastChild);
			tarea.appendChild(document.createTextNode(value)); 
			return(this);
		}
		else
			return($.fn.inheritedval.call(this,value));
	};
	$.fn.show = function(speed,callback)
	{
		return(this.each(function()
		{
			this.style.display = "block";
		}));
	};
	$.fn.hide = function(speed,callback)
	{
		return(this.each(function()
		{
			this.style.display = "none";
		}).end());
	};
}

$.event.inheritedfix = $.event.fix;
$.event.fix = function(e)
{
	e = $.event.inheritedfix(e);
	e.fromTextCtrl = (e.target && e.target.tagName && (/^input|textarea|a$/i).test(e.target.tagName));
	return(e);
}
$.fn.extend({
	mouseclick: function( handler )
	{
	        return( this.each( function()
	        {
			if(browser.isOpera)
			{
		        	$(this).mousedown(function(e)
				{
					if(e.button==2)
					{
						if(e.target)
						{
							var c = $(this).data("btn");
							if(c)
								c.remove();
							c = $("<input type=button>").css(
							{
								"z-index": 10000, position: "absolute",
								top: (e.clientY - 2), left: (e.clientX - 2),
								width: 5, height: 5,
								opacity: 0.01
							});
							$(document.body).append(c);
							$(this).data("btn",c);
						}
					}
					return(handler.apply(this,arguments));
				});
				$(this).mouseup(function(e)
				{
					var c = $(this).data("btn");
					if(c)
					{
						c.remove();
						$(this).data("btn",null);
						if((e.button==2) &&! (/^input|textarea|a$/i).test(e.target.tagName))
							return(false);
					}
				});
			}
			else
				$(this).mousedown( handler );
		}));
	}
});

function addslashes(str)
{
	return (str+'').replace(/([\\"'])/g, "\\$1").replace(/\u0000/g, "\\0");
}

function iv(val) 
{
	var v = parseInt(val + "");
	return(isNaN(v) ? 0 : v);
}

function linked(obj, _33, lst) 
{
	var tn = obj.tagName.toLowerCase();
	if((tn == "input") && (obj.type == "checkbox")) 
		var d = !obj.checked;
	else 
		if(tn == "select") 
		{
			var v = obj.options[obj.selectedIndex].value;
			var d = (v == _33) ? true : false;
		}
	for(var i = 0; i < lst.length; i++) 
	{
		var o = $$(lst[i]);
		if(o) 
		{
			o.disabled = d;
			o = $$("lbl_" + lst[i]);
			if(o) 
				o.className = (d) ? "disabled" : "";
		}
   	}
}

function escapeHTML(str)
{
	return( $("<div>").text(str).html() );
}

function askYesNo( title, content, funcYesName )
{
	$("#yesnoDlg-header").html(title);
	$("#yesnoDlg-content").html(content);
	$("#yesnoOK").unbind('click');
	$("#yesnoOK").click( function() { eval(funcYesName); theDialogManager.hide("yesnoDlg"); return(false); });
	theDialogManager.show("yesnoDlg");
}

function cloneObject( srcObj )
{
	if( srcObj == null ) return(srcObj);
	var newObject;
	switch( typeof(srcObj) )
	{
		case "object":
		{
			newObject = new srcObj.constructor();
			for( var property in srcObj )
				if( srcObj.hasOwnProperty(property) || typeof( srcObj[property] ) === 'object' )
					newObject[property]= cloneObject( srcObj[property] );
			break;
       		}
		default:
		{
			newObject = srcObj;
			break;
		}
	}
	return newObject;
}

var theOptionsSwitcher =
{
	current: "st_gl",

	run: function(id)
	{
	        $('#'+theOptionsSwitcher.current).hide();
		$("#mnu_" + theOptionsSwitcher.current).toggleClass("focus");
		theOptionsSwitcher.current = id;
	        $('#'+theOptionsSwitcher.current).show();
		$("#mnu_" + theOptionsSwitcher.current).toggleClass("focus");
	}
};

var theConverter = 
{
	round: function(num, p)
	{
		var v = Math.floor(num * Math.pow(10, p)) / Math.pow(10, p);
		var s = v + "";
		var d = s.indexOf(".");
		var n = 0;
		if(d >- 1)
		{
			var ind = s.length - d;
			p++;
			if(ind < p)
				n = p - ind;
		}
		else
		{
			if(p > 0)
			{
				n = p;
				s = s + ".";
			}
		}
		for(var i = 0; i < n; i++)
			s += "0";
		return(s);
	},
	time: function(tm,noRound)
	{
		if((noRound==null) && (tm >= 2419200))
			return "\u221e";
		var val = tm % (604800 * 52);
		var w = iv(val / 604800);
		val = val % 604800;
		var d = iv(val / 86400);
		val = val % 86400;
		var h = iv(val / 3600);
		val = val % 3600;
		var m = iv(val / 60);
		val = val % 60;
		var v = 0;
		var ret = "";
		if(w > 0)
		{	
			ret = w + theUILang.time_w;
			v++;
		}
		if(d > 0)
		{
			ret += d + theUILang.time_d;
			v++;
		}
		if((h > 0) && (v < 2))
		{
			ret += h + theUILang.time_h;
	      		v++;
		}
		if((m > 0) && (v < 2))
		{	
			ret += m + theUILang.time_m;
			v++;
		}
		if(v < 2)
			ret += val + theUILang.time_s;
		return( ret.substring(0,ret.length-1) );
	},
	bytes: function(bt, p)
	{
		p = (p == null) ? 1 : p;
		var a = new Array(theUILang.bytes, theUILang.KB, theUILang.MB, theUILang.GB, theUILang.TB, theUILang.PB);
		var ndx = 0;
		if(bt == 0)
			ndx = 1;
		else
		{
			if(bt < 1024)
			{
				bt /= 1024;
				ndx = 1;
			}
			else
			{
				while(bt >= 1024)
				{
        	    			bt /= 1024;
            				ndx++;
            			}
	         	}
		}
		return(this.round(bt, p) + " " + a[ndx]);
	},
	speed: function(bt)
	{
		return(this.bytes(bt)+ "/" + theUILang.s);
	},
	date: function(dt)
	{
		var today = new Date();
		today.setTime(dt*1000);
		var month = today.getMonth()+1;
		month = (month < 10) ? ("0" + month) : month;
		var day = today.getDate();
		day = (day < 10) ? ("0" + day) : day;
		var h = today.getHours();
		var m = today.getMinutes();
		var s = today.getSeconds();
		h = (h < 10) ? ("0" + h) : h;
		m = (m < 10) ? ("0" + m) : m;
		s = (s < 10) ? ("0" + s) : s;
		return(day+"."+month+"."+today.getFullYear()+" "+h+":"+m+":"+s);
	}
};

var theFormatter = 
{
	torrents: function(table,arr)
	{
		for(var i in arr)
		{
			if(arr[i]==null)
				arr[i] = '';
			else
   			switch(iv(i))
			{
				case 3: 
					arr[i] = (arr[i] / 10) + "%";
					break;
				case 2:
				case 4: 
				case 5: 
				case 15: 
					arr[i] = theConverter.bytes(arr[i], 2);
					break;
				case 6: 
					arr[i] = (arr[i] ==- 1) ? "\u221e" : theConverter.round(arr[i] / 1000, 3);
					break;
				case 7: 
				case 8: 
					arr[i] = theConverter.speed(arr[i]);
					break;
				case 9: 
					arr[i] = (arr[i] <=- 1) ? "\u221e" : theConverter.time(arr[i]);
					break;
				case 13: 
					arr[i] = theFormatter.tPriority(arr[i]);
					break;
				case 14: 
					arr[i] = theConverter.date(arr[i]);
					break;
			}
		}
		return(arr);
	},
	tPriority: function(no) 
	{
		var ret = "";
		switch(iv(no))
		{
			case 0:
				ret = theUILang.Dont_download;
				break;
			case 1: 
				ret = theUILang.Low_priority;
				break;
			case 2:
				ret = theUILang.Normal_priority;
				break;
			case 3:
				ret = theUILang.High_priority;
				break;
		}
		return(ret);
	},
	fPriority: function(no)
	{
		var ret = "";
		switch(iv(no))
		{
			case -1:
				ret = "?";
				break;
			case 0:
				ret = theUILang.Dont_download;
				break;
			case 1:
				ret = theUILang.Normal_priority;
				break;
			case 2:
				ret = theUILang.High_priority;
				break;
		}
		return(ret);
	},
	yesNo: function(no)
	{
		var ret = "";
		switch(iv(no))
		{
			case 0:
				ret = "no";
				break;
			case 1:
				ret = "yes";
				break;
		}
		return(ret);
	},
	trackerType: function(no)
	{
		var ret = "";
		switch(iv(no))
		{
			case 1:
				ret = "http";
				break;
			case 2:
				ret = "udp";
				break;
			case 3:
				ret = "dht";
				break;
		}
		return(ret);
	},
	pluginStatus: function(no)
	{
		var ret = "";
		switch(iv(no))
		{
			case 0:
				ret = theUILang.plgDisabled;
				break;
			case 1:
				ret = theUILang.plgLoaded;
				break;
		}
		return(ret);
	},
	peers: function(table,arr)
	{
		for(var i in arr)
		{
			if(arr[i]==null)
				arr[i] = '';
			else
   			switch(table.getIdByCol(i)) 
   			{
      				case 'done' : 
      					arr[i] = arr[i]+"%";
	      				break;
				case 'downloaded' :
				case 'uploaded' :
      					arr[i] = theConverter.bytes(arr[i]);
      					break;
	      			case 'dl' : 
      				case 'ul' : 
					arr[i] = theConverter.speed(arr[i]);
      					break;
	      		}
	   	}
		return(arr);
	},
	trackers: function(table,arr)
	{
		for(var i in arr)
		{
			if(arr[i]==null)
				arr[i] = '';
			else
		        switch(table.getIdByCol(i))
   			{
      				case 'type' : 
      					arr[i] = theFormatter.trackerType(arr[i]);
	      				break;
      				case 'enabled' : 
      					arr[i] = theFormatter.yesNo(arr[i]);
      					break;
	      		}
		}
		return(arr);
	},
	plugins: function(table,arr)
	{
		for(var i in arr)
		{
			if(arr[i]==null)
				arr[i] = '';
			else
		        switch(table.getIdByCol(i))
   			{
      				case 'status' : 
      					arr[i] = theFormatter.pluginStatus(arr[i]);
	      				break;
      				case 'version' : 
      					arr[i] = new Number(arr[i]).toFixed(2);
	      				break;
	      		}
		}
		return(arr);
	},
	files: function(table,arr)
	{
		for(var i in arr)
		{
			if(arr[i]==null)
				arr[i] = '';
			else
   			switch(table.getIdByCol(i))
   			{
      				case 'size' : 
      				case 'done' : 
      					arr[i] = theConverter.bytes(arr[i], 2);
      					break;
	      			case 'percent' : 
      					arr[i] = arr[i] + "%";
      					break;
	      			case 'priority' : 
      					arr[i] = theFormatter.fPriority(arr[i]);
      					break;
	      		}
	   	}
		return(arr);
	}
};
                    
var theSearchEngines = 
{
	sites:
	[
		{ name: 'Mininova', 		url: 'http://www.mininova.org/search/?utorrent&search=' },
		{ name: 'HQTtracker.ru', 	url: 'http://hqtracker.ru/browse.php?cat=0&search_in=1&search=' },
		{ name: 'The Pirate Bay', 	url: 'http://thepiratebay.org/search.php?q=' },
		{ name: 'INTERFILM', 		url: 'http://interfilm.nu/movie/?do=search&str=' },
		{ name: 'IsoHunt', 		url: 'http://isohunt.com/torrents.php?ext=&op=and&ihq=' },
		{ name: 'VideoTracker.ru', 	url: 'http://videotracker.ru/browse.php?cat=0&search_in=1&search=' },
		{ name: '', 			url: '' },
		{ name: 'Google', 		url: 'http://google.com/search?q=' }
	],
	current: 0,

	run: function()
	{
	        if(theSearchEngines.current>=0)
			window.open(theSearchEngines.sites[theSearchEngines.current].url + $("#query").val(), "_blank");
		else
			theWebUI.setTeg($("#query").val());
	},
	set: function( no, noSave )
	{
		theSearchEngines.current = no;
		if(!noSave)
			theWebUI.save();
	},
	show: function()
	{
		theContextMenu.clear();
		$.each(this.sites,function(ndx,val)
		{
			if(val.name=="")
				theContextMenu.add([CMENU_SEP]);
			else
			if(theSearchEngines.current==ndx)
				theContextMenu.add([CMENU_SEL, val.name, "theSearchEngines.set("+ndx+")"]);
			else
				theContextMenu.add([val.name, "theSearchEngines.set("+ndx+")"]);
		});
		theContextMenu.add([CMENU_SEP]);
		if(theSearchEngines.current==-1)
			theContextMenu.add([CMENU_SEL, theUILang.innerSearch, "theSearchEngines.set(-1)"]);
		else
			theContextMenu.add([theUILang.innerSearch, "theSearchEngines.set(-1)"]);
		var offs = $("#search").offset();
		theContextMenu.show(offs.left-5,offs.top+5+$("#search").height());
        }
};

var Timer = function()
{
	this.initial = 0;
	this.interval = 0;
};

Timer.prototype.start = function()
{
	this.initial = (new Date()).getTime();
};

Timer.prototype.stop = function()
{
	this.interval = (new Date()).getTime() - this.initial;
};

var theTabs = 
{
	tabs: 
	{
   		gcont : theUILang.General, 
   		FileList : theUILang.Files, 
   		TrackerList : theUILang.Trackers, 
   		PeerList : theUILang.Peers,
   		Speed : theUILang.Speed,
   		PluginList : theUILang.Plugins,
   		lcont : theUILang.Logger
   	}, 

   	init: function() 
   	{
		if(browser.isKonqueror && (browser.versionMajor<4))
			delete this.tabs["Speed"];
   		var s = "";
   		for(var n in this.tabs) 
      			s += "<li id=\"tab_" + n + "\"><a href=\"javascript://void();\" onmousedown=\"theTabs.show('" + n + "'); return(false);\" onfocus=\"this.blur();\">" + this.tabs[n] + "</a></li>";
		$("#tabbar").html(s);
   		this.show("gcont");
   	}, 
	onShow : function(id)
	{
	},
   	show : function(id) 
	{
   		var p = null, l = null;
   		for(var n in this.tabs) 
   		{
      			if((l = $("#tab_" + n)).length && (p = $('#'+n)).length) 
      			{
         			if(n == id) 
         			{
            				p.show();
            				var prefix = null;
            				switch(n)
            				{
            					case "FileList":
							prefix = "fls";
							break;
               					case "TrackerList":
							prefix = "trk";
							break;
               					case "PeerList":
							prefix = "prs";
							break;
						case "PluginList":
							prefix = "plg";
							break;
               					case "Speed":
               						theWebUI.setActiveView(id);
               						theWebUI.speedGraph.resize(); 
               						break;
						default:
							this.onShow(n);
               				}
               				if(prefix)
               				        theWebUI.getTable(prefix).calcSize().resizeHack();
               				theWebUI.setActiveView(id);
            				l.addClass("selected").css("z-index",1);
            			}
         			else 
         			{
            				p.hide();
            				l.removeClass("selected").css("z-index",0);
            			}
         		}
      		}
   	}
};

function log(text,noTime) 
{
	var tm = '';
	if(!noTime)
		tm = "[" + theConverter.date(new Date().getTime()/1000) + "]";
	var obj = $("#lcont");
	if(obj.length)
	{
		obj.append( $("<div>").text(tm + " " + text).show() );
		obj.scrollTop = obj.scrollHeight;
		theTabs.show("lcont");
	}
}

function rDirectory()
{
	this.dirs = new Array();
	this.dirs[""] = new Array();
	this.current = "";
}

rDirectory.prototype.addFile = function(aData,no)
{
	var name = aData.name;
	var fileAdded = false;
	while(name.length)
	{
		var file = splitName(name);
		if(!this.dirs[file.path])
		{
			this.dirs[file.path] = {};
			var up = splitName(file.path).path;
			this.dirs[file.path]["_d_"+up] = { data: { name: "..", size: null, done: null, percent: null, priority: -2 }, icon: "Icon_Dir", link: up };
		}
		if(!fileAdded)
		{
			var sId = "_f_"+no;
			var data = cloneObject( aData );
			data.name = file.name;
		        if(this.dirs[file.path][sId])
				this.dirs[file.path][sId].data = data;
			else
				this.dirs[file.path][sId] = { "data": data, icon: "Icon_File", link: null };
			fileAdded = true;
		}
		else
		{
			var sId = "_d_"+name;
			if(!this.dirs[file.path][sId])
				this.dirs[file.path][sId] = { data: { name: file.name, size: 0, done: 0, percent: 0.0, priority: -1 }, icon: "Icon_Dir", link: name };
		}
		name = file.path;
	} 

	function splitName(name)
	{
		var ret = { "path": "", "name": name };
		var loc = name.lastIndexOf('/');
		if(loc>=0)
		{
			ret.path = name.substr(0,loc);
			ret.name = name.substr(loc+1);
		}
		return(ret);
	}
}

rDirectory.prototype.updateDirs = function(name)
{
	var dir = this.dirs[name];
	var allStat = { size: 0, done: 0, priority: -2 };
	var stat;
	for(var i in dir) 
	{
		if(dir[i].data.name!="..")
		{
			if(dir[i].link!=null)
			{
				stat = this.updateDirs(dir[i].link)
				dir[i].data.size = stat.size;
				dir[i].data.done = stat.done;
				dir[i].data.percent = ((dir[i].data.size > 0) ? theConverter.round((dir[i].data.done/dir[i].data.size)*100,1): "100.0");
				dir[i].data.priority = stat.priority;
			}
			else
				stat = dir[i].data;
			allStat.size+=stat.size;
			allStat.done+=stat.done;
			if(allStat.priority==-2)
				allStat.priority = stat.priority;
			else
				if(allStat.priority!=stat.priority) 
					allStat.priority = -1;
		}
	}
	return(allStat);
}

rDirectory.prototype.getEntryPriority = function(k)
{
	var entry = this.dirs[this.current][k];
	return((entry.data.name=="..") ? null : entry.data.priority);
}

rDirectory.prototype.getFilesIds = function(arr,current,k,prt)
{
	var entry = this.dirs[current][k];
	if(entry.data.name!="..")
	{
		if(entry.link!=null)
		{
	        	for(var i in this.dirs[entry.link])
				this.getFilesIds(arr,entry.link,i,prt);
		}
		else
			if(entry.data.priority!=prt)
				arr.push(k.substr(3));
	}
}

rDirectory.prototype.getDirectory = function()
{
	this.updateDirs(this.current);
	return(this.dirs[this.current]);
}

rDirectory.prototype.setDirectory = function(name)
{
	this.current = name;
}

var theBTClientVersion = 
{
	azLikeClients4:   
	{
		"AR" : "Ares", "AT" : "Artemis", "AV" : "Avicora",
		"BG" : "BTGetit", "BM" : "BitMagnet", "BP" : "BitTorrent Pro (Azureus + Spyware)",
		"BX" : "BittorrentX", "bk" : "BitKitten (libtorrent)", "BS" : "BTSlave",
		"BW" : "BitWombat", "BX" : "BittorrentX", "EB" : "EBit",
		"DE" : "Deluge", "DP" : "Propogate Data Client", "FC" : "FileCroc",
		"FT" : "FoxTorrent/RedSwoosh", "GR" : "GetRight", "HN" : "Hydranode",
		"LC" : "LeechCraft", "LH" : "LH-ABC", "NX" : "Net Transport",
		"MO" : "MonoTorrent", "MR" : "Miro", "MT" : "Moonlight",
		"OT" : "OmegaTorrent", "PD" : "Pando", "QD" : "QQDownload",
		"RS" : "Rufus", "RT" : "Retriever", "RZ" : "RezTorrent",
		"SD" : "Xunlei", "SS" : "SwarmScope", "SZ" : "Shareaza",
		"S~" : "Shareaza beta", "st" : "SharkTorrent", "TN" : "Torrent .NET",
		"TS" : "TorrentStorm", "UL" : "uLeecher!", "VG" : "Vagaa",
		"WT" : "BitLet", "WY" : "Wyzo", "XL" : "Xunlei",
		"XT" : "XanTorrent", "ZT" : "Zip Torrent", 
		'GS' : "GSTorrent", 'KG' : "KGet", 'ST' : "SymTorrent", 
		'BE' : "BitTorrent SDK"
	 },
	azLikeClients3:
	{
	        "AG" : "Ares", "A~" : "Ares", "ES" : "Electric Sheep",
        	"HL" : "Halite", "LT" : "libtorrent (Rasterbar)", "lt" : "libTorrent (Rakshasa)",
	        "MP" : "MooPolice", "TT" : "TuoTu", "qB" : "qBittorrent"
	},
	azLikeClients2x2:
	{
	        "AX" : "BitPump", "BC" : "BitComet", "CD" : "Enhanced CTorrent",
        	"LP" : "Lphant"
	},
	azLikeClientsSpec:
	{
		'UM' : "uTorrent for Mac", 'UT' : "uTorrent", 'TR' : "Transmission",
		'AZ' : "Azureus", 'KT' : "KTorrent", "BF" : "BitFlu",
	        'LW' : "LimeWire", "BB" : "BitBuddy", "BR" : "BitRocket",
		"CT" : "CTorrent", 'XX' : "Xtorrent"
	},
	shLikeClients:
	{
		'O' : "Osprey ", 'Q' : "BTQueue", 
        	'A' : "ABC", 'R' : "Tribler", 'S' : "Shad0w",
	        'T': "BitTornado", 'U': "UPnP NAT Bit Torrent"
	},
// -SP3603 -UT1830%81%80%06%3A%05%CB
	get: function( origStr )
	{

		function shChar( ch )
		{
			var codes = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz.-";
			var loc = codes.indexOf(ch);
			if(loc<0) loc = 0;
			return(String(loc));
		}

		function getMnemonicEnd( ch )
		{
			switch( ch )
		    	{
       				case 'b': case 'B': return " (Beta)";
				case 'd': return " (Debug)";
				case 'x': case 'X': case 'Z': return " (Dev)";
			}
			return("");
		}

		var ret = null;
		var str = unescape(origStr);
		if(str.match(/^-[A-Z~][A-Z~][A-Z0-9][A-Z0-9]..-/i))
		{
	        	var sign = str.substr(1,2);
			var cli = this.azLikeClientsSpec[sign];
			if(cli)
			{
			        switch(sign)
			        {
					case 'UT':
					case 'UM':
						ret = cli+" "+str.charAt(3)+"."+str.charAt(4)+"."+str.charAt(5)+getMnemonicEnd(str.charAt(6));
						break;
					case 'TR':
						if(str.substr(3,2)=='00')
						{
							if(str.charAt(5)=='0')
							ret = cli+" 0."+str.charAt(6);
						}
						else
							ret = cli+" 0."+parseInt(str.substr(5,2),10);
						break;
					default:
						var ch = str.charAt(6);
						ret = cli+" "+str.charAt(3)+"."+parseInt(str.substr(4,2),10);
						if((ch=='Z') || (ch=='X')) 
							ret+='+';
						break;
				}
			}
			else
				switch(sign)
				{
					case 'KT':
						var ch = str.charAt(5);
                		                if( ch == 'D' )
							ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+" Dev "+shChar(str.charAt(6));
					        else
					        if( ch == 'R' )
						        ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+" RC "+shChar(str.charAt(6));
						else
							ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+shChar(str.charAt(5));
						break;
					case 'AZ':
						if(str.charAt(3) > '3' || ( str.charAt(3) == '3' && str.charAt(4) >= '1' ))
							cli = "Vuze";
						ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+shChar(str.charAt(5))+"."+shChar(str.charAt(6));
						break;
	                              	case 'BF':
	                              	case 'LW':
						ret = cli;
						break;

					case 'BB':
						ret = cli+" "+str.charAt(3)+"."+str.charAt(4)+str.charAt(5)+str.charAt(6);
						break;
					case 'BR':
						ret = cli+" "+str.charAt(3)+"."+str.charAt(4)+" ("+str.charAt(5)+str.charAt(6)+")";
						break;
					case 'CT':
						ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+parseInt(str.substr(5,2),10);
						break;
					case 'XX':
						ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+" ("+parseInt(str.substr(5,2),10)+")";
						break;
				}
		}
		else
		{
			cli = this.azLikeClients4[sign];
			if(cli)
				ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+shChar(str.charAt(5))+"."+shChar(str.charAt(6));
			else
			{
				cli = this.azLikeClients3[sign];
				if(cli)
					ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+shChar(str.charAt(5));
				else
				{
					cli = this.azLikeClients2x2[sign];
					if(cli)
						ret = cli+" "+parseInt(str.substr(3,2),10)+"."+parseInt(str.substr(5,2),10);
				}
			}
		}
		if(!ret)
		{
			if(str.match(/^[MQ]\d-\d[\-\d][\-\d][\-\d]-/))
			{
				ret = (str.charAt(0)=='Q') ? "Queen Bee " : "BitTorrent ";
				if(str.charAt(4) == '-')
					ret += (str.charAt(1)+"."+str.charAt(3)+"."+str.charAt(5));
				else
					ret += (str.charAt(1)+"."+str.charAt(3)+str.charAt(4)+"."+str.charAt(6));
			}
		        else
			if(str.match(/^-BOW/))
			{
				if( str.substr(4,4)=="A0B" ) 
	    				ret = "Bits on Wheels 1.0.5";
				else
				if( str.substr(4,4)=="A0C" ) 
		    			ret = "Bits on Wheels 1.0.6";
				else
					ret = "Bits on Wheels "+str.charAt(4)+"."+str.charAt(5)+"."+str.charAt(6);
			}
			else
			if(str.match(/^AZ2500BT/))
				ret = "BitTyrant (Azureus Mod)";
			else
			if(str.match(/^-FG\d\d\d\d/))
				ret = "FlashGet "+parseInt(str.substr(3,2),10)+"."+parseInt(str.substr(5,2),10);
			else
			if(str.match(/^346-/))
				ret = "TorrenTopia";
			else
			if(str.match(/^-G3/))
				ret = "G3 Torrent";
			else
			if(str.match(/^LIME/))
				ret = "Limewire";
			else
			if(str.match(/^martini/))
				ret = "Martini Man";
			else
			if(str.match(/^Pando/))
				ret = "Pando";
			else
			if(str.match(/^a0[02]---0/))
				ret = "Swarmy";
			else
			if(str.match(/^10-------/))
				ret = "JVtorrent";
			else
			if(str.match(/^eX/))
				ret = "eXeem";
			else
			if(str.match(/^-aria2-/))
				ret = "aria2";
			else
			if(str.match(/^S3-.-.-./))
				ret = "Amazon S3 "+str.charAt(3)+"."+str.charAt(5)+"."+str.charAt(7);
			else
			if(str.match(/^OP/))
				ret = "Opera (build "+str.substr(2,4)+")";
			else
			if(str.match(/^-ML/))
				ret = "MLDonkey "+str.substr(3,5);
			else
			if(str.match(/^AP/))
				ret = "AllPeers "+str.substr(2,4);
			else
			if(str.match(/^DNA\d\d\d\d\d\d/))
				ret = "BitTorrent DNA "+parseInt(str.substr(3,2),10)+"."+parseInt(str.substr(5,2),10)+"."+parseInt(str.substr(7,2),10);
			else
			if(str.match(/^Plus/))
				ret = "Plus! v2 "+str.charAt(4)+"."+str.charAt(5)+str.charAt(6);
			else
			if(str.match(/^XBT\d\d\d/))
				ret = "XBT Client "+str.charAt(3)+"."+str.charAt(4)+"."+str.charAt(5)+getMnemonicEnd(str.charAt(6));
			else
			if(str.match(/^Mbrst/))
				ret = "burst! "+str.charAt(5)+"."+str.charAt(7)+"."+str.charAt(9);
			else
			if(str.match(/^btpd/))
				ret = "BT Protocol Daemon "+str.charAt(5)+str.charAt(6)+str.charAt(7);
			else
			if(str.match(/^BLZ/))
				ret = "Blizzard Downloader "+(str.charCodeAt(3)+1)+"."+str.charCodeAt(4);
			else
			if(str.match(/.*UDP0$/))
				ret = "BitSpirit";
			else
			if(str.match(/^QVOD/))
				ret = "QVOD "+str.charAt(4)+"."+str.charAt(5)+"."+str.charAt(6)+"."+str.charAt(7);
			else
			if(str.match(/^-NE/))
				ret = "BT Next Evolution "+str.charAt(3)+"."+str.charAt(4)+"."+str.charAt(5)+"."+str.charAt(6);
			else
			{
				var mod = null;
				if(str.match(/^exbc/))
					mod = '';
				else
				if(str.match(/^FUTB/))
					mod = '(Solidox Mod) ';
				else
				if(str.match(/^xUTB/))
					mod = '(Mod 2) ';
				if(mod!=null)
				{
					var isBitlord = (str.substr(6,4)=="LORD");
					var name = isBitlord ? "BitLord " : "BitComet ";
					var major = str.charCodeAt(4);
					var minor = str.charCodeAt(5);
					var sep = ".";
					if(!(isBitlord && major>0) && (minor<10))
						sep+="0";
					ret = name+mod+major+sep+minor;
				}
			}
		}
		if(!ret)
		{
			if(str.match(/^[A-Z]([A-Z0-9\-\.]{1,5})/i))
			{
				var cli = this.shLikeClients[str.charAt(0)];
				if(cli)
					ret = cli+" "+shChar(str.charAt(1))+"."+shChar(str.charAt(2))+"."+shChar(str.charAt(3));
			}
		}
		return(ret ? ret : "Unknown ("+origStr+")");
	}
};