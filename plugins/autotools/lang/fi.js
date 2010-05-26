

 var s_NoAccess		= "Autotools plugin: rTorrent's user can't access ";
 var s_PluginFail	= "Plugin will not work.";

 theUILang.autotools			= "Autotools";
 theUILang.autotoolsEnableLabel		= "Enable \"AutoLabel\" feature, Template:";
 theUILang.autotoolsEnableMove		= "Enable \"AutoMove\" feature";
 theUILang.autotoolsPathToFinished	= "Path to finished downloads";
 theUILang.autotoolsEnableWatch		= "Enable \"AutoWatch\" feature";
 theUILang.autotoolsPathToWatch		= "Path to base watch directory";
 theUILang.autotoolsWatchStart		= "Start download automatically";
 theUILang.autotoolsPHPNotFound		= s_NoAccess + "php interpreter. " + s_PluginFail;
 theUILang.autotoolsLabelShNotAvailable	= s_NoAccess + "file plugins/autotools/label.sh for read/execute. " + s_PluginFail;
 theUILang.autotoolsLabelPhpNotAvailable	= s_NoAccess + "file plugins/autotools/label.php for read. " + s_PluginFail;
 theUILang.autotoolsMoveShNotAvailable	= s_NoAccess + "file plugins/autotools/move.sh for read/execute. " + s_PluginFail;
 theUILang.autotoolsMovePhpNotAvailable	= s_NoAccess + "file plugins/autotools/move.php for read. " + s_PluginFail;
 theUILang.autotoolsWatchPhpNotAvailable	= s_NoAccess + "file plugins/autotools/watch.php for read. " + s_PluginFail;
 theUILang.autotoolsNoPathToFinished	= "Autotools plugin: path to finished downloads is not set. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch		= "Autotools plugin: path to base watch directory is not set. " + s_PluginFail;

thePlugins.get("autotools").langLoaded();