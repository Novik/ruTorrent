var plugin = new rPlugin("seedingtime");
plugin.loadLanguages();

utWebUI.seedingColumnNo = utWebUI.trtColumns.length;
utWebUI.seedingNo = 27;
utWebUI.seedingTimeSupported = true;
utWebUI.allSeedingStuffLoaded = false;

function formatSeedingTime(s)
{
	return( s.length ? formatDate(s) : "");
}

utWebUI.seedingfillAdditionalTorrentsCols = utWebUI.fillAdditionalTorrentsCols;
utWebUI.fillAdditionalTorrentsCols = function(id,row)
{
	row = utWebUI.seedingfillAdditionalTorrentsCols(id,row);
	if(utWebUI.seedingTimeSupported)
		row[utWebUI.seedingColumnNo] = this.torrents[id][utWebUI.seedingNo];
	return(row);
}

utWebUI.seedingupdateAdditionalTorrentsCols = utWebUI.updateAdditionalTorrentsCols;
utWebUI.updateAdditionalTorrentsCols = function(id)
{
	utWebUI.seedingupdateAdditionalTorrentsCols(id);
	if(utWebUI.seedingTimeSupported && this.trtTable.setValue(id, utWebUI.seedingColumnNo, this.torrents[id][utWebUI.seedingNo]))
		this.noUpdate = false;
}

rTorrentStub.prototype.seedinglist = rTorrentStub.prototype.list;
rTorrentStub.prototype.list = function()
{
	this.seedinglist();
	if(utWebUI.seedingTimeSupported)
	{
		if(utWebUI.allSeedingStuffLoaded)
			utWebUI.seedingNo = this.commands[this.commands.length-1].params.length-7;
		this.commands[this.commands.length-1].addParameter("string","d.get_custom=seedingtime");
	}
}

rTorrentStub.prototype.seedinggetAdditionalResponseForListItem = rTorrentStub.prototype.getAdditionalResponseForListItem;
rTorrentStub.prototype.getAdditionalResponseForListItem = function(values)
{
	var ret = this.seedinggetAdditionalResponseForListItem(values);
	if(utWebUI.seedingTimeSupported)
	{
		var seedingTime = this.getValue(values,utWebUI.seedingNo+6);
		seedingTime = parseInt(seedingTime.replace(/(^\s+)|(\s+$)/g, ""),10);
		if(isNaN(seedingTime))
			seedingTime = 0;
		ret+=(','+seedingTime);
	}
	return(ret);
}

utWebUI.seedingTimeCreate = function() 
{
	utWebUI.allSeedingStuffLoaded = true;
}

utWebUI.seedinginitDone = utWebUI.initDone;

function FormatWithSeeding(_55, _56) 
{
	var ret = utWebUI.seedingformat(_55, _56);
	if(_56 == null)
		ret[utWebUI.seedingColumnNo] = (_55[utWebUI.seedingColumnNo]==0) ? "" : formatDate(_55[utWebUI.seedingColumnNo]);
	else
	if(_56 == utWebUI.seedingColumnNo)
		ret = (_55==0) ? "" : formatDate(_55);
	return(ret);
}

utWebUI.seedinginitDoneNew = function()
{
	if(utWebUI.allSeedingStuffLoaded)
	{
		utWebUI.trtTable.renameColumn(utWebUI.seedingColumnNo,WUILang.seedingTime);
		utWebUI.seedingformat = utWebUI.trtTable.format;
		utWebUI.trtTable.format = FormatWithSeeding;

		if( utWebUI.rssTable )
			utWebUI.rssSeedingRenameColumn();
	}
	else
		setTimeout('utWebUI.seedinginitDoneNew()',1000);
}

utWebUI.initDone = function()
{
	utWebUI.seedinginitDone();
	if(utWebUI.seedingTimeSupported)
		utWebUI.seedinginitDoneNew();
}

utWebUI.rssSeedingRenameColumn = function()
{
	if(utWebUI.rssTable.created)
	{
		utWebUI.rssTable.format = FormatWithSeeding;
		utWebUI.rssTable.renameColumn(utWebUI.seedingColumnNo,WUILang.seedingTime);
	}
	else
		setTimeout('utWebUI.rssSeedingRenameColumn()',1000);
}

utWebUI.showSeedingTimeError = function(err)
{
	if(utWebUI.allSeedingStuffLoaded)
		log(err);
	else
		setTimeout('utWebUI.showSeedingTimeError('+err+')',1000);
}