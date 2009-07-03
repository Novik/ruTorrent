var stgHtml=
"<div class=\"lm\">"+
	"<ul>"+
		"<li class=\"first\"><a id=\"mnu_st_gl\" href=\"javascript:ch_mnu('st_gl');\" class=\"focus\">"+
			WUILang.General+
		"</a></li>"+
		"<li><a id=\"mnu_st_dl\" href=\"javascript:ch_mnu('st_dl');\">"+
			WUILang.Downloads+
		"</a></li>"+
		"<li><a id=\"mnu_st_con\" href=\"javascript:ch_mnu('st_con');\">"+
			WUILang.Connection+
		"</a></li>"+
		"<li><a id=\"mnu_st_bt\" href=\"javascript:ch_mnu('st_bt');\">"+
			WUILang.BitTorrent+
		"</a></li>"+
		"<li class=\"last\"><a id=\"mnu_st_ao\"  href=\"javascript:ch_mnu('st_ao');\">"+
			WUILang.Advanced+
		"</a></li>"+
	"</ul>"+
"</div>"+

"<div id=\"st_gl\" class=\"stg_con\">"+
	"<fieldset>"+
		"<legend>"+WUILang.User_Interface+"</legend>"+
		"<div class=\"op50l\">"+
			"<input type=\"checkbox\" id=\"webui.confirm_when_deleting\" checked=\"true\" />"+
			"<label for=\"webui.confirm_when_deleting\">"+WUILang.Confirm_del_torr+"</label>"+
		"</div>"+
		"<div class=\"op50l algnright\">"+
			WUILang.Update_GUI_every+":&nbsp;<input type=\"text\" id=\"webui.update_interval\" class=\"TextboxShort\" value=\"3000\" />"+
			WUILang.ms+
		"</div>"+
		"<div class=\"op50l\"><input type=\"checkbox\" id=\"webui.alternate_color\" />"+
			"<label for=\"webui.alternate_color\">"+WUILang.Alt_list_bckgnd+"</label>"+
		"</div>"+
		"<div class=\"op50l algnright\">"+
			WUILang.ReqTimeout+":&nbsp;<input type=\"text\" id=\"webui.reqtimeout\" class=\"TextboxShort\" value=\"5000\" />"+
			WUILang.ms+
		"</div>"+
		"<div class=\"op50l\"><input type=\"checkbox\" id=\"webui.show_cats\" checked=\"true\" />"+
			"<label for=\"webui.show_cats\">"+WUILang.Show_cat_start+"</label>"+
		"</div>"+
		"<div class=\"op50l algnright\"><input type=\"checkbox\" id=\"webui.show_dets\" checked=\"true\" />"+
			"<label for=\"webui.show_dets\">"+WUILang.Show_det_start+"</label>"+
		"</div>"+
		"<div class=\"op100l\"><input type=\"checkbox\" id=\"webui.needmessage\"/>"+
			"<label for=\"webui.needmessage\">"+WUILang.GetTrackerMessage+"</label>"+
		"</div>"+
		"<div class=\"op100l\">"+
			"<label for=\"webui.speed_display\">"+WUILang.Show_speed+":</label>&nbsp;"+
			"<select id=\"webui.speed_display\" />"+
				"<option value=\"0\" selected=\"selected\">"+WUILang.Don_t+"</option>"+
				"<option value=\"1\">"+WUILang.In_status_bar+"</option>"+
				"<option value=\"2\">"+WUILang.In_title_bar+"</option>"+
			"</select>"+
		"</div>"+
		"<div class=\"op100l\">"+
			"<label for=\"webui.minrows\">"+WUILang.Virt_row_thres+":</label>&nbsp;<input type=\"text\" id=\"webui.minrows\" class=\"TextboxVShort\" value=\"50\" />"+
		"</div>"+
		"<div class=\"op100l algnright\">"+
			"<input type=\"button\" value=\""+WUILang.Restor_def+"\" class=\"Button\" onclick=\"javascript:utWebUI.RestoreUI();return(false);\" />"+
		"</div>"+
	"</fieldset>"+
"</div>"+

"<div id=\"st_dl\" class=\"stg_con\">"+
	"<fieldset>"+
		"<legend>"+WUILang.Bandwidth_sett+"</legend>"+
		"<table>"+
			"<tr>"+
				"<td>"+WUILang.Number_ul_slots+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"max_uploads\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Number_Peers_min+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"min_peers\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Number_Peers_max+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"max_peers\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Number_Peers_For_Seeds_min+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"min_peers_seed\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Number_Peers_For_Seeds_max+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"max_peers_seed\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Tracker_Numwant+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"tracker_numwant\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
		"</table>"+
	"</fieldset>"+
	"<fieldset>"+
		"<legend>"+WUILang.Ather_sett+"</legend>"+
		"<table>"+
			"<tr>"+
				"<td><input id=\"check_hash\" type=\"checkbox\"/>"+
					"<label for=\"check_hash\">"+WUILang.Check_hash+"</label>"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Directory_For_Dl+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"directory\" class=\"Textbox str\" maxlength=\"100\" /></td>"+
			"</tr>"+
		"</table>"+
	"</fieldset>"+
