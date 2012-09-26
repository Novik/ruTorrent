/*
 *      UI content.
 *
 *	$Id$
 */

function makeContent()
{
	$(".cat").mouseclick(theWebUI.labelContextMenu);
	$("#st_up").mouseclick(theWebUI.upRateMenu);
	$("#st_down").mouseclick(theWebUI.downRateMenu);

	$("#mnu_add").attr("title",theUILang.mnu_add+"...");
	$("#mnu_remove").attr("title",theUILang.mnu_remove);
	$("#mnu_start").attr("title",theUILang.mnu_start);
	$("#mnu_pause").attr("title",theUILang.mnu_pause);
	$("#mnu_stop").attr("title",theUILang.mnu_stop);
	$("#mnu_settings").attr("title",theUILang.mnu_settings+"...");
	$("#mnu_search").attr("title",theUILang.mnu_search+"...");
	$("#mnu_go").attr("title",theUILang.mnu_go);
	$("#mnu_help").attr("title",theUILang.mnu_help+"...");

	$("#query").keydown( function(e) 
	{
		if(e.keyCode == 13) 
			theSearchEngines.run();
	});

	new DnD("HDivider",
	{
		left : function() { return(60); },
		right : function() { return( $(window).width()-20 ); },
		restrictY : true,
		maskId : "dividerDrag",
		onStart : function(e) { return(theWebUI.settings["webui.show_cats"]); },
		onRun : function(e) { $(document.body).css( "cursor", "e-resize" ); },
		onFinish : function(e) 
		{
		        var self = e.data;
			var w = self.mask.offset().left-2;
			theWebUI.resizeLeft(w,null);	
			w = $(window).width()-w-11;
			theWebUI.resizeTop(w,null);
      		        theWebUI.resizeBottom(w,null);
			theWebUI.setHSplitter();
			$(document.body).css( "cursor", "default" );
		}
	});

	new DnD("VDivider",
	{
		top : function() { return(60); },
		bottom : function() { return( $(window).height()-60 ); },
		restrictX : true,
		maskId : "dividerDrag",
		onStart : function(e) { return(theWebUI.settings["webui.show_dets"]); },
		onRun : function(e) { $(document.body).css( "cursor", "n-resize" ); },
		onFinish : function(e) 
		{
		        var self = e.data;
		        var offs = self.mask.offset();
      		        theWebUI.resizeTop(null,offs.top-($("#t").is(":visible") ?  $("#t").height() : -1)-8);
      		        theWebUI.resizeBottom(null,$(window).height()-offs.top-$("#StatusBar").height()-14);
      		        theWebUI.setVSplitter();
			$(document.body).css( "cursor", "default" );
		}
	});

	$(document.body).append($("<iframe name='uploadfrm'/>").css({visibility: "hidden"}).attr( { name: "uploadfrm" } ).width(0).height(0).load(function()
	{
		$("#torrent_file").val("");
		$("#add_button").attr("disabled",false);
		var d = (this.contentDocument || this.contentWindow.document);
		if(d && (d.location.href != "about:blank"))
			try { eval(d.body.innerHTML); } catch(e) {}
	}));
	$(document.body).append($("<iframe name='uploadfrmurl'/>").css({visibility: "hidden"}).attr( { name: "uploadfrmurl" } ).width(0).height(0).load(function()
	{
		$("#url").val("");
		var d = (this.contentDocument || this.contentWindow.document);
		if(d.location.href != "about:blank")
			try { eval(d.body.innerHTML); } catch(e) {}
	}));
	theDialogManager.make("padd",theUILang.peerAdd,
		'<div class="content fxcaret">'+theUILang.peerAddLabel+'<br><input type="text" id="peerIP" class="Textbox" value="my.friend.addr:6881"/></div>'+
		'<div class="aright buttons-list"><input type="button" class="OK Button" value="'+theUILang.ok+'" onclick="theWebUI.addNewPeer();theDialogManager.hide(\'padd\');return(false);" />'+
			'<input type="button" class="Cancel Button" value="'+theUILang.Cancel+'"/></div>',
		true);
	theDialogManager.make("tadd",theUILang.torrent_add,
		'<div class="cont fxcaret">'+
			'<form action="addtorrent.php" id="addtorrent" method="post" enctype="multipart/form-data" target="uploadfrm">'+
				'<label>'+theUILang.Base_directory+':</label><input type="text" id="dir_edit" name="dir_edit" class="TextboxLarge"/><br/>'+
				'<label>&nbsp;</label><input type="checkbox" name="not_add_path" id="not_add_path"/>'+theUILang.Dont_add_tname+'<br/>'+
				'<label>&nbsp;</label><input type="checkbox" name="torrents_start_stopped" id="torrents_start_stopped"/>'+theUILang.Dnt_start_down_auto+'<br/>'+
				'<label>&nbsp;</label><input type="checkbox" name="fast_resume" id="fast_resume"/>'+theUILang.doFastResume+'<br/>'+
				'<label>'+theUILang.Label+':</label><input type="text" id="tadd_label" name="tadd_label" class="TextboxLarge"/><br/>'+
				'<hr/>'+
				'<label>'+theUILang.Torrent_file+':</label><input type="file" multiple="multiple" name="torrent_file[]" id="torrent_file" accept="application/x-bittorrent" class="TextboxLarge"/><br/>'+
				'<label>&nbsp;</label><input type="submit" value="'+theUILang.add_button+'" id="add_button" class="Button" /><br/>'+
			'</form>'+
			'<hr/>'+
			'<form action="addtorrent.php" id="addtorrenturl" method="post" target="uploadfrmurl">'+
				'<label>'+theUILang.Torrent_URL+':</label><input type="text" id="url" name="url" class="TextboxLarge"/><br/>'+
				'<label>&nbsp;</label><input type="submit" id="add_url" value="'+theUILang.add_url+'" class="Button" disabled="true"/>'+
			'</form>'+
		'</div>');
	theDialogManager.setHandler('tadd','beforeShow',function()
	{
		$("#add_button").attr("disabled",false);
	});

	input = $$('url');
	input.onupdate = input.onkeyup = function() { $('#add_url').attr('disabled',$.trim(input.value)==''); };
	input.onpaste = function() { setTimeout( input.onupdate, 10 ) };
	var makeAddRequest = function(frm)
	{
		var s = theURLs.AddTorrentURL+"?";
		if($("#torrents_start_stopped").attr("checked"))
			s += 'torrents_start_stopped=1&';
		if($("#fast_resume").attr("checked"))
			s += 'fast_resume=1&';
		if($("#not_add_path").attr("checked"))
			s += 'not_add_path=1&';
		var dir = $.trim($("#dir_edit").val());
		if(dir.length)
			s += ('dir_edit='+encodeURIComponent(dir)+'&');
		var lbl = $.trim($("#tadd_label").val());
		if(lbl.length)
			s += ('label='+encodeURIComponent(lbl));
		frm.action = s;
		return(true);
	}
	$("#addtorrent").submit(function()
	{
		if(!$("#torrent_file").val().match(".torrent")) 
		{
			alert(theUILang.Not_torrent_file);
	   		return(false);
   		}
		$("#add_button").attr("disabled",true);
		return(makeAddRequest(this));
	});
	$("#addtorrenturl").submit(function()
	{
	   	$("#add_url").attr("disabled",true);
	   	return(makeAddRequest(this));
	});
	theDialogManager.make("dlgProps",theUILang.Torrent_properties,
		'<div class="content fxcaret">'+
			'<fieldset>'+
				'<legend>'+theUILang.Bandwidth_sett+'</legend>'+
				'<div><input type="text" id="prop-ulslots" />'+theUILang.Number_ul_slots+':</div>'+
				'<div style="clear:right"><input type="text" id="prop-peers_min" />'+theUILang.Number_Peers_min+':</div>'+
				'<div style="clear:right"><input type="text" id="prop-peers_max" />'+theUILang.Number_Peers_max+':</div>'+
				'<div style="clear:right"><input type="text" id="prop-tracker_numwant" />'+theUILang.Tracker_Numwant+':</div>'+
				'<div class="props-spacer">'+
					'<input type="checkbox" id="prop-pex" /><label for="prop-pex" id="lbl_prop-pex">'+theUILang.Peer_ex+'</label>'+
					'<input type="checkbox" id="prop-superseed" /><label for="prop-superseed" id="lbl_prop-superseed">'+theUILang.SuperSeed+'</label>'+
				'</div>'+
			'</fieldset>'+
		'</div>'+
		'<div class="aright buttons-list"><input type="button" value="'+theUILang.ok+'" class="OK Button" onclick="theWebUI.setProperties(); return(false);" /><input type="button" value="'+theUILang.Cancel+'" class="Cancel Button"/></div>',
		true);
	theDialogManager.make("dlgHelp",theUILang.Help,
		'<div class="content">'+
			'<center>'+
				'<table width=100% border=0>'+
					'<tr><td><strong>F1</strong></td><td>'+theUILang.This_screen+'</td></tr>'+
					'<tr><td><strong><strong>Ctrl-F1</strong></td><td><a href="javascript://void();" onclick="theDialogManager.toggle(\'dlgAbout\'); return(false);">'+theUILang.About_program+'</a></td></tr>'+
					'<tr><td><strong><strong>F4</strong></td><td><a href="javascript://void();" onclick="theWebUI.toggleMenu(); return(false);">'+theUILang.Toggle_menu+'</a></td></tr>'+
					'<tr><td><strong><strong>F6</strong></td><td><a href="javascript://void();" onclick="theWebUI.toggleDetails(); return(false);">'+theUILang.Toggle_details+'</a></td></tr>'+
					'<tr><td><strong><strong>F7</strong></td><td><a href="javascript://void();" onclick="theWebUI.toggleCategories(); return(false);">'+theUILang.Toggle_categories+'</a></td></tr>'+
					'<tr><td><strong><strong>Ctrl-O</strong></td><td><a href="javascript://void();" onclick="theWebUI.showAdd(); return(false);">'+theUILang.torrent_add+'</a></td></tr>'+
					'<tr><td><strong><strong>Ctrl-P</strong></td><td><a href="javascript://void();" onclick="theWebUI.showSettings(); return(false);">'+theUILang.ruTorrent_settings+'</a></td></tr>'+
					'<tr><td><strong><strong>Del</strong></td><td>'+theUILang.Delete_current_torrents+'</td></tr>'+
					'<tr><td><strong><strong>Ctrl-A</strong></td><td>'+theUILang.Select_all+'</td></tr>'+
					'<tr><td><strong><strong>Ctrl-Z</strong></td><td>'+theUILang.Deselect_all+'</td></tr>'+
				'</table>'+
			'</center>'+
		'</div>');
	theDialogManager.make("dlgAbout",theUILang.About_program,
		'<div class="content"> <strong>'+theUILang.Developers+'</strong>:<br/><br/>'+
			'&nbsp;&nbsp;&nbsp;Original &micro;Torrent WebUI:<br/>'+
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Carsten Niebuhr (Directrix)<br/><br/>'+
			'&nbsp;&nbsp;&nbsp;rTorrent adaptation (ruTorrent):<br/>'+
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Moskalets Alexander (<a href="mailto:novik65@gmail.com">Novik</a>)<br/>'+
			'<br/>'+
			'<strong>'+theUILang.Check_new_version+'&nbsp;<a href="http://rutorrent.googlecode.com" target=_blank>'+theUILang.here+'</a></strong><br/>'+
			'<br/>'+
			'<center><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=2KEV2MSBTF99U" target=_blank><img src="images/btn_donate.gif" border=0/></a></center>'+
		'</div>');
	theDialogManager.make("dlgLabel",theUILang.enterLabel,
		'<div class="content fxcaret">'+theUILang.Enter_label_prom+':<br/>'+
			'<input type="text" id="txtLabel" class="Textbox"/></div>'+
		'<div class="aright buttons-list"><input type="button" class="OK Button" value="'+theUILang.ok+'" onclick="theWebUI.createLabel();theDialogManager.hide(\'dlgLabel\');return(false);" />'+
			'<input type="button" class="Cancel Button" value="'+theUILang.Cancel+'"/></div>',
		true);
	theDialogManager.make("yesnoDlg","",
		'<div class="content" id="yesnoDlg-content"></div>'+
		'<div id="yesnoDlg-buttons" class="aright buttons-list"><input type="button" class="OK Button" value="'+theUILang.ok+'" id="yesnoOK">'+
		'<input type="button" class="Button Cancel" value="'+theUILang.Cancel+'" id="yesnoCancel"></div>',
		true);
	var languages = '';
	for (var i in AvailableLanguages)
		languages+="<option value='"+i+"'>"+AvailableLanguages[i]+"</option>";
	var retries = '';
	for (var i in theUILang.retryOnErrorList)
		retries+="<option value='"+i+"'>"+theUILang.retryOnErrorList[i]+"</option>";
	theDialogManager.make("stg",theUILang.ruTorrent_settings,
		'<div id="stg_c" class="fxcaret">'+
			"<div class=\"lm\">"+
				"<ul>"+
					"<li class=\"first\"><a id=\"mnu_st_gl\" href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_gl\'); return(false);\" class=\"focus\">"+
						theUILang.General+
					"</a></li>"+
					"<li id='hld_st_dl'><a id=\"mnu_st_dl\" href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_dl\'); return(false);\">"+
						theUILang.Downloads+
					"</a></li>"+
					"<li id='hld_st_con'><a id=\"mnu_st_con\" href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_con\'); return(false);\">"+
						theUILang.Connection+
					"</a></li>"+
					"<li id='hld_st_bt'><a id=\"mnu_st_bt\" href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_bt\'); return(false);\">"+
						theUILang.BitTorrent+
					"</a></li>"+
					"<li  id='hld_st_ao' class=\"last\"><a id=\"mnu_st_ao\" href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_ao\'); return(false);\">"+
						theUILang.Advanced+
					"</a></li>"+
				"</ul>"+
			"</div>"+
			"<div id=\"st_gl\" class=\"stg_con\">"+
				"<fieldset>"+
					"<legend>"+theUILang.User_Interface+"</legend>"+
					"<div class=\"op50l\">"+
						"<input type=\"checkbox\" id=\"webui.confirm_when_deleting\" checked=\"true\" />"+
						"<label for=\"webui.confirm_when_deleting\">"+theUILang.Confirm_del_torr+"</label>"+
					"</div>"+
					"<div class=\"op50l algnright\">"+
						theUILang.Update_GUI_every+":&nbsp;<input type=\"text\" id=\"webui.update_interval\" class=\"TextboxShort\" value=\"3000\" />"+
						theUILang.ms+
					"</div>"+
					"<div class=\"op50l\"><input type=\"checkbox\" id=\"webui.alternate_color\" />"+
						"<label for=\"webui.alternate_color\">"+theUILang.Alt_list_bckgnd+"</label>"+
					"</div>"+
					"<div class=\"op50l algnright\">"+
						theUILang.ReqTimeout+":&nbsp;<input type=\"text\" id=\"webui.reqtimeout\" class=\"TextboxShort\" value=\"5000\" />"+
						theUILang.ms+
					"</div>"+
					"<div class=\"op50l\"><input type=\"checkbox\" id=\"webui.show_cats\" checked=\"true\" />"+
						"<label for=\"webui.show_cats\">"+theUILang.Show_cat_start+"</label>"+
					"</div>"+
					"<div class=\"op50l algnright\"><input type=\"checkbox\" id=\"webui.show_dets\" checked=\"true\" />"+
						"<label for=\"webui.show_dets\">"+theUILang.Show_det_start+"</label>"+
					"</div>"+
					"<div class=\"op50l\"><input type=\"checkbox\" id=\"webui.needmessage\"/>"+
						"<label for=\"webui.needmessage\">"+theUILang.GetTrackerMessage+"</label>"+
					"</div>"+

					"<div class=\"op50l algnright\">"+
						"<label for=\"webui.dateformat\">"+theUILang.DateFormat+":</label>&nbsp;"+
						"<select id=\"webui.dateformat\">"+
							"<option value='0'>31.12.2011</option>"+
							"<option value='1'>2011-12-31</option>"+
							"<option value='2'>12/31/2011</option>"+
						"</select>"+
					"</div>"+

					"<div class=\"op50l\">"+
						"<label for=\"webui.ignore_timeouts\">"+"<input type=\"checkbox\" id=\"webui.ignore_timeouts\" checked=\"true\" />"+theUILang.dontShowTimeouts+"</label>"+
					"</div>"+

					"<div class=\"op50l algnright\"><input type=\"checkbox\" id=\"webui.effects\"/>"+
						"<label for=\"webui.effects\">"+theUILang.UIEffects+"</label>"+
					"</div>"+


					"<div class=\"op100l\"><input type=\"checkbox\" id=\"webui.fullrows\"  onchange=\"linked(this, 1, ['webui.no_delaying_draw']);\"/>"+
						"<label for=\"webui.fullrows\">"+theUILang.fullTableRender+"</label>"+
					"</div>"+
					"<div class=\"op100l\"><input type=\"checkbox\" id=\"webui.no_delaying_draw\"/>"+
						"<label for=\"webui.no_delaying_draw\" id=\"lbl_webui.no_delaying_draw\" >"+theUILang.showScrollTables+"</label>"+
					"</div>"+
					"<div class=\"op100l\"><input type=\"checkbox\" id=\"webui.log_autoswitch\"/>"+
						"<label for=\"webui.log_autoswitch\" id=\"lbl_webui.log_autoswitch\" >"+theUILang.logAutoSwitch+"</label>"+
					"</div>"+
					"<div class=\"op100l\">"+
						"<label for=\"webui.retry_on_error\">"+theUILang.retryOnErrorTitle+":</label>&nbsp;"+
						"<select id=\"webui.retry_on_error\">"+
							retries+
						"</select>"+
					"</div>"+
					"<div class=\"op50l\">"+
						"<label for=\"webui.lang\">"+theUILang.mnu_lang+":</label>&nbsp;"+
						"<select id=\"webui.lang\">"+
							languages+
						"</select>"+
					"</div>"+
				"</fieldset>"+
				"<fieldset>"+
					"<legend>"+theUILang.speedList+"</legend>"+
					"<table>"+
						"<tr>"+
							"<td>"+theUILang.UL+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"webui.speedlistul\" class=\"Textbox speedEdit\" maxlength=\"128\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.DL+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"webui.speedlistdl\" class=\"Textbox speedEdit\" maxlength=\"128\" /></td>"+
						"</tr>"+
					"</table>"+
				"</fieldset>"+
			"</div>"+
			"<div id=\"st_dl\" class=\"stg_con\">"+
				"<fieldset>"+
					"<legend>"+theUILang.Bandwidth_sett+"</legend>"+
					"<table>"+
						"<tr>"+
							"<td>"+theUILang.Number_ul_slots+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"max_uploads\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Number_Peers_min+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"min_peers\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Number_Peers_max+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"max_peers\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Number_Peers_For_Seeds_min+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"min_peers_seed\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Number_Peers_For_Seeds_max+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"max_peers_seed\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Tracker_Numwant+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"tracker_numwant\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
					"</table>"+
				"</fieldset>"+
				"<fieldset>"+
					"<legend>"+theUILang.Ather_sett+"</legend>"+
					"<table>"+
						"<tr>"+
							"<td><input id=\"check_hash\" type=\"checkbox\"/>"+
								"<label for=\"check_hash\">"+theUILang.Check_hash+"</label>"+
							"</td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Directory_For_Dl+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"directory\" class=\"Textbox str\" maxlength=\"100\" /></td>"+
						"</tr>"+
					"</table>"+
				"</fieldset>"+
			"</div>"+
			"<div id=\"st_con\" class=\"stg_con\">"+
				"<div>"+
					"<input id=\"port_open\" type=\"checkbox\" onchange=\"linked(this, 0, ['port_range', 'port_random']);\" />"+
					"<label for=\"port_open\">"+
						theUILang.Enable_port_open+
					"</label>"+
				"</div>"+
				"<fieldset>"+
					"<legend>"+theUILang.Listening_Port+"</legend>"+
					"<table>"+
						"<tr>"+
							"<td><label id=\"lbl_port_range\" for=\"port_range\" class=\"disabled\">"+theUILang.Port_f_incom_conns+":</label></td>"+
							"<td class=\"alr\">"+
								"<input type=\"text\" id=\"port_range\" class=\"TextboxShort\" class=\"disabled\" maxlength=\"13\" />"+
							"</td>"+
						"</tr>"+
						"<tr>"+
							"<td colspan=\"2\">"+
								"<input id=\"port_random\" type=\"checkbox\"  class=\"disabled\"/>"+
								"<label  id=\"lbl_port_random\" for=\"port_random\"  class=\"disabled\">"+theUILang.Rnd_port_torr_start+"</label>"+
							"</td>"+
						"</tr>"+
					"</table>"+
				"</fieldset>"+
				"<fieldset>"+
					"<legend>"+theUILang.Bandwidth_Limiting+"</legend>"+
					"<table>"+
						"<tr>"+
							"<td>"+theUILang.Global_max_upl+" ("+theUILang.kbs+"): [0: "+theUILang.unlimited+"]</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"upload_rate\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Glob_max_downl+" ("+theUILang.kbs+"): [0: "+theUILang.unlimited+"]</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"download_rate\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
					"</table>"+
				"</fieldset>"+
				"<fieldset>"+
					"<legend>"+theUILang.Ather_Limiting+"</legend>"+
					"<table>"+
						"<tr>"+
							"<td>"+theUILang.Number_ul_slots+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"max_uploads_global\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Number_dl_slots+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"max_downloads_global\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Glob_max_memory+" ("+theUILang.MB+"):</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"max_memory_usage\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Glob_max_files+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"max_open_files\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Glob_max_http+":</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"max_open_http\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
					"</table>"+
				"</fieldset>"+
			"</div>"+
			"<div id=\"st_bt\" class=\"stg_con\">"+
				"<fieldset>"+
					"<legend>"+theUILang.Add_bittor_featrs+"</legend>"+
					"<table>"+
						"<tr>"+
							"<td><input id=\"dht\" type=\"checkbox\"  onchange=\"linked(this, 0, ['dht_port']);\" />"+
								"<label for=\"dht\">"+theUILang.En_DHT_ntw+"</label>"+
						"</td>"+
							"<td><input id=\"peer_exchange\" type=\"checkbox\" />"+
								"<label for=\"peer_exchange\">"+theUILang.Peer_exch+"</label>"+
							"</td>"+
						"</tr>"+
						"<tr>"+
							"<td id=\"lbl_dht_port\" class=\"disabled\">"+theUILang.dht_port+":</td>"+
							"<td><input type=\"text\" id=\"dht_port\" class=\"Textbox num\" maxlength=\"6\" class=\"disabled\"/></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Ip_report_track+":</td>"+
							"<td><input type=\"text\" id=\"ip\" class=\"Textbox str\" maxlength=\"50\" /></td>"+
						"</tr>"+
					"</table>"+
				"</fieldset>"+
			"</div>"+
			"<div id=\"st_ao\" class=\"stg_con\">"+
				"<fieldset>"+
					"<legend>"+theUILang.Advanced+"</legend>"+
					"<div id=\"st_ao_h\">"+
						"<table width=\"99%\" cellpadding=\"0\" cellspacing=\"0\">"+
							"<tr>"+
								"<td>hash_interval</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"hash_interval\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>hash_max_tries</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"hash_max_tries\" class=\"Textbox num\" maxlength=\"5\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>hash_read_ahead</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"hash_read_ahead\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>http_cacert</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"http_cacert\" class=\"Textbox str\" maxlength=\"100\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>http_capath</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"http_capath\" class=\"Textbox str\" maxlength=\"100\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>max_downloads_div</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"max_downloads_div\" class=\"Textbox num\" maxlength=\"5\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>max_uploads_div</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"max_uploads_div\" class=\"Textbox num\" maxlength=\"5\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>max_file_size</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"max_file_size\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>preload_type</td>"+
								"<td class=\"alr\">"+
									"<select id=\"preload_type\">"+
										"<option value=\"0\" selected=\"selected\">off</option>"+
										"<option value=\"1\">madvise</option>"+
										"<option value=\"2\">direct paging</option>"+
									"</select>"+
								"</td>"+
							"</tr>"+
							"<tr>"+
								"<td>preload_min_size</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"preload_min_size\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>preload_required_rate</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"preload_required_rate\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>receive_buffer_size</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"receive_buffer_size\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>send_buffer_size</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"send_buffer_size\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td><label for=\"safe_sync\">safe_sync</label></td>"+
								"<td class=\"alr\"><input type=\"checkbox\" id=\"safe_sync\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>timeout_safe_sync</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"timeout_safe_sync\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>timeout_sync</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"timeout_sync\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td><label for=\"scgi_dont_route\">scgi_dont_route</label></td>"+
								"<td class=\"alr\"><input type=\"checkbox\" id=\"scgi_dont_route\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>session</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"session\" class=\"Textbox str\" maxlength=\"100\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td><label for=\"session_lock\">session_lock</label></td>"+
								"<td class=\"alr\"><input type=\"checkbox\" id=\"session_lock\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td><label for=\"session_on_completion\">session_on_completion</label></td>"+
								"<td class=\"alr\"><input type=\"checkbox\" id=\"session_on_completion\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>split_file_size</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"split_file_size\" class=\"Textbox num\" maxlength=\"20\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>split_suffix</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"split_suffix\" class=\"Textbox str\" maxlength=\"100\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td><label for=\"use_udp_trackers\">use_udp_trackers</label></td>"+
								"<td class=\"alr\"><input type=\"checkbox\" id=\"use_udp_trackers\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>http_proxy</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"http_proxy\" class=\"Textbox str\" maxlength=\"100\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>proxy_address</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"proxy_address\" class=\"Textbox str\" maxlength=\"100\" /></td>"+
							"</tr>"+
							"<tr>"+
								"<td>bind</td>"+
								"<td class=\"alr\"><input type=\"text\" id=\"bind\" class=\"Textbox str\" maxlength=\"100\" /></td>"+
							"</tr>"+
						"</table>"+
					"</div>"+
				"</fieldset>"+
			"</div>"+
			"<div id=\"st_btns\" class='aright buttons-list'>"+
				"<input type=\"button\" value=\"OK\" onclick=\"theDialogManager.hide('stg');theWebUI.setSettings();return(false);\" class=\"OK Button\" />"+
				"<input type=\"button\" value=\""+theUILang.Cancel+"\" class=\"Cancel Button\" />"+
			"</div>"+
		"</div>");
}

