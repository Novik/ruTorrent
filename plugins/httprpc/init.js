plugin.list = rTorrentStub.prototype.list;
rTorrentStub.prototype.list = function()
{
        this.torrents = cloneObject(theWebUI.torrents);
	this.mountPoint = "plugins/httprpc/action.php";
	this.dataType = "json";
	this.contentType = "application/x-www-form-urlencoded";
	this.content = "mode=list";
	if(theRequestManager.cid)
		this.content+=("&cid="+theRequestManager.cid);
	for(var i=33; i<theRequestManager.trt.commands.length; i++)
		this.content+=("&cmd="+encodeURIComponent(theRequestManager.map("trt",i)));
}

plugin.listResponse = rTorrentStub.prototype.listResponse;
rTorrentStub.prototype.listResponse = function(data)
{
        var ret = {};
        ret.torrents = this.torrents;
        theRequestManager.cid = data.cid;
	if(data.d)
		$.each( data.d, function( ndx, hash )
		{
			delete ret.torrents[hash];
		});
	$.each( data.t, function( ndx, values )
	{
		var torrent = {};
		var state = 0;
		var is_open = iv(values[1]);
		var is_hash_checking = iv(values[2]);
		var is_hash_checked = iv(values[3]);
		var get_state = iv(values[4]);
		var get_hashing = iv(values[24]);
		var is_active = iv(values[29]);
		torrent.msg = values[30];
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
		torrent.name = values[5];
		torrent.size = values[6];
		var get_completed_chunks = iv(values[7]);
		var get_hashed_chunks = iv(values[25]);
		var get_size_chunks = iv(values[8]);
		var chunks_processing = (is_hash_checking==0) ? get_completed_chunks : get_hashed_chunks;
		torrent.done = Math.floor(chunks_processing/get_size_chunks*1000);
		torrent.downloaded = iv(values[9]);
		torrent.uploaded = iv(values[10]);
		torrent.ratio = iv(values[11]);
		torrent.ul = iv(values[12]);
		torrent.dl = iv(values[13]);
		var get_chunk_size = iv(values[14]);
		torrent.eta = (torrent.dl>0) ? Math.floor((get_size_chunks-get_completed_chunks)*get_chunk_size/torrent.dl) : -1;
		try {
		torrent.label = $.trim(decodeURIComponent(values[15]));
		} catch(e) { torrent.label = ''; }
		var get_peers_not_connected = iv(values[17]);
		var get_peers_connected = iv(values[18]);
		var get_peers_all = get_peers_not_connected+get_peers_connected;
		torrent.peers_actual = values[16];
		torrent.peers_all = get_peers_all;
		torrent.seeds_actual = values[19];
		torrent.seeds_all = get_peers_all;
		torrent.remaining = values[20];
		torrent.priority = values[21];
		torrent.state_changed = values[22];
		torrent.skip_total = values[23];
		torrent.base_path = values[26];
		torrent.created = values[27];
		torrent.tracker_focus = values[28];
		try {
		torrent.comment = values[31];
		if(torrent.comment.search("VRS24mrker")==0)
			torrent.comment = decodeURIComponent(torrent.comment.substr(10));
		} catch(e) { torrent.comment = ''; }
		torrent.free_diskspace = values[32];
		torrent.seeds = torrent.seeds_actual + " (" + torrent.seeds_all + ")";
		torrent.peers = torrent.peers_actual + " (" + torrent.peers_all + ")";
		var hash = values[0];
		$.each( theRequestManager.trt.handlers, function(i,handler)
		{
		        if(handler)
				handler.response( hash, torrent, (handler.ndx===null) ? null : values[handler.ndx] );
		});
		ret.torrents[hash] = torrent;
	});
	ret.labels = {};
	$.each( ret.torrents, function( hash, torrent )
	{
		if(torrent.label.length>0)
		{
			if(!$type(ret.labels[torrent.label]))
				ret.labels[torrent.label] = 1;
			else
				ret.labels[torrent.label]++;
		}		
	});
	this.torrents = ret.torrents;
	return( ret );
}

rTorrentStub.prototype.getCommon = function(cmd)
{
	this.mountPoint = "plugins/httprpc/action.php";
	this.dataType = "json";
	this.contentType = "application/x-www-form-urlencoded";
	this.content = "mode="+cmd;
	for(var i=0; i<this.hashes.length; i++)
		this.content+=("&hash="+this.hashes[i]);
	for(var i=0; i<this.vs.length; i++)
	        this.content+=("&v="+encodeURIComponent(this.vs[i]));
	for(var i=0; i<this.ss.length; i++)
	        this.content+=("&s="+encodeURIComponent(this.ss[i]));
	if($type(theRequestManager[cmd]))
		for(var i=theRequestManager[cmd].count; i<theRequestManager[cmd].commands.length; i++)
			this.content+=("&cmd="+encodeURIComponent(theRequestManager.map(cmd,i)));
}

