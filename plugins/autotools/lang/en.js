

 var s_NoAccess		= "Autotools plugin: rTorrent's user can't access ";
 var s_PluginFail	= "Plugin will not work.";

 WUILang.autotools			= "Autotools";
 WUILang.autotoolsEnableLabel		= "Enable \"AutoLabel\" feature";
 WUILang.autotoolsEnableMove		= "Enable \"AutoMove\" feature";
 WUILang.autotoolsPathToFinished	= "Path to finished downloads";
 WUILang.autotoolsEnableWatch		= "Enable \"AutoWatch\" feature";
 WUILang.autotoolsPathToWatch		= "Path to base watch directory";
 WUILang.autotoolsPHPNotFound		= s_NoAccess + "php interpreter. " + s_PluginFail;
 WUILang.autotoolsLabelShNotAvailable	= s_NoAccess + "file plugins/autotools/label.sh for read/execute. " + s_PluginFail;
 WUILang.autotoolsLabelPhpNotAvailable	= s_NoAccess + "file plugins/autotools/label.php for read. " + s_PluginFail;
 WUILang.autotoolsMoveShNotAvailable	= s_NoAccess + "file plugins/autotools/move.sh for read/execute. " + s_PluginFail;
 WUILang.autotoolsMovePhpNotAvailable	= s_NoAccess + "file plugins/autotools/move.php for read. " + s_PluginFail;
 WUILang.autotoolsNoPathToFinished	= "Autotools plugin: path to finished downloads is not set. " + s_PluginFail;

utWebUI.autotoolsCreate();

