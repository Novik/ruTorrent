function rXMLRPCCommand( cmd )
{
	this.command = cmd;
	this.params = new Array();
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
	this.mountPoint = WUIResorces.XMLRPCMountPoint;
	this.faultString = null;
	this.contentType = "text/xml; charset=UTF-8";
	this.responseMustBeXML = true;

	this.cmdInfoArray = new Array( "bind", "check_hash", "dht_port", "directory", "download_rate", 
		"hash_interval", "hash_max_tries", "hash_read_ahead", "http_cacert", "http_capath",
		"http_proxy", "ip", "max_downloads_div", "max_downloads_global", "max_file_size",
		"max_memory_usage", "max_open_files", "max_open_http", "max_peers", "max_peers_seed",
		"max_uploads", "max_uploads_global", "min_peers_seed", "min_peers", "peer_exchange",
		"port_open", "upload_rate", "port_random", "port_range", "preload_min_size",
		"preload_required_rate", "preload_type", "proxy_address", "receive_buffer_size", "safe_sync",
		"scgi_dont_route", "send_buffer_size", "session", "session_lock", "session_on_completion",
		"split_file_size", "split_suffix", "timeout_safe_sync", "timeout_sync", "tracker_numwant",
		"use_udp_trackers", "max_uploads_div", "max_open_sockets" );
	
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
	if(this.commands.length>0)
		this.makeMultiCall();
}

rTorrentStub.prototype.getfiles = function()
{
	var cmd = new rXMLRPCCommand("f.multicall");
	cmd.addParameter("string",this.hashes[0]);
	cmd.addParameter("string","");
	cmd.addParameter("string","f.get_path=");
	cmd.addParameter("string","f.get_completed_chunks=");
	cmd.addParameter("string","f.get_size_chunks=");
	cmd.addParameter("string","f.get_size_bytes=");
	cmd.addParameter("string","f.get_priority=");
	this.commands.push( cmd );
}

rTorrentStub.prototype.getpeers = function()
{
	var cmd = new rXMLRPCCommand("p.multicall");
	cmd.addParameter("string",this.hashes[0]);
	cmd.addParameter("string","");
	cmd.addParameter("string","p.get_id=");
	cmd.addParameter("string","p.get_address=");
	cmd.addParameter("string","p.get_client_version=");
	cmd.addParameter("string","p.is_incoming=");
	cmd.addParameter("string","p.is_encrypted=");
	cmd.addParameter("string","p.is_snubbed=");
	cmd.addParameter("string","p.get_completed_percent=");
	cmd.addParameter("string","p.get_down_total=");
	cmd.addParameter("string","p.get_up_total=");
	cmd.addParameter("string","p.get_down_rate=");
	cmd.addParameter("string","p.get_up_rate=");
	cmd.addParameter("string","p.get_id_html=");
	this.commands.push( cmd );
}

rTorrentStub.prototype.gettrackers = function()
{
	var cmd = new rXMLRPCCommand("t.multicall");
	cmd.addParameter("string",this.hashes[0]);
	cmd.addParameter("string","");
	cmd.addParameter("string","t.get_url=");
	cmd.addParameter("string","t.get_type=");
	cmd.addParameter("string","t.is_enabled=");
	cmd.addParameter("string","t.get_group=");
	cmd.addParameter("string","t.get_scrape_complete=");
	cmd.addParameter("string","t.get_scrape_incomplete=");
	cmd.addParameter("string","t.get_scrape_downloaded=");
	this.commands.push( cmd );
}

