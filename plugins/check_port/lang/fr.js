/*
 * PLUGIN CHECK_PORT
 *
 * French language file.
 *
 * Author: Nicobubulle (nicobubulle@gmail.com)
 */

 theUILang.checkWebsiteNotFound = "Plugin 'Check_port' : Configuration invalide. Le plugin ne fonctionnera pas.";
 theUILang.checkPort		= "Vérification du port";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Statut du port inconnu",
 				  "Le port est fermé",
 				  "Le port est ouvert"
 				  ];
 theUILang.notAvailable = "-";

 theUILang.forcePort		= "Forcer un port spécifique...";
 theUILang.forcePortPrompt	= "Définir le port d'écoute (1-65535) :";
 theUILang.forcePortInvalid	= "Numéro de port invalide.";

thePlugins.get("check_port").langLoaded();
