/*
 * PLUGIN CHECK_PORT
 *
 * Ukrainian language file.
 *
 * Author: Oleksandr Natalenko (oleksandr@natalenko.name)
 */

 theUILang.checkWebsiteNotFound = "Check_port plugin: Plugin will not work. Invalid configuration";
 theUILang.checkPort		= "Перевірити стан порту";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Стан порту невідомий",
 				  "Порт закрито",
 				  "Порт відкрито"
 				  ];
 theUILang.notAvailable = "-";
 theUILang.portNotConfigured = "Not available on this server";

 theUILang.forcePort		= "Force specific port...";
 theUILang.forcePortPrompt	= "Set the listening port (1-65535):";
 theUILang.forcePortInvalid	= "Invalid port number.";

thePlugins.get("check_port").langLoaded();
