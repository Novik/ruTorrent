/*
 * PLUGIN CHECK_PORT
 *
 * Norwegian language file.
 *
 * Author: nirosa (nirosax@gmail.com)
 */

 theUILang.checkWebsiteNotFound = "Check_port-plugin: Plugin vil ikke virke. Ugyldig oppsett";
 theUILang.checkPort		= "Sjekk portstatus";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Portstatus er ukjent",
 				  "Port er lukket",
 				  "Port er åpen"
 				  ];
 theUILang.notAvailable = "-";

 theUILang.forcePort		= "Tving bestemt port...";
 theUILang.forcePortPrompt	= "Angi lytteporten (1-65535):";
 theUILang.forcePortInvalid	= "Ugyldig portnummer.";

thePlugins.get("check_port").langLoaded();
