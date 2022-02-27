/*
 *      UI content.
 *
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

	$("#query").on('keydown', function(e)
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

	$(document.body).append($("<iframe name='uploadfrm'/>").css({visibility: "hidden"}).attr( { name: "uploadfrm" } ).width(0).height(0).on('load', function()
	{
		$("#torrent_file").val("");
		$("#add_button").prop("disabled",false);
		var d = (this.contentDocument || this.contentWindow.document);
		if(d && (d.location.href != "about:blank"))
		{
			try { var txt = d.body.textContent ? d.body.textContent : d.body.innerText; eval(txt); } catch(e) {}
		}
	}));
	$(document.body).append($("<iframe name='uploadfrmurl'/>").css({visibility: "hidden"}).attr( { name: "uploadfrmurl" } ).width(0).height(0).on('load', function()
	{
		$("#url").val("");
		var d = (this.contentDocument || this.contentWindow.document);
		if(d.location.href != "about:blank")
			try { eval(d.body.textContent ? d.body.textContent : d.body.innerText); } catch(e) {}
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
				'<span id="not_add_path_option">'+
				'<label>&nbsp;</label><input type="checkbox" name="not_add_path" id="not_add_path"/>'+theUILang.Dont_add_tname+'<br/>'+
				'</span>'+
				'<span id="torrents_start_stopped_option">'+
				'<label>&nbsp;</label><input type="checkbox" name="torrents_start_stopped" id="torrents_start_stopped"/>'+theUILang.Dnt_start_down_auto+'<br/>'+
				'</span>'+
				'<span id="fast_resume_option">'+
				'<label>&nbsp;</label><input type="checkbox" name="fast_resume" id="fast_resume"/>'+theUILang.doFastResume+'<br/>'+
				'</span>'+
				'<span id="randomize_hash_option">'+
				'<label>&nbsp;</label><input type="checkbox" name="randomize_hash" id="randomize_hash"/>'+theUILang.doRandomizeHash+'<br/>'+
				'</span>'+
				'<label>'+theUILang.Label+':</label><input type="text" id="tadd_label" name="tadd_label" class="TextboxLarge" /><select id="tadd_label_select"></select><br/>'+
				'<hr/>'+
				'<label>'+theUILang.Torrent_file+':</label><input type="file" multiple="multiple" name="torrent_file[]" id="torrent_file" accept=".torrent" class="TextboxLarge"/><br/>'+
				'<label>&nbsp;</label><input type="submit" value="'+theUILang.add_button+'" id="add_button" class="Button" /><br/>'+
			'</form>'+
			'<hr/>'+
			'<form action="addtorrent.php" id="addtorrenturl" method="post" target="uploadfrmurl">'+
				'<label>'+theUILang.Torrent_URL+':</label><input type="text" id="url" name="url" class="TextboxLarge"/><br/>'+
				'<label>&nbsp;</label><input type="submit" id="add_url" value="'+theUILang.add_url+'" class="Button" disabled="true"/>'+
			'</form>'+
		'</div>');

	$("#tadd_label_select").on('change', function(e)
	{
		var index = this.selectedIndex;
		switch (index)
		{
			case 1:
			{
				$(this).hide();
				$("#tadd_label").show();
			}
			case 0:
			{
				$("#tadd_label").val("");
				break;
			}
			default:
			{
				$("#tadd_label").val(this.options[index].value);
				break;
			}
		}
	});

	theDialogManager.setHandler('tadd','beforeShow',function()
	{
		$("#tadd_label").hide();
		$("#tadd_label_select").empty()
			.append('<option selected>'+theUILang.No_label+'</option>')
			.append('<option>'+theUILang.newLabel+'</option>').show();
		for (var lbl in theWebUI.cLabels)
			$("#tadd_label_select").append("<option>"+lbl+"</option>");
		$("#add_button").prop("disabled",false);
		$("#tadd_label_select").change();
	});

	var input = $$('url');
	input.onupdate = input.onkeyup = function() { $('#add_url').prop('disabled',input.value.trim()==''); };
	input.onpaste = function() { setTimeout( input.onupdate, 10 ) };
	var makeAddRequest = function(frm)
	{
		var s = theURLs.AddTorrentURL;
		var req = []
		if($("#torrents_start_stopped").prop("checked"))
			req.push('torrents_start_stopped=1');
		if($("#fast_resume").prop("checked"))
			req.push('fast_resume=1');
		if($("#not_add_path").prop("checked"))
			req.push('not_add_path=1');
		if($("#randomize_hash").prop("checked"))
			req.push('randomize_hash=1');
		var dir = $("#dir_edit").val().trim();
		if(dir.length)
			req.push('dir_edit='+encodeURIComponent(dir));
		var lbl = $("#tadd_label").val().trim();
		if(lbl.length)
			req.push('label='+encodeURIComponent(lbl));
		if(req.length)
			s+=('?'+req.join('&'));
		frm.action = s;
		return(true);
	}
	$("#addtorrent").on('submit', function()
	{
		if(!$("#torrent_file").val().match(/\.torrent$/i))
		{
			alert(theUILang.Not_torrent_file);
	   		return(false);
   		}
		$("#add_button").prop("disabled",true);
		return(makeAddRequest(this));
	});
	$("#addtorrenturl").on('submit', function()
	{
	   	$("#add_url").prop("disabled",true);
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
		'</div>');
	theDialogManager.make("dlgAbout","ruTorrent v"+theWebUI.version,
		'<div class="content"> <strong>'+theUILang.Developers+'</strong>:<br/><br/>'+
			'&nbsp;&nbsp;&nbsp;Original &micro;Torrent WebUI:<br/>'+
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Carsten Niebuhr (Directrix)<br/><br/>'+
			'&nbsp;&nbsp;&nbsp;rTorrent adaptation (ruTorrent):<br/>'+
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Moskalets Alexander (<a href="mailto:novik65@gmail.com">Novik</a>)<br/>'+
			'<br/>'+
			'<strong>'+theUILang.Check_new_version+'&nbsp;<a href="https://github.com/Novik/ruTorrent" target=_blank>'+theUILang.here+'</a></strong><br/>'+
			'<br/>'+
			'<center><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=2KEV2MSBTF99U" target=_blank><img src="images/btn_donate.gif" border="0"/></a></center>'+
		'</div>');
	theDialogManager.make("dlgLabel",theUILang.enterLabel,
		'<div class="content fxcaret">'+theUILang.Enter_label_prom+':<br/>'+
			'<input type="text" id="txtLabel" class="Textbox"/></div>'+
		'<div class="aright buttons-list"><input type="button" class="OK Button" value="'+theUILang.ok+'" onclick="theWebUI.createLabel();theDialogManager.hide(\'dlgLabel\');return(false);" />'+
			'<input type="button" class="Cancel Button" value="'+theUILang.Cancel+'"/></div>',
		true);
	theDialogManager.setHandler('dlgLabel','afterShow',function()
	{
		setTimeout(function(){
			$("#txtLabel").off('focus').on('focus',function() { 
				$(this).select(); 
			}).trigger('focus');		
		}, 0);
	});
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
					"<li  id='hld_st_fmt' ><a id=\"mnu_st_fmt\" href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_fmt\'); return(false);\">"+
						theUILang.Format+
					"</a></li>"+
					"<li  id='hld_st_ao' class=\"last\"><a id=\"mnu_st_ao\" href=\"javascript://void();\" onclick=\"theOptionsSwitcher.run(\'st_ao\'); return(false);\">"+
						theUILang.Advanced+
					"</a></li>"+
				"</ul>"+
			"</div>"+
			"<div id=\"st_gl\" class=\"stg_con\">"+
				"<fieldset>"+
					"<legend>"+theUILang.User_Interface+"</legend>"+
					$('<div>').addClass('optionColumn userInterfaceOptions').append(
						...[
							['webui.confirm_when_deleting', theUILang.Confirm_del_torr],
							['webui.alternate_color', theUILang.Alt_list_bckgnd],
							['webui.ignore_timeouts', theUILang.dontShowTimeouts],
							['webui.fullrows', theUILang.fullTableRender],
							['webui.no_delaying_draw', theUILang.showScrollTables],
							['webui.log_autoswitch', theUILang.logAutoSwitch],
							['webui.register_magnet', theUILang.registerMagnet],
							['webui.show_cats', theUILang.Show_cat_start],
							['webui.show_dets', theUILang.Show_det_start],
							['webui.open_tegs.keep', theUILang.KeepSearches],
							['webui.selected_tab.keep', theUILang.KeepSelectedTab],
							['webui.selected_labels.keep', theUILang.KeepSelectedLabels],
							['webui.effects', theUILang.UIEffects],
							['webui.speedintitle', theUILang.showSpeedInTitle],
						].map(([id, label]) =>
						$('<div>').append(
							$('<input>').attr({ type: 'checkbox', id, checked: 'true' }),
							$('<label>').attr({ for: id }).text(label)
						)))[0].outerHTML +
					$('<div>').addClass('optionColumn').append(
						...[
							['webui.update_interval', theUILang.Update_GUI_every +':', theUILang.ms, 3000],
							['webui.reqtimeout', theUILang.ReqTimeout +':', theUILang.ms, 5000],
						].map(([id, prefix, suffix, value]) =>
							$('<div>').append(
								$('<span>').text(prefix),
								$('<input>').attr({type: 'number', id, value, min: 0 }),
								$('<span>').text(suffix),
						)),
					$('<div>').append(
						$('<label>').attr({ for: 'webui.speedgraph.max_seconds' }).text(theUILang.speedGraphDuration),
						$('<select>').attr({ id: 'webui.speedgraph.max_seconds' }).append(
							...Object.entries(theUILang.speedGraphDurationOptions).map(([value, text]) =>
								$('<option>').attr({ value }).text(text)
					))))[0].outerHTML+
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
					"<legend>"+theUILang.Other_sett+"</legend>"+
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
							"<td>"+theUILang.Global_max_upl+" ("+theUILang.KB + "/" + theUILang.s+"): [0: "+theUILang.unlimited+"]</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"upload_rate\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
						"<tr>"+
							"<td>"+theUILang.Glob_max_downl+" ("+theUILang.KB + "/" + theUILang.s+"): [0: "+theUILang.unlimited+"]</td>"+
							"<td class=\"alr\"><input type=\"text\" id=\"download_rate\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
						"</tr>"+
					"</table>"+
				"</fieldset>"+
				"<fieldset>"+
					"<legend>"+theUILang.Other_Limiting+"</legend>"+
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
			"<div id=\"st_fmt\" class=\"stg_con\">"+
				"<fieldset>"+
					"<legend>"+theUILang.Format+"</legend>"+
					$('<div>').addClass('optionColumn userInterfaceOptions').append(
						$('<div>').append(
							$('<label>').attr({ for: 'webui.dateformat' }).text(theUILang.DateFormat),
							$('<select>').attr({ id: 'webui.dateformat' }).css({ width: '7em' }).append(
								...Object.entries({0: '31.12.2011', 1: '2011-12-31', 2: '12/31/2011' }).map(([value, text]) =>
									$('<option>').attr({ value }).text(text)
						))),
						...[
							['webui.show_statelabelsize', theUILang.showStateLabelSize],
							['webui.show_labelsize', theUILang.showLabelSize],
							['webui.show_searchlabelsize', theUILang.showSearchLabelSize],
							['webui.labelsize_rightalign', theUILang.labelSizeRightAlign],
							['webui.show_label_path_tree', theUILang.showCustomLabelTree],
							['webui.show_empty_path_labels', theUILang.showEmptyPathLabel],
							['webui.show_label_text_overflow', theUILang.showLabelTextOverflow],
							['webui.show_open_status', theUILang.showOpenStatus],
						].map(([id, label]) =>
						$('<div>').append(
							$('<input>').attr({ type: 'checkbox', id, checked: 'true' }),
							$('<label>').attr({ for: id, id: 'lbl_'+id }).text(label)
						)))[0].outerHTML +
				"</fieldset>"+
				"<fieldset>"+
					"<legend>"+theUILang.DecimalPlacesSizes+"</legend>"+
					$('<table>').append(
						$('<tr>').append(...[ '', 'Default', 'KB', 'MB', 'GB', 'TB', 'PB'].map((unit) =>
							$('<th>').text(unit !== '' ? theUILang[unit] : ''))
						),...Object.entries({
							catlist: theUILang.CatListSizeDecimalPlaces,
							table: theUILang.TableSizeDecimalPlaces,
							details: theUILang.DetailsSizeDecimalPlaces,
							other: theUILang.OtherSizeDecimalPlaces,
						}).map(([context, name]) =>
							$('<tr>').append(...
								$('<th>').text(name),
								...['default', 'kb', 'mb', 'gb', 'tb', 'pb'].map(unit =>
									$('<td>').append(
											$('<input>')
												.attr({
													type: 'number',
													id: 'webui.size_decimal_places.' + context + '.' + unit,
													maxlength: 1,
													min: 0,
												}).addClass('Textbox')
					))))).addClass('decimalDigitEdit')[0].outerHTML+
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
		canChangeTorrentProperties:	0x0080,
		canAddTorrentsWithoutPath:	0x0100,
		canAddTorrentsWithoutStarting:	0x0200,
		canAddTorrentsWithResume:	0x0400,
		canAddTorrentsWithRandomizeHash:	0x0800
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
		$("#prop-ulslots").prop("disabled",true);
		$("#prop-peers_min").prop("disabled",true);
		$("#prop-peers_max").prop("disabled",true);
		$("#prop-tracker_numwant").prop("disabled",true);
		$("#prop-pex").remove();
		$("#lbl_prop-pex").remove();
		$("#prop-superseed").remove();
		$("#lbl_prop-superseed").remove();
		$("#dlgProps .OK").remove();
        }
        if(!(theWebUI.showFlags & showEnum.canAddTorrentsWithoutPath))
	{
		$("#addtorrent #not_add_path_option").remove();
	}
        if(!(theWebUI.showFlags & showEnum.canAddTorrentsWithoutStarting))
	{
		$("#addtorrent #torrents_start_stopped_option").remove();
	}
        if(!(theWebUI.showFlags & showEnum.canAddTorrentsWithResume))
	{
		$("#addtorrent #fast_resume_option").remove();
	}
        if(!(theWebUI.showFlags & showEnum.canAddTorrentsWithRandomizeHash))
	{
		$("#addtorrent #randomize_hash_option").remove();
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
				"get_hash_interval"		: { name: "cat", prm: 0 },
				"get_hash_max_tries"		: { name: "cat", prm: 0 },
				"get_hash_read_ahead"		: { name: "cat", prm: 0 },
				"set_hash_interval"		: { name: "cat", prm: 0 },
				"set_hash_max_tries"		: { name: "cat", prm: 0 },
				"set_hash_read_ahead"		: { name: "cat", prm: 0 }
			});
		}
	}
	if(theWebUI.systemInfo.rTorrent.iVersion>=0x806)
	{
		theRequestManager.aliases[""] = { name: "", prm: 0 };
		$.extend(theRequestManager.aliases,
		{
			"d.set_peer_exchange" 		: { name: "d.peer_exchange.set", prm: 0 },
			"d.set_connection_seed"		: { name: "d.connection_seed.set", prm: 0 }
		});
	}
	if(theWebUI.systemInfo.rTorrent.iVersion>=0x904)
	{
		$.extend(theRequestManager.aliases,
		{
			"create_link"		:	{ name: "d.create_link", prm: 0 },
			"d.get_base_filename"	:	{ name: "d.base_filename", prm: 0 },
			"d.get_base_path"	:	{ name: "d.base_path", prm: 0 },
			"d.get_bitfield"	:	{ name: "d.bitfield", prm: 0 },
			"d.get_bytes_done"	:	{ name: "d.bytes_done", prm: 0 },
			"d.get_chunk_size"	:	{ name: "d.chunk_size", prm: 0 },
			"d.get_chunks_hashed"	:	{ name: "d.chunks_hashed", prm: 0 },
			"d.get_complete"	:	{ name: "d.complete", prm: 0 },
			"d.get_completed_bytes"	:	{ name: "d.completed_bytes", prm: 0 },
			"d.get_completed_chunks"	:	{ name: "d.completed_chunks", prm: 0 },
			"d.get_connection_current"	:	{ name: "d.connection_current", prm: 0 },
			"d.get_connection_leech"	:	{ name: "d.connection_leech", prm: 0 },
			"d.get_connection_seed"	:	{ name: "d.connection_seed", prm: 0 },
			"d.get_creation_date"	:	{ name: "d.creation_date", prm: 0 },
			"d.get_custom"		:	{ name: "d.custom", prm: 0 },
			"d.get_custom1"		:	{ name: "d.custom1", prm: 0 },
			"d.get_custom2"		:	{ name: "d.custom2", prm: 0 },
			"d.get_custom3"		:	{ name: "d.custom3", prm: 0 },
			"d.get_custom4"		:	{ name: "d.custom4", prm: 0 },
			"d.get_custom5"		:	{ name: "d.custom5", prm: 0 },
			"d.get_custom_throw"	:	{ name: "d.custom_throw", prm: 0 },
			"d.get_directory"	:	{ name: "d.directory", prm: 0 },
			"d.get_directory_base"	:	{ name: "d.directory_base", prm: 0 },
			"d.get_down_rate"	:	{ name: "d.down.rate", prm: 0 },
			"d.get_down_total"	:	{ name: "d.down.total", prm: 0 },
			"d.get_free_diskspace"	:	{ name: "d.free_diskspace", prm: 0 },
			"d.get_hash"		:	{ name: "d.hash", prm: 0 },
			"d.get_hashing"		:	{ name: "d.hashing", prm: 0 },
			"d.get_hashing_failed"	:	{ name: "d.hashing_failed", prm: 0 },
			"d.get_ignore_commands"	:	{ name: "d.ignore_commands", prm: 0 },
			"d.get_left_bytes"	:	{ name: "d.left_bytes", prm: 0 },
			"d.get_loaded_file"	:	{ name: "d.loaded_file", prm: 0 },
			"d.get_local_id"	:	{ name: "d.local_id", prm: 0 },
			"d.get_local_id_html"	:	{ name: "d.local_id_html", prm: 0 },
			"d.get_max_file_size"	:	{ name: "d.max_file_size", prm: 0 },
			"d.get_max_size_pex"	:	{ name: "d.max_size_pex", prm: 0 },
			"d.get_message"		:	{ name: "d.message", prm: 0 },
			"d.get_mode"		:	{ name: "d.mode", prm: 0 },
			"d.get_name"		:	{ name: "d.name", prm: 0 },
			"d.get_peer_exchange"	:	{ name: "d.peer_exchange", prm: 0 },
			"d.get_peers_accounted"	:	{ name: "d.peers_accounted", prm: 0 },
			"d.get_peers_complete"	:	{ name: "d.peers_complete", prm: 0 },
			"d.get_peers_connected"	:	{ name: "d.peers_connected", prm: 0 },
			"d.get_peers_max"	:	{ name: "d.peers_max", prm: 0 },
			"d.get_peers_min"	:	{ name: "d.peers_min", prm: 0 },
			"d.get_peers_not_connected"	:	{ name: "d.peers_not_connected", prm: 0 },
			"d.get_priority"	:	{ name: "d.priority", prm: 0 },
			"d.get_priority_str"	:	{ name: "d.priority_str", prm: 0 },
			"d.get_ratio"		:	{ name: "d.ratio", prm: 0 },
			"d.get_size_bytes"	:	{ name: "d.size_bytes", prm: 0 },
			"d.get_size_chunks"	:	{ name: "d.size_chunks", prm: 0 },
			"d.get_size_files"	:	{ name: "d.size_files", prm: 0 },
			"d.get_size_pex"	:	{ name: "d.size_pex", prm: 0 },
			"d.get_skip_rate"	:	{ name: "d.skip.rate", prm: 0 },
			"d.get_skip_total"	:	{ name: "d.skip.total", prm: 0 },
			"d.get_state"		:	{ name: "d.state", prm: 0 },
			"d.get_state_changed"	:	{ name: "d.state_changed", prm: 0 },
			"d.get_state_counter"	:	{ name: "d.state_counter", prm: 0 },
			"d.get_throttle_name"	:	{ name: "d.throttle_name", prm: 0 },
			"d.get_tied_to_file"	:	{ name: "d.tied_to_file", prm: 0 },
			"d.get_tracker_focus"	:	{ name: "d.tracker_focus", prm: 0 },
			"d.get_tracker_numwant"	:	{ name: "d.tracker_numwant", prm: 0 },
			"d.get_tracker_size"	:	{ name: "d.tracker_size", prm: 0 },
			"d.get_up_rate"		:	{ name: "d.up.rate", prm: 0 },
			"d.get_up_total"	:	{ name: "d.up.total", prm: 0 },
			"d.get_uploads_max"	:	{ name: "d.uploads_max", prm: 0 },
			"d.multicall"		:	{ name: "d.multicall2", prm: 1 },
			"d.set_connection_current"	:	{ name: "d.connection_current.set", prm: 1 },
			"d.set_custom"		:	{ name: "d.custom.set", prm: 0 },
			"d.set_custom1"		:	{ name: "d.custom1.set", prm: 0 },
			"d.set_custom2"		:	{ name: "d.custom2.set", prm: 0 },
			"d.set_custom3"		:	{ name: "d.custom3.set", prm: 0 },
			"d.set_custom4"		:	{ name: "d.custom4.set", prm: 0 },
			"d.set_custom5"		:	{ name: "d.custom5.set", prm: 0 },
			"d.set_directory"	:	{ name: "d.directory.set", prm: 0 },
			"d.set_directory_base"	:	{ name: "d.directory_base.set", prm: 0 },
			"d.set_hashing_failed"	:	{ name: "d.hashing_failed.set", prm: 0 },
			"d.set_ignore_commands"	:	{ name: "d.ignore_commands.set", prm: 0 },
			"d.set_max_file_size"	:	{ name: "d.max_file_size.set", prm: 0 },
			"d.set_message"		:	{ name: "d.message.set", prm: 0 },
			"d.set_peers_max"	:	{ name: "d.peers_max.set", prm: 0 },
			"d.set_peers_min"	:	{ name: "d.peers_min.set", prm: 0 },
			"d.set_priority"	:	{ name: "d.priority.set", prm: 0 },
			"d.set_throttle_name"	:	{ name: "d.throttle_name.set", prm: 0 },
			"d.set_tied_to_file"	:	{ name: "d.tied_to_file.set", prm: 0 },
			"d.set_tracker_numwant"	:	{ name: "d.tracker_numwant.set", prm: 0 },
			"d.set_uploads_max"	:	{ name: "d.uploads_max.set", prm: 0 },
			"execute"		:	{ name: "execute2", prm: 1 },
			"execute_capture"	:	{ name: "execute.capture", prm: 1 },
			"execute_capture_nothrow"	:	{ name: "execute.capture_nothrow", prm: 1 },
			"execute_nothrow"	:	{ name: "execute.nothrow", prm: 1 },
			"execute_raw"		:	{ name: "execute.raw", prm: 1 },
			"execute_raw_nothrow"	:	{ name: "execute.raw_nothrow", prm: 1 },
			"execute_throw"		:	{ name: "execute.throw", prm: 1 },
			"f.get_completed_chunks"	:	{ name: "f.completed_chunks", prm: 0 },
			"f.get_frozen_path"	:	{ name: "f.frozen_path", prm: 0 },
			"f.get_last_touched"	:	{ name: "f.last_touched", prm: 0 },
			"f.get_match_depth_next"	:	{ name: "f.match_depth_next", prm: 0 },
			"f.get_match_depth_prev"	:	{ name: "f.match_depth_prev", prm: 0 },
			"f.get_offset"		:	{ name: "f.offset", prm: 0 },
			"f.get_path"		:	{ name: "f.path", prm: 0 },
			"f.get_path_components"	:	{ name: "f.path_components", prm: 0 },
			"f.get_path_depth"	:	{ name: "f.path_depth", prm: 0 },
			"f.get_priority"	:	{ name: "f.priority", prm: 0 },
			"f.get_range_first"	:	{ name: "f.range_first", prm: 0 },
			"f.get_range_second"	:	{ name: "f.range_second", prm: 0 },
			"f.get_size_bytes"	:	{ name: "f.size_bytes", prm: 0 },
			"f.get_size_chunks"	:	{ name: "f.size_chunks", prm: 0 },
			"f.set_priority"	:	{ name: "f.priority.set", prm: 0 },
			"fi.get_filename_last"	:	{ name: "fi.filename_last", prm: 0 },
			"get_bind"		:	{ name: "network.bind_address", prm: 0 },
			"get_check_hash"	:	{ name: "pieces.hash.on_completion", prm: 0 },
			"get_connection_leech"	:	{ name: "protocol.connection.leech", prm: 0 },
			"get_connection_seed"	:	{ name: "protocol.connection.seed", prm: 0 },
			"get_dht_port"		:	{ name: "dht.port", prm: 0 },
			"get_dht_throttle"	:	{ name: "dht.throttle.name", prm: 0 },
			"get_directory"		:	{ name: "directory.default", prm: 0 },
			"get_down_rate"		:	{ name: "throttle.global_down.rate", prm: 0 },
			"get_down_total"	:	{ name: "throttle.global_down.total", prm: 0 },
			"get_download_rate"	:	{ name: "throttle.global_down.max_rate", prm: 0 },
			"get_http_cacert"	:	{ name: "network.http.cacert", prm: 0 },
			"get_http_capath"	:	{ name: "network.http.capath", prm: 0 },
			"get_http_proxy"	:	{ name: "network.http.proxy_address", prm: 0 },
			"get_ip"		:	{ name: "network.local_address", prm: 0 },
			"get_max_downloads_div"	:	{ name: "throttle.max_downloads.div", prm: 0 },
			"get_max_downloads_global"	:	{ name: "throttle.max_downloads.global", prm: 0 },
			"get_max_file_size"	:	{ name: "system.file.max_size", prm: 0 },
			"get_max_memory_usage"	:	{ name: "pieces.memory.max", prm: 0 },
			"get_max_open_files"	:	{ name: "network.max_open_files", prm: 0 },
			"get_max_open_http"	:	{ name: "network.http.max_open", prm: 0 },
			"get_max_open_sockets"	:	{ name: "network.max_open_sockets", prm: 0 },
			"get_max_peers"		:	{ name: "throttle.max_peers.normal", prm: 0 },
			"get_max_peers_seed"	:	{ name: "throttle.max_peers.seed", prm: 0 },
			"get_max_uploads"	:	{ name: "throttle.max_uploads", prm: 0 },
			"get_max_uploads_div"	:	{ name: "throttle.max_uploads.div", prm: 0 },
			"get_max_uploads_global"	:	{ name: "throttle.max_uploads.global", prm: 0 },
			"get_memory_usage"	:	{ name: "pieces.memory.current", prm: 0 },
			"get_min_peers"		:	{ name: "throttle.min_peers.normal", prm: 0 },
			"get_min_peers_seed"	:	{ name: "throttle.min_peers.seed", prm: 0 },
			"get_name"		:	{ name: "session.name", prm: 0 },
			"get_peer_exchange"	:	{ name: "protocol.pex", prm: 0 },
			"get_port_open"		:	{ name: "network.port_open", prm: 0 },
			"get_port_random"	:	{ name: "network.port_random", prm: 0 },
			"get_port_range"	:	{ name: "network.port_range", prm: 0 },
			"get_preload_min_size"	:	{ name: "pieces.preload.min_size", prm: 0 },
			"get_preload_required_rate"	:	{ name: "pieces.preload.min_rate", prm: 0 },
			"get_preload_type"	:	{ name: "pieces.preload.type", prm: 0 },
			"get_proxy_address"	:	{ name: "network.proxy_address", prm: 0 },
			"get_receive_buffer_size"	:	{ name: "network.receive_buffer.size", prm: 0 },
			"get_safe_sync"		:	{ name: "pieces.sync.always_safe", prm: 0 },
			"get_scgi_dont_route"	:	{ name: "network.scgi.dont_route", prm: 0 },
			"get_send_buffer_size"	:	{ name: "network.send_buffer.size", prm: 0 },
			"get_session"		:	{ name: "session.path", prm: 0 },
			"get_session_lock"	:	{ name: "session.use_lock", prm: 0 },
			"get_session_on_completion"	:	{ name: "session.on_completion", prm: 0 },
			"get_split_file_size"	:	{ name: "system.file.split_size", prm: 0 },
			"get_split_suffix"	:	{ name: "system.file.split_suffix", prm: 0 },
			"get_stats_not_preloaded"	:	{ name: "pieces.stats_not_preloaded", prm: 0 },
			"get_stats_preloaded"	:	{ name: "pieces.stats_preloaded", prm: 0 },
			"get_throttle_down_max"	:	{ name: "throttle.down.max", prm: 0 },
			"get_throttle_down_rate"	:	{ name: "throttle.down.rate", prm: 0 },
			"get_throttle_up_max"	:	{ name: "throttle.up.max", prm: 1 },
			"get_throttle_up_rate"	:	{ name: "throttle.up.rate", prm: 1 },
			"get_timeout_safe_sync"	:	{ name: "pieces.sync.timeout_safe", prm: 0 },
			"get_timeout_sync"	:	{ name: "pieces.sync.timeout", prm: 0 },
			"get_tracker_numwant"	:	{ name: "trackers.numwant", prm: 0 },
			"get_up_rate"		:	{ name: "throttle.global_up.rate", prm: 0 },
			"get_up_total"		:	{ name: "throttle.global_up.total", prm: 0 },
			"get_upload_rate"	:	{ name: "throttle.global_up.max_rate", prm: 0 },
			"get_use_udp_trackers"	:	{ name: "trackers.use_udp", prm: 0 },
			"get_xmlrpc_size_limit"	:	{ name: "network.xmlrpc.size_limit", prm: 0 },
			"http_cacert"		:	{ name: "network.http.cacert", prm: 0 },
			"http_capath"		:	{ name: "network.http.capath", prm: 0 },
			"http_proxy"		:	{ name: "network.http.proxy_address", prm: 0 },
			"load_raw"		:	{ name: "load.raw", prm: 1 },
			"load_raw_start"	:	{ name: "load.raw_start", prm: 1 },
			"load_raw_verbose"	:	{ name: "load.raw_verbose", prm: 1 },
			"load_start"		:	{ name: "load.start", prm: 1 },
			"load_start_verbose"	:	{ name: "load.start_verbose", prm: 1 },
			"load_verbose"		:	{ name: "load.verbose", prm: 1 },
			"p.get_address"		:	{ name: "p.address", prm: 0 },
			"p.get_client_version"	:	{ name: "p.client_version", prm: 0 },
			"p.get_completed_percent"	:	{ name: "p.completed_percent", prm: 0 },
			"p.get_down_rate"	:	{ name: "p.down_rate", prm: 0 },
			"p.get_down_total"	:	{ name: "p.down_total", prm: 0 },
			"p.get_id"		:	{ name: "p.id", prm: 0 },
			"p.get_id_html"		:	{ name: "p.id_html", prm: 0 },
			"p.get_options_str"	:	{ name: "p.options_str", prm: 0 },
			"p.get_peer_rate"	:	{ name: "p.peer_rate", prm: 0 },
			"p.get_peer_total"	:	{ name: "p.peer_total", prm: 0 },
			"p.get_port"		:	{ name: "p.port", prm: 0 },
			"p.get_up_rate"		:	{ name: "p.up_rate", prm: 0 },
			"p.get_up_total"	:	{ name: "p.up_total", prm: 0 },
			"peer_exchange"		:	{ name: "protocol.pex.set", prm: 1 },
			"port_open"		:	{ name: "network.port_open", prm: 0 },
			"session_save"		:	{ name: "session.save", prm: 0 },
			"set_bind"		:	{ name: "network.bind_address.set", prm: 1 },
			"set_check_hash"	:	{ name: "pieces.hash.on_completion.set", prm: 1 },
			"set_connection_leech"	:	{ name: "protocol.connection.leech.set", prm: 1 },
			"set_connection_seed"	:	{ name: "protocol.connection.seed.set", prm: 1 },
			"set_dht_port"		:	{ name: "dht.port.set", prm: 1 },
			"set_dht_throttle"	:	{ name: "dht.throttle.name.set", prm: 1 },
			"set_directory"		:	{ name: "directory.default.set", prm: 1 },
			"set_download_rate"	:	{ name: "throttle.global_down.max_rate.set", prm: 1 },
			"set_http_cacert"	:	{ name: "network.http.cacert.set", prm: 1 },
			"set_http_capath"	:	{ name: "network.http.capath.set", prm: 1 },
			"set_http_proxy"	:	{ name: "network.http.proxy_address.set", prm: 1 },
			"set_ip"		:	{ name: "network.local_address.set", prm: 1 },
			"set_max_downloads_div"	:	{ name: "throttle.max_downloads.div.set", prm: 1 },
			"set_max_downloads_global"	:	{ name: "throttle.max_downloads.global.set", prm: 1 },
			"set_max_file_size"	:	{ name: "system.file.max_size.set", prm: 1 },
			"set_max_memory_usage"	:	{ name: "pieces.memory.max.set", prm: 1 },
			"set_max_open_files"	:	{ name: "network.max_open_files.set", prm: 1 },
			"set_max_open_http"	:	{ name: "network.http.max_open.set", prm: 1 },
			"set_max_peers"		:	{ name: "throttle.max_peers.normal.set", prm: 1 },
			"set_max_peers_seed"	:	{ name: "throttle.max_peers.seed.set", prm: 1 },
			"set_max_uploads"	:	{ name: "throttle.max_uploads.set", prm: 1 },
			"set_max_uploads_div"	:	{ name: "throttle.max_uploads.div.set", prm: 1 },
			"set_max_uploads_global"	:	{ name: "throttle.max_uploads.global.set", prm: 1 },
			"set_min_peers"		:	{ name: "throttle.min_peers.normal.set", prm: 1 },
			"set_min_peers_seed"	:	{ name: "throttle.min_peers.seed.set", prm: 1 },
			"set_name"		:	{ name: "session.name.set", prm: 1 },
			"set_peer_exchange"	:	{ name: "protocol.pex.set", prm: 1 },
			"set_port_open"		:	{ name: "network.port_open.set", prm: 1 },
			"set_port_random"	:	{ name: "network.port_random.set", prm: 1 },
			"set_port_range"	:	{ name: "network.port_range.set", prm: 1 },
			"set_preload_min_size"	:	{ name: "pieces.preload.min_size.set", prm: 1 },
			"set_preload_required_rate"	:	{ name: "pieces.preload.min_rate.set", prm: 1 },
			"set_preload_type"	:	{ name: "pieces.preload.type.set", prm: 1 },
			"set_proxy_address"	:	{ name: "network.proxy_address.set", prm: 1 },
			"set_receive_buffer_size"	:	{ name: "network.receive_buffer.size.set", prm: 1 },
			"set_safe_sync"		:	{ name: "pieces.sync.always_safe.set", prm: 1 },
			"set_scgi_dont_route"	:	{ name: "network.scgi.dont_route.set", prm: 1 },
			"set_send_buffer_size"	:	{ name: "network.send_buffer.size.set", prm: 1 },
			"set_session"		:	{ name: "session.path.set", prm: 1 },
			"set_session_lock"	:	{ name: "session.use_lock.set", prm: 1 },
			"set_session_on_completion"	:	{ name: "session.on_completion.set", prm: 1 },
			"set_split_file_size"	:	{ name: "system.file.split_size.set", prm: 1 },
			"set_split_suffix"	:	{ name: "system.file.split_suffix.set", prm: 1 },
			"set_timeout_safe_sync"	:	{ name: "pieces.sync.timeout_safe.set", prm: 1 },
			"set_timeout_sync"	:	{ name: "pieces.sync.timeout.set", prm: 1 },
			"set_tracker_numwant"	:	{ name: "trackers.numwant.set", prm: 1 },
			"set_upload_rate"	:	{ name: "throttle.global_up.max_rate.set", prm: 1 },
			"set_use_udp_trackers"	:	{ name: "trackers.use_udp.set", prm: 1 },
			"set_xmlrpc_dialect"	:	{ name: "network.xmlrpc.dialect.set", prm: 1 },
			"set_xmlrpc_size_limit"	:	{ name: "network.xmlrpc.size_limit.set", prm: 1 },
			"schedule"		:	{ name: "schedule2", prm: 1 },
			"schedule_remove"	:	{ name: "schedule_remove2", prm: 1 },
			"system.file_allocate"	:	{ name: "system.file.allocate", prm: 0 },
			"system.file_allocate.set"	:	{ name: "system.file.allocate.set", prm: 1 },
			"system.method.erase"	:	{ name: "method.erase", prm: 1 },
			"system.method.get"	:	{ name: "method.get", prm: 1 },
			"system.method.has_key"	:	{ name: "method.has_key", prm: 1 },
			"system.method.insert"	:	{ name: "method.insert", prm: 1 },
			"system.method.list_keys"	:	{ name: "method.list_keys", prm: 1 },
			"system.method.set"	:	{ name: "method.set", prm: 1 },
			"system.method.set_key"	:	{ name: "method.set_key", prm: 1 },
			"t.get_group"		:	{ name: "t.group", prm: 0 },
			"t.get_id"		:	{ name: "t.id", prm: 0 },
			"t.get_min_interval"	:	{ name: "t.min_interval", prm: 0 },
			"t.get_normal_interval"	:	{ name: "t.normal_interval", prm: 0 },
			"t.get_scrape_complete"	:	{ name: "t.scrape_complete", prm: 0 },
			"t.get_scrape_downloaded"	:	{ name: "t.scrape_downloaded", prm: 0 },
			"t.get_scrape_incomplete"	:	{ name: "t.scrape_incomplete", prm: 0 },
			"t.get_scrape_time_last"	:	{ name: "t.scrape_time_last", prm: 0 },
			"t.get_type"		:	{ name: "t.type", prm: 0 },
			"t.get_url"		:	{ name: "t.url", prm: 0 },
			"t.set_enabled"		:	{ name: "t.is_enabled.set", prm: 0 },
			"throttle_down"		:	{ name: "throttle.down", prm: 1 },
			"throttle_ip"		:	{ name: "throttle.ip", prm: 1 },
			"throttle_up"		:	{ name: "throttle.up", prm: 1 },
			"tracker_numwant"	:	{ name: "trackers.numwant", prm: 0 },
			"use_udp_trackers"	:	{ name: "trackers.use_udp.set", prm: 1 },
			"view_add"		:	{ name: "view.add", prm: 1 },
			"view_filter"		:	{ name: "view.filter", prm: 1 },
			"view_filter_on"	:	{ name: "view.filter_on", prm: 1 },
			"view_list"		:	{ name: "view.list", prm: 0 },
			"view_set"		:	{ name: "view.set", prm: 1 },
			"view_sort"		:	{ name: "view.sort", prm: 1 },
			"view_sort_current"	:	{ name: "view.sort_current", prm: 1 },
			"view_sort_new"		:	{ name: "view.sort_new", prm: 1 },
			"xmlrpc_dialect"	:	{ name: "network.xmlrpc.dialect.set", prm: 1 },
			"xmlrpc_size_limit"	:	{ name: "network.xmlrpc.size_limit.set", prm: 1 },
			"delete_link"		:	{ name: "d.delete_link", prm: 0 },
			"delete_tied"		:	{ name: "d.delete_tied", prm: 0 },
			"dht_add_node"		:	{ name: "dht.add_node", prm: 1 },
			"dht_statistics"	:	{ name: "dht.statistics", prm: 0 },
			"load"			:	{ name: "load.normal", prm: 1 }
		});
	}
	if(theWebUI.systemInfo.rTorrent.iVersion < 0x907) {
		const title = theUILang.requiresAtLeastRtorrent.replace('{version}', 'v0.9.7');
		$($$('webui.show_open_status')).attr({ disabled: '', title });
		$($$('lbl_webui.show_open_status')).attr({ title }).addClass('disabled');
	}
	if(theWebUI.systemInfo.rTorrent.apiVersion>=11)	// at current moment (2019.07.20) this is feature-bind branch of rtorrent
	{
		$.extend(theRequestManager.aliases,
		{
			"get_port_open"		: { name: "network.listen.is_open", prm: 0 },
			"get_port_random" 	: { name: "network.port.randomize", prm: 0 },
			"get_port_range" 	: { name: "network.port.range", prm: 0 },
			"set_port_open"		: { name: "network.listen.open", prm: 1 },
			"set_port_random"	: { name: "network.port.randomize.set", prm: 1 },
			"set_port_range"	: { name: "network.port.range.set", prm: 1 },
			"network.listen.port" 	: { name: "network.port", prm: 0 }
		});
	}
	$("#rtorrentv").text(theWebUI.systemInfo.rTorrent.version+"/"+theWebUI.systemInfo.rTorrent.libVersion);
}