rTorrentStub.prototype.getalltrackers = function()
{
	for(var i=0; i<this.hashes.length; i++)
	{
		var cmd = new rXMLRPCCommand("t.multicall");
		cmd.addParameter("string",this.hashes[i]);
		cmd.addParameter("string","");
		cmd.addParameter("string","t.get_url=");
		cmd.addParameter("string","t.get_type=");
		cmd.addParameter("string","t.is_enabled=");
		cmd.addParameter("string","t.get_group=");
		cmd.addParameter("string","t.get_scrape_complete=");
		cmd.addParameter("string","t.get_scrape_incomplete=");
		cmd.addParameter("string","t.get_scrape_downloaded=");
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
	cmd.addParameter("string","d.get_hash=");
	cmd.addParameter("string","d.is_open=");
	cmd.addParameter("string","d.is_hash_checking=");
	cmd.addParameter("string","d.is_hash_checked=");
	cmd.addParameter("string","d.get_state=");
	cmd.addParameter("string","d.get_name=");
	cmd.addParameter("string","d.get_size_bytes=");
	cmd.addParameter("string","d.get_completed_chunks=");
	cmd.addParameter("string","d.get_size_chunks=");
	cmd.addParameter("string","d.get_bytes_done=");
	cmd.addParameter("string","d.get_up_total=");
	cmd.addParameter("string","d.get_ratio=");
	cmd.addParameter("string","d.get_up_rate=");
	cmd.addParameter("string","d.get_down_rate=");
	cmd.addParameter("string","d.get_chunk_size=");
	cmd.addParameter("string","d.get_custom1=");
	cmd.addParameter("string","d.get_peers_accounted=");
	cmd.addParameter("string","d.get_peers_not_connected=");
	cmd.addParameter("string","d.get_peers_connected=");
	cmd.addParameter("string","d.get_peers_complete=");
	cmd.addParameter("string","d.get_left_bytes=");
	cmd.addParameter("string","d.get_priority=");
	cmd.addParameter("string","d.get_state_changed=");
	cmd.addParameter("string","d.get_skip_total=");
	cmd.addParameter("string","d.get_hashing=");
	cmd.addParameter("string","d.get_chunks_hashed=");
	cmd.addParameter("string","d.get_base_path=");
	cmd.addParameter("string","d.get_creation_date=");
	cmd.addParameter("string","d.get_tracker_focus=");
	cmd.addParameter("string","d.is_active=");
	if(utWebUI.bShowMessage)
	{
		cmd.addParameter("string","d.get_message=");
	}
	else
	{
		cmd.addParameter("string","d.get_custom5=");
	}
	cmd.addParameter("string","d.get_custom2=");
	cmd.addParameter("string","d.get_free_diskspace=");
	this.commands.push( cmd );
}

rTorrentStub.prototype.setuisettings = function()
{
	var cookieNdx = this.getCookieNdx();
	if(cookieNdx>=0)
	{
		this.content = "v="+this.vs[cookieNdx];
		this.mountPoint = WUIResorces.SetSettingsURL;
		this.contentType = "application/x-www-form-urlencoded";
	}
}

rTorrentStub.prototype.getuisettings = function()
{
	this.content = '<?xml version="1.0" encoding="UTF-8"?><dummy/>';
	this.mountPoint = WUIResorces.GetSettingsURL;
}

rTorrentStub.prototype.getplugins = function()
{
	this.content = '<?xml version="1.0" encoding="UTF-8"?><dummy/>';
	this.mountPoint = WUIResorces.GetPlugingURL;
	this.responseMustBeXML = false;
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
	for( var cmd in this.cmdInfoArray )
		this.commands.push(new rXMLRPCCommand("get_"+this.cmdInfoArray[cmd]));
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
	this.commands.push( new rXMLRPCCommand("get_up_total") );
	this.commands.push( new rXMLRPCCommand("get_down_total") );
	this.commands.push( new rXMLRPCCommand("get_upload_rate") );
	this.commands.push( new rXMLRPCCommand("get_download_rate") );
}

rTorrentStub.prototype.getprops = function()
{
	var cmd = new rXMLRPCCommand("d.get_peer_exchange");
	cmd.addParameter("string",this.hashes[0]);
	this.commands.push( cmd );
	cmd = new rXMLRPCCommand("d.get_peers_max");
	cmd.addParameter("string",this.hashes[0]);
	this.commands.push( cmd );
	cmd = new rXMLRPCCommand("d.get_peers_min");
	cmd.addParameter("string",this.hashes[0]);
	this.commands.push( cmd );
	cmd = new rXMLRPCCommand("d.get_tracker_numwant");
	cmd.addParameter("string",this.hashes[0]);
	this.commands.push( cmd );
	cmd = new rXMLRPCCommand("d.get_uploads_max");
	cmd.addParameter("string",this.hashes[0]);
	this.commands.push( cmd );
	cmd = new rXMLRPCCommand("d.is_private");
	cmd.addParameter("string",this.hashes[0]);
	this.commands.push( cmd );
	cmd = new rXMLRPCCommand("d.get_connection_current");
	cmd.addParameter("string",this.hashes[0]);
	this.commands.push( cmd );
}

rTorrentStub.prototype.setprops = function()
{
	var cmd = null;
	for(var i=0; i<this.ss.length; i++)
	{
		if(this.ss[i]=="superseed")
		{
//			cmd = new rXMLRPCCommand("d.set_connection_current");
//			cmd.addParameter("string",this.hashes[0]);
//			cmd.addParameter("string",(this.vs[i]!=0) ? "initial_seed" : "seed");
        		var conn = (this.vs[i]!=0) ? "initial_seed" : "seed";
			cmd = new rXMLRPCCommand("branch");
			cmd.addParameter("string",this.hashes[0]);
			cmd.addParameter("string","d.is_active=");
			cmd.addParameter("string","cat=$d.stop=,$d.close=,$d.set_connection_seed="+conn+",$d.open=,$d.start=");
			cmd.addParameter("string","d.set_connection_seed="+conn);
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

rTorrentStub.prototype.makeMultiCall = function()
{
	this.content = '<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>';
	if(this.commands.length==1)
	{
		var cmd = this.commands[0];
	        this.content+=(cmd.command+'</methodName><params>\r\n');
	        for(var i=0; i<cmd.params.length; i++)
	        {
	        	var prm = cmd.params[i];
			this.content += ('<param><value><'+prm.type+'>'+prm.value+
				'</'+prm.type+'></value></param>\r\n');
	        }
	        cmd = null;
	}
	else
	{
		this.content+='system.multicall</methodName><params><param><value><array><data>';
		for(var i=0; i<this.commands.length; i++)
		{
			var cmd = this.commands[i];
			this.content+=('\r\n<value><struct><member><name>methodName</name><value><string>'+
				cmd.command+'</string></value></member><member><name>params</name><value><array><data>');
			for(var j=0; j<cmd.params.length; j++)
			{
				var prm = cmd.params[j];
				this.content += ('\r\n<value><'+prm.type+'>'+
					prm.value+'</'+prm.type+'></value>');
			}
			this.content+="\r\n</data></array></value></member></struct></value>";
			cmd = null;
		}
		this.content+='\r\n</data></array></value></param>';
	}
	this.content += '</params></methodCall>';
}

rTorrentStub.prototype.getCookieNdx = function()
{
	for(var i=0; i<this.ss.length; i++)
	{
		if(this.ss[i]=="webui.cookie")
			return(i);
	}
	return(-1);
}

function toHex( n ) { var s = n.toString( 16 ); if(s.length<2) s='0'+s; return(s); }

function parseQuote( cookie )
{
	var ret = '';
	if(cookie)
	{
		for(var i=0; i<cookie.length; i++)
		{
			var c = cookie.charAt(i);
			var code = cookie.charCodeAt(i);
			if((c=='\\') || (c=="\""))
			        ret+='\\';
			else 
			if(code<32)
				c = "\\x"+toHex(code);
			ret+=c;
		}
	}
	return(ret);
}

function parseCookie( cookie )
{
	var ret = '';
	if(cookie)
	{
		var prev = ' ';
		for(var i=0; i<cookie.length; i++)
		{
			var c = cookie.charAt(i);
			if(c=='"' && prev!='\\')
			        ret+='\\';
			ret+=c;
			prev = c;
		}
	}
	return(ret);
}

rTorrentStub.prototype.getValue = function(values,i) 
{
        var ret = "";
	if(values && values.length && (values.length>i))
	{
		var value = values[i];
		var el = value.childNodes[0];
		while(!el.tagName)
			el = el.childNodes[0];
		ret = parseQuote( el.childNodes.length ? el.childNodes[0].data : "" );
	}
	return(ret)
}

var azLikeClients4 = {  
	"AR" : "Ares", "AT" : "Artemis", "AV" : "Avicora",
	"BG" : "BTGetit", "BM" : "BitMagnet", "BP" : "BitTorrent Pro (Azureus + Spyware)",
	"BX" : "BittorrentX", "bk" : "BitKitten (libtorrent)", "BS" : "BTSlave",
	"BW" : "BitWombat", "BX" : "BittorrentX", "EB" : "EBit",
	"DE" : "Deluge", "DP" : "Propogate Data Client", "FC" : "FileCroc",
	"FT" : "FoxTorrent/RedSwoosh", "GR" : "GetRight", "HN" : "Hydranode",
	"LC" : "LeechCraft", "LH" : "LH-ABC", "NX" : "Net Transport",
	"MO" : "MonoTorrent", "MR" : "Miro", "MT" : "Moonlight",
	"OT" : "OmegaTorrent", "PD" : "Pando", "QD" : "QQDownload",
	"RS" : "Rufus", "RT" : "Retriever", "RZ" : "RezTorrent",
	"SD" : "Xunlei", "SS" : "SwarmScope", "SZ" : "Shareaza",
	"S~" : "Shareaza beta", "st" : "SharkTorrent", "TN" : "Torrent .NET",
	"TS" : "TorrentStorm", "UL" : "uLeecher!", "VG" : "Vagaa",
	"WT" : "BitLet", "WY" : "Wyzo", "XL" : "Xunlei",
	"XT" : "XanTorrent", "ZT" : "Zip Torrent", 

	'GS' : "GSTorrent", 'KG' : "KGet", 'ST' : "SymTorrent", 
	'BE' : "BitTorrent SDK"
	 };

var azLikeClients3 = {  
        "AG" : "Ares", "A~" : "Ares", "ES" : "Electric Sheep",
        "HL" : "Halite", "LT" : "libtorrent (Rasterbar)", "lt" : "libTorrent (Rakshasa)",
        "MP" : "MooPolice", "TT" : "TuoTu", "qB" : "qBittorrent" };

var azLikeClients2x2 = {  
        "AX" : "BitPump", "BC" : "BitComet", "CD" : "Enhanced CTorrent",
        "LP" : "Lphant" };

var azLikeClientsSpec = {
	'UM' : "uTorrent for Mac", 'UT' : "uTorrent", 'TR' : "Transmission",
	'AZ' : "Azureus", 'KT' : "KTorrent", "BF" : "BitFlu",
        'LW' : "LimeWire", "BB" : "BitBuddy", "BR" : "BitRocket",
	"CT" : "CTorrent", 'XX' : "Xtorrent" };

var shLikeClients = {  
	'O' : "Osprey ", 'Q' : "BTQueue", 
        'A' : "ABC", 'R' : "Tribler", 'S' : "Shad0w",
        'T': "BitTornado", 'U': "UPnP NAT Bit Torrent" };

function shChar( ch )
{
	var codes = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz.-";
	var loc = codes.indexOf(ch);
	if(loc<0) loc = 0;
	return(String(loc));
}

function getMnemonicEnd( ch )
{
	switch( ch )
    	{
       		case 'b': case 'B': return " (Beta)";
		case 'd': return " (Debug)";
		case 'x': case 'X': case 'Z': return " (Dev)";
	}
	return("");
}

rTorrentStub.prototype.getClientVersion = function( origStr )
{
	var ret = null;
	var str = unescape(origStr);
	if(str.match(/^-[A-Z~][A-Z~][A-Z0-9][A-Z0-9]..-/i))
	{
	        var sign = str.substr(1,2);
		var cli = azLikeClientsSpec[sign];
		if(cli)
		{
			if((sign=='UT') || (sign=='UM'))
				ret = cli+" "+str.charAt(3)+"."+str.charAt(4)+"."+str.charAt(5)+getMnemonicEnd(str.charAt(6));
			else
			if(sign=='TR')
			{
				if(str.substr(3,2)=='00')
				{
					if(str.charAt(5)=='0')
						ret = cli+" 0."+str.charAt(6);
					else
						ret = cli+" 0."+parseInt(str.substr(5,2),10);
				}
				else
				{
					var ch = str.charAt(6);
					ret = cli+" "+str.charAt(3)+"."+parseInt(str.substr(4,2),10);
					if((ch=='Z') || (ch=='X')) 
						ret+='+';
				}
			}
			else
			if(sign=='KT')
			{
				var ch = str.charAt(5);
                                if( ch == 'D' )
					ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+" Dev "+shChar(str.charAt(6));
			        else
			        if( ch == 'R' )
				        ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+" RC "+shChar(str.charAt(6));
				else
					ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+shChar(str.charAt(5));
			}
			else
			if(sign=='AZ') 
			{
				if(str.charAt(3) > '3' || ( str.charAt(3) == '3' && str.charAt(4) >= '1' ))
					cli = "Vuze";
				ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+shChar(str.charAt(5))+"."+shChar(str.charAt(6));
			}
			else
			if((sign=='BF') || (sign=='LW'))
				ret = cli;
			else
			if(sign=='BB')
				ret = cli+" "+str.charAt(3)+"."+str.charAt(4)+str.charAt(5)+str.charAt(6);
			else
			if(sign=='BR')
				ret = cli+" "+str.charAt(3)+"."+str.charAt(4)+" ("+str.charAt(5)+str.charAt(6)+")";
			else
			if(sign=='CT') 
				ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+parseInt(str.substr(5,2),10);
			else
			if(sign=='XX')
				ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+" ("+parseInt(str.substr(5,2),10)+")";
		}
		else
		{
			cli = azLikeClients4[sign];
			if(cli)
				ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+shChar(str.charAt(5))+"."+shChar(str.charAt(6));
			else
			{
				cli = azLikeClients3[sign];
				if(cli)
					ret = cli+" "+shChar(str.charAt(3))+"."+shChar(str.charAt(4))+"."+shChar(str.charAt(5));
				else
				{
					cli = azLikeClients2x2[sign];
					if(cli)
						ret = cli+" "+parseInt(str.substr(3,2),10)+"."+parseInt(str.substr(5,2),10);
				}
			}
		}
	}
	if(!ret)
	{
		if(str.match(/^[MQ]\d-\d[\-\d][\-\d][\-\d]-/))
		{
			ret = (str.charAt(0)=='Q') ? "Queen Bee " : "BitTorrent ";
			if(str.charAt(4) == '-')
				ret += (str.charAt(1)+"."+str.charAt(3)+"."+str.charAt(5));
			else
				ret += (str.charAt(1)+"."+str.charAt(3)+str.charAt(4)+"."+str.charAt(6));
		}
	        else
		if(str.match(/^-BOW/))
		{
			if( str.substr(4,4)=="A0B" ) 
	    			ret = "Bits on Wheels 1.0.5";
			else
			if( str.substr(4,4)=="A0C" ) 
	    			ret = "Bits on Wheels 1.0.6";
			else
				ret = "Bits on Wheels "+str.charAt(4)+"."+str.charAt(5)+"."+str.charAt(6);
		}
		else
		if(str.match(/^AZ2500BT/))
			ret = "BitTyrant (Azureus Mod)";
		else
		if(str.match(/^-FG\d\d\d\d/))
			ret = "FlashGet "+parseInt(str.substr(3,2),10)+"."+parseInt(str.substr(5,2),10);
		else
		if(str.match(/^346-/))
			ret = "TorrenTopia";
		else
		if(str.match(/^-G3/))
			ret = "G3 Torrent";
		else
		if(str.match(/^LIME/))
			ret = "Limewire";
		else
		if(str.match(/^martini/))
			ret = "Martini Man";
		else
		if(str.match(/^Pando/))
			ret = "Pando";
		else
		if(str.match(/^a0[02]---0/))
			ret = "Swarmy";
		else
		if(str.match(/^10-------/))
			ret = "JVtorrent";
		else
		if(str.match(/^eX/))
			ret = "eXeem";
		else
		if(str.match(/^-aria2-/))
			ret = "aria2";
		else
		if(str.match(/^S3-.-.-./))
			ret = "Amazon S3 "+str.charAt(3)+"."+str.charAt(5)+"."+str.charAt(7);
		else
		if(str.match(/^OP/))
			ret = "Opera (build "+str.substr(2,4)+")";
		else
		if(str.match(/^-ML/))
			ret = "MLDonkey "+str.substr(3,5);
		else
		if(str.match(/^AP/))
			ret = "AllPeers "+str.substr(2,4);
		else
		if(str.match(/^DNA\d\d\d\d\d\d/))
			ret = "BitTorrent DNA "+parseInt(str.substr(3,2),10)+"."+parseInt(str.substr(5,2),10)+"."+parseInt(str.substr(7,2),10);
		else
		if(str.match(/^Plus/))
			ret = "Plus! v2 "+str.charAt(4)+"."+str.charAt(5)+str.charAt(6);
		else
		if(str.match(/^XBT\d\d\d/))
			ret = "XBT Client "+str.charAt(3)+"."+str.charAt(4)+"."+str.charAt(5)+getMnemonicEnd(str.charAt(6));
		else
		if(str.match(/^Mbrst/))
			ret = "burst! "+str.charAt(5)+"."+str.charAt(7)+"."+str.charAt(9);
		else
		if(str.match(/^btpd/))
			ret = "BT Protocol Daemon "+str.charAt(5)+str.charAt(6)+str.charAt(7);
		else
		if(str.match(/^BLZ/))
			ret = "Blizzard Downloader "+(str.charCodeAt(3)+1)+"."+str.charCodeAt(4);
		else
		if(str.match(/.*UDP0$/))
			ret = "BitSpirit";
		else
		if(str.match(/^QVOD/))
			ret = "QVOD "+str.charAt(4)+"."+str.charAt(5)+"."+str.charAt(6)+"."+str.charAt(7);
		else
		if(str.match(/^-NE/))
			ret = "BT Next Evolution "+str.charAt(3)+"."+str.charAt(4)+"."+str.charAt(5)+"."+str.charAt(6);
		else
		{
			var mod = null;
			if(str.match(/^exbc/))
				mod = '';
			else
			if(str.match(/^FUTB/))
				mod = '(Solidox Mod) ';
			else
			if(str.match(/^xUTB/))
				mod = '(Mod 2) ';
			if(mod!=null)
			{
				var isBitlord = (str.substr(6,4)=="LORD");
				var name = isBitlord ? "BitLord " : "BitComet ";
				var major = str.charCodeAt(4);
				var minor = str.charCodeAt(5);
				var sep = ".";
				if(!(isBitlord && major>0) && (minor<10))
					sep+="0";
				ret = name+mod+major+sep+minor;
			}
		}
	}
	if(!ret)
	{
		if(str.match(/^[A-Z]([A-Z0-9\-\.]{1,5})/i))
		{
			var cli = shLikeClients[str.charAt(0)];
			if(cli)
				ret = cli+" "+shChar(str.charAt(1))+"."+shChar(str.charAt(2))+"."+shChar(str.charAt(3));
		}
	}
	return(ret ? ret : "Unknown ("+origStr+")");
}

rTorrentStub.prototype.getResponse = function(xmlDoc,docText) 
{
	var ret = "";
	if(this.responseMustBeXML)
	{
		if(!xmlDoc)
		{
			return(ret);
		}
		var fault = xmlDoc.getElementsByTagName('fault');
		if(fault && fault.length)
		{
			var names = xmlDoc.getElementsByTagName('value');
			this.faultString = "XMLRPC Error: "+this.getValue(names,1)+' - '+this.getValue(names,2); 
		}
	}
	if(!this.isError())
	{
		if(eval('typeof(this.'+this.action+'Response) != "undefined"'))
			eval("ret = this."+this.action+"Response(xmlDoc,docText)");
	}
	return(ret);
}

rTorrentStub.prototype.getpluginsResponse = function(xmlDoc,docText)
{
	eval(docText);
	return(false);
}

rTorrentStub.prototype.setprioResponse = function(xmlDoc,docText)
{
	return(this.hashes[0]);
}

rTorrentStub.prototype.getuisettingsResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	var cookie = '{}';
	if(datas && datas.length && datas[0].childNodes.length)
		cookie = datas[0].childNodes[0].data;
	return( '{"":"","settings": [\r\n'+
		'["webui.enable",0,"1"]\r\n'+
		',["webui.cookie",0,"'+
		parseCookie(cookie)+
		'"]\r\n'+
		']\r\n}' );
}

rTorrentStub.prototype.getpropsResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	var data = datas[0];
	var values = data.getElementsByTagName('value');

	var ret = '{"":"","props": [ {"hash": "';
	ret+=this.hashes[0];
	ret+='", "pex": ';
	if(this.getValue(values,11)!='0')
		ret+='-1';
	else
		ret+=this.getValue(values,1);
	ret+=', "peers_max": ';
	ret+=this.getValue(values,3);
	ret+=', "peers_min": ';
	ret+=this.getValue(values,5);
	ret+=', "tracker_numwant": ';
	ret+=this.getValue(values,7);
	ret+=', "ulslots": ';
	ret+=this.getValue(values,9);
	ret+=', "superseed": ';
	ret+=(this.getValue(values,13)=="initial_seed") ? '1' : '0';
	ret+='}]}';
	return(ret);
}

rTorrentStub.prototype.gettotalResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	var data = datas[0];
	var values = data.getElementsByTagName('value');
	return( '{"":"","total": [ '+this.getValue(values,1)+','+this.getValue(values,3)+','+this.getValue(values,5)+','+this.getValue(values,7)+' ]}' );
}

rTorrentStub.prototype.getsettingsResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	var data = datas[0];
	var values = data.getElementsByTagName('value');
	var ret = '{"":"","settings": [["dht",0,"';

	var i = 5;
	var dht_active = this.getValue(values,2);
	var dht = this.getValue(values,3);
	if(dht_active!='0')
	{
		i+=11;
		dht = this.getValue(values,7);
	}
	if((dht=="auto") || (dht=="on"))
		ret+='1';
	else
		ret+='0';				
	ret+='"]';

	for(;i<255; i++)
	{
		var s = this.getValue(values,i).replace(/(^\s+)|(\s+$)/g, "");
		if(s.length)
			break;
	}

	for( var cmd in this.cmdInfoArray )
	{
		ret+='\r\n,["'+this.cmdInfoArray[cmd]+'",0,"'+this.getValue(values,i)+'"]';
		i+=2;
	}
	ret+=']}';
	return(ret);
}