plugin.getfiles = rTorrentStub.prototype.getfiles;
rTorrentStub.prototype.getfiles = function()
{
        this.getCommon("fls");
}

plugin.getpeers = rTorrentStub.prototype.getpeers;
rTorrentStub.prototype.getpeers = function()
{
        this.getCommon("prs");
}

plugin.gettrackers = rTorrentStub.prototype.gettrackers;
rTorrentStub.prototype.gettrackers = function()
{
        this.getCommon("trk");
}

plugin.settrackerstate = rTorrentStub.prototype.settrackerstate;
rTorrentStub.prototype.settrackerstate = function()
{
        this.getCommon("trkstate");
}

plugin.setprio = rTorrentStub.prototype.setprio;
rTorrentStub.prototype.setprio = function()
{
        this.getCommon("setprio");
}

plugin.recheck = rTorrentStub.prototype.recheck;
rTorrentStub.prototype.recheck = function()
{
	this.getCommon("recheck");
}

plugin.start = rTorrentStub.prototype.start;
rTorrentStub.prototype.start = function()
{
	this.getCommon("start");
}

plugin.stop = rTorrentStub.prototype.stop;
rTorrentStub.prototype.stop = function()
{
	this.getCommon("stop");
}

plugin.pause = rTorrentStub.prototype.pause;
rTorrentStub.prototype.pause = function()
{
	this.getCommon("pause");
}

plugin.unpause = rTorrentStub.prototype.unpause;
rTorrentStub.prototype.unpause = function()
{
	this.getCommon("unpause");
}

plugin.remove = rTorrentStub.prototype.remove;
rTorrentStub.prototype.remove = function()
{
	this.getCommon("remove");
}

plugin.dsetprio = rTorrentStub.prototype.dsetprio;
rTorrentStub.prototype.dsetprio = function()
{
	this.getCommon("dsetprio");
}

plugin.setlabel = rTorrentStub.prototype.setlabel;
rTorrentStub.prototype.setlabel = function()
{
	this.getCommon("setlabel");
}

plugin.getalltrackers = rTorrentStub.prototype.getalltrackers;
rTorrentStub.prototype.getalltrackers = function()
{
	this.getCommon("trkall");
	for(var i=theRequestManager.trk.count; i<theRequestManager.trk.commands.length; i++)
		this.content+=("&cmd="+encodeURIComponent(theRequestManager.map("trk",i)));
}

plugin.setsettings = rTorrentStub.prototype.setsettings;
rTorrentStub.prototype.setsettings = function()
{
        this.getCommon("setsettings");
}

plugin.getsettings = rTorrentStub.prototype.getsettings;
rTorrentStub.prototype.getsettings = function()
{
        this.getCommon("stg");
}

plugin.gettotal = rTorrentStub.prototype.gettotal;
rTorrentStub.prototype.gettotal = function()
{
        this.getCommon("ttl");
}

plugin.getprops = rTorrentStub.prototype.getprops;
rTorrentStub.prototype.getprops = function()
{
        this.getCommon("prp");
}

plugin.setprops = rTorrentStub.prototype.setprops;
rTorrentStub.prototype.setprops = function()
{
        this.getCommon("setprops");
}

plugin.setulrate = rTorrentStub.prototype.setulrate;
rTorrentStub.prototype.setulrate = function()
{
	this.getCommon("setul");
}

plugin.setdlrate = rTorrentStub.prototype.setdlrate;
rTorrentStub.prototype.setdlrate = function()
{
	this.getCommon("setdl");
}

plugin.getpropsResponse = rTorrentStub.prototype.getpropsResponse;
rTorrentStub.prototype.getpropsResponse = function(values)
{
	var ret = {};
	var hash = this.hashes[0];
	ret[hash] =  
	{
		pex: (values[5]!='0') ? -1 : values[0],
		peers_max: values[1],
		peers_min: values[2],
		tracker_numwant: values[3],
		ulslots: values[4],
		superseed: (values[6]=="initial_seed") ? 1 : 0
	};
	$.each( theRequestManager.prp.handlers, function(i,handler)
	{
	        if(handler)
			handler.response( hash, ret, (handler.ndx===null) ? null : values[handler.ndx] );
	});
	return(ret);
}

plugin.gettotalResponse = rTorrentStub.prototype.gettotalResponse;
rTorrentStub.prototype.gettotalResponse = function(values)
{
	var ret = { UL: iv(values[0]), DL: iv(values[1]), rateUL: iv(values[2]), rateDL: iv(values[3]) };
	$.each( theRequestManager.ttl.handlers, function(i,handler)
	{
	        if(handler)
			handler.response( ret, (handler.ndx===null) ? null : values[handler.ndx] );
	});
	return( ret );
}

