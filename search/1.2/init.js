var plugin = new rPlugin("search");
plugin.loadMainCSS();
plugin.loadLanguages();

utWebUI.searchAddAndShowSettings = utWebUI.addAndShowSettings;
utWebUI.addAndShowSettings = function(arg) 
{
	var s = '';
	for(var i=0; i<searchSities.length; i++)
	{
		s+=(searchSities[i].name+'|'+searchSities[i].url);
		if(i<searchSities.length-1)
			s+='\r\n';
	}
	$$('searchsites').value = s;
	utWebUI.searchAddAndShowSettings(arg);
}

utWebUI.sitesWasChanged = function() 
{
	var arr = $$('searchsites').value.split("\n");
	if(arr.length!=searchSities.length)
	{
		return(true);
	}
	for(var i = 0; i<arr.length; i++)
	{
		var tmp = arr[i].replace(/(^\s+)|(\s+$)/g, "")
		tmp = tmp.split("\|",2);
		if((tmp.length<2) ||
		        (tmp[0]!=searchSities[i].name) ||
			(tmp[1]!=searchSities[i].url))
			return(true);

	}
	return(false);
}

utWebUI.sitesSetSettings = utWebUI.setSettings;
utWebUI.setSettings = function() 
{
	this.sitesSetSettings();
	if(this.sitesWasChanged())
		this.Request("?action=setsearch");
}

rTorrentStub.prototype.setsearch = function()
{
	this.content = 'dummy=1';
	var arr = $$('searchsites').value.split("\n");
	for(var i = 0; i<arr.length; i++)
		this.content = 	this.content+"&site="+encodeURIComponent(arr[i]);
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/search/action.php";
}

rTorrentStub.prototype.setsearchResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	return(datas[0].childNodes[0].data);
}

utWebUI.searchCreate = function() 
{
	var dlg = document.createElement("DIV");
	dlg.className = "stg_con";
	dlg.id = "st_search";
	dlg.innerHTML = 
		"<fieldset>"+
			"<legend>"+WUILang.searchDesc+"</legend>"+
			"<div class=\"op100l\">"+
				"<textarea id='searchsites'></textarea>"+
			"</div>"+
		"</fieldset>";
	plugin.attachPageToOptions(dlg,WUILang.searchName);
}
