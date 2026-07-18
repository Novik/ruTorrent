/*
 * PLUGIN CHECK_PORT
 *
 * Spanish language file.
 *
 * Author:
 */

 theUILang.checkWebsiteNotFound = "Check_port plugin: Plugin will not work. Invalid configuration";
 theUILang.checkPort		= "Verificar estado del puerto";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Es estado del puerto es desconocido",
 				  "Puerto cerrado",
 				  "Puerto abierto"
 				  ];
 theUILang.notAvailable = "-";

 theUILang.forcePort		= "Forzar puerto específico...";
 theUILang.forcePortPrompt	= "Establecer el puerto de escucha (1-65535):";
 theUILang.forcePortInvalid	= "Número de puerto no válido.";

thePlugins.get("check_port").langLoaded();
