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

function injectCSS(fname) 
{
	var newSS=document.createElement('link'); 
	newSS.rel='stylesheet'; 
	newSS.href=fname; 
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
	} catch(e) {}			// konqueror hack
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

rPlugin.prototype.loadCSS = function(name)
{
	injectCSS(this.path+name+".css");
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
		$(".lm ul li:last").removeClass("last");
		$(".lm ul").append( $("<li>").attr("id","hld_"+dlg.id).addClass("last").html("<a id='mnu_"+dlg.id+"' href=\"javascript://void()\" onclick=\"theOptionsSwitcher.run('"+dlg.id+"'); return(false);\">"+name+"</a>") );
		$(dlg).css( {display: "none"} );
	}
	return(this);
}

rPlugin.prototype.removePageFromOptions = function(id)
{
	if(theOptionsSwitcher.current==id)
		theOptionsSwitcher.run('st_gl');
	$("#"+id).remove();
	$("#hld_"+id).remove();
	$(".lm ul li:last").addClass("last");
	return(this);
}

rPlugin.prototype.attachPageToTabs = function(dlg,name,idBefore)
{
        if(this.canChangeTabs())
        {
                if(!dlg.className)
			dlg.className = "tab";
		theTabs.tabs[dlg.id] = name; 
		var newLbl = document.createElement("li");
		newLbl.id = "tab_"+dlg.id;
		newLbl.innerHTML = "<a href=\"javascript://void();\" onmousedown=\"theTabs.show('"+dlg.id+"');\" onfocus=\"this.blur();\">" + name + "</a>";
		if(!idBefore)
			idBefore = "lcont";
		$$(idBefore).parentNode.insertBefore(dlg,$$(idBefore));
		var beforeLbl = $$("tab_"+idBefore);
		beforeLbl.parentNode.insertBefore(newLbl,beforeLbl);
		theTabs.show("lcont");
	}
	return(this);
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
        if(this.canChangeToolbar())
        {
        	if( !$$("mnu_plugins") )
        		this.addButtonToToolbar("plugins",theUILang.Plugins+"...","theWebUI.showPluginsMenu()","help");
		thePlugins.registerTopMenu( this, weight );
	}
	return(this);
}

rPlugin.prototype.addButtonToToolbar = function(id,name,onclick,idBefore)
{
        if(this.canChangeToolbar())
        {
		var newBtn = document.createElement("A");
		newBtn.id="mnu_"+id;
		newBtn.href='javascript://void();';
		newBtn.title=name;
		newBtn.innerHTML='<div class="top-menu-item" id="'+id+'" onclick="'+onclick+';return(false);"></div>';
		$(newBtn).addClass('top-menu-item').focus( function(e) { this.blur(); } );
		var targetBtn = idBefore ? $$("mnu_"+idBefore) : null;	
		if(targetBtn)
			targetBtn.parentNode.insertBefore(newBtn,targetBtn);	
		else
		{
			targetBtn = $$("mnu_settings");
			targetBtn.parentNode.appendChild(newBtn);
		}
	}
	return(this);
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

rPlugin.prototype.addPaneToStatusbar = function(id,div,no)
{
        if(this.canChangeStatusBar())
        {
		var row = $("#firstStatusRow").get(0);
		var td = row.insertCell(iv(no));
		$(td).attr("id",id).addClass("statuscell").append( $(div) );
	}
	return(this);
}

rPlugin.prototype.removePaneFromStatusbar = function(id)
{
	$("#"+id).remove();
	return(this);
}

rPlugin.prototype.addPaneToCategory = function(id,name)
{
        if(this.canChangeCategory())
        {
		$('#CatList').append(
			$("<div>").addClass("catpanel").attr("id",id).text(name).click(function() { theWebUI.togglePanel(this); })).
				append($("<div>").attr("id",id+"_cont").addClass("catpanel_cont"));
		theWebUI.showPanel($$(id),!theWebUI.settings["webui.closed_panels"][id]);
	}
	return($("#"+id+"_cont"));
}

rPlugin.prototype.removePaneFromCategory = function(id)
{
	$("#"+id).remove();
	$("#"+id+"_cont").remove();
	return(this);
}