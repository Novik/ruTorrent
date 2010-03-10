if(plugin.enabled)
{
	plugin.loadMainCSS();

	plugin.config = theWebUI.config;
	theWebUI.config = function(data)
	{
		theRequestManager.addRequest("trt", 'cat="$t.multicall=d.get_hash=,t.get_scrape_complete=,cat={#}"',function(hash,torrent,value)
		{
		        var arr = value.split('#');
			torrent.seeds_all = 0;
			for(var i=0; i<arr.length; i++)
				torrent.seeds_all += iv(arr[i]);
			torrent.seeds = torrent.seeds_actual + " (" + torrent.seeds_all + ")";
		});
		theRequestManager.addRequest("trt", 'cat="$t.multicall=d.get_hash=,t.get_scrape_incomplete=,cat={#}"',function(hash,torrent,value)
		{
		        var arr = value.split('#');
			torrent.peers_all = 0;
			for(var i=0; i<arr.length; i++)
				torrent.peers_all += iv(arr[i]);
			torrent.peers = torrent.peers_actual + " (" + torrent.peers_all + ")";
		});
		plugin.config.call(this,data);
	}
}
