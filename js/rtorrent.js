/*
 *      Link to rTorrent.
 *
 */

/**
 * @typedef {Object} StatusMask
 * @property {number} started
 * @property {number} paused
 * @property {number} checking
 * @property {number} hashing
 * @property {number} error
 */

/** @type {StatusMask} */
var dStatus = { started : 1, paused : 2, checking : 4, hashing : 8, error : 16 };

var theRequestManager = 
{
	aliases: {},
	torrents: {},
	cid: 0,
        trt:
        {
		commands:
		[ 
			"d.get_hash=", "d.is_open=", "d.is_hash_checking=", "d.is_hash_checked=", "d.get_state=",
			"d.get_name=", "d.get_size_bytes=", "d.get_completed_chunks=", "d.get_size_chunks=", "d.get_bytes_done=",
			"d.get_up_total=", "d.get_ratio=", "d.get_up_rate=", "d.get_down_rate=", "d.get_chunk_size=",
			"d.get_custom1=", "d.get_peers_accounted=", "d.get_peers_not_connected=", "d.get_peers_connected=", "d.get_peers_complete=",
			"d.get_left_bytes=", "d.get_priority=", "d.get_state_changed=", "d.get_skip_total=", "d.get_hashing=",
			"d.get_chunks_hashed=", "d.get_base_path=", "d.get_creation_date=", "d.get_tracker_size=", "d.is_active=",
			"d.get_message=", "d.get_custom2=", "d.get_free_diskspace=", "d.is_private=", "d.is_multi_file="
		],
		handlers: []
	},
	trk: 
	{
		commands: 
		[
		        "t.get_url=", "t.get_type=", "t.is_enabled=", "t.get_group=", "t.get_scrape_complete=", 
			"t.get_scrape_incomplete=", "t.get_scrape_downloaded=",
			"t.get_normal_interval=", "t.get_scrape_time_last="
		],
		handlers: []
	},
	fls: 
	{
		commands: 
		[
			"f.get_path=", "f.get_completed_chunks=", "f.get_size_chunks=", "f.get_size_bytes=", "f.get_priority="
		],
		handlers: []
	},
	prs: 
	{
		commands: 
		[ 
			"p.get_id=", "p.get_address=", "p.get_client_version=", "p.is_incoming=", "p.is_encrypted=",
			"p.is_snubbed=", "p.get_completed_percent=", "p.get_down_total=", "p.get_up_total=", "p.get_down_rate=",
			"p.get_up_rate=", "p.get_id_html=", "p.get_peer_rate=", "p.get_peer_total=", "p.get_port="
		],
		handlers: []
	},
	ttl: 
	{
		commands: 
		[
			"get_up_total", "get_down_total", "get_upload_rate", "get_download_rate"
		],
		handlers: []
	},
	opn:
	{
		commands:
		[
			"network.http.current_open", "network.open_sockets"
		],
		handlers: []
	},
	prp: 
	{
		commands: 
		[ 
			"d.get_peer_exchange", "d.get_peers_max", "d.get_peers_min", "d.get_tracker_numwant", "d.get_uploads_max",
			"d.is_private", "d.get_connection_seed"
		],
		handlers: []
	},
	stg:
	{
		commands:
		[
			"check_hash", "bind", "dht_port", "directory", "download_rate", 
			"hash_interval", "hash_max_tries", "hash_read_ahead", "http_cacert", "http_capath",
			"http_proxy", "ip", "max_downloads_div", "max_downloads_global", "max_file_size",
			"max_memory_usage", "max_open_files", "max_open_http", "max_peers", "max_peers_seed",
			"max_uploads", "max_uploads_global", "min_peers_seed", "min_peers", "peer_exchange",
			"port_open", "upload_rate", "port_random", "port_range", "preload_min_size",
			"preload_required_rate", "preload_type", "proxy_address", "receive_buffer_size", "safe_sync",
			"scgi_dont_route", "send_buffer_size", "session", "session_lock", "session_on_completion",
			"split_file_size", "split_suffix", "timeout_safe_sync", "timeout_sync", "tracker_numwant",
			"use_udp_trackers", "max_uploads_div", "max_open_sockets"
		],
		handlers: []
	},
	init: function()
	{
	        var self = this;
		$.each( ["trt","trk", "fls", "prs", "ttl", "opn", "prp", "stg"], function(ndx,cmd)
		{
			self[cmd].count = self[cmd].commands.length;
		});
	},
	addRequest: function( system, command, responseHandler )
	{
		this[system].handlers.push( { ndx: command ? this[system].commands.length : null, response: responseHandler } );
		if(command)
		        this[system].commands.push(command);
	        return(this[system].handlers.length-1);
	},
	onResponse: function(reqType, values, ...args)
	{
		// call all handlers for the response type with response data
		for (const handler of this[reqType].handlers) {
			if (handler)
				handler.response(...args, (handler.ndx===null) ? null : values[handler.ndx]);
		}
	},
	removeRequest: function( system, id )
	{
		this[system].handlers[id] = null;
	},
	map: function(cmd,no)
	{
		if(!$type(no))
		{
			var add = '';
			if(cmd.length && (cmd[cmd.length-1]=='='))
			{
				cmd = cmd.substr(0,cmd.length-1);
				add = '=';
			}
			return(this.aliases[cmd] ? this.aliases[cmd].name+add : cmd+add);
		}			
		return( this.map(this[cmd].commands[no]) );
	},
	patchCommand: function( cmd, name )
	{
		if(this.aliases[name] && this.aliases[name].prm)
			cmd.addParameter("string","");
	},
	patchRequest: function( commands )
	{
		for( var i in commands )
		{
			var cmd = commands[i];
			var prefix = '';
			if(cmd.command.indexOf('t.') === 0)
				prefix = ':t';
			else
			if(cmd.command.indexOf('p.') === 0)
				prefix = ':p';
			else
			if(cmd.command.indexOf('f.') === 0)
				prefix = ':f';
			if(prefix && 
				(cmd.params.length>1) && 
				(cmd.command.indexOf('.multicall')<0) &&
				(cmd.params[0].value.indexOf(':') < 0))
			{
				cmd.params[0].value = cmd.params[0].value+prefix+cmd.params[1].value;
				cmd.params.splice( 1, 1 );
			}
		}
	}
};

