﻿/*
 * WebUI - The WEB interface for uTorrent - http://www.utorrent.com
 * NO COPYCATS of language update
 * 
 * == BEGIN LICENSE ==
 * 
 * Licensed under the terms of any of the following licenses at your
 * choice:
 * 
 *  - GNU General Public License Version 2 or later (the "GPL")
 *    http://www.gnu.org/licenses/gpl.html
 * 
 *  - GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 *    http://www.gnu.org/licenses/lgpl.html
 * 
 *  - Mozilla Public License Version 1.1 or later (the "MPL")
 *    http://www.mozilla.org/MPL/MPL-1.1.html
 * 
 * == END LICENSE ==
 * 
 * File Name: sr.js
 * 	Serbian language file.
 * 
 * File Author:
 * 		Zoltan Csala (zcsala021 at gmail dot com)
 */
 
 var theUILang =
 {
 //all used
 kbs				: "kB/s",
 
 
 //Settings window
 
 General			: "Опште",
 Downloads			: "Низтовари",
 Connection			: "Веза",
 BitTorrent			: "BitTorrent",
 Queueing			: "Queueing",
 Scheduler			: "Scheduler",
 Advanced			: "Напредно",
 Disk_Cache			: "Disk Cache",
 Enable_Web_Interface : "Омогућити мрежни интерфејс",
 Authent			: "Authentication",
 Username			: "Корисничко име",
 Password			: "Лозинка",
 Enable_Guest_acc	: "Enable Guest account with username",
 Connectivity		: "Повезаност",
 Alt_list_port		: "Alternative listening port (default is the bittorrent port)",
 Rest_access		: "Restrict access to the following IPs (Separate multiple entries with ,)",
 User_Interface		: "Кориснички интерфејс",
 Confirm_del_torr	: "Confirm when deleting torrents",
 Update_GUI_every	: "Освежи графички интерфејс сваких",
 ms					: "милисекунди",
 Alt_list_bckgnd	: "Наизменично бојење редова у листи",
 Show_speed			: "Прикажи брзину",
 Don_t				: "Don't",
 In_status_bar		: "In status bar",
 In_title_bar		: "In title bar",
 Show_cat_start		: "Покажи категорије на почетку",
 Show_det_start		: "Покажи детаље на почетку",
 Restor_def			: "Врати подразумеване вредности",
 When_add_torrent	: "Приликом додавања торената",
 Dnt_start_down_auto: "Don't start the download automatically",
 Other_sett			: "Друга подешавања",
 Append_ut_incompl	: "Append .!ut to incomplete files",
 Prealloc_all_files	: "Pre-allocate all files",
 Prev_stnd_w_act_con: "Prevent stand-by if there are active torrents",
 Listening_Port		: "Listening Port",
 Port_f_incom_conns	: "Port used for incoming connections",
 Random_Port		: "Случајни порт",
 Rnd_port_torr_start: "Randomize port each time rTorrent starts",
 En_UPnP_mapp		: "Enable UPnP port mapping",
 Add_torr_mustdie_f	: "Add rTorrent to Windows Firewall exceptions (Windows XP SP2 or later only)",
 Proxy_Server		: "Proxy Server",
 Type				: "Type",
 none				: "(none)",
 Proxy				: "Proxy",
 Port				: "Порт",
 Use_proxy_4_p2p_con: "Use proxy server for peer-to-peer connections",
 Bandwidth_Limiting : "Bandwidth Limiting",
 Global_max_upl		: "Global maximum upload rate",
 unlimited			: "неограничено",
 Alt_down_r_downl	: "Alternate upload rate when not downloading",
 Glob_max_downl		: "Global maximum download rate",
 Num_of_conn		: "Број веза",
 Glob_max_conn		: "Global maximum number of connections",
 Max_conn_peer_torr	: "Maximum number of connected peers per torrent",
 Num_upl_slots		: "Number of upload slots per torrent",
 Extra_ulslots		: "Use additional upload slots if upload speed < 90%",
 Add_bittor_featrs	: "Additional BitTorrent Features",
 En_DHT_ntw			: "Enable DHT Network",
 Ask_scrape			: "Ask tracker for scrape information",
 En_DHT_new_torrs	: "Enable DHT for new torrents",
 Peer_exch			: "Peer Exchange",
 Ip_report_track	: "IP/Hostname to report to tracker",
 Protocol_enc		: "Protocol Encryption",
 Outgoing			: "Outgoing",
 Disabled			: "Disabled",
 Enabled			: "Enabled",
 Forced				: "Forced",
 All_inc_legacy_conn: "Allow incoming legacy connections",
 Queue_sett			: "Queue Settings",
 Max_n_act_torrs	: "Maximum number of active torrents (upload or download)",
 Max_num_act_downl	: "Maximum number of active downloads",
 Seed_while			: "Seed While [Default Values]",
 Ratio_is			: "Ratio is",
 or_time_is			: " % or seeding time is ",
 Ignore				: "(Ignore)",
 nin_min			: "90 minutes",
 two_h				: "2 сата",
 three_h			: "3 сата",
 four_h				: "4 сата",
 five_h				: "5 сати",
 six_h				: "6 сати",
 seven_h			: "7 сати",
 eight_h			: "8 сати",
 nine_h				: "9 сати",
 ten_h				: "10 сати",
 twelve_h			: "12 сати",
 sixteen_h			: "16 сати",
 twenty_h			: "20 сати",
 tw_four_h			: "24 сата",
 thirty_h			: "30 сати",
 thirty_six_h		: "36 сати",
 forty_eight_h		: "48 сати",
 sixty_h			: "60 сати",
 sev_two_h			: "72 сата",
 nin_six_h			: "96 сати",
 Seed_prior			: "Seeding tasks have higher priority than downloading tasks",
 When_torr_reach	: "When Torrent has reached the seeding goal",
 Limit_upl_rate		: "Limit the upload rate to [use 0 to stop torrent]",
 En_shedul			: "Enable Scheduler",
 Shed_sett			: "Scheduler Settings",
 Lim_upl_rate		: "Limited upload rate",
 Lim_dow_rate		: "Limited download rate",
 Dis_DHT_when_t_off	: "Disable DHT when turning off",
 Disc_cahe			: "Disk Cache",
 Disc_cahe_bla_bla	: "The disk cache is used to keep frequently accessed data in memory to reduce the number of reads and writes to the hard drive. rTorrent normally manages the cache automatically, but you may change its behaviour by modifying these settings.",
 Overwr_d_cahe_au	: "Overwrite automatic Cache Size and specify the size manually",
 MB					: "MB",
 Red_mem_usage		: "Reduce memory usage when cache is not needed",
 Adv_cache_sett		: "Advanced Cache Settings",
 En_cach_disc		: "Enable Caching of Disk Writes",
 Wr_out_ever_2_min	: "Write out untouched blocks every 2 minutes",
 Wr_immed			: "Write out finished pieces immediately",
 En_cah_disc_read	: "Enable Caching of Disk Reads",
 Turn_off_w_read	: "Turn off read caching if the upload speed is slow",
 Rem_old_from_cahe	: "Remove old blocks from cache",
 Increase_autom_cach: "Increase automatic cache size when cache thrashing",
 Advanced			: "Advanced",
 Advanced_label		: "Advanced Options [WARNING: Do not modify!]",
 Cancel				: "Cancel",
 uTorrent_settings	: "rTorrent Settings",
 
// Main window
 Doesnt_support		: "The ruTorrent does not support your browser.",
 Name				: "Име",
 Status				: "Статус",
 Size				: "Величина",
 Done				: "Готово",
 Downloaded			: "Низтоварено",
 Uploaded			: "Узтоварено",
 Ratio				: "Однос",
 DL					: "DL",
 UL					: "UL",
 ETA				: "ETA",
 Label				: "Ознака",
 Peers				: "Парњаци",
 Seeds				: "Сејачи",
 Avail				: "На расп.",
 Remaining			: "Преостало",
 Priority			: "Приоритет",
 Download			: "Низтовар",
 Upload				: "Узтовар",
 Not_torrent_file	: "Датотека мора бити torrent датотека.",
 Pausing			: "Заустављено",
 Seeding			: "Сејање",
 Downloading		: "Низтоварање",
 Checking			: "Проверавање",
 Error				: "Грешка",
 Queued				: "Стављено у ред",
 Finished			: "Завршени",
 Stopped			: "Заустављено",
 Request_timed_out	: "The request to rTorrent has timed out.",
 
 Force_Start		: "Присилни почетак",
 Start				: "Почни",
 Pause				: "Паузирај",
 Stop				: "Заустави",
 Force_recheck		: "Force Re-check",
 New_label			: "Нова ознака ...",
 Remove_label		: "Уклони ознаку",
 Labels				: "Ознаке",
 Remove				: "Уклони",
 Delete_data		: "Уклони податке",
 Remove_and			: "Remove And",
 Details			: "Детаљи ...",
 Properties			: "Својства ...",
 of					: "од", 		//this two creates line 
 connected			: "повезано",	//  XX of YY connected
 High_priority		: "Висок",
 Normal_priority	: "Нормалан",
 Low_priority		: "Низак",
 Dont_download		: "Немој да низтовараш",
 Shure_restore_UI	: "Are you sure that you want to restore the user interface?",
 Reloading			: "Поновно учитавање...",
 Name				: "Име",
 Date_				: "Датум",
 Files				: "Датотеке",
 Logger				: "Дневник",
 
 s					: "с",    // part of "KB/s"
 bytes				: "бајтова",
 KB					: "КБ",
 MB					: "МБ",
 GB					: "ГБ",
 TB					: "ТБ",
 PB					: "ПБ",
 
 // main WND s
 Loading			: "Учитавање ...",
 Torrent_file		: "Торент датотека",
 Torrent_URL		: "Torrent URL",
 Cookies			: "Колачићи",
 RSS_Downloader		: "RSS Downloader",
 Torrent_properties	: "Својства торента",
 Trackers			: "Трекери",
 Max_down_rate		: "Maximum download rate",
 Max_upl_rate		: "Maximum upload rate",
 Bandwidth_sett		: "Bandwidth Settings",
 Number_ul_slots	: "Number of upload slots",
 Override_default	: "Override default settings",
 Initial_seed		: "Почетно сејање",
 Enable_DHT			: "Омогући DHT",
 Peer_ex			: "Парњачка размена",
 About				: "О програму",
 Enter_label_prom	: "Enter the new label for the selected torrents",
 Remove_torrents	: "Remove Torrent(s)",
 Rem_torrents_prompt: "Do you really want to remove the selected torrent(s)?",
 All				: "Све",
 Active				: "Активни",
 Inactive			: "Неактивни",
 No_label			: "Без ознаке",
 Transfer			: "Пренос",
 Time_el			: "Протекло време",
 Remaining			: "Преостало",
 Share_ratio		: "Однос дељења",
 Down_speed			: "Брзина низтовара",
 Down_limit			: "Down Limit",
 Ul_speed			: "Брзина узтовара",
 Ul_limit			: "Up Limit",
 Wasted				: "Страћено",
 Tracker			: "Трекер",
 Track_URL			: "URL трекера",
 Track_status		: "Статус трекера",
 Update_in			: "Ажурирање после",
 DHT_status			: "DHT Status",
 Save_as			: "Снима се као",
 Tot_size			: "Укупна величина",
 Created_on			: "Направљено",
 Comment			: "Коментар",
 
 
 //buttons
 add_button			: "Додај датотеку",
 add_url			: "Додај URL",
 ok				    : " У реду ",
 Cancel				: "Одустани",
 no				    : "   Не   ",
 
 mnu_add			: "Додај торент ",
 mnu_remove			: "Уклони",
 mnu_start			: "Покрени",
 mnu_pause			: "Паузирај",
 mnu_stop			: "Заустави",
 mnu_rss			: "RSS Downloader",
 mnu_settings		: "Подешавања ",
 mnu_search			: "Претрага ",
 mnu_lang			: "Језик",
 
 //Other variables added by spide
 torrent_add		: "Додај торент", /*Caption of torrent add box*/
 time_w				: "н ", /*for x weeks*/
 time_d				: "д ", /*for x days remaining*/
 time_h				: "ч ", /*for x hours remaining*/
 time_m				: "м ", /*for x minutes remaining*/
 time_s				: "с ", /*for x seconds remaining*/

 //Novik's addition
 Base_directory    		: "Директоријум",
 Number_Peers_min		: "Најмањи број парњака",
 Number_Peers_max		: "Највећи број парњака",
 Tracker_Numwant		: "Жељени број парњака",
 Number_Peers_For_Seeds_min	: "Minimum number of seeds",
 Number_Peers_For_Seeds_max	: "Maximum number of seeds",
 Enable_port_open		: "Open listening port",
 dht_port			: "UDP port to use for DHT",
 Ather_Limiting			: "Друга ограничења",
 Number_dl_slots		: "Number of download slots",
 Glob_max_memory		: "Максимална потрошња меморије",
 Glob_max_files			: "Максималан број отворених датотека",
 Glob_max_http			: "Максималан број отворених НТТР веза",
 Glob_max_sockets		: "Maximum number of open sockets",
 Ather_sett				: "Друго",
 Directory_For_Dl		: "Подразумевани директоријум за низтоваре",
 Check_hash				: "Провери хеш после низтовара",
 Hash					: "Хеш",
 IsOpen					: "Open",
 DisableTracker			: "Disable",
 EnableTracker			: "Enable",
 ClientVersion			: "Клијент",
 Flags					: "Ознаке",
 ReqTimeout				: "Request timeout",
 GetTrackerMessage		: "Receive messages from tracker",
 Help					: "Помоћ",
 PHPDoesnt_enabled		: "Your Web-server does not support PHP. Correct this and try again.",
 Speed					: "Брзина",
 Dont_add_tname			: "Don't add torrent's name to path", 
 Free_Disk_Space		: "Слободан простор на диску",
 badXMLRPCVersion		: "rTorrent is compiled with incorrect version of xmlrpc-c library, without i8 support. Version must be >= 1.11. Some functionality will be unavailable.",
 badLinkTorTorrent		: "Bad link to rTorrent. Check if it is really running. Check $scgi_port and $scgi_host settings in config.php and scgi_port in rTorrent configuration file.",
 badUploadsPath			: "Web-server can't access torrents directory for read/write/execute. You can't add torrents through ruTorrent.",
 badSettingsPath		: "Web-server can't access settings directory for read/write/execute. ruTorrent can't save own settings.",
 mnu_help				: "О програму",
 badUploadsPath2		: "rTorrent's user can't access torrents directory for read/execute. You can't add torrents through ruTorrent.",
 View					: "View",
 AsList					: "као листу",
 AsTree					: "као стабло",
 Group					: "Група",
 SuperSeed				: "Супер-сејање",
 badTestPath			: "rTorrent's user can't access file ./test.sh for read/execute. ruTorrent will not work.",
 badSettingsPath2		: "rTorrent's user can't access settings directory for read/write/execute. ruTorrent will not work.",
 scrapeDownloaded		: "Downloaded",
 Total					: "Укупно",
 PCRENotFound			: "PHP module PCRE is not installed. ruTorrent will not work.",
 addTorrentSuccess		: "torrent was successfully passed to rTorrent.",
 addTorrentFailed		: "Error: torrent wasn't passed to rTorrent.",
 pnlState				: "State",
 newLabel				: "New Label",
 enterLabel				: "Enter Label",
 UIEffects				: "Show effects for UI elements",
 Plugins				: "Додаци",
 plgName				: "Име",
 plgStatus				: "Статус",
 plgLoaded				: "Учитан",
 plgDisabled			: "Disabled",
 plgVersion				: "Верзија",
 plgAuthor				: "Аутор",
 plgDescr				: "Опис",
 mnu_go					: "Go",
 pluginCantStart		: "plugin can't start for unknown reason.",
 doFastResume			: "Брзи наставак",
 innerSearch			: "Local torrents",
 removeTeg				: "Remove tag",
 errMustBeInSomeHost	: "ruTorrent and rTorrent must be installed on the same host. Plugin will not work.",
 warnMustBeInSomeHost	: "ruTorrent and rTorrent must be installed on the same host. Some functionality will be unavailable.",
 plgShutdown			: "Shutdown",
 limit					: "Ограничење",
 speedList				: "Speed popup list (comma-separated)",
 ClearButton			: "Clear",
 dontShowTimeouts		: "Ignore message about timeouts",
 fullTableRender		: "Full render of large tables",
 showScrollTables		: "Прикажи садржај табеле приликом померања",
 idNotFound			: "rTorrent's user can't access 'id' program. Some functionality will be unavailable.",
 gzipNotFound			: "Web-server can't access 'gzip' program. ruTorrent will not work.",
 cantObtainUser			: "ruTorrent can't detect the uid or rtorrent user. Some functionality will be unavailable.",
 retryOnErrorTitle		: "If rtorrent is not available try again after",
 retryOnErrorList		: { 0: "Don't try again", 30: "30 sec", 60: "1 min", 120: "2 min", 300: "5 min", 900: "15 min" },
 statNotFound			: "rTorrent's user can't access 'stat' program. Some functionality will be unavailable.",
 statNotFoundW			: "Web-server user can't access 'stat' program. Some functionality will be unavailable.",
 badrTorrentVersion		: "This version of rTorrent doesn't support this plugin. Plugin will not work. rTorrent version must be >=",
 badPHPVersion                  : "This version of PHP doesn't support this plugin. Plugin will not work. PHP version must be >=",
 rTorrentExternalNotFoundError	: "Plugin will not work. rTorrent's user can't access external program",
 rTorrentExternalNotFoundWarning: "Some functionality will be unavailable. rTorrent's user can't access external program",
 webExternalNotFoundError	: "Plugin will not work. Web-server user can't access external program",
 webExternalNotFoundWarning	: "Some functionality will be unavailable. Web-server user can't access external program",
 rTorrentBadScriptPath		: "Plugin will not work. rTorrent's user can't access file for read/execute",
 rTorrentBadPHPScriptPath	: "Plugin will not work. rTorrent's user can't access file for read",
 dependenceError		: "Plugin will not work. It require existence of plugin(s)",
 peerAdd			: "Add peer...",
 peerBan			: "Ban",
 peerKick			: "Kick",
 peerSnub			: "Snub",
 peerDetails			: "Details",
 peerUnsnub			: "Unsnub",
 peerAddLabel			: "Enter IP/Hostname[:port]",
 noTorrentList			: "Torrent list not yet available, connection to rtorrent not established."
 };
