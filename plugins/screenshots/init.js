var explorerIsInstalled = thePlugins.isInstalled("explorer");

plugin.loadMainCSS();
plugin.loadLang();
plugin.playTimer = null;

if(plugin.canChangeOptions() && !explorerIsInstalled)
{
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg)
	{
		if(plugin.enabled && plugin.allStuffLoaded)
		{
			$.each( plugin.ffmpegSettings, function(name,val)
			{
				if($('#'+name).is(":checkbox"))
					$('#'+name).prop('checked', val!=0).trigger('change');
				else
					$('#'+name).val(val);
			});
		}
		plugin.addAndShowSettings.call(theWebUI,arg);
	}

	plugin.ffmpegWasChanged = function()
	{
		var ret = false;
		if( plugin.allStuffLoaded )
		{
			$.each( plugin.ffmpegSettings, function(name,val)
			{
				if($('#'+name).is(":checkbox"))
				{
					if($('#'+name).prop('checked')!=val)
					{
						ret = true;
						return(false);
					}
				}
				else
				if($('#'+name).val()!=val)
				{
					ret = true;
					return(false);
				}
			});
		}
		return(ret);
	}

	plugin.ffmpegSet = function(data)
	{
		plugin.ffmpegSettings = data;
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function()
	{
		plugin.setSettings.call(this);
		if(plugin.enabled && plugin.ffmpegWasChanged())
			this.request("?action=setffmpeg",[plugin.ffmpegSet,plugin]);
	}

	rTorrentStub.prototype.setffmpeg = function()
	{
		var s = '';
		$.each( plugin.ffmpegSettings, function(name,val)
		{
			if($('#'+name).is(":checkbox"))
				s+=("&"+name+"="+ ($('#'+name).prop('checked') ? 1 : 0) );
			else
				s+=("&"+name+"="+encodeURIComponent($('#'+name).val()).trim());
		});
		this.content = "cmd=ffmpegset"+s;
	        this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/screenshots/action.php";
		this.dataType = "json";
	}
}

