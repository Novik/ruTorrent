/*
 * PLUGIN AUTOTOOLS
 *
 * Swedish language file.
 *
 * Author: Magnus Holm (holmen@brasse.se) 
 */

 var s_PluginFail			= "Insticksprogrammet kommer inte att fungera.";

 theUILang.autotools			= "Automatiseringsverktyg";
 theUILang.autotoolsEnableLabel 	= "Aktivera \"Automatisk etikett\"-funktion, Mall:";
 theUILang.autotoolsEnableMove		= "Aktivera \"Flytta automatiskt\"-funktion";
 theUILang.autotoolsPathToFinished	= "Sökväg till färdiga nedladdningar";
 theUILang.autotoolsEnableWatch 	= "Aktivera \"Automatisk övervakning\"-funktion";
 theUILang.autotoolsPathToWatch 	= "Sökväg till övervakad mapp";
 theUILang.autotoolsWatchStart		= "Starta nedladdning automatiskt";
 theUILang.autotoolsNoPathToFinished	= "Insticksprogram, Automatiseringsverktyg: ej angett sökväg till färdiga nedladdningar." + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Insticksprogram, Automatiseringsverktyg: ej angett söhväg till övervakad match. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Operationstyp";
 theUILang.autotoolsFileOpMove		= "Flytta";
 theUILang.autotoolsFileOpHardLink 	= "Hård länk";
 theUILang.autotoolsFileOpCopy		= "Kopiera";
 theUILang.autotoolsFileOpSoftLink	= "Mjuk länk";

thePlugins.get("autotools").langLoaded();
