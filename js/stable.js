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

var dxSTable = function() 
{
	this.rowdata = {};
	this.rowIDs = [];
	this.rowSel = {};
	this.maxRows = false;
	this.noDelayingDraw = true;
	this.viewRows = 0;
	this.colsdata = new Array();
	this.stSel = [];
	this.format = function(r) { return r; };
	this.sortId = '';
	this.reverse = 0;
	this.sortId2 = '';
	this.secRev = 0;
	this.colDnD = null;
	this.colOrder = new Array();
	this.ids = new Array();
	this.onselect = null;
	this.ondelete = null;
	this.onsort = null;
	this.onmove = null;
	this.onresize = null;
	this.ondblclick = null;
	this.currIndex = -1;
	this.hotCell = -1;
	this.index = -1;
	this.indexNew = -1;
	this.isMoving = false;
	this.isResizing = false;
	this.isSorting = false;
	this.mouseX = 0;  // x offset of cursor relative to the cell's left border
	this.selCount = 0;
	this.created = false;
	this.prgStartColor = new RGBackground(".meter-value-start-color");
	this.prgEndColor = new RGBackground(".meter-value-end-color");
	this.mni = 0;
	this.mxi = 0;
	this.pendingSync = {};
	this.syncDOMHandlers = {
		throttle: {
			timeoutId: 0,
			delayMs: 500
		},
		debounce: {
			timeoutId: 0,
			startTime: -1,
			delayMs: 50
		},
		lazy: false,
		reqAFrameId: 0
	};
}

dxSTable.prototype.setPaletteByURL = function(url) {
	// TODO: deprecated, remove in v6
	noty("`dxSTable.setPaletteByURL()` is deprecated and will be removed in v6. Please avoid using this method.");
}

dxSTable.prototype.bindKeys = function()
{
	$(document).off("keydown", this.keyEvents);
	$(document).on("keydown", this, this.keyEvents);
}

dxSTable.prototype.create = function(ele, styles, aName)
{
	if(!ele || this.created)
		return;
	this.prefix = aName;
	this.dCont = $(ele).addClass("stable").append(
		$("<div>").addClass("stable-body").append(  // -> this.dBody (`this.dHead` gone)
			$("<table>").append(  // -> (old `this.tBody` and `this.tHead` gone)
				$("<colgroup>"),  // -> cg
				$("<thead>").append(  // -> this.tHead.tb
					$("<tr>"),  // -> this.tHeadRow
				),
				$("<tbody>").addClass("stable-virtpad").append(
					$("<tr>"),  // -> this.tpad
				),
				$("<tbody>"),  // -> this.tBody (new pointer)
				$("<tbody>").addClass("stable-virtpad").append(
					$("<tr>"),  // -> this.bpad
				),
			),
		),
		$("<div>").addClass("stable-gadgets").append(
			$("<div>").addClass("rowcover").hide(),  // -> this.rowCover
			$("<div>").addClass("stable-move-header").hide(),  // -> this.obj (drag and move object)
			$("<div>").addClass("stable-separator-header").hide(),  // -> this.sepobj (drag and move separator)
			$("<div>").addClass("stable-resize-header").hide(),  // -> this.colReszObj
			$("<span>").addClass("stable-scrollpos"),  // -> this.scp
		),
	);

	for (let i in this.colOrder) {
		if (this.colOrder[i] >= styles.length) {
			this.colOrder = new Array();
			break;
		}
	}
	this.colDnD = new DnD(
		this.tHeadRow[0],
		{
			allowMobile: true,
			onStart: (ev) => {
				if (ev && ev.which === 3) {
					this.onRightClick(ev);
					return;
				}

				if (this.hotCell > -1) {
					this.isResizing = true;
					this.isMoving = false;
				} else {
					this.isSorting = true;
					this.index = this.currIndex;
					const p = this.tHeadCols[this.index];
					this.obj.css({
						width: p.offsetWidth - 16,
						left: p.offsetLeft - this.dBody.scrollLeft,
						"text-align": (this.colsdata[this.index].type == TYPE_NUMBER) ? "right" : "left",
						cursor: "move",
					}).text(p.lastChild.innerText);
				}
				return true;
			},
			onRun: (ev) => {
				if (this.isResizing) {
					this.colDragResize(ev);
				} else {
					this.isMoving = true;
					this.isSorting = false;
					this.colDragMove(ev);
				}
			},
			onFinish: (ev) => {
				if (this.isResizing) {
					this.colDragResizeEnd(ev);
				} else if (this.isMoving) {
					this.colDragMoveEnd(ev);
				} else {
					this.Sort(ev);
				}
				this.isResizing = false;
				this.isMoving = false;
				this.isSorting = false;
			},
		},
	);
	for (let i = 0; i < styles.length; i++) {
		if(!$type(this.colOrder[i]))
			this.colOrder[i] = i;
		if(!$type(styles[this.colOrder[i]].enabled)) 
			styles[this.colOrder[i]].enabled = true;
		this.colsdata[i] = styles[this.colOrder[i]];
		this.colsdata[i].width = iv(this.colsdata[i].width);
		this.ids[i] = styles[i].id;

		const td = $("<td>").append(
				$("<div>").append($("<span>").text(styles[this.colOrder[i]].text))
			)
				.width(styles[this.colOrder[i]].width)
				.attr("index", i)
				.toggle(!!this.colsdata[i].enabled);
		this.tHeadRow.append(td);
		td.mouseclick((e) => { 
			this.onRightClick(e);
		});
		if(!$.support.touchable)
			td.on('mousedown', (e) => { this.bindKeys(); });
	}

	this.tBody.mouseclick(this.handleClick.bind(this));
	if(typeof this.ondblclick === 'function') {
		this.tBody.dblclick(this.handleClick.bind(this));
	}

	const cg = this.dCont.find("colgroup");
	for (var i = 0; i < styles.length; i++) {
		cg.append(
			$("<col>").width(this.colsdata[i].width).toggle(!!this.colsdata[i].enabled),
		);
	}
	this.init();
	this.resizeColumn();
	this.created = true;
}

