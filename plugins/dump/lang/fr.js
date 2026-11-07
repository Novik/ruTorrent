 /*
 * PLUGIN DUMP
 *
 * French language file.
 *
 * Author:
 */

theUILang.dumpTorrent		= "Dump du .torrent...";
theUILang.dumpErrorNoHash			= "Plugin 'Dump' : la requête ne contenait aucun hash de torrent.";
theUILang.dumpErrorNoCommand			= "Plugin 'Dump' : la requête ne contenait aucune commande de dump.";
theUILang.dumpErrorNoAnswer			= "Plugin 'Dump' : rTorrent n'a pas répondu, le fichier torrent n'a donc pas pu être trouvé.";
theUILang.dumpErrorNoSessionDirectory		= "Plugin 'Dump' : rTorrent n'indique aucun répertoire de session, et ce torrent n'a pas de fichier associé.";
theUILang.dumpErrorNoFile			= "Plugin 'Dump' : aucun fichier torrent à l'emplacement {file}.";
theUILang.dumpErrorUnreadable			= "Plugin 'Dump' : impossible de lire le fichier {file} en tant qu'utilisateur {user}, vérifiez les droits du fichier.";
theUILang.dumpErrorUnreadableUnknownUser	= "Plugin 'Dump' : impossible de lire le fichier {file}, vérifiez les droits du fichier.";

thePlugins.get("dump").langLoaded();
