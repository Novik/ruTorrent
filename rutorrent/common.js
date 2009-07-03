var ELE_DIV = document.createElement("DIV");
var ELE_SPAN = document.createElement("SPAN");
var ELE_TABLE = document.createElement("TABLE");
var ELE_TBODY = document.createElement("TBODY");
var ELE_COLGROUP = document.createElement("COLGROUP");
var ELE_COL = document.createElement("COL");
var ELE_TR = document.createElement("TR");
var ELE_TD = document.createElement("TD");
function $$(id) {
   if(document.all) {
      return document.all[id];
      }
   else {
      if(document.getElementById) {
         return document.getElementById(id);
         }
      else {
         for(var _2 = 1; _2 < document.layers.length; _2++) {
            if(document.layers[_2].id == id) {
               return document.layers[_2];
               }
            }
         }
      }
   return false;
   }
function getOffsetLeft(_3) {
   var x = 0;
   while(_3) {
      x += parseInt(_3.offsetLeft);
      _3 = _3.offsetParent;
      }
   return x;
   }
function getOffsetTop(_5) {
   var y = 0;
   while(_5) {
      y += parseInt(_5.offsetTop);
      _5 = _5.offsetParent;
      }
   return y;
   }
function getWindowHeight() {
   if(typeof (window.innerHeight) == "number") {
      return window.innerHeight;
      }
   else {
      if(document.documentElement && (document.documentElement.clientHeight)) {
         return document.documentElement.clientHeight;
         }
      else {
         if(document.body && (document.body.clientHeight)) {
            return document.body.clientHeight;
            }
         else {
            return 0;
            }
         }
      }
   }
function getWindowWidth() {
   if(typeof (window.innerWidth) == "number") {
      return window.innerWidth;
      }
   else {
      if(document.documentElement && (document.documentElement.clientWidth)) {
         return document.documentElement.clientWidth;
         }
      else {
         if(document.body && (document.body.clientWidth)) {
            return document.body.clientWidth;
            }
         else {
            return 0;
            }
         }
      }
   }
function ft(_7,noRoundTime) {
   var _8 = "";
   if((noRoundTime==null) && (_7 >= 2419200)) {
      return "\u221e";
      }
   var _9, w, d, h, m, s, v = 0;
   _9 = _7 % (604800 * 52);
   w = iv(_9 / 604800);
   _9 = _9 % 604800;
   d = iv(_9 / 86400);
   _9 = _9 % 86400;
   h = iv(_9 / 3600);
   _9 = _9 % 3600;
   m = iv(_9 / 60);
   _9 = _9 % 60;
   s = _9;
   if(w > 0) {
      _8 = w + WUILang.time_w;
      v++;
      }
   if(d > 0) {
      _8 += d + WUILang.time_d;
      v++;
      }
   if((h > 0) && (v < 2)) {
      _8 += h + WUILang.time_h;
      v++;
      }
   if((m > 0) && (v < 2)) {
      _8 += m + WUILang.time_m;
      v++;
      }
   if(v < 2) {
      _8 += s + WUILang.time_s;
      }
   var l = _8.length - 1;
   return _8.substring(0, l);
   }
function ffs(_b, p) {
   p = (p == null) ? 1 : p;
   var a = new Array(WUILang.bytes, WUILang.KB, WUILang.MB, WUILang.GB, WUILang.TB, WUILang.PB);
   var _e = 0;
   if(_b == 0) {
      _e = 1;
      }
   else {
      if(_b < 1024) {
         _b /= 1024;
         _e = 1;
         }
      else {
         while(_b >= 1024) {
            _b /= 1024;
            _e++;
            }
         }
      }
   return (round(_b, p) + " " + a[_e]);
   }
function iv(_f) 
{
	var v = parseInt(_f + "");
	return(isNaN(v) ? 0 : v);
}
function round(num, p) {
   var v = Math.floor(num * Math.pow(10, p)) / Math.pow(10, p);
   var s = "" + v + "";
   var d = s.indexOf(".");
   var n = 0;
   if(d >- 1) {
      var ind = s.length - d;
      p++;
      if(ind < p) {
         n = p - ind;
         }
      }
   else {
      if(p > 0) {
         n = p;
         s = s + ".";
         }
      }
   for(var i = 0; i < n; i++) {
      s += "0";
      }
   return s;
   }

