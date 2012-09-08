plugin.loadLang();
plugin.loadMainCSS();

theWebUI.startConsoleTask = function( taskName, requesterName, parameters, options )
{
	plugin.clearTimeout();
	plugin.no = 0;
	plugin.pid = 0;
	plugin.status = -1;
	plugin.tskcmderrors = 0;
	plugin.tskcmdlog = 0;
	plugin.taskName = taskName;
	plugin.requesterName = requesterName;
	plugin.parameters = parameters || {};
	plugin.options = options || {};
	plugin.start();
}

plugin.start = function()
{
	$('#tskcmdlog').empty();
	$('#tskcmderrors').empty();
	theDialogManager.setModalState();
	var req = '';
	for( var i in this.parameters )
	     req+=('&v='+i+'&s='+encodeURIComponent(this.parameters[i]));
	theWebUI.requestWithoutTimeout("?action=taskstart&hash="+this.taskName+"&hash="+this.requesterName+req,[this.onStart, this]);
}

plugin.shutdown = function()
{
	if($type(this.options.onShutdown)=="function")
		this.options.onShutdown(this);
	this.clearTimeout();
	this.no = 0;
	this.pid = 0;
	this.status = -1;
	this.tskcmderrors = 0;
	this.tskcmdlog = 0;
}

plugin.clearTimeout = function()
{
	if(this.timeout)
	{
		window.clearTimeout(this.timeout);
		this.timeout = null;
	}
}

plugin.onStart = function(data)
{
        theDialogManager.clearModalState();
	if(data.status || this.options.noclose)
	{
	        $("#tskConsole-header").html(theUILang.tskCommand);
	        theDialogManager.show("tskConsole");
		this.check(data);
	}
}

plugin.check = function(data)
{
	this.clearTimeout();
        this.no = data.no;
        this.pid = data.pid;
	this.status = data.status;
	this.fillConsole('tskcmdlog',data.log);
	this.setConsoleControls( this.fillConsole('tskcmderrors',data.errors) );
	if(this.status<0)
	{
		var self = this;
		this.timeout = window.setTimeout( function() 
		{
			theWebUI.requestWithoutTimeout("?action=taskcheck&hash="+self.no,[self.check,self]);
		},1000);
	}
	else
	{
		if($type(this.options.onFinished)=="function")
			this.options.onFinished(this);
		if(!this.status && !this.options.noclose)
			theDialogManager.hide("tskConsole");
	}
}

plugin.isActive = function()
{
	return(plugin.pid && (plugin.status<0))
}

plugin.kill = function()
{
	theWebUI.requestWithoutTimeout("?action=taskkill&hash="+this.no);
	if($type(this.options.onFinished)=="function")
		this.options.onFinished(this);
}

plugin.setConsoleControls = function( errPresent )
{
	if(plugin.status>=0)
	{
		$('#tsk_btns').css( "background", "none" );
		$("#tskConsole-header").html(theUILang.tskCommandDone);
	}
	else
		$('#tsk_btns').css( "background", "transparent url(./plugins/_task/images/ajax-loader.gif) no-repeat 5px 7px" );
	if(errPresent)
	{
		$('#tskcmdlog').height(plugin.cHeight-18).parent().height(plugin.cHeight);
		$('#tskcmderrors').show();
		$('#tskcmderrors_set').show();
	}
	else
	{
		$('#tskcmderrors').hide();
		$('#tskcmderrors_set').hide();
		$('#tskcmdlog').height(plugin.cHeight*2+3).parent().height(plugin.cHeight*2+21);
	}
}

plugin.fillConsole = function(id,arr)
{
       	if(arr)
        {
		var s = ''
		for(var i = 0; i<arr.length; i++)
			s += ($type(this.options.onShowLog)=="function") ? this.options.onShowLog(this,arr[i],id,i) : escapeHTML(arr[i])+'<br>';
		var crc = getCRC( s, 0 );
		if(plugin[id]!=crc)
		{
			plugin[id] = crc;
			if(browser.isKonqueror)
				s = '<br>'+s;
			$('#'+id).html(s);
			if(!this.options.noclose)
				$('#'+id)[0].scrollTop = $('#'+id)[0].scrollHeight;
		}
		return(s!='');
	}
	return(false);
}

rTorrentStub.prototype.taskstart = function()
{
	this.content = "cmd="+this.hashes[0];
	for(var i=0; i<this.ss.length; i++)
		this.content += ('&'+this.vs[i]+'='+this.ss[i]);
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/"+this.hashes[1]+"/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.taskcheck = function()
{
	this.content = "cmd=check&no="+this.hashes[0];
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/_task/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.taskkill = function()
{
	this.content = "cmd=kill&no="+this.hashes[0];
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/_task/action.php";
	this.dataType = "json";
}

plugin.onLangLoaded = function()
{
	theDialogManager.make("tskConsole",theUILang.tskCommand,
		"<div class='fxcaret'>"+
			"<fieldset id='tskcmdlog_set'>"+
				"<legend>"+theUILang.tskConsole+"</legend>"+
				"<div class='tskconsole' id='tskcmdlog'></div>"+
			"</fieldset>"+
			"<fieldset id='tskcmderrors_set'>"+
				"<legend>"+theUILang.tskErrors+"</legend>"+
				"<div class='tskconsole' id='tskcmderrors'></div>"+
			"</fieldset>"+
		"</div>"+
		"<div class='aright buttons-list' id='tsk_btns'>"+
			"<input type='button' id='tskCancel' class='Cancel Button' value='"+theUILang.Cancel+"'/>"+
		"</div>",true);
	theDialogManager.setHandler('tskConsole','afterHide',function()
	{
	        if(plugin.isActive())
	        	plugin.kill();
		plugin.shutdown();
	});
	theDialogManager.setHandler('tskConsole','afterShow',function()
	{
		if(!plugin.cHeight)
			plugin.cHeight = $('#tskcmderrors').parent().height();
	});
	$(".tskconsole").enableSysMenu();
}
