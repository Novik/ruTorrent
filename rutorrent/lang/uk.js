/*
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
 * File Name: ua.js
 * 	Ukrainian language file.
 * 
 * File Author:
 * 		Artem Lopata (mod-s@yandex.ru)
 */
 
 var theUILang =
 {
 //all used
 kbs				: "кБ/с",
 
 
 //Settings window
 
 General			: "Загальне",
 Downloads			: "Завантаження",
 Connection			: "З'єднання",
 BitTorrent			: "BitTorrent",
 Queueing			: "Черга",
 Scheduler			: "Розклад",
 Advanced			: "Додатково",
 User_Interface		: "Інтерфейс користувача",
 Confirm_del_torr	: "Підтв. видалення торента",
 Update_GUI_every	: "Оновл. GUI кожні",
 ms					: "мс",
 Alt_list_bckgnd	: "Альтернативний колір списку",
 Show_cat_start		: "Показувати категорії при старті",
 Show_det_start		: "Показувати деталі при старті",
 Dnt_start_down_auto: "Не починати завантаження автоматично",
 Other_sett			: "Інші налаштування",
 Listening_Port		: "Порт",
 Port_f_incom_conns	: "Порт для вхідних з'єднань",
 Rnd_port_torr_start: "Випадковий порт при кожному відкритті rTorrent",
 Type				: "Тип",
 Bandwidth_Limiting : "Глобальні обмеження швидкості",
 Global_max_upl		: "Макс. швидкість віддачі",
 unlimited			: "необмеж.",
 Glob_max_downl		: "Макс. швидкість завантажень",
 Add_bittor_featrs	: "Додаткові функції BitTorrent",
 En_DHT_ntw			: "Увімкнути мережу DHT",
 Peer_exch			: "Обмін пірами",
 Ip_report_track	: "IP/Hostname для передачі трекерові",
 Disabled			: "Відключено",
 Enabled			: "Увімкнуто",
 Advanced			: "Додатково",
 Cancel				: "Відміна",
 uTorrent_settings	: "Налаштування rTorrent",
 
// Main window
 Doesnt_support		: "ruTorrent не піддтримує Ваш браузер.",
 Name				: "Ім'я",
 Status				: "Статус",
 Size				: "Розмір",
 Done				: "Зроблено",
 Downloaded			: "Завантажено",
 Uploaded			: "Віддано",
 Ratio				: "Ратіо",
 DL					: "Завантаження",
 UL					: "Віддача",
 ETA				: "Час",
 Label				: "Мітка",
 Peers				: "Піри",
 Seeds				: "Сіди",
 Avail				: "Дост.",
 Remaining			: "Залишилося",
 Priority			: "Приоритет",
 Download			: "Завантаження",
 Upload				: "Віддача",
 Not_torrent_file	: "Файл повинен бути файлом .torrent.",
 Pausing			: "Пауза",
 Seeding			: "Роздача",
 Downloading		: "Закачка",
 Checking			: "Перевірка",
 Error				: "Помилка",
 Queued				: "У черзі",
 Finished			: "Закінчено",
 Stopped			: "Зупинено",
 Request_timed_out	: "rTorrent не відповідає на запит",
 
 Start				: "Старт",
 Pause				: "Пауза",
 Stop				: "Стоп",
 Force_recheck		: "Швидк. перевірка",
 New_label			: "Нова мітка...",
 Remove_label		: "Видалити мітку",
 Labels				: "Мітки",
 Remove				: "Видалити",
 Delete_data		: "Видалити дані",
 Remove_and			: "Видалити та",
 Details			: "Деталі...",
 Properties			: "Властивості...",
 of					: "з", 		//this two creates line 
 connected			: "з'єднані",	//  XX of YY connected
 High_priority		: "Високий приоритет",
 Normal_priority	: "Нормальний приоритет",
 Low_priority		: "Низький приоритет",
 Dont_download		: "Не завантажувати",
 Files				: "Файли",
 Logger				: "Лог",
 
 s					: "с",    // part of "KB/s"
 bytes				: "байт",
 KB					: "KБ",
 MB					: "MБ",
 GB					: "ГБ",
 TB					: "ТБ",
 PB					: "ПБ",
 
 // main WND s
 Loading			: "Завантаження...",
 Torrent_file		: "Торент файл",
 Torrent_URL		: "Торент URL",
 Torrent_properties	: "Властивості торента",
 Trackers			: "Трекери",
 Bandwidth_sett		: "Налаштування обмежень",
 Number_ul_slots	: "Кільк. слотів віддачі",
 Peer_ex			: "Обмін пірами",
 About				: "Про програму",
 Enter_label_prom	: "Введіть нову мітку для виділених торентів",
 Remove_torrents	: "Видалити торент(и)",
 Rem_torrents_prompt: "Ви дійсно бажаєте відалити вибрані торент(и)?",
 All				: "Усі",
 Active				: "Активні",
 Inactive			: "Не активні",
 No_label			: "Без мітки",
 Transfer			: "Передача",
 Time_el			: "Часу збігло",
 Remaining			: "Залишилось",
 Share_ratio		: "Пропорція",
 Down_speed			: "Швидкість завантаження",
 Down_limit			: "Обм. завантаження",
 Ul_speed			: "Швидкість віддачі",
 Ul_limit			: "Обм. віддачі",
 Wasted				: "Змарновано",
 Tracker			: "Трекер",
 Track_URL			: "URL трекеру",
 Track_status		: "Статус трекеру",
 Save_as			: "Зберегти як",
 Created_on			: "Створено",
 Comment			: "Коментар",
 
 
 //buttons
 add_button			: "Дод. файл",
 add_url			: "Дод. URL",
 ok				: "   OK   ",
 Cancel				: " Відміна ",
 no				: "   Ні   ",
 
 mnu_add			: "Додати торент",
 mnu_remove			: "Видалити",
 mnu_start			: "Старт",
 mnu_pause			: "Пауза",
 mnu_stop			: "Стоп",
 mnu_rss			: "RSS Завантажувач",
 mnu_settings		: "Налаштування",
 mnu_search			: "Пошук",
 mnu_lang			: "Мова",
 
  //Other variables added by spide
 torrent_add			: "Додати торент", /*Caption of torrent add box*/
 time_w				: "w ", /*for x weeks*/
 time_d				: "d ", /*for x days remaining*/
 time_h				: "h ", /*for x hours remaining*/
 time_m				: "m ", /*for x minutes remaining*/
 time_s				: "s ", /*for x seconds remaining*/ 

 //Novik's addition
 Base_directory    		: "Тека",
 Number_Peers_min		: "Мінімальна кількість Пірів",
 Number_Peers_max		: "Максимальна кількість Пірів",
 Tracker_Numwant		: "Бажана кількість Пірів",
 Number_Peers_For_Seeds_min	: "Мінімальна кількість Сідів",
 Number_Peers_For_Seeds_max	: "Максимальна кількість Сідів",
 Enable_port_open		: "Відкрити вхід. порт",
 dht_port			: "UDP порт для DHT",
 Ather_Limiting			: "Інші обмеження",
 Number_dl_slots		: "Кількість слотів завантаження",
 Glob_max_memory		: "Об'єм макс. використання ОЗУ",
 Glob_max_files			: "Максимальна кількість відкритих файлів",
 Glob_max_http			: "Максимальна кількість відкритих http з'єднань",
 Glob_max_sockets		: "Максимальна кількість відкритих сокетів",
 Ather_sett			: "Інше",
 Directory_For_Dl		: "Тека для закачки за замовчанням",
 Check_hash			: "Перевірити хеш після закачки",
 Hash				: "Хеш",
 IsOpen				: "Відкр.",
 DisableTracker			: "Вимкн.",
 EnableTracker			: "Увімкн.",
 ClientVersion			: "Клієнт",
 Flags				: "Парам.",
 ReqTimeout			: "Тайм-аут для запитів",
 GetTrackerMessage		: "Отримувати повідомлення з трекера",
 Help				: "Допомога",
 PHPDoesnt_enabled		: "Ваший web сервер не підтримує PHP. Виправіть та спробуйте знову",
 Speed				: "Швидкість",
 Dont_add_tname			: "Не додавати ім'я торента до теки", 
 Free_Disk_Space		: "Вільно на диску",
 badXMLRPCVersion		: "rTorrent зкомпільований з невірною версією xmlrpc-c бібліотеки, без підтримки i8. Версія має бути >= 1.11. Частина функціональності не працюватиме",
 badLinkTorTorrent		: "Немає зв'язку з rTorrent. Він взагалі працює? Перевірте $scgi_port та $scgi_host налаштування в config.php та scgi_port в конфігураційному файлі rTorrent",
 badUploadsPath			: "Web сервер не має доступу до теки торентів на читання/запис/виконання. Неможливо додати торент за допомогою ruTorrent",
 badSettingsPath		: "Web сервер не має доступу до теки налаштування. ruTorrent не може зберегти власні налаштування",
 mnu_help			: "Допомога",
 badUploadsPath2		: "rTorrent's користувач не має доступу до теки торентів на читання/виконання. Неможливо додати торент за допомогою ruTorrent",
 View				: "Огляд",
 AsList				: "списком",
 AsTree				: "деревом",
 Group				: "Група",
 SuperSeed			: "Супер-сід",
 badTestPath			: "Користувач rTorrent-а не має доступа до файла ./test.sh на читання/виконання. ruTorrent не працюватиме",
 badSettingsPath2		: "Користувач rTorrent-а не має доступа до теки налаштування на читання/запис/виконання. ruTorrent не працюватиме",
 scrapeDownloaded		: "Завантажено",
 Total				: "Загалом",
 PCRENotFound			: "PHP модуль PCRE не встановлено. ruTorrent не працюватиме",
 addTorrentSuccess		: "torrent-файл успішно додався до rTorrent",
 addTorrentFailed		: "torrent-файл не додався до rTorrent через помилку",
 pnlState			: "Стан",
 newLabel			: "Нова мітка",
 enterLabel			: "Введіть мітку",
 UIEffects			: "Show effects for UI elements",
 Plugins			: "Plugins",
 plgName			: "Name",
 plgStatus			: "Status",
 plgLoaded			: "Loaded",
 plgDisabled			: "Disabled",
 plgVersion			: "Version",
 plgAuthor			: "Author",
 plgDescr			: "Description",
 mnu_go				: "Go",
 pluginCantStart		: "plugin can't start for unknown reason.",
 doFastResume			: "Fast resume",
 innerSearch			: "Local torrents",
 removeTeg			: "Remove tag",
 errMustBeInSomeHost		: "ruTorrent and rTorrent must be installed on the same host. Plugin will not work.",
 warnMustBeInSomeHost		: "ruTorrent and rTorrent must be installed on the same host. Some functionality will be unavailable.",
 plgShutdown			: "Shutdown",
 limit				: "limit",
 speedList			: "Speed popup list (comma-separated)",
 ClearButton			: "Clear",
 dontShowTimeouts		: "Ignore message about timeouts",
 fullTableRender		: "Full render of large tables",
 showScrollTables		: "Show table contents while scrolling",
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
 noTorrentList			: "Torrent list not yet available, connection to rtorrent not established.",
 yes				: "yes",
 no				: "no",
 DateFormat			: "Date format",
 DLStrategy			: "Download strategy",
 prioritizeFirst		: "Leading chunk first",
 prioritizeLast			: "Trailing chunk first",
 prioritizeNormal		: "Normal",
 updateTracker			: "Update trackers",
 scrapeUpdate			: "Was updated",
 trkInterval			: "Interval"
 };
