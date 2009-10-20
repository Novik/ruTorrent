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
	fname = fname + "?time=" + (new Date()).getTime();
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

function rPlugin( name )
{
	this.name = name;
	this.path = "plugins/"+name+"/";
}

rPlugin.prototype.loadLanguages = function(initFunc) 
{
	injectScript(this.path+"lang/"+GetActiveLanguage()+".js",initFunc);
}

rPlugin.prototype.loadCSS = function(name)
{
	injectCSS(this.path+name+".css");
}

rPlugin.prototype.loadMainCSS = function()
{
	this.loadCSS(this.name);
}

rPlugin.prototype.attachPageToOptions = function(dlg,name)
{
	$$("stg_c").insertBefore(dlg,$$("st_ao"));
	var el = $$("mnu_st_ao");
	var ul = null;
	var li = null;
	while(el)
	{
		el = el.parentNode;
		if(el.tagName)
		{
			var s = el.tagName.toLowerCase();
			if(s=="ul")
			{
				ul = el;
				break;
			}
			else
			if(s=="li")
				li = el;
		}
	}
	if(ul && li)
	{
		el = document.createElement("LI");
		el.innerHTML = "<a id='mnu_"+dlg.id+"' href=\"javascript:ch_mnu('"+dlg.id+"');\">"+name+"</a>";
		ul.insertBefore(el,li);
	}
}

rPlugin.prototype.attachPageToTabs = function(dlg,name,idBefore)
{
	dlg.className = "tab";
	tdTabs.tabs[dlg.id] = name; 
	var newLbl = document.createElement("li");
	newLbl.id = "tab_"+dlg.id;
	newLbl.innerHTML = "<a href=\"javascript://utwebui/\" onmousedown=\"javascript:tdTabs.show('"+dlg.id+"');\" onfocus=\"javascript:this.blur();\">" + name + "</a>";
 	var beforeTab = idBefore ? $$(idBefore) : null;
 	if(beforeTab)
 	{
		beforeTab.parentNode.insertBefore(dlg,beforeTab);
		var beforeLbl = $$("tab_"+beforeTab);
		beforeLbl.parentNode.insertBefore(newLbl,beforeLbl);
	}
	else
	{
		beforeTab = $$("lcont");
		beforeTab.parentNode.appendChild(dlg);
		var beforeLbl = $$("tab_lcont");
		beforeLbl.parentNode.appendChild(newLbl);
	}
}

rPlugin.prototype.addButtonToToolbar = function(id,name,href,idBefore)
{
	var newBtn = document.createElement("A");
	newBtn.id="mnu_"+id;
	newBtn.href=href;
	newBtn.title=name;
	newBtn.innerHTML='<div id="'+id+'"></div>';
	var targetBtn = idBefore ? $$("mnu_"+idBefore) : null;	
	if(targetBtn)
		targetBtn.parentNode.insertBefore(newBtn,targetBtn);	
	else
	{
		targetBtn = $$("mnu_remove");
		targetBtn.parentNode.appendChild(newBtn);
	}
	return(newBtn);
}
	
rPlugin.prototype.addSeparatorToToolbar = function(idBefore)	
{
        var targetBtn = idBefore ? $$("mnu_"+idBefore) : null;
	var sep = document.createElement("DIV");
	sep.className = "TB_Separator";
	if(targetBtn)
		targetBtn.parentNode.insertBefore(sep,targetBtn);	
	else
	{
	        targetBtn = $$("mnu_remove");
		targetBtn.parentNode.appendChild(sep);
	}
	return(sep);
}

function getCSSRule( selectorText )
{
	function getRulesArray(i)
	{
		var crossrule = null;
		try {
		if(document.styleSheets[i].cssRules)
			crossrule=document.styleSheets[i].cssRules;
		else 
			if(document.styleSheets[i].rules)
				crossrule=document.styleSheets[i].rules;
		} catch(e) {}
		return(crossrule);
	}

	selectorText = selectorText.toLowerCase()
	var ret = null;
	for( var j=document.styleSheets.length-1; j>=0; j-- )
	{
		var rules = getRulesArray(j);
		for( var i=0; rules && i<rules.length; i++ )
		{
			if(rules[i].selectorText && rules[i].selectorText.toLowerCase()==selectorText)
			{
				ret = rules[i];
				break;
			}			
		}
	}
	return(ret);
}