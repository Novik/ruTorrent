plugin.loadMainCSS();
plugin.loadLang();

if(plugin.canChangeOptions())
{
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) 
	{
		if(plugin.enabled)
		{
			var s = '';
			for(var i=0; i<theSearchEngines.sites.length; i++)
			{
				s+=(theSearchEngines.sites[i].name+'|'+theSearchEngines.sites[i].url);
				if(i<theSearchEngines.sites.length-1)
					s+='\r\n';
			}
			$('#searchsites').val( s );
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.sitesWasChanged = function() 
	{
		var arr = $('#searchsites').val().split("\n");
		if(arr.length!=theSearchEngines.sites.length)
			return(true);
		for(var i = 0; i<arr.length; i++)
		{
			var tmp = $.trim(arr[i]);
			tmp = tmp.split("\|",2);
			if((tmp.length<2) ||
			        (tmp[0]!=theSearchEngines.sites[i].name) ||
				(tmp[1]!=theSearchEngines.sites[i].url))
				return(true);
		}
		return(false);
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() 
	{
		plugin.setSettings.call(this);
		if(plugin.enabled && this.sitesWasChanged())
			this.request("?action=setsearch");
	}

	rTorrentStub.prototype.setsearch = function()
	{
		this.content = 'dummy=1';
		var arr = $('#searchsites').val().split("\n");
		for(var i = 0; i<arr.length; i++)
			this.content = 	this.content+"&site="+encodeURIComponent(arr[i]);
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/search/action.php";
		this.dataType = "script";
	}
}

plugin.onLangLoaded = function() 
{
	this.attachPageToOptions($("<div>").attr("id","st_search").html(
		"<fieldset>"+
			"<legend>"+theUILang.searchDesc+"</legend>"+
			"<div class=\"op100l\">"+
				"<textarea id='searchsites'></textarea>"+
			"</div>"+
		"</fieldset>")[0],theUILang.searchName);
}

plugin.onRemove = function()
{
	this.removePageFromOptions("st_search");
}