var sav = null;
function saveDocument()
{
	if(browser.isKonqueror)
		sav = document.events;
}

function restoreDocument()
{
	if(browser.isKonqueror)
		document.events = sav;
}

function addEvent(_18, _19, fnc)
{
 	var _1a = fnc;
	if(_19=="keydown")
	{
		_19 = "click";	
		_1a = function() { document.onkeydown = function(e) { Key.onKeyDown(e); return(fnc(e)); } };
	}
	if(!_1a.$$guid)
      		_1a.$$guid = addEvent.guid++;
	if(!_18.events)
		_18.events = {};
	var _1b = _18.events[_19];
	if(!_1b)
	{
		_1b = _18.events[_19] = {};
		if(_18["on" + _19])
			_1b[0] = _18["on" + _19];
	}
	_1b[_1a.$$guid] = _1a;
	_18["on" + _19] = handleEvent;
	saveDocument();
	return _1a.$$guid;
}

addEvent.guid = 1;
function removeEvent(_1c, _1d, _1e) 
{
	if(_1c.events && _1c.events[_1d]) 
	{
		delete _1c.events[_1d][_1e];
	}
}

function removeElementEvents(el) 
{
	if(el.events)
	{
		for(var k in el.events)
		{
			for(var f in el.events[k])
			{
				delete el.events[k][f];
			}
			delete el.events[k];
		}
	}
}

function handleEvent(_1f)
{
	var ret = false;
	restoreDocument();
	if(this.events)
	{
		ret = true;
	        _1f = _1f || fixEvent(window.event);
		var _21 = this.events[_1f.type];
		for(var i in _21)
		{
			this.$$handleEvent = _21[i];
			if(this.$$handleEvent.apply(this, [_1f])===false)
				ret = false;
		}
	}
	return(ret);
}

function fixEvent(_23) {
   _23.preventDefault = fixEvent.preventDefault;
   _23.stopPropagation = fixEvent.stopPropagation;
   return _23;
   }
fixEvent.preventDefault = function() {
   this.returnValue = false;
   };
fixEvent.stopPropagation = function() {
   this.cancelBubble = true;
   };
var css_browser_selector = function() {
   var ua = navigator.userAgent.toLowerCase();
   var h = document.getElementsByTagName("html")[0];
   var c = h.className;
   if(ua.indexOf("msie") !=- 1 &&!(ua.indexOf("opera") !=- 1) && (ua.indexOf("webtv") ==- 1)) {
      h.className = "ie" + " ie" + ua.charAt(ua.indexOf("msie") + 5) + " " + c;
      }
   else {
      if(ua.indexOf("gecko/") !=- 1) {
         h.className = ("gecko " + c);
         }
      else {
         if(ua.indexOf("opera") !=- 1) {
            h.className = ("opera " + c);
            }
         else {
            if(ua.indexOf("konqueror") !=- 1) {
               h.className = ("konqueror " + c);
               }
            else {
               if(ua.indexOf("applewebkit/") !=- 1) {
                  h.className = ("webkit safari " + c);
                  }
               else {
                  if(ua.indexOf("mozilla/") !=- 1) {
                     h.className = ("gecko " + c);
                     }
                  }
               }
            }
         }
      }
   }
