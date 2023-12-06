/*
 * PLUGIN AUTOTOOLS
 *
 * Ukrainian language file.
 *
 * Author: Oleksandr Natalenko (oleksandr@natalenko.name)
 */

 var s_PluginFail			= "Плагін не працюватиме.";

 theUILang.autotools			= "Автоматизація";
 theUILang.autotoolsEnableLabel		= "Увімкнути функцію «АвтоМітки», Шаблон:";
 theUILang.autotoolsPathToFinished	= "Каталог для завершених завантажень";
 theUILang.autotoolsEnableWatch		= "Увімкнути функцію «АвтоДодавання»";
 theUILang.autotoolsPathToWatch		= "Каталог для пошуку нових торентів";
 theUILang.autotoolsWatchStart		= "Автоматично починати завантаження";
 theUILang.autotoolsNoPathToFinished	= "Плагін autotools: каталог для завершених завантажень не задано. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Плагін autotools: базовий каталог для нових торентів не задано. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Тип операції";
 theUILang.autotoolsFileOpMove		= "Перемістити";
 theUILang.autotoolsFileOpHardLink	= "Жорстке посилання";
 theUILang.autotoolsFileOpCopy		= "Скопіювати";
 theUILang.autotoolsFileOpSoftLink	= "Символьне посилання";
 theUILang.autotoolsAddLabel		= "Додавати мітку торента до шляху";
 theUILang.autotoolsAddName		= "Додавати назву торента до шляху";
 theUILang.autotoolsEnableMove		= "Увімкнути \"АвтоПереміщення\", якщо мітка торента відповідає фільтру";
 theUILang.autotoolsSkipMoveForFiles	= "Пропускати торенти, які містять файли, що збігаються із шаблоном";

thePlugins.get("autotools").langLoaded();
