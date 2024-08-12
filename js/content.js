/*
 *      UI content.
 *
 */

function makeContent()
{
	$("#st_up").mouseclick(theWebUI.upRateMenu);
	$("#st_down").mouseclick(theWebUI.downRateMenu);

	[
		"add", "remove", "start", "pause", "stop",
		"settings", "search", "go", "help"
	].forEach(id => {
		$(`#mnu_${id}`).attr({title:theUILang[`mnu_${id}`] + "..."}).append(
			$("<span>").addClass("d-inline d-md-none").text(theUILang[`mnu_${id}`]),
		);
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
		$("#add_button").prop("disabled",true);
		var d = this.contentDocument;
		if(d && (d.location.href != "about:blank"))
		{
			try { var txt = d.body.textContent ? d.body.textContent : d.body.innerText; eval(txt); } catch(e) {}
		}
	}));
	$(document.body).append($("<iframe name='uploadfrmurl'/>").css({visibility: "hidden"}).attr( { name: "uploadfrmurl" } ).width(0).height(0).on('load', function()
	{
		$("#url").val("");
		var d = this.contentDocument;
		if(d && d.location.href != "about:blank")
			try { eval(d.body.textContent ? d.body.textContent : d.body.innerText); } catch(e) {}
	}));
	theDialogManager.make("padd",theUILang.peerAdd,
		'<div class="content fxcaret">'+theUILang.peerAddLabel+'<br><input type="text" id="peerIP" class="Textbox" value="my.friend.addr:6881"/></div>'+
		'<div class="aright buttons-list"><input type="button" class="OK Button" value="'+theUILang.ok+'" onclick="theWebUI.addNewPeer();theDialogManager.hide(\'padd\');return(false);" />'+
			'<input type="button" class="Cancel Button" value="'+theUILang.Cancel+'"/></div>',
		true);
	theDialogManager.make("tadd",theUILang.torrent_add,
		$("<div>").addClass("cont fxcaret").append(
			$("<form>").attr(
				{action: "addtorrent.php", id: "addtorrent", method: "post", enctype: "multipart/form-data", target: "uploadfrm"}
			).append(
				$("<fieldset>").append(
					$("<legend>").text(theUILang.Torrent_options),
					$("<div>").addClass("row").append(
						$("<div>").addClass("d-none col-md-3 d-md-flex align-items-center justify-content-end").append(
							$("<label>").attr({for: "dir_edit"}).text(theUILang.Base_directory + ": "),
						),
						$("<div>").addClass("col-md-9 d-flex flex-row align-items-center").append(
							$("<input>").attr(
								{type: "text", id: "dir_edit", name: "dir_edit", placeholder: theUILang.Base_directory}
							).addClass("flex-grow-1"),
						),
					),
					$("<div>").addClass("row").append(
						$("<div>").addClass("col-md-9 offset-md-3 d-flex flex-column align-items-start").append(
							...[
								["not_add_path", theUILang.Dont_add_tname],
								["torrents_start_stopped", theUILang.Dnt_start_down_auto],
								["fast_resume", theUILang.doFastResume],
								["randomize_hash", theUILang.doRandomizeHash],
							].map(([id, label]) => $("<div>").attr({id: id + "_option"}).addClass(
								"d-flex flex-row align-items-center"
							).append(
									$("<input>").attr({type: "checkbox", name: id, id: id}),
									$("<label>").attr({for: id}).text(label),
								)
							),
						)
					),
					$("<div>").addClass("row").append(
						$("<div>").addClass("d-none col-md-3 d-md-flex justify-content-end align-items-center").append(
							$("<label>").attr({for: "tadd_label"}).text(theUILang.Label + ": "),
						),
						$("<div>").addClass("col-md-7 d-flex flex-row align-items-center").append(
							$("<input>").attr({type: "text", id: "tadd_label", name: "tadd_label", placeholder: theUILang.Label}).addClass("flex-grow-1"),
							$("<select>").attr({id: "tadd_label_select"}).addClass("flex-grow-1"),
						),
						$("<div>").addClass("col-md-2 d-flex align-items-center").append(
							$("<button>").attr({type: "button", id: "tadd-return-select", name: "tadd-return-select"}).text(theUILang.Return_select_label),
						),
					),
				),
				$("<fieldset>").append(
					$("<legend>").text(theUILang.Add_from_file),
					$("<div>").addClass("row").append(
						$("<div>").addClass("d-none col-md-3 d-md-flex justify-content-end align-items-center").append(
							$("<label>").attr({for: "torrent_file"}).text(theUILang.Torrent_file + ": "),
						),
						$("<div>").addClass("col-md-6 d-flex").append(
							$("<input>").attr({type: "file", multiple: "multiple", name: "torrent_file[]", id: "torrent_file", accept: ".torrent"}).addClass("flex-shrink-1"),
						),
						$("<div>").addClass("col-md-3 d-flex align-items-center").append(
							$("<input>").val(theUILang.add_button).attr({type: "submit", id: "add_button"}).addClass("Button").prop("disabled", true),
						),
					),
				),
			),
			$("<form>").attr(
				{action: "addtorrent.php", id: "addtorrenturl", method: "post", target: "uploadfrmurl"}
			).append(
				$("<fieldset>").append(
					$("<legend>").text(theUILang.Add_from_URL),
					$("<div>").addClass("row").append(
						$("<div>").addClass("d-none col-md-3 d-md-flex justify-content-end align-items-center").append(
							$("<label>").attr({for: "url"}).text(theUILang.Torrent_URL + ": "),
						),
						$("<div>").addClass("col-md-6 d-flex").append(
							$("<input>").attr({type: "text", id: "url", name: "url", placeholder: theUILang.Torrent_URL}).addClass("flex-grow-1"),
						),
						$("<div>").addClass("col-md-3 d-flex align-items-center").append(
							$("<input>").val(theUILang.add_url).attr({type: "submit", id: "add_url"}).addClass("Button").prop("disabled", true),
						),
					),
				),
			),
		)[0].outerHTML
  );

	$("#tadd_label_select").on('change', function(e) {
		const index = this.selectedIndex;
		switch (index) {
			case 1: {
				$(this).hide();
				$("#tadd_label").show();
				$("#tadd-return-select").show();
			} break;
			case 0: {
				$("#tadd_label").val("");
			} break;
			default: {
				$("#tadd_label").val(this.options[index].value);
			} break;
		}
	});

	$("#tadd-return-select").on("click", function(e) {
		$(this).hide();
		$("#tadd_label").val("").hide();
		$("#tadd_label_select").prop("selectedIndex", 0);
		$("#tadd_label_select").show();
	});

	theDialogManager.setHandler('tadd','beforeShow',function()
	{
		$("#tadd_label").hide();
		$("#tadd-return-select").hide();
		$("#tadd_label_select").empty()
			.append('<option selected>'+theUILang.No_label+'</option>')
			.append('<option>'+theUILang.newLabel+'</option>').show();
		for(const [torrentLabel] of theWebUI.categoryList.torrentLabelTree.torrentLabels)
			$("#tadd_label_select").append("<option>"+torrentLabel+"</option>");
		$("#torrent_file").val("");
		$("#add_button").prop("disabled", true);
		$("#tadd_label_select").trigger('change');
	});

	const file_input = $$("torrent_file");
	file_input.onchange = function() {
		$("#add_button").prop("disabled",file_input.files.length===0);
	};
	const url_input = $$('url');
	url_input.onupdate = url_input.onkeyup = function() { $('#add_url').prop('disabled',url_input.value.trim()===''); };
	url_input.onpaste = function() { setTimeout( url_input.onupdate, 10 ) };
	var makeAddRequest = function(frm) {
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
		return true;
	}
	$("#addtorrent").on('submit', function() {
		if (!$("#torrent_file").val().match(/\.torrent$/i)) {
			alert(theUILang.Not_torrent_file);
	   		return false;
   		}
		$("#add_button").prop("disabled", true);
		return makeAddRequest(this);
	});
	$("#addtorrenturl").on('submit', function() {
	   	$("#add_url").prop("disabled", true);
	   	return makeAddRequest(this);
	});
	theDialogManager.make("dlgProps", theUILang.Torrent_properties,
		$("<div>").addClass("cont fxcaret").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Bandwidth_sett),
				$("<div>").addClass("row").append(
					...[
						["prop-ulslots", theUILang.Number_ul_slots],
						["prop-peers_min", theUILang.Number_Peers_min],
						["prop-peers_max", theUILang.Number_Peers_max],
						["prop-tracker_numwant", theUILang.Tracker_Numwant],
					].flatMap(([id, text]) => [
						$("<div>").addClass("col-12 col-md-6").append(
							$("<label>").attr({for:id}).text(text + ": "),
						),
						$("<div>").addClass("col-12 col-md-6").append(
							$("<input>").attr({type:"text", id:id,})
						),
					]),
					...[
						["prop-pex", theUILang.Peer_ex],
						["prop-superseed", theUILang.SuperSeed],
					].map(([id, text]) => $("<div>").addClass("col-12 col-md-6").append(
						$("<input>").attr({type:"checkbox", id:id}),
						$("<label>").attr({for:id, id:`lbl_${id}`}).text(text),
					)),
				),
			),
		)[0].outerHTML +
		$("<div>").addClass("buttons-list").append(
			$("<button>").attr({type:"button", onclick:"theWebUI.setProperties(); return(false);"}).addClass("OK").text(theUILang.ok),
			$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
		)[0].outerHTML,
		true,
	);
	theDialogManager.make("dlgHelp", theUILang.Help,
		$("<div>").addClass("py-2 container-fluid").append(
			$("<div>").addClass("row").append(
				...[
					["F1", theUILang.This_screen,],
					["Ctrl-F1", theUILang.About_program, 'theDialogManager.toggle("dlgAbout"); return(false);'],
					["F4", theUILang.Toggle_menu, "theWebUI.toggleMenu(); return(false);"],
					["F6", theUILang.Toggle_details, "theWebUI.toggleDetails(); return(false);"],
					["F7", theUILang.Toggle_categories, "theWebUI.toggleCategories(); return(false);"],
					["Ctrl-O", theUILang.torrent_add, "theWebUI.showAdd(); return(false);"],
					["Ctrl-P", theUILang.ruTorrent_settings, "theWebUI.showSettings(); return(false);"],
					["Ctrl-F", theUILang.Quick_search,],
					["Del", theUILang.Delete_current_torrents,],
					["Ctrl-A", theUILang.Select_all,],
					["Ctrl-Z", theUILang.Deselect_all,],
				].flatMap(([keyboard, text, action]) => [
					$("<div>").addClass("col-4").append(
						$("<span>").addClass("fw-bold").text(keyboard),
					),
					$("<div>").addClass("col-8").append(
						action ?
						$("<a>").attr({href:"#", onclick:action}).text(text) :
						$("<span>").text(text)
					),
				]),
			),
		)[0].outerHTML,
	);
	theDialogManager.make("dlgAbout", "ruTorrent v" + theWebUI.version,
		$("<div>").addClass("py-2 container-fluid").append(
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-12 pb-3").append(
					$("<strong>").text(theUILang.Developers + ": "),
				),
				$("<div>").addClass("col-11 offset-1").append(
					$("<span>").text(theUILang.Original_webui + ": "),
				),
				$("<div>").addClass("col-10 offset-2").append(
					$("<span>").text("Carsten Niebuhr (Directrix)"),
				),
				$("<div>").addClass("col-11 offset-1").append(
					$("<span>").text(theUILang.rTorrent_adaption + ": "),
				),
				$("<div>").addClass("col-10 offset-2").append(
					$("<span>").text("Moskalets Alexander ("),
					$("<a>").attr({href:"mailto:novik65@gmail.com"}).text("Novik"),
					$("<span>").text(")")
				),
				$("<div>").addClass("pt-3 col-12").append(
					$("<strong>").text(theUILang.Check_new_version),
					$("<span>").html("&nbsp;"),
					$("<a>").attr({href:"https://github.com/Novik/ruTorrent", target:"_blank"}).text(theUILang.here),
				),
			),
		)[0].outerHTML,
	);
	theDialogManager.make("dlgLabel", theUILang.enterLabel,
		$("<div>").addClass("cont fxcaret").append(
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-12").append(
					$("<label>").attr({for:"txtLabel"}).text(theUILang.Enter_label_prom + ": "),
				),
				$("<div>").addClass("col-12").append(
					$("<input>").attr({type:"text", id:"txtLabel"}),
				),
			),
		)[0].outerHTML +
		$("<div>").addClass("buttons-list").append(
			$("<button>").attr({type:"button", onclick:"theWebUI.createLabel();theDialogManager.hide('dlgLabel');return(false);"}).addClass("OK").text(theUILang.ok),
			$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
		)[0].outerHTML,
		true,
	);
	theDialogManager.setHandler('dlgLabel', 'afterShow', function() {
		setTimeout(function() {
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

	const stgPanel = $("<div>").addClass("lm").append(
		$("<ul>").append(
			...[
				["st_gl", theUILang.General, true],
				["st_dl", theUILang.Downloads],
				["st_con", theUILang.Connection],
				["st_bt", theUILang.BitTorrent],
				["st_fmt", theUILang.Format],
				["st_ao", theUILang.Advanced],
			].map(([id, text, defaultFocus]) => $("<li>").attr({id: `hld_${id}`}).append(
				$("<a>").attr(
					{id: `mnu_${id}`, href: "#", onclick: `theOptionsSwitcher.run('${id}');return(false);`}
				).addClass(defaultFocus ? "focus" : "").text(text),
			)),
		),
	);

	const stgGlCont = $("<div>").attr({id: "st_gl"}).addClass("stg_con").append(
		$("<fieldset>").append(
			$("<legend>").text(theUILang.User_Interface),
			$("<div>").addClass("row").append(
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
				].map(([id, label]) => $("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "checkbox", id: id}),
					$("<label>").attr({for: id}).text(label),
				)),
			),
			...[
				["webui.update_interval", theUILang.Update_GUI_every + ": ", theUILang.ms, 3000],
				["webui.reqtimeout", theUILang.ReqTimeout + ": ", theUILang.ms, 5000],
			].map(([id, prefix, suffix, value]) => $("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6").append(
					$('<label>').attr({for: id}).text(prefix),
				),
				$("<div>").addClass("col-9 col-md-5").append(
					$('<input>').attr({type: "number", id: id, value: value, min: 0}).addClass("flex-grow-1"),
				),
				$("<div>").addClass("col-3 col-md-1").append(
					$('<span>').text(suffix),
				),
			)),
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6").append(
					$("<label>").attr({for: "webui.speedgraph.max_seconds"}).text(theUILang.speedGraphDuration + ": "),
				),
				$("<div>").addClass("col-md-6").append(
					$("<select>").attr({id: "webui.speedgraph.max_seconds"}).addClass("flex-grow-1").append(
						...Object.entries(theUILang.speedGraphDurationOptions).map(([value, text]) => $("<option>").attr({value: value}).text(text),
					)),
				),
			),
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6").append(
					$("<label>").attr({for: "webui.retry_on_error"}).text(theUILang.retryOnErrorTitle + ": "),
				),
				$("<div>").addClass("col-md-6").append(
					$("<select>").attr({id: "webui.retry_on_error"}).append(
						...Object.entries(theUILang.retryOnErrorList).map(
							([index, text]) => $("<option>").val(index).text(text)
						),
					),
				),
			),
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-6 col-md-3").append(
					$("<label>").attr({for: "webui.lang"}).text(theUILang.mnu_lang + ": "),
				),
				$("<div>").addClass("col-6 col-md-3").append(
					$("<select>").attr({id: "webui.lang"}).append(
						...Object.entries(AvailableLanguages).map(
							([code, language]) => $("<option>").val(code).text(language)
						),
					),
				),
			),
		),
		$("<fieldset>").append(
			$("<legend>").text(theUILang.speedList),
			$("<div>").addClass("row").append(
				...[
					["webui.speedlistul", theUILang.UL],
					["webui.speedlistdl", theUILang.DL],
				].flatMap(([id, text]) => [
					$("<div>").addClass("col-md-2").append(
						$("<label>").attr({for:id}).text(text + ": "),
					),
					$("<div>").addClass("col-md-10").append(
						$("<input>").attr({type:"text", id:id}).prop("maxlength", 128).addClass("speedEdit"),
					),
				]),
			),
		),
	);

	const stgDlCont = $("<div>").attr({id: "st_dl"}).addClass("stg_con").append(
		$("<fieldset>").append(
			$("<legend>").text(theUILang.Bandwidth_sett),
			...[
				[theUILang.Number_ul_slots, "max_uploads"],
				[theUILang.Number_Peers_min, "min_peers"],
				[theUILang.Number_Peers_max, "max_peers"],
				[theUILang.Number_Peers_For_Seeds_min, "min_peers_seed"],
				[theUILang.Number_Peers_For_Seeds_max, "max_peers_seed"],
				[theUILang.Tracker_Numwant, "tracker_numwant"],
			].map(([label, id]) => $("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6").append(
					$("<label>").attr({for: id}).text(label + ": "),
				),
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "text", id: id}).prop("maxlength", 6),
				),
			)),
		),
		$("<fieldset>").append(
			$("<legend>").text(theUILang.Other_sett),
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "checkbox", id: "check_hash"}),
					$("<label>").attr({for: "check_hash"}).text(theUILang.Check_hash),
				),
			),
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6").append(
					$("<label>").attr({for: "directory"}).text(theUILang.Directory_For_Dl + ": "),
				),
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "text", id: "directory"}).prop("maxlength", 100),
				),
			),
		),
	);

	const stgConCont = $("<div>").attr({id: "st_con"}).addClass("stg_con").append(
		$("<fieldset>").append(
			$("<legend>").text(theUILang.Listening_Port),
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "checkbox", id: "port_open", onclick: "linked(this, 0, ['port_range', 'port_random']);"}),
					$("<label>").attr({for: "port_open"}).text(theUILang.Enable_port_open),
				),
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "checkbox", id: "port_random"}),
					$("<label>").attr({for: "port_random", id: "lbl_port_random"}).text(theUILang.Rnd_port_torr_start),
				),
				$("<div>").addClass("col-md-6").append(
					$("<label>").attr({for: "port_range", id: "lbl_port_range"}).text(theUILang.Port_f_incom_conns + ": "),
				),
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "text", id: "port_range"}).prop("maxlength", 13),
				),
			),
		),
		$("<fieldset>").append(
			$("<legend>").text(theUILang.Bandwidth_Limiting),
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-md-8").append(
					$("<label>").attr({for: "upload_rate"}).text(`${theUILang.Global_max_upl} (${theUILang.KB}/${theUILang.s}): [0: ${theUILang.unlimited}]`),
				),
				$("<div>").addClass("col-md-4").append(
					$("<input>").attr({type: "text", id: "upload_rate"}).prop("maxlength", 6),
				),
				$("<div>").addClass("col-md-8").append(
					$("<label>").attr({for: "download_rate"}).text(`${theUILang.Glob_max_downl} (${theUILang.KB}/${theUILang.s}): [0: ${theUILang.unlimited}]`),
				),
				$("<div>").addClass("col-md-4").append(
					$("<input>").attr({type: "text", id: "download_rate"}).prop("maxlength", 6),
				),
			),
		),
		$("<fieldset>").append(
			$("<legend>").text(theUILang.Other_Limiting),
			...[
				["max_uploads_global", theUILang.Number_ul_slots],
				["max_downloads_global", theUILang.Number_dl_slots],
				["max_memory_usage", theUILang.Glob_max_memory + " (" + theUILang.MB + ")"],
				["max_open_files", theUILang.Glob_max_files],
				["max_open_http", theUILang.Glob_max_http],
			].map(([id, label]) => $("<div>").addClass("row").append(
				$("<div>").addClass("col-md-8").append(
					$("<label>").attr({for: id}).text(label + ": "),
				),
				$("<div>").addClass("col-md-4").append(
					$("<input>").attr({type: "number", id: id}).prop("maxlength", 6),
				),
			)),
		),
	);

	const stgBtCont = $("<div>").attr({id: "st_bt"}).addClass("stg_con").append(
		$("<fieldset>").append(
			$("<legend>").text(theUILang.Add_bittor_featrs),
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "checkbox", id: "dht", onchange: "linked(this, 0, ['dht_port']);"}),
					$("<label>").attr({for: "dht"}).text(theUILang.En_DHT_ntw),
				),
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "checkbox", id: "peer_exchange"}),
					$("<label>").attr({for: "peer_exchange"}).text(theUILang.Peer_exch),
				),
				$("<div>").addClass("col-md-6").append(
					$("<label>").attr({id:"lbl_dht_port", for:"dht_port"}).addClass("disabled").text(theUILang.dht_port + ": "),
				),
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type:"text", id:"dht_port"}).prop("maxlength", 6).prop("disabled", true),
				),
				$("<div>").addClass("col-md-6").append(
					$("<label>").attr({id:"lbl_ip", for:"ip"}).text(theUILang.Ip_report_track + ": "),
				),
				$("<div>").addClass("col-md-6").append(
					$("<input>").attr({type:"text", id:"ip"}).prop("maxlength", 6),
				),
			),
		),
	);

	const stgFmtCont = $("<div>").attr({id: "st_fmt"}).addClass("stg_con").append(
		$("<fieldset>").append(
			$("<legend>").text(theUILang.Format),
			$("<div>").addClass("row").append(
				$("<div>").addClass("col-md-6").append(
					$("<label>").attr({for: "webui.dateformat"}).addClass("flex-shrink-0").text(theUILang.DateFormat + ": "),
					$("<select>").attr({ id: "webui.dateformat"}).addClass("flex-shrink-1").append(
						...Object.entries({0: "31.12.2011", 1: "2011-12-31", 2: "12/31/2011"}).map(
							([value, text]) => $("<option>").val(value).text(text)
						),
					),
				),
				...[
					['webui.show_viewlabelsize', theUILang.showViewLabelSize],
					['webui.show_statelabelsize', theUILang.showStateLabelSize],
					['webui.show_labelsize', theUILang.showLabelSize],
					['webui.show_searchlabelsize', theUILang.showSearchLabelSize],
					['webui.labelsize_rightalign', theUILang.labelSizeRightAlign],
					['webui.show_label_path_tree', theUILang.showCustomLabelTree],
					['webui.show_empty_path_labels', theUILang.showEmptyPathLabel],
					['webui.show_label_text_overflow', theUILang.showLabelTextOverflow],
					['webui.show_open_status', theUILang.showOpenStatus],
					['webui.show_view_panel', theUILang.showViewPanel],
				].map(([id, label]) => $("<div>").addClass("col-md-6").append(
					$("<input>").attr({type: "checkbox", id: id, checked: "true"}),
					$("<label>").attr({for: id, id: "lbl_" + id}).text(label),
				)),
			),
		),
		$("<fieldset>").append(
			$("<legend>").text(theUILang.DecimalPlacesSizes),
			$("<div>").addClass("decimalDigitEdit").append(
				$("<div>").addClass("row").append(
					...[
						"", "Default", "KB", "MB", "GB", "TB", "PB",
					].map((unit) => $("<div>").addClass(
						unit !== "" ? "col" : "col-12 col-md-3"						
					).text(unit !== "" ? theUILang[unit] : "")),
				),
				...Object.entries({
					catlist: theUILang.CatListSizeDecimalPlaces,
					table: theUILang.TableSizeDecimalPlaces,
					details: theUILang.DetailsSizeDecimalPlaces,
					other: theUILang.OtherSizeDecimalPlaces,
				}).map(([context, name]) => $('<div>').addClass("row").append(
					$("<div>").addClass("col-12 col-md-3").text(name + ": "),
					...["default", "kb", "mb", "gb", "tb", "pb"].map(unit => $("<div>").addClass("col").append(
						$("<input>").attr({
							type: "number",
							id: "webui.size_decimal_places." + context + "." + unit,
							maxlength: 1,
							min: 0,
						}),
					)),
				)),				
			),
		),
	);

	const stgAoCont = $("<div>").attr({id: "st_ao"}).addClass("stg_con").append(
		$("<fieldset>").append(
			$("<legend>").text(theUILang.Advanced),
			$("<div>").attr({id: "st_ao_h"}).append(
				...[
					["hash_interval", 20], ["hash_max_tries", 5], ["hash_read_ahead", 20],
					["http_cacert", 100], ["http_capath", 100],
					["max_downloads_div", 5], ["max_uploads_div", 5], ["max_file_size", 20],
				].map(([id, maxlength]) => $("<div>").addClass("row").append(
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: id}).text(id),
					), 
					$("<div>").addClass("col-md-6").append(
						$("<input>").attr({type: "text", id: id}).prop("maxlength", maxlength),
					),
				)),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: "preload_type"}).text("preload_type"),
					),
					$("<div>").addClass("col-md-6").append(
						$("<select>").attr({id: "preload_type"}).append(
							$("<option>").val(0).text("off"),
							$("<option>").val(1).text("madvise"),
							$("<option>").val(2).text("direct paging"),
						),
					),
				),
				...[
					["preload_min_size", 20], ["preload_required_rate", 20],
					["receive_buffer_size", 20], ["send_buffer_size", 20],
					["safe_sync", ], ["timeout_safe_sync", 20], ["timeout_sync", 20], ["scgi_dont_route", ],
					["session", 100], ["session_lock", ], ["session_on_completion",],
					["split_file_size", 20], ["split_suffix", 100], ["use_udp_trackers", ],
					["http_proxy", 100], ["proxy_address", 100], ["bind", 100],
				].map(([id, maxlength]) => $("<div>").addClass("row").append(
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: id}).text(id),
					),
					$("<div>").addClass("col-md-6").append(
						maxlength ?
						$("<input>").attr({type: "text", id: id}).prop("maxlength", maxlength) :
						$("<input>").attr({type: "checkbox", id: id}),
					),
				)),
			),
		),
	);

	theDialogManager.make("stg",theUILang.ruTorrent_settings,
		$("<div>").attr({id: "stg_c"}).addClass("fxcaret cont").append(
			stgPanel,
			$("<div>").attr({id: "stg-pages"}).append(
				stgGlCont, stgDlCont, stgConCont, stgBtCont, stgFmtCont, stgAoCont,
				$("<div>").attr({id: "st_btns"}).addClass("buttons-list").append(
					$("<button>").text(theUILang.ok).attr({onclick: "theDialogManager.hide('stg');theWebUI.setSettings();return(false);"}),
					$("<button>").text(theUILang.Cancel).addClass("Cancel"),
				),
			),
		)[0].outerHTML,
	);
}

function hasThemeHint() {
	return 'theme-hint' in window.localStorage;
}

function setThemeHint(dark) {
	const theme = dark ? 'dark-theme' : 'light-theme';
	const previousTheme = window.localStorage['theme-hint'];
	if (theme !== previousTheme) {
		window.localStorage['theme-hint'] = theme;
		$(':root').removeClass(previousTheme).addClass(theme);
	}
}

if (hasThemeHint()) {
	$(':root').addClass('pre-theme-load').addClass(window.localStorage['theme-hint']);
}

function correctContent()
{
	if (hasThemeHint() && !thePlugins.isInstalled("theme")) {
		// Remove theme hint if theme plugin is not used
		$(':root').removeClass('pre-theme-load').removeClass(window.localStorage['theme-hint']);
		delete window.localStorage['theme-hint'];
	}

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
			for (let i = 0; i < 3; i++) {
				$("#st_ao_h div.row:first").remove();
			}
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
