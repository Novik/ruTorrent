var plugin = new rPlugin("retrackers");
plugin.loadMainCSS();
plugin.loadLanguages();

utWebUI.allRetrackersStuffLoaded = false;

utWebUI.oldAddAndShowSettings = utWebUI.addAndShowSettings;
utWebUI.addAndShowSettings = function(arg) 
{
	$$('dont_private').checked = (utWebUI.retrackers.dontAddPrivate==1);
	var s = '';
	for(var i=0; i<utWebUI.retrackers.trackers.length; i++)
	{
		var grp = utWebUI.retrackers.trackers[i];
		if(i>0)
			s+='\r\n';
		for(var j=0; j<grp.length; j++)
		{
			s+=grp[j];
			s+='\r\n';
		}
	}
	$$('eretrackers').value = s;
	utWebUI.oldAddAndShowSettings(arg);
}

utWebUI.retrackersWasChanged = function() 
{
	if($$('dont_private').checked!=(utWebUI.retrackers.dontAddPrivate==1))
		return(true);
	var arr = $$('eretrackers').value.split("\n");
	var curGroup = new Array();
        var groups = new Array();
	for(var i = 0; i<arr.length; i++)
	{
		var s = arr[i].replace(/(^\s+)|(\s+$)/g, "");
		if(s.length)
			curGroup.push(s);
		else
			if(curGroup.length)
			{
				groups.push(curGroup);
				curGroup = new Array();
			}
	}
	if(curGroup.length)
		groups.push(curGroup);
	if(groups.length!=utWebUI.retrackers.trackers.length)
		return(true);
	for(var i = 0; i<groups.length; i++)	
	{
		if(groups[i].length!=utWebUI.retrackers.trackers[i].length)
			return(true);
		for(var j = 0; j<groups[i].length; j++)	
			if(groups[i][j]!=utWebUI.retrackers.trackers[i][j])
				return(true);
	}
	return(false);
}

utWebUI.retrackersSetSettings = utWebUI.setSettings;
utWebUI.setSettings = function() 
{
	this.retrackersSetSettings();
	if(this.retrackersWasChanged())
		this.Request("?action=setretrackers");
}

rTorrentStub.prototype.setretrackers = function()
{
	this.content = 'dont_private='+($$('dont_private').checked ? '1' : '0');
	var arr = $$('eretrackers').value.split("\n");
	for(var i = 0; i<arr.length; i++)
	{
		var s = arr[i].replace(/(^\s+)|(\s+$)/g, "");
		if(s.toLowerCase()!='dht://')
			this.content = 	this.content+"&tracker="+encodeURIComponent(s);
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/retrackers/action.php";
}

rTorrentStub.prototype.setretrackersResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	return(datas[0].childNodes[0].data);
}

utWebUI.retrackersCreate = function() 
{
	var dlg = document.createElement("DIV");
	dlg.className = "stg_con";
	dlg.id = "st_retrackers";
	dlg.innerHTML = 
		"<fieldset>"+
			"<legend>"+WUILang.retrackers+"</legend>"+
			"<div class=\"op100l\">"+
				"<textarea id='eretrackers'></textarea>"+
			"</div>"+
			"<div class=\"op100l\">"+		
				"<input type='checkbox' id='dont_private' checked='true' />"+
				"<label for='dont_private'>"+WUILang.dontAddToPrivate+"</label>"+
			"</div>"+
		"</fieldset>";
	plugin.attachPageToOptions(dlg,WUILang.retrackers);
	utWebUI.allRetrackersStuffLoaded = true;
}

utWebUI.showRetrackersError = function(err)
{
	if(utWebUI.allRetrackersStuffLoaded)
		log(err);
	else
		setTimeout('utWebUI.showRetrackersError('+err+')',1000);
}