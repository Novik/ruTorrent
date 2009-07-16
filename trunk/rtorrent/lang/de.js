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
 * File Name: de.js
 * 	German language file.
 * 
 * File Author:
 * 		schnurlos (utorrent@gmx.at)
 */
 
 var WUILang =
 {
 //all used
 kbs				: "kB/s",
 
 
 //Settings window
 
 General			: "Allgemein",
 Downloads			: "Downloads",
 Connection			: "Verbindung",
 BitTorrent			: "BitTorrent",
 Queueing			: "Warteschlange",
 Scheduler			: "Zeitplan",
 Advanced			: "Erweitert",
 Disk_Cache			: "Disk-Cache",
 Enable_Web_Interface : "Erlaube den Web-Zugriff",
 Authent			: "Zugriffsdaten",
 Username			: "Benutzer",
 Password			: "Passwort",
 Enable_Guest_acc	: "Erlaube Gastzugriff mit Benutzernamen",
 Connectivity		: "Verbindungen",
 Alt_list_port		: "Alternativer Listening-Port (Standard: uTorrent-Port)",
 Rest_access		: "Beschr&auml;nke Zugriff auf folgende IPs (Trenne mehrere Eintr&auml;ge durch ',')",
 User_Interface		: "Benutzer Interface",
 Confirm_del_torr	: "L&ouml;schen von Torrents best&auml;tigen",
 Update_GUI_every	: "Anzeige aktualisieren in",
 ms					: "ms",
 Alt_list_bckgnd	: "Abwechselnder Listenhintergrund",
 Show_speed			: "Zeige Speed",
 Don_t				: "Nicht anzeigen",
 In_status_bar		: "In der Statusleiste",
 In_title_bar		: "In der Titelleiste",
 Show_cat_start		: "Zeige Gruppen beim Start",
 Virt_row_thres		: "Schwellenwert f. Reihen",
 Show_det_start		: "Zeige Details beim Start",
 Restor_def			: "Standardeinstellungen wiederherstellen",
 When_add_torrent	: "Beim Hinzuf&uuml;gen von Torrents",
 Dnt_start_down_auto: "Download nicht automatisch starten",
 Other_sett			: "Sonstige Einstellungen",
 Append_ut_incompl	: "An unfertige Dateien .!ut anh&auml;ngen",
 Prealloc_all_files	: "Speicher f&uuml;r Dateien vorbelegen",
 Prev_stnd_w_act_con: "Kein Standby solange noch Torrents aktiv sind",
 Listening_Port		: "Port f&uuml;r den Empfang",
 Port_f_incom_conns	: "Port f&uuml;r eingehende Verbindungen",
 Random_Port		: "Zuf&auml;lliger Port",
 Rnd_port_torr_start: "Bei jedem Start ein anderer Port",
 En_UPnP_mapp		: "Aktiviere UPnP Port Mapping",
 Add_torr_mustdie_f	: "Ausnahme f&uuml;r Windows Firewall (Windows XP SP2 oder sp&auml;ter)",
 Proxy_Server		: "Proxy Server",
 Type				: "Typ",
 none				: "(keiner)",
 Proxy				: "Proxy",
 Port				: "Port",
 Use_proxy_4_p2p_con: "Benutze Proxyserver f&uuml;r Peer zu Peer Verbindungen",
 Bandwidth_Limiting : "Bandbreiten Begrenzung",
 Global_max_upl		: "Max. gesamte Upload-Rate",
 unlimited			: "unbegrenzt",
 Alt_down_r_downl	: "Alternative Upload-Rate, wenn Du nur seedest",
 Glob_max_downl		: "Max. gesamte Download-Rate",
 Num_of_conn		: "Anzahl der Verbindungen",
 Glob_max_conn		: "Max. Gesamtanzahl der Verbindungen",
 Max_conn_peer_torr	: "Max. Anzahl von verbundenen Peers pro Torrent",
 Num_upl_slots		: "Anzahl der Uploadslots pro Torrent",
 Extra_ulslots		: "Nutze zus&auml;tzliche Uploadslots falls Upload < 90%",
 Add_bittor_featrs	: "Zus&auml;tzliche BitTorrent Funktionen",
 En_DHT_ntw			: "Verwende DHT-Netz",
 Ask_scrape			: "Frage beim Tracker nach Scrape-Info",
 En_DHT_new_torrs	: "Erlaube DHT f&uuml;r neue Torrents",
 Peer_exch			: "Peerlistentausch (PEX)",
 Ip_report_track	: "IP/Hostname an den Tracker melden",
 Protocol_enc		: "Protokoll Verschl&uuml;sselung",
 Outgoing			: "Ausgehend",
 Disabled			: "Deaktiviert",
 Enabled			: "Erlaubt",
 Forced				: "Erzwungen",
 All_inc_legacy_conn: "Erlaube eingehende Legacy Verbindungen",
 Queue_sett			: "Warteschlangen Einstellungen",
 Max_n_act_torrs	: "Max. Anzahl von aktiven Torrents (Up- oder Download)",
 Max_num_act_downl	: "Max. Anzahl von aktiven Downloads",
 Seed_while			: "Seede solange [Standard Werte]",
 Ratio_is			: "Rate ist",
 or_time_is			: " % oder geseedet f&uuml;r ",
 Ignore				: "(Ignorieren)",
 nin_min			: "90 Minuten",
 two_h				: "2 Stunden",
 three_h			: "3 Stunden",
 four_h				: "4 Stunden",
 five_h				: "5 Stunden",
 six_h				: "6 Stunden",
 seven_h			: "7 Stunden",
 eight_h			: "8 Stunden",
 nine_h				: "9 Stunden",
 ten_h				: "10 Stunden",
 twelve_h			: "12 Stunden",
 sixteen_h			: "16 Stunden",
 twenty_h			: "20 Stunden",
 tw_four_h			: "24 Stunden",
 thirty_h			: "30 Stunden",
 thirty_six_h		: "36 Stunden",
 forty_eight_h		: "48 Stunden",
 sixty_h			: "60 Stunden",
 sev_two_h			: "72 Stunden",
 nin_six_h			: "96 Stunden",
 Seed_prior			: "Seeds haben eine h&ouml;here Priorit&auml;t als Downloads",
 When_torr_reach	: "Wenn die gew&uuml;nschte Share-Rate erreicht ist",
 Limit_upl_rate		: "Upload-Rate begrenzen auf [0 h&auml;lt Torrent an]",
 En_shedul			: "Zeitplan verwenden",
 Shed_sett			: "Einstellungen f&uuml;r Zeitplan",
 Lim_upl_rate		: "Begrenzte Upload-Rate",
 Lim_dow_rate		: "Begrenzte Download-R.",
 Dis_DHT_when_t_off	: "DHT mit Zeitplan deaktivieren",
 Disc_cahe			: "Disk-Cache",
 Disc_cahe_bla_bla	: "Der Disk-Cache wird verwendet, um oft ben&ouml;tigte Daten im RAM zu halten, und dadurch die Lese- u. Schreibzugriffe auf der Festplatte zu reduzieren. rTorrent regelt das normalerweise automatisch, aber hier kannst du die Einstellungen &auml;ndern.",
 Overwr_d_cahe_au	: "&uuml;berschreibe die automatische Cache-Gr&ouml;sse und verwende",
 MB					: "MB",
 Red_mem_usage		: "Reduziere die Speicherbenutzung wenn der Cache nicht ben&ouml;tigt wird",
 Adv_cache_sett		: "Erweiterte Cache-Einstellungen",
 En_cach_disc		: "Verwende Caching bei Schreibzugriffen",
 Wr_out_ever_2_min	: "Schreibe unver&auml;nderte Bl&ouml;cke alle 2 Minuten",
 Wr_immed			: "Schreibe fertige Teile sofort",
 En_cah_disc_read	: "Verwende Caching bei Lesezugriffen",
 Turn_off_w_read	: "Schalte Caching aus, wenn die Upload-Geschwindigkeit niedrig ist",
 Rem_old_from_cahe	: "Entferne alte Bl&ouml;cke aus dem Cache",
 Increase_autom_cach: "Erh&ouml;he die Gr&ouml;sse des Cache automatisch bei &uuml;berlast",
 Advanced			: "Erweitert",
 Advanced_label		: "Experten-Einstellungen [ACHTUNG: &auml;nderungen auf eigenes Risiko!]",
 Cancel				: "Abbrechen",
 uTorrent_settings	: "rTorrent Einstellungen",
 
// Main window
 Doesnt_support		: "Diese <sup>BETA</sup> vom rTorrent-WebUI unterst&uuml;tzt deinen Browser nicht.",
 Name				: "Name",
 Status				: "Status",
 Size				: "Gr&ouml;sse",
 Done				: "Fertig",
 Downloaded			: "Geladen",
 Uploaded			: "Upgeloadet",
 Ratio				: "Rate",
 DL					: "DL",
 UL					: "UL",
 ETA				: "Fertig",
 Label				: "Gruppe",
 Peers				: "Peers",
 Seeds				: "Seeds",
 Avail				: "Verf.",
 Remaining			: "Restzeit",
 Priority			: "Priorit&auml;t",
 Download			: "Download",
 Upload				: "Upload",
 Not_torrent_file	: "Die Datei ist keine torrent-Datei.",
 Pausing			: "Angehalten",
 Seeding			: "Seeden",
 Downloading		: "Download",
 Checking			: "Check...",
 Error				: "Fehler",
 Queued				: "Warte",
 Finished			: "Fertig",
 Stopped			: "Gestoppt",
 Request_timed_out	: "Zeit&uuml;berschreitung - keine R&uuml;ckmeldung von rTorrent.",
 
 Force_Start		: "Erzwinge Start",
 Start				: "Start",
 Pause				: "Pause",
 Stop				: "Stop",
 Force_recheck		: "Erzwinge neuerliche &Uuml;berpr&uuml;fung",
 New_label			: "Neue Gruppe...",
 Remove_label		: "Gruppe l&ouml;schen",
 Labels				: "Gruppen",
 Remove				: "Entfernen",
 Delete_data		: "Daten l&ouml;schen",
 Remove_and			: "Entfernen und",
 Details			: "Details...",
 Properties			: "Eigenschaften...",
 of					: "von", 		//this two creates line 
 connected			: "verbundenen",	//  XX of YY connected
 High_priority		: "H&ouml;chste Priorit&auml;t",
 Normal_priority	: "Normale Priorit&auml;t",
 Low_priority		: "Niedrige Priorit&auml;t",
 Dont_download		: "Nicht Laden",
 Shure_restore_UI	: "Willst du wirklich die Standardeinstellungen wiederherstellen?",
 Reloading			: "Lade neu...",
 Name				: "Name",
 Date_				: "Datum",
 Files				: "Dateien",
 Logger				: "Logger",
 
 s					: "s",    // part of "KB/s"
 bytes				: "Bytes",
 KB					: "kB",
 MB					: "MB",
 GB					: "GB",
 TB					: "TB",
 PB					: "PB",
 
 // main WND s
 Loading			: "Lade uTorrent WebUI in deutsch...",
 Torrent_file		: "Torrent Datei",
 Torrent_URL		: "Torrent URL",
 Cookies			: "Cookies",
 RSS_Downloader		: "RSS Downloader",
 Torrent_properties	: "Torrent Eigenschaften",
 Trackers			: "Tracker",
 Max_down_rate		: "Max. Download-Rate",
 Max_upl_rate		: "Max. Upload-Rate",
 Bandwidth_sett		: "Bandbreiten Einstellungen",
 Number_ul_slots	: "Anzahl Upload-Slots",
 Override_default	: "Standard-Set &uuml;bersteuern",
 Initial_seed		: "Initial-Seeden",
 Enable_DHT			: "DHT verwenden",
 Peer_ex			: "Peerlistentausch",
 About				: "&uuml;ber rTorrent...",
 Enter_label_prom	: "Neue Gruppe f&uuml;r markierte Torrents eingeben",
 Remove_torrents	: "Torrent(s) l&ouml;schen",
 Rem_torrents_prompt: "M&ouml;chtest Du den/die markierte(n) Torrent(s) wirklich l&ouml;schen?",
 All				: "Alle",
 Active				: "Aktiv",
 Inactive			: "Inaktiv",
 No_label			: "Keine Gruppe",
 Transfer			: "Transfer",
 Time_el			: "Laufzeit",
 Remaining			: "Fehlt noch",
 Share_ratio		: "Share-Rate",
 Down_speed			: "Download-Rate",
 Down_limit			: "Down-Limit",
 Ul_speed			: "Upload-Rate",
 Ul_limit			: "Up-Limit",
 Wasted				: "Unbrauchbar",
 Tracker			: "Tracker",
 Track_URL			: "Tracker URL",
 Track_status		: "Tracker Status",
 Update_in			: "N&auml;chstes Update",
 DHT_status			: "DHT Status",
 Save_as			: "Speichern unter",
 Tot_size			: "Gesamtgr&ouml;sse",
 Created_on			: "Erstellt am",
 Comment			: "Kommentar",
 
 
 //buttons
 add_button			: "Datei dazu",
 add_url			: "URL dazu",
 ok1				: "   OK   ",
 ok2				: "   OK   ",
 ok3				: "   OK   ",
 Cancel1			: " Abbrechen ",
 Cancel2			: " Abbrechen ",
 no1				: "  Nein ",
 
 mnu_add			: "Torrent dazu...",
 mnu_remove			: "Entfernen",
 mnu_start			: "Start",
 mnu_pause			: "Pause",
 mnu_stop			: "Stop",
 mnu_rss			: "RSS Downloader",
 mnu_settings		: "Einstellungen",
 mnu_search			: "Suche...",
 mnu_lang			: "Sprache auswaehlen",
 
 //Other variables added by spide
 torrent_add			: "Add Torrent", /*Caption of torrent add box*/
 time_w				: "w ", /*for x weeks*/
 time_d				: "t ", /*for x days remaining*/
 time_h				: "h ", /*for x hours remaining*/
 time_m				: "m ", /*for x minutes remaining*/
 time_s				: "s ", /*for x seconds remaining*/

 //Novik's addition
 Base_directory    		: "Directory",
 Number_Peers_min		: "Minimum number of peers",
 Number_Peers_max		: "Maximum number of peers",
 Tracker_Numwant		: "Wished number of peers",
 Number_Peers_For_Seeds_min	: "Minimum number of seeds",
 Number_Peers_For_Seeds_max	: "Maximum number of seeds",
 Enable_port_open		: "Open listening port",
 dht_port			: "UDP port to use for DHT",
 Ather_Limiting			: "Other limitations",
 Number_dl_slots		: "Number of download slots",
 Glob_max_memory		: "Maximum memory usage",
 Glob_max_files			: "Maximum number of open files",
 Glob_max_http			: "Maximum number of open http connections",
 Glob_max_sockets		: "Maximum number of open sockets",
 Ather_sett			: "Other",
 Directory_For_Dl		: "Default directory for downloads",
 Check_hash			: "Check hash after download",
 Hash				: "Hash",
 IsOpen				: "Open",
 DisableTracker			: "Disable",
 EnableTracker			: "Enable",
 ClientVersion			: "Client",
 Flags				: "Flags",
 ReqTimeout			: "Request timeout",
 GetTrackerMessage		: "Receive messages from tracker",
 Help				: "Help",
 PHPDoesnt_enabled		: "Your web server does not support PHP. Correct this and try again.",
 Speed				: "Speed",
 Dont_add_tname			: "Don't add torrent's name to directory", 
 Free_Disk_Space		: "Free disk space",
 badXMLRPCVersion		: "rTorrent is compiled with incorrect version of xmlrpc-c library, without i8 support. Version must be >= 1.11. Some functionality will be unavailable.",
 badLinkTorTorrent		: "Bad link to rTorrent. Check if it is really running. Check $scgi_port and $scgi_host settings in config.php and scgi_port in rTorrent configuration file.",
 badUploadsPath			: "Web server can't access torrents directory for read/write/execute. You can't add torrents through ruTorrent.",
 badSettingsPath		: "Web server can't access settings directory for read/write/execute. ruTorrent can't save own settings.",
 mnu_help			: "About...",
 badUploadsPath2		: "rTorrent's user can't access torrents directory for read/execute. You can't add torrents through ruTorrent.",
 View				: "View",
 AsList				: "as list",
 AsTree				: "as tree",
 Group				: "Group",
 SuperSeed			: "Super-seed",
 badTestPath			: "rTorrent's user can't access file ./test.sh for read/execute. ruTorrent will not work.",
 badSettingsPath2		: "rTorrent's user can't access settings directory for read/write/execute. ruTorrent will not work.",
 scrapeDownloaded		: "Downloaded",
 badSessionPath			: "Web server can't access rTorrent's session directory for read. ruTorrent will not work.",
 Total				: "Total"
 };
