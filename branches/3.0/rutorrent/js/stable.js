/*
 *
 *	Copyright Emil A Eklund - Column List Widget 1.03
 *			 (http://webfx.eae.net/dhtml/collist/columnlist.html)
 *	Copyright Erik Arvidsson - Sortable Table 1.12
 *			 (http://webfx.eae.net/dhtml/sortabletable/sortabletable.html)
 *	Copyright 2007, 2008 Carsten Niebuhr
 *			 (http://trac.utorrent.com/trac)
 *	Copyright 2009, 2010 Novik
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 *	$Id$
*/

var TYPE_STRING = 0;
var TYPE_NUMBER = 1;
var TYPE_DATE = 2;
var TYPE_STRING_NO_CASE = 3;
var TYPE_PROGRESS = 4;
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
	this.paletteURL = ".";
	this.sortAscImage = this.paletteURL+"/images/asc.gif";
	this.sortDescImage = this.paletteURL+"/images/desc.gif";
	this.cancelSort = false;
	this.cancelMove = false;
	this.colMove = new dxSTable.ColumnMove(this);
	this.colOrder = new Array();
	this.ids = new Array();
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
	this.selCount = 0;
	this.created = false;
	this.colReszObj = null;
	this.rowCover = null;
};

dxSTable.prototype.setPaletteByURL = function(url) 
{
	this.paletteURL = url;	
	this.sortAscImage = url+"/images/asc.gif";
	this.sortDescImage = url+"/images/desc.gif";
	this.Sort();
}

dxSTable.prototype.create = function(ele, styles, aName)
{
	var tr, td, cl, cg, div;
	this.prefix = aName;
	this.dCont = ele;

	this.dHead = $("<div>").addClass("stable-head").get(0);
	this.dBody = $("<div>").addClass("stable-body").get(0);
	$(this.dCont).addClass("stable");
	this.tHead = $("<table>").width(100).get(0);
	this.tHead.cellSpacing = 0;
	this.tHead.cellPadding = 0;
	this.tHead.tb = $("<tbody>").get(0);
	
	this.dCont.appendChild(this.dHead);
	this.dCont.appendChild(this.dBody);
	this.dHead.appendChild(this.tHead);
	this.tHead.appendChild(this.tHead.tb);

	tr = $("<tr>");
	this.tHead.tb.appendChild(tr.get(0));
	var self = this, span;
	var j = 0;
	if(this.sIndex>=styles.length)
		this.sIndex = -1;

	for(var i in this.colOrder)
	{
		if(this.colOrder[i]>=styles.length)
		{
			this.colOrder = new Array();
			break;
		}
	}

	for(var i = 0, l = styles.length; i < l; i++) 
	{
		if(typeof this.colOrder[i] == "undefined")
			this.colOrder[i] = i;
		if(typeof styles[this.colOrder[i]].enabled == "undefined") 
		{
			styles[this.colOrder[i]].enabled = true;
		}
		this.cols++;
		this.colsdata[i] = styles[this.colOrder[i]];
		this.colsdata[i].width = iv(this.colsdata[i].width);
		this.ids[i] = styles[i].id;

		td = $("<td>").mousemove( function(e) 
		{
			if(self.isResizing) 
				return;
			var x = e.clientX - $(this).offset().left;	
			this.lastMouseX = e.clientX;
			var w = this.offsetWidth;
			var i = parseInt(this.getAttribute("index"));
			if(x <= 8) 
			{
				if(i != 0) 
				{
					self.hotCell = i - 1;
					this.style.cursor = "e-resize";
				}
				else 
				{
					self.hotCell =- 1;
					this.style.cursor = "default";
				}
			}
			else 
			{
				if(x >= w - 1) 
				{
					self.hotCell = i;
					this.style.cursor = "e-resize";
				}
				else 
				{
					self.hotCell =- 1;
					this.style.cursor = "default";
				}
			}
       		});
		tr.append( td.append( $("<div>").html(styles[this.colOrder[i]].text)).
			width(styles[this.colOrder[i]].width).
			attr("index", i));
		this.colMove.init(td.get(0), preventSort, null, moveColumn);
		if(!browser.isIE8up)
			td.mouseclick( function(e) { return(self.onRightClick(e)); } );
		td.mousedown( function(e) { $(document).bind( "keydown", self, self.keyEvents ) } );
		td.mouseup( function(e) { self.Sort(e, this); } );		
		this.tHeadCols[i] = td.get(0);
		if(!this.colsdata[i].enabled && !browser.isIE8up) 
			td.hide();
		j++;
	}
	this.tBody = $("<table>").width(0).get(0);
	this.tBody.cellSpacing = 0;
	this.tBody.cellPadding = 0;
	this.tpad = $("<div>").addClass("stable-virtpad").get(0);
	this.dBody.appendChild(this.tpad);
	this.dBody.appendChild(this.tBody);
	this.bpad = $("<div>").addClass("stable-virtpad").get(0);
	this.dBody.appendChild(this.bpad);
	this.tBody.tb = $("<tbody>").get(0);
	this.tBody.appendChild(this.tBody.tb);

	cg = $("<colgroup>");
	this.tBody.appendChild(cg.get(0));
	for(var i = 0; i < styles.length; i++) 
	{
		cl = $("<col>").width(this.colsdata[i].width);
		cg.append(cl);
		this.tBodyCols[i] = cl.get(0);
      		if(!this.colsdata[i].enabled && !browser.isIE8up)
			cl.hide();
	}
	this.scp = $("<span></span>").addClass("stable-scrollpos").get(0);
	this.dCont.appendChild(this.scp);
	this.dCont.style.position = "relative";
	this.init();
	$(window).unload(function() { self.clearRows(); });
	this.calcSize();
	this.resizeColumn();

	this.colReszObj = $("<div>").addClass("stable-resize-header").get(0);
	this.dBody.appendChild(this.colReszObj);

	this.rowCover = $("<div>").addClass("rowcover").get(0);
	this.dHead.appendChild(this.rowCover);
	this.created = true;
};

