/*
 * PLUGIN AUTOTOOLS
 *
 * Hungarian language file.
 *
 * Author: Tiby08
 */

 var s_PluginFail			= "A bővitmény nem működik.";

 theUILang.autotools			= "Auto Beállítás";
 theUILang.autotoolsEnableLabel		= "Engedélyezi az \"Auto címkézés\" funkciót, sablon:";
 theUILang.autotoolsPathToFinished	= "Befejezett letöltési elérési út";
 theUILang.autotoolsEnableWatch		= "Engedélyezi az \"AutoWatch\" funkciót";
 theUILang.autotoolsPathToWatch		= "Watch mappa elérési út";
 theUILang.autotoolsWatchStart		= "Letöltés indítása automatikusan";
 theUILang.autotoolsNoPathToFinished	= "Auto Beállítás bővítmény: a letöltési mappa nincs beállítva. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Auto Beállítás bővítmény: a watch mappa nincs beállítva. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Művelet típusa";
 theUILang.autotoolsFileOpMove		= "Áthelyezés";
 theUILang.autotoolsFileOpHardLink	= "Hard link";
 theUILang.autotoolsFileOpCopy		= "Másolás";
 theUILang.autotoolsFileOpSoftLink	= "Soft link";
 theUILang.autotoolsAddLabel		= "Add torrent's label to path";
 theUILang.autotoolsAddName		= "Add torrent's name to path";
 theUILang.autotoolsEnableMove		= "Enable \"AutoMove\" if torrent's label matches filter";
 theUILang.autotoolsSkipMoveForFiles	= "Skip torrents that contain files matching pattern";

thePlugins.get("autotools").langLoaded();