theRequestManager.init();

function rXMLRPCCommand( cmd )
{
	this.command = theRequestManager.map(cmd);
	this.params = new Array();
	theRequestManager.patchCommand( this, cmd );
}

rXMLRPCCommand.prototype.addParameter = function(aType,aValue)
{
	this.params.push( {type : aType, value : aValue} );	
}

function rTorrentStub( URI )
{
	this.action = "none";
	this.hashes = new Array();
	this.ss = new Array();
	this.vs = new Array();
	this.listRequired = false;
	this.content = null;
	this.mountPoint = theURLs.XMLRPCMountPoint;
	this.faultString = [];
	/* Remove 'charset=UTF-8' due to bug in rtorrent.
	 * https://github.com/Novik/ruTorrent/issues/2879
	 * https://github.com/rakshasa/rtorrent/issues/1444#issuecomment-2847868158
	 */
	this.contentType = "text/xml";
	this.dataType = "xml";
	this.method = "POST";
	this.ifModified = false;
	this.cache = false;

	var loc = URI.indexOf("?");
	if(loc>=0)
		URI = URI.substr(loc);
	this.URI = URI;
	if(URI.indexOf("?list=1")==0)
	{
		this.action = "list";
	}
	else
	{
		var vars = URI.split("&");
		for(var i=0; i<vars.length; i++)
		{
			var parts = vars[i].split("=");
			if(parts[0]=="?action")
				this.action = parts[1];
			else
			if(parts[0]=="hash")
				this.hashes.push(parts[1]);
			else
			if(parts[0]=="s" || parts[0]=="p")
				this.ss.push(parts[1]);
			else
			if(parts[0]=="v" || parts[0]=="f")
				this.vs.push(parts[1]);
			else
			if(parts[0]=="list")
				this.listRequired = true;
		}
	}
	this.commands = new Array();
	if(eval('typeof(this.'+this.action+') != "undefined"'))
		eval("this."+this.action+"()");
	this.commandOffset = 0;
	this.allHashes = this.hashes;
	theRequestManager.patchRequest( this.commands );
	if(this.commands.length>0)
		this.makeNextMultiCall();
}

rTorrentStub.prototype.getfiles = function()
{
	var cmd = new rXMLRPCCommand("f.multicall");
	cmd.addParameter("string",this.hashes[0]);
	cmd.addParameter("string","");
	for( var i in theRequestManager.fls.commands )
		cmd.addParameter("string",theRequestManager.map("fls",i));
	this.commands.push( cmd );
}