dxSTable.prototype.toggleColumn = function(i)
{
	$(this.tBodyCols[i]).toggle();
	$(this.tHeadCols[i]).toggle();;
	this.colsdata[i].enabled = !this.colsdata[i].enabled;
        for (var D = 0, B = this.tBody.tb.childNodes.length; D < B; D ++ )
        {
		$(this.tBody.tb.childNodes[D].childNodes[i]).toggle();
	}
        this.dHead.scrollLeft = this.dBody.scrollLeft;
        this.calcSize();
        this.resizeColumn();
        if(typeof this.oncoltoggled == "function")
	{
		this.oncoltoggled();
	}
}

dxSTable.prototype.onRightClick = function(e)
{
        if(e.button==2)
        {
		theContextMenu.clear();
		for(var i = 0; i<this.colsdata.length; i++)
		{
			if(this.colOrder[i])
			{
				var a = [this.colsdata[i].text, "theWebUI.getTable('"+this.prefix+"').toggleColumn("+i+")"];
				if(this.colsdata[i].enabled)
					a.unshift(CMENU_SEL);
				theContextMenu.add(a);
			}
		}
		theContextMenu.show(e.clientX,e.clientY);
		return(false);
	}
}

dxSTable.prototype.resizeHack = function()
{
	if(!browser.isIE7x)
		this.resizeColumn(this);
}

var preventSort = function() 
{
	this.cancelSort = true;
};

dxSTable.prototype.calcSize = function() 
{
	if(this.created && this.dCont.offsetWidth >= 4) 
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
		this.rowCover.style.width = this.dHead.style.width;
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
	return(this);
};

dxSTable.prototype.resizeColumn = function() 
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

dxSTable.ColumnMove = function(p)
{
	this.parent = p;
	this.obj = $("<div>").addClass("stable-move-header").get(0);
	this.sepobj = $("<div>").addClass("stable-separator-header").get(0);
};

