/*
 *
 *	Copyright Emil A Eklund - Column List Widget 1.03
 *			 (http://webfx.eae.net/dhtml/collist/columnlist.html)
 *	Copyright Erik Arvidsson - Sortable Table 1.12
 *			 (http://webfx.eae.net/dhtml/sortabletable/sortabletable.html)
 *	Copyright 2007, 2008 Carsten Niebuhr
 *			 (http://trac.utorrent.com/trac)
 *	Copyright 2009, 2011 Novik
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
*/

var TYPE_STRING = 0;
var TYPE_NUMBER = 1;
var TYPE_DATE = 2;
var TYPE_STRING_NO_CASE = 3;
var TYPE_PROGRESS = 4;
var TYPE_PEERS = 5;
var TYPE_SEEDS = 6;
var ALIGN_AUTO = 0;
var ALIGN_LEFT = 1;
var ALIGN_CENTER = 2;
var ALIGN_RIGHT = 3;

var TR_HEIGHT	=	19;

var dxSTable = function() 
{
	this.rows = 0;
	this.rowdata = new Object();
	this.rowIDs = new Array();
	this.rowSel = new Array();
	this.maxRows = false;
	this.noDelayingDraw = true;
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
	this.prgStartColor = new RGBackground(".meter-value-start-color");
	this.prgEndColor = new RGBackground(".meter-value-end-color");
	this.mni = 0;
	this.mxi = 0;
	this.maxViewRows = 100;
}

dxSTable.prototype.setPaletteByURL = function(url) 
{
	this.paletteURL = url;	
	this.sortAscImage = url+"/images/asc.gif";
	this.sortDescImage = url+"/images/desc.gif";
	if(this.created)
		this.Sort();
}

dxSTable.prototype.bindKeys = function()
{
	$(document).off( (browser.isOpera && browser.versionMinor<9.8) ? "keypress" : "keydown", this.keyEvents );
	$(document).on( (browser.isOpera && browser.versionMinor<9.8) ? "keypress" : "keydown", this, this.keyEvents );
}

dxSTable.prototype.create = function(ele, styles, aName)
{
	if(!ele || this.created)
		return;
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
		if(!$type(this.colOrder[i]))
			this.colOrder[i] = i;
		if(!$type(styles[this.colOrder[i]].enabled)) 
			styles[this.colOrder[i]].enabled = true;
		this.cols++;
		this.colsdata[i] = styles[this.colOrder[i]];

		if(browser.isIE7x && (this.colsdata[i].type==TYPE_PROGRESS))
			this.colsdata[i].type = TYPE_NUMBER;

		this.colsdata[i].width = iv(this.colsdata[i].width);
		this.ids[i] = styles[i].id;

		td = $("<td>").on( "mousemove touchstart", function(e) 
		{
			if(self.isResizing) 
				return;
			var x = e.clientX - $(this).offset().left;	
			this.lastMouseX = e.clientX;
			var w = this.offsetWidth;
			var i = parseInt(this.getAttribute("index"));
			var delta = $.support.touchable ? 16 : 8;
			if(x <= delta) 
			{
				if(i!= 0) 
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
				if(x >= w - delta) 
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
		td.mouseclick(function(e)
		{ 
			self.onRightClick(e);
		}).on('mouseup', function(e) 
		{ 
			self.Sort(e);
		});
		if(!$.support.touchable)
			td.on('mousedown', function(e) { self.bindKeys(); });
		this.tHeadCols[i] = td.get(0);
		if(!this.colsdata[i].enabled)
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
      		if(!this.colsdata[i].enabled)
			cl.hide();
	}
	this.scp = $("<span></span>").addClass("stable-scrollpos").get(0);
	this.dCont.appendChild(this.scp);
	this.dCont.style.position = "relative";
	this.init();
	$(window).on('unload', function() { self.clearRows(); });
	this.calcSize().resizeColumn();

	this.colReszObj = $("<div>").addClass("stable-resize-header").get(0);
	this.dBody.appendChild(this.colReszObj);

	this.rowCover = $("<div>").addClass("rowcover").get(0);
	this.dHead.appendChild(this.rowCover);
	this.created = true;
}

dxSTable.prototype.toggleColumn = function(i)
{
	this.colsdata[i].enabled = !this.colsdata[i].enabled;
	$(this.tBodyCols[i]).css( "display", this.colsdata[i].enabled ? "" : "none" );
	$(this.tHeadCols[i]).css( "display", this.colsdata[i].enabled ? "" : "none" );
	if(!browser.isIE7x)
	        for (var D = 0, B = this.tBody.tb.childNodes.length; D < B; D ++ )
			$(this.tBody.tb.childNodes[D].childNodes[i]).css( "display", this.colsdata[i].enabled ? "" : "none" );
	if(this.colsdata[i].enabled)
	{
		$(this.tBodyCols[i]).width( this.colsdata[i].width );
		$(this.tHeadCols[i]).width( this.colsdata[i].width );
	}
        this.dHead.scrollLeft = this.dBody.scrollLeft;
        this.calcSize().resizeColumn();
	if($type(this.onresize) == "function")
		this.onresize();
}

dxSTable.prototype.removeColumnById = function(id, name)
{
	this.removeColumn(this.getColById(id), name);
}

dxSTable.prototype.removeColumn = function(no)
{
	i = this.getColOrder(no);
	if(i>=0)
	{
		$(this.tHeadCols[i]).remove();
		$(this.tBodyCols[i]).remove();

		for (var D = 0, B = this.tBody.tb.childNodes.length; D < B; D ++ )
			$(this.tBody.tb.childNodes[D].childNodes[i]).remove();

		this.ids.splice(no,1);
		this.colOrder.splice(i,1);
		for(var j = 0; j < this.cols; j++)
			if(this.colOrder[j] > no)
				this.colOrder[j]--;

		this.colsdata.splice(i,1);
		this.tBodyCols.splice(i,1);
		this.tHeadCols.splice(i,1);

		this.cols--;
		if(this.sIndex == i)
			this.sIndex = -1;
		if(this.secIndex == i)
			this.secIndex = 0;

	        this.dHead.scrollLeft = this.dBody.scrollLeft;
        	this.calcSize().resizeColumn();
	}
}

dxSTable.prototype.onRightClick = function(e)
{
        if((e.which==3) && !this.isMoving)
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
		theContextMenu.setNoHide();
		theContextMenu.show(e.clientX,e.clientY);
		return(false);
	}
}

dxSTable.prototype.resizeHack = function()
{
	if(!browser.isIE7x)
		this.resizeColumn();
	return(this);
}

var preventSort = function() 
{
	this.cancelSort = true;
}

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
				if((browser.isChrome && (browser.versionMajor<537)) || browser.isKonqueror || browser.isSafari)
					_9a+=4;
				if(_9a>8)
					this.tHeadCols[i].style.width = (_9a - 4) + "px";
			}
		}
	}
	return(this);
}

