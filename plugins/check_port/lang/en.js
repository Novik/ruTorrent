/*
 * PLUGIN CHECK_PORT
 *
 * English language file.
 *
 * Author:
 */

 theUILang.checkWebsiteNotFound = "Check_port plugin: Plugin will not work. Invalid configuration";
 theUILang.checkPort		= "Check Port Status";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Port status is unknown",
 				  "Port is closed",
 				  "Port is open"
 				  ];
 theUILang.notAvailable = "-";
 theUILang.portNotConfigured = "Not available on this server";

 theUILang.forcePort		= "Force specific port...";
 theUILang.forcePortPrompt	= "Set the listening port (1-65535):";
 theUILang.forcePortInvalid	= "Invalid port number.";

thePlugins.get("check_port").langLoaded();