rTorrentStub.prototype.getpeers = function()
{
	var cmd = new rXMLRPCCommand("p.multicall");
	cmd.addParameter("string",this.hashes[0]);
	cmd.addParameter("string","");
	for( var i in theRequestManager.prs.commands )
		cmd.addParameter("string",theRequestManager.map("prs",i));
	this.commands.push( cmd );
}

rTorrentStub.prototype.gettrackers = function()
{
	var cmd = new rXMLRPCCommand("t.multicall");
	cmd.addParameter("string",this.hashes[0]);
	cmd.addParameter("string","");
	for( var i in theRequestManager.trk.commands )
		cmd.addParameter("string",theRequestManager.map("trk",i));
	this.commands.push( cmd );
}

rTorrentStub.prototype.getalltrackers = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("t.multicall");
		cmd.addParameter("string",this.hashes[i]);
		cmd.addParameter("string","");
		for( var j in theRequestManager.trk.commands )
			cmd.addParameter("string",theRequestManager.map("trk",j));
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.settrackerstate = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		var cmd = new rXMLRPCCommand("t.set_enabled");
		cmd.addParameter("string",this.hashes[0]);
		cmd.addParameter("i4",this.vs[i]);
		cmd.addParameter("i4",this.ss[0]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.list = function()
{
	var cmd = new rXMLRPCCommand("d.multicall");
	cmd.addParameter("string","main");
	for( var i in theRequestManager.trt.commands )
	{
		if(!theWebUI.settings["webui.needmessage"] && (theRequestManager.trt.commands[i]=="d.get_message="))
			cmd.addParameter("string",theRequestManager.map("d.get_custom5="));
		else
			cmd.addParameter("string",theRequestManager.map("trt",i));
	}
	this.commands.push( cmd );
}

rTorrentStub.prototype.setuisettings = function()
{
	// encodeURIComponent is called inside webui.js to avoid injection
	this.content = "v="+this.vs[0];
	this.mountPoint = theURLs.SetSettingsURL;
	this.contentType = "application/x-www-form-urlencoded";
	this.dataType = "text";
}

rTorrentStub.prototype.getuisettings = function()
{
	this.mountPoint = theURLs.GetSettingsURL;
	this.dataType = "json";
	this.method = 'GET';
}

rTorrentStub.prototype.getplugins = function()
{
	this.mountPoint = theURLs.GetPluginsURL;
	this.dataType = "script";
	this.cache = true;
	this.method = 'GET';
}

rTorrentStub.prototype.doneplugins = function()
{
	this.mountPoint = theURLs.GetDonePluginsURL;
	this.dataType = "script";
	this.content = "cmd="+encodeURIComponent(this.ss[0]);
	this.contentType = "application/x-www-form-urlencoded";
	for(var i=0; i<this.hashes.length; i++)
		this.content += ("&plg="+encodeURIComponent(this.hashes[i]));
}

rTorrentStub.prototype.recheck = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("d.check_hash");
		cmd.addParameter("string",this.hashes[i]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.setsettings = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		var prmType = "string";
		if(this.ss[i].charAt(0)=='n')
			prmType = "i8";
		var prm = this.vs[i];
		var cmd = null;
		if(this.ss[i]=="ndht")
		{
			if(prm==0)
				prm = "disable";
			else
				prm = "auto";
			prmType = "string";
			cmd = new rXMLRPCCommand('dht');
		}
		else
			cmd = new rXMLRPCCommand('set_'+this.ss[i].substr(1));
		cmd.addParameter(prmType,prm);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.getsettings = function()
{
	this.commands.push(new rXMLRPCCommand("dht_statistics"));
	for( var cmd in theRequestManager.stg.commands )
		this.commands.push(new rXMLRPCCommand('get_'+theRequestManager.stg.commands[cmd]));
}

rTorrentStub.prototype.start = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("d.open");
		cmd.addParameter("string",this.hashes[i]);
		this.commands.push( cmd );
		cmd = new rXMLRPCCommand("d.start");
		cmd.addParameter("string",this.hashes[i]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.stop = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("d.stop");
		cmd.addParameter("string",this.hashes[i]);
		this.commands.push( cmd );
		cmd = new rXMLRPCCommand("d.close");
		cmd.addParameter("string",this.hashes[i]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.updateTracker = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("d.tracker_announce");
		cmd.addParameter("string",this.hashes[i]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.pause = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("d.stop");
		cmd.addParameter("string",this.hashes[i]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.unpause = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("d.start");
		cmd.addParameter("string",this.hashes[i]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.remove = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("d.erase");
		cmd.addParameter("string",this.hashes[i]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.dsetprio = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("d.set_priority");
		cmd.addParameter("string",this.hashes[i]);
		cmd.addParameter("i4",this.vs[0]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.setprio = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		var cmd = new rXMLRPCCommand("f.set_priority");
		cmd.addParameter("string",this.hashes[0]);
		cmd.addParameter("i4",this.vs[i]);
		cmd.addParameter("i4",this.ss[0]);
		this.commands.push( cmd );
	}
	cmd = new rXMLRPCCommand("d.update_priorities");
	cmd.addParameter("string",this.hashes[0]);
	this.commands.push( cmd );
}

rTorrentStub.prototype.setprioritize = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		switch(this.ss[0])
		{
			case '0':
			{
				var cmd = new rXMLRPCCommand( "f.prioritize_first.disable" );
				cmd.addParameter("string",this.hashes[0]+":f"+this.vs[i]);
				this.commands.push( cmd );
				cmd = new rXMLRPCCommand( "f.prioritize_last.disable" );
				cmd.addParameter("string",this.hashes[0]+":f"+this.vs[i]);
				this.commands.push( cmd );
				break;
			}
			case '1':
			{
				var cmd = new rXMLRPCCommand( "f.prioritize_first.enable" );
				cmd.addParameter("string",this.hashes[0]+":f"+this.vs[i]);
				this.commands.push( cmd );
				cmd = new rXMLRPCCommand( "f.prioritize_last.disable" );
				cmd.addParameter("string",this.hashes[0]+":f"+this.vs[i]);
				this.commands.push( cmd );
				break;
			}
			case '2':
			{
				var cmd = new rXMLRPCCommand( "f.prioritize_first.disable" );
				cmd.addParameter("string",this.hashes[0]+":f"+this.vs[i]);
				this.commands.push( cmd );
				cmd = new rXMLRPCCommand( "f.prioritize_last.enable" );
				cmd.addParameter("string",this.hashes[0]+":f"+this.vs[i]);
				this.commands.push( cmd );
				break;
			}
		}
	}
	cmd = new rXMLRPCCommand("d.update_priorities");
	cmd.addParameter("string",this.hashes[0]);
	this.commands.push( cmd );
}

rTorrentStub.prototype.setlabel = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("d.set_custom1");
		cmd.addParameter("string",this.hashes[i]);
		cmd.addParameter("string",this.vs[0]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.gettotal = function()
{
	for(var i in theRequestManager.ttl.commands)
		this.commands.push( new rXMLRPCCommand(theRequestManager.ttl.commands[i]) );
}

rTorrentStub.prototype.getopen = function()
{
	this.commands = this.commands.concat(
		theRequestManager.opn.commands.map(cmd => new rXMLRPCCommand(cmd))
	);
	if (theWebUI.systemInfo.rTorrent.apiVersion >= 11)
		this.commands.push(new rXMLRPCCommand('network.open_files'));
}

rTorrentStub.prototype.getprops = function()
{
	for(var i in theRequestManager.prp.commands)
	{
		var cmd = new rXMLRPCCommand(theRequestManager.prp.commands[i]);
		cmd.addParameter("string",this.hashes[0]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.setprops = function()
{
	var cmd = null;
	for(var i=0; i<this.ss.length; i++)
	{
		if(this.ss[i]=="superseed")
		{
        		var conn = (this.vs[i]!=0) ? "initial_seed" : "seed";
			cmd = new rXMLRPCCommand("branch");
			cmd.addParameter("string",this.hashes[0]);
			cmd.addParameter("string",theRequestManager.map("d.is_active="));
			cmd.addParameter("string",theRequestManager.map("cat")+
				'=$'+theRequestManager.map("d.stop=")+
				',$'+theRequestManager.map("d.close=")+
				',$'+theRequestManager.map("d.set_connection_seed=")+conn+
				',$'+theRequestManager.map("d.open=")+
				',$'+theRequestManager.map("d.start="));
			cmd.addParameter("string",theRequestManager.map("d.set_connection_seed=")+conn);
		}
		else
		{
			if(this.ss[i]=="ulslots")
				cmd = new rXMLRPCCommand("d.set_uploads_max");
			else
			if(this.ss[i]=="pex")
				cmd = new rXMLRPCCommand("d.set_peer_exchange");
			else
				cmd = new rXMLRPCCommand("d.set_"+this.ss[i]);
			cmd.addParameter("string",this.hashes[0]);
			cmd.addParameter("i4",this.vs[i]);
		}
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.setulrate = function()
{
	var cmd = new rXMLRPCCommand("set_upload_rate");
	cmd.addParameter("string",this.ss[0]);
	this.commands.push( cmd );
}

rTorrentStub.prototype.setdlrate = function()
{
	var cmd = new rXMLRPCCommand("set_download_rate");
	cmd.addParameter("string",this.ss[0]);
	this.commands.push( cmd );
}

rTorrentStub.prototype.snub = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		var cmd = new rXMLRPCCommand("p.snubbed.set");
		cmd.addParameter("string",this.hashes[0]+":p"+this.vs[i]);
                cmd.addParameter("i4",1);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.unsnub = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		var cmd = new rXMLRPCCommand("p.snubbed.set");
		cmd.addParameter("string",this.hashes[0]+":p"+this.vs[i]);
                cmd.addParameter("i4",0);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.ban = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		var cmd = new rXMLRPCCommand("p.banned.set");
		cmd.addParameter("string",this.hashes[0]+":p"+this.vs[i]);
                cmd.addParameter("i4",1);
		this.commands.push( cmd );
		cmd = new rXMLRPCCommand("p.disconnect");
		cmd.addParameter("string",this.hashes[0]+":p"+this.vs[i]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.kick = function()
{
	for(var i=0; i<this.vs.length; i++)
	{
		var cmd = new rXMLRPCCommand("p.disconnect");
		cmd.addParameter("string",this.hashes[0]+":p"+this.vs[i]);
		this.commands.push( cmd );
	}
}

rTorrentStub.prototype.addpeer = function()
{
	var cmd = new rXMLRPCCommand("add_peer");
	cmd.addParameter("string",this.hashes[0]);
	cmd.addParameter("string",decodeURIComponent(this.vs[0]));
	this.commands.push( cmd );
}

rTorrentStub.prototype.createqueued = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("f.multicall");
		cmd.addParameter("string",this.hashes[i]);
		cmd.addParameter("string","");
		cmd.addParameter("string",theRequestManager.map("f.set_create_queued=")+'0');
		cmd.addParameter("string",theRequestManager.map("f.set_resize_queued=")+'0');
		this.commands.push( cmd );
	}

}

rTorrentStub.prototype.makeNextMultiCall = function()
{
	this.content = '<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>';
	if(this.commandOffset == this.commands.length - 1)
	{
		var cmd = this.commands[this.commandOffset++];
	        this.content+=(cmd.command+'</methodName><params>');
	        for(var i=0; i<cmd.params.length; i++)
	        {
	        	var prm = cmd.params[i];
			this.content += ('<param><value><'+prm.type+'>'+prm.value+
				'</'+prm.type+'></value></param>');
	        }
	        cmd = null;
	}
	else
	{
		// fragmentation of xml command (Content-Length must be <2MB for rtorrent 0.9.8)
		var maxContentSize = 2 << (20 + 3*(theWebUI.systemInfo.rTorrent.apiVersion>=11));
		this.content+='system.multicall</methodName><params><param><value><array><data>';
		this.hashes = [];
		for(; this.commandOffset < this.commands.length; this.commandOffset++)
		{
			var cmd = this.commands[this.commandOffset];
			var cmd_string=('<value><struct><member><name>methodName</name><value><string>'+
				cmd.command+'</string></value></member><member><name>params</name><value><array><data>');
			for(var j=0; j<cmd.params.length; j++)
			{
				var prm = cmd.params[j];
				cmd_string += ('<value><'+prm.type+'>'+
					prm.value+'</'+prm.type+'></value>');
			}
			cmd_string+="</data></array></value></member></struct></value>";
			if (this.hashes.length > 0 && this.content.length + cmd_string.length + 31 + 22 > maxContentSize)
				break;
			this.content+=cmd_string;
			this.hashes.push(this.allHashes[this.commandOffset])
			cmd_string = null;
			cmd = null;
		}
		this.content+='</data></array></value></param>';
	}
	this.content += '</params></methodCall>';
}

rTorrentStub.prototype.getXMLValue = function(values,i)
{
        var ret = "";
	if(values && values.length && (values.length>i))
	{
		var value = values[i];
		var el = value.childNodes[0];
		while(!el.tagName)
			el = el.childNodes[0];
		ret = $type(el.textContent) ? el.textContent.trim() :
			el.childNodes.length ? 
			el.childNodes[0].data : "";
	}
	return((ret==null) ? "" : ret);
}

rTorrentStub.prototype.processAction = function(actionSuffix, data)
{
	const parseFunc = this.action+actionSuffix;
	return parseFunc in this ? this[parseFunc](data) : data;
}

rTorrentStub.prototype.getResponse = function(data) 
{
	var ret = "";
	let responseData = data;
	if(this.dataType=="xml")
	{
		if(!data)
			return(ret);
		var fault = data.getElementsByTagName('fault');
		if(fault && fault.length)
		{
			var names = data.getElementsByTagName('value');
			this.faultString.push("XMLRPC Error: "+this.getXMLValue(names,2)+" ["+this.action+"]");
		}
		else
		{
			var names = data.getElementsByTagName('name');
			if(names)
				for(var i=0; i<names.length; i++)
					if(names[i].childNodes[0].data=="faultString")
					{
						var values = names[i].parentNode.getElementsByTagName('value');
						this.faultString.push("XMLRPC Error: "+this.getXMLValue(values,0)+" ["+this.action+"]");
					}
		}
		responseData = this.processAction('ParseXML', responseData);
	}
	if(!this.isError())
		ret = this.processAction('Response', responseData);
	return(ret);
}

rTorrentStub.prototype.getXMLValues = function(xml, step=1, offset=0)
{
	const datas = xml.getElementsByTagName('data');
	const dataValues = [];
	for (const data of datas) {
		const xmlValues = data.getElementsByTagName('value');
		const values = [];
		for (let i = offset; i < xmlValues.length; i+=step) {
			let el = xmlValues[i].childNodes[0];
			while(!el.tagName)
					el = el.childNodes[0];
			values.push( 'textContent' in el ? el.textContent.trim() :
					(el.childNodes.length ? el.childNodes[0].data : ''))
		}
		dataValues.push(values);
	}
	return dataValues;
}

rTorrentStub.prototype.setprioResponse = function(xml)
{
	return(this.hashes[0]);
}

rTorrentStub.prototype.setprioritizeResponse = function(xml)
{
	return(this.hashes[0]);
}

rTorrentStub.prototype.getpropsParseXML = function(xml)
{
	return this.getXMLValues(xml, 2, 1)[0];
}

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
	theRequestManager.onResponse('prp', values, hash, ret);
	return(ret);
}


rTorrentStub.prototype.gettotalParseXML = function(xml)
{
	return this.getXMLValues(xml, 2, 1)[0];
}

rTorrentStub.prototype.gettotalResponse = function(values)
{
	const ret = { UL: iv(values[0]), DL: iv(values[1]), rateUL: iv(values[2]), rateDL: iv(values[3]) };
	theRequestManager.onResponse('ttl', values, ret);
	return( ret );
}

rTorrentStub.prototype.getopenParseXML = function(xml)
{
	const values = this.getXMLValues(xml, 2, 1)[0];
	if (theWebUI.systemInfo.rTorrent.apiVersion < 11)
		values.push(-1);
	return values;
}

rTorrentStub.prototype.getopenResponse = function(values)
{
	const ret = { http: iv(values[0]), sock: iv(values[1]), fd: iv(values[values.length-1]) };
	theRequestManager.onResponse('opn', values, ret);
	return( ret );
}


rTorrentStub.prototype.getsettingsParseXML = function(xml)
{
	// map values as in plugins/httprpc/action.php
	const xmlValues = xml.getElementsByTagName('data')[0].getElementsByTagName('value');
	const offset = 2;
	const dhtInactiveValueNames = ['active', 'dht', 'throttle'];
	const dhtActiveValueNames = ['active', 'buckets', 'bytes_read', 'bytes_written', 'cycle', 'dht', 'errors_caught', 'errors_received', 'nodes', 'peers', 'peers_max', 'queries_received', 'queries_sent', 'replies_received', 'throttle', 'torrents'];

	const dht_active = this.getXMLValue(xmlValues, offset);
	const dhtValueNames = dht_active!='0' ? dhtActiveValueNames : dhtInactiveValueNames;
	const dht = this.getXMLValue(xmlValues, offset + dhtValueNames.indexOf('dht'));
	return [(dht=="auto") || (dht=="on") ? 1 : 0]
		.concat(this.getXMLValues(xml, 2, offset+dhtValueNames.length)[0]);
}

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
		ret[theRequestManager.stg.commands[cmd]] = v;
	}
	theRequestManager.onResponse('stg', values.slice(1), ret);
	return(ret);
}

rTorrentStub.prototype.getfilesParseXML = function(xml)
{
	return this.getXMLValues(xml).slice(1);
}

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

		theRequestManager.onResponse('fls', data, hash, ret);
		ret[hash].push(fls);
	}
	return(ret);
}

rTorrentStub.prototype.getpeersParseXML = function(xml)
{
	return this.getXMLValues(xml).slice(1);
}

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

		theRequestManager.onResponse('prs', data, id, peer);
		ret[id] = peer;
	}
	return(ret);
}

rTorrentStub.prototype.gettrackersParseXML = function(xml)
{
	return this.getXMLValues(xml).slice(1);
}

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
		trk.interval = data[7];
		trk.last = data[8];

		theRequestManager.onResponse('trk', data, hash, trk);

		ret[hash].push(trk);
	}
	return(ret);
}

rTorrentStub.prototype.getalltrackersParseXML = function(xml)
{
	const values = {};
	var allDatas = xml.getElementsByTagName('data');
	var delta = (this.hashes.length>1) ? 1 : 0;
	var cnt = delta;
	for( var i=0; i<this.hashes.length; i++)
	{
		const vs = this.getXMLValues(allDatas[cnt]);
		values[this.hashes[i]] = vs.slice(delta);
		cnt += vs.length + 1;
	}
	return values;
}

rTorrentStub.prototype.getalltrackersResponse = function(values)
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

			theRequestManager.onResponse('trk', data, hash, trk);

			ret[hash].push(trk);
		}
	}
	return(ret);
}