rTorrentStub.prototype.getfilesResponse = function(xmlDoc,docText)
{
	var ret = '{"":"","files": ["';
	ret+=this.hashes[0];
	ret+='", [';
	var datas = xmlDoc.getElementsByTagName('data');
	for(var j=1;j<datas.length;j++)
	{
		var data = datas[j];
		var values = data.getElementsByTagName('value');
		var item = '["';
		item+=this.getValue(values,0);	// f.get_path
		item+='",';
		var get_size_bytes = parseInt(this.getValue(values,3));	// f.get_size_bytes
		item+=get_size_bytes;
		item+=',';
		var get_size_chunks = parseInt(this.getValue(values,2));	// f.get_size_chunks
		var get_completed_chunks = parseInt(this.getValue(values,1));	// f.get_completed_chunks
		var get_completed_bytes = (get_size_chunks==0) ? 0 : get_size_bytes/get_size_chunks*get_completed_chunks;
		item+=get_completed_bytes;
		item+=',';
		item+=this.getValue(values,4);	// f.get_priority
		item+=']';
		if(j>1)
			item=','+item;
		ret+=item;
	}
	ret+=']]}';
	return(ret);
}

rTorrentStub.prototype.getpeersResponse = function(xmlDoc,docText)
{
        var ret = '{"":"","peers": [';
	var datas = xmlDoc.getElementsByTagName('data');
	for(var j=1;j<datas.length;j++)
	{
		var data = datas[j];
		var values = data.getElementsByTagName('value');
		var item = '["';
		item+=this.getValue(values,0);	//	p.get_id
		item+='","';
		item+=this.getValue(values,1);	//	p.get_address
		item+='","';
		var cv = this.getValue(values,2);
		var mycv = this.getClientVersion(this.getValue(values,11));
		if((mycv.indexOf("Unknown")>=0) && (cv.indexOf("Unknown")<0))
			mycv = cv;
		item+=mycv;
		item+='","';
		if(this.getValue(values,3)==1)	//	p.is_incoming
			item+='I';
		if(this.getValue(values,4)==1)	//	p.is_encrypted
			item+='E';
		if(this.getValue(values,5)==1)	//	p.is_snubbed
			item+='S';
		item+='",';
		item+=this.getValue(values,6);	//	get_completed_percent
		item+=',';
		item+=this.getValue(values,7);	//	p.get_down_total
		item+=',';
		item+=this.getValue(values,8);	//	p.get_up_total
		item+=',';
		item+=this.getValue(values,9);	//	p.get_down_rate
		item+=',';
		item+=this.getValue(values,10);	//	p.get_up_rate

		item+=']';
		if(j>1)
			item=','+item;
		ret+=item;
	}
	ret+=']}';
	return(ret);
}