"</div>"+

"<div id=\"st_con\" class=\"stg_con\">"+

	"<div>"+
		"<input id=\"port_open\" type=\"checkbox\" onchange=\"javascript:linked(this, 0, ['port_range', 'port_random']);\" />"+
		"<label for=\"port_open\">"+
			WUILang.Enable_port_open+
		"</label>"+
	"</div>"+

	"<fieldset>"+
		"<legend>"+WUILang.Listening_Port+"</legend>"+
		"<table>"+
			"<tr>"+
				"<td><label id=\"lbl_port_range\" for=\"port_range\" class=\"disabled\">"+WUILang.Port_f_incom_conns+":</label></td>"+
				"<td class=\"alr\">"+
					"<input type=\"text\" id=\"port_range\" class=\"TextboxShort\" class=\"disabled\" maxlength=\"13\" />"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td colspan=\"2\">"+
					"<input id=\"port_random\" type=\"checkbox\"  class=\"disabled\"/>"+
					"<label  id=\"lbl_port_random\" for=\"port_random\"  class=\"disabled\">"+WUILang.Rnd_port_torr_start+"</label>"+
				"</td>"+
			"</tr>"+
		"</table>"+
	"</fieldset>"+
	"<fieldset>"+
		"<legend>"+WUILang.Bandwidth_Limiting+"</legend>"+
		"<table>"+
			"<tr>"+
				"<td>"+WUILang.Global_max_upl+" ("+WUILang.kbs+"): [0: "+WUILang.unlimited+"]</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"upload_rate\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Glob_max_downl+" ("+WUILang.kbs+"): [0: "+WUILang.unlimited+"]</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"download_rate\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
		"</table>"+
	"</fieldset>"+
	"<fieldset>"+
		"<legend>"+WUILang.Ather_Limiting+"</legend>"+
		"<table>"+
			"<tr>"+
				"<td>"+WUILang.Number_ul_slots+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"max_uploads_global\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Number_dl_slots+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"max_downloads_global\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Glob_max_memory+" ("+WUILang.MB+"):</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"max_memory_usage\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Glob_max_files+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"max_open_files\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Glob_max_http+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"max_open_http\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Glob_max_sockets+":</td>"+
				"<td class=\"alr\"><input type=\"text\" id=\"max_open_sockets\" class=\"Textbox num\" maxlength=\"6\" /></td>"+
			"</tr>"+				
		"</table>"+
	"</fieldset>"+
"</div>"+

"<div id=\"st_bt\" class=\"stg_con\">"+
	"<fieldset>"+
		"<legend>"+WUILang.Add_bittor_featrs+"</legend>"+
		"<table>"+
			"<tr>"+
				"<td><input id=\"dht\" type=\"checkbox\"  onchange=\"javascript:linked(this, 0, ['dht_port']);\" />"+
					"<label for=\"dht\">"+WUILang.En_DHT_ntw+"</label>"+
				"</td>"+
				"<td><input id=\"peer_exchange\" type=\"checkbox\" />"+
					"<label for=\"peer_exchange\">"+WUILang.Peer_exch+"</label>"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td id=\"lbl_dht_port\" class=\"disabled\">"+WUILang.dht_port+":</td>"+
				"<td><input type=\"text\" id=\"dht_port\" class=\"Textbox num\" maxlength=\"6\" class=\"disabled\"/></td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+WUILang.Ip_report_track+":</td>"+
				"<td><input type=\"text\" id=\"ip\" class=\"Textbox str\" maxlength=\"50\" /></td>"+
			"</tr>"+
		"</table>"+
	"</fieldset>"+
"</div>"+

"<div id=\"st_ao\" class=\"stg_con\">"+
	"<fieldset>"+
		"<legend>"+WUILang.Advanced+"</legend>"+
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
						"<select id=\"preload_type\" />"+
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

"<div id=\"st_btns\">"+
	"<input type=\"button\" value=\"OK\" onclick=\"javascript:Hide('stg');utWebUI.setSettings();return(false);\" class=\"Button\" />"+
	"<input type=\"button\" value=\""+WUILang.Cancel+"\" onclick=\"javascript:Hide('stg');utWebUI.loadSettings();return(false);\" class=\"Button\" />"+
"</div>";