.apply(this);
function BrowserDetect() {
   var ua = navigator.userAgent.toLowerCase();
   this.isGecko = (ua.indexOf("gecko") !=- 1 && ua.indexOf("safari") ==- 1);
   this.isAppleWebKit = (ua.indexOf("applewebkit") !=- 1);
   this.isKonqueror = (ua.indexOf("konqueror") !=- 1);
   this.isSafari = (ua.indexOf("safari") !=- 1);
   this.isOmniweb = (ua.indexOf("omniweb") !=- 1);
   this.isOpera = (ua.indexOf("opera") !=- 1);
   this.isIcab = (ua.indexOf("icab") !=- 1);
   this.isAol = (ua.indexOf("aol") !=- 1);
   this.isIE = (ua.indexOf("msie") !=- 1 &&!this.isOpera && (ua.indexOf("webtv") ==- 1));
   this.isMozilla = (this.isGecko && ua.indexOf("gecko/") + 14 == ua.length);
   this.isFirebird = (ua.indexOf("firebird/") !=- 1);
   this.isFirefox = (ua.indexOf("firefox/") !=- 1);
   this.isNS = ((this.isGecko) ? (ua.indexOf("netscape") !=- 1) : ((ua.indexOf("mozilla") !=- 1) &&!this.isOpera &&!this.isSafari && (ua.indexOf("spoofer") ==- 1) && (ua.indexOf("compatible") ==- 1) && (ua.indexOf("webtv") ==- 1) && (ua.indexOf("hotjava") ==- 1)));
   this.isIECompatible = ((ua.indexOf("msie") !=- 1) &&!this.isIE);
   this.isNSCompatible = ((ua.indexOf("mozilla") !=- 1) &&!this.isNS &&!this.isMozilla);
   this.geckoVersion = ((this.isGecko) ? ua.substring((ua.lastIndexOf("gecko/") + 6), (ua.lastIndexOf("gecko/") + 14)) :- 1);
   this.equivalentMozilla = ((this.isGecko) ? parseFloat(ua.substring(ua.indexOf("rv:") + 3)) :- 1);
   this.appleWebKitVersion = ((this.isAppleWebKit) ? parseFloat(ua.substring(ua.indexOf("applewebkit/") + 12)) :- 1);
   this.versionMinor = parseFloat(navigator.appVersion);
   if(this.isGecko && !this.isMozilla && !this.isKonqueror) {
      this.versionMinor = parseFloat(ua.substring(ua.indexOf("/", ua.indexOf("gecko/") + 6) + 1));
      }
   else {
      if(this.isMozilla) {
         this.versionMinor = parseFloat(ua.substring(ua.indexOf("rv:") + 3));
         }
      else {
         if(this.isIE && this.versionMinor >= 4) {
            this.versionMinor = parseFloat(ua.substring(ua.indexOf("msie ") + 5));
            }
         else {
            if(this.isKonqueror) {
               this.versionMinor = parseFloat(ua.substring(ua.indexOf("konqueror/") + 10));
               }
            else {
               if(this.isSafari) {
                  this.versionMinor = parseFloat(ua.substring(ua.lastIndexOf("safari/") + 7));
                  }
               else {
                  if(this.isOmniweb) {
                     this.versionMinor = parseFloat(ua.substring(ua.lastIndexOf("omniweb/") + 8));
                     }
                  else {
                     if(this.isOpera) {
                        this.versionMinor = parseFloat(ua.substring(ua.indexOf("opera") + 6));
                        }
                     else {
                        if(this.isIcab) {
                           this.versionMinor = parseFloat(ua.substring(ua.indexOf("icab") + 5));
                           }
                        }
                     }
                  }
               }
            }
         }
      }
   this.versionMajor = parseInt(this.versionMinor);
   this.isDOM1 = (document.getElementById);
   this.isDOM2Event = (document.addEventListener && document.removeEventListener);
   this.mode = document.compatMode ? document.compatMode : "BackCompat";
   this.isWin = (ua.indexOf("win") !=- 1);
   this.isWin32 = (this.isWin && (ua.indexOf("95") !=- 1 || ua.indexOf("98") !=- 1 || ua.indexOf("nt") !=- 1 || ua.indexOf("win32") !=- 1 || ua.indexOf("32bit") !=- 1 || ua.indexOf("xp") !=- 1));
   this.isMac = (ua.indexOf("mac") !=- 1);
   this.isUnix = (ua.indexOf("unix") !=- 1 || ua.indexOf("sunos") !=- 1 || ua.indexOf("bsd") !=- 1 || ua.indexOf("x11") !=- 1);
   this.isLinux = (ua.indexOf("linux") !=- 1);
   this.isNS4x = (this.isNS && this.versionMajor == 4);
   this.isNS40x = (this.isNS4x && this.versionMinor < 4.5);
   this.isNS47x = (this.isNS4x && this.versionMinor >= 4.7);
   this.isNS4up = (this.isNS && this.versionMinor >= 4);
   this.isNS6x = (this.isNS && this.versionMajor == 6);
   this.isNS6up = (this.isNS && this.versionMajor >= 6);
   this.isNS7x = (this.isNS && this.versionMajor == 7);
   this.isNS7up = (this.isNS && this.versionMajor >= 7);
   this.isIE4x = (this.isIE && this.versionMajor == 4);
   this.isIE4up = (this.isIE && this.versionMajor >= 4);
   this.isIE5x = (this.isIE && this.versionMajor == 5);
   this.isIE55 = (this.isIE && this.versionMinor == 5.5);
   this.isIE5up = (this.isIE && this.versionMajor >= 5);
   this.isIE6x = (this.isIE && this.versionMajor == 6);
   this.isIE6up = (this.isIE && this.versionMajor >= 6);
   this.isIE7x = (this.isIE && this.versionMajor == 7);
   this.isIE7up = (this.isIE && this.versionMajor >= 7);
   this.isIE8up = (this.isIE && this.versionMajor >= 8);
   this.isOldIE = (this.isIE && this.versionMajor < 7);
   this.isIE4xMac = (this.isIE4x && this.isMac);
   this.isFirefox3x = (this.isFirefox && this.versionMajor == 3);
//alert(ua);
//alert(this.isAppleWebKit);
   }
