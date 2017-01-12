/* TODO: Would be good to move this to configuration somewhere */
var uploadtarget = 1.5;

/* Add the columns to the torrent list */
theWebUI.tables.trt.columns.push({text: "UL target", width: "60px", id: "upload_target", type: TYPE_NUMBER});
theWebUI.tables.trt.columns.push({text: "UL remaining", width: "60px", id: "upload_remaining", type: TYPE_NUMBER});
theWebUI.tables.trt.columns.push({text: "UL ETA", width: "60px", id: "upload_eta", type: TYPE_NUMBER});

/* Overwrite the listResponse function to add the upload data */
rTorrentStub.prototype.listResponse = function(data)
{
        if(this.dataType == "json")
        {
	        var ret = { labels: {}, torrents: {} };
        	theRequestManager.cid = data.cid;
		if(data.d)
			$.each( data.d, function( ndx, hash )
			{
				delete theRequestManager.torrents[hash];
			});
		$.each( data.t, function( hash, values )
		{
			if($type(theRequestManager.torrents[hash]))
			{
				$.each( values, function( ndx, value )
				{
					theRequestManager.torrents[hash][ndx] = value;
				});
			}
			else
				theRequestManager.torrents[hash] = values;
		});
		$.each( theRequestManager.torrents, function( hash, values )
		{
			var torrent = {};
			var state = 0;
			var is_open = iv(values[0]);
			var is_hash_checking = iv(values[1]);
			var is_hash_checked = iv(values[2]);
			var get_state = iv(values[3]);
			var get_hashing = iv(values[23]);
			var is_active = iv(values[28]);
			torrent.msg = values[29];
			if(is_open!=0)
			{
				state|=dStatus.started;
				if((get_state==0) || (is_active==0))
					state|=dStatus.paused;
			}
			if(get_hashing!=0)
				state|=dStatus.hashing;
			if(is_hash_checking!=0)
				state|=dStatus.checking;
			if(torrent.msg.length && torrent.msg!="Tracker: [Tried all trackers.]")
				state|=dStatus.error;
			torrent.state = state;
			torrent.name = values[4];
			torrent.size = values[5];
			var get_completed_chunks = iv(values[6]);
			var get_hashed_chunks = iv(values[24]);
			var get_size_chunks = iv(values[7]);
			var chunks_processing = (is_hash_checking==0) ? get_completed_chunks : get_hashed_chunks;
			torrent.done = Math.floor(chunks_processing/get_size_chunks*1000);
			torrent.downloaded = iv(values[8]);
			torrent.uploaded = iv(values[9]);
			torrent.ratio = iv(values[10]);
			torrent.ul = iv(values[11]);
			torrent.dl = iv(values[12]);
			var get_chunk_size = iv(values[13]);
			torrent.eta = (torrent.dl>0) ? Math.floor((get_size_chunks-get_completed_chunks)*get_chunk_size/torrent.dl) : -1;

			/* Add the upload eta data */
			torrent.upload_target = parseInt(values[5]) * uploadtarget;
			torrent.upload_remaining = torrent.upload_target - torrent.uploaded;
			torrent.upload_eta = (torrent.ul>0) ? Math.floor(torrent.upload_remaining/torrent.ul) : -1;
			
			/* format the upload eta data, because theFormatter does not know these columns */
			torrent.upload_eta = (torrent.upload_eta <=- 1) ? "\u221e" : theConverter.time(torrent.upload_eta);
			torrent.upload_target = theConverter.bytes(torrent.upload_target, 2);
			torrent.upload_remaining = theConverter.bytes(torrent.upload_remaining, 2);
			
			try {
			torrent.label = $.trim(decodeURIComponent(values[14]));
			} catch(e) { torrent.label = ''; }

			if(torrent.label.length>0)
			{
				if(!$type(ret.labels[torrent.label]))
					ret.labels[torrent.label] = 1;
				else
					ret.labels[torrent.label]++;
			}

			var get_peers_not_connected = iv(values[16]);
			var get_peers_connected = iv(values[17]);
			var get_peers_all = get_peers_not_connected+get_peers_connected;
			torrent.peers_actual = values[15];
			torrent.peers_all = get_peers_all;
			torrent.seeds_actual = values[18];
			torrent.seeds_all = get_peers_all;
			torrent.remaining = values[19];
			torrent.priority = values[20];
			torrent.state_changed = values[21];
			torrent.skip_total = values[22];
			torrent.base_path = values[25];
			torrent.created = values[26];
			torrent.tracker_focus = values[27];
			try {
			torrent.comment = values[30];
			if(torrent.comment.search("VRS24mrker")==0)
				torrent.comment = decodeURIComponent(torrent.comment.substr(10));
			} catch(e) { torrent.comment = ''; }
			torrent.free_diskspace = values[31];
			torrent.private = values[32];
			torrent.multi_file = iv(values[33]);
			torrent.seeds = torrent.seeds_actual + " (" + torrent.seeds_all + ")";
			torrent.peers = torrent.peers_actual + " (" + torrent.peers_all + ")";
			$.each( theRequestManager.trt.handlers, function(i,handler)
			{
		        	if(handler)
					handler.response( hash, torrent, (handler.ndx===null) ? null : values[handler.ndx-1] );
			});
			ret.torrents[hash] = torrent;
		});
		return( ret );
	}
	return(plugin.origlistResponse.call(this,data));

}