dxSTable.ColumnMove.prototype =
{
	parent : null, 
	obj : null, 
	sepobj : null, 
	added : false, 
	rx : -1, 
	index :- 1, 
	indexnew :- 1, 
	mid : 0, 
	uid : 0, 

	init : function(o, _1b, _1c, _1d) 
	{      
		var self = this;      
		$(o).mousedown( function(e)
		{
			if(self.parent.hotCell >- 1)
				return;
			return(self.start(e, this));
		});
		this.onDrag = _1c || new Function();
		this.onDragEnd = _1d || new Function();
	},
	start : function(e, p)
	{	
		if(this.parent.cancelMove)
			return;
		if(e && e.button==2)
			return(true);
		this.parent.isMoving = true;
		var o = this.obj;
		this.index = parseInt(p.getAttribute("index"));
		while(o.firstChild) 
			o.removeChild(o.firstChild);
		o.appendChild(document.createTextNode(p.lastChild.innerHTML));
		o.style.width = (p.offsetWidth - 16) + "px";
		o.style.left = p.offsetLeft + "px";
		o.style.textAlign = (this.parent.colsdata[this.index].type == TYPE_NUMBER) ? "right" : "left";
		this.sepobj.style.left = p.offsetLeft + "px";
		o.lastMouseX = e.clientX;
		o.style.visibility = "visible";
		var self = this;
		$(document).bind("mousemove",self,self.drag);
		$(document).bind("mouseup",self,self.end);
		this.rx = $(this.parent.dHead).offset().left;
		this.obj.style.cursor = "move";
		return(false);
	},
	drag : function(e) 
	{
		var self = e.data;
		self.parent.cancelSort = true;
		var o = self.obj, l = parseInt(o.style.left), ex = e.clientX, i = 0, c = self.parent.cols;
		if(!self.added) 
		{
			self.parent.dHead.appendChild(self.obj);
			self.parent.dHead.appendChild(self.sepobj);
			self.added = true;
		}
		l += ex;
		if(typeof o.lastMouseX == "undefined")
			o.lastMouseX = ex;
		l -= o.lastMouseX;
		o.style.left = l + "px";
		var ox = 0;
		var orx = ex + self.parent.dBody.scrollLeft - self.rx;
		for(i = 0; i < c; i++) 
		{
			ox += self.parent.tHeadCols[i].offsetWidth;
			if(ox > orx) 
				break;
		}
		if(i >= c) 
		{
			self.sepobj.style.left = self.parent.tHeadCols[c - 1].offsetLeft + self.parent.tHeadCols[c - 1].offsetWidth - 1 + "px";
			i = c;
		}
		else 
			self.sepobj.style.left = self.parent.tHeadCols[i].offsetLeft + "px";
		self.indexnew = i;
		self.obj.lastMouseX = ex;
		self.onDrag.apply(self.parent, [i]);
		return(false);
   	},
	end : function(e)
	{
		var self = e.data;	
		try {
			self.obj.style.cursor = "default";
			self.parent.dHead.removeChild(self.obj);
			self.parent.dHead.removeChild(self.sepobj);
			self.added = false;
			self.onDragEnd.apply(self.parent, [self.index, self.indexnew]);
		} catch(ex) {}
		self.index =- 1;
		self.indexnew =- 1;
		self.parent.isMoving = false;
		self.parent.cancelSort = false;
		$(document).unbind("mousemove",self.drag);
		$(document).unbind("mouseup",self.end);
		return(false);
	}
};

