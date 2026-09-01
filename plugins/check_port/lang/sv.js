/*
 * PLUGIN CHECK_PORT
 *
 * Swedish language file.
 *
 * Author: Magnus Holm (holmen@brasse.se)
 */

 theUILang.checkWebsiteNotFound = "Check_port plugin: Plugin will not work. Invalid configuration";
 theUILang.checkPort		= "Kontrollera portstatus";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Portstatus okänd",
 				  "Port är stängd",
 				  "Port är öppen"
 				  ];
 theUILang.notAvailable = "-";
 theUILang.portNotConfigured = "Not available on this server";

 theUILang.forcePort		= "Force specific port...";
 theUILang.forcePortPrompt	= "Set the listening port (1-65535):";
 theUILang.forcePortInvalid	= "Invalid port number.";

thePlugins.get("check_port").langLoaded();
