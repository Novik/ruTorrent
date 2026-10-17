/*
 * PLUGIN CHECK_PORT
 *
 * Norwegian (nynorsk) language file.
 *
 * Author: jakobbg (jakob@grimstveit.no)
 */

 theUILang.checkWebsiteNotFound = "Check_port-plugin: Plugin vil ikkje virke. Ugyldig oppsett";
 theUILang.checkPort		= "Sjekk portstatus";
 theUILang.checkingPort		= "Sjekkar portstatus";
 theUILang.portStatus		= [
 				  "Portstatus er ukjent",
 				  "Port er lukket",
 				  "Port er åpen"
 				  ];
 theUILang.notAvailable = "-";
 theUILang.portNotConfigured = "Ikkje tilgjengeleg på denne tenaren";

 theUILang.forcePort		= "Tving bestemt port...";
 theUILang.forcePortPrompt	= "Oppgje lytteporten (1-65535):";
 theUILang.forcePortInvalid	= "Ugyldig portnummer.";

thePlugins.get("check_port").langLoaded();
