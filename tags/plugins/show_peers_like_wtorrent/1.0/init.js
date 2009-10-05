utWebUI.origAddTrackers = utWebUI.addTrackers;
utWebUI.addTrackers = function(_db) 
{
        utWebUI.origAddTrackers(_db);
        for(var hash in this.trackers)
        {
		var trackers = this.trackers[hash];
		if(typeof this.torrents[hash] != "undefined")
		{
		        var get_scrape_complete = 0;
		        var get_scrape_incomplete = 0;
			for(var i in trackers)
			{
				get_scrape_complete+=trackers[i][4];
				get_scrape_incomplete+=trackers[i][5];
			}
			this.trtTable.setValue(hash, 11, this.torrents[hash][12] + " (" + get_scrape_incomplete + ")");
			this.trtTable.setValue(hash, 12, this.torrents[hash][14] + " (" + get_scrape_complete + ")");
		}
        }
}