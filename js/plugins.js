// this function is obsolete

function injectScript(fname,initFunc)
{
	var h = document.getElementsByTagName("head").item(0);
	s = document.createElement("script");
	if(initFunc)
	{
		if(browser.isIE)
			s.onreadystatechange = function()
			{
				if((this.readyState == 'loaded') || (this.readyState == 'complete'))
					initFunc();
			}
		else
			s.onload = initFunc;
	}
	if(s.setAttribute)
		s.setAttribute('src', fname);
	else
		s.src = fname;
	s.type = "text/javascript";
	void (h.appendChild(s));
}

function injectCSS(fname, onLoadFunc)
{
	var newSS=document.createElement('link');
	newSS.rel='stylesheet';
	newSS.href=fname;
	newSS.onload = onLoadFunc;
	var h = document.getElementsByTagName("head").item(0);
	void (h.appendChild(newSS));
}

function injectCSSText(text)
{
	var style=document.createElement('style');
	style.setAttribute("type", "text/css");
	if(style.styleSheet)
		style.styleSheet.cssText = text;
	else
		style.appendChild(document.createTextNode(text));
	var h = document.getElementsByTagName("head").item(0);
	void (h.appendChild(style));
}

var thePlugins =
{
	list: {},
	topMenu: [],
	restictions:
	{
		cantChangeToolbar: 	0x0001,
		cantChangeMenu:		0x0002,
		cantChangeOptions:	0x0004,
		cantChangeTabs:		0x0008,
		cantChangeColumns:	0x0010,
		cantChangeStatusBar:	0x0020,
		cantChangeCategory:	0x0040,
		cantShutdown:		0x0080,
		canBeLaunched:		0x0100
	},

	register: function(plg)
	{
		this.list[plg.name] = plg;
	},

	get: function(name)
	{
	        return(this.list[name]);
	},

	isInstalled: function(name,version)
	{
	        var plg = this.list[name];
	        return(plg && plg.enabled && (!version || (plg.version>=version)) ? true : false);
	},

	checkLoad: function()
	{
		for( var i in this.list )
		{
			var plg = this.list[i];
			if(plg.enabled && ($type(plg["onLangLoaded"])=="function") && !plg.allStuffLoaded)
				return(false);
		}
		return(true);
	},

	waitLoad: function( callback )
	{
		if(this.checkLoad())
			eval( callback+'()' );
		else
			window.setTimeout( 'thePlugins.waitLoad("'+callback+'")', 500 );
	},

	registerTopMenu: function( plg, weight )
	{
		this.topMenu.push( { "name": plg.name, "weight": weight } );
		this.topMenu.sort( function(a,b) { return(a.weight-b.weight); } );
	}

};

function rPlugin( name, version, author, descr, restictions, help )
{
	this.name = name;
	this.path = "plugins/"+name+"/";
	this.version = (version==null) ? 1.0 : version;
	this.descr = (descr==null) ? "" : descr;
	this.author = (author==null) ? "unknown" : author;
	this.allStuffLoaded = false;
	this.enabled = true;
	this.launched = true;
	this.restictions = restictions;
	this.help = help;
	thePlugins.register(this);
}

rPlugin.prototype.markLoaded = function()
{
	this.allStuffLoaded = true;
}

rPlugin.prototype.enable = function()
{
	this.enabled = true;
	return(this);
}

rPlugin.prototype.disable = function()
{
	this.enabled = false;
	return(this);
}

rPlugin.prototype.launch = function()
{
	this.launched = true;
	return(this);
}

rPlugin.prototype.unlaunch = function()
{
	this.launched = false;
	return(this);
}

rPlugin.prototype.remove = function()
{
	if($type(this["onRemove"])=="function")
		this.onRemove();
	this.disable();
	return(this);
}

rPlugin.prototype.showError = function(err)
{
	if( this.allStuffLoaded )
		noty( eval(err), "error" );
	else
		setTimeout( 'thePlugins.get("'+this.name+'").showError("' + err + '")', 1000 );
}

rPlugin.prototype.langLoaded = function()
{
	try {
	if(($type(this["onLangLoaded"])=="function") && this.enabled)
		this.onLangLoaded();
	} catch(e) {
		console.warn(`Plugin "${this.name}" failed to load:`, e);
	} // konqueror hack
	this.markLoaded();
}