dxSTable.prototype.renameColumnById = function(id, name)
{
	this.renameColumn(this.getColById(id), name);
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
			return(true);
		rev = false;
		col = this.tHead.tb.rows[0].cells[this.sIndex];
	}
	else 
	{
		if(e.button==2)
			return(true);
		col = (e.target) ? e.target : e.srcElement;
	}
	if(col.tagName == "DIV") 
	{
		col = col.parentNode;
	}
	var ind = parseInt(col.getAttribute("index"));
	if(e && e.shiftKey)
	{
		if(this.secIndex == ind) 
			this.secRev = 1 - this.secRev;
		else 
			this.secRev = 0;
		this.secIndex = ind;
		ind = this.sIndex;
		rev = false;
		col = this.tHead.tb.rows[0].cells[this.sIndex];
	}
	if(rev) 
	        this.reverse = (this.sIndex == ind) ? 1 - this.reverse : 0;
	if(this.sIndex >= 0) 
	{
		var td = this.tHead.tb.rows[0].cells[this.sIndex];
		td.style.backgroundImage = "url("+this.paletteURL+"/images/blank.gif)";
	}
	col.style.backgroundImage = "url(" + (this.reverse ? this.sortAscImage : this.sortDescImage) + ")";
	this.sIndex = ind;
	var d = this.getCache(ind);
	var u = d.slice(0);
	var self = this;
	switch(this.colsdata[ind].type) 
	{
		case TYPE_STRING : 
			d.sort(function(x, y) { return self.sortAlphaNumeric(x, y); });
      			break;
      		case TYPE_PROGRESS :
      		case TYPE_NUMBER : 
      			d.sort(function(x, y) { return self.sortNumeric(x, y); });
      			break;
      		default : 
      			d.sort();
      			break;
      	}
   	if(this.reverse) 
		d.reverse();
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
		this.refreshRows();
	this.calcSize().resizeHack();
	if($type(this.onsort) == "function") 
		this.onsort();
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
	var self = this;
	this.scrollTimeout = null;
	this.scrollTop = 0;
	this.scrollDiff = 0;
	this.scOdd = null;
	this.isScrolling = false;
	
	this.dBody.onscroll = 
		function() 
		{
			self.dHead.scrollLeft = self.dBody.scrollLeft;
			if((self.scrollTop != self.dBody.scrollTop) && (self.viewRows > self.maxRows)) 
			{
				this.isScrolling = true;
				self.scOdd = null;
				self.scrollDiff = self.scrollTop - self.dBody.scrollTop;
				self.scrollTop = self.dBody.scrollTop;
				if(Math.abs(self.scrollDiff) == 19) 
				{
					handleScroll.apply(self);
					return;
				}
				self.tBody.style.visibility = "hidden";
				if(self.scrollTimeout != null) 
				{
					window.clearTimeout(self.scrollTimeout);
				}
				self.scrollTimeout = window.setTimeout(
					function() { self.isScrolling = false; handleScroll.apply(self); }
				        , 500);
				self.scrollPos();
			}
		};
	if(browser.isKonqueror)
		this.dBody.addEventListener("scroll", this.dBody.onscroll, false);
	this.tHead.onmousedown = function(e) 
		{
			if(self.isResizing)
			      self.colDragEnd(e);
			else
			if((self.hotCell >- 1) &&!(self.isMoving)) 
			{
				self.cancelSort = true;
				self.cancelMove = true;
                                $(document).bind("mousemove",self,self.colDrag);
                                $(document).bind("mouseup",self,self.colDragEnd);
				self.rowCover.style.display = "block";
				return(false);
         		}
      		};
	this.tHead.onmouseout = function(e) { this.isOutside = true;  };
	this.tHead.onmouseover = function(e) { this.isOutside = false; };
	this.tHead.onmouseup = function(e) 
		{
			if((self.hotCell >- 1) &&!(self.isMoving)) 
			{
				self.cancelSort = false;
				self.cancelMove = false;
			}
		};
	$(this.dCont).mousedown( function(e) { $(document).bind( "keydown", self, self.keyEvents ) } );
};

function getLeftScrollPos(obj) {
   var x = 0;
   while(obj) {
      x += obj.scrollLeft;
      obj = obj.offsetParent;
      }
   return x;
   }

dxSTable.prototype.colDrag = function(e) 
{
	var self = e.data;
	self.isResizing = true;
	if(self.hotCell ==- 1) 
		return(true);
	while(!self.colsdata[self.hotCell].enabled && self.hotCell>0)
		self.hotCell--;
	var o = self.tHeadCols[self.hotCell];
	
	var i = parseInt(o.getAttribute("index"));
	var tb = self.tBody;
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
	self.colsdata[self.hotCell].width += (e.clientX-o.lastMouseX);
	o.style.width = nw + "px";
	o.lastMouseX = ex;
	document.body.style.cursor = "e-resize";

	self.colReszObj.style.visibility = "visible";
	if(!browser.isAppleWebKit && !browser.isKonqueror)
		nw+=4;
	self.colReszObj.style.left = (o.offsetLeft+nw-self.dHead.scrollLeft) + "px";

	nw = iv(self.dBody.style.height) + iv(self.dHead.offsetHeight);
	if(self.dBody.scrollWidth > self.dBody.clientWidth)
		nw-=window.scrollbarHeight;
	self.colReszObj.style.height = nw + "px";

	try { document.selection.empty(); } catch(ex) {}
	return(false);
};

