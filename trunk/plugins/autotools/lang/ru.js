

 var s_NoAccess		= "Плагин autotools: у пользователя rTorrent нет доступа к ";
 var s_PluginFail	= "Плагин не будет работать.";

 theUILang.autotools			= "Автоматизация";
 theUILang.autotoolsEnableLabel		= "Включить функцию \"АвтоМетки\", Шаблон:";
 theUILang.autotoolsEnableMove		= "Включить функцию \"АвтоПеремещение\"";
 theUILang.autotoolsPathToFinished	= "Каталог для завершенных закачек";
 theUILang.autotoolsEnableWatch		= "Включить функцию \"АвтоДобавление\"";
 theUILang.autotoolsPathToWatch		= "Каталог для поиска новых торрентов";
 theUILang.autotoolsWatchStart		= "Начинать закачку автоматически";
 theUILang.autotoolsPHPNotFound		= s_NoAccess + " интерпретатору php. " + s_PluginFail;
 theUILang.autotoolsLabelShNotAvailable	= s_NoAccess + " к файлу plugins/autotools/label.sh на чтение/выполнение. " + s_PluginFail;
 theUILang.autotoolsLabelPhpNotAvailable	= s_NoAccess + " к файлу plugins/autotools/label.php на чтение. " + s_PluginFail;
 theUILang.autotoolsMoveShNotAvailable	= s_NoAccess + " к файлу plugins/autotools/move.sh на чтение/выполнение. " + s_PluginFail;
 theUILang.autotoolsMovePhpNotAvailable	= s_NoAccess + " к файлу plugins/autotools/move.php на чтение. " + s_PluginFail;
 theUILang.autotoolsWatchPhpNotAvailable	= s_NoAccess + " к файлу plugins/autotools/watch.php на чтение. " + s_PluginFail;
 theUILang.autotoolsNoPathToFinished	= "Плагин autotools: не задан каталог для завершенных закачек. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch		= "Плагин autotools: не задан базовый каталог для новых торрентов. " + s_PluginFail;

thePlugins.get("autotools").langLoaded();