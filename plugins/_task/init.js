plugin.loadLang();
plugin.loadMainCSS();

plugin.foreground = {};
plugin.background = {};
plugin.running = 0;

theWebUI.startConsoleTask = function( taskName, requesterName, parameters, options )
{
	plugin.clearForeTimeout();
	plugin.foreground = 
	{
		no: 0,
		pid: 0,
		status: -1,
		errors: 0,
		start: 0,
		finish: 0,
		log: 0,
		name: taskName,
		tskcmderrors: 0,	// errors crc
		tskcmdlog: 0,		// log crc
		requester: requesterName,
		params: parameters,
		options: options || {}
	};
	plugin.start();
}

theWebUI.getConsoleTask = function()
{
	return(plugin.foreground);
}

theWebUI.setConsoleTaskState = function( options )
{
	for( var name in options )
	{
		switch(name)
		{
			case 'showerrors':
			{
				plugin.setConsoleControls(options[name]);
				break;
			}
		}
	}
}

//			

plugin.start = function()
{
	$('#tskcmdlog').empty();
	$('#tskcmderrors').empty();
	theDialogManager.setModalState();
	var req = '';
	for( var i in this.foreground.params )
	{
		var parameter = this.foreground.params[i];
		switch($type(parameter))
		{
			case "array":
			{
				for( var k in parameter )
					req+=('&v='+i+'[]&s='+encodeURIComponent(parameter[k]));
				break;					
			}
			case "object":
			{
				for( var property in  parameter )
				{
					if( parameter.hasOwnProperty(property) )
						req+=('&v='+i+'['+property+']&s='+encodeURIComponent(parameter[property]));
				}						
				break;					
			}			
			default:
			{
				req+=('&v='+i+'&s='+encodeURIComponent(parameter));
				break;
			}
		}							
	}
	theWebUI.requestWithoutTimeout("?action=taskstart&hash="+this.foreground.name+"&hash="+this.foreground.requester+req,[this.onStart, this]);
}

plugin.clear = function()
{
	this.clearForeTimeout();
	this.foreground.no = 0;
	this.foreground.pid = 0;
	this.foreground.status = -1;
	this.foreground.tskcmderrors = 0;
	this.foreground.tskcmdlog = 0;
}

plugin.callNotification = function( type, data, status )
{
	var ret = null;
	type = "onTask"+type;
	var requester = thePlugins.get(this.foreground.requester);
	if(requester && $type(requester[type])=="function")
		ret = requester[type](data || this.foreground, status || false);
	return(ret);		
}

plugin.fromBackground = function( no )
{
	this.foreground = cloneObject(this.background[no]);
	this.foreground.options = {};
	this.foreground.tskcmderrors = 0;
	this.foreground.tskcmdlog = 0;	
	$('#tskcmdlog').empty();
	$('#tskcmderrors').empty();
	plugin.onStart(this.foreground); 
}

plugin.toBackground = function()
{
       	if(!plugin.isInBackground())
		plugin.background[ plugin.foreground.no ] = cloneObject( this.foreground );
	plugin.callNotification("HideInterface");	
	plugin.clear();			
	theDialogManager.hide('tskConsole');	
	plugin.refreshTasks();
}

plugin.shutdown = function()
{
	this.kill();		
	this.callNotification("HideInterface");
	this.callNotification("Shutdown");
	this.clear();
}

plugin.clearForeTimeout = function()
{
	if(this.foreTimeout)
	{
		window.clearTimeout(this.foreTimeout);
		this.foreTimeout = null;
	}
}

plugin.clearBackTimeout = function()
{
	if(this.backTimeout)
	{
		window.clearTimeout(this.backTimeout);
		this.backTimeout = null;
	}
}

plugin.onStart = function(data)
{
        theDialogManager.clearModalState();
	if(data.status || this.foreground.options.noclose || this.isInBackground())
	{
		if(!this.isInBackground())
			plugin.refreshTasks();
		plugin.callNotification("ShowInterface",$.extend(this.foreground,data));	
	        $("#tskConsole-header").html(theUILang.tskCommand);
	        theDialogManager.show("tskConsole");
		this.check(data);
	}
	else
		if(!data.status)
		{
			this.foreground.no = data.no;
			this.kill();		
			this.callNotification("Shutdown");
			this.clear();
		}			
}