if(plugin.canChangeMenu())
{
	plugin.createFileMenu = theWebUI.createFileMenu;
	theWebUI.createFileMenu = function( e, id )
	{
		if(plugin.createFileMenu.call(this, e, id))
		{
			if(plugin.enabled && plugin.allStuffLoaded)
			{
				var fno = null;
				var table = this.getTable("fls");
				if((table.selCount == 1)  && (theWebUI.dID.length==40))
				{
					var fid = table.getFirstSelected();
					var ext = '';
					var s = table.getRawValue(fid,0);
					var pos = s.lastIndexOf(".");
					if(pos>0)
					{
						ext = s.substring(pos+1);
						s = s.substring(0,pos);
					}
					$('#scimgfile').val(s);
					if(this.settings["webui.fls.view"])
					{
						var arr = fid.split('_f_');
						fno = arr[1];
					}
					else
					if(!this.dirs[this.dID].isDirectory(fid))
						fno = fid.substr(3);
					if($.inArray( ext.toLowerCase(), plugin.extensions )==-1)
						fno = null;
				}
				theContextMenu.add( [theUILang.exFFMPEG,  (fno==null) ? null : "theWebUI.fileFFMPEG('" + theWebUI.dID + "',"+fno+")"] );
			}
			return(true);
		}
		return(false);
	}

	theWebUI.fileFFMPEG = function(hash,no)
	{
	        this.startConsoleTask( "ffmpeg", plugin.name,
	        	{ "hash" : hash, "no" : no },
	        	{ noclose: true });
	}

	plugin.onTaskShowInterface = function(task)
	{
	        $('.scplay').hide();
	        $('#tskcmdlog').addClass('scframe_cont');
	}

	plugin.onTaskShowLog = function(task,line,id,ndx)
	{
		if(id=='tskcmdlog')
		{
			if( !$('#scframe'+ndx+' img').length )
			{
				if(!ndx)
					$('#'+id).empty();
				else
					$('.scframe').hide();
				$('#'+id).append("<div class='scframe' id='scframe"+ndx+"'><img src='plugins/screenshots/action.php?cmd=ffmpeggetimage&no="+task.no+
					"&fno="+line+"&file="+encodeURIComponent($('#scimgfile').val())+"' /></div>");
				$('#scframe'+ndx+' img').on('load', function()
				{
					plugin.centerFrame(ndx);
				});
			}
			return('');
		}
		return(escapeHTML(line)+'<br>');
	}

	plugin.onTaskFinished = function(task,onBackground)
	{
		if(!onBackground)
		{
		        if($('.scframe').length)
			{
				$('.scframe').hide();
				$('#scframe0').show();
			}
			$("#sctaskno").val(task.no);
			plugin.setPlayControls();
		}
	}

	plugin.onTaskHideInterface = function(task)
	{
	        $('.scplay').hide();
		$('#tskcmdlog').removeClass('scframe_cont');
		if(plugin.playTimer)
		{
			window.clearInterval(plugin.playTimer);
			plugin.playTimer = null;
		}
	}

	plugin.setPlayControls = function()
	{
		if($('.scframe').length)
		{
			$('.scplay').show();
			var current = plugin.getCurrentFrame();
			$("#tskConsole-header").html(theUILang.exScreenshot+" "+(current+1)+"/"+$('.scframe').length);
			$("#scplay").val(plugin.playTimer ? "▀" : "►")
			if((current==0) || plugin.playTimer)
			{
				$("#scfirst,#scprev").prop("disabled",true);
				$("#scfirst,#scprev").addClass("disabled");
			}
			else
			{
				$("#scfirst,#scprev").prop("disabled",false);
				$("#scfirst,#scprev").removeClass("disabled");
			}
			if((current==$('.scframe').length-1) || plugin.playTimer)
			{
				$("#sclast,#scnext").prop("disabled",true);
				$("#sclast,#scnext").addClass("disabled");
			}
			else
			{
				$("#sclast,#scnext").prop("disabled",false);
				$("#sclast,#scnext").removeClass("disabled");
			}
			if($('.scframe').length==1)
			{
				$("#scplay,#scsaveall").prop("disabled",true);
				$("#scplay,#scsaveall").addClass("disabled");
			}
			else
			{
				$("#scplay,#scsaveall").prop("disabled",false);
				$("#scplay,#scsaveall").removeClass("disabled");
			}
		}
		else
			$('.scplay').hide();
	}
}

plugin.centerFrame = function(no)
{
	var img = $('#scframe'+no+' img');
	if(img.height())
	{
		var delta = ($('#tskcmdlog').height()-img.height())/2;
		if(delta>0)
			img.parent().css("top",delta);
	}
}


plugin.getCurrentFrame = function()
{
	return($('.scframe:visible').length ? iv($('.scframe:visible').attr("id").substr(7)) : 0);
}

plugin.setNextFrame = function()
{
	plugin.setCurrentFrame( plugin.getCurrentFrame()+1 );
}

plugin.setCurrentFrame = function(no)
{
	if(no<0)
		no = $('.scframe').length-1;
	if(no>=$('.scframe').length)
		no = 0;
	$('.scframe:visible').hide();
	$('#scframe'+no).show();
	plugin.centerFrame(no);
	plugin.setPlayControls();
}