dxSTable.prototype.resizeColumn = function() 
{
	if (this.tBody == null)
		return;
	
	var _e = this.tBody.getElementsByTagName("colgroup")[0].getElementsByTagName("col");
	var needCallHandler = false;
	var w = 0, c;
	for(var i = 0, l = _e.length; i < l; i++) 
	{
                c = this.tHeadCols[i];
		w = this.colsdata[i].width;
		if(iv(_e[i].style.width)!=w)
		{
			_e[i].style.width = w + "px";
			needCallHandler = true;
		}
		if(
			(browser.isAppleWebKit || browser.isKonqueror || browser.isIE8up) &&
			this.tBody.rows.length>0)
		{
			if((this.tBody.rows[0].cells[i].width || browser.isSafari) && (this.tBody.rows[0].cells[i].width!=w) && (w>=4))
			{
				this.tBody.rows[0].cells[i].width=w;
				needCallHandler = true;
			}
//			for( var j=0; j<this.tBody.rows.length; j++ )
//				this.tBody.rows[j].cells[i].style.textAlign = c.style.textAlign;
		}

	}
	this.tBody.tb.style.width = this.tHead.offsetWidth + "px";
	this.tBody.style.width = this.tHead.offsetWidth + "px";

	if(($type(this.onresize) == "function") && needCallHandler)
	{
		this.onresize();
	}
}