/**
 * @typedef {Object} Torrent
 * @property {boolean} updated
 * @property {string} addtime - timestamp - number with quotes (e.g.: "123")
 * @property {string} base_path
 * @property {number} chkstate - rutracker_check plugin. See @ruTrackerChecker
 * @property {number} chktime
 * @property {string} comment
 * @property {string} created - timestamp - number with quotes (e.g.: "123")
 * @property {number} dl
 * @property {number} done - percentage based on 1000 (1 = 0.1%, 1000 = 100%)
 * @property {number} downloaded
 * @property {number} eta
 * @property {string} free_diskspace - number with quotes (e.g.: "123")
 * @property {string} label
 * @property {string} msg
 * @property {number} multi_file
 * @property {string} name
 * @property {string} peers (format: "0 (0)")
 * @property {string} peers_actual - number with quotes (e.g.: "123")
 * @property {number} peers_all
 * @property {string} priority - number with quotes (e.g.: "123")
 * @property {string} private - number with quotes (e.g.: "123")
 * @property {number} ratio
 * @property {number} ratioday
 * @property {string} ratiogroup
 * @property {number} ratiomonth
 * @property {number} ratioweek
 * @property {string} remaining - number with quotes (e.g.: "123")
 * @property {number} sch_ignore
 * @property {string} seedingtime - number with quotes (e.g.: "123")
 * @property {string} seeds (format "0 (0)")
 * @property {number} seeds_actual
 * @property {number} seeds_all
 * @property {string} size - number with quotes (e.g.: "123")
 * @property {string} skip_total - number with quotes (e.g.: "123")
 * @property {number} state - mask
 * @property {string} state_changed - number with quotes (e.g.: "123")
 * @property {string} status (e.g. "Seeding")
 * @property {string} throttle
 * @property {string} tracker
 * @property {string} tracker_size
 * @property {number} ul
 * @property {number} uploaded
 */