dxSTable.prototype.colDragEnd = function(e) 
{
        var self = e.data;
	self.rowCover.style.display = "none";
	$(document).unbind("mousemove",self.colDrag);
	$(document).unbind("mouseup",self.colDragEnd);
	self.isResizing = false;
	self.colReszObj.style.left = 0;
	self.colReszObj.style.height = 0;
	self.colReszObj.style.visibility = "hidden";
	self.resizeColumn();
	if(self.tHead.isOutside) 
	{
		self.cancelSort = false;
		self.cancelMove = false;
	}
	document.body.style.cursor = "default";
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
	this.calcSize().resizeHack();
};

dxSTable.prototype.keyEvents = function(e) 
{
	var self = e.data;
	if(!e.fromTextCtrl && !theDialogManager.isModalState())
	{
		var c = e.which;
		if((browser.isKonqueror && c == 127) || (c == 46))
		{
			if(typeof self.ondelete == "function") 
				self.ondelete();
		}
		else 
		if(e.ctrlKey)
		{
			switch(c)
			{
				case 65:
				{
					self.fillSelection();
					if(typeof self.onselect == "function") 
						self.onselect(e);
					return(false);
				}
				case 90:
				{
					self.clearSelection();
					if(typeof self.onselect == "function") 
						self.onselect(e);
					return(false);
	            		}
        	 	}
	      	}
	}
};

dxSTable.prototype.selectRow = function(e, row) 
{
        $(document).bind( "keydown", this, this.keyEvents );
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
	return(false);
};

dxSTable.prototype.addRowById = function(ids, sId, icon, attr)
{
        var cols = [];
        for(var i=0; i<this.cols; i++)
		cols.push(null);
	for(var i in ids) 
	{
		var no = this.getColById(i);
		if(no>=0)
			cols[no] = ids[i];
	}
	this.addRow(cols, sId, icon, attr);
}

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

dxSTable.prototype.createRow = function(cols, sId, icon, attr) 
{
	var tr, td, div, data, i, l, j;
	if(typeof (attr) == "undefined") 
	{
		attr = [];
	}
	tb = this.tBody.tb;
	tr = $("<tr>").get(0);
	if(sId != null) 
	{
		tr.id = sId;
	}
	var self = this;
	if(this.colorEvenRows) 
	{
		tr.className = (this.rows & 1) ? "odd" : "even";
	}

	$(tr).mouseclick( function(e) { return(self.selectRow(e, this)); });

	if(typeof this.ondblclick == "function") 
	{
		if(browser.isKonqueror)
			tr.addEventListener("dblclick", function(e) {self.ondblclick(this);}, false);
		else
			$(tr).dblclick( function(e) { return(self.ondblclick(this)); });
	}
	tr.setAttribute("index", this.rows);
	for(var k in attr) 
		tr.setAttribute(k, attr[k]);
	data = this.format(this,cols.slice(0));
	for(i = 0, j = 0; i < this.cols; i++) 
	{
		var ind = this.colOrder[i];
		td = $("<td>").addClass("stable-" + this.dCont.id + "-col-" + ind).attr("rawvalue", $type(cols[ind]) ? cols[ind] : "").get(0);
		div = $("<div>").get(0);

		if(this.colsdata[i].type==TYPE_PROGRESS)
		{
		        $(div).addClass("meter-value").css({ float: "left" }).width(iv(data[ind])+"%").html("&nbsp;");
			$(td).append( $("<span></span>").addClass("meter-text").css({overflow: "visible"}).text(data[ind]) );
		}
		else
			div.innerHTML = (String(data[ind]) == "") ? "&nbsp;" : escapeHTML(data[ind]);
		if((ind == 0) && (icon != null)) 
		{ 
			if(!browser.isFirefox3x)
				td.appendChild( $("<span></span>").addClass("stable-icon " + icon).get(0) );
			else 
				div.className = "ie" + icon;
		}
		td.appendChild(div);
		tr.appendChild(td);
		if(!this.colsdata[i].enabled && !browser.isIE8up)
			td.style.display = "none";
	}
	tr.title = cols[0];
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
		$(obj).unbind();
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
		$(obj).unbind();
		delete obj; 
	}
	this.rows = 0;
	this.viewRows = 0;

	this.rowSel = new Array(0);
	this.rowdata = new Array(0);
	this.rowIDs = new Array(0);
	this.bpad.style.height = "0px";
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

