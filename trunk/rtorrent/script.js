var resizing = false, resizeTimeout = null;
var _isResizing = false;
var tdl = 0, tul = 0, stimer = null;
var tdb = 0, tub = 0;
var tdlimit = 0, tulimit = 0;
var version = "2.6";

function init() 
{
	calcScrollbarSize();
	if(arguments.callee.done) 
	{
		return;
	}
	arguments.callee.done = true;
	if(_timer) 
	{
		clearInterval(_timer);
	}
	log("WebUI started.");
	document.title = "ruTorrent v" + version;
	Key.onKeyDown = keyDown;
	window.onresize = function(e) 
		{
			if(browser.isIE && (resizing == false)) 
			{
				if(resizeTimeout != null) 
				{
					window.clearTimeout(resizeTimeout);
				}
				resizeTimeout = window.setTimeout("resizeUI()", 500);
			}
			else 
			{
				resizeUI();
			}
		};
	ContextMenu.init("ContextMenu");
	if(browser.isIE && !browser.isIE7up) 
	{
		$$("List").style.width = "99%";
	}
	utWebUI.init();
	var o = $$("stg_c");
	o.innerHTML = stgHtml;
	utWebUI.getPlugins();
	if($_COOKIE == null)
	{
		Hide('loadimg');
    		$$("msg").innerHTML = WUILang.PHPDoesnt_enabled;
      		return;
	}
	utWebUI.config();
	var _3 = new Array();
	if($_COOKIE["webui.trt.colwidth"] != null) 
	{
		_3 = $_COOKIE["webui.trt.colwidth"];
		for(var i in _3)
		{
//			if(browser.isOldIE && _3[i]>4)
			if(!browser.isAppleWebKit && !browser.isKonqueror && (_3[i]>4))
				_3[i]-=4;
			if(i<utWebUI.trtColumns.length)
	  	                utWebUI.trtColumns[i].width = _3[i] + "px";
			else
				break;
		}
	}
	if($_COOKIE["webui.trt.colenabled"] != null) 
	{
		_3 = $_COOKIE["webui.trt.colenabled"];
		for(var i in _3)
			if(i<utWebUI.trtColumns.length)
				utWebUI.trtColumns[i].enabled = _3[i];
			else
				break;
	}
	var ol = $$("List");
	utWebUI.trtTable.format = FormatTL;
	utWebUI.trtTable.create(ol, utWebUI.trtColumns, "utWebUI.trtTable");
	utWebUI.trtTable.onresize = utWebUI.Save;
	utWebUI.trtTable.oncoltoggled = utWebUI.Save;
	utWebUI.trtTable.reverse = utWebUI.trtSortR;

	if($_COOKIE["webui.fls.colwidth"] != null) 
	{
		_3 = $_COOKIE["webui.fls.colwidth"];
		for(var i in _3) 
		{
			if(iv(_3[i]) == 0) 
				continue;
			if(!browser.isAppleWebKit && !browser.isKonqueror && (_3[i]>4))
				_3[i]-=4;
			if(i<utWebUI.flsColumns.length)
				utWebUI.flsColumns[i].width = _3[i] + "px";
		}
	}
	if($_COOKIE["webui.fls.colenabled"] != null) 
	{
		_3 = $_COOKIE["webui.fls.colenabled"];
		for(var i in _3)
			if(i<utWebUI.flsColumns.length)
				utWebUI.flsColumns[i].enabled = _3[i];
	}
	if($_COOKIE["webui.fls.view"] != null)
	{
		if($_COOKIE["webui.fls.view"]==1)
			utWebUI.fileListMode = true;
	}
	var fl = $$("FileList");
	utWebUI.flsTable.format = FormatFL;
	utWebUI.flsTable.create(fl, utWebUI.flsColumns, "utWebUI.flsTable");
	utWebUI.flsTable.onresize = utWebUI.Save;
	utWebUI.flsTable.oncoltoggled = utWebUI.Save;

	utWebUI.flsTable.reverse = utWebUI.flsSortR;
	if($_COOKIE["webui.trk.colwidth"] != null) 
	{
		_3 = $_COOKIE["webui.trk.colwidth"];
		for(var i in _3)
		{
			if(iv(_3[i]) == 0) 
				continue;
			if(!browser.isAppleWebKit && !browser.isKonqueror && (_3[i]>4))
				_3[i]-=4;
			if(i<utWebUI.trkColumns.length)
				utWebUI.trkColumns[i].width = _3[i] + "px";
		}
	}
	if($_COOKIE["webui.trk.colenabled"] != null) 
	{
		_3 = $_COOKIE["webui.trk.colenabled"];
		for(var i in _3)
			if(i<utWebUI.trkColumns.length)
				utWebUI.trkColumns[i].enabled = _3[i];
	}
	fl = $$("TrackerList");
	utWebUI.trkTable.format = FormatTR;
	utWebUI.trkTable.create(fl, utWebUI.trkColumns, "utWebUI.trkTable");
	utWebUI.trkTable.onresize = utWebUI.Save;
	utWebUI.trkTable.oncoltoggled = utWebUI.Save;
	utWebUI.trkTable.reverse = utWebUI.trkSortR;
	if($_COOKIE["webui.prs.colwidth"] != null) 
	{
		_3 = $_COOKIE["webui.prs.colwidth"];
		for(var i in _3) 
		{
			if(iv(_3[i]) == 0) 
				continue;
			if(!browser.isAppleWebKit && !browser.isKonqueror && (_3[i]>4))
				_3[i]-=4;
			if(i<utWebUI.prsColumns.length)
				utWebUI.prsColumns[i].width = _3[i] + "px";
         	}
	}
	if($_COOKIE["webui.prs.colenabled"] != null) 
	{
		_3 = $_COOKIE["webui.prs.colenabled"];
		for(var i in _3)
			if(i<utWebUI.prsColumns.length)
				utWebUI.prsColumns[i].enabled = _3[i];
	}
	fl = $$("PeerList");
	utWebUI.prsTable.format = FormatPR;
	utWebUI.prsTable.create(fl, utWebUI.prsColumns, "utWebUI.prsTable");
	utWebUI.prsTable.onresize = utWebUI.Save;
	utWebUI.prsTable.oncoltoggled = utWebUI.Save;
	utWebUI.prsTable.reverse = utWebUI.prsSortR;
	utWebUI.speedGraph.create($$("Speed"));

	Update();
	$$("query").onkeydown = function(e) 
		{
			if(!e) 
			{
				e = window.event;
         		}
			var k = e.keyCode || e.which;
			if(k == 13) 
			{
				Search();
			}
      		};
	stimer = window.setInterval("UpdateStatus()", 1000);
	Drag.mask = $$("dragmask");
	var ww = getWindowWidth(), wh = getWindowHeight();
	Drag.init($$("stg_h"), $$("stg"), 0, ww, 0, wh, true);
	Drag.init($$("tadd_h"), $$("tadd"), 0, ww, 0, wh, true);
	Drag.init($$("dlgProps-header"), $$("dlgProps"), 0, ww, 0, wh, true);
	Drag.init($$("dlgAbout-header"), $$("dlgAbout"), 0, ww, 0, wh, true);
	Drag.init($$("dlgHelp-header"), $$("dlgHelp"), 0, ww, 0, wh, true);
	Drag.init($$("dlgRss-header"), $$("dlgRss"), 0, ww, 0, wh, true);
	Drag.init($$("dlgLabel-header"), $$("dlgLabel"), 0, ww, 0, wh, true);
	Drag.init($$("yesnoDlg-header"), $$("yesnoDlg"), 0, ww, 0, wh, true);
	window.setTimeout(ShowUI, 1000);
	var d = $$("HDivider");
	d.onmousedown = function(e) 
		{
			e = FixEvent(e);
			if(browser.isFirefox)
				e.preventDefault();
			this.lastX = e.clientX;
			smm = addEvent(document, "mousemove", function(e) { return(SepMove(e, 0)); });
			smu = addEvent(document, "mouseup", function(e) { return(SepUp(e, 0)); });
      		};
	d = $$("VDivider");
	d.onmousedown = function(e) 
		{
			e = FixEvent(e);
			if(browser.isFirefox)
				e.preventDefault();
			this.lastY = e.clientY;
			smm = addEvent(document, "mousemove", function(e) { return(SepMove(e, 1)); });
			smu = addEvent(document, "mouseup", function(e) { return(SepUp(e, 1)); });
      		};
	resizeUI();
	tdTabs.show("gcont");
//	window.onerror = function(msg, url, _3e) 
//	{
//		log("JS error: [" + url + " : " + _3e + "] " + msg);
//		return true;
//	};
	utWebUI.initDone();
}

function cleanup()
{
	window.onload = null;
	if(document.removeEventListener) 
        	document.removeEventListener("DOMContentLoaded",init,false);
}

if(document.addEventListener) 
{
	document.addEventListener("DOMContentLoaded", init, false);
}
if(/WebKit/i.test(navigator.userAgent))
{
	var _timer=setInterval(function(){if(/loaded|complete/.test(document.readyState)){init();}},10);
}
window.onload = init;
addEvent(window, "unload", cleanup);

addEvent(document, "mouseup", 
	function(e) 
	{
		var ret = true;
		if(!e) 
		{
			e = window.event; 
		}
		var ele = (typeof e.target != "undefined") ? e.target : e.srcElement; 
		if(e.button == 2) 
		{
			var tg = ele.tagName;
			if(tg)
				tg = tg.toLowerCase();
	   		if(!tg || ((tg != "input") && (tg != "textarea")))
	   		{
      				CancelEvent(e);
	      			ret = false;
			}
   		}
		else
		{
			if((ele.id!="search") && (ele.id!="mnu_search"))
	   			window.setTimeout("ContextMenu.hide()", 50); 
		}
	   	return(ret);
	}
);

var smm, smu, smx;

function SepMove(e, dir) 
{
	e = FixEvent(e);
	_isResizing = true;
	utWebUI.trtTable.isResizing = true;
	utWebUI.flsTable.isResizing = true;
	utWebUI.trkTable.isResizing = true;
	utWebUI.prsTable.isResizing = true;
	if(dir == 0) 
	{
		if(!utWebUI.bCategories) 
		{
      			return(true);
      		}
		var ex = e.clientX;
		var cw = mouse.X + ex - $$("HDivider").lastX - 5;
		$$("HDivider").lastX = ex;
		if(cw < 60) 
		{
			return(true);
		}
		var lw = getWindowWidth() - cw - 10;
		$$("List").style.width = lw + "px";
		$$("CatList").style.width = cw + "px";
		$$("tdetails").style.width = (lw - 1) + "px";

		document.body.style.cursor = "e-resize";
   	}
	else 
	{
		if(dir == 1) 
		{
			if(!utWebUI.bDetails) 
			{
				return(true);
			}
			var eh = $$("t").offsetHeight;
			if(isNaN(eh)) 
			{
				eh = 0;
			}
			var ey = e.clientY;
			var lh = mouse.Y + ey - $$("VDivider").lastY - eh - 8;
			var gh = getWindowHeight() - lh - eh - 9;
			$$("VDivider").lastY = ey;
			if((lh < 60) || (gh < 60)) 
			{
				return(true);
			}
			$$("List").style.height = lh + "px";
			$$("HDivider").style.height = gh + "px";
			o = $$("tdetails");
			o.style.height = gh + "px";
			$$("tdcont").style.height = (o.offsetHeight - 39) + "px";
			document.body.style.cursor = "n-resize";
      		}
   	}
	try { document.selection.empty(); } catch(ex) {}
	CancelEvent(e);
	return(false);
}

function SepUp(e, dir) 
{
	removeEvent(document, "mousemove", smm);
	removeEvent(document, "mouseup", smu);
	document.body.style.cursor = "default";
	utWebUI.trtTable.isResizing = false;
	utWebUI.flsTable.isResizing = false;
	utWebUI.trkTable.isResizing = false;
	utWebUI.prsTable.isResizing = false;
	if(dir == 0) 
	{
		if(!utWebUI.bCategories) 
		{
			return(true);
		}
		utWebUI.trtTable.resize(iv($$("List").style.width));
		utWebUI.resizeBottomBar(iv($$("tdetails").style.width) - 8 , null);
		var r = 1 - (mouse.X + e.clientX - $$("HDivider").lastX) / getWindowWidth();
		r = Math.floor(r * Math.pow(10, 3)) / Math.pow(10, 3);
		if(utWebUI.hSplit != r) 
		{
			utWebUI.hSplit = r;
			utWebUI.Save();
		}
	}
	else 
	{
		if(dir == 1) 
		{
			if(!utWebUI.bDetails) 
			{
				return(true);
			}
			utWebUI.trtTable.resize(null, iv($$("List").style.height));
			if(browser.isOldIE)
			{
				var he = iv($$("tdetails").style.height);
			        $$("tdcont").style.height = (he - 54) + "px";
			}
			var h = iv($$("tdcont").style.height);
			utWebUI.resizeBottomBar(null,h-2);
			var r = (mouse.Y + e.clientY - $$("VDivider").lastY) / getWindowHeight();
			r = Math.floor(r * Math.pow(10, 3)) / Math.pow(10, 3);
			if(utWebUI.vSplit != r) 
			{
				utWebUI.vSplit = r;
				utWebUI.Save();
			}
      		}
   	}
	_isResizing = false;
	return(false);
}

if(typeof Array.prototype.push == "undefined") 
{
	Array.prototype.push = function() 
	{
		for(var i = 0, l = arguments.length; i < l; i++) 
		{
			this[this.length] = arguments[i];
      		}
		return this.length;
   	};
}

function ShowUI() 
{
	$$("cover").style.display = "none";
	tdTabs.show("lcont");
}

function selcheck(obj, val, _29) 
{
	var v = obj.options[obj.selectedIndex].value;
	var b = (v == val);
	for(var i = 0, l = _29.length; i < l; i++) 
	{
		var o = $$(_29[i][1]);
		if(o == null) 
		{
			continue;
		}
		if(_29[i][0] && b) 
		{
			o.disabled = false;
      		}
		else 
		{
			o.disabled = true;
		}
   	}
}