var moveColumn = function(_11, _12) 
{
	var i, l, oParent, oCol, oBefore, aRows, a;
	if(_11 == _12)
		return;
	oCol = this.tHeadCols[_11];
	oParent = oCol.parentNode;
	if(_12 == this.cols) 
	{
		oParent.removeChild(oCol);
		oParent.appendChild(oCol);
	}
	else 
	{
		oBefore = this.tHeadCols[_12];
		oParent.removeChild(oCol);
		oParent.insertBefore(oCol, oBefore);
	}
	oCol = this.tBody.getElementsByTagName("colgroup")[0].getElementsByTagName("col")[_11];
	oParent = oCol.parentNode;
	if(_12 == this.cols) 
	{
		oParent.removeChild(oCol);
		oParent.appendChild(oCol);
	}
	else 
	{
		oBefore = this.tBody.getElementsByTagName("colgroup")[0].getElementsByTagName("col")[_12];
		oParent.removeChild(oCol);
		oParent.insertBefore(oCol, oBefore);
	}
	aRows = this.tBody.tb.rows;
	l = aRows.length;
	i = 0;
	while(i < l) 
	{
		oCol = aRows[i].cells[_11];
		oParent = aRows[i];
		if(_12 == this.cols) 
		{
			oParent.removeChild(oCol);
			oParent.appendChild(oCol);
		}
		else 
		{
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
	for(i = 0; i < this.cols; i++) 
	{
		if(i == _11)
			continue;
		if(i == _12) 
		{
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
	if(_12 == this.cols) 
	{
		aHC.push(oCol);
		aBC.push(_18);
		aC.push(this.colsdata[_11]);
		aO.push(this.colOrder[_11]);
	}
	this.tHeadCols = aHC.slice(0);
	this.tBodyCols = aBC.slice(0);
	this.colsdata = aC.slice(0);
	this.colOrder = aO.slice(0);
	for(i = 0; i < this.cols; i++)
		this.tHeadCols[i].setAttribute("index", i);
	if((_12 == this.sIndex) && (_11 > _12))
		this.sIndex = _12 + 1;
	else 
	{
		if((_11 < _12) && (this.sIndex < _12) && (this.sIndex > _11))
			this.sIndex--;
		else
		{
			if(_11 == this.sIndex) 
			{
				this.sIndex = _12;
				if(_12 > _11)
					this.sIndex = _12 - 1;
			}
		}
	}
	if((_12 == this.secIndex) && (_11 > _12))
		this.secIndex = _12 + 1;
	else 
	{
		if((_11 < _12) && (this.secIndex < _12) && (this.secIndex > _11))
			this.secIndex--;
		else
		{
			if(_11 == this.secIndex) 
			{
				this.secIndex = _12;
				if(_12 > _11)
					this.secIndex = _12 - 1;
			}
		}
	}
	this.cancelSort = false;
	if($type(this.onmove) == "function")
		this.onmove();
}

dxSTable.ColumnMove = function(p)
{
	this.parent = p;
	this.obj = $("<div>").addClass("stable-move-header").get(0);
	this.sepobj = $("<div>").addClass("stable-separator-header").get(0);
}

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
	ignoreNextMove : false,

	init : function(o, _1b, _1c, _1d) 
	{      
		var self = this;      
		$(o).on('mousedown', function(e)
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
		if(e && e.which==3)
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
		self.ignoreNextMove = true;
		$(document).on("mousemove",self,self.drag);
		$(document).on("mouseup touchend",self,self.end);
		this.rx = $(this.parent.dHead).offset().left;
		this.obj.style.cursor = "move";
		return(false);
	},
	drag : function(e) 
	{
		var self = e.data;
		if(self.ignoreNextMove)
		{
			self.ignoreNextMove = false;
			return;
		}
		self.parent.cancelSort = true;
		var o = self.obj, l = parseInt(o.style.left), ex = e.clientX, i = 0, c = self.parent.cols;
		if(!self.added) 
		{
			self.parent.dHead.appendChild(self.obj);
			self.parent.dHead.appendChild(self.sepobj);
			self.added = true;
		}
		l += ex;
		if(!$type(o.lastMouseX))
			o.lastMouseX = ex;
		l -= o.lastMouseX;
		o.style.left = l + "px";
		var ox = 0;
		var orx = ex + self.parent.dBody.scrollLeft - self.rx;
		for(i = 0; i < c; i++) 
		{
		        if(self.parent.colsdata[i].enabled) 
		        {
				ox += self.parent.tHeadCols[i].offsetWidth;
				if(ox > orx) 
					break;
			}
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
		$(document).off("mousemove",self.drag);
		$(document).off("mouseup touchend",self.end);
		return(false);
	}
}

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
		{
		        this.calcSize().resizeHack();
			return(true);
		}
		rev = false;
		col = this.tHead.tb.rows[0].cells[this.sIndex];
	}
	else 
	{
		if(e.which==3)
			return(true);
		col = (e.target) ? e.target : e.srcElement;
	}
	if(col.tagName == "DIV") 
	{
		col = col.parentNode;
	}
	var ind = parseInt(col.getAttribute("index"));
	if(e && e.shiftKey && (this.sIndex >- 1))
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
		case TYPE_PEERS :
		case TYPE_SEEDS :
			d.sort(function(x, y) { return self.sortPeers(x, y); });
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
}

dxSTable.prototype.sortNumeric = function(x, y)
{
	var r = theSort.Numeric(x.v, y.v);
	return( (r == 0) ? this.sortSecondary(x, y) : r );
}

dxSTable.prototype.sortAlphaNumeric = function(x, y)
{
	var r = theSort.AlphaNumeric(x.v, y.v);
	return( (r == 0) ? this.sortSecondary(x, y) : r );
}

dxSTable.prototype.sortPeers = function(x, y)
{
	var r = theSort.PeersConnected(x.v, y.v);
	r = ( (r == 0) ? theSort.PeersTotal(x.v, y.v) : r );
	return( (r == 0) ? this.sortSecondary(x, y) : r );
}

dxSTable.prototype.sortSecondary = function(x, y)
{
	var m = this.getValue(x.e, this.secIndex);
	var n = this.getValue(y.e, this.secIndex);
	if(this.secRev)
	{
		var tmp = m;
		m = n;
      		n = tmp;
	}
	var ret = this.colsdata[this.secIndex].type;
	var order = (ret==0) ? theSort.AlphaNumeric(m, n) : ((ret==1) || (ret==4)) ? theSort.Numeric(m, n) : theSort.Default(m, n);
	return( order !== 0 ? order : theSort.Default(x.key, y.key) );
}

var theSort = 
{
	Default: function(x, y)
	{
		if(x==null) x = "";
		if(y==null) y = "";
		var a = x + "";
		var b = y + "";
		return((a < b) ? -1 : (a > b) ? 1 : 0);
	},
	Numeric: function(x, y)
	{	
		return(ir(x) - ir(y));
	},
	AlphaNumeric: function(x, y)
	{
		if(x==null) x = "";
		if(y==null) y = "";
		var a = (x + "").toLowerCase();
		var b = (y + "").toLowerCase();
		return(a.localeCompare(b));
	},
	PeersTotal: function(x, y)
	{
		return( this.Numeric( this.PeerValue(x,this.peers_total_re), this.PeerValue(y,this.peers_total_re) ) );
	},
	PeersConnected: function(x, y)
	{
		return( this.Numeric( this.PeerValue(x,this.peers_connected_re), this.PeerValue(y,this.peers_connected_re) ) );
	},
	PeerValue: function(x,pcre)
	{
		var val = ((x || '')+"").match(pcre);
		return( val ? val[1] : 0 );
	},

	peers_total_re: /\((\d+)\)$/,
	peers_connected_re: /^(\d+)/
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
}

dxSTable.prototype.setBodyState = function(v)
{
        this.tBody.style.visibility = v;
	for(var i = 0; i < this.cols; i++) 
	{
		if((this.colsdata[i].type==TYPE_PROGRESS) && this.colsdata[i].enabled)
		{
                        for(var j = 0; j < this.rows; j++)
                        {
				var id = this.rowIDs[j];
				if($$(id))
				{
					var td = $$(id).cells[i];
					if(iv($(td).attr("rawvalue"))==0)
						$(td.lastChild).css("visibility", "hidden");
					else
						$(td.lastChild).css("visibility", v);
				}
			}
		}
	}
}

dxSTable.prototype.assignEvents = function() 
{
	var self = this;
	this.scrollTimeout = null;
	this.scrollTop = 0;
	this.scrollDiff = 0;
	this.scOdd = null;
	this.isScrolling = false;

	$(this.dBody).on( "scroll",
		function(e) 
		{
			self.dHead.scrollLeft = self.dBody.scrollLeft;
			var maxRows = self.getMaxRows();
			if(self.scrollTop != self.dBody.scrollTop) 
			{
				self.scOdd = null;
				self.scrollDiff = self.scrollTop - self.dBody.scrollTop;
				self.scrollTop = self.dBody.scrollTop;
				if(self.noDelayingDraw || (Math.abs(self.scrollDiff) <= TR_HEIGHT*3) || (self.viewRows <= maxRows))
				{
					handleScroll.apply(self);
					return;
				}
				this.isScrolling = true;
				self.setBodyState("hidden");
				if(!!self.scrollTimeout) 
					window.clearTimeout(self.scrollTimeout);
				self.scrollTimeout = window.setTimeout(
					function() { self.isScrolling = false; handleScroll.apply(self); }
				        , 500);
				self.scrollPos();
			}
		});
	this.tHead.onmousedown = function(e) 
		{
			if(self.isResizing)
			      self.colDragEnd(e);
			else
			if((self.hotCell >- 1) && !self.isMoving) 
			{
				self.cancelSort = true;
				self.cancelMove = true;
                                $(document).on("mousemove",self,self.colDrag);
                                $(document).on("mouseup touchend",self,self.colDragEnd);
				self.rowCover.style.display = "block";
				return(false);
         		}
      		};
	this.tHead.onmouseup = function(e) 
		{
			if((self.hotCell >- 1) && !self.isMoving)
			{
				self.cancelSort = false;
				self.cancelMove = false;
			}
		};
	if(!$.support.touchable)
		$(this.dCont).on('mousedown', function(e) { self.bindKeys(); } );
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
	if(!$type(o.lastMouseX)) 
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
}

dxSTable.prototype.colDragEnd = function(e) 
{
        var self = e.data;
	$(document).off("mousemove",self.colDrag);
	$(document).off("mouseup touchend",self.colDragEnd);
	self.rowCover.style.display = "none";
	self.isResizing = false;
	self.colReszObj.style.left = 0;
	self.colReszObj.style.height = 0;
	self.colReszObj.style.visibility = "hidden";
	self.resizeColumn();
	self.cancelSort = false;
	self.cancelMove = false;
	document.body.style.cursor = "default";
	return(false);	
}

dxSTable.prototype.scrollPos = function()
{
	this.scp.style.display = "block";
	var mni = Math.floor(this.dBody.scrollTop / TR_HEIGHT);
	var mxi = mni + Math.floor(this.dBody.clientHeight / TR_HEIGHT);
	var mid = Math.floor(((mni + mxi) / 2));
	if(mid > this.viewRows)
		mid = this.viewRows - 1;
	var vr =- 1;
	var str = "";
	for(var i = 0; i < this.rows; i++)
	{
		var id = this.rowIDs[i];
		var r = this.rowdata[id];
		if($type(r) && r.enabled)
		{
			vr++;
			if(vr == mid)
			{
				str = r.data[0];
				break;
			}
		}
	}
	this.scp.innerHTML = escapeHTML("Current Row: " + str);
}

function handleScroll() 
{
	if(!!this.scrollTimeout) 
		window.clearTimeout(this.scrollTimeout);
	this.scrollTimeout = null;
	this.refreshRows(null, true);
	this.setBodyState("visible");
	this.scp.style.display = "none";
}

dxSTable.prototype.getMaxRows = function()
{
	return((this.maxRows || this.viewRows<this.maxViewRows) ? 1000000 : Math.ceil(Math.min(this.dBody.clientHeight,this.dCont.clientHeight) / TR_HEIGHT));	
}

dxSTable.prototype.refreshRows = function( height, fromScroll ) 
{
	if(this.isScrolling || !this.created) 
	{
		return;
   	}

   	var maxRows = height ? height/TR_HEIGHT : this.getMaxRows();
	var mni = Math.floor(this.dBody.scrollTop / TR_HEIGHT);
	if(mni + maxRows > this.viewRows) 
	{
		mni = this.viewRows - maxRows;
	}
	if(mni < 0) 
	{
		mni = 0;
   	}
	var mxi = mni + maxRows;
	if((mni==this.mni && mxi==this.mxi) && fromScroll)
		return;

	this.cancelSort = true;
	this.mni = mni;
	this.mxi = mxi;
	var h = (this.viewRows - maxRows) * TR_HEIGHT;
	var ht = (h<0) ? 0 : mni*TR_HEIGHT;
	var hb = (h<0) ? 0 : h - ht;
	this.tpad.style.height = ht + "px";
	this.bpad.style.height = hb + "px";
	var tb = this.tBody.tb, vr =- 1, i = 0, c = 0, obj = null;

	for(i = 0; i < this.rows; i++) 
	{
		var id = this.rowIDs[i];
		var r = this.rowdata[id];
		if(!$type(r)) 
			continue;
		obj = $$(id);
		if(!r.enabled) 
		{
			if( (obj != null) && (obj.parentNode == tb) )
			{
				tb.removeChild(obj);
			}
			continue;
		}
		vr++;
		if((vr >= mni) && (vr <= mxi)) 
		{
			if(!$type(tb.rows[c])) 
			{
				if( (obj != null) && (obj.parentNode == tb) )
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
					if( (obj != null) && (obj.parentNode == tb) )
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
			if( (obj != null) && (obj.parentNode == tb) )
			{
				tb.removeChild(obj);
			}
		}
   	}
	this.refreshSelection();
	this.cancelSort = false;
	this.calcSize().resizeHack();
}

dxSTable.prototype.keyEvents = function(e) 
{
	var self = e.data;
	if(!e.fromTextCtrl && !theDialogManager.isModalState())
	{
		var c = e.which;
		if((browser.isKonqueror && c == 127) || (c == 46))
		{
			if($type(self.ondelete) == "function") 
				self.ondelete();
		}
		else 
		if(e.metaKey)
		{
			switch(c)
			{
				case 65:
				{
					self.fillSelection();
					if($type(self.onselect) == "function") 
						self.onselect(e);
					return(false);
				}
				case 90:
				{
					self.clearSelection();
					if($type(self.onselect) == "function") 
						self.onselect(e);
					return(false);
	            		}
        	 	}
	      	}
	}
}

dxSTable.prototype.selectRow = function(e, row) 
{
	if(!$.support.touchable)
		this.bindKeys();
	var id = row.id;
	if(!((e.which==3) && (this.rowSel[id] == true))) 
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
						_81 = true;
					if(k == id) 
						passedCID = true;
				}
			}
		}
		else 
		{
			if(e.metaKey) 
			{
				this.stSel = id;
				this.rowSel[id] =!this.rowSel[id];
				if(this.rowSel[id]) 
					this.selCount++;
				else 
					this.selCount--;
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
						this.rowSel[k] = false;
				}
			}
		}
		if(this.selCount == 0) 
			this.stSel = null;
		this.refreshSelection();
	}
	if($type(this.onselect) == "function") 
		this.onselect(e, id);
	return(false);
}