dxSTable.prototype.getColById = function(id)
{
        for(var i = 0; i < this.ids.length; i++)
        	if(this.ids[i]==id)
			return(i);
	return(-1);
};

dxSTable.prototype.getIdByCol = function(col)
{
	return(this.ids[col]);
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

dxSTable.prototype.getValueById = function(row, id)
{
	return(this.getRawValue(row, this.getColById(id)));
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

dxSTable.prototype.setValuesById = function(row,ids,zeroFill)
{
	var ret = false;
	for(var i = 0; i < this.cols; i++)
	{
		var id = this.ids[i];
		if( $type(ids[id]) )
			ret = this.setValue(row,i,ids[id]) || ret;
		else
			if(zeroFill)
				ret = this.setValue(row,i,null) || ret;
	}
//	for(var i in ids) 
//		ret = this.setValueById(row,i,ids[i]) || ret;
	return(ret);
}

dxSTable.prototype.setValues = function(row,arr)
{
	var ret = false;
	for(var i = 0; i < this.cols; i++)
		ret = this.setValue(row,i,arr[i]) || ret;
	return(ret);
};

dxSTable.prototype.setValueById = function(row, id, val)
{
	return(this.setValue(row, this.getColById(id), val));
}

dxSTable.prototype.setValue = function(row, col, val)
{
	if((col>=0) && (this.getRawValue(row,col)!=val))
	{
		this.rowdata[row].data[col] = val;
		var r = $$(row);
		if(r == null)
			return(false);
		arr = {};
		arr[col] = val;
		val = this.format(this,arr)[col];
		var c = this.getColOrder(col);
		var td = r.cells[c];

		if(this.colsdata[c].type==TYPE_PROGRESS)
		{
			td.lastChild.style.width = iv(val)+"%";
			td.firstChild.innerHTML = escapeHTML(val);
		}
		else
			td.lastChild.innerHTML = escapeHTML(val);
		return(true);
	}
	return(false);
};

dxSTable.prototype.getIcon = function(row)
{
	return(this.rowdata[row].icon);
}

dxSTable.prototype.setIcon = function(row, icon) 
{
	if(this.rowdata[row].icon != icon)
	{
		this.rowdata[row].icon = icon;
		var r = $$(row);
		if(r == null) 
			return(false);
		var td = r.cells[this.getColOrder(0)];
		if(icon)
			td.firstChild.className = (browser.isFirefox3x) ? "ie"+icon : "stable-icon " + icon;
		else
			td.firstChild.className = "";
		return(true);
	}
	return(false);
};

dxSTable.prototype.setAttr = function(row, attr)
{
        if(($type(attr)=="object") || ($type(attr)=="array"))
        {
		if(!this.rowdata[row].attr)
			this.rowdata[row].attr = {};
		for(var name in attr) 
			this.rowdata[row].attr[name] = attr[name];
		var r = $$(row);
		if(r)
			for(var name in attr) 
				r.setAttribute(name, attr[name]);
	}
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
		this.calcSize().resizeHack();
	}
};

dxSTable.prototype.isColumnEnabled = function(i) 
{
	return(this.colsdata[this.getColOrder(i)].enabled ? 1 : 0);
};

dxSTable.prototype.getColWidth = function(i) 
{
	return(this.colsdata[this.getColOrder(i)].width);
};

dxSTable.prototype.getFirstSelected = function() 
{
	var ret = null;
	for( var k in this.rowSel )
	{
		if( this.rowSel[k] )
		{
			ret = k;
			break;
		}
	}
	return(ret);
}