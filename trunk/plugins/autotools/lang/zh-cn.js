

 var s_NoAccess		= "Autotools plugin: rTorrent's user can't access ";
 var s_PluginFail	= "Plugin will not work.";

 theUILang.autotools			= "自动工具";
 theUILang.autotoolsEnableLabel		= "启用 \"自动标签\" 功能, 模板:";
 theUILang.autotoolsEnableMove		= "启用 \"自动移动\" 功能";
 theUILang.autotoolsPathToFinished	= "下载完成路径";
 theUILang.autotoolsEnableWatch		= "启用 \"自动监视\" 功能";
 theUILang.autotoolsPathToWatch		= "监视路径";
 theUILang.autotoolsWatchStart		= "自动开始下载";
 theUILang.autotoolsPHPNotFound		= s_NoAccess + "php interpreter. " + s_PluginFail;
 theUILang.autotoolsLabelShNotAvailable	= s_NoAccess + "file plugins/autotools/label.sh for read/execute. " + s_PluginFail;
 theUILang.autotoolsLabelPhpNotAvailable	= s_NoAccess + "file plugins/autotools/label.php for read. " + s_PluginFail;
 theUILang.autotoolsMoveShNotAvailable	= s_NoAccess + "file plugins/autotools/move.sh for read/execute. " + s_PluginFail;
 theUILang.autotoolsMovePhpNotAvailable	= s_NoAccess + "file plugins/autotools/move.php for read. " + s_PluginFail;
 theUILang.autotoolsWatchPhpNotAvailable	= s_NoAccess + "file plugins/autotools/watch.php for read. " + s_PluginFail;
 theUILang.autotoolsNoPathToFinished	= "Autotools plugin: path to finished downloads is not set. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch		= "Autotools plugin: path to base watch directory is not set. " + s_PluginFail;

thePlugins.get("autotools").langLoaded();