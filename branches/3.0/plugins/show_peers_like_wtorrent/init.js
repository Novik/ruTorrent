theWebUI.calcPeers = function(torrents)
{
        for(var hash in this.trackers)
        {
		var trackers = this.trackers[hash];
		var torrent = torrents[hash];
		if($type(torrent))
		{
		        var get_scrape_complete = 0;
		        var get_scrape_incomplete = 0;
			for(var i in trackers)
			{
				get_scrape_complete+=iv(trackers[i].seeds);
				get_scrape_incomplete+=iv(trackers[i].peers);
			}
			torrent.peers_all = get_scrape_incomplete;
			torrent.seeds_all = get_scrape_complete;
			torrent.seeds = torrent.seeds_actual + " (" + torrent.seeds_all + ")";
			torrent.peers = torrent.peers_actual + " (" + torrent.peers_all + ")";
		}
        }
}

plugin.getAllTrackers = theWebUI.getAllTrackers;
theWebUI.getAllTrackers = function(arr)
{
	if(!plugin.enabled)
		plugin.getAllTrackers.call(this,arr);
}

plugin.addTorrents = theWebUI.addTorrents;
theWebUI.addTorrents = function(data) 
{
	if(plugin.enabled)
	        this.calcPeers(data.torrents);
	plugin.addTorrents.call(this,data);
	if(plugin.enabled)
	{
		var tArray = [];
		for(var hash in this.torrents)
			tArray.push(hash);
		plugin.getAllTrackers.call(this,tArray);
	}
}
