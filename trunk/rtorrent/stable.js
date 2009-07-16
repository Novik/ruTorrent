var TYPE_STRING = 0;
var TYPE_NUMBER = 1;
var TYPE_DATE = 2;
var TYPE_STRING_NO_CASE = 3;
var ALIGN_AUTO = 0;
var ALIGN_LEFT = 1;
var ALIGN_CENTER = 2;
var ALIGN_RIGHT = 3;

var dxSTable = function() 
{
	this.rows = 0;
	this.rowdata = new Object();
	this.rowIDs = new Array();
	this.rowSel = new Array();
	this.maxRows = 50;
	this.viewRows = 0;
	this.cols = 0;
	this.colsdata = new Array();
	this.stSel = null;
	this.format = function(r) { return r; };
	this.sIndex =- 1;
	this.reverse = 0;
	this.secIndex = 0;
	this.secRev = 0;
	this.tBody = null;
	this.tHead = null;
	this.tHeadCols = new Array();
	this.tBodyCols = new Array();
	this.colorEvenRows = false;
	this.paletteURL = "./palette/0";
	this.sortAscImage = this.paletteURL+"/images/asc.gif";
	this.sortDescImage = this.paletteURL+"/images/desc.gif";
	this.cancelSort = false;
	this.cancelMove = false;
	this.colMove = new dxSTable.ColumnMove(this);
	this.colOrder = new Array();
	this.onselect = null;
	this.ondelete = null;
	this.onsort = null;
	this.onmove = null;
	this.onresize = null;
	this.oncoltoggled = null;
	this.ondblclick = null;
	this.sortTimeout = null;
	this.hotCell =- 1;
	this.isMoving = false;
	this.isResizing = false;
	this.isSorting = false;
	this.mmDrag = 0;
	this.muDrag = 0;
	this.selCount = 0;
	this.created = false;
};

dxSTable.prototype.setPalette = function(palette) 
{
	this.paletteURL = "./palette/"+palette;	
	this.sortAscImage = this.paletteURL+"/images/asc.gif";
	this.sortDescImage = this.paletteURL+"/images/desc.gif";
	this.Sort();
}

dxSTable.prototype.setPaletteByURL = function(url) 
{
	this.paletteURL = url;	
	this.sortAscImage = url+"/images/asc.gif";
	this.sortDescImage = url+"/images/desc.gif";
	this.Sort();
}

dxSTable.prototype.create = function(_2, _3, aName)
{
	var tr, td, cl, cg, div;
	this.name = aName;
	this.dCont = _2;
	this.dHead = ELE_DIV.cloneNode(true);
	this.dBody = ELE_DIV.cloneNode(true);
	this.dCont.className = "stable";
	this.dHead.className = "stable-head";
	this.dBody.className = "stable-body";
	this.dCont.appendChild(this.dHead);
	this.dCont.appendChild(this.dBody);
	this.tHead = ELE_TABLE.cloneNode(true);
	this.tHead.style.width = "100px";
	this.tHead.cellSpacing = 0;
	this.tHead.cellPadding = 0;
	this.dHead.appendChild(this.tHead);
	this.tHead.tb = ELE_TBODY.cloneNode(true);
	this.tHead.appendChild(this.tHead.tb);
	tr = ELE_TR.cloneNode(true);
	this.tHead.tb.appendChild(tr);
	var _5 = this, span;
	var j = 0;
	if(this.sIndex>=_3.length)
		this.sIndex = -1;

	for(var i in this.colOrder)
	{
		if(this.colOrder[i]>=_3.length)
		{
			this.colOrder = new Array();
			break;
		}
	}

	for(var i = 0, l = _3.length; i < l; i++) 
	{
		if(typeof this.colOrder[i] == "undefined")
			this.colOrder[i] = i;
		if(typeof _3[this.colOrder[i]].enabled == "undefined") 
		{
			_3[this.colOrder[i]].enabled = true;
		}
		this.cols++;
		this.colsdata[i] = _3[this.colOrder[i]];

		td = ELE_TD.cloneNode(true);
		tr.appendChild(td);

		td.onmousemove = function(e) 
			{
				if(_5.isResizing) 
				{
					return;
            			}
				e = FixEvent(e);
				var x = mouse.X - getOffsetLeft(this) + _5.dBody.scrollLeft;
				this.lastMouseX = e.clientX;
				var w = this.offsetWidth;
				var i = parseInt(this.getAttribute("index"));
				if(x <= 8) 
				{
					if(i != 0) 
					{
						_5.hotCell = i - 1;
						this.style.cursor = "e-resize";
					}
					else 
					{
						_5.hotCell =- 1;
						this.style.cursor = "default";
					}
				}
				else 
				{
					if(x >= w - 1) 
					{
						_5.hotCell = i;
						this.style.cursor = "e-resize";
					}
					else 
					{
						_5.hotCell =- 1;
						this.style.cursor = "default";
					}
				}
         		};
		span = ELE_DIV.cloneNode(true);
		span.innerHTML = _3[this.colOrder[i]].text;
		td.appendChild(span);
		td.style.width = (_3[this.colOrder[i]].width) ? _3[this.colOrder[i]].width : "auto";
		td.setAttribute("index", i);
		this.colMove.init(td, preventSort, null, moveColumn);
		if(!browser.isIE8up)
			addRightClickHandler( td, function(e) { return(_5.onRightClick(e, this)); });
		addEvent(td, "mouseup", function(e) { return(_5.Sort(e, this)); });
		this.tHeadCols[i] = td;
		if(!this.colsdata[i].enabled && !browser.isIE8up) 
			td.style.display = "none";
		j++;
	}
	this.tBody = ELE_TABLE.cloneNode(true);
	this.tBody.style.width = "0";
	this.tBody.cellSpacing = 0;
	this.tBody.cellPadding = 0;
	if(!browser.isOldIE)
	{
		this.tpad = ELE_DIV.cloneNode(true);
		this.tpad.className = "stable-virtpad";
		this.dBody.appendChild(this.tpad);
	}
	this.dBody.appendChild(this.tBody);
	this.bpad = ELE_DIV.cloneNode(true);
	this.bpad.className = "stable-virtpad";
	this.dBody.appendChild(this.bpad);
	this.tBody.tb = ELE_TBODY.cloneNode(true);
	this.tBody.appendChild(this.tBody.tb);
	cg = ELE_COLGROUP.cloneNode(true);
	this.tBody.appendChild(cg);
	for(var i = 0, j = 0; i < _3.length; i++) 
	{
		cl = ELE_COL.cloneNode(true);
		if(!browser.isOldIE)
		{
			cl.style.width = (this.colsdata[i].width) ? this.colsdata[i].width : "auto";
		}
		cg.appendChild(cl);
		this.tBodyCols[j] = cl;
      		if(!this.colsdata[i].enabled && !browser.isIE8up)
		{
			cl.style.display = "none";
         	}
		j++;
	}
	this.scp = ELE_SPAN.cloneNode(true);
	this.scp.className = "stable-scrollpos";
	this.dCont.appendChild(this.scp);
	this.dCont.style.position = "relative";
	this.init();
	addEvent(window, "unload", function() { _5.clearRows(); });
	this.calcSize();
	resizeColumn.apply(this);
	this.created = true;
};

