/*
 * PLUGIN CHECK_PORT
 *
 * Dutch language file.
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

 theUILang.forcePort		= "Specifieke poort forceren...";
 theUILang.forcePortPrompt	= "Luisterpoort instellen (1-65535):";
 theUILang.forcePortInvalid	= "Ongeldig poortnummer.";

thePlugins.get("check_port").langLoaded();
