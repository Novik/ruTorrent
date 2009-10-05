var plugin = new rPlugin("cookies");
plugin.loadMainCSS();
plugin.loadLanguages();

utWebUI.cookiesAddAndShowSettings = utWebUI.addAndShowSettings;
utWebUI.addAndShowSettings = function(arg) 
{
	var s = '';
	for(var i=0; i<hostCookies.length; i++)
	{
		s+=(hostCookies[i].host+'|'+hostCookies[i].cookies);
		if(i<hostCookies.length-1)
			s+='\r\n';
	}
	$$('hostcookies').value = s;
	utWebUI.cookiesAddAndShowSettings(arg);
}

utWebUI.cookiesWasChanged = function() 
{
	var arr = $$('hostcookies').value.split("\n");
	if(arr.length!=hostCookies.length)
	{
		return(true);
	}
	for(var i = 0; i<arr.length; i++)
	{
		var tmp = arr[i].replace(/(^\s+)|(\s+$)/g, "")
		tmp = tmp.split("\|",2);
		if((tmp.length<2) ||
		        (tmp[0]!=hostCookies[i].host) ||
			(tmp[1]!=hostCookies[i].cookies))
			return(true);

	}
	return(false);
}

utWebUI.cookiesSetSettings = utWebUI.setSettings;
utWebUI.setSettings = function() 
{
	this.cookiesSetSettings();
	if(this.cookiesWasChanged())
		this.Request("?action=setcookies");
}

rTorrentStub.prototype.setcookies = function()
{
	this.content = 'dummy=1';
	var arr = $$('hostcookies').value.split("\n");
	for(var i = 0; i<arr.length; i++)
		this.content = 	this.content+"&cookie="+encodeURIComponent(arr[i]);
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/cookies/action.php";
}

rTorrentStub.prototype.setcookiesResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	return(datas[0].childNodes[0].data);
}

utWebUI.cookiesCreate = function() 
{
	var dlg = document.createElement("DIV");
	dlg.className = "stg_con";
	dlg.id = "st_cookies";
	dlg.innerHTML = 
		"<fieldset>"+
			"<legend>"+WUILang.cookiesDesc+"</legend>"+
			"<div class=\"op100l\">"+
				"<textarea id='hostcookies'></textarea>"+
			"</div>"+
		"</fieldset>";
	plugin.attachPageToOptions(dlg,WUILang.cookiesName);
}
