/*
 *      Common UI objects.
 *
 */

// Drag & Drop object 
class DnD {
	constructor(id, options) {
		this.obj = $('#' + id);
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
		header.off("mousedown");
		header.on("mousedown", this, this.start);
	}

	start(e) {
		// allow dnd only for medium-sized screens and up
		if ($(window).width() < 768) return false;
		// disallow dnd on links
		if (e.target.tagName === "A") return false;

		const self = e.data;
		if (self.options.onStart(e)) {
			$(document).on("mousemove", self, self.run);
			$(document).on("mouseup", self, self.finish);
		}
		return false;
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

// Options window
const theOptionsWindow = {
	handlerTypes: {
		// bootstrap list group events
		// ref: https://getbootstrap.com/docs/5.3/components/list-group/#events
		beforeShow: "show.bs.tab",
		afterShow: "shown.bs.tab",
		beforeHide: "hide.bs.tab",
		afterHide: "hidden.bs.tab",
	},
	init: function() {
		// initialize options window with default option pages
		const stgGlCont = $("<div>").attr({id: "st_gl"}).addClass("stg_con").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.User_Interface),
				$("<div>").addClass("row").append(
					...[
						['webui.confirm_when_deleting', theUILang.Confirm_del_torr],
						['webui.alternate_color', theUILang.Alt_list_bckgnd],
						['webui.ignore_timeouts', theUILang.dontShowTimeouts],
						['webui.fullrows', theUILang.fullTableRender],
						['webui.no_delaying_draw', theUILang.showScrollTables],
						['webui.log_autoswitch', theUILang.logAutoSwitch],
						['webui.register_magnet', theUILang.registerMagnet],
						['webui.show_cats', theUILang.Show_cat_start],
						['webui.show_dets', theUILang.Show_det_start],
						['webui.open_tegs.keep', theUILang.KeepSearches],
						['webui.selected_tab.keep', theUILang.KeepSelectedTab],
						['webui.selected_labels.keep', theUILang.KeepSelectedLabels],
						['webui.effects', theUILang.UIEffects],
						['webui.speedintitle', theUILang.showSpeedInTitle],
					].map(([id, label]) => $("<div>").addClass("col-md-6").append(
						$("<input>").attr({type: "checkbox", id: id}),
						$("<label>").attr({for: id}).text(label),
					)),
					...[
						["webui.update_interval", theUILang.Update_GUI_every + ": ", theUILang.ms, 3000],
						["webui.reqtimeout", theUILang.ReqTimeout + ": ", theUILang.ms, 5000],
					].map(([id, prefix, suffix, value]) => [
						$("<div>").addClass("col-md-6").append(
							$('<label>').attr({for: id}).text(prefix),
						),
						$("<div>").addClass("col-9 col-md-5").append(
							$('<input>').attr({type: "number", id: id, value: value, min: 0}).addClass("flex-grow-1"),
						),
						$("<div>").addClass("col-3 col-md-1").append(
							$('<span>').text(suffix),
						),
					]),
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: "webui.speedgraph.max_seconds"}).text(theUILang.speedGraphDuration + ": "),
					),
					$("<div>").addClass("col-md-6").append(
						$("<select>").attr({id: "webui.speedgraph.max_seconds"}).addClass("flex-grow-1").append(
							...Object.entries(theUILang.speedGraphDurationOptions).map(([value, text]) => $("<option>").attr({value: value}).text(text),
						)),
					),
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: "webui.retry_on_error"}).text(theUILang.retryOnErrorTitle + ": "),
					),
					$("<div>").addClass("col-md-6").append(
						$("<select>").attr({id: "webui.retry_on_error"}).append(
							...Object.entries(theUILang.retryOnErrorList).map(
								([index, text]) => $("<option>").val(index).text(text)
							),
						),
					),
					$("<div>").addClass("col-6 col-md-3").append(
						$("<label>").attr({for: "webui.lang"}).text(theUILang.mnu_lang + ": "),
					),
					$("<div>").addClass("col-6 col-md-3").append(
						$("<select>").attr({id: "webui.lang"}).append(
							...Object.entries(AvailableLanguages).map(
								([code, language]) => $("<option>").val(code).text(language)
							),
						),
					),
				),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.speedList),
				$("<div>").addClass("row").append(
					...[
						["webui.speedlistul", theUILang.UL],
						["webui.speedlistdl", theUILang.DL],
					].flatMap(([id, text]) => [
						$("<div>").addClass("col-md-2").append(
							$("<label>").attr({for:id}).text(text + ": "),
						),
						$("<div>").addClass("col-md-10").append(
							$("<input>").attr({type:"text", id:id}).prop("maxlength", 128).addClass("speedEdit"),
						),
					]),
				),
			),
		);
		const stgDlCont = $("<div>").attr({id: "st_dl"}).addClass("stg_con").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Bandwidth_sett),
				...[
					[theUILang.Number_ul_slots, "max_uploads"],
					[theUILang.Number_Peers_min, "min_peers"],
					[theUILang.Number_Peers_max, "max_peers"],
					[theUILang.Number_Peers_For_Seeds_min, "min_peers_seed"],
					[theUILang.Number_Peers_For_Seeds_max, "max_peers_seed"],
					[theUILang.Tracker_Numwant, "tracker_numwant"],
				].map(([label, id]) => $("<div>").addClass("row").append(
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: id}).text(label + ": "),
					),
					$("<div>").addClass("col-md-6").append(
						$("<input>").attr({type: "text", id: id}).prop("maxlength", 6),
					),
				)),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Other_sett),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-6").append(
						$("<input>").attr({type: "checkbox", id: "check_hash"}),
						$("<label>").attr({for: "check_hash"}).text(theUILang.Check_hash),
					),
				),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: "directory"}).text(theUILang.Directory_For_Dl + ": "),
					),
					$("<div>").addClass("col-md-6").append(
						$("<input>").attr({type: "text", id: "directory"}).prop("maxlength", 100),
					),
				),
			),
		);
		const stgConCont = $("<div>").attr({id: "st_con"}).addClass("stg_con").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Listening_Port),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-6").append(
						$("<input>")
							.attr({type: "checkbox", id: "port_open"})
							.on("click", (ev) => linked(ev.target, 0, ['port_range', 'port_random'])),
						$("<label>").attr({for: "port_open"}).text(theUILang.Enable_port_open),
					),
					$("<div>").addClass("col-md-6").append(
						$("<input>").attr({type: "checkbox", id: "port_random"}),
						$("<label>").attr({for: "port_random", id: "lbl_port_random"}).text(theUILang.Rnd_port_torr_start),
					),
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: "port_range", id: "lbl_port_range"}).text(theUILang.Port_f_incom_conns + ": "),
					),
					$("<div>").addClass("col-md-6").append(
						$("<input>").attr({type: "text", id: "port_range"}).prop("maxlength", 13),
					),
				),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Bandwidth_Limiting),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-8").append(
						$("<label>").attr({for: "upload_rate"}).text(`${theUILang.Global_max_upl} (${theUILang.KB}/${theUILang.s}): [0: ${theUILang.unlimited}]`),
					),
					$("<div>").addClass("col-md-4").append(
						$("<input>").attr({type: "text", id: "upload_rate"}).prop("maxlength", 6),
					),
					$("<div>").addClass("col-md-8").append(
						$("<label>").attr({for: "download_rate"}).text(`${theUILang.Glob_max_downl} (${theUILang.KB}/${theUILang.s}): [0: ${theUILang.unlimited}]`),
					),
					$("<div>").addClass("col-md-4").append(
						$("<input>").attr({type: "text", id: "download_rate"}).prop("maxlength", 6),
					),
				),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Other_Limiting),
				...[
					["max_uploads_global", theUILang.Number_ul_slots],
					["max_downloads_global", theUILang.Number_dl_slots],
					["max_memory_usage", theUILang.Glob_max_memory + " (" + theUILang.MB + ")"],
					["max_open_files", theUILang.Glob_max_files],
					["max_open_http", theUILang.Glob_max_http],
				].map(([id, label]) => $("<div>").addClass("row").append(
					$("<div>").addClass("col-md-8").append(
						$("<label>").attr({for: id}).text(label + ": "),
					),
					$("<div>").addClass("col-md-4").append(
						$("<input>").attr({type: "number", id: id}).prop("maxlength", 6),
					),
				)),
			),
		);
		const stgBtCont = $("<div>").attr({id: "st_bt"}).addClass("stg_con").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Add_bittor_featrs),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-6").append(
						$("<input>")
							.attr({type: "checkbox", id: "dht"})
							.on("change", (ev) => linked(ev.target, 0, ['dht_port'])),
						$("<label>").attr({for: "dht"}).text(theUILang.En_DHT_ntw),
					),
					$("<div>").addClass("col-md-6").append(
						$("<input>").attr({type: "checkbox", id: "peer_exchange"}),
						$("<label>").attr({for: "peer_exchange"}).text(theUILang.Peer_exch),
					),
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({id:"lbl_dht_port", for:"dht_port"}).addClass("disabled").text(theUILang.dht_port + ": "),
					),
					$("<div>").addClass("col-md-6").append(
						$("<input>").attr({type:"text", id:"dht_port"}).prop("maxlength", 6).prop("disabled", true),
					),
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({id:"lbl_ip", for:"ip"}).text(theUILang.Ip_report_track + ": "),
					),
					$("<div>").addClass("col-md-6").append(
						$("<input>").attr({type:"text", id:"ip"}).prop("maxlength", 6),
					),
				),
			),
		);
		const stgFmtCont = $("<div>").attr({id: "st_fmt"}).addClass("stg_con").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Format),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: "webui.dateformat"}).addClass("flex-shrink-0").text(theUILang.DateFormat + ": "),
						$("<select>").attr({ id: "webui.dateformat"}).addClass("flex-shrink-1").append(
							...Object.entries({0: "31.12.2011", 1: "2011-12-31", 2: "12/31/2011"}).map(
								([value, text]) => $("<option>").val(value).text(text)
							),
						),
					),
					...[
						['webui.show_viewlabelsize', theUILang.showViewLabelSize],
						['webui.show_statelabelsize', theUILang.showStateLabelSize],
						['webui.show_labelsize', theUILang.showLabelSize],
						['webui.show_searchlabelsize', theUILang.showSearchLabelSize],
						['webui.labelsize_rightalign', theUILang.labelSizeRightAlign],
						['webui.show_label_path_tree', theUILang.showCustomLabelTree],
						['webui.show_empty_path_labels', theUILang.showEmptyPathLabel],
						['webui.show_label_text_overflow', theUILang.showLabelTextOverflow],
						['webui.show_open_status', theUILang.showOpenStatus],
						['webui.show_view_panel', theUILang.showViewPanel],
					].map(([id, label]) => $("<div>").addClass("col-md-6").append(
						$("<input>").attr({type: "checkbox", id: id, checked: "true"}),
						$("<label>").attr({for: id, id: "lbl_" + id}).text(label),
					)),
				),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.DecimalPlacesSizes),
				$("<div>").attr({id:"decimalDigitEdit"}).addClass("row").append(
					$("<div>").addClass("col-12 overflow-x-auto").append(
						$("<table>").append(
							$("<thead>").append(
								$("<tr>").append(
									...[
										"", "Default", "KB", "MB", "GB", "TB", "PB",
									].map((unit) => $("<th>").text(theUILang[unit] ?? "")),
								),
							),
							$("<tbody>").append(
								...Object.entries({
									catlist: theUILang.CatListSizeDecimalPlaces,
									table: theUILang.TableSizeDecimalPlaces,
									details: theUILang.DetailsSizeDecimalPlaces,
									other: theUILang.OtherSizeDecimalPlaces,
								}).map(([context, name]) => $('<tr>').append(
									$("<td>").text(name + ": "),
									...["default", "kb", "mb", "gb", "tb", "pb"].map(unit => $("<td>").append(
										$("<input>").attr({
											type: "number",
											id: "webui.size_decimal_places." + context + "." + unit,
											maxlength: 1,
											min: 0,
										}),
									)),
								)),
							),
						),
					),
				),
			),
		);
		const stgAoCont = $("<div>").attr({id: "st_ao"}).addClass("stg_con").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Advanced),
				$("<div>").attr({id: "st_ao_h"}).append(
					...[
						["hash_interval", 20], ["hash_max_tries", 5], ["hash_read_ahead", 20],
						["http_cacert", 100], ["http_capath", 100],
						["max_downloads_div", 5], ["max_uploads_div", 5], ["max_file_size", 20],
					].map(([id, maxlength]) => $("<div>").addClass("row").append(
						$("<div>").addClass("col-md-6").append(
							$("<label>").attr({for: id}).text(id),
						), 
						$("<div>").addClass("col-md-6").append(
							$("<input>").attr({type: "text", id: id}).prop("maxlength", maxlength),
						),
					)),
					$("<div>").addClass("row").append(
						$("<div>").addClass("col-md-6").append(
							$("<label>").attr({for: "preload_type"}).text("preload_type"),
						),
						$("<div>").addClass("col-md-6").append(
							$("<select>").attr({id: "preload_type"}).append(
								$("<option>").val(0).text("off"),
								$("<option>").val(1).text("madvise"),
								$("<option>").val(2).text("direct paging"),
							),
						),
					),
					...[
						["preload_min_size", 20], ["preload_required_rate", 20],
						["receive_buffer_size", 20], ["send_buffer_size", 20],
						["safe_sync", ], ["timeout_safe_sync", 20], ["timeout_sync", 20], ["scgi_dont_route", ],
						["session", 100], ["session_lock", ], ["session_on_completion",],
						["split_file_size", 20], ["split_suffix", 100], ["use_udp_trackers", ],
						["http_proxy", 100], ["proxy_address", 100], ["bind", 100],
					].map(([id, maxlength]) => $("<div>").addClass("row").append(
						$("<div>").addClass("col-md-6").append(
							$("<label>").attr({for: id}).text(id),
						),
						$("<div>").addClass("col-md-6").append(
							maxlength ?
							$("<input>").attr({type: "text", id: id}).prop("maxlength", maxlength) :
							$("<input>").attr({type: "checkbox", id: id}),
						),
					)),
				),
			),
		);
		const stgDevCont = $("<div>").attr({id: "st_dev"}).addClass("stg_con").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Developers),
				$("<div>").attr({id: "st_dev_h"}).addClass("row").append(
					...[
						['webui.side_panel_min_width', theUILang.sidePanelMinWidth, theUILang.Pixel],
						['webui.side_panel_max_width_percent', theUILang.sidePanelMaxWidthPercent, "%"],
						['webui.list_table_min_height', theUILang.listTableMinHeight, theUILang.Pixel],
					].map(([id, label, unit]) => [
						$("<div>").addClass("col-12 col-md-6").append(
							$("<label>").attr({for:id}).text(label),
						),
						$("<div>").addClass("col-10 col-md-5").append(
							$("<input>").attr({type:"text", id}),
						),
						$("<div>").addClass("col-2 col-md-1").append(
							$("<span>").text(unit),
						),
					]),
				),
			),
		);
		[
			[stgGlCont, theUILang.General],
			[stgDlCont, theUILang.Downloads],
			[stgConCont, theUILang.Connection],
			[stgBtCont, theUILang.BitTorrent],
			[stgFmtCont, theUILang.Format],
			[stgAoCont, theUILang.Advanced],
			[stgDevCont, theUILang.Developers],
		].forEach(([dlg, name]) => this.attachPage(dlg[0], name));
		this.goToPage("st_gl");
	},
	attachPage: function(dlg, name) {
		if ($("#stg_c").find(dlg.id).length) return;
		// append to navigation panel
		$("#stg_c").find(".list-group").append(
			$("<a>")
				.attr({id: `mnu_${dlg.id}`, href: `#${dlg.id}`, "data-bs-toggle":"list", role:"tab", "aria-controls":dlg.id})
				.addClass("list-group-item list-group-item-action")
				.text(name),
		);
		// append to settings page
		$("#stg_c").find("#st_btns").before(
			$(dlg).attr({role:"tabpanel", "aria-labelledby":`mnu_${dlg.id}`}).addClass("stg_con tab-pane fade"),
		);
	},
	removePage: function(id) {
		$("#stg_c").find(`#${id}, #mnu_${id}`).remove();
	},
	getCurrentPage: function() {
		return $("#stg_c .tab-pane.active").attr("id");
	},
	goToPage: function(id) {
		bootstrap.Tab.getOrCreateInstance(`#mnu_${id}`).show();
	},
	addHandler: function(id, type, callback) {
		const event = this.handlerTypes[type];
		if (event) {
			document.getElementById(id).addEventListener(event, callback);
		}
	},
	removeHandler: function(id, type, callback) {
		const event = this.handlerTypes[type];
		if (event) {
			document.getElementById(id).removeEventListener(event, callback);
		}
	},
}