function linked(obj, _33, lst) 
{
	var tn = obj.tagName.toLowerCase();
	if((tn == "input") && (obj.type == "checkbox")) 
	{
		var d =!obj.checked;
   	}
	else 
	{
		if(tn == "select") 
		{
			var v = obj.options[obj.selectedIndex].value;
			var d = (v == _33) ? true : false;
		}
   	}
	for(var i = 0, l = lst.length; i < l; i++) 
	{
		var o = $$(lst[i]);
		if(!o) 
		{
			continue;
		}
		o.disabled = d;
		o = $$("lbl_" + lst[i]);
		if(!o) 
		{
			continue;
		}
		o.className = (d) ? "disabled" : "";
   	}
}

function redirect(url) 
{
	window.location.href = url;
}

function keyDown(e) 
{
	if(!e) 
	{
		e = window.event;
	}
	var k = e.keyCode || e.which;
	var ret = true;
	switch(k) 
	{
   		case 79 : 				// ^O
   		{
			if(e.ctrlKey) 
   			{	
      				utWebUI.showAdd();	
      				CancelEvent(e);
				ret = false;
      			}
			break;
		}
		case 80 :                               // ^P
		{
			if(e.ctrlKey) 
			{	
      				utWebUI.showSettings();	
				CancelKeyEvent(e);
				ret = false;
      			}
   			break;
		}
  		case 112:				// F1
   		{
			if(e.ctrlKey) 
				Show("dlgAbout");
			else
				Show("dlgHelp");
			CancelKeyEvent(e);
			ret = false;
		   	break;
   		}
		case 115 : 				// F4
		{
			utWebUI.toggleMenu();
   			CancelKeyEvent(e);
			ret = false;
			break;
		}
		case 117 :                      	// F6
		{
			utWebUI.toggleDetails();
   			CancelKeyEvent(e);
			ret = false;
			break;
		}
		case 118 :                      	// F7
		{
			utWebUI.toggleCategories();
			CancelKeyEvent(e);
			ret = false;
			break;
		}
	}
	return(ret);
}

function CancelEvent(e) 
{
	if(!e) 
	{
		return;
   	}
	if(typeof e.preventDefault != "undefined") 
	{
		e.preventDefault();
		e.stopPropagation();
	}
	else 
	{
		e.returnValue = false;
		e.cancelBubble = true;
   	}
	if(browser.isOpera) 
	{
		window.blur();
		window.focus();
   	}
	return false;
}

function CancelKeyEvent(e) 
{
	if(!e) 
	{
		return;
   	}
	if(e.stopPropagation) 
	{
		e.stopPropagation();
   	}
   	if(e.stop) 
   	{
	   	e.stop();
   	}
	if(e.preventDefault) 
	{
		e.preventDefault();
   	}
	try 
	{
		e.returnValue = false;
   	} catch(ex) {}
	try 
	{
		e.cancelBubble = true;
   	} catch(ex) {}
	try 
	{
	   	e.keyCode = 0;
   	} catch(ex) {}
}

document.oncontextmenu = function(e) 
{
	if(!e)
		e = window.event; 
	if(e) 
	{
   		var ele = (typeof e.target != "undefined") ? e.target : e.srcElement; 
		if( ele &&
			(typeof ele.tagName != "undefined") &&
			((ele.tagName.toLowerCase() == "input") ||
			(ele.tagName.toLowerCase() == "textarea")))
   		{
   			ContextMenu.hide();
      			return true; 
   		}
	}
	return false;
};

document.ondragstart = function() 
{
	return false;
};

document.onselectstart = function() 
{
	if(document.activeElement)
	{
		var tg = document.activeElement.tagName.toLowerCase();
		return((tg=="input") || (tg=="textarea"));
	}
	return(false);
};

function showCallStack()
{
	var f = showCallStack;
	while((f=f.caller)!==null)
	{
		alert(f.toString());
	}
}

function resizeUI() 
{
	if(_isResizing) 
	{
		return;
   	}
	resizing = true;
	window.clearTimeout(resizeTimeout);
	var eh = 0, th = iv($$("t").offsetHeight);
	if(!isNaN(th)) 
	{
		eh += th;
   	}
	var ww = getWindowWidth(), wh = getWindowHeight(), o, h, cs, ds;
	var cs = utWebUI.bCategories;
	var ds = utWebUI.bDetails;
	h = Math.floor(wh * ((ds) ? utWebUI.vSplit : 1));
	h -= 13 + eh;
	var lw = Math.floor(ww * ((cs) ? utWebUI.hSplit : 1)) - ((cs) ? 5 : 13);
	utWebUI.trtTable.resize(lw, h);

	h = (wh-eh-14);

	if(cs == true) 
	{
		var cw = Math.floor(ww * (1 - utWebUI.hSplit)) - 5;
		o = $$("CatList");
		o.style.height = h + "px";
		o.style.width = cw + "px";
	}
	if(ds == true) 
	{
		o = $$("tdetails");
		o.style.width = lw + "px";
		var he = Math.floor(wh * (1 - utWebUI.vSplit));
		if(browser.isOldIE)
			he-=20;
		o.style.height = he + "px";
		he = (!browser.isOldIE && o.offsetHeight && (o.offsetHeight>34)) ? o.offsetHeight - 34 : he - 29;
		$$("tdcont").style.height = he + "px";
		utWebUI.resizeBottomBar(lw - 8 , he-2);
	}
	o = $$("HDivider");

	o.style.height = h + "px";
	resizing = false;
}

function Toggle(obj) 
{
	var d = obj.style.display;
	obj.style.display = (d != "block") ? "block" : "none";
	if(d != "block")
		obj.style.zIndex = Drag.zindex++; 
}

function ToggleB(obj) 
{
	var d = obj.style.display;
	obj.style.display = ((d != "block") && (d != "")) ? "" : "none";
}

function isShown(id) 
{
	return ($$(id).style.display == "block");
}

function Update() 
{
	utWebUI.getTorrents("list=1");
	tdl = 0;
	tul = 0;
}

function UpdateStatus() 
{
	if((tdl == 0) && (tul == 0)) 
	{
		return;
	}
	var s = WUILang.Download + ": " + ffs(tdl) + "/" + WUILang.s;
	if(tdlimit>0 && tdlimit<100*1024*1024)
		s+="  ["+ffs(tdlimit) + "/" + WUILang.s+"]";
	s+="  " + WUILang.Total + ": " + ffs(tdb)+"  |  " + WUILang.Upload + ": " + ffs(tul) + "/" + WUILang.s;
	if(tulimit>0 && tulimit<100*1024*1024)
		s+="  ["+ffs(tulimit) + "/" + WUILang.s+"]";
	s+="  " + WUILang.Total + ": " + ffs(tub);

	if(utWebUI.bSpdDis == 1) 
	{
   		window.status = s;
   		window.defaultStatus = s;
   	}
	else 
	{
   		if(utWebUI.bSpdDis == 2) 
   		{
   			s = "rTorrent WebUI v" + version + " - " + s;
			if(document.title !=s)
      				document.title = s;
      		}
   	}
}

function E(_54) 
{
	$$("ermsg").innerHTML = _54;
	$$("al").style.visibility = "hidden";
	Show("al");
	$$("al").style.visibility = "visible";
	window.setTimeout("Hide('al')", 4000);
}

function formatDate(dt)
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

function FormatTL(_55, _56) 
{
	if(_56 == null) 
	{
		_55[2] = (_55[2]==null) ? "" : ffs(_55[2], 2);
		_55[3] = (_55[3]==null) ? "" : (_55[3]/10)+"%";
		_55[4] = (_55[4]==null) ? "" : ffs(_55[4], 2);
		_55[5] = (_55[5]==null) ? "" : ffs(_55[5], 2);
		_55[6] = (_55[6]==null) ? "" : (_55[6] ==- 1) ? "\u221e" : round(_55[6]/1000,3);
		_55[7] = (_55[7]==null) ? "" : ffs(_55[7]) + "/" + WUILang.s + "";
		_55[8] = (_55[8]==null) ? "" : ffs(_55[8]) + "/" + WUILang.s + "";
		_55[9] = (_55[9]==null) ? "" : (_55[9] <=- 1) ? "\u221e" : ft(_55[9]);
		if(_55[11] == null)
			_55[11] = "";
		if(_55[12] == null)
			_55[12] = "";
		_55[13] = (_55[13]==null) ? "" : FormatDPri(_55[13]);
		_55[14] = (_55[14]==null) ? "" : formatDate(_55[14]);
		_55[15] = (_55[15]==null) ? "" : ffs(_55[15], 2);
		for(var i=16; i<_55.length; i++)
			if(_55[i]==null) _55[i] = "";
	}
	else 
	{
		if(_55==null) _55="";
		else
		switch(_56) 
		{
			case 2: 
				_55 = ffs(_55, 2);
				break;
			case 3: 
				_55 = (_55 / 10) + "%";
				break;
			case 4: 
				_55 = ffs(_55, 2);
				break;
			case 5: 
				_55 = ffs(_55, 2);
				break;
			case 6: 
				_55 = (_55 ==- 1) ? "\u221e" : round(_55 / 1000, 3);
				break;
			case 7: 
				_55 = ffs(_55) + "/" + WUILang.s + "";
				break;
			case 8: 
				_55 = ffs(_55) + "/" + WUILang.s + "";
				break;
			case 9: 
				_55 = (_55 <=- 1) ? "\u221e" : ft(_55);
				break;
			case 13: 
				_55 = FormatDPri(_55);
				break;
			case 14: 
				_55 = formatDate(_55);
				break;
			case 15 : 
				_55 = ffs(_55, 2);
				break;
		}
	}
	return _55;
}

function FormatDPri(no) 
{
	var ret = ""
	switch(no) 
	{
		case 0:
			ret = "idle";
			break;
		case 1: 
			ret = "low";
			break;
		case 2:
			ret = "normal";
			break;
		case 3:
			ret = "high";
			break;
	}
	return(ret);
}

function FormatFPri(no) 
{
	var ret = ""
	switch(no) 
	{
		case -1:
			ret = "?";
			break;
		case 0:
			ret = "skip";
			break;
		case 1:
			ret = "normal";
			break;
		case 2:
			ret = "high";
			break;
	}
	return(ret);
}

function getYesNo(no)
{
	var ret = ""
	switch(no) 
	{
		case 0:
			ret = "no";
			break;
		case 1:
			ret = "yes";
			break;
	}
	return(ret);
}

function getTrkType(no)
{
	var ret = ""
	switch(no) 
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
}

function FormatPR(_57, _58) 
{
	if(_58 == null) 
	{
		_57[3] = (_57[3]==null) ? "" : _57[3]+"%";
		_57[4] = (_57[4]==null) ? "" : ffs(_57[4]);
		_57[5] = (_57[5]==null) ? "" : ffs(_57[5]);
		_57[6] = (_57[6]==null) ? "" : ffs(_57[6]) + "/" + WUILang.s + "";
		_57[7] = (_57[7]==null) ? "" : ffs(_57[7]) + "/" + WUILang.s + "";
		for(var i=8; i<_57.length; i++)
			if(_57[i]==null) _57[i] = "";
	}
	else 
	{
		if(_58==null)
			_57 = "";
		else
   		switch(_58) 
   		{
      			case 3 : 
      				_57 = _57+"%";
      				break;
			case 4 :
			case 5 :
      				_57 = ffs(_57);
      				break;
      			case 6 : 
      			case 7 : 
				_57 = ffs(_57) + "/" + WUILang.s + "";
      				break;
      		}
   	}
	return _57;
}

function FormatTR(_57, _58) 
{
	if(_58 == null) 
	{
   		_57[1] = (_57[1]==null) ? "" : getTrkType(_57[1]);
   		_57[2] = (_57[2]==null) ? "" : getYesNo(_57[2]);
		for(var i=3; i<_57.length; i++)
			if(_57[i]==null) _57[i] = "";
	}
	else 
	{
		if(_58==null)
			_57 = "";
		else
   		switch(_58) 
   		{
      			case 1 : 
      				_57 = getTrkType(_57);
      				break;
      			case 2 : 
      				_57 = getYesNo(_57);
      				break;
      		}
   	}
	return _57;
}

function FormatFL(_57, _58) 
{
	if(_58 == null) 
	{
   		_57[1] = (_57[1]==null) ? "" : ffs(_57[1], 2);
   		_57[2] = (_57[2]==null) ? "" : ffs(_57[2], 2);
   		_57[3] = (_57[3]==null) ? "" : _57[3] + "%";
   		_57[4] = FormatFPri(_57[4]);
		for(var i=5; i<_57.length; i++)
			if(_57[i]==null) _57[i] = "";
	}
	else 
	{
	        if(_58==null)
	        	_57="";
		else
   		switch(_58) 
   		{
      			case 1 : 
      				_57 = ffs(_57, 2);
      				break;
      			case 2 : 
      				_57 = ffs(_57, 2);
      				break;
      			case 3 : 
      				_57 = _57 + "%";
      				break;
      			case 4 : 
      				_57 = FormatFPri(_57);
      				break;
      		}
   	}
	return _57;
}

function getObjValue(id) 
{
	var o = $$(id), v = null;
	if(!o) 
	{
   		return null;
   	}
	var tn = o.tagName.toLowerCase();
	if(tn == "input") 
	{
   		switch(o.type) 
   		{
      			case "checkbox": 
      				v = (o.checked) ? 1 : 0;
      				break;
      			default: 
      				v = o.value;
		      		break;
      		}
   	}
	else 
	{
   		if(tn == "select") 
   		{
      			if(o.selectedIndex ==- 1) 
      			{
         			return null;
         		}
      			v = o.options[o.selectedIndex].value;
      		}
   		else 
   		{
      			v = o.value;
      		}
   	}
	return v;
}

function CentreObject(id) 
{
	try {
   	var o = $$(id);
   	var w = (iv(o.offsetWidth) == 0) ? iv(o.style.width) : iv(o.offsetWidth);
   	o.style.left = (getWindowWidth() / 2) - (w / 2) + "px";
   	} catch(e) {}
}