dxSTable.prototype.addRowById = function(ids, sId, icon, attr, fast = false)
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
	this.addRow(cols, sId, icon, attr, fast);
}

dxSTable.prototype.addRow = function(cols, sId, icon, attr, fast = false) 
{
	if(cols.length != this.cols) 
		return;
	if(this.sortTimeout != null) 
	{
		window.clearTimeout(this.sortTimeout);
		this.sortTimeout = null;
	}
	this.rowdata[sId] = {"data" : cols, "icon" : icon, "attr" : attr, "enabled" : true, fmtdata: this.format(this,cols.slice(0))};
	this.rowSel[sId] = false;
	this.rowIDs.push(sId);
	
	// When adding hundreds or thousands of rows at once, it's faster to skip a few steps
	// This is safe as long as we call dxSTable.prototype.refreshRows() when we're done
	if (!fast)
	{
		var maxRows = this.getMaxRows();
		if(this.viewRows < maxRows)
			this.tBody.tb.appendChild(this.createRow(cols, sId, icon, attr));
		this.rows++;
		this.viewRows++;
		if(this.viewRows > maxRows) 
			this.bpad.style.height = ((this.viewRows - maxRows) * TR_HEIGHT) + "px";

		var self = this;
		if((this.sIndex !=- 1) && !this.noSort)
			this.sortTimeout = window.setTimeout(function() { self.Sort(); }, 200);
	}
	else
	{
		this.rows++;
		this.viewRows++;
	}
}

