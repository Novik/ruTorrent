/*
 * PLUGIN AUTOTOOLS
 *
 * Spanish language file.
 *
 * Author: 
 */

 var s_PluginFail			= "El Plugin no funcionará.";

 theUILang.autotools			= "Autotools";
 theUILang.autotoolsEnableLabel		= "Habilitar \"AutoLabel\", Template:";
 theUILang.autotoolsPathToFinished	= "Ruta para las descargas finalizadas";
 theUILang.autotoolsEnableWatch		= "Habilitar \"AutoWatch\"";
 theUILang.autotoolsPathToWatch		= "Ruta al directorio watch";
 theUILang.autotoolsWatchStart		= "Comenzar descargas automáticamentes";
 theUILang.autotoolsNoPathToFinished	= "Autotools plugin: no está definida la ruta de descargas. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Autotools plugin: no está definida la ruta al directorio watch. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Tipo de operación";
 theUILang.autotoolsFileOpMove		= "Mover";
 theUILang.autotoolsFileOpHardLink	= "Enlace fijo";
 theUILang.autotoolsFileOpCopy		= "Copiar";
 theUILang.autotoolsFileOpSoftLink	= "Enlace simbólico";
 theUILang.autotoolsAddLabel		= "Add torrent's label to path";
 theUILang.autotoolsAddName		= "Add torrent's name to path";
 theUILang.autotoolsEnableMove		= "Enable \"AutoMove\" if torrent's label matches filter";
 theUILang.autotoolsSkipMoveForFiles	= "Skip torrents that contain files matching pattern";

thePlugins.get("autotools").langLoaded();