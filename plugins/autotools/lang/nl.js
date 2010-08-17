

 var s_NoAccess		= "Autotools plugin: rTorrent user heeft geen toegang ";
 var s_PluginFail		= "De plugin zal niet werken.";

 theUILang.autotools				= "Autotools";
 theUILang.autotoolsEnableLabel		= "Aanzetten \"AutoLabel\" feature, Template:";
 theUILang.autotoolsEnableMove		= "Aanzetten \"AutoVerplaats\" feature";
 theUILang.autotoolsPathToFinished		= "Pad naar voltooide downloads";
 theUILang.autotoolsEnableWatch		= "Aanzetten \"AutoWatch\" feature";
 theUILang.autotoolsPathToWatch		= "Pad naar watch folder";
 theUILang.autotoolsWatchStart		= "Start download automatisch";
 theUILang.autotoolsPHPNotFound		= s_NoAccess + "php interpreter. " + s_PluginFail;
 theUILang.autotoolsLabelShNotAvailable	= s_NoAccess + "file plugins/autotools/label.sh for read/execute. " + s_PluginFail;
 theUILang.autotoolsLabelPhpNotAvailable	= s_NoAccess + "file plugins/autotools/label.php for read. " + s_PluginFail;
 theUILang.autotoolsMoveShNotAvailable	= s_NoAccess + "file plugins/autotools/move.sh for read/execute. " + s_PluginFail;
 theUILang.autotoolsMovePhpNotAvailable	= s_NoAccess + "file plugins/autotools/move.php for read. " + s_PluginFail;
 theUILang.autotoolsWatchPhpNotAvailable	= s_NoAccess + "file plugins/autotools/watch.php for read. " + s_PluginFail;
 theUILang.autotoolsNoPathToFinished	= "Autotools plugin: pad naar voltooide downloads folder is niet ingevuld. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch		= "Autotools plugin: pad naar watch folder is niet ingevuld. " + s_PluginFail;

thePlugins.get("autotools").langLoaded();