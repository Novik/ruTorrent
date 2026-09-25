/*
 *  Material Design theme for ruTorrent
 *  Based on "Oblivion" theme, by LesBleus
 *  Icons from "small-n-flat" pack by paomedia ( https://github.com/paomedia/small-n-flat )
 *  Author: Phlo
 */ 

TR_HEIGHT = 22

plugin.materialdesignAllDone = plugin.allDone;
plugin.allDone = function()
{
	plugin.materialdesignAllDone.call(this);
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