/**
 * @typedef {Object} ListResponseType
 * @property {Object.<string, Torrent>} torrents
 * @property {Object.<string, number>} labels - count of labels
 * @property {Object.<string, number>} labels_size - cumulative size of torrents by label
 */

rTorrentStub.prototype.listParseXML = function(xml)
{
	// map values as in plugins/httprpc/action.php
	const dataValues = this.getXMLValues(xml).slice(1);
	const torrents = {};
	for(const values of dataValues) {
		torrents[values[0]] = values.slice(1);
	}
	return {
		t: torrents,
		// cache id (unused)
		cid: theRequestManager.cid,
		// deleted torrents
		d: Object.keys(theRequestManager.torrents)
			.filter(hash => !(hash in torrents)),
	};
}

/**
 * @param {Object} xml
 * @returns {ListResponseType}
 */
rTorrentStub.prototype.listResponse = function(data)
{
	/** @type {ListResponseType} */
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
		torrent.size = iv(values[5]);
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
		torrent.label = decodeURIComponent(values[14]).trim();
		} catch(e) { torrent.label = ''; }

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
		torrent.tracker_size = values[27];
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
		theRequestManager.onResponse('trt', [null].concat(values), hash, torrent);
		ret.torrents[hash] = torrent;
		torrent = null; // clean up memory leak
	});
	data = null; // clean up memory leak
	return( ret );
}

