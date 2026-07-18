/*
 * PLUGIN CHECK_PORT
 *
 * Danish language file.
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

 theUILang.forcePort		= "Gennemtving bestemt port...";
 theUILang.forcePortPrompt	= "Angiv lytteporten (1-65535):";
 theUILang.forcePortInvalid	= "Ugyldigt portnummer.";

thePlugins.get("check_port").langLoaded();
