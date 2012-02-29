plugin.loadMainCSS();
plugin.loadLang();

if(plugin.canChangeOptions())
{
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) 
	{
	        if(plugin.enabled)
	        {
			$('#dont_private').attr("checked",(theWebUI.retrackers.dontAddPrivate==1));
			$('#add_begin').attr("checked",(theWebUI.retrackers.addToBegin==1));
			var s = '';
			for(var i=0; i<theWebUI.retrackers.trackers.length; i++)
			{
				var grp = theWebUI.retrackers.trackers[i];
				if(i>0)
					s+='\r\n';
				for(var j=0; j<grp.length; j++)
				{
					s+=grp[j];
					s+='\r\n';
				}
			}
			$('#eretrackers').val(s);
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.retrackersWasChanged = function() 
	{
		if(($$('dont_private').checked!=(theWebUI.retrackers.dontAddPrivate==1)) ||
			($$('add_begin').checked!=(theWebUI.retrackers.addToBegin==1)))
			return(true);
		var arr = $('#eretrackers').val().split("\n");
		var curGroup = new Array();
	        var groups = new Array();
		for(var i = 0; i<arr.length; i++)
		{
			var s = $.trim(arr[i]);
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
		if(groups.length!=theWebUI.retrackers.trackers.length)
			return(true);
		for(var i = 0; i<groups.length; i++)	
		{
			if(groups[i].length!=theWebUI.retrackers.trackers[i].length)
				return(true);
			for(var j = 0; j<groups[i].length; j++)	
				if(groups[i][j]!=theWebUI.retrackers.trackers[i][j])
					return(true);
		}
		return(false);
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() 
	{
		plugin.setSettings.call(this);
		if(plugin.enabled && this.retrackersWasChanged())
			this.request("?action=setretrackers");
	}

	rTorrentStub.prototype.setretrackers = function()
	{
		this.content = 'dont_private='+($$('dont_private').checked ? '1' : '0') + 
			'&add_begin='+($$('add_begin').checked ? '1' : '0');
		var arr = $('#eretrackers').val().split("\n");
		for(var i = 0; i<arr.length; i++)
		{
			var s = $.trim(arr[i]);
			if(s.toLowerCase()!='dht://')
				this.content = 	this.content+"&tracker="+encodeURIComponent(s);
		}
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/retrackers/action.php";
		this.dataType = "script";
	}
}

plugin.onLangLoaded = function() 
{
	if(this.canChangeOptions())
	        this.attachPageToOptions(
		        $("<div>").attr("id","st_retrackers").html(
				"<fieldset>"+
					"<legend>"+theUILang.retrackers+"</legend>"+
					"<div class=\"op100l\">"+
						"<textarea id='eretrackers'></textarea>"+
					"</div>"+
					"<div class=\"op100l\">"+		
						"<input type='checkbox' id='dont_private' checked='true' />"+
						"<label for='dont_private'>"+theUILang.dontAddToPrivate+"</label>"+
					"</div>"+
					"<div class=\"op100l\">"+		
						"<input type='checkbox' id='add_begin' checked='false' />"+
						"<label for='add_begin'>"+theUILang.addToBegin+"</label>"+
					"</div>"+
				"</fieldset>")[0],theUILang.retrackers);
}

plugin.onRemove = function() 
{
	this.removePageFromOptions("st_retrackers");
}