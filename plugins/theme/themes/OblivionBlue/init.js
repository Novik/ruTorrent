/*
 *  OblivionBlue skin for ruTorrent
 *  Oblivion Author: LesBleus
 *  Updated for ruTorrent 5.x: Abzie
 */

plugin.oblivionAllDone = plugin.allDone;
plugin.allDone = function()
{
	plugin.oblivionAllDone.call(this);
	$.each(["diskspace","quotaspace","cpuload"], function(ndx,name)
	{
		var plg = thePlugins.get(name);
		if(plg && plg.enabled)
		{
			plg.prgStartColor = new RGBackground("#6678AB");
			plg.prgEndColor = new RGBackground("#6678AB");
		}
	});
}

plugin.oldTableCreate = dxSTable.prototype.create;
dxSTable.prototype.create = function(ele, styles, aName)
{
	plugin.oldTableCreate.call(this, ele, styles, aName);
	this.prgStartColor = new RGBackground("#d76000");
	this.prgEndColor = new RGBackground("#4A7ED5");
}
