/*
 *  Oblivion skin for ruTorrent
 *  Author: LesBleus
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
			plg.prgStartColor = new RGBackground("#8fbc00");
			plg.prgEndColor = new RGBackground("#d76000");
		}
	});
}

plugin.oldTableCreate = dxSTable.prototype.create;
dxSTable.prototype.create = function(ele, styles, aName)
{
	plugin.oldTableCreate.call(this, ele, styles, aName);
	this.prgStartColor = new RGBackground("#d76000");
	this.prgEndColor = new RGBackground("#8fbc00");
}
