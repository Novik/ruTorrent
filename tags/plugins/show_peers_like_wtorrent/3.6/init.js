plugin.config = theWebUI.config;
theWebUI.config = function(data)
{
	plugin.config.call(this,data);
	plugin.reqId1 = theRequestManager.addRequest("trt", 
		theRequestManager.map('cat=')+
		'"$'+theRequestManager.map("t.multicall=")+
		theRequestManager.map("d.get_hash=")+
		','+theRequestManager.map("t.get_scrape_complete=")+
		','+theRequestManager.map('cat=')+'{#}"',
		function(hash,torrent,value)
		{
		        var arr = value.split('#');
			torrent.seeds_all = 0;
			for(var i=0; i<arr.length; i++)
				torrent.seeds_all += iv(arr[i]);
			torrent.seeds = torrent.seeds_actual + " (" + torrent.seeds_all + ")";
		}
	);
	plugin.reqId2 = theRequestManager.addRequest("trt", 
		theRequestManager.map('cat=')+
		'"$'+theRequestManager.map("t.multicall=")+
		theRequestManager.map("d.get_hash=")+
		','+theRequestManager.map("t.get_scrape_incomplete=")+
		','+theRequestManager.map('cat=')+'{#}"',
		function(hash,torrent,value)
		{
		        var arr = value.split('#');
			torrent.peers_all = 0;
			for(var i=0; i<arr.length; i++)
				torrent.peers_all += iv(arr[i]);
			torrent.peers = torrent.peers_actual + " (" + torrent.peers_all + ")";
		}
	);
}

plugin.onRemove = function()
{
	theRequestManager.removeRequest( "trt", plugin.reqId1 );
	theRequestManager.removeRequest( "trt", plugin.reqId2 );
}