dxSTable.prototype.createRow = function(cols, sId, icon, attr) 
{
	if(!$type(attr)) 
		attr = [];
	var tr = $("<tr>").attr( { index: this.rows, title: cols[0] });
	if(sId != null) 
		tr.attr("id",sId);
	var self = this;
	if(this.colorEvenRows) 
		tr.addClass( (this.rows & 1) ? "odd" : "even" );

	tr.mouseclick(function(e) { return(self.selectRow(e, this)); });

	if($type(this.ondblclick) == "function") 
		tr.on('dblclick', function(e) { return(self.ondblclick(this)); } );

	for(var k in attr) 
		tr.attr(k, attr[k]);
	var data = this.rowdata[sId].fmtdata;
	var s = "";
	var div;
	var ret;
	for(var i = 0; i < this.cols; i++) 
	{
		var ind = this.colOrder[i];
		s+="<td class='stable-"+this.dCont.id+"-col-"+ind+"'";
		var span1 = "";
		var span2 = "";
		if(this.colsdata[i].type==TYPE_PROGRESS)
		{
			s+=" rawvalue='"+($type(cols[ind]) ? cols[ind] : "")+"'";
		        span1 = "<span class='meter-text' style='overflow: visible'>"+escapeHTML(data[ind])+"</span>";
			div = "<div class='meter-value' style='float: left; background-color: "+
		 		(new RGBackground()).setGradient(this.prgStartColor,this.prgEndColor,parseFloat(data[ind])).getColor()+
				"; width: "+iv(data[ind])+"%"+
				"; visibility: "+(iv(data[ind]) ? "visible" : "hidden")+
				"'>&nbsp;</div>";
		}
		else
			div = "<div>"+((String(data[ind]) == "") ? "&nbsp;" : escapeHTML(data[ind]))+"</div>";
		if((ind == 0) && (icon != null)) 
			span2 = "<span class='stable-icon "+icon+"'></span>";
		if(!this.colsdata[i].enabled && !browser.isIE7x)
			s+=" style='display: none'";
		s+=">";
		s+=span1;
		s+=span2;
		s+=div;
		s+="</td>";
	}
	ret = tr.append(s).get(0);
	if(!browser.isIE7x)
	{
		var _e = this.tBody.getElementsByTagName("colgroup")[0].getElementsByTagName("col");
		for(var i = 0, l = _e.length; i < l; i++) 
			ret.cells[i].style.textAlign = this.tHeadCols[i].style.textAlign;
	}
	return(ret);
}