dxSTable.prototype.toggleColumn = function(i)
{
	ToggleB(this.tBodyCols[i]);
	ToggleB(this.tHeadCols[i]);
	this.colsdata[i].enabled = !this.colsdata[i].enabled;
        for (var D = 0, B = this.tBody.tb.childNodes.length; D < B; D ++ )
        {
		ToggleB(this.tBody.tb.childNodes[D].childNodes[i]);
	}
        this.dHead.scrollLeft = this.dBody.scrollLeft;
        this.calcSize();
        resizeColumn.apply(this);
        if(typeof this.oncoltoggled == "function")
	{
		this.oncoltoggled();
	}
}

dxSTable.prototype.onRightClick = function(e)
{
	ContextMenu.clear();
	for(var i = 0; i<this.colsdata.length; i++)
	{
		if(this.colOrder[i])
		{
			if(this.colsdata[i].enabled)
				ContextMenu.add([CMENU_SEL, this.colsdata[i].text, this.name+".toggleColumn("+i+")"]);
			else
				ContextMenu.add([this.colsdata[i].text, this.name+".toggleColumn("+i+")"]);
		}
	}
	ContextMenu.show();
	return(false);
}

dxSTable.prototype.resizeHack = function()
{
	if(browser.isOpera || browser.isFirefox || browser.isAppleWebKit || browser.isKonqueror || browser.isIE8up)
		resizeColumn.apply(this);
}

var preventSort = function() 
{
	this.cancelSort = true;
};

dxSTable.prototype.calcSize = function() 
{
	if(this.dCont.offsetWidth >= 4) 
	{
		this.dBody.style.width = this.dCont.offsetWidth - 2 + "px";
		this.dBody.style.paddingTop = this.dHead.offsetHeight + "px";
		this.tBody.style.width = this.tHead.offsetWidth + "px";
		var h = this.dCont.clientHeight - this.dHead.offsetHeight;
		if(h >= 0) 
			this.dBody.style.height = h + "px";
		var nsb = -2;
		if((this.dBody.offsetWidth != this.dBody.clientWidth) && (window.scrollbarWidth!=null))
			nsb-=window.scrollbarWidth;

		this.dHead.style.width = this.dCont.clientWidth + nsb + "px";
		if((this.cols > 0) && (!this.isResizing)) 
		{
			var j =- 1;
			for(var i = 0, l = this.cols; i < l; i++) 
			{
				var _9a = iv(this.tBodyCols[i].style.width);
				if(browser.isIE && (this.tBodyCols[i].offsetWidth != 0)) 
				{
					_9a = this.tBodyCols[i].offsetWidth;
				}
				if(!_9a) 
				{
					continue;
				}
				if(browser.isAppleWebKit || browser.isKonqueror)
					_9a+=4;
				if(_9a>8)
					this.tHeadCols[i].style.width = (_9a - 4) + "px";
			}
		}
	}
};

var resizeColumn = function() 
{
	var _e = this.tBody.getElementsByTagName("colgroup")[0].getElementsByTagName("col");
	var needCallHandler = false;
	var w = 0, c;
	for(var i = 0, l = _e.length; i < l; i++) 
	{
		c = this.tHeadCols[i];
		w = (browser.isIE) ? iv(c.style.width)+2 : c.offsetWidth;
		if(browser.isIE8up)
			w = w + 2;
		if(iv(_e[i].style.width)!=w)
		{
			_e[i].style.width = w + "px";
			needCallHandler = true;
		}
		if(
			(browser.isAppleWebKit || browser.isKonqueror || browser.isIE8up) &&
			this.tBody.rows.length>0)
		{
			if(this.tBody.rows[0].cells[i].width!=w)
			{
				this.tBody.rows[0].cells[i].width=w;
				needCallHandler = true;
			}
			for( var j=0; j<this.tBody.rows.length; j++ )
				this.tBody.rows[j].cells[i].style.textAlign = c.style.textAlign;
		}
	}
	this.tBody.tb.style.width = this.tHead.offsetWidth + "px";
	this.tBody.style.width = this.tHead.offsetWidth + "px";
	if((typeof this.onresize == "function") && needCallHandler)
	{
		this.onresize();
	}
};

