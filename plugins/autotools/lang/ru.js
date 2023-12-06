/*
 * PLUGIN AUTOTOOLS
 *
 * Russian language file.
 *
 * Author: 
 */

 var s_PluginFail			= "Плагин не будет работать.";

 theUILang.autotools			= "Автоматизация";
 theUILang.autotoolsEnableLabel		= "Включить функцию \"АвтоМетки\", Шаблон:";
 theUILang.autotoolsPathToFinished	= "Каталог для завершенных закачек";
 theUILang.autotoolsEnableWatch		= "Включить функцию \"АвтоДобавление\"";
 theUILang.autotoolsPathToWatch		= "Каталог для поиска новых торрентов";
 theUILang.autotoolsWatchStart		= "Начинать закачку автоматически";
 theUILang.autotoolsNoPathToFinished	= "Плагин autotools: не задан каталог для завершенных закачек. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Плагин autotools: не задан базовый каталог для новых торрентов. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Тип операции";
 theUILang.autotoolsFileOpMove		= "Переместить";
 theUILang.autotoolsFileOpHardLink	= "Жесткая ссылка";
 theUILang.autotoolsFileOpCopy		= "Скопировать";
 theUILang.autotoolsFileOpSoftLink	= "Мягкая ссылка";
 theUILang.autotoolsAddLabel		= "Добавлять к пути метку торрента";
 theUILang.autotoolsAddName		= "Добавлять к пути имя торрента";
 theUILang.autotoolsEnableMove		= "Включить \"АвтоПеремещение\", если метка торрента соответствует";
 theUILang.autotoolsSkipMoveForFiles	= "Пропускать торренты, которые содержат файлы, совпадающие с шаблоном";

thePlugins.get("autotools").langLoaded();