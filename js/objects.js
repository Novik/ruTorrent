/*
 *      Common UI objects.
 *
 */

// Drag & Drop object 
class DnD {
	/**
	 * Construct a drag-and-drop instance.
	 * @param {string | HTMLElement} dndObj Reference to the drag-and-drop element.
	 * Can be the HTML `id` of the element, or a reference to the HTML element.
	 * @param {Object} options Drag and drop options to be passed.
	 */
	constructor(dndObj, options) {
		this.obj = ($type(dndObj) === "string")
			? $('#' + dndObj)
			: $(dndObj);
		const headers = this.obj.find(".dlg-header");
		const header = headers.length > 0 ? $(headers[0]) : this.obj;
		this.options = options || {};
		if (!this.options.left)
			this.options.left = 0;
		if (!this.options.top)
			this.options.top = 0;
		if (!this.options.right)
			this.options.right = (() => $(window).width())();
		if (!this.options.bottom)
			this.options.bottom = (() => $(window).height())();
		if (!this.options.onStart)
			this.options.onStart = function() {return true;};
		if (!this.options.onRun)
			this.options.onRun = function() {};
		if (!this.options.onFinish)
			this.options.onFinish = function() {};
		this.options.allowMobile = !!this.options.allowMobile;
		header.off("mousedown");
		header.on("mousedown", this, this.start);
	}

	start(e) {
		const self = e.data;
		// allow dnd on small-sized screens only when explicitly allowed to
		if (($(window).width() < 768) && !self.options.allowMobile){
			return false;
		}
		// disallow dnd on links
		if (e.target.tagName === "A") return false;

		if (self.options.onStart(e)) {
			$(document).on("mousemove", self, self.run);
			$(document).on("mouseup", self, self.finish);
		}
	}

	run(e) {
		$("body").css({cursor:"grabbing"});
		// `e.data` refers to the DnD object attached to the current dialog header
		const self = e.data;

		const offs = self.obj.offset();
		if (!self.options.restrictX) {
			self.obj.css({
				left: Math.min(
					Math.max(self.options.left, offs.left + e.originalEvent.movementX),
					self.options.right,
				),
			});
		}
		if (!self.options.restrictY) {
			self.obj.css({
				top: Math.min(
					Math.max(self.options.top, offs.top + e.originalEvent.movementY),
					self.options.bottom,
				),
			});
		}
		self.options.onRun(e);
		return false;
	}

	finish(e) {
		$("body").css({cursor:""});
		const self = e.data;
		self.options.onFinish(e);
		$(document).off("mousemove", self.run);
		$(document).off("mouseup", self.finish);
		return false;
	}
}

