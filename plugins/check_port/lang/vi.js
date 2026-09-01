/*
 * PLUGIN CHECK_PORT
 *
 * Vietnamese language file.
 *
 * Author: Ta Xuan Truong (truongtx8 AT gmail DOT com)
 */

 theUILang.checkWebsiteNotFound = "Check_port plugin: Plugin will not work. Invalid configuration";
 theUILang.checkPort		= "Kiểm tra trạng thái cổng";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Không rõ tình trạng cổng",
 				  "Cổng bị đóng",
 				  "Cổng đang mở"
 				  ];
 theUILang.notAvailable = "-";
 theUILang.portNotConfigured = "Not available on this server";

 theUILang.forcePort		= "Force specific port...";
 theUILang.forcePortPrompt	= "Set the listening port (1-65535):";
 theUILang.forcePortInvalid	= "Invalid port number.";

thePlugins.get("check_port").langLoaded();