var browser = new BrowserDetect();
function escapeHTML(str) {
   var div = document.createElement("div");
   var _2a = document.createTextNode(str);
   div.appendChild(_2a);
   return div.innerHTML;
   }

function getHttpObj() {
   var _32 = null;
   if(window.XMLHttpRequest) {
      try {
         _32 = new XMLHttpRequest();
         }
      catch(e) {
         return false;
         }
      }
   else {
      if(window.ActiveXObject) {
         var _33 = new Array("MSXML2", "Microsoft", "MSXML", "MSXML3");
         for(var i = 0; i < _33.length; i++) {
            try {
               _32 = new ActiveXObject(_33[i] + ".XmlHttp");
               break;
               }
            catch(e) {
               continue;
               }
            }
         }
      }
   return _32;
   }
var Timer = function() {
   this.initial = 0;
   this.interval = 0;
   };
Timer.prototype.start = function() {
   this.initial = (new Date()).getTime();
   };
Timer.prototype.stop = function() {
   this.interval = (new Date()).getTime() - this.initial;
   };
var mouse = new Object();
mouse = {
   "X" : 0, "Y" : 0, "update" : function(x, y) {
      this.X = x;
      this.Y = y;
      }
   , "button" : 0};
addEvent(document, "mousemove", function(e) {
   if(!e) {
      e = window.event; }
   var x = (e.clientX) ? e.clientX : e.pageX; var y = (e.clientY) ? e.clientY : e.pageY; mouse.update(x, y); }
);
function isArray() {
   var obj = this;
   if((obj.constructor.toString().indexOf("Array") >- 1) && (typeof obj.length != "undefined")) {
      return true;
      }
   else {
      return false;
      }
   }
function json_encode(obj) {
   if(typeof obj != "object") {
      return false;
      }
   var _3c = isArray.apply(obj);
   var _3d = (_3c) ? "[" : "{";
   for(var e in obj) {
      if(!_3c) {
         _3d += "\"" + e + "\":";
         }
      if((typeof obj[e] == "object") && (!isArray.apply(obj[e]))) {
         _3d += json_encode(obj[e]) + ",";
         }
      else {
         if(isArray.apply(obj[e])) {
            _3d += json_encode(obj[e]) + ",";
            }
         else {
            if(typeof obj[e] == "string") {
               _3d += "\"" + obj[e] + "\",";
               }
            else {
               if(typeof obj[e] == "number") {
                  _3d += obj[e] + ",";
                  }
               else {
                  if(typeof obj[e] == "boolean") {
                     _3d += ((obj[e]) ? "true" : "false") + ",";
                     }
                  }
               }
            }
         }
      }
   if((_3d != "{") && (_3d != "[")) {
      _3d = _3d.substr(0, _3d.length - 1);
      }
   return _3d + ((_3c) ? "]" : "}");
   }