rTorrentStub.prototype.gettrackersResponse = function(xmlDoc,docText)
{
	var ret = '{"":"","trackers": ["';
	ret+=this.hashes[0];
	ret+='", [';
	var datas = xmlDoc.getElementsByTagName('data');
	for(var j=1;j<datas.length;j++)
	{
		var data = datas[j];
		var values = data.getElementsByTagName('value');
		var item = '["';
		item+=this.getValue(values,0);
		item+='",';
		item+=this.getValue(values,1);
		item+=',';
		item+=this.getValue(values,2);
		item+=',';
		item+=this.getValue(values,3);
		item+=',';
		item+=this.getValue(values,4);
		item+=',';
		item+=this.getValue(values,5);
		item+=',';
		item+=this.getValue(values,6);
		item+=']';
		if(j>1)
			item=','+item;
		ret+=item;
	}
	ret+=']]}';
	return(ret);
}

rTorrentStub.prototype.getalltrackersResponse = function(xmlDoc,docText)
{
        var allDatas = xmlDoc.getElementsByTagName('data');
	var ret = '{"":"","trackers": [';
	var cnt = 0;
	for( var i=0; i<this.hashes.length; i++)
	{
		if(i>0)
			ret=ret+',';
		ret+='"';
		ret+=this.hashes[i];
		ret+='", [';
		var datas = allDatas[cnt].getElementsByTagName('data');
		for(var j=0;j<datas.length;j++)
		{
			var data = datas[j];
			var values = data.getElementsByTagName('value');
			var item = '["';
			item+=this.getValue(values,0);
			item+='",';
			item+=this.getValue(values,1);
			item+=',';
			item+=this.getValue(values,2);
			item+=',';
			item+=this.getValue(values,3);
			item+=',';
			item+=this.getValue(values,4);
			item+=',';
			item+=this.getValue(values,5);
			item+=',';
			item+=this.getValue(values,6);
			item+=']';
			if(j>1)
				item=','+item;
			ret+=item;
		}
		cnt+=(datas.length+1);
		ret+=']';
	}
	ret+=']}';
	return(ret);
}

