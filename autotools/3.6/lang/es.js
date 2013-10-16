/*
 * PLUGIN AUTOTOOLS
 *
 * Spanish language file.
 *
 * Author: 
 */

 var s_PluginFail			= "El Plugin no funcionará.";

 theUILang.autotools			= "Autotools";
 theUILang.autotoolsEnableLabel 	= "Habilitar \"AutoLabel\", Template:";
 theUILang.autotoolsEnableMove		= "Habilitar \"AutoMove\" ";
 theUILang.autotoolsPathToFinished	= "Ruta para las descargas finalizadas";
 theUILang.autotoolsEnableWatch 	= "Habilitar \"AutoWatch\" ";
 theUILang.autotoolsPathToWatch 	= "Ruta al directorio watch";
 theUILang.autotoolsWatchStart		= "Comenzar descargas automáticamentes";
 theUILang.autotoolsNoPathToFinished	= "Autotools plugin: no está definida la ruta de descargas. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Autotools plugin: no está definida la ruta al directorio watch. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Tipo de operación";
 theUILang.autotoolsFileOpMove		= "Mover";
 theUILang.autotoolsFileOpHardLink 	= "Enlace fijo";
 theUILang.autotoolsFileOpCopy		= "Copiar";
 theUILang.autotoolsFileOpSoftLink	= "Enlace simbólico";

thePlugins.get("autotools").langLoaded();