var moveColumn = function(_11, _12) {
   var i, l, oParent, oCol, oBefore, aRows, a;
   if(_11 == _12) {
      return;
      }
   oCol = this.tHeadCols[_11];
   oParent = oCol.parentNode;
   if(_12 == this.cols) {
      oParent.removeChild(oCol);
      oParent.appendChild(oCol);
      }
   else {
      oBefore = this.tHeadCols[_12];
      oParent.removeChild(oCol);
      oParent.insertBefore(oCol, oBefore);
      }
   oCol = this.tBody.getElementsByTagName("colgroup")[0].getElementsByTagName("col")[_11];
   oParent = oCol.parentNode;
   if(_12 == this.cols) {
      oParent.removeChild(oCol);
      oParent.appendChild(oCol);
      }
   else {
      oBefore = this.tBody.getElementsByTagName("colgroup")[0].getElementsByTagName("col")[_12];
      oParent.removeChild(oCol);
      oParent.insertBefore(oCol, oBefore);
      }
   aRows = this.tBody.tb.rows;
   l = aRows.length;
   i = 0;
   while(i < l) {
      oCol = aRows[i].cells[_11];
      oParent = aRows[i];
      if(_12 == this.cols) {
         oParent.removeChild(oCol);
         oParent.appendChild(oCol);
         }
      else {
         oBefore = aRows[i].cells[_12];
         oParent.removeChild(oCol);
         oParent.insertBefore(oCol, oBefore);
         }
      i++;
      }
   var aHC = new Array();
   var aBC = new Array();
   var aC = new Array();
   var aO = new Array();
   oCol = this.tHeadCols[_11];
   var _18 = this.tBodyCols[_11];
   for(i = 0; i < this.cols; i++) {
      if(i == _11) {
         continue;
         }
      if(i == _12) {
         aHC.push(oCol);
         aBC.push(_18);
         aC.push(this.colsdata[_11]);
         aO.push(this.colOrder[_11]);
         }
      aHC.push(this.tHeadCols[i]);
      aBC.push(this.tBodyCols[i]);
      aC.push(this.colsdata[i]);
      aO.push(this.colOrder[i]);
      }
   if(_12 == this.cols) {
      aHC.push(oCol);
      aBC.push(_18);
      aC.push(this.colsdata[_11]);
      aO.push(this.colOrder[_11]);
      }
   this.tHeadCols = aHC.slice(0);
   this.tBodyCols = aBC.slice(0);
   this.colsdata = aC.slice(0);
   this.colOrder = aO.slice(0);
   for(i = 0; i < this.cols; i++) {
      this.tHeadCols[i].setAttribute("index", i);
      }
   if((_12 == this.sIndex) && (_11 > _12)) {
      this.sIndex = _12 + 1;
      }
   else {
      if((_11 < _12) && (this.sIndex < _12) && (this.sIndex > _11)) {
         this.sIndex--;
         }
      else {
         if(_11 == this.sIndex) {
            this.sIndex = _12;
            if(_12 > _11) {
               this.sIndex = _12 - 1;
               }
            }
         }
      }
   this.cancelSort = false;
   if(typeof this.onmove == "function") {
      this.onmove();
      }
   };
dxSTable.ColumnMove = function(p) {
   this.parent = p;
   this.obj = ELE_DIV.cloneNode(true);
   this.obj.className = "stable-move-header";
   this.sepobj = ELE_DIV.cloneNode(true);
   this.sepobj.className = "stable-separator-header";
   };
dxSTable.ColumnMove.prototype = {
   "parent" : null, "obj" : null, "sepobj" : null, "added" : false, "rx" :- 1, "index" :- 1, "indexnew" :- 1, "mid" : 0, "uid" : 0, "init" : function(o, _1b, _1c, _1d) {
      var _1e = this;
      o.onmousedown = function(e) {
         if(_1e.parent.hotCell >- 1) {
            return;
            }
         return _1e.start(e, this);
         };
      this.onDrag = _1c || new Function();
      this.onDragEnd = _1d || new Function();
      }
   , "start" : function(e, p) {
      if(this.parent.cancelMove) {
         return;
         }
      e = FixEvent(e);
	if(e && e.button==2)
		return(true);
      this.parent.isMoving = true;
      var o = this.obj;
      this.index = parseInt(p.getAttribute("index"));
      while(o.firstChild) {
         o.removeChild(o.firstChild);
         }
      o.appendChild(document.createTextNode(p.lastChild.innerHTML));
      o.style.width = (p.offsetWidth - 16) + "px";
      o.style.left = p.offsetLeft + "px";
      var _23 = (this.parent.colsdata[this.index].type == TYPE_NUMBER) ? "right" : "left";
      o.style.textAlign = _23;
      this.sepobj.style.left = p.offsetLeft + "px";
      o.lastMouseX = e.clientX;
      o.style.visibility = "visible";
      var _24 = this;
      this.mid = addEvent(document, "mousemove", function(e) {
         return(_24.drag(e)); }
      );
      this.uid = addEvent(document, "mouseup", function(e) {
         return(_24.end(e)); }
      );
      this.rx = getLeftPos(this.parent.dHead);
      return false;
      }
   , "drag" : function(e) 
   {
	e = FixEvent(e);
	this.parent.cancelSort = true;
	var o = this.obj, l = parseInt(o.style.left), ex = e.clientX, i = 0, c = this.parent.cols;
	if(!this.added) 
	{
		this.parent.dHead.appendChild(this.obj);
		this.parent.dHead.appendChild(this.sepobj);
		this.added = true;
	}
	l += ex;
	if(typeof o.lastMouseX == "undefined")
		o.lastMouseX = ex;
	l -= o.lastMouseX;
	o.style.left = l + "px";
	var ox = 0;
	var orx = ex + this.parent.dBody.scrollLeft - this.rx;
	for(i = 0; i < c; i++) 
	{
		ox += this.parent.tHeadCols[i].offsetWidth;
		if(ox > orx) 
		{
			break;
		}
	}
	if(i >= c) 
	{
		this.sepobj.style.left = this.parent.tHeadCols[c - 1].offsetLeft + this.parent.tHeadCols[c - 1].offsetWidth - 1 + "px";
		i = c;
	}
	else 
	{
		this.sepobj.style.left = this.parent.tHeadCols[i].offsetLeft + "px";
	}
	this.indexnew = i;
	this.obj.lastMouseX = ex;
	this.onDrag.apply(this.parent, [i]);
	return(false);
   }
   , "end" : function() {
      try {
         this.parent.dHead.removeChild(this.obj);
         this.parent.dHead.removeChild(this.sepobj);
         this.added = false;
         this.onDragEnd.apply(this.parent, [this.index, this.indexnew]);
         }
      catch(e) {
         }
      this.index =- 1;
      this.indexnew =- 1;
      this.parent.isMoving = false;
      this.parent.cancelSort = false;
      removeEvent(document, "mousemove", this.mid);
      removeEvent(document, "mouseup", this.uid);
      return(false);
      }
   };