rTorrentStub.prototype.isError = function()
{
	return(this.faultString.length);
}

rTorrentStub.prototype.logErrorMessages = function()
{
	for(var i in this.faultString)
		noty(this.faultString[i],"error");
}

function Ajax(URI, isASync, onComplete, onTimeout, onError, reqTimeout, partialData)
{
	var stub = URI instanceof rTorrentStub ? URI : new rTorrentStub(URI);
	var request = $.ajax(
	{
		type: stub.method,
		url: stub.mountPoint,
		async: (isASync == null) ? true : isASync,
		contentType: stub.contentType,
		data: (stub.content == null) ? "" : stub.content,
		processData: false,
		timeout: reqTimeout || 10000,
		cache: stub.cache,
		ifModified: stub.ifModified,
		dataType: stub.dataType,
		traditional: true,
		global: true
	});
	
	request.fail(function(jqXHR, textStatus, errorThrown)
	{
		Ajax_UpdateTime(jqXHR);
		
		if((textStatus=="timeout") && ($type(onTimeout) == "function"))
			onTimeout();
		else if($type(onError) == "function")
		{
			var status = "Status unavailable";
			var response = "Response unavailable";
			try { status = jqXHR.status; response = jqXHR.responseText; } catch(e) {};				
			if( stub.dataType=="script" )
				response = errorThrown;
			onError(status+" ["+textStatus+","+stub.action+"]",response);
		}
		stub = null; // Cleanup memory leak
	});
	
	request.done(function(data, textStatus, jqXHR)
	{
		Ajax_UpdateTime(jqXHR);
		
		stub.logErrorMessages();
		if(!stub.isError()) {
			var responseText = stub.getResponse(data);
			if (partialData) {
				if (responseText instanceof Object && !(responseText instanceof XMLDocument)) {
					// merge responses for this.hashes with previous partialData
					Object.assign(responseText, partialData);
				} else if (responseText instanceof String) {
					// keep responseText = this.allHashes[0]
					responseText = partialData;
				}
			}
			// If the ajax call is pending
			if (stub.commandOffset < stub.commands.length) {
				stub.makeNextMultiCall();
				Ajax(stub, isASync, onComplete, onTimeout, onError, reqTimeout, responseText);
			} else {
				if(stub.listRequired) {
					Ajax("?list=1", isASync, onComplete, onTimeout, onError, reqTimeout);
				} else {	
					switch($type(onComplete)) {
						case "function": {
							onComplete(responseText);
							break;
						}				
						case "array": {
							onComplete[0].apply(onComplete[1], new Array(responseText, onComplete[2]));
							break;
						}	
					}	
				}
			}
			responseText = null; // Cleanup memory leak
		}
		stub = null; // Cleanup memory leak
	});
	
	// Nullify ajax request varriables to cleanup up memory leaks
	request.onreadystatechange = null;
	request = null;
}

function Ajax_UpdateTime(jqXHR)
{
	if(theWebUI.deltaTime==0)
	{
		var diff = 0;
		try { diff = new Date().getTime()-Date.parse(jqXHR.getResponseHeader("Date")); } catch(e) { diff = 0; };
		theWebUI.deltaTime = iv(diff);
		diff = null; // Cleanup memory leak
	}
	
	if(theWebUI.serverDeltaTime==0)
	{
		var timestamp = jqXHR.getResponseHeader("X-Server-Timestamp");
		if(timestamp != null)
			theWebUI.serverDeltaTime = new Date().getTime()-iv(timestamp)*1000;
		timestamp = null; // Cleanup memory leak
	}
	jqXHR = null; // Cleanup memory leak
}

$(document).ready(function() 
{
	var timer = null;

	$(document).ajaxStart( function()
	{
		timer = window.setTimeout( function()
		{
			$('#ind').css( { visibility: 'visible' } )
		}, 500);
	});
	$(document).ajaxStop( function()
	{
	        if(timer)
        	{
        		window.clearTimeout(timer);
	        	timer = null;
		}
		$('#ind').css( { visibility: "hidden" } );
	});
});
