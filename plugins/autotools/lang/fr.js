/*
 * PLUGIn AUTOTOOLS
 * 
 * au fichier Name: fr.js
 *      French language au fichier.
 * 
 * au fichier Author:
 *    Nicobubulle (nicobubulle@gmail.com)
 */
 var s_NoAccess         = "Plugin 'Autotools' : rTorrent ne peut pas acc\u00e9der ";
 var s_PluginFail       = "Le plugin ne fonctionnera pas.";

 theUILang.autotools                    = "Autotools";
 theUILang.autotoolsActiverLabel         = "Activer la fonctionnalit\u00e9 'AutoLabel'. Masque:";
 theUILang.autotoolsActiverMove          = "Activer la fonctionnalit\u00e9 'AutoMove'.";
 theUILang.autotoolsPathToFinished      = "Chemin vers le r\u00e9pertoire des torrents termin\u00e9s";
 theUILang.autotoolsActiverWatch         = "Activer la fonctionnalit\u00e9 'AutoWatch'.";
 theUILang.autotoolsPathToWatch         = "Chemin vers le r\u00e9pertoire \u00e0 observer";
 theUILang.autotoolsWatchStart          = "D\u00e9marrer le t\u00e9l\u00e9chargement automatiquement.";
 theUILang.autotoolsPHPNotFound         = s_NoAccess + "\u00e0 l'interpr\u00e9teur php. " + s_PluginFail;
 theUILang.autotoolsLabelShNotAvailable = s_NoAccess + "au fichier plugins/autotools/label.sh en lecture/ex\u00e9cution. " + s_PluginFail;
 theUILang.autotoolsLabelPhpNotAvailable        = s_NoAccess + "au fichier plugins/autotools/label.php en lecture. " + s_PluginFail;
 theUILang.autotoolsMoveShNotAvailable  = s_NoAccess + "au fichier plugins/autotools/move.sh en lecture/ex\u00e9cution. " + s_PluginFail;
 theUILang.autotoolsMovePhpNotAvailable = s_NoAccess + "au fichier plugins/autotools/move.php en lecture. " + s_PluginFail;
 theUILang.autotoolsWatchPhpNotAvailable        = s_NoAccess + "au fichier plugins/autotools/watch.php en lecture. " + s_PluginFail;
 theUILang.autotoolsNoPathToFinished    = "Plugin 'Autotools': le chemin vers le r\u00e9pertoire des torrents termin\u00e9s n'est pas d\u00e9fini. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch               = "Plugin 'Autotools': le chemin vers le r\u00e9pertoire \u00e0 observer n'est pas d\u00e9fini. " + s_PluginFail;

thePlugins.get("autotools").langLoaded();