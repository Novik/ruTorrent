plugin.loadMainCSS();
plugin.loadLang();

if(plugin.canChangeOptions())
{
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) 
	{
	        if(plugin.enabled)
	        {
			$('#dont_private').prop("checked",(theWebUI.retrackers.dontAddPrivate==1));
			$('#add_begin').prop("checked",(theWebUI.retrackers.addToBegin==1));
			var s = '';
			for(var i=0; i<theWebUI.retrackers.list.length; i++)
			{
				var grp = theWebUI.retrackers.list[i];
				if(i>0)
					s+='\r\n';
				for(var j=0; j<grp.length; j++)
				{
					s+=grp[j];
					s+='\r\n';
				}
			}
			$('#eretrackers').val(s);
			s = '';
			for(var i=0; i<theWebUI.retrackers.todelete.length; i++)
			{
				s+=theWebUI.retrackers.todelete[i];
				s+='\r\n';
			}
			$('#dretrackers').val(s);
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
			var s = arr[i].trim();
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
		if(groups.length!=theWebUI.retrackers.list.length)
			return(true);
		for(var i = 0; i<groups.length; i++)	
		{
			if(groups[i].length!=theWebUI.retrackers.list[i].length)
				return(true);
			for(var j = 0; j<groups[i].length; j++)	
				if(groups[i][j]!=theWebUI.retrackers.list[i][j])
					return(true);
		}
		arr = $('#dretrackers').val().split("\n");
		var todelete = [];
		for(var i=0; i<arr.length; i++)
		{
			var s = arr[i].trim();
			if(s.length)
				todelete.push(s);
		}
		if(todelete.length!=theWebUI.retrackers.todelete.length)
			return(true);
		for(var i=0; i<theWebUI.retrackers.todelete.length; i++)
			if(theWebUI.retrackers.todelete[i]!=todelete[i])
				return(true);
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
			var s = arr[i].trim();
			if(s.toLowerCase()!='dht://')
				this.content = 	this.content+"&tracker="+encodeURIComponent(s);
		}
		arr = $('#dretrackers').val().split("\n");
		for(var i = 0; i<arr.length; i++)
		{
			var s = arr[i].trim();
			if(s.length)
				this.content = 	this.content+"&todelete="+encodeURIComponent(s);
		}
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/retrackers/action.php";
		this.dataType = "script";
	}
}

plugin.onLangLoaded = function() {
	if (this.canChangeOptions()) {
		this.attachPageToOptions(
			$("<div>").attr("id","st_retrackers").append(
				$("<fieldset>").append(
					$("<legend>").append(theUILang.retrackers),
					$("<div>").append(
						$("<div>").addClass("row").append(
							$("<div>").addClass("col-12").append(
								$("<input>").attr({type:"checkbox", id:"dont_private"}).prop("checked", true),
								$("<label>").attr({for:"dont_private"}).text(theUILang.dontAddToPrivate),
							),
							$("<div>").addClass("col-12").append(
								$("<input>").attr({type:"checkbox", id:"add_begin"}).prop("checked", true),
								$("<label>").attr({for:"add_begin"}).text(theUILang.addToBegin),
							),
							$("<div>").addClass("col-12").append(
								$("<label>").attr({for:"eretrackers"}).text(theUILang.retrackersAdd),
							),
							$("<div>").addClass("col-12").append(
								$("<textarea>").attr({id:"eretrackers"}).addClass("retrackers"),
							),
							$("<div>").addClass("col-12").append(
								$("<label>").attr({for:"dretrackers"}).text(theUILang.retrackersDel),
							),
							$("<div>").addClass("col-12").append(
								$("<textarea>").attr({id:"dretrackers"}).addClass("retrackers"),
							),
						),
					),
				),
			)[0],
			theUILang.retrackers,
		);
	}
}

plugin.onRemove = function() 
{
	this.removePageFromOptions("st_retrackers");
}