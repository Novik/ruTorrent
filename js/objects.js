/*
 *      Common UI objects.
 *
 */

// Drag & Drop object 
function DnD( id, options )
{
	this.obj = $('#'+id);
	var headers = this.obj.find(".dlg-header");
	var header = headers.length>0 ? $(headers[0]) : this.obj;
	this.options = options || {};
	if(!this.options.left)
		this.options.left = function() { return(0); };
	if(!this.options.top)
		this.options.top = function() { return(0); };
	if(!this.options.right)
		this.options.right = function() { return($(window).width()); };
	if(!this.options.bottom)
		this.options.bottom = function() { return($(window).height()); };
	if(!this.options.onStart)
		this.options.onStart = function() { return(true); }
	if(!this.options.onRun)
		this.options.onRun = function() {}
	if(!this.options.onFinish)
		this.options.onFinish = function() {}
	if(!this.options.maskId)
		this.options.maskId = 'dragmask';
	this.detachedMask = (this.options.maskId!=id);
	this.mask = $('#'+this.options.maskId);
	header.off( "mousedown" );
	header.on( "mousedown", this, this.start );
}

DnD.prototype.start = function( e )
{
	var self = e.data;
	if(self.options.onStart(e))
	{
		var offs = self.obj.offset();
		theDialogManager.bringToTop(self.obj.attr("id"))
		theDialogManager.bringToTop(self.mask.attr("id"))
		if(self.detachedMask)
		{
			self.mask.css( { left: offs.left, top: offs.top, width: self.obj.width(), height: self.obj.height() } );
			self.mask.show();
		}
		self.delta = { x: e.clientX-offs.left, y: e.clientY-offs.top };
		$(document).on("mousemove",self,self.run); 
		$(document).on("mouseup",self,self.finish);
	}	
	return(false);
}

DnD.prototype.run = function( e )
{
	var self = e.data;
	if(!self.options.restrictX)
		self.mask.css( { left: Math.min(Math.max(self.options.left(), e.clientX),self.options.right())-self.delta.x } ); 
	if(!self.options.restrictY)
		self.mask.css( { top: Math.min(Math.max(self.options.top(), e.clientY),self.options.bottom())-self.delta.y } );
	self.options.onRun(e);
	return(false);
}

DnD.prototype.finish = function( e )
{
	var self = e.data;
	self.options.onFinish(e);
	if(self.detachedMask)
	{
		var offs = self.mask.offset();
		self.mask.hide();
		self.obj.css( { left: offs.left, top: offs.top } );
	}
	$(document).off("mousemove",self.run); 
	$(document).off("mouseup",self.finish);
	return(false);
}

