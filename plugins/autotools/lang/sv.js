/*
 * PLUGIN AUTOTOOLS
 *
 * Swedish language file.
 *
 * Author: Magnus Holm (holmen@brasse.se)
 */

 var s_PluginFail			= "Insticksprogrammet kommer inte att fungera.";

 theUILang.autotools			= "Automatiseringsverktyg";
 theUILang.autotoolsEnableLabel		= "Aktivera \"Automatisk etikett\"-funktion, Mall:";
 theUILang.autotoolsPathToFinished	= "Sökväg till färdiga nedladdningar";
 theUILang.autotoolsEnableWatch		= "Aktivera \"Automatisk övervakning\"-funktion";
 theUILang.autotoolsPathToWatch		= "Sökväg till övervakad mapp";
 theUILang.autotoolsWatchStart		= "Starta nedladdning automatiskt";
 theUILang.autotoolsNoPathToFinished	= "Insticksprogram, Automatiseringsverktyg: ej angett sökväg till färdiga nedladdningar. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Insticksprogram, Automatiseringsverktyg: ej angett söhväg till övervakad match. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Operationstyp";
 theUILang.autotoolsFileOpMove		= "Flytta";
 theUILang.autotoolsFileOpHardLink	= "Hård länk";
 theUILang.autotoolsFileOpCopy		= "Kopiera";
 theUILang.autotoolsFileOpSoftLink	= "Mjuk länk";
 theUILang.autotoolsAddLabel		= "Add torrent's label to path";
 theUILang.autotoolsAddName		= "Add torrent's name to path";
 theUILang.autotoolsEnableMove		= "Enable \"AutoMove\" if torrent's label matches filter";
 theUILang.autotoolsSkipMoveForFiles	= "Skip torrents that contain files matching pattern";

thePlugins.get("autotools").langLoaded();