rPlugin.prototype.loadLangPrim = function(lang,template,sendNotify)
{
	var self = this;
	$.ajax(
	{
		url: template.replace('{lang}',lang), // this is because plugin.path may be changed during call
		dataType: "script",
		cache: true
	}).done( function()
	{
		!sendNotify || self.langLoaded();
	}).fail( function()
	{
		(lang=='en') ?
			(!window.console || console.error( "Plugin '"+self.name+"': localization for '"+lang+"' not found." )) :
			self.loadLangPrim('en',template,sendNotify);
	});
}

rPlugin.prototype.loadLang = function(sendNotify)
{
	this.loadLangPrim(GetActiveLanguage(),this.path+"lang/{lang}.js",sendNotify);
	return(this);
}

rPlugin.prototype.loadCSS = function(name, onLoadFunc)
{
	injectCSS(this.path+name+".css", onLoadFunc);
	return(this);
}

rPlugin.prototype.loadMainCSS = function()
{
	this.loadCSS(this.name);
	return(this);
}

rPlugin.prototype.canChangeMenu = function()
{
	return(!(this.restictions & thePlugins.restictions.cantChangeMenu));
}

rPlugin.prototype.canChangeOptions = function()
{
	return(!(this.restictions & thePlugins.restictions.cantChangeOptions));
}

rPlugin.prototype.canChangeToolbar = function()
{
	return(!(this.restictions & thePlugins.restictions.cantChangeToolbar));
}

rPlugin.prototype.canChangeTabs = function()
{
	return(!(this.restictions & thePlugins.restictions.cantChangeTabs));
}

rPlugin.prototype.canChangeColumns = function()
{
	return(!(this.restictions & thePlugins.restictions.cantChangeColumns));
}

rPlugin.prototype.canChangeStatusBar = function()
{
	return(!(this.restictions & thePlugins.restictions.cantChangeStatusBar));
}

rPlugin.prototype.canChangeCategory = function()
{
	return(!(this.restictions & thePlugins.restictions.cantChangeCategory));
}

rPlugin.prototype.canShutdown = function()
{
	return(!(this.restictions & thePlugins.restictions.cantShutdown));
}

rPlugin.prototype.canBeLaunched = function()
{
	return(this.restictions & thePlugins.restictions.canBeLaunched);
}

rPlugin.prototype.attachPageToOptions = function(dlg,name)
{
	if(this.canChangeOptions())
	{
		$("#st_btns").before( $(dlg).addClass("stg_con") );
		$(".lm ul").append(
			$("<li>").attr("id","hld_"+dlg.id).append(
				$("<div>").append(
					$("<div>"),
					$("<div>"),
				),
				$("<a>").attr(
					{id: `mnu_${dlg.id}`, href: "#", onclick: `theOptionsSwitcher.run('${dlg.id}');return(false);`}
				).text(name),
			),
		);
	}
	theOptionsSwitcher.items[dlg.id] = {afterShow: null, afterHide: null,};
	return(this);
}

rPlugin.prototype.removePageFromOptions = function(id) {
	if (theOptionsSwitcher.current === id)
		theOptionsSwitcher.run('st_gl');
	$("#" + id).remove();
	$("#hld_" + id).remove();
	return this;
}

rPlugin.prototype.attachPageToTabs = function(dlg, name, idBefore) {
	if (this.canChangeTabs()) {
		if(!dlg.className)
			dlg.className = "tab";
		theTabs.tabs[dlg.id] = name;
		const newLabel = $("<li>").attr({id:"tab_"+dlg.id}).addClass("nav-item").append(
			$("<a>").attr({href:"#"}).addClass("nav-link").text(name).on("click", () => theTabs.show(dlg.id)).on("focus", (ev) => ev.target.blur()),
		);
		if(!idBefore)
			idBefore = "lcont";
		if(theWebUI.activeView === dlg.id) {
			$('#tdcont').children().hide();
			$(dlg).show();
		} else {
			$(dlg).hide();
		}
		$$(idBefore).parentNode.insertBefore(dlg,$$(idBefore));
		$("#tab_"+idBefore).before(newLabel);
		if (theWebUI.activeView === dlg.id) {
			setTimeout(() => theTabs.show(dlg.id));
		}
	}
	return this;
}

