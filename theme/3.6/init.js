plugin.loadLang();
if(theWebUI.theme)
{
	plugin.path = "plugins/theme/themes/"+theWebUI.theme+"/";
	plugin.loadCSS("style");
	plugin.loadCSS("stable");

	plugin.allDone = function()
	{
		plugin.loadCSS("plugins");
		$.each(theWebUI.tables, function(ndx,table)
		{
			table.obj.setPaletteByURL("plugins/theme/themes/"+theWebUI.theme);
		});
	}

	plugin.config = theWebUI.config;
	theWebUI.config = function(data)
	{
		this.getTable("trt").setPaletteByURL("plugins/theme/themes/"+theWebUI.theme);
		plugin.loadCSS("plugins");
		plugin.config.call(this,data);
		thePlugins.waitLoad( "thePlugins.get('theme').allDone" );
	}
}

plugin.onLangLoaded = function()
{
	var themes = '<option value="" '+(theWebUI.theme ? '' : 'selected')+'>'+theUILang.themeStandard+'</option>';
	for( var i in plugin.themes )
		themes += '<option value="'+plugin.themes[i]+'"'+(theWebUI.theme==plugin.themes[i] ? 'selected' : '')+'>'+plugin.themes[i]+'</option>';
	$($$("webui.lang")).parent().after(
		'<div class="op50l algnright">'+
		'<label for="webui.theme">'+theUILang.theme+':</label>'+
		'<select id="webui.theme">'+themes+'</select></div>' );
}

rTorrentStub.prototype.settheme = function()
{
	this.content = "theme="+$($$("webui.theme")).val();
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/theme/action.php";
	this.dataType = "script";
}

theDialogManager.setHandler('stg','beforeShow',function()
{
	$($$("webui.theme")).val(theWebUI.theme);
});

plugin.setSettings = theWebUI.setSettings;
theWebUI.setSettings = function() 
{
	plugin.setSettings.call(this);
	if($($$("webui.theme")).val()!=theWebUI.theme)
		theWebUI.request("?action=settheme",[theWebUI.reload, theWebUI]);
}
