/*
 * PLUGIN AUTOTOOLS
 *
 * Italian language file.
 *
 * Author: Gianni
 */

 var s_PluginFail			= "Il plugin non funzionerà";

 theUILang.autotools			= "Autotools";
 theUILang.autotoolsEnableLabel		= "Abilita funzione \"AutoLabel\" , Modello:";
 theUILang.autotoolsPathToFinished	= "Percorso dei download completati";
 theUILang.autotoolsEnableWatch		= "Abilita funzione \"AutoWatch\" ";
 theUILang.autotoolsPathToWatch		= "Percorso della cartella di base da controllare";
 theUILang.autotoolsWatchStart		= "Avvia download automaticamente";
 theUILang.autotoolsNoPathToFinished	= "Autotools plugin: il percorso dei download completati non è impostato. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Autotools plugin: il percorso dalla cartella da controllare non è impostato. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Tipo di operazione";
 theUILang.autotoolsFileOpMove		= "Sposta";
 theUILang.autotoolsFileOpHardLink	= "Link solido";
 theUILang.autotoolsFileOpCopy		= "Copia";
 theUILang.autotoolsFileOpSoftLink	= "Link simbolico";
 theUILang.autotoolsAddLabel		= "Aggiungi l'etichetta del torrent al percorso";
 theUILang.autotoolsAddName		= "Aggiungi il nome del torrent al percorso";
 theUILang.autotoolsEnableMove		= "Abilita la funzione \"AutoMove\" se l'etichetta del torrent soddisfa il filtro";
 theUILang.autotoolsSkipMoveForFiles	= "Salta torrent che contengono file che soddisfano questo filtro";

thePlugins.get("autotools").langLoaded();
