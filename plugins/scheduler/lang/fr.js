/*
 * PLUGIN SCHEDULER
 *
 * File Name: fr.js
 *      French language file.
 *
 * File Author:
 *    Nicobubulle (nicobubulle@gmail.com)
 */
 
 theUILang.scheduler                    = "Planificateur";
 theUILang.schedulerOn                  = "Activer le planificateur";
 theUILang.schedulerGraph                       = "Scheduler Table";
 theUILang.schShortWeek                 = [ "Lun", "Mar", "Mer", "Jeu", "Vre", "Sam", "Dim" ];
 theUILang.schFullWeek                  = [ "Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche" ];

 theUILang.schUnlimited                 = "Illimité";
 theUILang.schLimited                   = "Limité";
 theUILang.schTurnOff                   = "Éteint";
 theUILang.schSeedingOnly                       = "Seulement en Seed";

 theUILang.schUnlimitedDesc             = "Illimité - Sans limite globale";
 theUILang.schLimitedDesc                       = "Limité - Utilise les limites spécifiques du planificateur";
 theUILang.schTurnOffDesc                       = "Éteint - Arrete tous les torrents";
 theUILang.schSeedingOnlyDesc           = "Seulement en Seed - Partage uniquement les données";

 theUILang.schLimitedUL                 = "Envoi limité";
 theUILang.schLimitedDL                 = "Réception limitée";

 theUILang.schedPHPNotFound             = "Plugin 'Scheduler': rTorrent ne peut pas accéder à l'interpréteur php. Le plug-in ne fonctionnera pas.";
 theUILang.schedUpdaterNotAvailable     = "Plugin 'Scheduler': rTorrent ne peut pas accéder au fichier plugins/scheduler/update.php en Lecture. Le plug-in ne fonctionnera pas.";

 theUILang.shcIgnore                    = "Ignorer le planificateur";

thePlugins.get("scheduler").langLoaded();