plugin.onLangLoaded = function()
{
	if(!thePlugins.get("_task").allStuffLoaded)
		setTimeout(arguments.callee,1000);
	else
	{
		if(!explorerIsInstalled)
			plugin.attachPageToOptions($("<div>").attr("id","st_screenshots").html(
				"<fieldset>"+
					"<legend>"+theUILang.exFFMPEG+"</legend>"+
					"<table>"+
					"<tr><td><input type=\"checkbox\" id=\"exusewidth\" onchange=\"linked(this, 0, ['exfrmwidth']);\"/><label id='lbl_exfrmwidth' for='exfrmwidth' class='disabled'>"+
						theUILang.exFrameWidth+'</label></td><td class="alr"><input type="text" id="exfrmwidth" class="TextboxShort" disabled="true"/></td></tr>'+
					"<tr><td>"+theUILang.exFramesCount+'</td><td class="alr"><input type="text" id="exfrmcount" class="TextboxShort"/></td></tr>'+
					"<tr><td>"+theUILang.exStartOffset+', '+theUILang.time_s+'</td><td class="alr"><input type="text" id="exfrmoffs" class="TextboxShort"/></td></tr>'+
					"<tr><td>"+theUILang.exBetween+', '+theUILang.time_s+'</td><td class="alr"><input type="text" id="exfrminterval" class="TextboxShort"/></td></tr>'+
					"<tr><td>"+theUILang.exPlayInterval+', '+theUILang.time_s+'</td><td class="alr"><input type="text" id="explayinterval" class="TextboxShort"/></td></tr>'+
					"<tr><td>"+theUILang.exImageFormat+'</td><td class="alr"><select id="exformat" class="TextboxShort">'+
						"<option value='0'>JPEG</option>"+
						"<option value='1'>PNG</option>"+
						'</select></td></tr>'+
					"</table>"+
				"</fieldset>"
				)[0],theUILang.exFFMPEG);
		$('#tsk_btns').prepend(
			"<input type='button' class='Button scplay' id='scfirst' value='<<'>"+
			"<input type='button' class='Button scplay' id='scprev' value='<'>"+
			"<input type='button' class='Button scplay' id='scplay' value='►'>"+
			"<input type='button' class='Button scplay' id='scnext' value='>'>"+
			"<input type='button' class='Button scplay' id='sclast' value='>>'>&nbsp;&nbsp;&nbsp;&nbsp;"+
			"<input type='button' class='Button scplay' id='scsave' value='"+theUILang.exSave+"'>"+
			"<input type='button' class='Button scplay' id='scsaveall' value='"+theUILang.exSaveAll+"'>"
			 );
		$("#scfirst").on('click', function()
		{
			plugin.setCurrentFrame(0);
		});
		$("#sclast").on('click', function()
		{
			plugin.setCurrentFrame($('.scframe').length-1);
		});
		$("#scprev").on('click', function()
		{
			plugin.setCurrentFrame( plugin.getCurrentFrame()-1 );
		});
		$("#scnext").on('click', plugin.setNextFrame );
		$("#scplay").on('click', function()
		{
		        if(plugin.playTimer)
			{
				window.clearInterval(plugin.playTimer);
				plugin.playTimer = null;
				plugin.setPlayControls();
			}
			else
			{
				plugin.playTimer = window.setInterval( plugin.setNextFrame, iv(plugin.ffmpegSettings.explayinterval)*1000 );
				plugin.setNextFrame();
			}
		});
		$(document.body).append($("<iframe name='scplayfrm'/>").css({visibility: "hidden"}).attr( { name: "scplayfrm", id: "scplayfrm" } ).width(0).height(0));
		$(document.body).append(
			$('<form action="plugins/screenshots/action.php" id="scgetimg" method="post" target="scplayfrm">'+
				'<input type="hidden" name="cmd" id="scimgcmd" value="ffmpeggetimage">'+
				'<input type="hidden" name="no" id="sctaskno" value="0">'+
				'<input type="hidden" name="fno" id="scimgno" value="0">'+
				'<input type="hidden" name="file" id="scimgfile" value="frame">'+
			'</form>').width(0).height(0));
		$("#scsave").on('click', function()
		{
			$("#scimgno").val(plugin.getCurrentFrame());
			$("#scimgcmd").val("ffmpeggetimage");
			$('#scgetimg').submit();
		});
		$("#scsaveall").on('click', function()
		{
			$("#scimgcmd").val("ffmpeggetall");
			$('#scgetimg').submit();
		});
		plugin.markLoaded();
	}
}

plugin.onRemove = function()
{
	if(!explorerIsInstalled)
		this.removePageFromOptions("st_screenshots");
}

plugin.langLoaded = function()
{
	if(plugin.enabled)
		plugin.onLangLoaded();
}
