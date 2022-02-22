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

plugin.origopn = rTorrentStub.prototype.getopen;
rTorrentStub.prototype.getopen = function()
{
	this.getCommon("opn");
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

plugin.onRemove = function()
{
	theRequestManager.cid = 0;
	theURLs.XMLRPCMountPoint = plugin.XMLRPCMountPoint;
}
