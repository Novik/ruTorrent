/*
 * PLUGIN SCHEDULER
 *
 * File Name: fr.js
 * 	French language file.
 *
 * File Author:
 *    Nicobubulle (nicobubulle@gmail.com)
 */
 
 theUILang.scheduler                    = "Planificateur";
 theUILang.schedulerOn                  = "Activer le planificateur";
 theUILang.schedulerGraph                       = "Scheduler Table";
 theUILang.schShortWeek                 = [ "Lun", "Mar", "Mer", "Jeu", "Vre", "Sam", "Dim" ];
 theUILang.schFullWeek                  = [ "Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche" ];

 theUILang.schUnlimited                 = "Illimit\u00e9";
 theUILang.schLimited                   = "Limit\u00e9";
 theUILang.schTurnOff                   = "Eteins";
 theUILang.schSeedingOnly                       = "Seulement en Seed";

 theUILang.schUnlimitedDesc             = "Illimit\u00e9 - Sans limite globale";
 theUILang.schLimitedDesc                       = "Limit\u00e9 - Utilise les limites sp\u00e9cifiques du planificateur";
 theUILang.schTurnOffDesc                       = "Eteins - Arrete tous les torrents";
 theUILang.schSeedingOnlyDesc           = "Seulement en Seed - Partage uniquement les donn\u00e9es";

 theUILang.schLimitedUL                 = "Envoi limit\u00e9";
 theUILang.schLimitedDL                 = "R\u00e9ception limit\u00e9e";

 theUILang.schedPHPNotFound             = "Plugin 'Scheduler': rTorrent ne peut pas acc\u00e9der \u00e0 l'interpr\u00e9teur php. Le plugin ne fonctionnera pas.";
 theUILang.schedUpdaterNotAvailable     = "Plugin 'Scheduler': rTorrent ne peut pas acc\u00e9der au fichier plugins/scheduler/update.php en Lecture. Le plugin ne fonctionnera pas.";

thePlugins.get("scheduler").langLoaded();