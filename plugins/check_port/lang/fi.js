/*
 * PLUGIN CHECK_PORT
 *
 * Finnish language file.
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

 theUILang.forcePort		= "Pakota tietty portti...";
 theUILang.forcePortPrompt	= "Aseta kuunteluportti (1-65535):";
 theUILang.forcePortInvalid	= "Virheellinen portin numero.";

thePlugins.get("check_port").langLoaded();