// Dialog manager
var theDialogManager = 
{
	maxZ: 2000,
	visible : [],
	items : {},
	divider: 0,
	modalState: false,

	make: function( id, name, content, isModal, noClose )
	{
		$(document.body).append($("<div>").attr("id",id).addClass("dlg-window").html(content).
			prepend( $("<div>").attr("id",id+"-header").addClass("dlg-header").text(name) ).
			prepend( $("<a></a>").addClass("dlg-close") ));
		return(this.add(id,isModal,noClose));
	},
        add: function( id, isModal, noClose )
	{
	        var obj = $('#'+id);
	        if(!isModal)
		        isModal = false;
		obj.css( { position: "absolute", display: "none", outline: "0px solid transparent" } ).
	        	data("modal",isModal).data("nokeyclose",noClose);
	        if(!noClose)
		        obj.find(".dlg-close").attr("href","javascript:theDialogManager.hide('"+id+"');");
	        var self = this;
		var checkForButtons = function me(val)
		{
			if(val.hasClass("Cancel"))
				val.on('click', function() { theDialogManager.hide(id); } );
			if(val.hasClass("Button"))
				$(val).on('focus', function() { this.blur(); } );
			val.children().each( function(ndx,val) { me($(val)) } );
		};
		checkForButtons(obj);
		var inScrollBarArea = function(obj,x,y)
		{
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
	show: function( id, callback )
	{
	        var obj = $('#'+id);
	        if(obj.data("modal"))
			this.setModalState();
	      	if($type(this.items[id]) && ($type(this.items[id].beforeShow)=="function"))
	        	this.items[id].beforeShow(id);
		this.center(id);
		obj.show(obj.data("modal") ? null : this.divider,callback); 
        	if($type(this.items[id]) && ($type(this.items[id].afterShow)=="function"))
	        	this.items[id].afterShow(id);
		this.bringToTop(id);
	},
	hide: function( id, callback )
	{
		var pos = $.inArray(id+"",this.visible);
		if(pos>=0)
			this.visible.splice(pos,1);
        	var obj = $('#'+id);
        	if($type(this.items[id]) && ($type(this.items[id].beforeHide)=="function"))
	        	this.items[id].beforeHide(id);
		obj.hide(this.divider,callback);
        	if($type(this.items[id]) && ($type(this.items[id].afterHide)=="function"))
	        	this.items[id].afterHide(id);
		if(obj.data("modal"))
			this.clearModalState();
	},
	setHandler: function(id,type,handler)
	{
		if($type(this.items[id]))
	        	this.items[id][type] = handler;
		return(this);
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

        init: function()
	{
		var self = this;
		$(document).on('mousemove', function(e) { self.mouse.x = e.clientX; self.mouse.y = e.clientY; } );
		$(document).on('mouseup', function(e)
		{
			var ele = $(e.target);
			if(e.which == 3) 
			{
				if(!e.fromTextCtrl)
					e.stopPropagation();
			}
			else
			{
				if(!ele.hasClass("top-menu-item") &&
					!ele.hasClass("exp") &&
					!ele.hasClass("CMenu") &&
					!(ele.hasClass("menu-cmd") && ele.hasClass("dis")) &&
					!ele.hasClass("menuitem") &&
					!ele.hasClass("menu-line"))
				{
					if(ele.hasClass("menu-cmd") && self.noHide)
						ele.toggleClass("sel");
					else
		   				window.setTimeout("theContextMenu.hide()", 50); 
				}
			}
		});
                this.obj = $("<UL>").css( { position: "absolute" } ).addClass("CMenu");
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
	add: function()
	{
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
		$.each(args, function(ndx,val) 
		{
		        if($type(val))
			{
				var li = $("<li>").addClass("menuitem");
				if(val[0] == CMENU_SEP)
					li.append($("<hr>").addClass("menu-line"));
				else
				if(val[0] == CMENU_CHILD)
				{
					li.append( $("<a></a>").addClass("exp").text(val[1]) );
					var ul = $("<ul>").addClass("CMenu");
					for(var j = 0, len = val[2].length; j < len; j++) 
					{
						self.add(ul, val[2][j]);
					}
					li.append(ul);
				}
				else
			       	if(val[0] == CMENU_SEL) 
		 		{
		 	        	var a = $("<a></a>").addClass("sel menu-cmd").text(val[1]);
			 	        switch($type(val[2]))
			 	        {
						case "string": a.attr("href","javascript://void();").on('click', function() { eval(val[2]) } ); break;
						case "function": a.attr("href","javascript://void();").on('click', val[2]); break;
					}
					li.append(a.on('focus', function() { this.blur(); } ));
				}
				else
				{
					if($type(val[0]))
					{
						var a = $("<a></a>").addClass("menu-cmd").text(val[0]);
						switch($type(val[1]))
						{
				 	        	case false: a.addClass("dis"); break;
							case "string": a.attr("href","javascript://void();").on('click', function() { eval(val[1]) } ); break;
							case "function": a.attr("href","javascript://void();").on('click', val[1]); break;
						}
						li.append(a.on('focus', function() { this.blur(); } ));
					}
				}
				if(aft)
					aft.after(li);
				else
					o.append(li);
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
		obj.children("li").hover( function() {
			var submenu = $(this).children("ul");
			if (submenu.length) {
				if(submenu.offset().left + submenu.width() > $(window).width())
					submenu.css( "left", -150 );
				if(submenu.offset().top + submenu.height() > $(window).height())
					submenu.css( "top", -submenu.height()+20 );
				if(submenu.offset().top<0)
					submenu.css( "top", -submenu.height()+20-submenu.offset().top );
				if ($(window).height() < submenu.offset().top + submenu.height())
					submenu.css( { "padding-right": 12, "max-height": $(window).height() - submenu.offset().top, overflow: "visible scroll" } );
			}
		}, function () {
			var submenu = $(this).children("ul");
			if (submenu.length)
				submenu.css( { "padding-right": 0, "max-height": "none", overflow: "visible" } );
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

