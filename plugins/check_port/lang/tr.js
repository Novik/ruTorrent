/*
 * PLUGIN CHECK_PORT
 *
 * Turkish language file.
 *
 * Authors: Müslüm Barış Korkmazer (bkbabinco@gmail.com)
 *		    Selim Şumlu
 */

 theUILang.checkWebsiteNotFound = "Check_port eklentisi: Eklenti çalışmayacak. Yapılandırma geçersiz.";
 theUILang.checkPort		= "Port durumunu denetle";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Port durumu bilinmiyor",
 				  "Port kapalı",
 				  "Port açık"
 				  ];
 theUILang.notAvailable = "-";
 theUILang.portNotConfigured = "Not available on this server";

 theUILang.forcePort		= "Force specific port...";
 theUILang.forcePortPrompt	= "Set the listening port (1-65535):";
 theUILang.forcePortInvalid	= "Invalid port number.";

thePlugins.get("check_port").langLoaded();