plugin.check = function(data)
{
	this.clearForeTimeout();
        this.foreground.no = data.no;
        this.foreground.pid = data.pid;
	this.foreground.status = data.status;
	this.foreground.params = data.params;
	this.fillConsole('tskcmdlog',data.log);
	this.setConsoleControls( this.fillConsole('tskcmderrors',data.errors) );
	if(this.foreground.status<0)
	{
		var self = this;
		this.foreTimeout = setTimeout( function() 
		{
			theWebUI.requestWithoutTimeout("?action=taskcheck&hash="+self.foreground.no,[self.check,self]);
		}, theWebUI.settings["webui.update_interval"]);
	}
	else
	{
		plugin.callNotification("Finished");
		if(!this.foreground.status && !this.foreground.options.noclose && !this.isInBackground())
			theDialogManager.hide("tskConsole");
	}
}

plugin.isActive = function()
{
	return(plugin.foreground.pid && (plugin.foreground.status<0))
}

plugin.isInBackground = function()
{
	return($type(plugin.background[plugin.foreground.no]));
}

plugin.kill = function()
{
	theWebUI.requestWithoutTimeout("?action=taskkill&hash="+this.foreground.no);
	if(this.foreground.status<0)
		plugin.callNotification("Finished");
}

plugin.setConsoleControls = function( errPresent )
{
	$('#tskBackground').prop( 'disabled', !plugin.canDetachTask() );
	if(plugin.foreground.status>=0)
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
		var s = '';
		var requester = thePlugins.get(this.foreground.requester);
		for(var i = 0; i<arr.length; i++)
			s += (requester && $type(requester["onTaskShowLog"])=="function") ? 
				requester.onTaskShowLog(this.foreground,arr[i],id,i) : escapeHTML(arr[i])+'<br>';
		var crc = getCRC( s, 0 );
		if(plugin.foreground[id]!=crc)
		{
			plugin.foreground[id] = crc;
			if(browser.isKonqueror)
				s = '<br>'+s;
			$('#'+id).html(s);
			if(!this.foreground.options.noclose)
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

rTorrentStub.prototype.taskremove = function()
{
	this.content = "cmd=remove";
	for(var i=0; i<this.hashes.length; i++)
		this.content += ('&no='+this.hashes[i]);	
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/_task/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.tasklist = function()
{
	this.content = "cmd=list";
        this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/_task/action.php";
	this.dataType = "json";
}

if(plugin.canChangeTabs())
{
	plugin.tasksConfig = theWebUI.config;
	theWebUI.config = function(data)
	{
        	plugin.attachPageToTabs($('<div>').attr("id","tasks").addClass("table_tab stable").get(0),"Tasks","lcont");
		theWebUI.tables["tasks"] =  
		{
        		obj:		new dxSTable(),
			container:	"tasks",
			columns:	
			[
				{ text: theUILang.Name, 		width: "100px", id: "name",		type: TYPE_STRING }, 
				{ text: "Plugin",	 		width: "100px", id: "plugin",		type: TYPE_STRING },
				{ text: "Parameter",	 		width: "200px", id: "arg",		type: TYPE_STRING },				
				{ text: theUILang.Status, 		width: "100px", id: "status",		type: TYPE_NUMBER }, 
				{ text: "Started", 			width: "110px", id: "start",		type: TYPE_NUMBER }, 
				{ text: "Elapsed", 			width: "110px", id: "elapsed",		type: TYPE_NUMBER }, 			
				{ text: "Finished", 			width: "110px", id: "finish",		type: TYPE_NUMBER }
			],
       		        onselect:	function(e,id) { this.tasksSelect(e,id) },
       		        ondelete:	function() { this.tasksRemove(); },
       		        ondblclick:	function(obj) 
       		        { 
       		        	var id = obj.id.substr(6);
				plugin.fromBackground(id);
       		        	return(false); 
       		        },
       	        	format:	function(table,arr)
			{
				for(var i in arr)
				{
					if(arr[i]==null)
						arr[i] = '';
					var s = table.getIdByCol(i);
					switch(s)
					{
						case 'finish':
						case 'start':
						{
							arr[i] = (arr[i]>3600*24*365) ? theConverter.date(iv(arr[i])+theWebUI.deltaTime/1000) : "";
							break;
						}							
						case 'elapsed':
						{
							arr[i] = arr[i] ? theConverter.time(iv(arr[i]),true) : "";
							break;
						}
						case 'status':
						{
							arr[i] = (arr[i]<0) ? theUILang.tskRunning : (arr[i]>0) ? theUILang.Error : theUILang.ok;
							break;
						}
					}													
				}
				return(arr);
			}
		};
		plugin.tasksConfig.call(theWebUI,data);	
		if(!plugin.showTabAlways)
		{
			$('li#tab_tasks').hide();		
			$(theWebUI.tables["tasks"].container).hide();
		}			
		plugin.renameTasksStuff();
	}	
}

plugin.renameTasksStuff = function()
{
	if(plugin.allStuffLoaded)
	{
		plugin.renameTab("tasks",theUILang.tskTasks);
		var table = theWebUI.getTable("tasks");
		table.renameColumnById("start",theUILang.tskStart);
		table.renameColumnById("finish",theUILang.tskFinish);
		table.renameColumnById("elapsed",theUILang.tskElapsed);
		table.renameColumnById("plugin",theUILang.tskPlugin);
		table.renameColumnById("arg",theUILang.tskArg);		
		$("#tasks .stable-body").mouseclick( function(e)
		{
			if((e.which==3) && plugin.allStuffLoaded && plugin.canChangeMenu())
			{
				table.tasksSelect(e,null);
				return(true);
			}	
			return(false);
		});		
		plugin.refreshTasks();
	}
	else
		setTimeout(arguments.callee,1000);
}

plugin.refreshTasks = function()
{
	plugin.clearBackTimeout();
	theWebUI.requestWithoutTimeout("?action=tasklist",[plugin.onGetTasks,plugin]);	
}

dxSTable.prototype.tasksRemove = function()
{
	if(theWebUI.settings["webui.confirm_when_deleting"])
		askYesNo( theUILang.tskDelete, theUILang.tskDeletePrompt, "theWebUI.getTable('tasks').tasksRemovePrim()" );
	else
		this.tasksRemovePrim();
}

dxSTable.prototype.tasksRemovePrim = function(cmd)
{
	var req = '';
	for( var k in this.rowSel )
	{
		if( this.rowSel[k] )
		{
			var id = k.substr(6);
			req+=("&hash=" + id);
			if(id==plugin.foreground.no)
				plugin.callNotification("HideInterface");
			plugin.callNotification("Shutdown", plugin.background[id]);
		}
	}
	if(req.length)
		theWebUI.request("?action=taskremove"+req,[plugin.onGetTasks, plugin]);
	plugin.clear();		
	theDialogManager.hide('tskConsole');		
}

dxSTable.prototype.tasksSelect = function(e,id)
{
	if(plugin.enabled && plugin.allStuffLoaded && (e.which==3))
	{
		theContextMenu.clear();
		if(this.selCount)
		{
			id = this.getFirstSelected().substr(6);
			theContextMenu.add([theUILang.tskActivate, this.selCount==1 ? function() 
			{
				plugin.fromBackground( id );
			} : null ]);
		}			
		theContextMenu.add([theUILang.tskRemove, this.selCount ? "theWebUI.getTable('tasks').tasksRemove()" : null ]); 
		theContextMenu.add([CMENU_SEP]);
		theContextMenu.add([theUILang.tskRefresh, plugin.refreshTasks]);
		theContextMenu.show(e.clientX,e.clientY);
	}
}

plugin.tasksOnShow = theTabs.onShow;
theTabs.onShow = function(id)
{
	if(id=="tasks")
	{
		var table = theWebUI.getTable("tasks");
		if(table)
		{
			table.refreshRows();
			plugin.refreshTasks();
		}
	}
	else
		plugin.tasksOnShow.call(this,id);
};

plugin.resizeBottom = theWebUI.resizeBottom;
theWebUI.resizeBottom = function( w, h )
{
	plugin.resizeBottom.call(theWebUI,w,h);
	if(theWebUI.configured)
	{
	       	if(w!==null)
			w-=8;
		if(h!==null)
       		{
			h-=($("#tabbar").outerHeight());
			h-=2;
        	}
		var table = this.getTable("tasks");
		if(table)
			table.resize(w,h);
	}
}

plugin.canDetachTask = function()
{
	return( !(plugin.foreground.options && plugin.foreground.options.nohide) &&
		((((plugin.running<plugin.maxConcurentTasks) || (plugin.foreground.status>=0)) && (plugin.foreground.pid>0)) 
			|| plugin.isInBackground()) );
}

plugin.onGetTasks = function(d)
{
	if($type(d))
	{
		var updated = false;
		var table = theWebUI.getTable("tasks");

		if( d[ plugin.foreground.no ] && !plugin.isInBackground() )
			delete d[ plugin.foreground.no ];

		plugin.running = 0;

		for( var id in d )
		{
			var item = d[id];
			if(!$type(plugin.background[id]))
			{
				table.addRowById(
				{
					name: item.name,
					status: item.status,
					plugin: item.requester,
					arg: item.params ? item.params['arg'] : '',
					start: item.start,
					elapsed: item.finish ? iv(item.finish)-iv(item.start) : (new Date().getTime()/1000-(iv(item.start)+theWebUI.deltaTime/1000)),
					finish: item.finish
				}, "tasks_"+id, (item.status<0) ? "Status_Down" : (item.status==0) ? "Status_Completed" : "Status_Error" );
				updated = true;
			}
			else
			{
				updated = table.setValuesById("tasks_"+id,
				{
					name: item.name,
					status: item.status,
					plugin: item.requester,
					arg: item.params ? item.params['arg'] : '',					
					start: item.start,
					elapsed: item.finish ? iv(item.finish)-iv(item.start) : (new Date().getTime()/1000-(iv(item.start)+theWebUI.deltaTime/1000)),
					finish: item.finish
				},true) || updated;
				updated = table.setIcon("tasks_"+id,(item.status<0) ? "Status_Down" : (item.status==0) ? "Status_Completed" : "Status_Error") || updated;
			}
			if(item.status<0)
				plugin.running++;
			else
				if(plugin.background[id] && (plugin.background[id].status<0))
					plugin.callNotification("Finished",item,true);
		}
		var deleted = false;
               	for( var id in plugin.background )
		{
			if(!$type(d[id]))
			{
				table.removeRow( "tasks_"+id );
				updated = true;
				deleted = true;
			}
		}
		plugin.background = d;
		if(updated)
		{
			if(deleted)
			{
				table.correctSelection();	
			}
			if(!plugin.isInBackground())
				$('#tskBackground').prop( 'disabled', !plugin.canDetachTask() );
			$('li#tab_tasks').show();
			$(theWebUI.tables["tasks"].container).show();
			table.refreshRows();
			if(table.sIndex !=- 1)
				table.Sort();
		}
	}
	if( ((theWebUI.activeView=='tasks')  && plugin.running ) || plugin.foreground.no ) 
	{
		plugin.clearBackTimeout();
		plugin.backTimeout = setTimeout(plugin.refreshTasks,theWebUI.settings["webui.update_interval"]);
	}		
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
			"<input type='button' id='tskBackground' class='Button' value='"+theUILang.tskBackground+"'/>"+
			"<input type='button' id='tskCancel' class='Cancel Button' value='"+theUILang.Cancel+"'/>"+
		"</div>",true);
	theDialogManager.setHandler('tskConsole','afterHide',function()
	{
		if( plugin.foreground.no )
		{
			if(!plugin.isInBackground())
				plugin.shutdown();
			else
				theWebUI.getTable('tasks').tasksRemovePrim();
		}
	});
	theDialogManager.setHandler('tskConsole','afterShow',function()
	{
		if(!plugin.cHeight)
			plugin.cHeight = $('#tskcmderrors').parent().height();
	});
	$('#tskBackground').click( plugin.toBackground );
	$(".tskconsole").enableSysMenu();
}
