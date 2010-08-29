/*
 * PLUGIN AUTOTOOLS
 * 
 * File Name: fr.js
 * 	French language file.
 * 
 * File Author:
 *    Nicobubulle (nicobubulle@gmail.com)
 */

 var s_NoAccess         = "Plug-in 'Autotools' : rTorrent ne peut pas accéder ";
 var s_PluginFail       = "Le plug-in ne fonctionnera pas.";

 theUILang.autotools                    = "Autotools";
 theUILang.autotoolsEnableLabel         = "Activer la fonctionnalité 'AutoLabel'. Masque:";
 theUILang.autotoolsEnableMove          = "Activer la fonctionnalité 'AutoMove'.";
 theUILang.autotoolsPathToFinished      = "Chemin vers le répertoire des torrents terminés";
 theUILang.autotoolsEnableWatch         = "Activer la fonctionnalité 'AutoWatch'.";
 theUILang.autotoolsPathToWatch         = "Chemin vers le répertoire à observer";
 theUILang.autotoolsWatchStart          = "Démarrer le téléchargement automatiquement.";
 theUILang.autotoolsPHPNotFound         = s_NoAccess + "à l'interpréteur php. " + s_PluginFail;
 theUILang.autotoolsLabelShNotAvailable = s_NoAccess + "au fichier plugins/autotools/label.sh en lecture/exécution. " + s_PluginFail;
 theUILang.autotoolsLabelPhpNotAvailable        = s_NoAccess + "au fichier plugins/autotools/label.php en lecture. " + s_PluginFail;
 theUILang.autotoolsMoveShNotAvailable  = s_NoAccess + "au fichier plugins/autotools/move.sh en lecture/exécution. " + s_PluginFail;
 theUILang.autotoolsMovePhpNotAvailable = s_NoAccess + "au fichier plugins/autotools/move.php en lecture. " + s_PluginFail;
 theUILang.autotoolsWatchPhpNotAvailable        = s_NoAccess + "au fichier plugins/autotools/watch.php en lecture. " + s_PluginFail;
 theUILang.autotoolsNoPathToFinished    = "Plug-in 'Autotools': le chemin vers le répertoire des torrents terminés n'est pas défini. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch               = "Plug-in 'Autotools': le chemin vers le répertoire à observer n'est pas défini. " + s_PluginFail;

thePlugins.get("autotools").langLoaded();