function Show(id) 
{
	$$(id).style.display = "block";
	$$(id).style.zIndex = Drag.zindex++; 
}

function Hide(id) 
{
	$$(id).style.display = "none";
}

function ShowModal(id) 
{
	Show("modalbg");
	Show(id);
}

function HideModal(id) 
{
	Hide(id);
	Hide("modalbg");
}

function vi(id) 
{
	$$(id).style.visibility = "visible";
}

function nv(id) 
{
	$$(id).style.visibility = "hidden";
}

function ToggleV(obj)
{
	if(obj.style.visibility == "hidden")
		obj.style.visibility = "visible";
	else
		obj.style.visibility = "hidden";
}

function CheckUpload(frm) 
{
	var _64 = $$("torrent_file").value;
	if(!_64.match(".torrent")) 
	{
		alert(WUILang.Not_torrent_file);
   		return false;
   	}
	$$("add_button").disabled = true;
	frm.action = WUIResorces.AddTorrentURL+"?";
	if($$("torrents_start_stopped").checked)
		frm.action = frm.action + 'torrents_start_stopped=1&';
	if($$("not_add_path").checked)
		frm.action = frm.action + 'not_add_path=1&';
	var dir = $$("dir_edit").value;
	dir = dir.replace(/(^\s+)|(\s+$)/g, "");
	if(dir.length)
		frm.action = frm.action + 'dir_edit='+encodeURIComponent(dir)+'&';
	var lbl = $$("tadd_label").value;
	lbl = lbl.replace(/(^\s+)|(\s+$)/g, "");
	if(lbl.length)
		frm.action = frm.action + 'label='+encodeURIComponent(lbl);
	return true;
}

function CheckURLUpload(frm) 
{
   	$$("add_url").disabled = true;
	frm.action = WUIResorces.AddTorrentURL+"?";
	if($$("torrents_start_stopped").checked)
		frm.action = frm.action + 'torrents_start_stopped=1&';
	if($$("not_add_path").checked)
		frm.action = frm.action + 'not_add_path=1&';
	var dir = $$("dir_edit").value;
	dir = dir.replace(/(^\s+)|(\s+$)/g, "");
	if(dir.length)
		frm.action = frm.action + 'dir_edit='+encodeURIComponent(dir)+'&';
	var lbl = $$("tadd_label").value;
	lbl = lbl.replace(/(^\s+)|(\s+$)/g, "");
	if(lbl.length)
		frm.action = frm.action + 'label='+encodeURIComponent(lbl);
	return true;
}

function US() 
{
	$$("torrent_file").value = "";
	$$("add_button").disabled = false;
}

function USurl() 
{
	$$("url").value = "";
	$$("add_url").disabled = false;
}

var amnu = "st_gl";

function ch_mnu(id) 
{
	$$(amnu).style.display = "none";
	$$(id).style.display = "block";
	$$("mnu_" + amnu).className = "";
	$$("mnu_" + id).className = "focus";
	amnu = id;
}

function setCB(obj, val, _68) 
{
	if(val == null) 
	{
   		obj.checked = _68;
   	}
	else 
	{
   		obj.checked = (iv(val) == 1) ? true : false;
   	}
}

var searchSities = 
[
	{ name: 'Mininova', 		url: 'http://www.mininova.org/search/?utorrent&search=' },
	{ name: 'HQTtracker.ru', 	url: 'http://hqtracker.ru/browse.php?cat=0&search_in=1&search=' },
	{ name: 'The Pirate Bay', 	url: 'http://thepiratebay.org/search.php?q=' },
	{ name: 'INTERFILM', 		url: 'http://interfilm.nu/movie/?do=search&str=' },
	{ name: 'IsoHunt', 		url: 'http://isohunt.com/torrents.php?ext=&op=and&ihq=' },
	{ name: 'VideoTracker.ru', 	url: 'http://videotracker.ru/browse.php?cat=0&search_in=1&search=' },
	{ name: '', 			url: '' },
	{ name: 'Google', 		url: 'http://google.com/search?q=' }
];

var seurl = 0;

function Search() 
{
	var _6b = $$("query").value;
	window.open(searchSities[seurl].url + _6b, "_blank");
}

function setSearch(i) 
{
	seurl=i;
}

function ShowSearch() 
{
	ContextMenu.clear();
	for(var i in searchSities)
	{
		if(searchSities[i].name=='')
			ContextMenu.add([CMENU_SEP]);
		else
		if(seurl==i)
			ContextMenu.add([CMENU_SEL, searchSities[i].name, "setSearch("+i+")"]);
		else
			ContextMenu.add([searchSities[i].name, "setSearch("+i+")"]);
	}
	var obj = $$("search");
	mouse.X = getOffsetLeft(obj)-5;
	mouse.Y = getOffsetTop(obj)+obj.offsetHeight+5;
	ContextMenu.show();
}

function getStatusIcon(state, completed) 
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
	return [icon, status];
}

function labelContextMenu(obj)
{
	utWebUI.trtTable.clearSelection();
	utWebUI.switchLabel(obj);
	utWebUI.trtTable.fillSelection();
	var sr = utWebUI.trtTable.rowSel;
	var id = null;
	for(var k in sr) 
	{
		if(sr[k] == true) 
		{
			id = k;
			break;
      		}
	}
	if(id)
	{
		utWebUI.createMenu(null, id);
		ContextMenu.show();
	}
	else
		ContextMenu.hide();
}