var cookies = new Object();
var $_COOKIE = null;
cookies = {
   "setCookie" : function(_3f, _40, _41) {
      if(_41 == true) {
         delete $_COOKIE[_3f];
         }
      else {
         $_COOKIE[_3f] = _40;
         }
      }
   , "setCookies" : function() {
      var v = json_encode($_COOKIE);
      utWebUI.Request("?action=setuisettings&s=webui.cookie&v=" + v);
      utWebUI.settings["webui.cookie"].v = v;
      }
   , "getCookie" : function(_43) {
      return $_COOKIE[_43];
      }
   , "getCookies" : function() {
      var v = utWebUI.settings["webui.cookie"].v;
      if(v == "") {
         v = "{}";
         }
      return eval("(" + v + ")");
      }
   , "removeCookie" : function(_45, _46, _47) {
      this.setCookie(_45, "", true);
      }
   };
var Key = { LEFT : 37, RIGHT : 39, UP : 38, DOWN : 40, BACKSPACE : 8, CAPSLOCK : 20, CTRL : 17, DELETE : 46, END : 35, ENTER : 13, ESCAPE : 27, HOME : 36, INSERT : 45, TAB : 9, PGDN : 34, PGUP : 33, SPACE : 32, SHIFT : 16 };

Key.onKeyDown = function() {};
Key.onKeyUp = function() {};

Key.addListener = function(o, ev)
{
	if(typeof ev.onKeyDown == "function")
		addEvent(o, "keydown", ev.onKeyDown);
	if(typeof ev.onKeyUp == "function")
		addEvent(o, "keyup", ev.onKeyUp);
}

Key.init = function()
{
	document.onkeydown = Key.onKeyDown;
	document.onkeyup = Key.onKeyUp;
	if(browser.isKonqueror)
		Key.DELETE = 127;
}

Key.init();

function inScrollBarArea(obj,x,y)
{
	if(obj.tagName)
	{
		var tg = obj.tagName.toLowerCase();
		if(tg=="input" || tg=="textarea")
			return(true);
	}
	var c = getAbsCoords(obj);
	return((obj.scrollHeight > obj.clientHeight) && (x>obj.clientWidth+c[0]));
}

