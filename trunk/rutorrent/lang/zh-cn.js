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
 * File Name: cn.js
 * 	Chinese Simplified language file.
 * 
 * File Author:
 * 		skevin (skevin@china.com)
 */
 
 var theUILang =
 {
 //all used
 kbs				: "kB/s",
 
 
 //Settings window
 
 General			: "常规",
 Downloads			: "下载",
 Connection			: "连接",
 BitTorrent			: "任务",
 Queueing			: "队列",
 Scheduler			: "计划",
 Advanced			: "高级",
 User_Interface		: "界面",
 Confirm_del_torr	: "删除 Torrent 时要求确认",
 Update_GUI_every	: "每次更新 GUI ",
 ms					: "ms",
 Alt_list_bckgnd	: "交替显示列表背景色",
 Show_cat_start		: "启动时显示类别",
 Show_det_start		: "启动时显示详情",
 Dnt_start_down_auto: "不要自动开始下载",
 Other_sett			: "其他设置",
 Listening_Port		: "监听端口",
 Port_f_incom_conns	: "传入连接所使用的端口",
 Rnd_port_torr_start: "每次 rTorrent 启动时使用随机端口",
 Type				: "类型",
 Bandwidth_Limiting : "带宽限制",
 Global_max_upl		: "全局最大上传速度",
 unlimited			: "无限制",
 Glob_max_downl		: "全局最大下载速度",
 Add_bittor_featrs	: "其他功能选项s",
 En_DHT_ntw			: "启用 DHT 网络",
 Peer_exch			: "用户交换",
 Ip_report_track	: "向 Tracker 提交的 IP 地址/主机名",
 Disabled			: "禁用",
 Enabled			: "启用",
 Advanced			: "高级",
 Cancel				: "取消",
 uTorrent_settings	: "rTorrent 设置",
 
// Main window
 Doesnt_support		: "这个 ruTorrent 不支持你使用的浏览器.",
 Name				: "名称",
 Status				: "状态",
 Size				: "大小",
 Done				: "完成率",
 Downloaded			: "已下载",
 Uploaded			: "已上传",
 Ratio				: "分享率",
 DL					: "下载速度",
 UL					: "上传速度",
 ETA				: "剩余时间",
 Label				: "标签",
 Peers				: "用户数量",
 Seeds				: "种子数量",
 Avail				: "健康度",
 Remaining			: "剩余时间",
 Priority			: "优先级",
 Download			: "下载",
 Upload				: "上传",
 Not_torrent_file	: "该文件不是有效的 torrent 文件.",
 Pausing			: "暂停中",
 Seeding			: "做种中",
 Downloading		: "下载中",
 Checking			: "检查中",
 Error				: "错误",
 Queued				: "排队中",
 Finished			: "已完成",
 Stopped			: "已停止",
 Request_timed_out	: "请求 rTorrent 超时.",
 
 Start				: "开始",
 Pause				: "暂停",
 Stop				: "停止",
 Force_recheck		: "强制重新检查",
 New_label			: "新建标签...",
 Remove_label		: "移除标签",
 Labels				: "标签",
 Remove				: "移除",
 Delete_data		: "删除下载的数据",
 Remove_and			: "移除并",
 Details			: "详情...",
 Properties			: "属性...",
 of					: "的", 		//this two creates line 
 connected			: "已连接",	//  XX of YY connected
 High_priority		: "较高优先级",
 Normal_priority	: "正常优先级",
 Low_priority		: "较低优先级",
 Dont_download		: "不要下载",
 Files				: "文件",
 Logger				: "日志",
 
 s					: "s",    // part of "KB/s"
 bytes				: "bytes",
 KB					: "KB",
 MB					: "MB",
 GB					: "GB",
 TB					: "TB",
 PB					: "PB",
 
 // main WND s
 Loading			: "加载中...",
 Torrent_file		: "Torrent 文件",
 Torrent_URL		: "Torrent 链接",
 Torrent_properties	: "Torrent 属性",
 Trackers			: "Tracker",
 Bandwidth_sett		: "带宽设置",
 Number_ul_slots	: "上传通道数",
 Peer_ex			: "用户交换",
 About				: "关于",
 Enter_label_prom	: "为选定的 Torrent 输入新的标签",
 Remove_torrents	: "移除 Torrent(s)",
 Rem_torrents_prompt: "您确定要移除选定的 Torrent 吗?",
 All				: "全部",
 Active				: "活动",
 Inactive			: "停止",
 No_label			: "其他",
 Transfer			: "传输",
 Time_el			: "已用时间",
 Remaining			: "剩余时间",
 Share_ratio		: "分享比率",
 Down_speed			: "下载速度",
 Down_limit			: "下载限制",
 Ul_speed			: "上传速度",
 Ul_limit			: "上传限制",
 Wasted				: "丢弃数据",
 Tracker			: "Tracker",
 Track_URL			: "Tracker 链接",
 Track_status		: "Tracker 状态",
 Save_as			: "文件位置",
 Created_on			: "创建时间",
 Comment			: "制作说明",
 
 
 //buttons
 add_button			: "添加文件",
 add_url			: "添加链接",
 ok				: "   OK   ",
 Cancel				: "  取消  ",
 no				: "   否   ",
 
 mnu_add			: "添加 Torrent",
 mnu_remove			: "移除",
 mnu_start			: "开始",
 mnu_pause			: "暂停",
 mnu_stop			: "停止",
 mnu_rss			: "RSS 下载器",
 mnu_settings			: "设置",
 mnu_search			: "搜索",
 mnu_lang			: "选用语言",
 
 //Other variables added by spide
 torrent_add			: "添加 Torrent", /*Caption of torrent add box*/
 time_w				: "w ", /*for x weeks*/
 time_d				: "d ", /*for x days remaining*/
 time_h				: "h ", /*for x hours remaining*/
 time_m				: "m ", /*for x minutes remaining*/
 time_s				: "s ", /*for x seconds remaining*/

 //Novik's addition
 Base_directory    		: "目录",
 Number_Peers_min		: "最小用户数",
 Number_Peers_max		: "最大用户数",
 Tracker_Numwant		: "希望用户数",
 Number_Peers_For_Seeds_min	: "最小种子数",
 Number_Peers_For_Seeds_max	: "最大种子数",
 Enable_port_open		: "打开监听端口",
 dht_port			: "DHT 使用的 UDP 端口",
 Ather_Limiting			: "其他限制",
 Number_dl_slots		: "下载通道数",
 Glob_max_memory		: "最大使用内存",
 Glob_max_files			: "最大打开文件数",
 Glob_max_http			: "最大打开 HTTP 连接数",
 Glob_max_sockets		: "最大打开 Sockets 数",
 Ather_sett			: "其他",
 Directory_For_Dl		: "默认下载目录",
 Check_hash			: "下载完成后检查 Hash",
 Hash				: "Hash",
 IsOpen				: "打开",
 DisableTracker			: "禁用",
 EnableTracker			: "启用",
 ClientVersion			: "客户端",
 Flags				: "标记",
 ReqTimeout			: "请求超时",
 GetTrackerMessage		: "从 Tracker 接收信息",
 Help				: "帮助",
 PHPDoesnt_enabled		: "你的 Web 服务器不支持 PHP. 纠正并重试.",
 Speed				: "速度",
 Dont_add_tname			: "不要添加 Torrent 的名称到目录", 
 Free_Disk_Space		: "可用磁盘空间",
 badXMLRPCVersion		: "rTorrent is compiled with incorrect version of xmlrpc-c library, without i8 support. Version must be >= 1.11. Some functionality will be unavailable.",
 badLinkTorTorrent		: "Bad link to rTorrent. Check if it is really running. Check $scgi_port and $scgi_host settings in config.php and scgi_port in rTorrent configuration file.",
 badUploadsPath			: "Web-server can't access torrents directory for read/write/execute. You can't add torrents through ruTorrent.",
 badSettingsPath		: "Web-server can't access settings directory for read/write/execute. ruTorrent can't save own settings.",
 mnu_help			: "关于",
 badUploadsPath2		: "rTorrent's user can't access torrents directory for read/execute. You can't add torrents through ruTorrent.",
 View				: "查看",
 AsList				: "为列表",
 AsTree				: "为树状",
 Group				: "组",
 SuperSeed			: "超级种子",
 badTestPath			: "rTorrent's user can't access file ./test.sh for read/execute. ruTorrent will not work.",
 badSettingsPath2		: "rTorrent's user can't access settings directory for read/write/execute. ruTorrent will not work.",
 scrapeDownloaded		: "已下载",
 Total				: "总计",
 PCRENotFound			: "PHP module PCRE is not installed. ruTorrent will not work.",
 addTorrentSuccess		: "torrent was successfully passed to rTorrent.",
 addTorrentFailed		: "Error: torrent wasn't passed to rTorrent.",
 pnlState			: "状态",
 newLabel			: "新标签",
 enterLabel			: "输入标签",
 UIEffects			: "UI 元素显示特效",
 Plugins			: "插件",
 plgName			: "名称",
 plgStatus			: "状态",
 plgLoaded			: "已载入",
 plgDisabled			: "禁用",
 plgVersion			: "版本",
 plgAuthor			: "作者",
 plgDescr			: "描述",
 mnu_go				: "Go",
 pluginCantStart		: "plugin can't start for unknown reason.",
 doFastResume			: "快速恢复",
 innerSearch			: "本地 torrents",
 removeTeg			: "移除标签",
 errMustBeInSomeHost		: "ruTorrent and rTorrent must be installed on the same host. Plugin will not work.",
 warnMustBeInSomeHost		: "ruTorrent and rTorrent must be installed on the same host. Some functionality will be unavailable.",
 plgShutdown			: "关闭",
 limit				: "限制",
 speedList			: "弹出列表速度 (逗号分隔)",
 ClearButton			: "清除",
 dontShowTimeouts		: "忽略有关超时消息",
 fullTableRender		: "全部呈现为大表",
 showScrollTables		: "滚动时显示表内容",
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
 trkInterval			: "Interval",
 logAutoSwitch			: "Autoswitch to 'Log' tab"
};
