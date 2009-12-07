

 var s_NoAccess		= "Плагин autotools: у пользователя rTorrent нет доступа к ";
 var s_PluginFail	= "Плагин не будет работать.";

 WUILang.autotools			= "Автоматизация";
 WUILang.autotoolsEnableLabel		= "Включить функцию \"АвтоМетки\"";
 WUILang.autotoolsEnableMove		= "Включить функцию \"АвтоПеремещение\"";
 WUILang.autotoolsPathToFinished	= "Каталог для завершенных закачек";
 WUILang.autotoolsEnableWatch		= "Включить функцию \"АвтоДобавление\"";
 WUILang.autotoolsPathToWatch		= "Каталог для поиска новых торрентов";
 WUILang.autotoolsPHPNotFound		= s_NoAccess + " интерпретатору php. " + s_PluginFail;
 WUILang.autotoolsLabelShNotAvailable	= s_NoAccess + " к файлу plugins/autotools/label.sh на чтение/выполнение. " + s_PluginFail;
 WUILang.autotoolsLabelPhpNotAvailable	= s_NoAccess + " к файлу plugins/autotools/label.php на чтение. " + s_PluginFail;
 WUILang.autotoolsMoveShNotAvailable	= s_NoAccess + " к файлу plugins/autotools/move.sh на чтение/выполнение. " + s_PluginFail;
 WUILang.autotoolsMovePhpNotAvailable	= s_NoAccess + " к файлу plugins/autotools/move.php на чтение. " + s_PluginFail;
 WUILang.autotoolsWatchPhpNotAvailable	= s_NoAccess + " к файлу plugins/autotools/watch.php на чтение. " + s_PluginFail;
 WUILang.autotoolsNoPathToFinished	= "Плагин autotools: не задан каталог для завершенных закачек. " + s_PluginFail;
 WUILang.autotoolsNoPathToWatch		= "Плагин autotools: не задан базовый каталог для новых торрентов. " + s_PluginFail;

utWebUI.autotoolsCreate();