var Drag = {
   obj : null, mask : null, zindex : 2000, mid : 0, uid : 0, init : function(o, _4f, _50, _51, _52, _53, _54) {
      o.onmousedown = Drag.start;
      _4f.style.zIndex = Drag.zindex;
	if(browser.isOpera)
      		_4f.onmousedown = function(ev) {
			ev = ev || window.event;
			var obj = (ev.srcElement) ? ev.srcElement : ev.target;
			if(!inScrollBarArea(obj,ev.clientX,ev.clientY))
				this.style.zIndex = Drag.zindex++; 
		      };
	else
		_4f.onmousedown = function() { this.style.zIndex = Drag.zindex++; };
      o.root = (_4f && (_4f != null)) ? _4f : o;
      if(_54) {
         o.root.style.visibility = "hidden";
         o.root.style.display = "block";
         var w = (iv(o.root.offsetWidth) == 0) ? iv(o.root.style.width) : iv(o.root.offsetWidth);
         var l = (getWindowWidth() / 2) - (w / 2) + "px";
         var h = (iv(o.root.offsetHeight) == 0) ? iv(o.root.style.height) : iv(o.root.offsetHeight);
         var t = (getWindowHeight() / 2) - (h / 2) + "px";
         if(isNaN(parseInt(o.root.style.left))) {
            o.root.style.left = (getWindowWidth() / 2 - iv(o.root.offsetWidth) / 2) + "px";
            }
         if(isNaN(parseInt(o.root.style.top))) {
            o.root.style.top = (getWindowHeight() / 2 - iv(o.root.offsetHeight) / 2) + "px";
            }
         o.root.style.display = "none";
         o.root.style.visibility = "visible";
         }
      if(isNaN(parseInt(o.root.style.left))) {
         o.root.style.left = "0px";
         }
      if(isNaN(parseInt(o.root.style.top))) {
         o.root.style.top = "0px";
         }
      o.minX = typeof _50 != "undefined" ? _50 : null;
      o.minY = typeof _52 != "undefined" ? _52 : null;
      o.maxX = typeof _51 != "undefined" ? _51 : null;
      o.maxY = typeof _53 != "undefined" ? _53 : null;
      o.root.onDragStart = new Function();
      o.root.onDragEnd = new Function();
      o.root.onDrag = new Function();
      }
   , start : function(e) {
      var o = Drag.obj = this;
      e = Drag.fixE(e);
      var y = parseInt(o.root.style.top);
      var x = parseInt(o.root.style.left);
      o.root.onDragStart(x, y);
      o.lastMouseX = e.clientX;
      o.lastMouseY = e.clientY;
      var mw = (o.root.style.width != "") ? iv(o.root.style.width) : (o.root.offsetWidth - 1);
      var mh = (o.root.style.height != "") ? iv(o.root.style.height) : (o.root.offsetHeight - 1);
      if(o.minX != null) {
         o.minMouseX = e.clientX - x + o.minX;
         }
      if(o.maxX != null) {
         o.maxMouseX = o.minMouseX + o.maxX - o.minX - mw;
         }
      if(o.minY != null) {
         o.minMouseY = e.clientY - y + o.minY;
         }
      if(o.maxY != null) {
         o.maxMouseY = o.minMouseY + o.maxY - o.minY - mh;
         }
      Drag.mask.style.width = mw + "px";
      Drag.mask.style.height = mh + "px";
      Drag.mask.style.left = x + "px";
      Drag.mask.style.top = y + "px";
      var _5f = Drag;
      Drag.mid = addEvent(document, "mousemove", function(e) {
         return(_5f.drag(e)); }
      );
      Drag.uid = addEvent(document, "mouseup", function(e) {
         return(_5f.end(e)); }
      );
      return false;
      }
   , drag : function(e) {
      e = Drag.fixE(e);
      var o = Drag.obj;
      Drag.mask.style.display = "block";
      var ey = e.clientY;
      var ex = e.clientX;
      var y = parseInt(Drag.mask.style.top);
      var x = parseInt(Drag.mask.style.left);
      var nx, ny;
      if(o.minX != null) {
         ex = Math.max(ex, o.minMouseX);
         }
      if(o.maxX != null) {
         ex = Math.min(ex, o.maxMouseX);
         }
      if(o.minY != null) {
         ey = Math.max(ey, o.minMouseY);
         }
      if(o.maxY != null) {
         ey = Math.min(ey, o.maxMouseY);
         }
      nx = x + (ex - o.lastMouseX);
      ny = y + (ey - o.lastMouseY);
      Drag.mask.style["left"] = nx + "px";
      Drag.mask.style["top"] = ny + "px";
      Drag.obj.lastMouseX = ex;
      Drag.obj.lastMouseY = ey;
      Drag.obj.root.onDrag(nx, ny);
      try {
         document.selection.empty();
         }
      catch(ex) {
         }
      return false;
      }
   , end : function() {
      var o = Drag.obj;
      var y = parseInt(Drag.mask.style.top);
      var x = parseInt(Drag.mask.style.left);
      Drag.obj.root.style.visibility = "hidden";
      Drag.obj.root.style["left"] = x + "px";
      Drag.obj.root.style["top"] = y + "px";
      Drag.obj.root.style.visibility = "visible";
      Drag.mask.style.display = "none";
      removeEvent(document, "mousemove", Drag.mid);
      removeEvent(document, "mouseup", Drag.uid);
      Drag.obj.root.onDragEnd(parseInt(Drag.obj.root.style[Drag.obj.hmode ? "left" : "right"]), parseInt(Drag.obj.root.style[Drag.obj.vmode ? "top" : "bottom"]));
      Drag.obj = null;
	return(false);
      }
   , fixE : function(e) {
      if(typeof e == "undefined") {
         e = window.event;
         }
      if(typeof e.layerX == "undefined") {
         e.layerX = e.offsetX;
         }
      if(typeof e.layerY == "undefined") {
         e.layerY = e.offsetY;
         }
      return e;
      }
   }; 

function askYesNo( title, content, funcYesName )
{
	$$("yesnoDlg-header").innerHTML = title;
	$$("yesnoDlg-content").innerHTML = content;
	$$("yesnoDlg-buttons").innerHTML = 
		"<input type='button' onfocus=\"javascript:this.blur();\" class='Button' value="+WUILang.ok1+" onclick='javascript:"+funcYesName+";HideModal(\"yesnoDlg\");return(false);'/><input type='button' class='Button' value="+WUILang.no1+" onfocus=\"javascript:this.blur();\" onclick='javascript:HideModal(\"yesnoDlg\");return(false);'/>";
	ShowModal("yesnoDlg");
}