var dStatus = { started : 1, paused : 2, checking : 4, hashing : 8, error : 16 };

rTorrentStub.prototype.getAdditionalResponseForListItem = function(values)
{
	return("");
}

rTorrentStub.prototype.listResponse = function(xmlDoc,docText)
{
	var ret = '';
	var datas = xmlDoc.getElementsByTagName('data');
	var no = 0;
	labels = new Object();
	for(var j=1;j<datas.length;j++)
	{
		var data = datas[j];
		var values = data.getElementsByTagName('value');
		var item = '["';
		item+=this.getValue(values,0);	// "97244485C282F5266EF24D8D4B792C0A89E49DC5"
		item+='",';
		var state = 0;

		var get_state = this.getValue(values,4);
		var is_open = this.getValue(values,1);
		var is_hash_checked = this.getValue(values,3);
		var is_hash_checking = this.getValue(values,2);
		var get_hashing = this.getValue(values,24);
		var is_active = this.getValue(values,29);
		var msg = this.getValue(values,30);

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
		if(msg.length && msg!="Tracker: [Tried all trackers.]")
			state|=dStatus.error;
		item+=state;			// 201
		item+=',';
		item+='"';
		item+=this.getValue(values,5);	// "Lost 1 - 1TV - LostFilm.TV"
		item+='",';
		item+=this.getValue(values,6);	// 9206970368
		item+=',';
		var get_completed_chunks = parseInt(this.getValue(values,7));
		var get_hashed_chunks = parseInt(this.getValue(values,25));
		var get_size_chunks = parseInt(this.getValue(values,8));
		var chunks_processing = (is_hash_checking==0) ? get_completed_chunks : get_hashed_chunks;
		var percent = Math.floor(chunks_processing/get_size_chunks*1000);
		item+=percent;
		item+=',';
		item+=this.getValue(values,9);
		item+=',';
		item+=this.getValue(values,10);
		item+=',';
		item+=this.getValue(values,11);
		item+=',';
		item+=this.getValue(values,12);
		item+=',';
		var get_down_rate = this.getValue(values,13);
		item+=get_down_rate;
		item+=',';
		var get_chunk_size = parseInt(this.getValue(values,14));
		if(get_down_rate>0)
			item+=Math.floor((get_size_chunks-get_completed_chunks)*get_chunk_size/get_down_rate);
		else
			item+='-1';
		item+=',"';
		var label = decodeURIComponent(this.getValue(values,15));
		label = label.replace(/(^\s+)|(\s+$)/g, "");
		label = parseQuote(label.replace(/\"/g, "'"));
		if(label.length>0)
		{
			if(typeof (labels[label]) == "undefined")
				labels[label] = 1;
			else
				labels[label]++;
		}
		item+=label;
		item+='",';
		item+=this.getValue(values,16);
		item+=',';
		var get_peers_not_connected = parseInt(this.getValue(values,17));
		var get_peers_connected = parseInt(this.getValue(values,18));
		var get_peers_all = get_peers_not_connected+get_peers_connected;
		item+=get_peers_all;
		item+=',';
		item+=this.getValue(values,19);
		item+=',';
		item+=get_peers_all;
		item+=',';
		item+=this.getValue(values,21);
		item+=',';
		item+=this.getValue(values,27); // get_creation_date
		item+=',';
		item+=this.getValue(values,20);
		item+=',';
		item+=this.getValue(values,22);	// state_changed
		item+=',';
		item+=this.getValue(values,23); // skip total
		item+=',"';

		item+=this.getValue(values,26); // get_base_path
		item+='",';
		item+=this.getValue(values,27); // get_creation_date
		item+=',';
		item+=this.getValue(values,28); // get_tracker_focus
		item+=',"';
		item+=msg; 
		item+='","';
		var comment = this.getValue(values,31);
		if(comment.search("VRS24mrker")==0)
			comment = parseQuote(decodeURIComponent(comment.substr(10)));
		item+=comment; // custom2
		item+='","';

		item+=this.getValue(values,32); // get_free_diskspace
		item+='"';

		item+=this.getAdditionalResponseForListItem(values);

		item+=']';
		if(j>1)
			item=','+item;
		ret+=item;
	}
	var labelS = '"label": [';
	for(var i in labels) 
	{
		var s = '["'+i+'",'+labels[i]+']';
		if(labelS.length>10)
			labelS += ',';
		labelS += s;
	}
	labelS += '],';
	ret = '{"":"",'+labelS+'"torrents": [\r\n'+ret;
	ret += '],\r\n"torrentc": "0","messages": [\r\n]\r\n}';
	return(ret);
}

rTorrentStub.prototype.isError = function()
{
	return(this.faultString != null);
}

rTorrentStub.prototype.getErrorMsg = function()
{
	return(this.faultString);
}

var indState = 0;
var showTimer = null;
function markRequestStart()
{
	if(indState<=0)
	{
		showTimer = window.setTimeout("Show('ind')", 500);
		indState=0;
	}
	indState = indState+1;
}

function markRequestFinish()
{
	indState = indState-1;
	if(indState<=0)
	{
		if(showTimer)
		{
			window.clearTimeout(showTimer);
			showTimer = null;
		}
		Hide('ind');
		indState=0;
	}
}

function Ajax(URI, httpMethod, isASync, onComplete, onTimeout, reqTimeout) 
{
	markRequestStart();
	var aj = this;
	aj.aborted = false;
	aj.obj = getHttpObj();
	aj.onComplete = onComplete;
	aj.onTimeout = onTimeout;
	if(!aj.obj) 
	      return;
	isASync = (isASync == null) ? true : isASync;
	reqTimeout = reqTimeout || 10000;
	aj.stub = new rTorrentStub(URI);
	if(!aj.stub.content)
	{
		log(aj.stub.action+" is unimpemented yet. ("+aj.stub.URI+")");
	}
	else
	{
		aj.timer = window.setTimeout(
			function() 
			{
				aj.abort();
	      			if(typeof (aj.onTimeout) == "function") 
      				{
				         aj.onTimeout(); 
				}
      				aj.cleanup(); 
	      		}, reqTimeout);
	   	if(isASync == true) 
   		{
      			aj.obj.onreadystatechange = 
      				function() 
	      			{
					if((typeof aj == "undefined") || 
						(typeof aj.obj == "undefined") ||
						aj.aborted)
					{
						return;
					}
		      			if(aj.obj.readyState == 4 || aj.obj.readyState=="complete")
		      			{
						window.clearTimeout(aj.timer);
						if(typeof aj.obj.status == "undefined")
						{
							return;
						}
						if((aj.obj.status > 600) || (aj.obj.status==0))
						{
							aj.cleanup();
							new Ajax(URI, httpMethod, isASync, onComplete, onTimeout, reqTimeout); 
							return;
						}
						if(((aj.obj.status >= 400) || (aj.obj.responseXML==null)) && 
							(typeof aj.obj.responseText != "undefined")) 
						{
        	    					log('Bad response: ('+aj.obj.status+') '+aj.obj.responseText);
        	    				}
	         				if(aj.obj.status != 200) 
        	 				{
	        	 				aj.cleanup();
            						return;
	            				}
						var responseText = aj.stub.getResponse(aj.obj.responseXML,aj.obj.responseText);
						if(aj.stub.isError())
						{
							log(aj.stub.getErrorMsg());
						}
	            				if(aj.stub.listRequired==true)
	            				{
	            					aj.cleanup();
	            					new Ajax("?list=1", httpMethod, isASync, onComplete, onTimeout, reqTimeout);
	            					tul = 0;
	            					tdl = 0;
	            					return;
	            				}
	            				else
	            				{
	            					if(!aj.stub.isError())
	            					{
	        	 					if(typeof (aj.onComplete) == "function") 
		        	 				{
			        	    				aj.onComplete(responseText);
        			    				}
         							else 
         							{
            								if(typeof (aj.onComplete) == "object") 
	            							{
        	       								aj.onComplete[0].apply(aj.onComplete[1], 
	               									new Array(responseText, aj.onComplete[2]));
			        	       				}
        			    					else 
            								{
               									try { eval(responseText); } catch(e) {}
               								}
								}
							}
							aj.cleanup();
	            				}
					}
	         		}
	      	}
		aj.obj.open("POST", aj.stub.mountPoint, isASync);
     		if(typeof (aj.obj.setRequestHeader) != "undefined")
		{
	        	aj.obj.setRequestHeader("Content-Type",aj.stub.contentType);
//	        	if((window.location.protocol=="https:") && browser.isAppleWebKit)
//			{
//		        	aj.obj.setRequestHeader("Connection","close");
//			}
		}
   		aj.obj.send(aj.stub.content);
	   	if(isASync == false) 
   		{
			window.clearTimeout(aj.timer);
			if(aj.obj.status > 600 || (aj.obj.status==0))
			{
				aj.cleanup();
				new Ajax(URI, httpMethod, isASync, onComplete, onTimeout, reqTimeout); 
				return;
			}
	      		if(aj.obj.status >= 400) 
      			{
         			alert(aj.obj.responseText && 
					aj.obj.responseText.length ? aj.obj.responseText : "Server say: "+aj.obj.status);
	         	}
      			if(aj.obj.status != 200) 
	      		{
				aj.cleanup();
        	 		return;
         		}
			var responseText = aj.stub.getResponse(aj.obj.responseXML,aj.obj.responseText);
			if(aj.stub.isError())
			{
				alert(aj.stub.getErrorMsg());
			}
			if(aj.stub.listRequired==true)
			{
				aj.cleanup();
				new Ajax("?list=1", httpMethod, isASync, onComplete, onTimeout, reqTimeout);
				return;
			}
			else
			{
				if(!aj.stub.isError())
				{
	      				if(typeof (aj.onComplete) == "function") 
      					{
		         			aj.onComplete(responseText);
		        	 	}
			      		else 
      					{
         					if(typeof (aj.onComplete) == "object") 
         					{
							aj.onComplete[0].apply(aj.onComplete[1], 
								new Array(responseText));
	        		    		}
        	 				else 
	        	 			{
		        	    			eval(responseText);
        		    			}
					}
	         		}
				aj.cleanup();
	      		}
		}
	}
}

Ajax.prototype.abort = function()
{
	this.aborted = true;
	if(this.obj)
		this.obj.abort(); 
}

Ajax.prototype.cleanup = function()
{
	if(this.obj)
	{
		delete this.obj.onreadystatechange;
		delete this.obj;
	}
	if(this.timer)
	{
		window.clearTimeout(this.timer);
		delete this.timer;
	}
	markRequestFinish();
}
