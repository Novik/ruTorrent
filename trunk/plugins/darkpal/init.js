plugin.loadCSS("style");
plugin.loadCSS("stable");

plugin.config = theWebUI.config;
theWebUI.config = function(data)
{
	plugin.config.call(this,data);
	$.each(this.tables, function(ndx,table)
	{
		table.obj.setPaletteByURL("plugins/darkpal");
	});
	plugin.loadCSS("plugins");
}


