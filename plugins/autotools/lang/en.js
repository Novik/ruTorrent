/*
 * PLUGIN AUTOTOOLS
 *
 * English language file.
 *
 * Author:
 */

 var s_PluginFail			= "The plugin will not work.";

 theUILang.autotools			= "Autotools";
 theUILang.autotoolsEnableLabel		= "Enable the \"AutoLabel\" feature, Template:";
 theUILang.autotoolsPathToFinished	= "Path to finished downloads";
 theUILang.autotoolsEnableWatch		= "Enable the \"AutoWatch\" feature";
 theUILang.autotoolsPathToWatch		= "Path to the base watch directory";
 theUILang.autotoolsWatchStart		= "Start the download automatically";
 theUILang.autotoolsNoPathToFinished	= "Autotools plugin: The path to finished downloads is not set. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Autotools plugin: The path to the base watch directory is not set. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Operation type";
 theUILang.autotoolsFileOpMove		= "Move";
 theUILang.autotoolsFileOpHardLink	= "Hard link";
 theUILang.autotoolsFileOpCopy		= "Copy";
 theUILang.autotoolsFileOpSoftLink	= "Soft link";
 theUILang.autotoolsAddLabel		= "Add the torrent's label to the path";
 theUILang.autotoolsAddName		= "Add the torrent's name to the path";
 theUILang.autotoolsEnableMove		= "Enable \"AutoMove\" if the torrent's label matches the filter";
 theUILang.autotoolsSkipMoveForFiles	= "Skip torrents that contain files matching the pattern";

thePlugins.get("autotools").langLoaded();