function getLeftPos(obj) {
   var x = 0;
   while(obj) {
      x += obj.offsetLeft;
      obj = obj.offsetParent;
      }
   return x;
   }
function FixEvent(e) {
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

dxSTable.prototype.renameColumn = function(no,name) 
{
	no = this.getColOrder(no);
	if(no>=0)
	{
		this.colsdata[no].text = name;
		this.tHead.tb.rows[0].cells[no].firstChild.innerHTML = name;
	}
}

dxSTable.prototype.Sort = function(e) 
{
	if(this.cancelSort) 
		return(true);
	this.isSorting = true;
	var col = null;
	var rev = true;
	if(e == null) 
	{
		if(this.sIndex ==- 1) 
		{
			return(true);
		}
		rev = false;
		col = this.tHead.tb.rows[0].cells[this.sIndex];
	}
	else 
	{
		e = FixEvent(e);
		col = (e.target) ? e.target : e.srcElement;
	}
	if(e && e.button==2)
		return(true);
	if(col.tagName == "DIV") 
	{
		col = col.parentNode;
	}
	var ind = parseInt(col.getAttribute("index"));
	if(e && e.shiftKey)
	{
		if(this.secIndex == ind) 
		{
			this.secRev = 1 - this.secRev;
		}
		else 
		{
			this.secRev = 0;
		}
		this.secIndex = ind;
		ind = this.sIndex;
		rev = false;
		col = this.tHead.tb.rows[0].cells[this.sIndex];
	}
	if(rev) 
	{
		if(this.sIndex == ind) 
		{
			this.reverse = 1 - this.reverse;
		}
		else 
		{
			this.reverse = 0;
		}
	}
	if(this.sIndex >= 0) 
	{
		var td = this.tHead.tb.rows[0].cells[this.sIndex];
		td.style.backgroundImage = "url("+this.paletteURL+"/images/blank.gif)";
	}
	var _33 = (this.reverse) ? this.sortAscImage : this.sortDescImage;
	col.style.backgroundImage = "url(" + _33 + ")";
	var _34 = this.colsdata[ind].type;
	this.sIndex = ind;
	var d = this.getCache(ind);
	var u = d.slice(0);
	var _37 = this;
	switch(_34) 
	{
		case TYPE_STRING : 
			d.sort(function(x, y) { return _37.sortAlphaNumeric(x, y); });
      			break;
      		case TYPE_NUMBER : 
      			d.sort(function(x, y) { return _37.sortNumeric(x, y); });
      			break;
      		default : 
      			d.sort();
      			break;
      	}
   	if(this.reverse) 
   	{
		d.reverse();
	}
	this.rowIDs = [];
	var c = 0, i = 0;
	while(i < this.rows) 
	{
		this.rowdata[d[i].key] = d[i].e;
		this.rowIDs.push(d[i].key);
      		i++;
	}
	this.clearCache(d);
	this.clearCache(u);
	this.isSorting = false;
	if(!this.isScrolling) 
	{
		this.refreshRows();
	}
	this.calcSize();
	this.resizeHack();
	if(typeof this.onsort == "function") 
	{
		this.onsort();
	}
	return(false);
};

dxSTable.prototype.sortNumeric = function(x, y) {
   var r = Sort.Numeric(x.v, y.v);
   if(r == 0) {
      return this.sortSecondary(x, y);
      }
   else {
      return r;
      }
   };
dxSTable.prototype.sortAlphaNumeric = function(x, y) {
   var r = Sort.AlphaNumeric(x.v, y.v);
   if(r == 0) {
      return this.sortSecondary(x, y);
      }
   else {
      return r;
      }
   };
dxSTable.prototype.sortSecondary = function(x, y) {
   var m = this.getValue(x.e, this.secIndex);
   var n = this.getValue(y.e, this.secIndex);
   if(this.secRev) {
      var _47 = m;
      m = n;
      n = _47;
      }
   var _48 = this.colsdata[this.colOrder[this.secIndex]].type;
   switch(_48) {
      case 0 : return Sort.AlphaNumeric(m, n);
      break;
      case 1 : return Sort.Numeric(m, n);
      break;
      default : return Sort.Default(m, n);
      break;
      }
   };
var Sort = new Object();
Sort = {
   "Default" : function(x, y) {
	if(x==null) x = "";
	if(y==null) y = "";
      var a = x + "";
      var b = y + "";
      if(a < b) {
         return - 1;
         }
      else {
         if(a > b) {
            return 1;
            }
         else {
            return 0;
            }
         }
      }
   , "Numeric" : function(x, y) {
	if(x==null) x = 0;
	if(y==null) y = 0;
      var a = parseFloat(x + ""), b = parseFloat(y + ""), r = null;
      return (a - b);
      }
   , "AlphaNumeric" : function(x, y) {
	if(x==null) x = "";
	if(y==null) y = "";
      var a = (x + "").toLowerCase();
      var b = (y + "").toLowerCase();
      if(a < b) {
         return - 1;
         }
      else {
         if(a > b) {
            return 1;
            }
         else {
            return 0;
            }
         }
      }
   };

dxSTable.prototype.init = function() 
{
	if(navigator.product == "Gecko") 
	{
		for(var n = 0, l = document.styleSheets.length; n < l; n++) 
		{
			if(!document.styleSheets[n].href || (document.styleSheets[n].href.indexOf("style.css") ==- 1)) 
			{
				continue;
			}
			try {
			var _55 = document.styleSheets[n].cssRules;
			for(var i = 0; i < _55.length; i++) 
			{
				if((_55[i].type == CSSRule.STYLE_RULE) && (_55[i].selectorText == ".stable-head")) 
				{
					_55[i].style.overflow = "-moz-scrollbars-none";
				}
			}
			} catch(e) {}
		}
	}
	this.assignEvents();
	this.setAlignment();
};

dxSTable.prototype.assignEvents = function() 
{
	var _57 = this;
	this.scrollTimeout = null;
	this.scrollTop = 0;
	this.scrollDiff = 0;
	this.scOdd = null;
	this.isScrolling = false;
	
	this.dBody.onscroll = 
		function() 
		{
			_57.dHead.scrollLeft = _57.dBody.scrollLeft;
			if((_57.scrollTop != _57.dBody.scrollTop) && (_57.viewRows > _57.maxRows)) 
			{
				this.isScrolling = true;
				_57.scOdd = null;
				_57.scrollDiff = _57.scrollTop - _57.dBody.scrollTop;
				_57.scrollTop = _57.dBody.scrollTop;
				if(Math.abs(_57.scrollDiff) == 19) 
				{
					handleScroll.apply(_57);
					return;
				}
				_57.tBody.style.visibility = "hidden";
				if(_57.scrollTimeout != null) 
				{
					window.clearTimeout(_57.scrollTimeout);
				}
				_57.scrollTimeout = window.setTimeout(
					function() { _57.isScrolling = false; handleScroll.apply(_57); }
				        , 500);
				_57.scrollPos();
			}
		};
	if(browser.isKonqueror)
		this.dBody.addEventListener("scroll", this.dBody.onscroll, false);
	this.tHead.onmousedown = function(e) 
		{
			if(_57.isResizing)
			      _57.colDragEnd(e);
			else
			if((_57.hotCell >- 1) &&!(_57.isMoving)) 
			{
				_57.cancelSort = true;
				_57.cancelMove = true;
				_57.mmDrag = addEvent(document, "mousemove", 
					function(e) { return(_57.colDrag(e)); });
				_57.muDrag = addEvent(document, "mouseup", 
					function(e) { return(_57.colDragEnd(e)); });
				CancelEvent(e);
				return(false);
         		}
      		};
	this.tHead.onmouseout = function(e) { this.isOutside = true;  };
	this.tHead.onmouseover = function(e) { this.isOutside = false; };
	this.tHead.onmouseup = function(e) 
		{
			if((_57.hotCell >- 1) &&!(_57.isMoving)) 
			{
				_57.cancelSort = false;
				_57.cancelMove = false;
			}
		};
	Key.addListener(this.dCont, { onKeyDown : function(e) { _57.keyEvents(e); } });
	if(browser.isGecko && !browser.isKonqueror) 
	{
		addEvent(this.tBody, "click", function() { _57.dBody.focus();return(false); });
	}
};

dxSTable.prototype.colDrag = function(e) 
{
	this.isResizing = true;
	e = FixEvent(e);
	if(this.hotCell ==- 1) 
	{
		return(true);
	}
	while(!this.colsdata[this.hotCell].enabled && this.hotCell>0)
		this.hotCell--;
	var o = this.tHeadCols[this.hotCell];
	
	var i = parseInt(o.getAttribute("index"));
	var tb = this.tBody;
	var ex = e.clientX;
	var w = parseInt(o.style.width);
	var nw = w + ex;
	if(typeof o.lastMouseX == "undefined") 
		o.lastMouseX = ex;
	nw-=o.lastMouseX;
	if(nw < 10) 
	{
		return(true);
	}
	o.style.width = nw + "px";
/*
	if(!browser.isOpera && 
		!browser.isAppleWebKit && 
		!browser.isFirefox) 
	{
		tb.style.width = "auto";
      	}
*/
	o.lastMouseX = ex;
	document.body.style.cursor = "e-resize";
	try { document.selection.empty(); } catch(ex) {}
	return(false);
};

dxSTable.prototype.colDragEnd = function(e) 
{
	removeEvent(document, "mousemove", this.mmDrag);
	removeEvent(document, "mouseup", this.muDrag);
	this.mmDrag = 0;
	this.muDrag = 0;
	this.isResizing = false;
	resizeColumn.apply(this);
	if(this.tHead.isOutside) 
	{
		this.cancelSort = false;
		this.cancelMove = false;
	}
	document.body.style.cursor = "default";
	CancelEvent(e);
	return(false);
};

dxSTable.prototype.scrollPos = function() {
   this.scp.style.display = "block";
   var _67 = this.dBody.scrollTop / (this.dBody.scrollHeight - this.dBody.clientHeight);
   if(isNaN(_67) || (_67 < 0)) {
      _67 = 0;
      }
   var _68 = Math.floor(this.dBody.clientHeight / 19);
   if(_68 > this.maxRows) {
      _68 = this.maxRows;
      }
   var _69 = Math.floor(Math.floor(this.dBody.scrollTop - ((this.viewRows - this.maxRows) * 19) * _67) / 19);
   var mni = Math.ceil(this.viewRows * _67) + _69;
   var mxi = mni + _68;
   if(mxi > this.viewRows) {
      mxi = this.viewRows;
      }
   var mid = Math.floor(((mni + mxi) / 2));
   if(mid > this.viewRows) {
      mid = this.viewRows - 1;
      }
   var vr =- 1;
   var str = "";
   for(var i = 0; i < this.rows; i++) {
      var id = this.rowIDs[i];
      var r = this.rowdata[id];
      if(typeof r == "undefined") {
         continue;
         }
      if(!r.enabled) {
         continue;
         }
      vr++;
      if(vr == mid) {
         str = r.data[0];
         }
      }
   this.scp.innerHTML = escapeHTML("Current Row: " + str);
   };

function handleScroll() 
{
	window.clearTimeout(this.scrollTimeout);
	this.scrollTimeout = null;
	this.refreshRows();
	this.tBody.style.visibility = "visible";
	this.scp.style.display = "none";
}

dxSTable.prototype.refreshRows = function() 
{
	if(this.isScrolling) 
	{
		return;
   	}
	this.cancelSort = true;
	var _72 = this.dBody.scrollTop / (this.dBody.scrollHeight - this.dBody.clientHeight);
	if(isNaN(_72) || (_72 < 0)) 
	{
		_72 = 0;
   	}
	var _73 = Math.floor(this.dBody.clientHeight / 19) + 4;
	var h = (this.viewRows - this.maxRows) * 19;
	if(h < 0) 
	{
		h = 0;
		_72 = 0;
	}
	var ht = Math.floor(h * _72);
	var hb = h - ht;
	if(!browser.isIE)
		this.tpad.style.height = ht + "px";
	this.bpad.style.height = hb + "px";
	var mni = Math.ceil(this.viewRows * _72);
	if(mni + _73 > this.viewRows) 
	{
		mni = this.viewRows - this.maxRows;
	}
	if(mni < 0) 
	{
		mni = 0;
   	}
	var mxi = mni + this.maxRows;
	var tb = this.tBody.tb, vr =- 1, i = 0, c = 0, obj = null;
	for(i = 0; i < this.rows; i++) 
	{
		var id = this.rowIDs[i];
		var r = this.rowdata[id];
		if(typeof r == "undefined") 
		{
			continue;
      		}
		obj = $$(id);
		if(!r.enabled) 
		{
			if(obj != null) 
			{
				tb.removeChild(obj);
			}
			continue;
		}
		vr++;
		if((vr >= mni) && (vr <= mxi)) 
		{
			if(typeof tb.rows[c] == "undefined") 
			{
				if(obj != null) 
				{
					tb.removeChild(obj);
            			}
				else 
				{
					obj = this.createRow(r.data, id, r.icon, r.attr);
				}
				tb.appendChild(obj);
         		}
			else 
			{
				if(tb.rows[c].id != id) 
				{
					if(obj != null) 
					{
						tb.removeChild(obj);
               				}
					else 
					{
						obj = this.createRow(r.data, id, r.icon, r.attr);
               				}
					tb.insertBefore(obj, tb.rows[c]);
            			}
         		}
			c++;
      		}
		else 
		{
			if(obj != null) 
			{
				tb.removeChild(obj);
			}
		}
   	}
	this.refreshSelection();
	this.cancelSort = false;
	this.calcSize();
	this.resizeHack();
};

dxSTable.prototype.keyEvents = function(e) 
{
	e = FixEvent(e);
	var ele = (typeof e.target != "undefined") ? e.target : e.srcElement; 
	if(ele && ele.tagName)
	{
		var tg = ele.tagName.toLowerCase();
		if((tg=="input") || (tg=="textarea"))
			return;
	}
	var c = e.keyCode || e.which;
	if(c == Key.DELETE) 
	{
		if(typeof this.ondelete == "function") 
		{
			this.ondelete();
		}
	}
	else 
	{
		if((c == 65) && 
			e.ctrlKey)
		{
			this.fillSelection();
			if(typeof this.onselect == "function") 
			{
				this.onselect(e);
			}
			CancelKeyEvent(e);
		}
		else 
		{
			if((c == 90) && 
				e.ctrlKey)
			{
				this.clearSelection();
				if(typeof this.onselect == "function") 
				{
					this.onselect(e);
				}
				CancelKeyEvent(e);
            		}
         	}
      	}
};

dxSTable.prototype.selectRow = function(e, row) 
{
	var id = row.id;
	if(!((e.button == 2) && (this.rowSel[id] == true))) 
	{
		if(e.shiftKey) 
		{
			if(this.stSel == null) 
			{
				this.stSel = id;
				this.rowSel[id] = true;
				this.selCount = 1;
			}
			else 
			{
				this.selCount = 0;
				var _81 = false, passedCID = false, k = "";
				for(var i = 0, l = this.rowIDs.length; i < l; i++) 
				{
					k = this.rowIDs[i];
					this.rowSel[k] = false;
					if((k == this.stSel) || _81) 
					{
						if(!passedCID) 
						{
							this.rowSel[k] = true;
							this.selCount++;
                     				}
						else 
						{
							if((k == this.stSel) || (k == id)) 
							{
				                        	this.rowSel[k] = true;
								this.selCount++;
							}
						}
					}
					else 
					{
						if((k == id) || passedCID) 
						{
							if(!_81) 
							{
								this.rowSel[k] = true;
								this.selCount++;
							}
							else 
							{
								if((k == this.stSel) || (k == id)) 
								{
									this.rowSel[k] = true;
									this.selCount++;
                           					}
                        				}
                     				}
                  			}
					if(!this.rowdata[k].enabled && this.rowSel[k]) 
					{
						this.rowSel[k] = false;
						this.selCount--;
                  			}
					if(k == this.stSel) 
					{
						_81 = true;
					}
					if(k == id) 
					{
						passedCID = true;
					}
				}
			}
		}
		else 
		{
			if(e.ctrlKey) 
			{
				this.stSel = id;
				this.rowSel[id] =!this.rowSel[id];
				if(this.rowSel[id]) 
				{
					this.selCount++;
				}
				else 
				{
					this.selCount--;
				}
			}
			else 
			{
				this.stSel = id;
				this.selCount = 0;
				for(var k in this.rowSel) 
				{
					if(k == id) 
					{
						this.rowSel[k] = true;
						this.selCount++;
					}
					else 
					{
						this.rowSel[k] = false;
					}
				}
			}
		}
		if(this.selCount == 0) 
		{
			this.stSel = null;
		}
		this.refreshSelection();
	}
	if(typeof this.onselect == "function") 
	{
		this.onselect(e, id);
	}
	CancelEvent(e);
	return(false);
};

dxSTable.prototype.addRow = function(cols, sId, icon, attr) 
{
	if(cols.length != this.cols) 
	{
		return;
	}
	if(this.sortTimeout != null) 
	{
		window.clearTimeout(this.sortTimeout);
		this.sortTimeout = null;
	}
	this.rowdata[sId] = {"data" : cols, "icon" : icon, "attr" : attr, "enabled" : true};
	this.rowSel[sId] = false;
	this.rowIDs.push(sId);
	if(this.viewRows < this.maxRows) 
	{
		this.tBody.tb.appendChild(this.createRow(cols, sId, icon, attr));
	}
	this.rows++;
	this.viewRows++;
	if(this.viewRows > this.maxRows) 
	{
		this.bpad.style.height = ((this.viewRows - this.maxRows) * 19) + "px";
	}
	var self = this;
	if(this.sIndex !=- 1) 
	{
		this.sortTimeout = window.setTimeout(function() { self.Sort(); }, 200);
	}
};

dxSTable.prototype.createRow = function(_89, sId, _8b, _8c) 
{
	var tr, td, div, data, i, l, j;
	if(typeof (_8c) == "undefined") 
	{
		_8c = [];
	}
	tb = this.tBody.tb;
	tr = ELE_TR.cloneNode(true);
	if(sId != null) 
	{
		tr.id = sId;
	}
	var _8e = this;
	if(this.colorEvenRows) 
	{
		tr.className = (this.rows & 1) ? "odd" : "even";
	}

if(browser.isOpera && !("oncontextmenu"in document.createElement("foo")))
{
	var C = null;
        addEvent(tr,"mousedown",
		function(E)
		{
			if(E.button==2)
			{
				if(E.target)
				{
					var F = E.target.ownerDocument;
					if(C)
						C.parentNode.removeChild(C);
					C = F.createElement("input");
					C.type = "button"; 
					C.style.cssText = "z-index: 10000;position:absolute;top:" + (E.clientY - 2) + "px;left:" + (E.clientX - 2) + "px;width:5px;height:5px;opacity:0.01";
					(F.body || F.documentElement).appendChild(C);
				}
			}
			_8e.selectRow(E, this);
			return(false);
		});
	addEvent(tr,"mouseup", 
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
	addEvent(tr, "mousedown", function(e) { return(_8e.selectRow(e, this)); });

	if(typeof this.ondblclick == "function") 
	{
		if(browser.isKonqueror)
			tr.addEventListener("dblclick", function(e) {_8e.ondblclick(this);}, false);
		else
			addEvent(tr, "dblclick", function(e) { return(_8e.ondblclick(this)); });
	}
	tr.setAttribute("index", this.rows);
	for(var k in _8c) 
	{
		tr.setAttribute(k, _8c[k]);
	}
	data = this.format(_89.slice(0));
	for(i = 0, j = 0; i < this.cols; i++) 
	{
		var ind = this.colOrder[i];
		td = ELE_TD.cloneNode(true);
		td.className = "stable-" + this.dCont.id + "-col-" + ind;
		td.setAttribute("rawvalue", _89[ind]);
		div = ELE_DIV.cloneNode(true);
		div.innerHTML = (String(data[ind]) == "") ? "&nbsp;" : escapeHTML(data[ind]);
		if((ind == 0) && (_8b != null)) 
		{ 
			if(!browser.isOldIE && !browser.isFirefox3x)
			{
				var div1 = ELE_SPAN.cloneNode(true);
				div1.className = "stable-icon " + _8b;
				td.appendChild(div1);
			}
			else 
			{
				div.className = "ie" + _8b;
			}
		}
		else
			if(browser.isKonqueror)
			{
				var div1 = ELE_SPAN.cloneNode(true);
				div1.className = "stable-lpad";
				td.appendChild(div1);
			}
		td.appendChild(div);
		tr.appendChild(td);
		if(!this.colsdata[i].enabled && !browser.isIE8up)
			td.style.display = "none";
	}
	tr.title = _89[0];
	return tr;
};

dxSTable.prototype.removeRow = function(sId) 
{
	if(typeof this.rowdata[sId] == "undefined") 
	{
		return;
	}
	if(this.rowdata[sId].enabled) 
	{
		this.viewRows--;
	}
	try 
	{
		var obj = this.tBody.tb.removeChild($$(sId));
		removeElementEvents(obj);
		delete obj;
	} catch(ex) {}
	delete this.rowSel[sId];
	delete this.rowdata[sId];
	for(var i in this.rowIDs) 
	{
		if(this.rowIDs[i] == sId) 
		{
			delete this.rowIDs[i];
			this.rowIDs.splice(i,1);
			break;
		}
	}
	this.rows--;
	this.refreshSelection();
};

dxSTable.prototype.clearRows = function() 
{
	var tb = this.tBody.tb;
	while(tb.firstChild) 
	{ 
		var obj = tb.removeChild(tb.firstChild); 
		removeElementEvents(obj);
		delete obj; 
	}
	this.rows = 0;
	this.viewRows = 0;

	this.rowSel = new Array(0);
	this.rowdata = new Array(0);
	this.rowIDs = new Array(0);
	this.bpad.style.height = "0px";
	if(!browser.isOldIE)
		this.tpad.style.height = "0px";
	this.dBody.scrollTop = 0;
};

dxSTable.prototype.setAlignment = function() {
   var i, aRows, aAlign, j;
   aAlign = new Array();
   for(i = 0; i < this.cols; i++) {
      switch(this.colsdata[i].align) {
         case ALIGN_LEFT : align = "left";
         break;
         case ALIGN_CENTER : align = "center";
         break;
         case ALIGN_RIGHT : align = "right";
         break;
         case ALIGN_AUTO : default : switch(this.colsdata[i].type) {
            case TYPE_NUMBER : align = "right";
            break;
            default : align = "left";
            }
         }
      aAlign.push(align);
      this.tHeadCols[i].style.textAlign = align;
      }
   var _9c = this.tBody.getElementsByTagName("colgroup")[0].getElementsByTagName("col");
   var _9d = _9c.length;
   if(document.all || browser.isAppleWebKit || browser.isKonqueror) {
      for(var i = 0; i < _9d; i++) {
         _9c[i].align = aAlign[i];
         }
      }
   else {
      var ss = null, rules = null;
      for(var n = 0, l = document.styleSheets.length; n < l; n++) {
         if(!document.styleSheets[n].href || (document.styleSheets[n].href.indexOf("stable.css") ==- 1)) {
            continue;
            }
	try {
         ss = document.styleSheets[n];
         rules = ss.cssRules;
	} catch(e) { return; }
         }
      if(rules == null) {
         return;
         }
      if(typeof this.colRules == "undefined") {
         this.colRules = new Array();
         }
      for(var j = 0; j < _9d; j++) {
         var k = this.colOrder[j];
         if(!this.colRules[k]) {
            for(var i = 0, l = rules.length; i < l; i++) {
               if((rules[i].type == CSSRule.STYLE_RULE) && (rules[i].selectorText == ".stable-" + this.dCont.id + "-col-" + k)) {
                  this.colRules[k] = rules[i];
                  break;
                  }
               }
            }
         if(typeof this.colRules[k] != "undefined") {
            this.colRules[k].style.textAlign = aAlign[j];
            }
         else {
             this.colRules[k] = ss.cssRules[ss.insertRule(".stable-" + this.dCont.id + "-col-" + k + " div { text-align: " + aAlign[j] + "; }", 0)];
            }
         }
      }
   };
dxSTable.prototype.hideRow = function(sId) {
   if(this.rowdata[sId].enabled) {
      this.viewRows--;
      }
   this.rowdata[sId].enabled = false;
   };
dxSTable.prototype.unhideRow = function(sId) {
   if(!this.rowdata[sId].enabled) {
      this.viewRows++;
      }
   this.rowdata[sId].enabled = true;
   };

dxSTable.prototype.refreshSelection = function(_a6) 
{
	var _a7 = this.tBody.tb.rows, l = _a7.length, j = 0;
	if(_a6) 
	{
		j = 1;
	}
	for(var i = 0; i < l; i++) 
	{
		if(this.rowSel[_a7[i].id] == true) 
		{
			_a7[i].className = "selected";
		}
		else 
		{
			if(!this.colorEvenRows) 
			{
				_a7[i].className = "even";
			}
			else 
			{
				_a7[i].className = (j & 1) ? "odd" : "even";
			}
		}
		j++;
      	}
};

dxSTable.prototype.clearSelection = function() {
   for(var k in this.rowSel) {
      this.rowSel[k] = false;
      }
   this.selCount = 0;
   this.refreshSelection();
   };
dxSTable.prototype.fillSelection = function() 
	{
		this.selCount = 0;
		for(var k in this.rowSel) 
   		{
			if(this.rowdata[k].enabled)
			{
				this.rowSel[k] = true;
				this.selCount++;
			}
		}
		this.refreshSelection();
	};
dxSTable.prototype.getCache = function(col) {
   if(!this.tBody) {
      return [];
      }
   var a = new Array(0);
   for(var k in this.rowdata) {
      a.push( {
         "key" : k, "v" : this.getValue(this.rowdata[k], col), "e" : this.rowdata[k]}
      );
      }
   this.rowdata = [];
   return a;
   };
dxSTable.prototype.clearCache = function(a) {
   var l = a.length;
   for(var i = 0; i < l; i++) {
      a[i].v = null;
      a[i].e = null;
      a[i] = null;
      }
   };
dxSTable.prototype.getColOrder = function(col) {
   for(var i = 0; i < this.cols; i++) {
      if(this.colOrder[i] == col) {
         return i;
         }
      }
   return - 1;
   };

dxSTable.prototype.updateRowFrom = function(tbl,tblRow,row)
{
	var updated = this.setIcon(row,tbl.getIcon(tblRow));
	for(var i = 0; i < this.cols; i++)
	{
		if(this.setValue(row,i,tbl.getRawValue(tblRow,i)))
			updated = true;
	}
	return(updated);
};

dxSTable.prototype.getValue = function(row, col)
{
	return(row.data[this.colOrder[col]]);
}

dxSTable.prototype.getRawValue = function(row, col)
{
	return(this.rowdata[row].data[col]);
};

dxSTable.prototype.getValues = function(row)
{
	var ret = new Array();
	for(var i = 0; i < this.cols; i++)
		ret.push(this.getRawValue(row,i));
	return(ret);
};

dxSTable.prototype.setValues = function(row,arr)
{
	var ret = false;
	for(var i = 0; i < this.cols; i++)
		ret = this.setValue(row,i,arr[i]) || ret;
	return(ret);
};

dxSTable.prototype.setValue = function(row, col, val)
{
	if(this.getRawValue(row,col)!=val)
	{
		this.rowdata[row].data[col] = val;
		var r = $$(row);
		if(r == null)
			return(false);
		val = this.format(val, col);
		var c = this.getColOrder(col);
		var td = r.cells[c];
		td.lastChild.innerHTML = escapeHTML(val);
		return(true);
	}
	return(false);
};

dxSTable.prototype.getIcon = function(row)
{
	return(this.rowdata[row].icon);
}

dxSTable.prototype.setIcon = function(row, _bc) 
{
	if(this.rowdata[row].icon != _bc)
	{
		this.rowdata[row].icon = _bc;
		var r = $$(row);
		if(r == null) 
			return(false);
		var td = r.cells[this.getColOrder(0)];
		td.firstChild.className = (browser.isOldIE || browser.isFirefox3x) ? "ie"+_bc :	"stable-icon " + _bc;
		return(true);
	}
	return(false);
};

dxSTable.prototype.setAttr = function(row, attrName, attrValue)
{
	if(!this.rowdata[row].attr)
		this.rowdata[row].attr = new Array();
	this.rowdata[row].attr[attrName] = attrValue;
	var r = $$(row);
	if(r)
		r.setAttribute(attrName, attrValue);
};

dxSTable.prototype.getAttr = function(row, attrName)
{
	return(this.rowdata[row].attr ? this.rowdata[row].attr[attrName] : null);
};

dxSTable.prototype.resize = function(w, h) 
{
	if(this.dCont)
	{
		if(w) 
			this.dCont.style.width = w + "px";
		if(h) 
			this.dCont.style.height = h + "px";
		this.calcSize();
		this.resizeHack();
	}
};

dxSTable.prototype.isColumnEnabled = function(i) 
{
	return(this.colsdata[this.getColOrder(i)].enabled ? 1 : 0);
};

dxSTable.prototype.getColWidth = function(i) 
{
	var col = this.tHeadCols[this.getColOrder(i)];
        var ret = col.offsetWidth;
        if(!ret)
        {
	       	ret = iv(col.style.width);
		if(browser.isIE || browser.isFirefox || browser.isOpera)
			ret = ret+4;
	}
	return(ret);
};