function correctContent()
{
	var showEnum = 
	{
		showDownloadsPage:	0x0001,
		showConnectionPage:	0x0002,
		showBittorentPage:	0x0004,
		showAdvancedPage:	0x0008,
		showPluginsTab:		0x0010,
		canChangeULRate:	0x0020,
		canChangeDLRate:	0x0040,
		canChangeTorrentProperties:	0x0080
	};

	if(!$type(theWebUI.systemInfo))
		theWebUI.systemInfo = { rTorrent: { version: '?', libVersion: '?', started: false, iVersion: 0, apiVersion : 0 } };

	if(!theWebUI.systemInfo.rTorrent.started)
        	theWebUI.showFlags &= ~0xFFEF;

	if(!(theWebUI.showFlags & showEnum.showDownloadsPage))
		rPlugin.prototype.removePageFromOptions("st_dl");
	if(!(theWebUI.showFlags & showEnum.showConnectionPage))
		rPlugin.prototype.removePageFromOptions("st_con");
	if(!(theWebUI.showFlags & showEnum.showBittorentPage))
		rPlugin.prototype.removePageFromOptions("st_bt");
	if(!(theWebUI.showFlags & showEnum.showAdvancedPage))
		rPlugin.prototype.removePageFromOptions("st_ao");
	if(!(theWebUI.showFlags & showEnum.showPluginsTab))
	{
		delete theWebUI.tables.plg;
  		rPlugin.prototype.removePageFromTabs("PluginList");
	}
	if(!(theWebUI.showFlags & showEnum.canChangeULRate))
		$("#st_up").mouseclick(null);
	if(!(theWebUI.showFlags & showEnum.canChangeDLRate))
		$("#st_down").mouseclick(null);
	if(!(theWebUI.showFlags & showEnum.canChangeTorrentProperties))
	{
		$("#prop-ulslots").attr("disabled","true");
		$("#prop-peers_min").attr("disabled","true");
		$("#prop-peers_max").attr("disabled","true");
		$("#prop-tracker_numwant").attr("disabled","true");
		$("#prop-pex").remove();
		$("#lbl_prop-pex").remove();
		$("#prop-superseed").remove();
		$("#lbl_prop-superseed").remove();
		$("#dlgProps .OK").remove();
        }		
	if(!theWebUI.systemInfo.rTorrent.started)
	{
		rPlugin.prototype.removePageFromTabs("TrackerList");
		rPlugin.prototype.removePageFromTabs("FileList");
		rPlugin.prototype.removePageFromTabs("PeerList");
		rPlugin.prototype.removePageFromTabs("Speed");
		$("#st_up").remove();
		$("#st_down").remove();
		rPlugin.prototype.removeButtonFromToolbar("add");
		rPlugin.prototype.removeSeparatorFromToolbar("remove");
		rPlugin.prototype.removeButtonFromToolbar("remove");
		rPlugin.prototype.removeSeparatorFromToolbar("start");
		rPlugin.prototype.removeButtonFromToolbar("start");
		rPlugin.prototype.removeButtonFromToolbar("pause");
		rPlugin.prototype.removeButtonFromToolbar("stop");
		rPlugin.prototype.removeSeparatorFromToolbar("settings");
	}
	else
	{
		if(theWebUI.systemInfo.rTorrent.iVersion>=0x809)
		{
			theRequestManager.addRequest("fls","f.prioritize_first=",function(hash, fls, value) 
			{
				if(value=='1')
					fls.prioritize = 1;
			});
			theRequestManager.addRequest("fls","f.prioritize_last=",function(hash, fls, value) 
			{
				if(value=='1')
					fls.prioritize = 2;
			});
		}
		if(theWebUI.systemInfo.rTorrent.iVersion>=0x900)
		{
			$('#st_ao_h table tr:first').remove();
			$('#st_ao_h table tr:first').remove();
			$('#st_ao_h table tr:first').remove();
			$.extend(theRequestManager.aliases, 
			{
				"get_hash_interval"		: "cat",
				"get_hash_max_tries"		: "cat",
				"get_hash_read_ahead"		: "cat",
				"set_hash_interval"		: "cat",
				"set_hash_max_tries"		: "cat",
				"set_hash_read_ahead"		: "cat"
			});
		}
	}
	if(theWebUI.systemInfo.rTorrent.newMethodsSet)
	{
		theRequestManager.aliases[""] = "";
		$.extend(theRequestManager.aliases, 
		{
			"d.set_peer_exchange" 		: "d.peer_exchange.set",
			"d.set_connection_seed"		: "d.connection_seed.set"
		});
	}
	$("#rtorrentv").text(theWebUI.systemInfo.rTorrent.version+"/"+theWebUI.systemInfo.rTorrent.libVersion);


}