dxSTable.prototype.removeRow = function(sId) 
{
	if(!$type(this.rowdata[sId])) 
		return;
	if(this.rowdata[sId].enabled) 
	{
		this.viewRows--;
	}
	try 
	{
		var obj = this.tBody.tb.removeChild($$(sId));
		$(obj).off();
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
}

dxSTable.prototype.clearRows = function() 
{
	if(this.created)
	{
		var tb = this.tBody.tb;
		while(tb.firstChild) 
		{ 
			var obj = tb.removeChild(tb.firstChild); 
			$(obj).off();
		}
		this.rows = 0;
		this.viewRows = 0;
		this.selCount = 0;		
		this.rowSel = new Array(0);
		this.rowdata = new Array(0);
		this.rowIDs = new Array(0);
		this.bpad.style.height = "0px";
		this.tpad.style.height = "0px";
		this.dBody.scrollTop = 0;
	}
}

dxSTable.prototype.setAlignment = function()
{
	var i, aRows, aAlign, j, align;
	var aAlign = [];
	for(i = 0; i < this.cols; i++)
	{
		switch(this.colsdata[i].align)
		{
			case ALIGN_LEFT: 
				align = "left";
				break;
			case ALIGN_CENTER: 
				align = "center";
				break;
         		case ALIGN_RIGHT: 
	         		align = "right";
				break;
			case ALIGN_AUTO: 
			default: 
				align = (this.colsdata[i].type==TYPE_NUMBER) ? "right" : "left";
		}
		aAlign.push(align);
		this.tHeadCols[i].style.textAlign = align;
	}
	var col = this.tBody.getElementsByTagName("colgroup")[0].getElementsByTagName("col");
	if(document.all || browser.isAppleWebKit || browser.isKonqueror)
	{
		for(var i = 0; i < col.length; i++)
			col[i].align = aAlign[i];
	}
	else
	{
		var ss = null, rules = null;
		for(var n = 0, l = document.styleSheets.length; n < l; n++)
		{
			if(!document.styleSheets[n].href || (document.styleSheets[n].href.indexOf("stable.css") ==- 1))
				continue;
			try {
			ss = document.styleSheets[n];
			rules = ss.cssRules;
			} catch(e) { return; }
		}
		if(rules == null)
			return;
		if(!$type(this.colRules))
			this.colRules = new Array();
		for(var j = 0; j < col.length; j++)
		{
			var k = this.colOrder[j];
			if(!this.colRules[k])
			{
				for(var i = 0, l = rules.length; i < l; i++)
				{
					if((rules[i].type == CSSRule.STYLE_RULE) && (rules[i].selectorText == ".stable-" + this.dCont.id + "-col-" + k))
					{
						this.colRules[k] = rules[i];
						break;
					}
				}
			}
			if($type(this.colRules[k]))
				this.colRules[k].style.textAlign = aAlign[j];
			else
				this.colRules[k] = ss.cssRules[ss.insertRule(".stable-" + this.dCont.id + "-col-" + k + " div { text-align: " + aAlign[j] + "; }", 0)];
		}
	}
}

dxSTable.prototype.hideRow = function(sId)
{
	if(this.rowdata[sId].enabled)
		this.viewRows--;
	this.rowdata[sId].enabled = false;
}

dxSTable.prototype.unhideRow = function(sId)
{
	if(!this.rowdata[sId].enabled)
		this.viewRows++;
	this.rowdata[sId].enabled = true;
}

dxSTable.prototype.refreshSelection = function() 
{
        if(this.created)
        {
		var rows = this.tBody.tb.rows, l = rows.length;
		for(var i = 0; i < l; i++) 
		{
			if(this.rowSel[rows[i].id] == true) 
				rows[i].className = "selected";
			else 
			{
				if(!this.colorEvenRows) 
					rows[i].className = "even";
				else 
					rows[i].className = (i & 1) ? "odd" : "even";
			}
      		}
	}
}

dxSTable.prototype.clearSelection = function()
{
	for(var k in this.rowSel)
		this.rowSel[k] = false;
	this.selCount = 0;
	this.refreshSelection();
}

dxSTable.prototype.correctSelection = function()
{
	this.selCount = 0;
	for(var k in this.rowSel) 
	{
		if(this.rowdata[k].enabled && this.rowSel[k])
		{
			this.selCount++;
		}
	}
}

dxSTable.prototype.fillSelection = function() 
{
	this.selCount = 0;
	for(var k in this.rowSel) 
		if(this.rowdata[k].enabled)
		{
			this.rowSel[k] = true;
			this.selCount++;
		}
	this.refreshSelection();
}

dxSTable.prototype.getCache = function(col)
{
	var a = new Array(0);
	if(this.tBody)
	{
		for(var k in this.rowdata)
			a.push( {"key" : k, "v" : this.getValue(this.rowdata[k], col), "e" : this.rowdata[k]} );
		this.rowdata = [];
	}
	return(a);
}

dxSTable.prototype.clearCache = function(a)
{
	var l = a.length;
	for(var i = 0; i < l; i++)
	{
		a[i].v = null;
		a[i].e = null;
		a[i] = null;
	}
}

dxSTable.prototype.getColOrder = function(col)
{
	for(var i = 0; i < this.cols; i++)
		if(this.colOrder[i] == col)
			return(i);
	return(-1);
}

dxSTable.prototype.getColById = function(id)
{
        for(var i = 0; i < this.ids.length; i++)
        	if(this.ids[i]==id)
			return(i);
	return(-1);
}

dxSTable.prototype.getIdByCol = function(col)
{
	return(this.ids[col]);
}

dxSTable.prototype.updateRowFrom = function(tbl,tblRow,row)
{
	var updated = this.setIcon(row,tbl.getIcon(tblRow));
	for(var i = 0; i < this.cols; i++)
	{
		if(this.setValue(row,i,tbl.getRawValue(tblRow,i)))
			updated = true;
	}
	return(updated);
}

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
}

