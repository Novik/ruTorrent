/*
 * The options window controller
 */

const theOptionsWindow = {
	handlerTypes: {
		// bootstrap list group events
		// ref: https://getbootstrap.com/docs/5.3/components/list-group/#events
		beforeShow: "show.bs.tab",
		afterShow: "shown.bs.tab",
		beforeHide: "hide.bs.tab",
		afterHide: "hidden.bs.tab",
	},
	currentPage: {get: function() {return $("#stg_c .tab-pane.active").attr("id");}},
	init: function() {
		// initialize options window with default option pages
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
					...[
						["webui.update_interval", theUILang.Update_GUI_every + ": ", theUILang.ms, 3000],
						["webui.reqtimeout", theUILang.ReqTimeout + ": ", theUILang.ms, 5000],
					].map(([id, prefix, suffix, value]) => [
						$("<div>").addClass("col-md-6").append(
							$('<label>').attr({for: id}).text(prefix),
						),
						$("<div>").addClass("col-9 col-md-5").append(
							$('<input>').attr({type: "number", id: id, value: value, min: 0}).addClass("flex-grow-1"),
						),
						$("<div>").addClass("col-3 col-md-1").append(
							$('<span>').text(suffix),
						),
					]),
					$("<div>").addClass("col-md-6").append(
						$("<label>").attr({for: "webui.speedgraph.max_seconds"}).text(theUILang.speedGraphDuration + ": "),
					),
					$("<div>").addClass("col-md-6").append(
						$("<select>").attr({id: "webui.speedgraph.max_seconds"}).addClass("flex-grow-1").append(
							...Object.entries(theUILang.speedGraphDurationOptions).map(([value, text]) => $("<option>").attr({value: value}).text(text),
						)),
					),
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
						$("<input>")
							.attr({type: "checkbox", id: "port_open"})
							.on("click", (ev) => linked(ev.target, 0, ['port_range', 'port_random'])),
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
						$("<input>")
							.attr({type: "checkbox", id: "dht"})
							.on("change", (ev) => linked(ev.target, 0, ['dht_port'])),
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
						$("<input>").attr({type:"text", id:"ip"}),
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
				$("<div>").attr({id:"decimalDigitEdit"}).addClass("row").append(
					$("<div>").addClass("col-12 overflow-x-auto").append(
						$("<table>").append(
							$("<thead>").append(
								$("<tr>").append(
									...[
										"", "Default", "KB", "MB", "GB", "TB", "PB",
									].map((unit) => $("<th>").text(theUILang[unit] ?? "")),
								),
							),
							$("<tbody>").append(
								...Object.entries({
									catlist: theUILang.CatListSizeDecimalPlaces,
									table: theUILang.TableSizeDecimalPlaces,
									details: theUILang.DetailsSizeDecimalPlaces,
									other: theUILang.OtherSizeDecimalPlaces,
								}).map(([context, name]) => $('<tr>').append(
									$("<td>").text(name + ": "),
									...["default", "kb", "mb", "gb", "tb", "pb"].map(unit => $("<td>").append(
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
					),
				),
			),
		);
		const stgAoCont = $("<div>").attr({id: "st_ao"}).addClass("stg_con").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Advanced),
				$("<div>").attr({id: "st_ao_h"}).addClass("row").append(
					...[
						["hash_interval", 20], ["hash_max_tries", 5], ["hash_read_ahead", 20],
						["http_cacert", 100], ["http_capath", 100],
						["max_downloads_div", 5], ["max_uploads_div", 5], ["max_file_size", 20],
					].flatMap(([id, maxlength]) => [
						$("<div>").addClass("col-md-6").append(
							$("<label>").attr({for: id}).text(id),
						), 
						$("<div>").addClass("col-md-6").append(
							$("<input>").attr({type: "text", id: id}).prop("maxlength", maxlength),
						),
					]),
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
					...[
						["preload_min_size", 20], ["preload_required_rate", 20],
						["receive_buffer_size", 20], ["send_buffer_size", 20],
						["safe_sync", ], ["timeout_safe_sync", 20], ["timeout_sync", 20], ["scgi_dont_route", ],
						["session", 100], ["session_lock", ], ["session_on_completion",],
						["split_file_size", 20], ["split_suffix", 100], ["use_udp_trackers", ],
						["http_proxy", 100], ["proxy_address", 100], ["bind", 100],
					].flatMap(([id, maxlength]) => {
						if (maxlength) {
							return [
								$("<div>").addClass("col-md-6").append(
									$("<label>").attr({for: id}).text(id),
								),
								$("<div>").addClass("col-md-6").append(
									$("<input>").attr({type: "text", id: id}).prop("maxlength", maxlength),
								),
							];
						} else {
							return  $("<div>").addClass("col-12").append(
								$("<input>").attr({type: "checkbox", id}),
								$("<label>").attr({for: id}).text(id),
							);
						}
					}),
				),
			),
		);
		const stgDevCont = $("<div>").attr({id: "st_dev"}).addClass("stg_con").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Developers),
				$("<div>").attr({id: "st_dev_h"}).addClass("row").append(
					...[
						['webui.side_panel_min_width', theUILang.sidePanelMinWidth, theUILang.Pixel],
						['webui.side_panel_max_width_percent', theUILang.sidePanelMaxWidthPercent, "%"],
						['webui.list_table_min_height', theUILang.listTableMinHeight, theUILang.Pixel],
					].map(([id, label, unit]) => [
						$("<div>").addClass("col-12 col-md-6").append(
							$("<label>").attr({for:id}).text(label),
						),
						$("<div>").addClass("col-10 col-md-5").append(
							$("<input>").attr({type:"text", id}),
						),
						$("<div>").addClass("col-2 col-md-1").append(
							$("<span>").text(unit),
						),
					]),
				),
			),
		);
		[
			[stgGlCont, theUILang.General],
			[stgDlCont, theUILang.Downloads],
			[stgConCont, theUILang.Connection],
			[stgBtCont, theUILang.BitTorrent],
			[stgFmtCont, theUILang.Format],
			[stgAoCont, theUILang.Advanced],
			[stgDevCont, theUILang.Developers],
		].forEach(([dlg, name]) => this.attachPage(dlg[0], name));
		this.goToPage("st_gl");
	},
	attachPage: function(dlg, name) {
		if ($("#stg_c").find(dlg.id).length) return;
		// append to navigation panel
		$("#stg_c").find(".list-group").append(
			$("<a>")
				.attr({id: `mnu_${dlg.id}`, href: `#${dlg.id}`, "data-bs-toggle":"list", role:"tab", "aria-controls":dlg.id})
				.addClass("list-group-item list-group-item-action")
				.text(name),
		);
		// append to settings page
		$("#stg_c").find("#st_btns").before(
			$(dlg).attr({role:"tabpanel", "aria-labelledby":`mnu_${dlg.id}`}).addClass("stg_con tab-pane fade"),
		);
	},
	removePage: function(id) {
		$("#stg_c").find(`#${id}, #mnu_${id}`).remove();
	},
	goToPage: function(id) {
		bootstrap.Tab.getOrCreateInstance(`#mnu_${id}`).show();
	},
	addHandler: function(id, type, callback) {
		const event = this.handlerTypes[type];
		if (event) {
			document.getElementById(id).addEventListener(event, callback);
		}
	},
	removeHandler: function(id, type, callback) {
		const event = this.handlerTypes[type];
		if (event) {
			document.getElementById(id).removeEventListener(event, callback);
		}
	},
}