dxSTable.prototype.handleClick = function(e)
{
	const row = $(e.target).parents('tr')[0];
	if (e.type == 'dblclick') {
		this.ondblclick(row);
	} else if (e.which === 3 || e.which === 1) {
		// only select rows with left or right click
		this.selectRow(e, row);
	}
}

dxSTable.prototype.toggleColumn = function(i) {
	this.colsdata[i].enabled = !this.colsdata[i].enabled;
	$(this.tBodyCols[i]).toggle(this.colsdata[i].enabled);
	$(this.tHeadCols[i]).toggle(this.colsdata[i].enabled);
	this.tBody.find(`tr td:nth-child(${i + 1})`).toggle(this.colsdata[i].enabled);
	if(this.colsdata[i].enabled)
	{
		$(this.tBodyCols[i]).width( this.colsdata[i].width );
		$(this.tHeadCols[i]).width( this.colsdata[i].width );
	}
	this.resizeColumn();
	if($type(this.onresize) == "function")
		this.onresize();
}

dxSTable.prototype.removeColumnById = function(id, name)
{
	this.removeColumn(this.getColById(id), name);
}

dxSTable.prototype.removeColumn = function(no) {
	const i = this.getColOrder(no);
	if(i>=0)
	{
		$(this.tHeadCols[i]).remove();
		$(this.tBodyCols[i]).remove();

		for (var D = 0, B = this.tBody[0].childNodes.length; D < B; D ++ )
			$(this.tBody[0].childNodes[D].childNodes[i]).remove();

		this.ids.splice(no,1);
		this.colOrder.splice(i,1);
		for(var j = 0; j < this.cols; j++)
			if(this.colOrder[j] > no)
				this.colOrder[j]--;

		this.colsdata.splice(i,1);

		for(let c = i; c < this.cols; c++)
			this.tHeadCols[c].setAttribute("index", c);
		if(this.getColNoById(this.sortId) === i)
			this.sortId = '';
		if(this.getColNoById(this.sortId2) === i) {
			this.sortId2 = '';
			this.secRev = 0;
		}

		this.resizeColumn();
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

dxSTable.prototype.calcSize = function() {
	// TODO: remove in v6
	noty("`dxStable.calcSize()` is deprecated and will be removed in v6. Please avoid using it and run `dxSTable.resizeColumn()` directly.")
}

dxSTable.prototype.resizeColumn = function() {
	if (this.tBody.length < 1)
		return;

	var _e = this.tBodyCols;
	for (var i = 0; i < _e.length; i++) {
		const w = this.colsdata[i].width;
		if (iv(_e[i].style.width) !== w) {
			_e[i].style.width = w + "px";
		}
		if ((browser.isAppleWebKit || browser.isKonqueror) && this.tBody[0].rows.length > 0) {
			if ((this.tBody[0].rows[0].cells[i].width || browser.isSafari) && (this.tBody[0].rows[0].cells[i].width !== w) && (w >= 4)) {
				this.tBody[0].rows[0].cells[i].width = w;
			}
		}
	}
}

/**
 * Moves a column identified by the given index number, to a location
 * by the given target index number.
 *
 * @param {Number} _11 Origin index of the moving column.
 * @param {Number} _12 New index of the moving column.
 * @returns
 */
dxSTable.prototype.moveColumn = function(_11, _12) {
	if(_11 == _12)
		return;

	// move header cells
	const oHeadCol = this.tHeadCols[_11];
	if(_12 == this.cols) {
		this.tHeadRow[0].appendChild(oHeadCol);
	} else {
		$(this.tHeadCols[_12]).before(oHeadCol);
	}

	// move body colgroup cols
	const oBodyCol = this.tBodyCols[_11];
	const oBodyParent = oBodyCol.parentNode;
	if(_12 == this.cols) {
		oBodyParent.appendChild(oBodyCol);
	} else {
		$(this.tBodyCols[_12]).before(oBodyCol);
	}

	// move body cells row by row
	const aRows = this.tBody[0].rows;
	for(let i = 0; i < aRows.length; i++) {
		const bodyRow = aRows[i];
		const oBodyCol = bodyRow.cells[_11];
		if(_12 == this.cols) {
			bodyRow.appendChild(oBodyCol);
		} else {
			$(bodyRow.cells[_12]).before(oBodyCol);
		}
	}

	// move data in `colsdata` and `colOrder` properties
	if (_11 < _12) {
		this.colsdata = [
			...this.colsdata.slice(0, _11),
			...this.colsdata.slice(_11 + 1, _12),
			this.colsdata[_11],
			...this.colsdata.slice(_12),
		];
		this.colOrder = [
			...this.colOrder.slice(0, _11),
			...this.colOrder.slice(_11 + 1, _12),
			this.colOrder[_11],
			...this.colOrder.slice(_12),
		];
	} else {
		this.colsdata = [
			...this.colsdata.slice(0, _12),
			this.colsdata[_11],
			...this.colsdata.slice(_12, _11),
			...this.colsdata.slice(_11 + 1),
		];
		this.colOrder = [
			...this.colOrder.slice(0, _12),
			this.colOrder[_11],
			...this.colOrder.slice(_12, _11),
			...this.colOrder.slice(_11 + 1),
		];
	}

	for(let i = 0; i < this.cols; i++)
		this.tHeadCols[i].setAttribute("index", i);
	if($type(this.onmove) == "function")
		this.onmove();
}

dxSTable.prototype.renameColumnById = function(id, name)
{
	this.renameColumn(this.getColById(id), name);
}

dxSTable.prototype.renameColumn = function(no, name) {
	no = this.getColOrder(no);
	if (no>=0) {
		this.colsdata[no].text = name;
		this.tHeadRow[0].cells[no].getElementsByTagName("span")[0].innerText = name;
	}
}

dxSTable.prototype.Sort = function(e) 
{
	if (!this.created)
		return true;
	this.isSorting = true;
	const primarySorting = Boolean(this.sortId);
	const notSorting = e == null && !primarySorting;
	if(notSorting || e?.which === 3) {
		if(notSorting) {
			this.resizeColumn();
		}
		return true;
	}
	const sortColNo = this.getColNoById(this.sortId);
	const oldCol = primarySorting ? this.tHeadCols[(sortColNo >= 0) ? sortColNo : 0] : null;
	const col = this.tHeadCols[this.currIndex] ?? oldCol;
	const sortIdCurrent = this.getIdByCol(this.colOrder[parseInt(col.getAttribute("index"))]) ?? '';
	const toggleReverse = (oldId, oldRev) => (oldId === sortIdCurrent) ? 1 - oldRev : 0;
	if (e) {
		if (e.shiftKey && primarySorting) {
			// do secondary sort
			this.secRev = toggleReverse(this.sortId2, this.secRev);
			this.sortId2 = sortIdCurrent;
		} else {
			// do primary sort
			this.reverse = toggleReverse(this.sortId, this.reverse);
			this.sortId = sortIdCurrent;
			this.sortId2 = 'name';
			this.secRev = 0;
		}
	}
	if (this.sortId === sortIdCurrent) {
		oldCol?.classList.remove("asc", "desc");
		if (this.reverse) {
			col.classList.add("desc");
			col.classList.remove("asc");
		} else {
			col.classList.add("asc");
			col.classList.remove("desc");
		}
	}

	const sortingValues = id => {
		const no = this.getColById(id);
		return no >= 0 ? Object.fromEntries(
			Object.entries(this.rowdata)
				.map(([k,v]) => [k, v.data[no]]
			)
		) : {};
	};

	const primaryValues = sortingValues(this.sortId);
	const primarySort = this.getSortFunc(this.sortId, this.reverse, x => primaryValues[x]);

	const secondary = this.sortId2 || this.ids[0];
	const secondaryValues = sortingValues(secondary);
	const secondarySort = this.getSortFunc(secondary, this.secRev, x => secondaryValues[x]);

	this.rowIDs.sort((x,y) => primarySort(x,y) || secondarySort(x,y) || theSort.Default(x, y));

	this.isSorting = false;
	this.refreshRows();
	if($type(this.onsort) == "function") 
		setTimeout(() => this.onsort());
	return(false);
}

dxSTable.prototype.getSorter = function(colType, valMapping)
{
	const peerSort = (x,y) => theSort.PeersConnected(x,y) || theSort.PeersTotal(x,y);
	const sorter = {
		[TYPE_STRING]: theSort.AlphaNumeric,
		[TYPE_PROGRESS]: theSort.Numeric,
		[TYPE_NUMBER]: theSort.Numeric,
		[TYPE_PEERS]: peerSort,
		[TYPE_SEEDS]: peerSort
	}[colType];
	return sorter ?
		((x, y) => sorter(valMapping(x), valMapping(y)))
		: ((_) => 0);
}

dxSTable.prototype.getSortFunc = function(id, reverse, valMapping)
{
	const order = reverse ? -1 : 1;
	const sorter = this.getSorter(this.colsdata[this.getColNoById(id)]?.type, valMapping);
	return (x,y) => order * sorter(x, y);
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

dxSTable.prototype.init = function() {
	this.assignEvents();
	this.setAlignment();
}

dxSTable.prototype.setBodyState = function(v) {
	this.tBody.css("visibility", v);
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
	this.scrollTimeout = 0;
	this.scrollTop = 0;
	this.scrollDiff = 0;
	this.scOdd = null;
	this.isScrolling = false;

	$(this.dBody).on("scroll", function(e) {
			var maxRows = self.getMaxRows();
			if (self.scrollTop != self.dBody.scrollTop) {
				self.scOdd = null;
				self.scrollDiff = self.scrollTop - self.dBody.scrollTop;
				self.scrollTop = self.dBody.scrollTop;
				if (
					self.isScrolling ||
					(!self.noDelayingDraw &&
						Math.abs(self.scrollDiff) > this.TR_HEIGHT*3 &&
						(self.viewRows > maxRows))
				) {
					self.isScrolling = true;
					if (self.scrollTimeout !== 0)
						clearTimeout(self.scrollTimeout);
					self.scrollTimeout = setTimeout( function() {
						self.scrollTimeout = 0;
						self.isScrolling = false;
						self.pendingSync.scroll = true;
						self.syncDOMAsync();
					}, 500);
					self.syncDOMAsync();
				}
				self.pendingSync.scroll = true;
				self.syncDOMAsync();
			}
	});
	this.tHeadRow.on("mousemove", (ev) => {
		if (this.isResizing || this.isMoving) return;

		// get the cell currently being hovered over and its index number
		const currCell = ev.target.closest("td");  // find the containing cell element
		this.currIndex = Array.from(this.tHeadCols).indexOf(currCell);

		// get the X coordinate within the entire header row
		let mouseX = ev.clientX + this.dBody.scrollLeft - this.dCont.offset().left;
		for (let i = 0; i < this.currIndex; i++) {
			mouseX -= this.tHeadCols[i].offsetWidth;
		}

		const delta = 8;
		if (mouseX < delta) {
			// cursor near left border - drag to resize PREVIOUS header cell
			this.hotCell = this.currIndex - 1;
			// can't resize if cursor near the left border of the first cell
			currCell.style.cursor = (this.currIndex === 0) ? "default" : "e-resize";
		} else if (mouseX > (this.tHeadCols[this.currIndex].offsetWidth - delta)) {
			// cursor near right border - drag to resize CURRENT header cell
			this.hotCell = this.currIndex;
			currCell.style.cursor = "e-resize";
		} else {
			// cursor on the cell body - drag to move CURRENT header cell
			this.hotCell = -1;
			currCell.style.cursor = "default";
		}
	});
	if(!$.support.touchable)
		this.dCont.on('mousedown', function(e) { self.bindKeys(); } );
}

dxSTable.prototype.colDrag = function(e) {
	noty("`dxSTable.colDrag()` is deprecated. Please use either `dxSTable.colDragResize()` or `dxSTable.colDragMove()` instead.");
	this.colDragResize(e);
}

dxSTable.prototype.colDragResize = function(e) {
	const o = this.tHeadCols[this.hotCell];
	const nw = o.clientWidth + e.originalEvent.movementX;
	if (nw < 10) {
		return(true);
	}
	this.colsdata[this.hotCell].width = nw;
	o.style.width = nw + "px";
	document.body.style.cursor = "e-resize";

	this.colReszObj
		.css({left: o.offsetLeft + nw})
		.show();
	this.resizeColumn();

	try { document.selection.empty(); } catch(ex) {}
	return(false);
}

dxSTable.prototype.colDragMove = function(e) {
	const o = this.obj;
	o.show().css({left: o.offset().left - this.tHeadRow.offset().left - this.dBody.scrollLeft + e.originalEvent.movementX});
	const c = this.cols;
	let mouseX = e.clientX - this.dCont.offset().left + this.dBody.scrollLeft;
	for (let i = 0; i < c; i++) {
		if (mouseX < this.tHeadCols[i].offsetWidth) {
			this.currIndex = i;
			break;
		}
		mouseX -= this.tHeadCols[i].offsetWidth;
	}
	if ((this.currIndex === c - 1) && (this.tHeadCols[c - 1].offsetWidth - mouseX < 16)) {
		// cursor on the last header cell AND near the right border (less than 16px to the border)
		this.sepobj.show().css("left", this.tHeadCols[c - 1].offsetLeft + this.tHeadCols[c - 1].offsetWidth - this.dBody.scrollLeft - 1);
		this.indexNew = this.currIndex + 1;
	} else {
		this.sepobj.show().css("left", this.tHeadCols[this.currIndex].offsetLeft - this.dBody.scrollLeft);
		this.indexNew = this.currIndex;
	}
}

dxSTable.prototype.colDragEnd = function(e) {
	noty("`dxSTable.colDragEnd()` is deprecated. Please use either `dxSTable.colDragResizeEnd()` or `dxSTable.colDragMoveEnd()` instead.");
	this.colDragResizeEnd(e);
}

dxSTable.prototype.colDragResizeEnd = function(e) {
	if ($type(this.onresize) === "function")
		this.onresize();
	this.rowCover.hide();
	this.colReszObj.hide();
	document.body.style.cursor = "default";
	return(false);	
}

dxSTable.prototype.colDragMoveEnd = function(e) {
	try {
		this.obj.css("cursor", "default");
		this.moveColumn(this.index, this.indexNew);
	} catch(ex) {}
	this.index = -1;
	this.indexNew = -1;
	this.isMoving = false;
	this.obj.hide();
	this.sepobj.hide();
	return false;
}

dxSTable.prototype.scrollPos = function()
{
	this.scp.style.display = "block";
	var mni = Math.floor(this.dBody.scrollTop / this.TR_HEIGHT);
	var mxi = mni + Math.floor(this.tBody.height() / this.TR_HEIGHT);
	var mid = Math.floor((mni + mxi) / 2);
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

dxSTable.prototype.getMaxRows = function()
{
	return this.maxRows
		? this.viewRows
		: Math.ceil(Math.min(this.tBody.height(), this.dCont.height()) / this.TR_HEIGHT);
}

dxSTable.prototype.refreshRows = function( height, fromScroll ) 
{
	if (this.isScrolling || !this.created)
		return;

	const maxRows = height ? height / this.TR_HEIGHT : this.getMaxRows();
	const topRow = Math.max(0, Math.min(
		this.viewRows - maxRows,
		Math.floor(this.dBody.scrollTop / this.TR_HEIGHT)
	));
	const extra = this.noDelayingDraw ? 16 : 4;
	const mni = Math.max(
		0,
		// floor to the neareast even number to keep alternating background color consistent
		Math.floor(this.dBody.scrollTop / (this.TR_HEIGHT * 2)) * 2 - extra,
	);
	const mxi = Math.min(
		this.viewRows,
		Math.ceil((this.dBody.scrollTop + this.dBody.getBoundingClientRect().height) / this.TR_HEIGHT) + extra,
	);
	if (fromScroll && (mni==this.mni && mxi==this.mxi))
		return;

	this.mni = mni;
	this.mxi = mxi;
	const createRow = (id) => {
		const r = this.rowdata[id];
		return this.createRow(r.data, id, r.icon, r.attr);
	};
	const viewRows = this.rowIDs
		.filter(id => this.rowdata[id]?.enabled)
		.filter((_, index) => index >= mni && index <= mxi)
		.map(id => $$(id) ?? createRow(id))

	this.tpad.height(mni * this.TR_HEIGHT);
	this.tBody[0].replaceChildren(...viewRows);
	this.bpad.height((this.viewRows - mxi) * this.TR_HEIGHT);

	this.refreshSelection();
	this.resizeColumn();
}

dxSTable.prototype.keyEvents = function(e) 
{
	var self = e.data;
	if(!e.fromTextCtrl && !theDialogManager.isModalState())
	{
		var c = e.which;
		if((browser.isKonqueror && c == 127) || (c == 46)) {
			// Delete key
			if ($type(self.ondelete) === "function") 
				self.ondelete();
		} else if (e.metaKey) {
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

	const targetId = row.id;
	const rightClick = e.which === 3;
	if (!(rightClick && this.rowSel[targetId])) {
		const toggle = e.metaKey;
		const range = e.shiftKey;
		const oldSel = this.stSel ?? [];
		const anchor = oldSel.length ? oldSel[toggle ? oldSel.length-1 : 0] : null;
		let selection = [];

		if (range && anchor && anchor !== targetId) {
			// range selection
			let behindAnchor = false;
			let behindTarget = false;
			let reverse = false;
			for(let i = 0; i < this.rowIDs.length; i++)
			{
				const id = this.rowIDs[i];
				behindAnchor |= anchor === id;
				behindTarget |= targetId === id;
				reverse |= behindTarget && !behindAnchor;

				if (
					(behindAnchor || behindTarget)
					&& !(toggle && this.rowSel[id])
					&& this.rowdata[id].enabled
				) {
					if (reverse) {
						selection.unshift(id);
					} else {
						selection.push(id);
					}
				}
				if (behindAnchor && behindTarget) {
					break;
				}
			}
		} else {
			selection = [targetId];
		}

		if (toggle) {
			const selSet = new Set(selection);
			// unselect ids if already selected
			selection = oldSel.filter(id => !selSet.has(id)).concat(
				selection.filter(id => !this.rowSel[id])
			);
		}
		const fullSelSet = new Set(selection);
		for (const id in this.rowSel) {
			this.rowSel[id] = fullSelSet.has(id);
		}
		this.selCount = fullSelSet.size;
		this.stSel = selection;
		this.markSelectionDirty();
	}

	if($type(this.onselect) == "function") 
		this.onselect(e, targetId);
	return(false);
}

dxSTable.prototype.setRowById = function(ids, sId, icon, attr)
{
	return (sId in this.rowdata)
		? Boolean(this.setValuesByIds(sId, ids) + this.setIcon(sId, icon) + this.setAttr(sId, attr))
		: this.addRowById(ids, sId, icon, attr);
}

dxSTable.prototype.addRowById = function(ids, sId, icon, attr)
{
	return this.addRow(this.ids.map(id => ids[id] ?? null), sId, icon, attr);
}

dxSTable.prototype.addRow = function(cols, sId, icon, attr)
{
	let validInput = !attr || !('id' in attr) || attr.id === sId;
	validInput &= cols.length === this.cols;
	if (!validInput)
	{
		console.error(`Invalid input to addRow: attr.id: '${attr?.id}' sId: '${sId}' cols: ${cols}`);
		return false;
	}
	this.rowdata[sId] = {"data" : cols, "icon" : icon, "attr" : attr, "enabled" : true, fmtdata: this.format(this,cols.slice(0))};
	this.rowSel[sId] = false;
	this.rowIDs.push(sId);
	
	this.viewRows++;

	this.markViewRowsChange(sId, 1);
	return true;
}

dxSTable.prototype.createIconHTML = function(icon) {
	// TODO: deprecated, remove this method in v6
	noty("Deprecation warning: `dxSTable.createIconHTML()` is deprecated. Please use `dxSTable.createIcon()` instead.");
	return this.createIcon(icon)[0].outerHTML;
}

dxSTable.prototype.createIcon = function(icon) {
	if (!icon) return "";
	const iconObj = $("<span>").addClass("stable-icon");
	if ($type(icon) === "object") {
		return iconObj.css({
			"background-image": `url(${icon.src})`,
			"background-size": "contain",
		});
	} else {
		return iconObj.addClass(icon).css("background-image", "");
	}
}

dxSTable.prototype.createRow = function(cols, sId, icon, attr) {
	const attrs = { id: sId, index: this.rows, title: cols[0] };
	if (sId == null) {
		delete attrs['id'];
	}
	Object.assign(attrs, attr || {});
	const data = this.rowdata[sId]?.fmtdata || {};

	const row = $("<tr>").attr(attrs);
	for (let i = 0; i < this.cols; i++) {
		const index = this.colOrder[i];
		const cdat = this.colsdata[i];
		const td = $("<td>").addClass(`stable-${this.dCont.attr("id")}-col-${index}`).toggle(!!cdat.enabled);
		const celldata = data[index] || '';
		const rawvalue = cols[index] || '';
		const isProgress = cdat.type === TYPE_PROGRESS;
		if (isProgress) {
			td.attr({rawvalue:rawvalue}).append(
				$("<span>").addClass("meter-text").css({overflow:"visible"}).text(celldata),
				$("<div>")
					.addClass("meter-value")
					.css(this.progressStyle(celldata)),
			);
		} else {
			td.append(
				$("<div>").text(celldata || " "),
			);
		}
		row.append(td);
	}
	row.find("td:first-child div").prepend(this.createIcon(icon));
	const ret = row[0];
	const _e = this.tBodyCols;
	for (let i = 0; i < _e.length; i++) {
		ret.cells[i].style.textAlign = this.tHeadCols[i].style.textAlign;
	}
	return ret;
}

dxSTable.prototype.removeRow = function(sId) 
{
	if(!(sId in this.rowdata))
		return;
	if (this.rowdata[sId].enabled)
		this.viewRows--;
	delete this.rowSel[sId];
	delete this.rowdata[sId];
	this.rowIDs.splice(this.rowIDs.indexOf(sId), 1);

	this.markViewRowsChange(sId, 0)
}

dxSTable.prototype.updateRows = function(rawRowObjs)
{
	const rowObjs = Object.fromEntries(
		Object.entries(rawRowObjs).map(
			([i,obj]) => [obj.attr?.id || i, obj])
	);
	for (const sId of Object.keys(this.rowdata))
		if (!(sId in rowObjs))
			this.removeRow(sId);
	for (const [sId, obj] of Object.entries(rowObjs))
		this.setRowById(obj, sId, obj.icon, obj.attr);
}

dxSTable.prototype.clearRows = function() 
{
	this.viewRows = 0;
	this.selCount = 0;
	this.rowdata = {};
	this.rowSel = {};
	this.rowIDs = [];

	delete this.pendingSync.rows;
	delete this.pendingSync.dirty;
	delete this.pendingSync.scroll;
	this.pendingSync.clear = 1;
	this.syncDOMAsync();
}

dxSTable.prototype.setAlignment = function() {
	var i, aAlign, j, align;
	var aAlign = [];
	for (let i = 0; i < this.cols; i++) {
		switch(this.colsdata[i].align) {
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
	var col = this.tBodyCols;
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
					if((rules[i].type == CSSRule.STYLE_RULE) && (rules[i].selectorText == ".stable-" + this.dCont.attr("id") + "-col-" + k))
					{
						this.colRules[k] = rules[i];
						break;
					}
				}
			}
			if($type(this.colRules[k]))
				this.colRules[k].style.textAlign = aAlign[j];
			else
				this.colRules[k] = ss.cssRules[ss.insertRule(".stable-" + this.dCont.attr("id") + "-col-" + k + " div { text-align: " + aAlign[j] + "; }", 0)];
		}
	}
}

dxSTable.prototype.hideRow = function(sId)
{
	if(this.rowdata[sId].enabled)
	{
		this.viewRows--;
		this.rowdata[sId].enabled = false;
		this.markViewRowsChange(sId);
	}
}

dxSTable.prototype.unhideRow = function(sId)
{
	if(!this.rowdata[sId].enabled)
	{
		this.viewRows++;
		this.rowdata[sId].enabled = true;
		this.markViewRowsChange(sId);
	}
}

dxSTable.prototype.refreshSelection = function() {
	if (this.created) {
		var rows = this.tBody[0].rows, l = rows.length;
		for (var i = 0; i < l; i++) {
			if(this.rowSel[rows[i].id] == true) 
				rows[i].classList.add("selected");
			else
				rows[i].classList.remove("selected");
		}
	}
}

dxSTable.prototype.clearSelection = function()
{
	for(var k in this.rowSel)
		this.rowSel[k] = false;
	this.selCount = 0;
	this.stSel = [];
	this.markSelectionDirty();
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
	this.stSel = [];
	for(var k in this.rowSel) 
		if(this.rowdata[k].enabled)
		{
			this.rowSel[k] = true;
			this.stSel.push(k);
		}
	this.selCount = this.stSel.length;
	this.markSelectionDirty();
}

dxSTable.prototype.getColOrder = function(col)
{
	return this.colOrder.indexOf(col);
}


dxSTable.prototype.getColById = function(id)
{
	return this.ids.indexOf(id);
}

dxSTable.prototype.getColNoById = function(id)
{
	return this.getColOrder(this.getColById(id));
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

dxSTable.prototype.getAllEnabledValuesById = function(id)
{
	const col = this.getColById(id);
	return this.rowIDs
		.map(rowId => this.rowdata[rowId])
		.filter(row => row.enabled)
		.map(row => row.data[col]);
}

dxSTable.prototype.getSelected = function()
{
	return this.rowIDs.filter(row => this.rowSel[row]);
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

dxSTable.prototype.setValuesByIds = function(row, rowObj)
{
	return Object.entries(this.ids)
		.filter(([_, propName]) => propName in rowObj)
		.map(([col, propName]) => this.setValue(row, col, rowObj[propName]))
		.some(change => change);
}

dxSTable.prototype.setValueById = function(row, id, val)
{
	return(this.setValue(row, this.getColById(id), val));
}

dxSTable.prototype.progressStyle = function(val) {
  const nval = iv(val);
  return {
    width: `${nval}%`,
    'background-color': new RGBackground()
      .setGradient(this.prgStartColor, this.prgEndColor, parseFloat(val))
      .getColor(),
    visibility: nval ? 'visible' : 'hidden',
  };
}

dxSTable.prototype.setValue = function(row, col, val)
{
	const rdata = this.rowdata[row];
	if((col>=0) && rdata &&
		(typeof val === 'object' || rdata.data[col] !== val))
	{
		rdata.data[col] = val;
		let arr = [];
		arr[col] = val;
		const fmtVal = this.format(this,arr)[col];
		const fmtdata = rdata.fmtdata;
		if(fmtdata[col] != fmtVal)
		{
			fmtdata[col] = fmtVal;
			this.markRowDirty(row, 'col', col);
		}
		return(true);
	}
	return(false);
}

dxSTable.prototype.markRowDirty = function(row, fieldName, mark = 'm')
{
	const dirtyRows = this.pendingSync.dirty ?? {};
	const dirtyRow = dirtyRows[row] ?? {};
	const dirtyField = dirtyRow[fieldName] ?? {};
	dirtyField[mark] = 1;
	dirtyRow[fieldName] = dirtyField;
	dirtyRows[row] = dirtyRow;
	this.pendingSync.dirty = dirtyRows;
	this.syncDOMAsync();
}

dxSTable.prototype.markViewRowsChange = function(sId, needsSort)
{
	this.pendingSync.prows = this.pendingSync.prows ?? {};
	const prev = this.pendingSync.prows[sId] ?? 0;
	this.pendingSync.prows[sId] = needsSort === undefined ? prev : needsSort;
	this.syncDOMAsync();
}

dxSTable.prototype.markSelectionDirty = function()
{
	this.pendingSync.dirtySelection = true;
	this.syncDOMAsync();
}

dxSTable.prototype.syncDOM = function()
{
	if (!this.created || !this.dCont.length)
		return;
	const p = this.pendingSync;
	this.pendingSync = {};
	if (p.clear)
	{
		this.bpad.height(0);
		this.tpad.height(0);
		this.dBody.scrollTop = 0;
		this.tBody.empty();
	} else if ('scrollTo' in p) {
		this.dBody.scrollTop = p.scrollTo;
	}

	const dirtyRows = !p.clear && p.dirty || {};

	for (const [row, marks] of Object.entries(dirtyRows)) {
		const tr = $$(row);
		const dataRow = this.rowdata[row];
		if(tr && dataRow)
		{
			// update attributes
			if ('attrRemove' in marks)
				for (const name of tr.getAttributeNames())
					if (name in marks.attrRemove)
						tr.removeAttribute(name);
			if ('attrSet' in marks)
				for (const [name, attr] of Object.entries(dataRow.attr || {}))
					if (name in marks.attrSet)
						tr.setAttribute(name, attr);

			// update icon
			if ('icon' in marks) {
				const icon = dataRow.icon;
				const td = tr.cells[this.getColOrder(0)];
				if ($(td).find("div span").hasClass("stable-icon"))
					$(td).find("div span").remove();
				if (icon !== null)
					$(td).find("div").prepend(this.createIcon(icon));
			}

			// update cols
			for (const colStr of Object.keys(marks.col || {}))
			{
				const col = Number.parseInt(colStr);
				const c = this.getColOrder(col);
				const td = tr.cells[c];
				if(td)
				{
					const fmtVal = dataRow.fmtdata[col];
					let textEl = td.lastChild;
					if(this.colsdata[c].type==TYPE_PROGRESS)
					{
						$(td).attr('rawvalue', dataRow.data[col])
							.children('.meter-value')
							.css(this.progressStyle(fmtVal));
						textEl = td.firstChild;
					}
					$(textEl).text(fmtVal);
				}
			}
		}
	}

	const pRows = Object.entries(p.prows || {});
	const needsRefresh = pRows.length;
	const sortCols = [this.sortId, this.sortId2]
		.map(id => this.getColById(id))
		.filter(col => col >= 0);
	const needsSort = p.clear || pRows.some(([_, sort]) => sort) || Object.values(dirtyRows)
		.some(marks => ('col' in marks) && sortCols.some(col => col in marks.col));
	const onlyNeedsScroll = p.scroll && !needsSort && !needsRefresh;
	const wantsCustomRefresh = p.resizeHeight || onlyNeedsScroll;
	const sortRefreshed = !this.noSort && needsSort && /* Sort returns 0 on success */ !this.Sort();

	if (wantsCustomRefresh) {
		this.refreshRows(p.resizeHeight, onlyNeedsScroll);
	} else if (needsRefresh && !sortRefreshed) {
		this.refreshRows();
	} else if (p.dirtySelection && !needsRefresh) {
		this.refreshSelection();
	}

	if (p.scroll)
	{
		if (this.isScrolling)
		{
			this.setBodyState("hidden");
			this.scrollPos();
		}
		else
		{
			this.setBodyState("visible");
			this.scp.style.display = "none";
		}
	}
}



dxSTable.prototype.syncDOMAsync = function()
{
	const syncer = this.syncDOMHandlers;
	const th = syncer.throttle;
	const dh = syncer.debounce;
	const stop = (handler) =>
	{
		if (handler.timeoutId !== 0)
		{
			clearTimeout(handler.timeoutId);
			handler.timeoutId = 0;
		}
	};
	const reqAFrame = () =>
	{
		stop(th);
		stop(dh);
		dh.startTime = -1;
		if (syncer.reqAFrameId === 0)
		{
			syncer.reqAFrameId = window.requestAnimationFrame(() => {
				syncer.reqAFrameId = 0;
				this.syncDOM();
			});
		}
	};
	const start = (handler, func, delayMs) =>
	{
		handler.timeoutId = setTimeout(() => {
			handler.timeoutId = 0;
			func();
		}, delayMs);
	};
	if (this.pendingSync.scroll)
	{
		// immediately react to user scroll
		reqAFrame();
	}
	else
	{
		// debounce other DOM updates
		// if not lazy we immediately react to the first event
		if (!syncer.lazy && dh.timeoutId === 0 && th.timeoutId === 0)
			reqAFrame();
		if (dh.timeoutId === 0)
		{
			// note that new Date().getTime() is much faster than clearTimeout()/setTimeout()
			const updateDebounce = () =>
			{
				if (dh.startTime !== -1)
				{
					const remainingMs = dh.delayMs - (dh.startTime - new Date().getTime());
					if (remainingMs > 0)
						start(dh, updateDebounce, remainingMs);
					else
						reqAFrame()
				}
			};
			dh.startTime = new Date().getTime();
			updateDebounce();
		}
		if (th.timeoutId === 0)
		{
			// run throttled DOM update in case debounce takes too long to settle
			start(th, reqAFrame, th.delayMs);
		}
	}
	// update debounce startTime
	if (dh.startTime !== -1)
		dh.startTime = new Date().getTime();
}

dxSTable.prototype.setLazy = function(lazy)
{
	this.syncDOMHandlers.throttle.lazy = Boolean(lazy);
}

dxSTable.prototype.getIcon = function(row)
{
	return(this.rowdata[row].icon);
}

dxSTable.prototype.setIcon = function(row, icon) 
{
	const dataRow = this.rowdata[row];
	const oldIconIsImg = Boolean(dataRow.icon?.src);
	const newIconIsImg = Boolean(icon?.src);
	if(newIconIsImg != oldIconIsImg ||
		(newIconIsImg && dataRow.icon.src !== icon.src) ||
		(!oldIconIsImg && dataRow.icon !== icon))
	{
		dataRow.icon = icon;
		this.markRowDirty(row, 'icon');
		return(true);
	}
	return(false);
}

dxSTable.prototype.setAttr = function(row, attr)
{
	// set attribute of row
	const attrEntries = Object.entries(attr || {})
	const dataRow = this.rowdata[row];
	if(dataRow && attrEntries.some(([name, val]) => dataRow[name] !== val))
	{
		dataRow.attr = dataRow.attr ?? {};
		// attributes are only removed if val === undefined
		for (const name of Object.keys(dataRow.attr))
			if ((name in attr) && attr[name] === undefined)
				delete dataRow.attr[name];
		for (const [name, value] of attrEntries)
		{
			const removed = value === undefined;
			if (!removed)
				dataRow.attr[name] = String(value);
			this.markRowDirty(row, removed ? 'attrRemove' : 'attrSet', name);
		}
		return true;
	}
	return false;
}

dxSTable.prototype.getAttr = function(row, attrName)
{
	return(this.rowdata[row]?.attr ? this.rowdata[row].attr[attrName] : null);
}

dxSTable.prototype.resize = function(w, h) 
{
	this.pendingSync.resizeWidth = w;
	this.pendingSync.resizeHeight = h;
	this.syncDOMAsync();
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
	this.pendingSync.scrollTo = value;
	this.syncDOMAsync();
	return 0;
}

// getter functions
Object.defineProperties(dxSTable.prototype, {
	TR_HEIGHT: {get: function() {return this.tBody.find("tr").height() || 19}},
	rows: {get: function() {return Object.keys(this.rowdata).length;}},
	cols: {get: function() {return this.colsdata.length;}},
	dBody: {get: function() {return this.dCont.find(".stable-body")[0];}},
	tHeadRow: {get: function() {return this.dCont.find("thead tr").first();}},
	tHeadCols: {get: function() {return this.tHeadRow[0].getElementsByTagName("td")}},
	tpad: {get: function() {return this.dCont.find(".stable-virtpad").first().find("tr");}},
	tBody: {get: function() {return this.dCont.find("table tbody:not(.stable-virtpad)");}},
	tBodyCols: {get: function() {return this.dCont.find("colgroup")[0].getElementsByTagName("col")}},
	bpad: {get: function() {return this.dCont.find(".stable-virtpad").last().find("tr");}},
	scp: {get: function() {return this.dCont.find(".stable-scrollpos")[0];}},
	colReszObj: {get: function() {return this.dCont.find(".stable-resize-header");}},
	rowCover: {get: function() {return this.dCont.find(".rowcover");}},
	obj: {get: function() {return this.dCont.find(".stable-move-header");}},
	sepobj: {get: function() {return this.dCont.find(".stable-separator-header")}},
});