// Dialog manager
var theDialogManager = {
	maxZ: 2000,
	visible : [],
	items : {},
	divider: 0,
	modalState: false,

	/**
	 * Create a new dialog window.
	 * @param {string} id The HTML `id` of the dialog window.
	 * @param {string} name The title shown in the header of the dialog window.
	 * @param {string | Object | Object[]} content Content of the dialog window,
	 * including the buttons in the footer. Can be an HTML string, a jQuery object,
	 * or an array of jQuery objects.
	 * @param {boolean} isModal The modal state of the dialog window, default is `false`.
	 * @param {boolean} noClose Whether to disable the close button, default is `false`.
	 * @returns {Object}
	 */
	make: function(id, name, content, isModal, noClose) {
		if ($type(content) === "string") {
			content = $(content);
		}
		$("#dialog-container").append(
			$("<div>").attr("id",id).addClass("dlg-window").append(
				$("<div>").addClass("dlg-header").append(
					$("<div>").attr({id:`${id}-header`}).text(name),
					$("<a>").attr({href:"#"}).addClass("dlg-close"),
				),
				content,
			).hide(),
		);
		return this.add(id, isModal, noClose);
	},
	add: function(id, isModal, noClose) {
		var obj = $('#'+id);
		if (!isModal)
			isModal = false;
		obj.data({
			modal: isModal,
			nokeyclose: noClose,
		});
		if (!noClose)
			// prevent dragging on close button
			obj.find(".dlg-close").on("click", () => theDialogManager.hide(id));
		var self = this;
		var checkForButtons = function me(val) {
			if(val.hasClass("Cancel"))
				val.on('click', function() { theDialogManager.hide(id); } );
			if(val.hasClass("Button"))
				$(val).on('focus', function() { this.blur(); } );
			val.children().each( function(ndx,val) { me($(val)) } );
		};
		checkForButtons(obj);
		var inScrollBarArea = function(obj,x,y) {
			if(obj.tagName && (/^input|textarea$/i).test(obj.tagName))
				return(false);
			var c = $(obj).offset();
			return((obj.scrollHeight > obj.clientHeight) && (x>obj.clientWidth+c.left));
		};
		obj.on('mousedown', function(e) 
		{
			if( (!browser.isOpera || !inScrollBarArea(e.target,e.clientX,e.clientY)) && !theDialogManager.modalState )
				self.bringToTop(this.id);
		}).attr("tabindex","0").on('keypress', function (e)
		{
			if((e.keyCode==13) && !(e.target && e.target.tagName && (/^textarea$/i).test(e.target.tagName)) && !$('#'+id+' .OK').prop('disabled'))
				$('#'+id+' .OK').trigger('click');
		});

//		this.center(id);
		this.items[id] = { beforeShow: null, afterShow: null, beforeHide: null, afterHide : null };
		obj.data("dnd",new DnD(id));
		return(this);
	},
	center: function( id )
	{
	        var obj = $('#'+id);
		obj.css( { left: Math.max(($(window).width()-obj.width())/2,0), top: Math.max(($(window).height()-obj.height())/2,0) } );
	},
	toggle: function( id )
	{
		var pos = $.inArray(id+"",this.visible);
		if(pos>=0)
			this.hide(id);
		else
			this.show(id);
	},
	setEffects: function( divider )
	{
		this.divider = divider;
	},
	setModalState: function()
	{
        	$('#modalbg').show();
        	this.bringToTop('modalbg');
		this.modalState = true;
	},
	clearModalState: function()
	{
       		$('#modalbg').hide();
		this.modalState = false;
	},
	show: function(id, callback) {
		if ($(window).width() < 768) {
			// Close side panel on mobile:
			// An offcanvas is a modal under the hood and will intercept focus events
			// from other elements, and make other text inputs unfocusable and uneditable.
			bootstrap.Offcanvas.getInstance("#offcanvas-sidepanel")?.hide();
			// close collapsible top menu on opening dialog windows
			bootstrap.Collapse.getInstance("#top-menu")?.hide();
		}

		const obj = $('#' + id);
		if (obj.data("modal"))
			this.setModalState();
		if ($type(this.items[id]) && ($type(this.items[id].beforeShow)=="function"))
			this.items[id].beforeShow(id);
		this.center(id);
		obj.show(obj.data("modal") ? null : this.divider, callback);
		if ($type(this.items[id]) && ($type(this.items[id].afterShow)=="function"))
			this.items[id].afterShow(id);
		this.bringToTop(id);
	},
	hide: function(id, callback) {
		const pos = $.inArray(id + "", this.visible);
		if (pos >= 0)
			this.visible.splice(pos, 1);
		const obj = $('#'+id);
		if ($type(this.items[id]) && ($type(this.items[id].beforeHide) === "function"))
			this.items[id].beforeHide(id);
		obj.hide(this.divider, callback);
		if ($type(this.items[id]) && ($type(this.items[id].afterHide) === "function"))
			this.items[id].afterHide(id);
		if (obj.data("modal"))
			this.clearModalState();
	},
	setHandler: function(id, type, handler) {
		if ($type(this.items[id]))
			this.items[id][type] = handler;
		return this;
	},
	addHandler: function(id, type, handler) {
		if ($type(this.items[id])) {
			const existing = this.items[id][type];
			if (existing) {
				this.items[id][type] = function() {
					existing();
					handler();
				};
			} else {
				this.items[id][type] = handler;
			}
		}
		return this;
	},
	isModalState: function()
	{
		return(this.modalState);
	},
	bringToTop: function( id )
	{
		if($type(this.items[id]))
		{
			var pos = $.inArray(id+"",this.visible);
			if(pos>=0)
			{
				if(pos==this.visible.length-1)
					return;
				this.visible.splice(pos,1);
			}
			this.visible.push(id);
		}
		$('#'+id).css("z-index",++theDialogManager.maxZ);
		if(!browser.isOpera)
			$('#'+id).trigger('focus');
	},
	hideTopmost: function()
	{
		if(this.visible.length && !$('#'+this.visible[this.visible.length-1]).data("nokeyclose"))
		{
			this.hide(this.visible.pop());
			return(true);
		}
		return(false);
	}
};

// Context menu
var CMENU_SEP =	" 0";
var CMENU_CHILD = " 1";
var CMENU_SEL = " 2";