var C = null;
function addRightClickHandler( obj, handler )
{
	if(browser.isOpera && !("oncontextmenu"in document.createElement("foo")))
	{
        	addEvent(obj,"mousedown",
			function(e)
			{
				if(e.button==2)
				{
					if(e.target)
					{
						var F = e.target.ownerDocument;
						if(C)
							C.parentNode.removeChild(C);
						C = F.createElement("input");
						C.type = "button"; 
						C.style.cssText = "z-index: 10000;position:absolute;top:" + (e.clientY - 2) + "px;left:" + (e.clientX - 2) + "px;width:5px;height:5px;opacity:0.01";
						(F.body || F.documentElement).appendChild(C);
					}
					handler(obj);
					return(false);
				}
				return(true);
			});
		addEvent(obj,"mouseup", 
			function(D)
			{
				if(C)
				{
					C.parentNode.removeChild(C);
					C = null;
					if((D.button==2) &&! (/^input|textarea|a$/i).test(D.target.tagName))
					{
						CancelEvent(D);
						return(false);
					}
				}
				return(true);
			});
	}
	else
		addEvent(obj, "mousedown", function(e) { if(e.button==2) { handler(obj); return(false);}; return(true); });
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

function rDirectory()
{
	this.dirs = new Array();
	this.dirs[""] = new Array();
	this.current = "";
}

rDirectory.prototype.addFile = function(aData,no)
{
	var name = aData[0];
	var fileAdded = false;
	while(name.length)
	{
		var file = splitName(name);
		if(!this.dirs[file.path])
		{
			this.dirs[file.path] = new Array();
			var up = splitName(file.path).path;
			this.dirs[file.path]["_d_"+up] = { data: ["..",null,null,null,-2], icon: "Icon_Dir", link: up };
		}
		if(!fileAdded)
		{
			var sId = "_f_"+no;
			aData[0] = file.name;
		        if(this.dirs[file.path][sId])
				this.dirs[file.path][sId].data = aData;
			else
				this.dirs[file.path][sId] = { data: aData, icon: "Icon_File", link: null };
			fileAdded = true;
		}
		else
		{
			var sId = "_d_"+name;
			if(!this.dirs[file.path][sId])
				this.dirs[file.path][sId] = { data: [file.name,0,0,0.0,-1], icon: "Icon_Dir", link: name };
		}
		name = file.path;
	} 
}

rDirectory.prototype.updateDirs = function(name)
{
	var dir = this.dirs[name];
	var allStat = new Array(0,0,0,-2);
	var stat;
	for(var i in dir) 
	{
		if(dir[i].data[0]!="..")
		{
			if(dir[i].link!=null)
			{
				stat = this.updateDirs(dir[i].link)
				dir[i].data[1] = stat[0];
				dir[i].data[2] = stat[1];
				dir[i].data[3] = ((dir[i].data[1] > 0) ? round((dir[i].data[2]/dir[i].data[1])*100,1): "100.0");
				dir[i].data[4] = stat[3];
			}
			else
				stat = dir[i].data.slice(1);
			allStat[0]+=stat[0];
			allStat[1]+=stat[1];
			if(allStat[3]==-2)
				allStat[3] = stat[3];
			else
				if(allStat[3]!=stat[3]) 
					allStat[3] = -1;
		}
	}
	return(allStat);
}

rDirectory.prototype.getEntryPriority = function(k)
{
	var entry = this.dirs[this.current][k];
	return((entry.data[0]=="..") ? null : entry.data[4]);
}

rDirectory.prototype.getFilesIds = function(arr,current,k,prt)
{
	var entry = this.dirs[current][k];
	if(entry.data[0]!="..")
	{
		if(entry.link!=null)
		{
	        	for(var i in this.dirs[entry.link])
				this.getFilesIds(arr,entry.link,i,prt);
		}
		else
			if(entry[3]!=prt)
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
