/*
 * PLUGIN CHECK_PORT
 *
 * Slovak language file.
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

 theUILang.forcePort		= "Vynútiť konkrétny port...";
 theUILang.forcePortPrompt	= "Nastavte počúvací port (1-65535):";
 theUILang.forcePortInvalid	= "Neplatné číslo portu.";

thePlugins.get("check_port").langLoaded();