var theContextMenu = 
{
	mouse: { x: 0, y: 0 },
	noHide: false,

	init: function() {
		var self = this;
		$(document).on('mousemove', function(e) { self.mouse.x = e.clientX; self.mouse.y = e.clientY; } );
		$(document).on('mouseup', function(e) {
			const ele = $(e.target);
			if (e.which === 3) {
				if (!e.fromTextCtrl)
					e.stopPropagation();
			} else {
				if(!ele.hasClass("top-menu-item") && !ele.parent().hasClass("top-menu-item") &&
					!ele.hasClass("exp") &&
					!ele.hasClass("CMenu") &&
					!(ele.hasClass("menu-cmd") && ele.hasClass("dis")) &&
					!ele.hasClass("menuitem") &&
					!ele.hasClass("menu-line"))
				{
					if (ele.hasClass("menu-cmd") && self.noHide)
						ele.toggleClass("sel");
					else
						window.setTimeout("theContextMenu.hide()", 50); 
				}
			}
		});
		this.obj = $("<ul>").addClass("CMenu").hide();
		$(document.body).append(this.obj);
	},
	get: function( label )
	{
	        var ret = null;
		$("a",this.obj).each( function(ndx,val) 
		{ 
			if($(val).text()==label)
			{
				ret = $(val).parent();
				return(false);
			}
		});
		return(ret);
	},
	add: function() {
		var args = new Array();
		$.each(arguments, function(ndx,val) { args.push(val); });
        	var aft = null;
		if(($type(args[0]) == "object") && args[0].hasClass && args[0].hasClass("CMenu")) 
		{
			var o = args[0];
			args.splice(0, 1);
		}
		else 
			var o = this.obj;
		if(($type(args[0]) == "object") && args[0].hasClass && args[0].hasClass("menuitem")) 
		{
			aft = args[0];
			args.splice(0, 1);		
		}
		var self = this;
		$.each(args, function(ndx,val) {
			if ($type(val)) {
				const li = $("<li>").addClass("menuitem");
				if (val[0] == CMENU_SEP)
					li.append($("<hr>").addClass("menu-line"));
				else if(val[0] == CMENU_CHILD) {
					li.append( $("<a>").addClass("exp").text(val[1]) );
					var ul = $("<ul>").addClass("CMenu").hide();
					for (var j = 0, len = val[2].length; j < len; j++) {
						self.add(ul, val[2][j]);
					}
					li.append(ul);
				} else if(val[0] == CMENU_SEL) {
					const a = $("<a>").addClass("sel menu-cmd").attr({href: "#"}).text(val[1]);
					switch ($type(val[2])) {
						case "string": {
							a.on('click', () => eval(val[2]));
							break;
						}
						case "function": {
							a.on('click', val[2]);
							break;
						}
						default: {
							return;
						}
					}
					li.append(
						a.on('focus', (ev) => ev.target.blur()),
					);
				} else {
					if ($type(val[0])) {
						const a = $("<a>").addClass("menu-cmd").text(val[0]);
						switch ($type(val[1])) {
							case false: {
								a.addClass("dis");
								break;
							}
							case "string": {
								a.attr({href:"#"}).on('click', () => eval(val[1]));
								break;
							}
							case "function": {
								a.attr({href:"#"}).on('click', val[1]);
								break;
							}
						}
						li.append(
							a.on('focus', (ev) => ev.target.blur()),
						);
					}
				}
				aft ? aft.after(li) : o.append(li);
			}
		});
	},
	clear: function()
	{
		this.obj.empty();
	},
	setNoHide: function()
	{
		this.noHide = true;
	},
	openSubmenu: function(ev) {
		const li = $(ev.currentTarget);
		const submenu = li.children("ul");
		if (submenu.length) {
			submenu.show().css({left:li.width()});
			if(submenu.offset().left + submenu.width() > $(window).width())
				submenu.css( "left", -submenu.width() );
			if(submenu.offset().top + submenu.height() > $(window).height())
				submenu.css( "top", -submenu.height()+20 );
			if(submenu.offset().top<0)
				submenu.css( "top", -submenu.height()+20-submenu.offset().top );
			if ($(window).height() < submenu.offset().top + submenu.height())
				submenu.css( { "max-height": $(window).height() - submenu.offset().top, overflow: "visible scroll" } );
		}
	},
	closeSubmenu: function(ev) {
		const submenu = $(ev.currentTarget).children("ul");
		if (submenu.length)
			submenu.css( { "max-height": "", overflow: "visible" } ).hide();
	},
	show: function(x,y)
	{
		var obj = this.obj;
		if(x==null)
			x = this.mouse.x;
		if(y==null)
			y = this.mouse.y;
		if(x + obj.width() > $(window).width()) 
			x -= obj.width();
		if(y + this.obj.height() > $(window).height()) 
			y -= obj.height();
		if(y<0)
			y = 0;
		obj.css( { left: x, top: y, "z-index": ++theDialogManager.maxZ } );
		obj.children("li").on({
			mouseover: theContextMenu.openSubmenu,
			mouseout: theContextMenu.closeSubmenu,
		});
		obj.show(theDialogManager.divider, function() { obj.css( { overflow: "visible" } ); } );
	},
	hide: function()
	{
		this.noHide = false;
	        if(this.obj.is(":visible"))
	        {
			this.obj.hide(theDialogManager.divider);
			this.clear();
			return(true);
		}
		return(false);
	}
}
