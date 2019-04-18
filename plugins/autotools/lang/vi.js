/*
 * PLUGIN AUTOTOOLS
 *
 * Vietnamese language file.
 *
 * Author: Ta Xuan Truong (truongtx8 AT gmail DOT com)
 */

 var s_PluginFail			= "Thành phần bổ sung sẽ không hoạt động.";

 theUILang.autotools			= "Công cụ tự động";
 theUILang.autotoolsEnableLabel		= "Kích hoạt tính năng \"Tự đánh nhãn\", Mẫu:";
 theUILang.autotoolsPathToFinished	= "Đường dẫn lưu torrent tải xong";
 theUILang.autotoolsEnableWatch		= "Kích hoạt tính năng \"Tự theo dõi\"";
 theUILang.autotoolsPathToWatch		= "Theo dõi đường dẫn";
 theUILang.autotoolsWatchStart		= "Tự động tải xuống";
 theUILang.autotoolsNoPathToFinished	= "Công cụ tự động: Chưa đặt đường dẫn lưu torrent tải xuống. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Công cụ tự động: Chưa đặt đường dẫn theo dõi. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Hành động";
 theUILang.autotoolsFileOpMove		= "Di chuyển";
 theUILang.autotoolsFileOpHardLink	= "Liên kết cứng";
 theUILang.autotoolsFileOpCopy		= "Sao chép";
 theUILang.autotoolsFileOpSoftLink	= "Liên kết mềm";
 theUILang.autotoolsAddLabel		= "Add torrent's label to path";
 theUILang.autotoolsAddName		= "Add torrent's name to path";
 theUILang.autotoolsEnableMove		= "Enable \"AutoMove\" if torrent's label matches filter";
 theUILang.autotoolsSkipMoveForFiles	= "Skip torrents that contain files matching pattern";

thePlugins.get("autotools").langLoaded();