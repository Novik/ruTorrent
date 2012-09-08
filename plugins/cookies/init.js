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
			for(var i=0; i<hostCookies.length; i++)
			{
				s+=(hostCookies[i].host+'|'+hostCookies[i].cookies);
				if(i<hostCookies.length-1)
					s+='\r\n';
			}
			$('#hostcookies').val(s);
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.cookiesWasChanged = function() 
	{
		var arr = $('#hostcookies').val().split("\n");
		if(arr.length!=hostCookies.length)
		{
			return(true);
		}
		for(var i = 0; i<arr.length; i++)
		{
			var tmp = $.trim(arr[i]);
			tmp = tmp.split("\|",2);
			if((tmp.length<2) ||
			        (tmp[0]!=hostCookies[i].host) ||
				(tmp[1]!=hostCookies[i].cookies))
				return(true);
		}
		return(false);
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() 
	{
		plugin.setSettings.call(this);
		if(plugin.enabled && this.cookiesWasChanged())
			this.request("?action=setcookies");
	}

	rTorrentStub.prototype.setcookies = function()
	{
		this.content = 'dummy=1';
		var arr = $('#hostcookies').val().split("\n");
		for(var i = 0; i<arr.length; i++)
			this.content = 	this.content+"&cookie="+encodeURIComponent(arr[i]);
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/cookies/action.php";
		this.dataType = "script";
	}
}

plugin.onLangLoaded = function() 
{
	this.attachPageToOptions($('<div>').attr("id","st_cookies").html(
		"<fieldset>"+
			"<legend>"+theUILang.cookiesDesc+"</legend>"+
			"<div class=\"op100l\">"+
				"<textarea id='hostcookies'></textarea>"+
			"</div>"+
		"</fieldset>")[0],theUILang.cookiesName);
}

plugin.onRemove = function()
{
	this.removePageFromOptions("st_cookies");
}