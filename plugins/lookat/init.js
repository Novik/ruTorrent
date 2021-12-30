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
			for(var i in plugin.lookData)
				s+=(i+'|'+plugin.lookData[i]+'\r\n');
			$('#lookat').val(s.trim());
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	theWebUI.lookatWasChanged = function() 
	{
		var arr = $('#lookat').val().split("\n");
		var j = 0;
		for(var i in plugin.lookData)
		{
			if(j>=arr.length)
				return(true);
			if( i+'|'+plugin.lookData[i] != arr[j++].trim() )
				return(true);
		}
		return(j!=arr.length);
	}

	plugin.setlookat = function(data)
	{
		plugin.lookData = data;
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() 
	{
		plugin.setSettings.call(this);
		if(plugin.enabled && this.lookatWasChanged())
			this.request("?action=setlookat",plugin.setlookat);
	}

	rTorrentStub.prototype.setlookat = function()
	{
		this.content = 'dummy=1';
		var arr = $('#lookat').val().split("\n");
		for(var i = 0; i<arr.length; i++)
			this.content = 	this.content+"&look="+encodeURIComponent(arr[i]);
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/lookat/action.php";
		this.dataType = "json";
	}
}

if(plugin.canChangeMenu())
{
	theWebUI.lookAt = function( no )
	{
		if( $type( plugin.lookData[no] ) )
		{
			var table = this.getTable("trt");
			var hash = table.getFirstSelected()
			var d = this.torrents[hash];
			if($type(d.name))
			{
				var title = d.name;
				var patt = new RegExp( plugin.partsToRemove, 'gi' );
				title = title.replace( patt, '' );
				var url = plugin.lookData[no].replace( '{title}', encodeURIComponent(title).replace(/(%20|_|\.|\[|\])/g,'+') );
				window.open(url, "_blank");
			}
		}
	}

	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled && plugin.allStuffLoaded)
		{
			var table = this.getTable("trt");
			var el = theContextMenu.get( theUILang.Properties );
			if( el )
			{
				var _c0 = [];
				for(var i in plugin.lookData)
					_c0.push( [i, (this.getTable("trt").selCount==1) && this.isTorrentCommandEnabled("lookat",id) ? "theWebUI.lookAt('"+addslashes(i)+"')" : null] );
				theContextMenu.add( el, [CMENU_CHILD, theUILang.lookAt, _c0] );
                        }
		}
	}
}

plugin.onLangLoaded = function() 
{
	this.attachPageToOptions($('<div>').attr("id","st_lookat").html(
		"<fieldset>"+
			"<legend>"+theUILang.lookAtDesc+"</legend>"+
			"<div class=\"op100l\">"+
				"<textarea id='lookat'></textarea>"+
			"</div>"+
		"</fieldset>")[0],theUILang.lookAt);
}

plugin.onRemove = function()
{
	this.removePageFromOptions("st_lookat");
}