var utWebUI = new Object();
utWebUI = 
{
"init" : function() 
	{
		this.trtColumns = [ 
			{"text" : WUILang.Name, 	"width" : "200px", 	"type" : TYPE_STRING}, 
      			{"text" : WUILang.Status, 	"width" : "100px", 	"type" : TYPE_STRING},
   			{"text" : WUILang.Size, 	"width" : "60px", 	"type" : TYPE_NUMBER},
	   		{"text" : WUILang.Done, 	"width" : "80px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Downloaded, 	"width" : "100px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Uploaded, 	"width" : "100px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Ratio, 	"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.DL, 		"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.UL, 		"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.ETA, 		"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Label, 	"width" : "60px", 	"type" : TYPE_STRING},
			{"text" : WUILang.Peers, 	"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Seeds, 	"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Priority, 	"width" : "80px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Created_on,	"width" : "100px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Remaining, 	"width" : "90px", 	"type" : TYPE_NUMBER}
			];
		this.flsColumns = [ 
			{"text" : WUILang.Name, 	"width" : "200px",	"type" : TYPE_STRING},
			{"text" : WUILang.Size, 	"width" : "60px", 	"type" : TYPE_NUMBER, 	"align" : ALIGN_RIGHT},
			{"text" : WUILang.Done, 	"width" : "80px", 	"type" : TYPE_NUMBER},
			{"text" : "%", 			"width" : "100px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Priority, 	"width" : "80px", 	"type" : TYPE_NUMBER}
	   		];
		this.trkColumns = [ 
			{"text" : WUILang.Name,		"width" : "200px", 	"type" : TYPE_STRING},
			{"text" : WUILang.Type, 	"width" : "60px", 	"type" : TYPE_STRING, 	"align" : ALIGN_RIGHT},
			{"text" : WUILang.Enabled, 	"width" : "60px", 	"type" : TYPE_STRING, 	"align" : ALIGN_RIGHT},
			{"text" : WUILang.Group, 	"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Seeds, 	"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Peers, 	"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.scrapeDownloaded, 	"width" : "80px", 	"type" : TYPE_NUMBER}
   			];
		this.prsColumns = [ 
			{"text" : WUILang.Name, 	"width" : "100px", 	"type" : TYPE_STRING},
			{"text" : WUILang.ClientVersion,"width" : "120px", 	"type" : TYPE_STRING},
			{"text" : WUILang.Flags, 	"width" : "60px", 	"type" : TYPE_STRING, 	"align" : ALIGN_RIGHT},
			{"text" : WUILang.Done, 	"width" : "80px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Downloaded, 	"width" : "100px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.Uploaded, 	"width" : "100px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.DL, 		"width" : "60px", 	"type" : TYPE_NUMBER},
			{"text" : WUILang.UL, 		"width" : "60px", 	"type" : TYPE_NUMBER}
			];
		this.torrents = new Object();
		this.files = new Array();
		this.dirs = new Array();
		this.trackers = new Array();
		this.settings = new Object();
		this.props = new Object();
		this.peers = new Object();
		this.labels = new Object();
		this.labels["-_-_-all-_-_-"] = 0;
		this.labels["-_-_-dls-_-_-"] = 0;
		this.labels["-_-_-com-_-_-"] = 0;
		this.labels["-_-_-act-_-_-"] = 0;
		this.labels["-_-_-iac-_-_-"] = 0;
		this.labels["-_-_-nlb-_-_-"] = 0;
		this.actLbl = "-_-_-all-_-_-";
		this.cLabels = new Object();
		this.dID = "";
		this.bShowMessage = 0;
		this.trtTable = new dxSTable();
		this.flsTable = new dxSTable();
		this.trkTable = new dxSTable();
		this.prsTable = new dxSTable();
		this.speedGraph = new rSpeedGraph();
		this.url = window.location.href.substr(0,window.location.href.lastIndexOf("/")+1);
		this.timer = new Timer();
		this.update = null;
		this.interval =- 1;
		this.iLoad = true;
		this.cID = "";
		this.bDetails = 1;
		this.bCategories = 1;
		this.bConfDel = 1;
		this.bAltCol = 0;
		this.bSpdDis = 0;
		this.isLoading = false;
		this.noUpdate = false;
		this.pID = "";
		this.hSplit = 0.88;
		this.vSplit = 0.5;
		this.trtSortR = 0;
		this.flsSortR = 0;
		this.trkSortR = 0;
		this.prsSortR = 0;
		this.minRows = 100;
		this.activeView = null;
		this.delmode = "remove";
		this.trtTable.ondelete = this.Remove;
		this.trtTable.onmove = this.Save;
		this.trtTable.onsort = this.trtSort;
		this.trtTable.onselect = this.trtSelect;
		this.trtTable.ondblclick = function(obj) { utWebUI.showDetails(obj.id); return(false); };
		this.flsTable.onmove = this.Save;
		this.flsTable.onsort = this.flsSort;
		this.flsTable.onselect = this.flsSelect;
		this.flsTable.ondblclick = function(obj) 
			{
				if(!utWebUI.fileListMode && (utWebUI.dID!=""))
				{
					var lnk = utWebUI.flsTable.getAttr(obj.id, "link");
		                	if(lnk!=null)
		                	{
		                		utWebUI.dirs[utWebUI.dID].setDirectory(lnk);
						utWebUI.flsTable.clearRows();
				    		utWebUI.redrawFiles(utWebUI.dID);
					}
				}
				return(false);
			}
		this.trkTable.onmove = this.Save;
		this.trkTable.onsort = this.trkSort;
		this.trkTable.onselect = this.trkSelect;
		this.prsTable.onmove = this.Save;
		this.prsTable.onsort = this.prsSort;
		this.prsTable.onselect = this.prsSelect;
		this.prsTable.ondblclick = 
			function(obj) 
			{ 
				if(obj.id && utWebUI.peers[obj.id] && utWebUI.peers[obj.id].length)
					window.open(WUIResorces.RIPEURL + utWebUI.peers[obj.id][0], "_blank");
				return(false);
			 };
		this.updateInterval = 3000;
		this.reqTimeout = 60000;
		this.fileListMode = false;

		this.flsTable.oldFilesSortAlphaNumeric = this.flsTable.sortAlphaNumeric;
		this.flsTable.oldFilesSortNumeric = this.flsTable.sortNumeric;

		this.flsTable.sortAlphaNumeric = function(x, y) 
			{
				if(!utWebUI.fileListMode && utWebUI.dID)
				{
				        var dir = utWebUI.dirs[utWebUI.dID];
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
		this.flsTable.sortNumeric = function(x, y) 
			{
				if(!utWebUI.fileListMode && utWebUI.dID)
				{
				        var dir = utWebUI.dirs[utWebUI.dID];
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

	}
, "config" : function() 
	{
		if(typeof $_COOKIE["webui.trt.colorder"] != "undefined") 
		{
			this.trtTable.colOrder = $_COOKIE["webui.trt.colorder"];
		}
		if(typeof $_COOKIE["webui.trt.sindex"] != "undefined") 
		{
			this.trtTable.sIndex = iv($_COOKIE["webui.trt.sindex"]);
		}
		if(typeof $_COOKIE["webui.trt.rev"] != "undefined") 
		{
			this.trtTable.reverse = iv($_COOKIE["webui.trt.rev"]);
		}
		if(typeof $_COOKIE["webui.fls.colorder"] != "undefined") 
		{
			this.flsTable.colOrder = $_COOKIE["webui.fls.colorder"];
		}
		if(typeof $_COOKIE["webui.fls.sindex"] != "undefined") 
		{
			this.flsTable.sIndex = iv($_COOKIE["webui.fls.sindex"]);
		}
		if(typeof $_COOKIE["webui.fls.rev"] != "undefined") 
		{
			this.flsTable.reverse = iv($_COOKIE["webui.fls.rev"]);
		}
		if(typeof $_COOKIE["webui.trk.colorder"] != "undefined") 
		{
			this.trkTable.colOrder = $_COOKIE["webui.trk.colorder"];
		}
		if(typeof $_COOKIE["webui.trk.sindex"] != "undefined") 
		{
			this.trkTable.sIndex = iv($_COOKIE["webui.trk.sindex"]);
		}
		if(typeof $_COOKIE["webui.trk.rev"] != "undefined") 
		{
			this.trkTable.reverse = iv($_COOKIE["webui.trk.rev"]);
		}
		if(typeof $_COOKIE["webui.prs.colorder"] != "undefined") 
		{
			this.prsTable.colOrder = $_COOKIE["webui.prs.colorder"];
		}
		if(typeof $_COOKIE["webui.prs.sindex"] != "undefined") 
		{
			this.prsTable.sIndex = iv($_COOKIE["webui.prs.sindex"]);
		}
		if(typeof $_COOKIE["webui.trk.rev"] != "undefined") 
		{
			this.prsTable.reverse = iv($_COOKIE["webui.prs.rev"]);
		}
		if(typeof $_COOKIE["webui.show_cats"] != "undefined") 
		{
			this.bCategories = parseInt($_COOKIE["webui.show_cats"]);
			$$("CatList").style.display = (this.bCategories) ? "block" : "none";
			$$("webui.show_cats").checked = (this.bCategories) ? true : false;
		}
		if(typeof $_COOKIE["webui.show_dets"] != "undefined") 
		{
			this.bDetails = parseInt($_COOKIE["webui.show_dets"]);
			$$("tdetails").style.display = (this.bDetails) ? "block" : "none";
			if(this.bDetails) 
			{
				tdTabs.show("gcont");
			}
			$$("webui.show_dets").checked = (this.bDetails) ? true : false;
      		}
		if(typeof $_COOKIE["webui.needmessage"] != "undefined") 
   		{
			this.bShowMessage = parseInt($_COOKIE["webui.needmessage"]);
			$$("webui.needmessage").checked = (this.bShowMessage) ? true : false;
		}
		if(typeof $_COOKIE["webui.confirm_when_deleting"] != "undefined") 
		{
			this.bConfDel = parseInt($_COOKIE["webui.confirm_when_deleting"]);
			$$("webui.confirm_when_deleting").checked = (this.bConfDel) ? true : false;
		}
		if(typeof $_COOKIE["webui.alternate_color"] != "undefined") 
		{
			this.bAltCol = parseInt($_COOKIE["webui.alternate_color"]);
			$$("webui.alternate_color").checked = (this.bAltCol) ? true : false;
			this.trtTable.colorEvenRows = (this.bAltCol) ? true : false;
			this.flsTable.colorEvenRows = (this.bAltCol) ? true : false;
			this.trkTable.colorEvenRows = (this.bAltCol) ? true : false;
			this.prsTable.colorEvenRows = (this.bAltCol) ? true : false;
		}
		if(typeof $_COOKIE["webui.speed_display"] != "undefined") 
		{
			this.bSpdDis = iv($_COOKIE["webui.speed_display"]);
			$$("webui.speed_display").value = this.bSpdDis;
		}
		if(typeof $_COOKIE["webui.update_interval"] != "undefined") 
		{
			this.updateInterval = iv($_COOKIE["webui.update_interval"]);
			$$("webui.update_interval").value = this.updateInterval;
		}
		if(typeof $_COOKIE["webui.reqtimeout"] != "undefined") 
		{
			this.reqTimeout = iv($_COOKIE["webui.reqtimeout"]);
			$$("webui.reqtimeout").value = this.reqTimeout;
		}
   		if(typeof $_COOKIE["webui.hsplit"] != "undefined") 
   		{
			this.hSplit = parseFloat($_COOKIE["webui.hsplit"]);
      		}
		if(typeof $_COOKIE["webui.vsplit"] != "undefined") 
		{
			this.vSplit = parseFloat($_COOKIE["webui.vsplit"]);
      		}
		if(typeof $_COOKIE["webui.trt.sortrev"] != "undefined") 
		{
			this.trtSortR = iv($_COOKIE["webui.trt.sortrev"]);
		}
		if(typeof $_COOKIE["webui.fls.sortrev"] != "undefined") 
		{
			this.flsSortR = iv($_COOKIE["webui.fls.sortrev"]);
		}
		if(typeof $_COOKIE["webui.trk.sortrev"] != "undefined") 
		{
			this.trkSortR = iv($_COOKIE["webui.trk.sortrev"]);
		}
		if(typeof $_COOKIE["webui.prs.sortrev"] != "undefined") 
		{
			this.prsSortR = iv($_COOKIE["webui.prs.sortrev"]);
		}
		if(typeof $_COOKIE["webui.minrows"] != "undefined") 
		{
			this.minRows = iv($_COOKIE["webui.minrows"]);
			this.trtTable.maxRows = this.minRows;
			this.flsTable.maxRows = this.minRows;
			this.trkTable.maxRows = this.minRows;
			this.prsTable.maxRows = this.minRows;
			$$("webui.minrows").value = this.minRows;
      		}
   	}
, "initDone" : function()
	{
	}
, "updatePeers" : function()
	{
		if(this.activeView=='PeerList')
		{
			if(this.dID != "") 
				this.Request("?action=getpeers&hash="+this.dID,[this.addPeers, this]);
			else
				this.clearPeers();
		}
	}
, "clearPeers" : function()
	{
		this.prsTable.clearRows();
		for(var k in this.peers) 
      			delete this.peers[k];
	}
, "addPeers" : function(_83) 
	{
  		var d = eval("(" + _83 + ")");
   		var i, l = d.peers.length;
   		var sl = this.prsTable.dBody.scrollLeft;
   		for(i = 0; i < l; i++) 
   		{
      			var sId = d.peers[i][0];
			if(typeof (this.peers[sId]) == "undefined") 
			{
				this.peers[sId] = d.peers[i].slice(1);
				this.prsTable.addRow(this.peers[sId], sId);
         		}
			else 
			{
				for(var j = 1; j < d.peers[i].length; j++) 
				{
					this.prsTable.setValue(sId, j-1, d.peers[i][j]);
					this.peers[sId][j-1] = d.peers[i][j];
				}
			}
			this.peers[sId][24] = true;
		}
		this.prsTable.dBody.scrollLeft = sl;
		for(var k in this.peers) 
		{
			if(this.peers[k][24]!=true)
			{
        			delete this.peers[k];
	        	 	this.prsTable.removeRow(k);
	        	}
			else
				this.peers[k][24] = false;
		}
		if(this.prsTable.sIndex !=- 1)
			this.prsTable.Sort();
		else
			utWebUI.prsTable.resizeHack();
		delete _83;
		d = null;
	}
, "setActiveView" : function(id)
	{
		$("#tooltip").remove();
		this.activeView=id;
	}
, "Request" : function(qs, onComplite, isASync) 
	{
		new Ajax(this.url + qs, "GET", isASync, onComplite, 
			function() { (isASync == false) ? utWebUI.Timeout() : utWebUI.TimeoutLog(); }, this.reqTimeout);
	}
, "RequestWithTimeout" : function(qs, onComplite, onTimeout, isASync) 
	{
		new Ajax(this.url + qs, "GET", isASync, onComplite, onTimeout, this.reqTimeout);
   	}
, "Timeout" : function() 
	{
		log(WUILang.Request_timed_out);
		if(!isShown("cover"))
		{
			resizeUI();
			ShowUI();
		}
	}
, "TimeoutLog" : function() 
	{
		log(WUILang.Request_timed_out);
	}
, "Perform" : function(_7a) 
	{
		if(_7a == "pause") 
		{
			var hp = this.getHashes("unpause");
			if(hp != "") 
			{
				this.Request("?action=unpause" + hp);
			}
		}
		var h = this.getHashes(_7a);
		if(h == "") 
		{
			return;
		}
		if((_7a.indexOf("remove")==0) && (h.indexOf(this.dID) >- 1)) 
		{
			this.dID = "";
			this.clearDetails();
		}
		this.getTorrents(_7a + h + "&list=1");
   	}
, "isCommandEnabled" : function(act,status) 
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
	}

, "getHashes" : function(act) 
	{
		var h = "";
		var sr = this.trtTable.rowSel;
		for(var k in sr) 
		{
			if((sr[k] == true) && this.isCommandEnabled(act,this.torrents[k][0]))
				h += "&hash=" + k;
      		}
		return h;
	}
, "Start" : function() {
   this.Perform("start");
   }
, "Pause" : function() {
   this.Perform("pause");
   }
, "Stop" : function() {
   this.Perform("stop");
   }
, "Remove" : function()
	{
		if(utWebUI.trtTable.selCount>0)
		{
			if(utWebUI.bConfDel)
			{
				this.delmode = "remove";
				askYesNo( WUILang.Remove_torrents, WUILang.Rem_torrents_prompt, "utWebUI.doRemove()" );
	      		}
			else
				utWebUI.Perform("remove");
		}
	}
, "doRemove" : function() {
   this.Perform(this.delmode);
   }
, "Recheck" : function() {
   this.Perform("recheck");
   }
, "getTorrents" : function(qs) {
   if(this.update) {
      window.clearTimeout(this.update);
      }
   this.timer.start();
   if(qs != "list=1") {
      qs = "action=" + qs;
      }
   qs = (this.cID == "") ? qs : (qs + "&cid=" + this.cID);
   this.RequestWithTimeout("?" + qs + "&getmsg=1", [this.addTorrents, this], 
	   function() 
	   { 
	   	utWebUI.TimeoutLog(); 
	   	Update();
	   });
   }
, "fillAdditionalTorrentsCols" : function(sId,_87)
	{
		return(_87);
	}
, "updateAdditionalTorrentsCols" : function(sId)
	{
	}
, "filterByLabel" : function(sId)
	{
		if(this.trtTable.getAttr(sId, "label").indexOf(this.actLbl) >- 1)
			this.trtTable.unhideRow(sId);
		else 
			this.trtTable.hideRow(sId);
	}
, "addTorrents" : function(_83) 
	{
   		var d = eval("(" + _83 + ")");
   		var i, l = d.torrents.length;
   		this.noUpdate = true;
   		var sl = this.trtTable.dBody.scrollLeft;
   		tul = 0;
		tdl = 0;
		var ln = 27;
   		for(i = 0; i < l; i++) 
   		{
      			var _87 = d.torrents[i];
      			var sId = _87[0];	// hash
      			var _89 = _87[1];	// state
      			var _8a = _87[4];	
			var _8b = getStatusIcon(_89, _8a);
			var _8c = _8b[0];
			var t = _87[8];
			_87[8] = _87[9];
			_87[9] = t;
			tdl += _87[8];
			tul += _87[9];
			_87.splice(3, 0, _8b[1]);
			if(typeof this.labels[sId] == "undefined") 
			{
				this.labels[sId] = "";
         		}
			var lbl = this.getLabels(sId, _87[12], _8a, _87[9], _87[10]);
			ln = _87.length - 1;
			if(typeof (this.torrents[sId]) == "undefined") 
			{
				this.torrents[sId] = _87.slice(1);
				this.labels[sId] = lbl;
				_87.splice(0, 2);
				_87[11] = _87[11] + " (" + _87[12] + ")";
				_87[12] = _87[13] + " (" + _87[14] + ")";
				_87[13] = _87[15];
				_87.splice(13, 2);
				_87.splice(16, ln-19);
				this.trtTable.addRow(this.fillAdditionalTorrentsCols(sId,_87), sId, _8c, {"label" : lbl});
				this.noUpdate = false;
				this.getTrackers(sId);
         		}
			else 
			{
				var aD = this.torrents[sId];
				if(lbl != this.labels[sId]) 
				{
					this.labels[sId] = lbl;
					this.trtTable.setAttr(sId, "label", lbl);
					this.filterByLabel(sId);
				}
				if((aD[0] != _87[1]) || (aD[3] != _87[4]) || (aD[2] != _87[3]))
				{
					this.torrents[sId][0] = _87[1];
					this.trtTable.setIcon(sId, _8c);
					this.trtTable.setValue(sId, 1, _8b[1]);
				}
				var needUpdateTrackers = false;
				if((aD[12] != _87[13]) || (aD[13] != _87[14])) 
				{
					this.torrents[sId][12] = _87[13];
					this.torrents[sId][13] = _87[14];
					this.trtTable.setValue(sId, 11, _87[13] + " (" + _87[14] + ")");
					this.noUpdate = false;
					needUpdateTrackers = true;
            			}
				if((aD[14] != _87[15]) || (aD[15] != _87[16])) 
				{
					this.torrents[sId][14] = _87[15];
					this.torrents[sId][15] = _87[16];
					this.trtTable.setValue(sId, 12, _87[15] + " (" + _87[16] + ")");
					this.noUpdate = false;
					needUpdateTrackers = true;
				}
				for(var j = 16; j < ln; j++) 
				{
					if(aD[j] != _87[j + 1]) 
					{
						this.torrents[sId][j] = _87[j + 1];
						if(j<19)
						{
							this.trtTable.setValue(sId, j - 3, _87[j + 1]);
							this.noUpdate = false;
						}
					}
				}
				for(var j = 1; j < 12; j++) 
				{
					if(aD[j] != _87[j + 1]) 
					{
						this.noUpdate = false;
						this.torrents[sId][j] = _87[j + 1];
						if((j == 4) && (this.dID == sId)) 
						{
							this.updateFiles(sId);
						}
						this.trtTable.setValue(sId, j - 1, _87[j + 1]);
					}
				}
				this.updateAdditionalTorrentsCols(sId);
				if(needUpdateTrackers && (this.dID == sId))
					this.getTrackers(sId);
			}
			this.torrents[sId][ln] = true;
			_87 = null;
		}
		this.trtTable.dBody.scrollLeft = sl;
		utWebUI.speedGraph.addData(tul,tdl);
		var wasRemoved = false;
		for(var k in this.torrents) 
		{
			if(this.torrents[k][ln]!=true)
			{
        			delete this.torrents[k];
				if(this.labels[k].indexOf("-_-_-nlb-_-_-") >- 1) 
				{
					this.labels["-_-_-nlb-_-_-"]--;
		            	}
	        	 	if(this.labels[k].indexOf("-_-_-com-_-_-") >- 1) 
        	 		{
            				this.labels["-_-_-com-_-_-"]--;
	            		}
	        	 	if(this.labels[k].indexOf("-_-_-dls-_-_-") >- 1) 
        	 		{
	        	    		this.labels["-_-_-dls-_-_-"]--;
        	    		}
	         		if(this.labels[k].indexOf("-_-_-act-_-_-") >- 1) 
		         	{
        		    		this.labels["-_-_-act-_-_-"]--;
            			}
		         	if(this.labels[k].indexOf("-_-_-iac-_-_-") >- 1) 
        		 	{
            				this.labels["-_-_-iac-_-_-"]--;
            			}
		         	this.labels["-_-_-all-_-_-"]--;
        		 	delete this.labels[k];
	        	 	this.trtTable.removeRow(k);
	        	 	wasRemoved = true;
	        	}
			else
				this.torrents[k][ln] = false;
		}

		this.cID = d.torrentc;
		this.loadLabels(d.label.slice(0));
		this.updateLabels(wasRemoved);
		d = null;
		this.loadTorrents();
		_83 = null;
		this.Request("?action=gettotal",[this.getTotal, this]);
	}
, "getTotal" : function( ttl )
	{
		var d = eval("(" + ttl + ")");
   		tub = d.total[0];
   		tdb = d.total[1];
   		tulimit = d.total[2];
   		tdlimit = d.total[3];
		ttl = null;
	}
, "loadTorrents" : function() 
	{
		if(this.iLoad) 
		{
			this.iLoad = false;
			this.trtTable.calcSize();
			this.trtTable.resizeHack();
		}
		else 
		{
			if(this.actLbl != "-_-_-all-_-_-") 
			{
				this.trtTable.refreshRows();
         		}
      		}
		if((this.trtTable.sIndex !=- 1) && !this.noUpdate) 
		{
			this.trtTable.Sort();
		}
		this.setInterval();
		this.update = window.setTimeout("Update()", this.interval);
		this.updateDetails();
		this.noUpdate = false;
   	}
, "setInterval" : function() 
	{
		this.timer.stop();
		if(this.interval ==- 1) 
		{
			this.interval = this.updateInterval + this.timer.interval * 4;
      		}
		else 
		{
			this.interval = parseInt((this.interval + this.updateInterval + this.timer.interval * 4) / 2);
		}
   	}
, "setUpdateInterval" : function() 
	{
		var v = $$("webui.update_interval").value;
		this.updateInterval = iv(v);
	}
, "loadLabels" : function(d) 
	{
		var p = $$("lbll"), i, l, k, temp = new Array();
		for(i = 0, l = d.length; i < l; i++) 
		{
			k = d[i][0], v = d[i][1];
			this.labels["-_-_-" + k + "-_-_-"] = v;
			this.cLabels[k] = 1;
			temp["-_-_-" + k + "-_-_-"] = true;
			if(!$$("-_-_-" + k + "-_-_-")) 
			{
				var o = document.createElement("LI");
				o.onclick = function() { utWebUI.switchLabel(this); return(false); };
				addRightClickHandler( o, labelContextMenu );
				o.id = "-_-_-" + k + "-_-_-";
				o.innerHTML = escapeHTML(k) + "&nbsp;(<span id=\"-_-_-" + k + "-_-_-c\">" + v + "</span>)";
				if(i == 0) 
				{
					p.appendChild(o);
				}
				else 
				{
					var ps = $$("-_-_-" + d[i - 1][0] + "-_-_-");
					if(ps.nextSibling) 
					{
						p.appendChild(o);
					}
					else 
					{
						p.insertBefore(o, ps.nextSibling);
					}
				}
			}
		}
		var _9a = false;
		for(i = 0, l = p.childNodes.length; i < l; i++) 
		{
			if(typeof p.childNodes[i] == "undefined") 
			{
				continue;
			}
			k = p.childNodes[i].id;
			if(typeof k == "undefined") 
			{
				continue;
			}
			if(typeof (temp[k]) == "undefined") 
			{
				p.removeChild(p.childNodes[i]);
				delete this.labels[k];
				delete this.cLabels[k.substr(1, k.length - 2)];
				if(this.actLbl == k) 
				{
					_9a = true;
				}
			}
		}
		if(_9a) 
		{
			this.actLbl = "";
			this.switchLabel($$("-_-_-all-_-_-"));
		}
   	}

, "getLabels" : function(id, lbl, _9d, dls, uls) {
   if(lbl == "") {
      lbl += "-_-_-nlb-_-_-";
      if(this.labels[id].indexOf("-_-_-nlb-_-_-") ==- 1) {
         this.labels["-_-_-nlb-_-_-"]++;
         }
      }
   else {
      if(this.labels[id].indexOf("-_-_-nlb-_-_-") >- 1) {
         this.labels["-_-_-nlb-_-_-"]--;
         }
      }
   lbl = "-_-_-" + lbl + "-_-_-";
   if(_9d < 1000) {
      lbl += "-_-_-dls-_-_-";
      if(this.labels[id].indexOf("-_-_-dls-_-_-") ==- 1) {
         this.labels["-_-_-dls-_-_-"]++;
         }
      if(this.labels[id].indexOf("-_-_-com-_-_-") >- 1) {
         this.labels["-_-_-com-_-_-"]--;
         }
      }
   else {
      lbl += "-_-_-com-_-_-";
      if(this.labels[id].indexOf("-_-_-com-_-_-") ==- 1) {
         this.labels["-_-_-com-_-_-"]++;
         }
      if(this.labels[id].indexOf("-_-_-dls-_-_-") >- 1) {
         this.labels["-_-_-dls-_-_-"]--;
         }
      }
   if((dls >= 1024) || (uls >= 1024)) {
      lbl += "-_-_-act-_-_-";
      if(this.labels[id].indexOf("-_-_-act-_-_-") ==- 1) {
         this.labels["-_-_-act-_-_-"]++;
         }
      if(this.labels[id].indexOf("-_-_-iac-_-_-") >- 1) {
         this.labels["-_-_-iac-_-_-"]--;
         }
      }
   else {
      lbl += "-_-_-iac-_-_-";
      if(this.labels[id].indexOf("-_-_-iac-_-_-") ==- 1) {
         this.labels["-_-_-iac-_-_-"]++;
         }
      if(this.labels[id].indexOf("-_-_-act-_-_-") >- 1) {
         this.labels["-_-_-act-_-_-"]--;
         }
      }
   lbl += "-_-_-all-_-_-";
   if(this.labels[id] == "") {
      this.labels["-_-_-all-_-_-"]++;
      }
   return lbl;
   }
, "setLabel" : function(lbl) 
	{
		var req = '';
   		var sr = this.trtTable.rowSel;
   		for(var k in sr) 
   		{
      			if(!sr[k]) 
      			{
         			continue;
        		}
      			if(this.torrents[k][11] != lbl) 
      			{
      				req += ("&hash=" + k + "&s=label&v=" + encodeURIComponent(lbl));
        		}
		}
		if(req.length>0)
		{
			this.Request("?action=setlabel"+req+"&list=1",[this.addTorrents, this]);
			tul = 0;
			tdl = 0;
		}
   	}
, "removeLabel" : function() 
	{
		var req = '';
   		var sr = this.trtTable.rowSel;
   		for(var k in sr) 
   		{
      			if(!sr[k]) 
      			{
         			continue;
         		}
      			if(this.torrents[k][11] != "") 
      			{
         			req += ("&hash=" + k + "&s=label&v=");
         		}
      		}
		if(req.length>0)
		{
			this.Request("?action=setlabel"+req+"&list=1",[this.addTorrents, this]);
			tul = 0;
			tdl = 0;
		}
   	}
, "newLabel" : function() {
   if(this.trtTable.selCount == 1) {
      var sr = this.trtTable.rowSel;
      for(var k in sr) {
         if(sr[k]) {
            var lbl = this.torrents[k][11];
            if(lbl == "") {
               $$("txtLabel").value = "New Label";
               }
            else {
               $$("txtLabel").value = this.torrents[k][11];
               }
            }
         }
      }
   else {
      $$("txtLabel").value = "New Label";
      }
   ShowModal("dlgLabel");
   }
, "createLabel" : function() 
	{
   		var lbl = $$("txtLabel").value;
		lbl = lbl.replace(/(^\s+)|(\s+$)/g, "");
		lbl = lbl.replace(/\"/g, "'");
   		if(lbl == "") 
   		{
      			return;
      		}
   		var sr = this.trtTable.rowSel;
   		var req = "";
   		for(var k in sr) 
   		{
      			if(!sr[k]) 
      			{
         			continue;
         		}
	      		if(this.torrents[k][11] != lbl) 
      			{
         			req+=("&hash=" + k + "&s=label&v=" + encodeURIComponent(lbl));
	         	}
      		}
		if(req.length>0)
		{
			this.Request("?action=setlabel"+req+"&list=1",[this.addTorrents, this]);
			tul = 0;
			tdl = 0;
		}
   	}
, "updateLabels" : function(wasRemoved) {
   for(var k in this.labels) {
      if(k.substr(0, 5) == "-_-_-") {
         $$(k + "c").innerHTML = this.labels[k];
         }
      }
   }
, "switchLabel" : function(o) {
   if(o.id == this.actLbl) {
      return;
      }
   if((this.actLbl != "") && ($$(this.actLbl) != null)) {
      $$(this.actLbl).className = "";
      }
   o.className = "sel";
   this.actLbl = o.id;
   for(var k in this.torrents) {
      if(this.trtTable.getAttr(k, "label").indexOf(this.actLbl) >- 1) {
         this.trtTable.unhideRow(k);
         }
      else {
         this.trtTable.hideRow(k);
         }
      }
   this.trtTable.clearSelection();
   if(this.dID != "") {
      this.dID = "";
      this.clearDetails();
      }
   this.trtTable.refreshRows();
   }
, "getPlugins" : function() 
	{
   		this.Request("?action=getplugins", [this.getUISettings, this], false);
   	}
, "getUISettings" : function(_ae) 
	{
   		this.Request("?action=getuisettings", [this.addSettings, this], _ae);
   	}
, "showSettings" : function() 
	{
		if(isShown("stg"))
			Hide("stg");
		else
	   		this.Request("?action=getsettings", [this.addAndShowSettings, this], true);
   	}
, "addAndShowSettings" : function(_af) 
	{
		this.addSettings(_af);
		Show("stg");
	}
, "addSettings" : function(_af) 
	{
   		var d = eval("(" + _af + ")"), v;
   		for(var i = 0, l = d.settings.length; i < l; i++) 
   		{
      			v = d.settings[i][2];
      			if((v == "true") || (v == "auto") || (v == "on"))
      			{
         			v = "1";
         		}
      			if(v == "false") 
      			{
         			v = "0";
         		}
      			this.settings[d.settings[i][0]] = {"t" : d.settings[i][1], "v" : v};
      		}
   		this.loadSettings();
   	}
, "loadSettings" : function() 
	{
		var t, v, o;
   		for(var k in this.settings) 
   		{
      			t = this.settings[k].t;
      			v = this.settings[k].v;
      			o = $$(k);
      			if(!o) 
      			{
         			continue;
         		}
      			if(o.type == "checkbox") 
      			{
         			o.checked = eval(v);
         		}
      			else 
      			{
         			if((o.tagName.toLowerCase() == "select") && (v == "")) 
         			{
            				o.selectedIndex = 0;
            			}
         			else 
         			{
            				if(k == "upload_rate") 
            				{
              					v /= 1024;
              					v = Math.ceil(v);
               				}
               				else
            				if(k == "download_rate") 
            				{
               					v /= 1024;
               					v = Math.ceil(v);
               				}
               				else
            				if(k == "max_memory_usage") 
            				{
               					v /= (1024*1024);
               					v = Math.ceil(v);
               				}
            				o.value = v;
            			}
         		}
      			if(typeof o.onchange == "function") 
      			{
         			o.onchange.apply(o);
         		}
      		}
   		if($_COOKIE == null) 
   		{
      			$_COOKIE = new Object();
      			$_COOKIE = cookies.getCookies();
      		}
   	}
, "setSettings" : function() 
	{
   		var t, v, nv = null, o;
   		v = $$("webui.confirm_when_deleting").checked;
   		if(this.bConfDel != v) 
   		{
      			this.bConfDel = (v) ? 1 : 0;
      		}
   		o = $$("webui.speed_display");
   		v = o.options[o.selectedIndex].value;
   		if(this.bSpdDis != v) 
   		{
      			this.bSpdDis = v;
      			if((v == 0) || (v == 2)) 
      			{
         			window.status = "";
         			window.defaultStatus = "";
         		}
      			if((v == 0) || (v == 1)) 
      			{
         			document.title = "rTorrent WebUI v" + version;
         		}
      		}
   		v = $$("webui.alternate_color").checked;
   		if(this.bAltCol != v) 
   		{
      			this.bAltCol = (v) ? 1 : 0;
      			this.trtTable.colorEvenRows = (this.bAltCol) ? true : false;
      			this.flsTable.colorEvenRows = (this.bAltCol) ? true : false;
      			this.trkTable.colorEvenRows = (this.bAltCol) ? true : false;
      			this.prsTable.colorEvenRows = (this.bAltCol) ? true : false;
     			this.trtTable.refreshSelection();
      			this.flsTable.refreshSelection();
      			this.trkTable.refreshSelection();
      			this.prsTable.refreshSelection();
      		}
		v = $$("webui.needmessage").checked;
   		if(this.bShowMessage != v) 
   		{
      			this.bShowMessage = (v) ? 1 : 0;
		}
   		v = $$("webui.show_cats").checked;
   		if(this.bCategories != v) 
   		{
      			this.bCategories = (v) ? 1 : 0;
      			$$("CatList").style.display = (!this.bCategories) ? "none" : "block";
      			resizeUI();
      		}
   		v = $$("webui.show_dets").checked;
   		if(this.bDetails != v) 
   		{
      			this.bDetails = (v) ? 1 : 0;
      			$$("tdetails").style.display = (!this.bDetails) ? "none" : "block";
      			resizeUI();
      		}
   		v = iv($$("webui.reqtimeout").value);
   		if(this.reqTimeout != v) 
   		{
      			this.reqTimeout = v;
    		}
   		v = iv($$("webui.minrows").value);
   		if(this.minRows != v) 
   		{
      			this.minRows = v;
      			this.trtTable.maxRows = this.minRows;
      			this.flsTable.maxRows = this.minRows;
      			this.trkTable.maxRows = this.minRows;
      			this.prsTable.maxRows = this.minRows;
      			this.trtTable.refreshRows();
      			this.flsTable.refreshRows();
      			this.trkTable.refreshRows();
      			this.prsTable.refreshRows();
      		}
   		this.setUpdateInterval();
   		var p =- 1, w = false;
   		var req = '';
   		for(var k in this.settings) 
   		{
      			t = this.settings[k].t;
      			v = this.settings[k].v;
      			o = $$(k);
      			if(!o) 
      			{
         			continue;
         		}
      			var tn = o.tagName.toLowerCase();
      			var k_type = "s";
      			if(tn == "input") 
      			{
         			switch(o.type) 
         			{
            				case "checkbox": 
            				{
            					nv = (o.checked) ? "1" : "0";
            					k_type = "n";
            					break;
          				}
            				default: 
            				{
            					nv = o.value;
            					if(o.className=="Textbox num")
		            				k_type = "n";
            					break;
         				}
            			}
         		}
      			else 
      			{
         			if(tn == "select") 
         			{
         				k_type = "n";
            				if(o.selectedIndex ==- 1) 
            				{
               					continue;
               				}
            				nv = o.options[o.selectedIndex].value;
            			}
         		}
			if(k == "upload_rate") 
			{
               			nv *= 1024;
               		}
               		else
            		if(k == "download_rate") 
            		{
               			nv *= 1024;
               		}
               		else
            		if(k == "max_memory_usage") 
            		{
               			nv *= 1048576;
               		}
      			if(v != nv) 
      			{
         			req+=("&s=" + k_type+k + "&v=" + nv);
         			this.settings[k].v = nv;
         		}
      		}
      		if(req.length>0)
	      		this.Request("?action=setsettings" + req);
		this.Save();
   	}
, "trtSelect" : function(e, id) 
	{
   		if(e.button == 2) 
   		{
      			if(utWebUI.bDetails && (utWebUI.trtTable.selCount == 1)) 
      			{
         			utWebUI.showDetails(id, false);
         		}
      			utWebUI.createMenu(e, id);
			ContextMenu.show();
      		}
      		var hash = null;
      		var sr = utWebUI.trtTable.rowSel;
		for(var k in sr) 
		{
			if(sr[k] == true)
			{
				hash = k;
				break;
			}
		}
		if((utWebUI.trtTable.selCount==1) && hash)
			utWebUI.showDetails(hash, false);
		else
		{
			utWebUI.dID = "";
			utWebUI.clearDetails();
		}
   	}
, "createMenu" : function(e, id) 
	{
   		var status = this.torrents[id][0];
   		ContextMenu.clear();
   		if(this.trtTable.selCount > 1) 
   		{
      			ContextMenu.add([WUILang.Start, "utWebUI.Start()"]);
      			ContextMenu.add([WUILang.Pause, "utWebUI.Pause()"]);
      			ContextMenu.add([WUILang.Stop, "utWebUI.Stop()"]);
      			ContextMenu.add([WUILang.Force_recheck, "utWebUI.Recheck()"]);
   		}
   		else 
   		{
   			if(this.isCommandEnabled("start",status))
	   			ContextMenu.add([WUILang.Start, "utWebUI.Start()"]);
			else
				ContextMenu.add([WUILang.Start]);
   			if(this.isCommandEnabled("pause",status) || this.isCommandEnabled("unpause",status))
	   			ContextMenu.add([WUILang.Pause, "utWebUI.Pause()"]);
			else
				ContextMenu.add([WUILang.Pause]);
   			if(this.isCommandEnabled("stop",status))
	   			ContextMenu.add([WUILang.Stop, "utWebUI.Stop()"]);
			else
				ContextMenu.add([WUILang.Stop]);
			if(this.isCommandEnabled("recheck",status))
				ContextMenu.add([WUILang.Force_recheck, "utWebUI.Recheck()"]);
			else
				ContextMenu.add([WUILang.Force_recheck]);
		}
   		ContextMenu.add([CMENU_SEP]);
   		var _bf = [];
   		for(var lbl in this.cLabels) 
   		{
      			if((this.trtTable.selCount == 1) && (this.torrents[id][11] == lbl)) 
      			{
         			_bf.push([CMENU_SEL, lbl]);
         		}
      			else 
      			{
         			_bf.push([lbl, "utWebUI.setLabel('" + lbl + "')"]);
         		}
      		}
      		if(_bf.length>0)
	   		_bf.push([CMENU_SEP]);
   		_bf.push([WUILang.New_label, "utWebUI.newLabel()"]);
   		_bf.push([WUILang.Remove_label, "utWebUI.removeLabel()"]);
   		ContextMenu.add([CMENU_CHILD, WUILang.Labels, _bf]);
   		ContextMenu.add([CMENU_SEP]);
   		var _c0 = [];
		if(this.trtTable.selCount > 1) 
		{
			_c0.push([WUILang.High_priority, "utWebUI.Perform('dsetprio&v=3')"]);
			_c0.push([WUILang.Normal_priority, "utWebUI.Perform('dsetprio&v=2')"]);
			_c0.push([WUILang.Low_priority,  "utWebUI.Perform('dsetprio&v=1')"]);
			_c0.push([WUILang.Dont_download,  "utWebUI.Perform('dsetprio&v=0')"]);
		}
		else
		{
			var p = this.torrents[id][16];
			if(p==0)
			{
				_c0.push([WUILang.High_priority, "utWebUI.Perform('dsetprio&v=3')"]);
				_c0.push([WUILang.Normal_priority, "utWebUI.Perform('dsetprio&v=2')"]);
				_c0.push([WUILang.Low_priority,  "utWebUI.Perform('dsetprio&v=1')"]);
				_c0.push([WUILang.Dont_download]);
			}
			else
			if(p==1)
			{
				_c0.push([WUILang.High_priority, "utWebUI.Perform('dsetprio&v=3')"]);
				_c0.push([WUILang.Normal_priority, "utWebUI.Perform('dsetprio&v=2')"]);
				_c0.push([WUILang.Low_priority]);
				_c0.push([WUILang.Dont_download,  "utWebUI.Perform('dsetprio&v=0')"]);
			}
			else
			if(p==2)
			{
				_c0.push([WUILang.High_priority, "utWebUI.Perform('dsetprio&v=3')"]);
				_c0.push([WUILang.Normal_priority]);
				_c0.push([WUILang.Low_priority,  "utWebUI.Perform('dsetprio&v=1')"]);
				_c0.push([WUILang.Dont_download,  "utWebUI.Perform('dsetprio&v=0')"]);
			}
			else
			{
				_c0.push([WUILang.High_priority]);
				_c0.push([WUILang.Normal_priority, "utWebUI.Perform('dsetprio&v=2')"]);
				_c0.push([WUILang.Low_priority,  "utWebUI.Perform('dsetprio&v=1')"]);
				_c0.push([WUILang.Dont_download,  "utWebUI.Perform('dsetprio&v=0')"]);
			}
		}
   		ContextMenu.add([CMENU_CHILD, WUILang.Priority, _c0]);
   		ContextMenu.add([CMENU_SEP]);
   		ContextMenu.add([WUILang.Remove, "utWebUI.Remove()"]);
   		ContextMenu.add([CMENU_SEP]);
   		ContextMenu.add([WUILang.Details, "utWebUI.showDetails('" + id + "')"]);
   		if(this.trtTable.selCount > 1) 
   		{
      			ContextMenu.add([WUILang.Properties]);
      		}
   		else 
   		{
      			ContextMenu.add([WUILang.Properties, "utWebUI.showProperties('" + id + "')"]);
      		}
   	}
, "showProperties" : function(k) 
	{
   		this.pID = k;
   		this.Request("?action=getprops&hash=" + k, [this.loadProperties, this]);
   	}
, "loadProperties" : function(_c3) 
	{
   		var d = eval("(" + _c3 + ")");
   		var _c5 = d.props[0];
   		if(typeof this.props[this.pID] == "undefined") 
   		{
      			this.props[this.pID] = {};
      		}
   		for(var k in _c5) 
   		{
      			this.props[this.pID][k] = _c5[k];
      		}
   		this.updateProperties();
   	}
, "updateProperties" : function() 
	{
   		var _c7 = this.props[this.pID];
   		$$("prop-ulslots").value = _c7.ulslots;
   		$$("prop-peers_min").value = _c7.peers_min;
   		$$("prop-peers_max").value = _c7.peers_max;
   		$$("prop-tracker_numwant").value = _c7.tracker_numwant;
   		o = $$("prop-pex");
   		if(_c7.pex ==- 1) 
   		{
      			o.checked = false;
      			o.disabled = true;
      			$$("lbl_prop-pex").className = "disabled";
      		}
   		else 
   		{
      			o.disabled = false;
      			o.checked = _c7.pex;
      			$$("lbl_prop-pex").className = "";
      		}
		o = $$("prop-superseed");
//		if(this.torrents[this.pID][2]==WUILang.Finished)
		if(this.torrents[this.pID][4]==1000)
		{
			o.disabled = false;
      			$$("lbl_prop-superseed").className = "";
		}
		else
		{
    			o.disabled = true;
     			$$("lbl_prop-superseed").className = "disabled";
     		}
		o.checked = _c7.superseed;
   		Show("dlgProps");
   	}
, "setProperties" : function() 
	{
   		Hide("dlgProps");
   		var req = '';
   		for(var k in this.props[this.pID]) 
   		{
      			var v = this.props[this.pID][k];
      			var nv = getObjValue("prop-" + k);
      			if(k == "hash") 
      			{
         			continue;
         		}
      			if((k == "pex") && (v ==- 1)) 
      			{
         			continue;
         		}
      			if((v != nv) && !isNaN(parseInt(nv)))
      			{
				this.props[this.pID][k] = nv;
      				req+=("&s=" + k + "&v=" + nv);
   			}
		}	      		
         	if(req.length>0)
	       		this.Request("?action=setprops&hash=" + this.pID + req);
        }

, "showDetails" : function(k, bs) 
	{
   		if(bs == null) 
   		{
      			tdTabs.show("gcont");
      		}
   		this.dID = k;
   		this.getFiles(k);
 		this.getTrackers(k);
   		if(bs == null) 
   		{
      			$$("tdetails").style.display = "block";
      			utWebUI.bDetails = 1;
      			resizeUI();
      			utWebUI.Save();
      		}
   		this.updateDetails();
   	}
, "clearDetails" : function(id) 
	{
		$$("dl").innerHTML = "";
		$$("ul").innerHTML = "";
		$$("ra").innerHTML = "";
		$$("us").innerHTML = "";
		$$("ds").innerHTML = "";
		$$("rm").innerHTML = "";
		$$("se").innerHTML = "";
		$$("pe").innerHTML = "";
		$$("et").innerHTML = "";
		$$("wa").innerHTML = "";
		$$("co").innerHTML = "";
		$$("bf").innerHTML = "";
		$$("hs").innerHTML = "";
		$$("ts").innerHTML = "";
		$$("tu").innerHTML = "";
		$$("ts").innerHTML = "";
		$$("cmt").innerHTML = "";
		$$("dsk").innerHTML = "";
		this.flsTable.clearRows();
		this.trkTable.clearRows();
		this.clearPeers();
	}
, "updateDetails" : function(id) 
	{
   		if((this.dID == "") || !this.torrents[this.dID])
   		{
      			return;
      		}
   		var d = this.torrents[this.dID].slice(1);
		$$("dl").innerHTML = ffs(d[4],2);
		$$("ul").innerHTML = ffs(d[5],2);
		$$("ra").innerHTML = (d[6] ==- 1) ? "&#8734;" : round(d[6]/1000,3);
		$$("us").innerHTML = ffs(d[8]) + "/" + WUILang.s + "";
		$$("ds").innerHTML = ffs(d[7]) + "/" + WUILang.s + "";
		$$("rm").innerHTML = (d[9] ==- 1) ? "&#8734;" : ft(d[9]);
		$$("se").innerHTML = d[13] + " " + WUILang.of + " " + d[14] + " " + WUILang.connected + "";
		$$("pe").innerHTML = d[11] + " " + WUILang.of + " " + d[12] + " " + WUILang.connected;
		var today = new Date();
		var seconds = Math.floor((today.getTime()-utWebUI.deltaTime)/1000-parseInt(d[18]));
		$$("et").innerHTML = ft(seconds,true);
		$$("wa").innerHTML = ffs(d[19],2);
        	$$("bf").innerHTML = d[20];
        	$$("co").innerHTML = formatDate(d[21]);
		var tracker_focus = d[22];
		$$("tu").innerHTML = 
			((typeof this.trackers[this.dID] != "undefined") &&
			(tracker_focus<this.trackers[this.dID].length)) ? 
			this.trackers[this.dID][tracker_focus][0] : '';
        	$$("hs").innerHTML = this.dID;
		$$("ts").innerHTML = this.torrents[this.dID][24];
		var url = d[24].replace(/(^\s+)|(\s+$)/g, "");

		if(!url.match(/<a href/))
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
		$$("cmt").innerHTML = url;
		if(d[25]!='0')		
			$$("dsk").innerHTML = ffs(d[25],2);
   		this.updatePeers();
	}
, "showAdd" : function() 
	{
   		Toggle($$("tadd"));
   	}
, "addURL" : function() 
	{
   		var url = encodeURI($$("url").value);
   		if(url == "") 
   		{
      			return;
      		}
   		var c = $$("cookies").value;
   		if(c != "") 
   		{
      			url += ":COOKIE:" + c;
      		}
   		this.Request("?action=add-url&s=" + url);
   	}
, "getTrackers" : function(k) 
	{
   		if(typeof (this.trackers) == "undefined") 
   		{
      			this.trackers = [];
      		}
		if(typeof this.trackers[k] == "undefined") 
      		{
         		this.trackers[k] = new Array();
         	}
      		this.Request("?action=gettrackers&hash=" + k, [this.addTrackers, this]);
   	}
, "addTrackers" : function(_db) 
	{
   		var d = eval("(" + _db + ")"), i, aData;
   		var k = d.trackers[0];
   		this.trackers[k] = new Array();
   		for(i = 0; i < d.trackers[1].length; i++) 
   		{
      			aData = d.trackers[1][i].slice(0);
     			if(typeof this.trackers[k][i] == "undefined") 
      			{
         			this.trackers[k][i] = new Array();
         		}
      			this.trackers[k][i] = aData.slice(0);
      			var sId = k + "_t_" + i;
      			if(this.dID == k) 
      			{
         			if(typeof this.trkTable.rowdata[sId] == "undefined") 
         			{
            				this.trkTable.addRow(aData, sId, null, {"added" : true});
            			}
         			else 
         			{
            				for(var j = 0; j < aData.length; j++) 
            				{
               					this.trkTable.setValue(sId, j, aData[j]);
               				}
               				this.trkTable.setAttr(sId, "added", true);
            			}
         		}
      		}
   		var _e0 = false;
   		var rowIDs = this.trkTable.rowIDs.slice(0);
		for(var i in rowIDs) 
		{
			if(this.dID != rowIDs[i].substr(0, 40)) 
			{
         			this.trkTable.removeRow(rowIDs[i]);
         			_e0 = true;
         		}
         		else
         			if(!this.trkTable.getAttr(rowIDs[i], "added"))
         			{
	         			this.trkTable.removeRow(rowIDs[i]);
        	 			_e0 = true;
         			}
         			else
	         			this.trkTable.setAttr(rowIDs[i], "added", false);
      		}
   		if(_e0) 
   		{
      			this.trkTable.refreshRows();
      		}
   		if(this.dID == k)
   		{
      			this.trkTable.calcSize();
      			this.trkTable.resizeHack();
      		}
      		this.updateDetails();
      		delete _db;
      		d = null;
   	}
, "prsSelect" : function(e, id) 
	{
   	}
, "trkSelect" : function(e, id) 
	{
		if(typeof id != "undefined")
		{
	   		var ind = iv(id.substr(43));
   			if(utWebUI.createTrackerMenu(e, ind))
		   		ContextMenu.show();
		}
   	}
, "createTrackerMenu" : function(e, ind)
	{
   		if(e.button != 2) 
   		{
      			return(false);
      		}
   		var id = this.dID;
   		var p = this.trackers[id][ind][2];
   		var _e8 = "";
   		ContextMenu.clear();
   		if(this.trkTable.selCount > 1) 
   		{
      			ContextMenu.add([WUILang.EnableTracker, "utWebUI.EnableTracker('" + id + "'," + ind + ")"]);
      			ContextMenu.add([WUILang.DisableTracker, "utWebUI.DisableTracker('" + id + "'," + ind + ")"]);
      		}
   		else 
   		{
      			if(p == 0) 
      			{
      				ContextMenu.add([WUILang.EnableTracker, "utWebUI.EnableTracker('" + id + "'," + ind + ")"]);
	      			ContextMenu.add([WUILang.DisableTracker]);
         		}
      			else 
      			{
	      			ContextMenu.add([WUILang.EnableTracker]);
      				ContextMenu.add([WUILang.DisableTracker, "utWebUI.DisableTracker('" + id + "'," + ind + ")"]);
         		}
      		}
		return(true);
   	}
, "EnableTracker" : function(id) 
	{
   		this.setTrackerState(id, 1);
   	}
, "DisableTracker" : function(id) 
	{
   		this.setTrackerState(id, 0);
   	}
, "setTrackerState" : function(id, p) 
	{
   		var trk = this.getTrackerIds(id, p);
   		this.Request("?action=settrackerstate&hash=" + id + "&p=" + p + trk,[this.refreshTrackersState,this]);
   	}
, "refreshTrackersState" : function(dummy) 
	{            
//		this.getTrackers(this.dID);
	}
, "getTrackerIds" : function(id, p) 
	{
   		var sr = this.trkTable.rowSel;
   		var str = "";
   		var needSort = false;
   		for(var k in sr) 
   		{
      			if(sr[k] == true) 
      			{
         			var i = iv(k.substr(43));
         			if(this.trackers[id][i][2] != p) 
         			{
            				str += "&f=" + i;
            				this.trackers[id][i][2] = p;
            				needSort = true;
            				this.trkTable.setValue(id + "_t_" + i, 2, p);
            			}
         		}
      		}
      		if(needSort)
			this.trkTable.Sort();
   		return str;
   	}
, "updateFiles" : function(sId) 
	{
		if(this.dID != sId) 
		{
      			return;
      		}
   		if(typeof (this.files[sId]) != "undefined") 
   		{
      			this.getFiles(sId, true);
      			this.updateDetails();
      		}
   	}
, "redrawFiles" : function(hash) 
	{
		if(this.dID == hash) 
      		{
	      		for(var i in this.files[hash]) 
      			{
       				var sId = hash + "_f_" + i;
       				var aData = this.files[hash][i].slice(0);
       				aData.push(iv(aData[3]));
         			aData[3] = ((aData[1] > 0) ? round((aData[2]/aData[1])*100,1): "100.0");
         			if(this.fileListMode)
         			{
					if(typeof this.flsTable.rowdata[sId] == "undefined") 
        	          			this.flsTable.addRow(aData, sId);
            				else
	            				for(var j = 0, lj = aData.length; j < lj; j++) 
               						this.flsTable.setValue(sId, j, aData[j]);
				}
				else
				{
					if((typeof (this.dirs[hash]) == "undefined"))
						this.dirs[hash] = new rDirectory();
					this.dirs[hash].addFile(aData, i);
				}
			}
			if(!this.fileListMode && this.dirs[hash])
			{
				var dir = this.dirs[hash].getDirectory();
				for(var i in dir) 
				{
					var entry = dir[i];
					if(entry.link!=null)
					{
						if(typeof this.flsTable.rowdata[i] == "undefined") 
						        this.flsTable.addRow(entry.data.slice(0), i, entry.icon, {"link" : entry.link});
						else
	            					for(var j = 0, lj = entry.data.length; j < lj; j++) 
               							this.flsTable.setValue(i, j, entry.data[j]);
					}
				}
				for(var i in dir) 
				{
					var entry = dir[i];
					if(entry.link==null)
					{
						if(typeof this.flsTable.rowdata[i] == "undefined") 
						        this.flsTable.addRow(entry.data, i, entry.icon, {"link" : null});
						else
	            					for(var j = 0, lj = aData.length; j < lj; j++) 
               							this.flsTable.setValue(i, j, entry.data[j]);
					}
				}
			}
       			this.flsTable.calcSize();
       			this.flsTable.resizeHack();
       	 	}
	}
, "getFiles" : function(hash, isUpdate) 
	{
   		if(typeof (this.files) == "undefined") 
   		{
      			this.files = [];
      		}
   		if(!isUpdate) 
   		{
      			this.flsTable.dBody.scrollTop = 0;
      			if(!browser.isOldIE)
	      			this.flsTable.tpad.style.height = "0px";
      			this.flsTable.bpad.style.height = "0px";
      		}
   		if((typeof (this.files[hash]) != "undefined") && !isUpdate) 
   		{
      			this.flsTable.clearRows();
      			this.redrawFiles(hash);
      		}
   		else 
   		{
      			if(!isUpdate) 
         			this.flsTable.clearRows();
      			if(typeof this.files[hash] == "undefined") 
         			this.files[hash] = new Array(0);
      			this.Request("?action=getfiles&hash=" + hash, [this.addFiles, this]);
      		}
   	}
, "addFiles" : function(_db) 
	{
   		var d = eval("(" + _db + ")"), i, l;
   		var hash = d.files[0];
   		for(i = 0, l = d.files[1].length; i < l; i++) 
   		{
      			if(typeof this.files[hash][i] == "undefined") 
         			this.files[hash][i] = new Array();
      			this.files[hash][i] = d.files[1][i].slice(0);
      		}
      		this.redrawFiles(hash);
      		delete _db;
      		d = null;
   	}
, "flsSelect" : function(e, id) 
	{
		if(typeof id != "undefined") 
		{
	   		var p = -1;
	   		if(utWebUI.fileListMode)
		   		p = utWebUI.files[utWebUI.dID][id.substr(43)][3];
			else
				p = utWebUI.dirs[utWebUI.dID].getEntryPriority(id);
   			if(utWebUI.createFileMenu(e, p))
				ContextMenu.show();
		}
   	}
, "createFileMenu" : function(e, p) 
	{
   		if(e.button != 2) 
   		{
      			return(false);
      		}
   		var id = this.dID;
   		ContextMenu.clear();
		var _bf = [];
		if(this.flsTable.selCount > 1) 
   		{
      			_bf.push([WUILang.High_priority, "utWebUI.High('" + id + "')"]);
			_bf.push([WUILang.Normal_priority, "utWebUI.Normal('" + id + "')"]);
			_bf.push([CMENU_SEP]);
			_bf.push([WUILang.Dont_download, "utWebUI.Skip('" + id + "')"]);
		}
   		else 
	   		if(p!=null)
   			{
   			       	_bf.push([WUILang.High_priority, (p == 2) ? null : "utWebUI.High('" + id + "')"]);
	   	        	_bf.push([WUILang.Normal_priority, (p == 1) ? null : "utWebUI.Normal('" + id + "')"]);
	   		        _bf.push([CMENU_SEP]);
   			        _bf.push([WUILang.Dont_download, (p == 0) ? null : "utWebUI.Skip('" + id + "')"]);
	      		}
	      	if(_bf.length)
	      		ContextMenu.add([CMENU_CHILD, WUILang.Priority, _bf]);
		var _bf1 = [];
		if(this.fileListMode)
		{
			_bf1.push([WUILang.AsList]);
			_bf1.push([WUILang.AsTree, "utWebUI.toggleFileView()"]);
		}
		else
		{
			_bf1.push([WUILang.AsList, "utWebUI.toggleFileView()"]);
			_bf1.push([WUILang.AsTree]);
		}
		ContextMenu.add([CMENU_CHILD, WUILang.View, _bf1]);
		return(true);
   	}
, "toggleFileView" : function() 
{
	this.fileListMode = !this.fileListMode;	
	utWebUI.flsTable.clearRows();
	if(utWebUI.dID!="")
		utWebUI.redrawFiles(utWebUI.dID);
	utWebUI.Save();
}
, "High" : function(id) 
	{
   		this.setPriority(id, 2);
   	}
, "Normal" : function(id) 
	{
   		this.setPriority(id, 1);
   	}
, "Skip" : function(id) 
	{
   		this.setPriority(id, 0);
   	}
, "getFileIds" : function(id, p) 
	{
   		var sr = this.flsTable.rowSel;
   		var str = "";
   		var needSort = false;
   		for(var k in sr) 
   		{
      			if(sr[k] == true) 
      			{
      				if(this.fileListMode)
      				{
	         			var i = iv(k.substr(43));
        	 			if(this.files[id][i][3] != p) 
         				{
						str += "&f=" + i;
	            				needSort = true;
        	    			}
        			}
        			else
        			{
				        var dir = utWebUI.dirs[id];
				        var ids = new Array();
				        dir.getFilesIds(ids,dir.current,k,p);
				        for(var i in ids)
						str += "&f=" + ids[i];
					needSort = true;
        			}
         		}
      		}
      		if(needSort)
			this.flsTable.Sort();
   		return str;
   	}
, "updateCurrentFiles" : function(hash) 
	{
		this.updateFiles(hash);
	}
, "setPriority" : function(id, p) 
	{
   		var fls = this.getFileIds(id, p);
   		this.Request("?action=setprio&hash=" + id + "&p=" + p + fls,[this.updateCurrentFiles, this]);
   	}
, "trtSort" : function() 
	{
   		if((utWebUI.trtTable.sIndex != iv($_COOKIE["webui.trt.sindex"])) || 
   			(utWebUI.trtTable.reverse != iv($_COOKIE["webui.trt.sortrev"]))) 
   		{
      			utWebUI.Save();
      		}
   	}
, "flsSort" : function() 
	{
   		if((utWebUI.flsTable.sIndex != iv($_COOKIE["webui.fls.sindex"])) || 
   			(utWebUI.flsTable.reverse != iv($_COOKIE["webui.fls.sortrev"]))) 
   		{
      			utWebUI.Save();
      		}
   	}
, "trkSort" : function() 
	{
   		if((utWebUI.trkTable.sIndex != iv($_COOKIE["webui.trk.sindex"])) || 
   			(utWebUI.trkTable.reverse != iv($_COOKIE["webui.trk.sortrev"]))) 
   		{
      			utWebUI.Save();
      		}
   	}
, "prsSort" : function() 
	{
   		if((utWebUI.prsTable.sIndex != iv($_COOKIE["webui.prs.sindex"])) || 
   			(utWebUI.prsTable.reverse != iv($_COOKIE["webui.prs.sortrev"]))) 
   		{
      			utWebUI.Save();
      		}
   	}
, "RestoreUI" : function(bc) 
	{
   		if((bc != false) && !confirm(WUILang.Shure_restore_UI)) 
   		{
      			return;
      		}
   		Hide("stg");
   		$$("msg").innerHTML = WUILang.Reloading;
   		$$("cover").style.display = "block";
   		utWebUI.Request("?action=setuisettings&s=webui.cookie&v={}", 
   			[function() { window.location.reload(false); } ]);
   	}
, "Save" : function() 
	{
   		var i, l = utWebUI.trtTable.cols, aWidth = new Array(l), ind, col;
		var aEnabled = new Array(l);
   		for(i = 0; i < l; i++) 
   		{
      			aWidth[i] = utWebUI.trtTable.getColWidth(i);
      			aEnabled[i] = utWebUI.trtTable.isColumnEnabled(i);
		}
   		cookies.setCookie("webui.trt.colwidth", aWidth);
   		cookies.setCookie("webui.trt.colenabled", aEnabled);
   		l = utWebUI.flsTable.cols;
   		aWidth = new Array(l);
   		aEnabled = new Array(l);
   		for(i = 0; i < l; i++) 
   		{
      			aWidth[i] = utWebUI.flsTable.getColWidth(i);
      			aEnabled[i] = utWebUI.flsTable.isColumnEnabled(i);
		}
   		cookies.setCookie("webui.fls.colwidth", aWidth);
   		cookies.setCookie("webui.fls.colenabled", aEnabled);
   		l = utWebUI.trkTable.cols;
   		aWidth = new Array(l);
   		aEnabled = new Array(l);
   		for(i = 0; i < l; i++) 
   		{
      			aWidth[i] = utWebUI.trkTable.getColWidth(i);
      			aEnabled[i] = utWebUI.trkTable.isColumnEnabled(i);
		}
		cookies.setCookie("webui.trk.colwidth", aWidth);
		cookies.setCookie("webui.trk.colenabled", aEnabled);
   		l = utWebUI.prsTable.cols;
   		aWidth = new Array(l);
   		aEnabled = new Array(l);
   		for(i = 0; i < l; i++) 
   		{
      			aWidth[i] = utWebUI.prsTable.getColWidth(i);
      			aEnabled[i] = utWebUI.prsTable.isColumnEnabled(i);
		}
	        cookies.setCookie("webui.fls.view", utWebUI.fileListMode ? 1 : 0);
		cookies.setCookie("webui.prs.colwidth", aWidth);
		cookies.setCookie("webui.prs.colenabled", aEnabled);
		cookies.setCookie("webui.trt.colorder", utWebUI.trtTable.colOrder);
		cookies.setCookie("webui.fls.colorder", utWebUI.flsTable.colOrder);
		cookies.setCookie("webui.trk.colorder", utWebUI.trkTable.colOrder);
		cookies.setCookie("webui.prs.colorder", utWebUI.prsTable.colOrder);
		cookies.setCookie("webui.trt.sindex", utWebUI.trtTable.sIndex);
		cookies.setCookie("webui.fls.sindex", utWebUI.flsTable.sIndex);
		cookies.setCookie("webui.trk.sindex", utWebUI.trkTable.sIndex);
		cookies.setCookie("webui.prs.sindex", utWebUI.prsTable.sIndex);
		cookies.setCookie("webui.trt.rev", utWebUI.trtTable.reverse);
		cookies.setCookie("webui.fls.rev", utWebUI.flsTable.reverse);
		cookies.setCookie("webui.trk.rev", utWebUI.trkTable.reverse);
		cookies.setCookie("webui.prs.rev", utWebUI.prsTable.reverse);
		cookies.setCookie("webui.show_cats", utWebUI.bCategories);
		cookies.setCookie("webui.show_dets", utWebUI.bDetails);
		cookies.setCookie("webui.needmessage", utWebUI.bShowMessage);
		cookies.setCookie("webui.reqtimeout", utWebUI.reqTimeout);
		cookies.setCookie("webui.confirm_when_deleting", utWebUI.bConfDel);
		cookies.setCookie("webui.alternate_color", utWebUI.bAltCol);
		cookies.setCookie("webui.speed_display", utWebUI.bSpdDis);
		cookies.setCookie("webui.update_interval", utWebUI.updateInterval);
		cookies.setCookie("webui.hsplit", utWebUI.hSplit);
		cookies.setCookie("webui.vsplit", utWebUI.vSplit);
		cookies.setCookie("webui.trt.sortrev", utWebUI.trtTable.reverse);
		cookies.setCookie("webui.fls.sortrev", utWebUI.flsTable.reverse);
		cookies.setCookie("webui.trk.sortrev", utWebUI.trkTable.reverse);
		cookies.setCookie("webui.prs.sortrev", utWebUI.prsTable.reverse);
		cookies.setCookie("webui.minrows", utWebUI.minRows);
		cookies.setCookies();
	}
, "showRSS" : function()
	{
		alert("RSS has not been implemented yet.");
	}
, "resizeBottomBar" : function(w,h) 
	{
		utWebUI.flsTable.resize(w,h);
		utWebUI.trkTable.resize(w,h);
		utWebUI.prsTable.resize(w,h);
		utWebUI.speedGraph.resize(w,h);
	}
, "toggleMenu" : function()
	{
		ToggleB($$("t"));
  		resizeUI();
	}
, "toggleDetails" : function()
	{
		ToggleB($$("tdetails"));
		if(!utWebUI.bDetails) 
			utWebUI.bDetails = 1;
		else
			utWebUI.bDetails = 0;
      		resizeUI();
		utWebUI.Save();
	}
, "toggleCategories" : function()
	{
		ToggleB($$("CatList"));
		if(!utWebUI.bCategories) 
			utWebUI.bCategories = 1;
		else
			utWebUI.bCategories = 0;
      		resizeUI();
		utWebUI.Save();
	}
};

var ContextMenu = new oContextMenu();
var CMENU_SEP =	" 0";
var CMENU_CHILD = " 1";
var CMENU_SEL = " 2";

function getAbsCoords(obj)
{
	var left = 0;
	var top = 0;
	while(obj)
	{
	        top+=obj.offsetTop;
		left+=obj.offsetLeft;
		obj = obj.offsetParent;
	}
	return([left,top]);
}

function oContextMenu(prnt)
{
	this.owner = prnt;
}

oContextMenu.prototype.init = function(id)
{
	this.obj = document.createElement("UL");
	this.obj.className = "CMenu";
	this.obj.style.position = "absolute";
	this.obj.id = id;
	var b = document.getElementsByTagName("body").item(0);
	b.appendChild(this.obj);
	this.submenus = new Array();
	this.activeSubMenu = null;
	this.mouseOvers = new Array();
	this.mouseOuts = new Array();
}

oContextMenu.prototype.get = function( label )
{
	var els = this.obj.getElementsByTagName("A");
	for(var i in els )	
	{
		if(els[i].innerHTML==label)
		{
			return(els[i].parentNode);
		}
	}
	return(null);
}

oContextMenu.prototype.add = function()
{
	var args = new Array();
	var after = null;
	for(var j = 0, len = arguments.length; j < len; j++) 
	{
		args[j] = arguments[j];
	}
	var i = 0, link, o, ul, li;
	if((typeof args[0] == "object") && (args[0].className == "CMenu")) 
	{
		o = args[0];
		args.splice(0, 1);
	}
	else 
	{
		o = this.obj;
	}
	if((typeof args[0] == "object") && (args[0].className == "menuitem")) 
	{
		after = args[0];
		args.splice(0, 1);		
	}
	var l = args.length;
	for(i = 0; i < l; i++) 
	{
		li = document.createElement("LI");
		li.className = "menuitem";
		if(args[i][0] == CMENU_SEP) 
		{
			li.appendChild(document.createElement("HR"));
		}
		else 
		{
			if(args[i][0] == CMENU_CHILD) 
			{
				link = document.createElement("A");
				link.className = "exp";
				link.innerHTML = args[i][1];
				li.appendChild(link);
				if(browser.isOldIE)
				{
					var obj = new oContextMenu(this);
					this.submenus.push(obj);
					obj.init();
					for(var j = 0, len = args[i][2].length; j < len; j++) 
						obj.add(args[i][2][j]);
					link.href = "javascript://void();";
					li.onmouseover = function() { obj.showChild(li); };
					li.onmouseout = function() { obj.setTimeout(); };
					this.mouseOvers.push(li);
					this.mouseOuts.push(li);
					if(after)
						o.insertBefore(li,after.nextSibling);
					else
						o.appendChild(li);
					continue;
				}
				else
				{
					ul = document.createElement("UL");
					ul.className = "CMenu";
					for(var j = 0, len = args[i][2].length; j < len; j++) 
					{
						this.add(ul, args[i][2][j]);
					}
					li.appendChild(ul);
				}
			}
         		else 
         		{
			       	if(args[i][0] == CMENU_SEL) 
			 	{
					link = document.createElement("A");
					link.className = "sel";
					link.innerHTML = args[i][1];
					if((typeof args[i][2] != "undefined") && args[i][2])
						link.href = "javascript:" + args[i][2] + ";";
					li.appendChild(link);
               			}
				else 
				{
					var hotKey = ""
					if((typeof args[i][2] != "undefined") && args[i][2])
						hotKey = "<span class='htkey'>"+args[i][2]+"</span>";
					if((typeof args[i][1] == "undefined") || !args[i][1])
					{
						link = document.createElement("A");
						link.className = "dis";
						link.innerHTML = args[i][0]+hotKey;
						li.appendChild(link);
                  			}
					else 
					{
						link = document.createElement("A");
						link.href = "javascript:" + args[i][1] + ";";
						link.innerHTML = args[i][0]+hotKey;
						li.appendChild(link);
					}
				}
			}
		}
		if(after)
			o.insertBefore(li,after.nextSibling);
		else
			o.appendChild(li);
		if(browser.isOldIE)
		{
			var self = this;
			li.onmouseover = function() 
				{         
					if(self.activeSubMenu)
				        	self.activeSubMenu.hideChild();
				};
		 	this.mouseOvers.push(li);
		}
	}
}

oContextMenu.prototype.clear = function()
{
	while(this.obj.firstChild) 
	{
		this.obj.removeChild(this.obj.firstChild);
	}
	for(var i in this.submenus )
		this.submenus[i].clear();
	for(var i in this.mouseOvers )
		delete this.mouseOvers[i];
	for(var i in this.mouseOuts )
		delete this.mouseOuts[i];
	this.mouseOvers = new Array();
	this.mouseOuts = new Array();
}

oContextMenu.prototype.show = function()
{
	this.obj.style.visibility = "hidden";
	this.obj.style.display = "block";
	var x = mouse.X;
	if(x + this.obj.offsetWidth > getWindowWidth()) 
	{
		x -= this.obj.offsetWidth;
	}
	var y = mouse.Y;
	if(y + this.obj.offsetHeight > getWindowHeight()) 
	{
		y -= this.obj.offsetHeight;
	}
	this.obj.style.left = x + "px";
	this.obj.style.top = y + "px";
	this.obj.style.visibility = "visible";
	this.obj.style.zIndex = Drag.zindex++; 
}

oContextMenu.prototype.hide = function()
{
	if(this.obj)
	{
		this.obj.style.display = "none";
		this.clear();
		this.obj.style.left = "0px";
		this.obj.style.top = "0px";
		for(var i in this.submenus )
		{
			this.submenus[i].hideChild();
			delete this.submenus[i];
		}
		this.activeSubMenu = null;
		this.submenus = new Array();
	}
}

oContextMenu.prototype.showChild = function(parent)
{
        if(this.owner.activeSubMenu)
        	this.owner.activeSubMenu.hideChild();
        this.owner.activeSubMenu = this;
	this.entered = 0;
	this.obj.style.visibility = "hidden";
	this.obj.style.display = "block";
	var coords = getAbsCoords(parent)
	var x = coords[0]+parent.offsetWidth;
	if(x + this.obj.offsetWidth > getWindowWidth()) 
	{
		x -= this.obj.offsetWidth;
	}
	var y = coords[1];
	if(y + this.obj.offsetHeight > getWindowHeight()) 
	{
		y -= this.obj.offsetHeight;
	}
	this.obj.style.left = x + "px";
	this.obj.style.top = y + "px";
	this.obj.style.visibility = "visible";
	this.obj.style.zIndex = Drag.zindex++; 

	var self = this;
	this.obj.onmouseover = function() { if(self.entered==0) self.setTimeout(); self.entered++ };
	this.obj.onmouseout = function() { if(self.entered>0) self.entered-- };
}

oContextMenu.prototype.checkVisibility = function()
{
	if(this.entered<=0)
		this.hideChild();
}

oContextMenu.prototype.setTimeout = function()
{
	var self = this;
	this.checkTimeout = window.setTimeout(
		function() { self.checkVisibility() }, 100);
}

oContextMenu.prototype.hideChild = function()
{
	if(this.checkTimeout!=null)
		window.clearTimeout(this.checkTimeout);
	this.checkTimeout = null;
	this.entered = 0;
	this.obj.style.display = "none";
	this.obj.style.left = "0px";
	this.obj.style.top = "0px";
}

var tdTabs = 
{
	"tabs" : 
	{
   		"gcont" : WUILang.General, 
   		"FileList" : WUILang.Files, 
   		"TrackerList" : WUILang.Trackers, 
   		"PeerList" : WUILang.Peers,
   		"Speed" : WUILang.Speed,
   		"lcont" : WUILang.Logger
   	}, 
   	"draw" : function() 
   	{
		if(browser.isKonqueror && (browser.versionMajor<4))
			delete this.tabs["Speed"];
   		var _111 = "";
   		for(var n in this.tabs) 
   		{
      			_111 += "<li id=\"tab_" + n + "\"><a href=\"javascript://utwebui/\" onmousedown=\"javascript:tdTabs.show('" + n + "');\" onfocus=\"javascript:this.blur();\">" + this.tabs[n] + "</a></li>";
      		}
   		document.write(_111);
   		this.show("gcont");
   	}, 
	"onShow" : function(id)
	{
	},
   	"show" : function(id) 
	{
   		var p = null, l = null;
   		for(var n in this.tabs) 
   		{
      			if((l = $$("tab_" + n)) && (p = $$(n))) 
      			{
         			if(n == id) 
         			{
            				p.style.display = "block";
            				if(n == "FileList") 
       	    				{
						utWebUI.flsTable.calcSize();
						utWebUI.flsTable.resizeHack();
	               			}
        	       			else
               				if(n == "TrackerList") 
            				{
               					utWebUI.trkTable.calcSize();
               					utWebUI.trkTable.resizeHack();
        	       			}
               				else
               				if(n == "PeerList") 
            				{
               					utWebUI.prsTable.calcSize();
               					utWebUI.prsTable.resizeHack();
               				}
               				else
               				if(n == "Speed")
               				{
						utWebUI.setActiveView(id);
               					utWebUI.speedGraph.resize(); 
               				}
					else
						this.onShow(n);
               				utWebUI.setActiveView(id);
//					if(n == "PeerList")
//						utWebUI.updatePeers();
            				l.className = "selected";
            				l.style.zIndex = 1;
            			}
         			else 
         			{
            				p.style.display = "none";
            				l.className = "";
            				l.style.zIndex = 0;
            			}
         		}
      		}
   	}
};

function log(text,noTime) 
{
	var tm = '';
	if(!noTime)
		tm = "[" + formatDate(new Date().getTime()/1000) + "]";
	var obj = $$("lcont");
	if(obj)
	{
		obj.innerHTML += "<div>" + tm + " " + escapeHTML(text) + "</div>";
		obj.scrollTop = obj.scrollHeight;
		tdTabs.show("lcont");
	}
}

