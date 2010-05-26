

 var s_NoAccess		= "Wtyczka Autotools: użytkownik, który uruchomił rTorrenta nie ma praw do";
 var s_PluginFail	= "Wtyczka nie będzie działać.";

 theUILang.autotools			= "Autotools";
 theUILang.autotoolsEnableLabel		= "Włącz funkcję \"AutoEtykieta\", Template: ";
 theUILang.autotoolsEnableMove		= "Włącz funkcję \"AutoPrzenoszenie\" ";
 theUILang.autotoolsPathToFinished	= "Ścieżka dla ukończonych pobierań";
 theUILang.autotoolsEnableWatch		= "Enable \"AutoWatch\" feature";
 theUILang.autotoolsPathToWatch		= "Path to base watch directory";
 theUILang.autotoolsWatchStart		= "Start download automatically";
 theUILang.autotoolsPHPNotFound		= s_NoAccess + "interpretera PHP . " + s_PluginFail;
 theUILang.autotoolsLabelShNotAvailable	= s_NoAccess + "odczytu i wykonywania pliku plugins/autotools/label.sh . " + s_PluginFail;
 theUILang.autotoolsLabelPhpNotAvailable	= s_NoAccess + "odczytu pliku plugins/autotools/label.php . " + s_PluginFail;
 theUILang.autotoolsMoveShNotAvailable	= s_NoAccess + "odczytu i wykonywania pliku plugins/autotools/move.sh . " + s_PluginFail;
 theUILang.autotoolsMovePhpNotAvailable	= s_NoAccess + "odczytu pliku plugins/autotools/move.php . " + s_PluginFail;
 theUILang.autotoolsWatchPhpNotAvailable	= s_NoAccess + "file plugins/autotools/watch.php for read. " + s_PluginFail;
 theUILang.autotoolsNoPathToFinished	= "Wtyczka Autotools: ścieżka dla ukończonych pobierań nie jest skonfigurowana. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch		= "Autotools plugin: path to base watch directory is not set. " + s_PluginFail;

thePlugins.get("autotools").langLoaded();