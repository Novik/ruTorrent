plugin.loadLang();
if(theWebUI.theme)
{
	plugin.path = "plugins/theme/themes/"+theWebUI.theme+"/";
	plugin.loadCSS("style", () => $(':root').removeClass('pre-theme-load'));
	plugin.loadCSS("stable");

	plugin.allDone = function() {
		plugin.loadCSS("plugins");
	}

	plugin.config = theWebUI.config;
	theWebUI.config = function()
	{
		plugin.loadCSS("plugins");
		plugin.config.call(this);
		thePlugins.waitLoad( "thePlugins.get('theme').allDone" );
	}
}

plugin.onLangLoaded = function()
{
	var themes = '<option value="" '+(theWebUI.theme ? '' : 'selected')+'>'+theUILang.themeStandard+'</option>';
	for( var i in plugin.themes )
		themes += '<option value="'+plugin.themes[i]+'"'+(theWebUI.theme==plugin.themes[i] ? 'selected' : '')+'>'+plugin.themes[i]+'</option>';
	$($$("webui.lang")).parent().after(
		$("<div>").addClass("col-6 col-md-3").append(
			$("<label>").attr({for: "webui.theme"}).text(theUILang.theme + ": "),
		),
		$("<div>").addClass("col-6 col-md-3").append(
			$("<select>").attr({id: "webui.theme"}).html(themes),
		),
	);
}

plugin.updateThemeHint = function(theme)
{
	const dark = !['Blue', 'Excel', ''].includes(theme);
	setThemeHint(dark);
}
plugin.updateThemeHint(theWebUI.theme);

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
	const themeVal = $($$("webui.theme")).val();
	if(themeVal !== theWebUI.theme)
	{
		theWebUI.request("?action=settheme", () => {
			plugin.updateThemeHint(themeVal);
			theWebUI.reload();
		});
	}
}