dxSTable.prototype.getValues = function(row)
{
	var ret = new Array();
	for(var i = 0; i < this.cols; i++)
		ret.push(this.getRawValue(row,i));
	return(ret);
}

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
	return(ret);
}

dxSTable.prototype.setValues = function(row,arr)
{
	var ret = false;
	for(var i = 0; i < this.cols; i++)
		ret = this.setValue(row,i,arr[i]) || ret;
	return(ret);
}

dxSTable.prototype.setValueById = function(row, id, val)
{
	return(this.setValue(row, this.getColById(id), val));
}

dxSTable.prototype.setValue = function(row, col, val)
{
	if((col>=0) && this.rowdata[row])
	{
		this.rowdata[row].data[col] = val;
		var r = $$(row);
		var rawvalue = val;
		var arr = [];
		arr[col] = val;
		val = this.format(this,arr)[col];

		if(this.rowdata[row].fmtdata[col] != val)
		{
			this.rowdata[row].fmtdata[col] = val;
        		if(r)
        		{
	        		var c = this.getColOrder(col);
				var td = r.cells[c];
			
			        if(td)
			        {
					if(this.colsdata[c].type==TYPE_PROGRESS)
					{
						$(td).attr("rawvalue",rawvalue);
						td.lastChild.style.width = iv(val)+"%";
						td.lastChild.style.backgroundColor = (new RGBackground()).setGradient(this.prgStartColor,this.prgEndColor,parseFloat(val)).getColor();
						if(!iv(val))
							$(td.lastChild).css({visibility: "hidden"});
						else
							$(td.lastChild).css({visibility: "visible"});
						td.firstChild.innerHTML = escapeHTML(val);
					}
					else
						td.lastChild.innerHTML = escapeHTML(val);
				}
			}					
			return(true);
		}
	}
	return(false);
}

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

		td.firstChild.className = (icon) ? "stable-icon " + icon : "";
		return(true);
	}
	return(false);
}

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
}

dxSTable.prototype.getAttr = function(row, attrName)
{
	return(this.rowdata[row].attr ? this.rowdata[row].attr[attrName] : null);
}

dxSTable.prototype.resize = function(w, h) 
{
	if(this.dCont)
	{
		if(w) 
			this.dCont.style.width = w + "px";
		if(h) 
			this.dCont.style.height = h + "px";
		this.refreshRows(h);
	}
}

dxSTable.prototype.isColumnEnabled = function(i) 
{
	return(this.colsdata[this.getColOrder(i)].enabled ? 1 : 0);
}

dxSTable.prototype.getColWidth = function(i) 
{
	return(this.colsdata[this.getColOrder(i)].width);
}

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

dxSTable.prototype.scrollTo = function(value) 
{
	if (this.dBody == null)
		return null;
	
	var old = this.dBody.scrollTop;
	this.dBody.scrollTop = value;
	return(old);
}
