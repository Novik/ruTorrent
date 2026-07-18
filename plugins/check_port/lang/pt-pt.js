/*
 * PLUGIN CHECK_PORT
 *
 * Portuguese (Portugal) language file.
 *
 * Author:
 */

 theUILang.checkWebsiteNotFound = "Check_port plugin: O plug-in não funcionará. Configuração inválida";
 theUILang.checkPort		= "Verificar estado da porta";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "O estado da porta é desconhecido",
 				  "A porta está fechada",
 				  "A porta está aberta"
 				  ];
 theUILang.notAvailable = "-";

 theUILang.forcePort		= "Forçar porta específica...";
 theUILang.forcePortPrompt	= "Definir a porta de escuta (1-65535):";
 theUILang.forcePortInvalid	= "Número de porta inválido.";

thePlugins.get("check_port").langLoaded();
