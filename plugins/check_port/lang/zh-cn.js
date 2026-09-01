/*
 * PLUGIN CHECK_PORT
 *
 * Chinese Simplified language file.
 *
 * Author:
 */

 theUILang.checkWebsiteNotFound = "Check_port plugin: Plugin will not work. Invalid configuration";
 theUILang.checkPort		= "检查端口状态";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "端口状态未知",
 				  "端口是关闭的",
 				  "端口是开放的"
 				  ];
 theUILang.notAvailable = "-";
 theUILang.portNotConfigured = "Not available on this server";

 theUILang.forcePort		= "Force specific port...";
 theUILang.forcePortPrompt	= "Set the listening port (1-65535):";
 theUILang.forcePortInvalid	= "Invalid port number.";

thePlugins.get("check_port").langLoaded();