plugin.getsettingsResponse = rTorrentStub.prototype.getsettingsResponse;
rTorrentStub.prototype.getsettingsResponse = function(values)
{
	var ret = {};
	ret.dht = values[0];
	for( var cmd=0; cmd<theRequestManager.stg.count; cmd++ )
	{
	        var v = values[cmd+1];
		switch(theRequestManager.stg.commands[cmd])
		{
			case "hash_interval":
				v = iv(v)/1000;
				break;
			case "hash_read_ahead":
				v = iv(v)/1048576;
				break;
		}
		ret[theRequestManager.stg.commands[cmd]] = values[cmd+1];
	}
	$.each( theRequestManager.stg.handlers, function(i,handler)
	{
        	if(handler)
			handler.response( ret, (handler.ndx===null) ? null : values[handler.ndx+1] );
	});
	return(ret);
}

plugin.getfilesResponse = rTorrentStub.prototype.getfilesResponse;
rTorrentStub.prototype.getfilesResponse = function(values)
{
	var ret = {};
	var hash = this.hashes[0];
	ret[hash] = [];
	for(var j=0; j<values.length; j++)
	{
		var data = values[j];
		var fls = {};
		fls.name = data[0];
		fls.size = iv(data[3]);
		var get_size_chunks = iv(data[2]);	// f.get_size_chunks
		var get_completed_chunks = iv(data[1]);	// f.get_completed_chunks
		if(get_completed_chunks>get_size_chunks)
			get_completed_chunks = get_size_chunks;
		var get_completed_bytes = (get_size_chunks==0) ? 0 : fls.size/get_size_chunks*get_completed_chunks;
		fls.done = get_completed_bytes;
		fls.priority = data[4];

		$.each( theRequestManager.fls.handlers, function(i,handler)
		{
        	        if(handler)
				handler.response( hash, fls, (handler.ndx===null) ? null : data[handler.ndx] );
		});

                ret[hash].push(fls);	
	}
	return(ret);
}

plugin.getpeersResponse = rTorrentStub.prototype.getpeersResponse;
rTorrentStub.prototype.getpeersResponse = function(values)
{
	var ret = {};
	for(var j=0;j<values.length;j++)
	{
		var data = values[j];
		var peer = {};
		peer.name = data[1];
		peer.ip = peer.name;
		var cv = data[2];
		var mycv = theBTClientVersion.get(data[11]);
		if((mycv.indexOf("Unknown")>=0) && (cv.indexOf("Unknown")<0))
			mycv = cv;
		peer.version = mycv;
		peer.flags = '';
		if(data[3]==1)			//	p.is_incoming
			peer.flags+='I';
		if(data[4]==1)			//	p.is_encrypted
			peer.flags+='E';
		if(data[5]==1)			//	p.is_snubbed
			peer.flags+='S';
		peer.done = iv(data[6]);	//	get_completed_percent
		peer.downloaded = iv(data[7]);	//	p.get_down_total
		peer.uploaded = iv(data[8]);	//	p.get_up_total
		peer.dl = iv(data[9]);		//	p.get_down_rate
		peer.ul = iv(data[10]);		//	p.get_up_rate
		var id = data[0];

		$.each( theRequestManager.prs.handlers, function(i,handler)
		{
        	        if(handler)
				handler.response( id, peer, (handler.ndx===null) ? null : data[handler.ndx] );
		});

		ret[id] = peer;
	}
	return(ret);
}

plugin.gettrackersResponse = rTorrentStub.prototype.gettrackersResponse;
rTorrentStub.prototype.gettrackersResponse = function(values)
{
	var ret = {};
	var hash = this.hashes[0];
	ret[hash] = [];
	for(var j=0;j<values.length;j++)
	{
		var data = values[j];
	        var trk = {};
		trk.name = data[0];
		trk.type = data[1];
		trk.enabled = data[2];
		trk.group = data[3];
		trk.seeds = data[4];
		trk.peers = data[5];
		trk.downloaded = data[6];

		$.each( theRequestManager.trk.handlers, function(i,handler)
		{
		        if(handler)
				handler.response( hash, trk, (handler.ndx===null) ? null : data[handler.ndx] );
		});

		ret[hash].push(trk);
	}
	return(ret);
}

plugin.getalltrackersResponse = rTorrentStub.prototype.getalltrackersResponse;
rTorrentStub.prototype.getalltrackersResponse = function(values)
{
	var ret = {};
	for( var i=0; i<this.hashes.length; i++)
	{
		var hash = this.hashes[i];
		ret[hash] = [];
		var torrent = values[i];

		for(var j=0; j<torrent.length; j++)
		{
			var data = torrent[j];
		        var trk = {};
			trk.name = data[0];
			trk.type = data[1];
			trk.enabled = data[2];
			trk.group = data[3];
			trk.seeds = data[4];
			trk.peers = data[5];
			trk.downloaded = data[6];

			$.each( theRequestManager.trk.handlers, function(i,handler)
			{
	        	        if(handler)
					handler.response( hash, trk, (handler.ndx===null) ? null : data[handler.ndx] );
			});

			ret[hash].push(trk);
		}
	}
	return(ret);
}

