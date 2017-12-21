theRequestManager.torrents = {};
plugin.XMLRPCMountPoint = theURLs.XMLRPCMountPoint;
theURLs.XMLRPCMountPoint = "plugins/httprpc/action.php";

plugin.origlist = rTorrentStub.prototype.list;
rTorrentStub.prototype.list = function()
{
	if(plugin.enabled)
	{
		this.dataType = "json";
		this.contentType = "application/x-www-form-urlencoded";
		this.content = "mode=list";
		if(theRequestManager.cid)
			this.content+=("&cid="+theRequestManager.cid);
		for(var i=theRequestManager.trt.count; i<theRequestManager.trt.commands.length; i++)
			this.content+=("&cmd="+encodeURIComponent(theRequestManager.map("trt",i)));
	}
	else
		plugin.origlist.call(this);
}

plugin.origlistResponse = rTorrentStub.prototype.listResponse;
rTorrentStub.prototype.listResponse = function(data)
{
        if(this.dataType == "json")
        {
		var ret = { labels: {}, labels_size: {}, torrents: {} };
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
			try {
			torrent.label = $.trim(decodeURIComponent(values[14]));
			} catch(e) { torrent.label = ''; }

			if(torrent.label.length>0)
			{
				if(!$type(ret.labels[torrent.label]))
				{
					ret.labels[torrent.label] = 1;
					ret.labels_size[torrent.label] = parseInt(torrent.size);
				}
				else
				{
					ret.labels[torrent.label]++;
					ret.labels_size[torrent.label] = parseInt(ret.labels_size[torrent.label]) + parseInt(torrent.size);
				}
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
			var pos = torrent.base_path.lastIndexOf('/');
			torrent.save_path = (torrent.base_path.substring(pos+1) === torrent.name) ? 
				torrent.base_path.substring(0,pos) : torrent.base_path;
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

rTorrentStub.prototype.getCommon = function(cmd)
{
        if(plugin.enabled)
        {
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
	else
		plugin["orig"+cmd].call(this);
}

plugin.origfls = rTorrentStub.prototype.getfiles;
rTorrentStub.prototype.getfiles = function()
{
        this.getCommon("fls");
}

plugin.origprs = rTorrentStub.prototype.getpeers;
rTorrentStub.prototype.getpeers = function()
{
        this.getCommon("prs");
}

plugin.origtrk = rTorrentStub.prototype.gettrackers;
rTorrentStub.prototype.gettrackers = function()
{
        this.getCommon("trk");
}

plugin.origtrkstate = rTorrentStub.prototype.settrackerstate;
rTorrentStub.prototype.settrackerstate = function()
{
        this.getCommon("trkstate");
}

plugin.origsetprio = rTorrentStub.prototype.setprio;
rTorrentStub.prototype.setprio = function()
{
        this.getCommon("setprio");
}

plugin.origrecheck = rTorrentStub.prototype.recheck;
rTorrentStub.prototype.recheck = function()
{
	this.getCommon("recheck");
}

plugin.origstart = rTorrentStub.prototype.start;
rTorrentStub.prototype.start = function()
{
	this.getCommon("start");
}

plugin.origstop = rTorrentStub.prototype.stop;
rTorrentStub.prototype.stop = function()
{
	this.getCommon("stop");
}

plugin.origpause = rTorrentStub.prototype.pause;
rTorrentStub.prototype.pause = function()
{
	this.getCommon("pause");
}

plugin.origunpause = rTorrentStub.prototype.unpause;
rTorrentStub.prototype.unpause = function()
{
	this.getCommon("unpause");
}

plugin.origremove = rTorrentStub.prototype.remove;
rTorrentStub.prototype.remove = function()
{
	this.getCommon("remove");
}

plugin.origdsetprio = rTorrentStub.prototype.dsetprio;
rTorrentStub.prototype.dsetprio = function()
{
	this.getCommon("dsetprio");
}

plugin.origsetlabel = rTorrentStub.prototype.setlabel;
rTorrentStub.prototype.setlabel = function()
{
	this.getCommon("setlabel");
}

plugin.origtrkall = rTorrentStub.prototype.getalltrackers;
rTorrentStub.prototype.getalltrackers = function()
{
	if( this.hashes.length > 50 )
		this.hashes = [];
	this.getCommon("trkall");
	if(plugin.enabled)
		for(var i=theRequestManager.trk.count; i<theRequestManager.trk.commands.length; i++)
			this.content+=("&cmd="+encodeURIComponent(theRequestManager.map("trk",i)));
}

plugin.origsetsettings = rTorrentStub.prototype.setsettings;
rTorrentStub.prototype.setsettings = function()
{
        this.getCommon("setsettings");
}

plugin.origstg = rTorrentStub.prototype.getsettings;
rTorrentStub.prototype.getsettings = function()
{
        this.getCommon("stg");
}

plugin.origttl = rTorrentStub.prototype.gettotal;
rTorrentStub.prototype.gettotal = function()
{
        this.getCommon("ttl");
}

plugin.origprp = rTorrentStub.prototype.getprops;
rTorrentStub.prototype.getprops = function()
{
        this.getCommon("prp");
}

plugin.origsetprops = rTorrentStub.prototype.setprops;
rTorrentStub.prototype.setprops = function()
{
        this.getCommon("setprops");
}

plugin.origsetul = rTorrentStub.prototype.setulrate;
rTorrentStub.prototype.setulrate = function()
{
	this.getCommon("setul");
}

plugin.origsetdl = rTorrentStub.prototype.setdlrate;
rTorrentStub.prototype.setdlrate = function()
{
	this.getCommon("setdl");
}

plugin.origsnub = rTorrentStub.prototype.snub;
rTorrentStub.prototype.snub = function()
{
	this.getCommon("snub");
}

plugin.origunsnub = rTorrentStub.prototype.unsnub;
rTorrentStub.prototype.unsnub = function()
{
	this.getCommon("unsnub");
}

plugin.origban = rTorrentStub.prototype.ban;
rTorrentStub.prototype.ban = function()
{
	this.getCommon("ban");
}

plugin.origkick = rTorrentStub.prototype.kick;
rTorrentStub.prototype.kick = function()
{
	this.getCommon("kick");
}

plugin.origaddpeer = rTorrentStub.prototype.addpeer;
rTorrentStub.prototype.addpeer = function()
{
	this.getCommon("add_peer");
}

plugin.origgetchunks = rTorrentStub.prototype.getchunks;
rTorrentStub.prototype.getchunks = function() 
{
	this.hashes[0] = theWebUI.dID;
        this.getCommon("getchunks");
}

plugin.origgetchunksResponse = rTorrentStub.prototype.getchunksResponse;
rTorrentStub.prototype.getchunksResponse = function(data)
{
	if(this.dataType == "json")
		return(data);
	return(plugin.origgetchunksResponse.call(this,data));
}

plugin.origgetpropsResponse = rTorrentStub.prototype.getpropsResponse;
rTorrentStub.prototype.getpropsResponse = function(values)
{
        if(this.dataType == "json")
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
	return(plugin.origgetpropsResponse.call(this,values));
}

plugin.origgettotalResponse = rTorrentStub.prototype.gettotalResponse;
rTorrentStub.prototype.gettotalResponse = function(values)
{
        if(this.dataType == "json")
        {
		var ret = { UL: iv(values[0]), DL: iv(values[1]), rateUL: iv(values[2]), rateDL: iv(values[3]) };
		$.each( theRequestManager.ttl.handlers, function(i,handler)
		{
		        if(handler)
				handler.response( ret, (handler.ndx===null) ? null : values[handler.ndx] );
		});
		return( ret );
	}
	return(plugin.origgettotalResponse.call(this,values));
}

plugin.origgetsettingsResponse = rTorrentStub.prototype.getsettingsResponse;
rTorrentStub.prototype.getsettingsResponse = function(values)
{
        if(this.dataType == "json")
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
			ret[theRequestManager.stg.commands[cmd]] = v;
		}
		$.each( theRequestManager.stg.handlers, function(i,handler)
		{
        		if(handler)
				handler.response( ret, (handler.ndx===null) ? null : values[handler.ndx+1] );
		});
		return(ret);
	}
	return(plugin.origgetsettingsResponse.call(this,values));
}

plugin.origgetfilesResponse = rTorrentStub.prototype.getfilesResponse;
rTorrentStub.prototype.getfilesResponse = function(values)
{
        if(this.dataType == "json")
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
	return(plugin.origgetfilesResponse.call(this,values));
}

plugin.origgetpeersResponse = rTorrentStub.prototype.getpeersResponse;
rTorrentStub.prototype.getpeersResponse = function(values)
{
        if(this.dataType == "json")
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
			peer.snubbed = 0;
			if(data[5]==1)			//	p.is_snubbed
			{
				peer.flags+='S';
				peer.snubbed = 1;
			}
			peer.done = iv(data[6]);		//	get_completed_percent
			peer.downloaded = iv(data[7]);		//	p.get_down_total
			peer.uploaded = iv(data[8]);		//	p.get_up_total
			peer.dl = iv(data[9]);			//	p.get_down_rate
			peer.ul = iv(data[10]);			//	p.get_up_rate
			peer.peerdl = iv(data[12]);		//	p.get_peer_rate
			peer.peerdownloaded = iv(data[13]);	//	p.get_peer_total			
			peer.port = iv(data[14]);		//	p.get_port

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
	return(plugin.origgetpeersResponse.call(this,values));
}

plugin.origgettrackersResponse = rTorrentStub.prototype.gettrackersResponse;
rTorrentStub.prototype.gettrackersResponse = function(values)
{
        if(this.dataType == "json")
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
			trk.interval = data[7];
			trk.last = data[8];

			$.each( theRequestManager.trk.handlers, function(i,handler)
			{
		        	if(handler)
					handler.response( hash, trk, (handler.ndx===null) ? null : data[handler.ndx] );
			});

			ret[hash].push(trk);
		}
		return(ret);
	}
	return(plugin.origgettrackersResponse.call(this,values));
}

plugin.origgetalltrackersResponse = rTorrentStub.prototype.getalltrackersResponse;
rTorrentStub.prototype.getalltrackersResponse = function(values)
{
        if(this.dataType == "json")
        {
		var ret = {};
		for( var hash in values )
		{
			ret[hash] = [];
			var torrent = values[hash];
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
	return(plugin.origgetalltrackersResponse.call(this,values));
}

plugin.onRemove = function()
{
	theRequestManager.torrents = {};
	theRequestManager.cid = 0;
	theURLs.XMLRPCMountPoint = plugin.XMLRPCMountPoint;
}