rPlugin.prototype.renameTab = function(id,name)
{
        if(this.canChangeTabs())
        {
		theTabs.tabs[id] = name;
		$("#tab_"+id+" a").text(name);
	}
	return(this);
}

rPlugin.prototype.removePageFromTabs = function(id)
{
	delete theTabs.tabs[id];
	$('#'+id).remove();
	$('#tab_'+id).remove();
	return(this);
}

rPlugin.prototype.registerTopMenu = function(weight)
{
	if (this.canChangeToolbar()) {
		if (!$$("mnu_plugins"))
			this.addButtonToToolbar("plugins", theUILang.Plugins, "theWebUI.showPluginsMenu()", "help");
		thePlugins.registerTopMenu(this, weight);
	}
	return this;
}

rPlugin.prototype.addButtonToToolbar = function(id, name, onclick, idBefore) {
	if (this.canChangeToolbar()) {
		const newBtn = $("<a>").attr(
			{id:`mnu_${id}`, href:"#", onclick:onclick, onfocus:"this.blur();", title:`${name}...`}
		).addClass("nav-link top-menu-item").append(
			$("<div>").attr({id:id}).addClass("nav-icon"),
			$("<span>").addClass("d-inline d-md-none").text(`${name}...`),
		);
		const beforeBtn = $(`#mnu_${idBefore}`);
		beforeBtn && beforeBtn.length > 0 ? newBtn.insertBefore(beforeBtn) : newBtn.insertBefore($("#mnu_settings"));
	}
	return this;
}

rPlugin.prototype.removeButtonFromToolbar = function(id)
{
	$("#mnu_"+id).remove();
}

rPlugin.prototype.addSeparatorToToolbar = function(idBefore)
{
        if(this.canChangeToolbar())
        {
	        var targetBtn = idBefore ? $$("mnu_"+idBefore) : null;
		var sep = document.createElement("DIV");
		sep.className = "TB_Separator";
		if(targetBtn)
			targetBtn.parentNode.insertBefore(sep,targetBtn);
		else
		{
	        	targetBtn = $$("mnu_settings");
			targetBtn.parentNode.appendChild(sep);
		}
	}
	return(this);
}

rPlugin.prototype.removeSeparatorFromToolbar = function(idBefore)
{
	$("#mnu_"+idBefore).prev().remove();
}

/**
 * Add a pane (status cell) to the status bar.
 * @param {string} id HTML `id` of the status cell to be added.
 * @param {object | HTMLElement | string} statusCell The content of the status cell. 
 * Can be a jQuery object, an HTML element, or an HTML string.
 * @param {number} no Number of position where the status cell will be placed.
 * The status cell will be inserted before the `no`th existing cell, if `no` is positive
 * **AND** no more than the count of current cells. Otherwise, it will be inserted to the
 * last of the cell list, before the server time cell.
 * @param {boolean} mobileVisible Whether the status cell shall be displayed on mobile,
 * default is `false`.
 * @returns {rPlugin}
 */
rPlugin.prototype.addPaneToStatusbar = function(id, statusCell, no, mobileVisible) {
	if (this.canChangeStatusBar()) {
		statusCell = $(statusCell)
			.attr({id: id})
			.addClass("status-cell").addClass(mobileVisible ? "" : "d-none d-md-flex");
		no = iv(no);
		const countCurrCells = $("#StatusBar div.status-cell").length;
		if (!countCurrCells || no > countCurrCells || no <= 0) {
			no = countCurrCells;
		}
		$(`#StatusBar div.status-cell:nth-child(${no})`).before(statusCell);
	}
	return this;
}

rPlugin.prototype.removePaneFromStatusbar = function(id)
{
	$("#"+id).remove();
	return(this);
}

rPlugin.prototype.addPaneToCategory = function(id,name, labelAttribs, statisticInit)
{
	if(this.canChangeCategory())
	{
		theWebUI.categoryList.addPanel(id, name, labelAttribs, statisticInit);
	}
	return($($$(id)));
}

rPlugin.prototype.removePaneFromCategory = function(id)
{
	theWebUI.categoryList.removePanel(id);
	return(this);
}
