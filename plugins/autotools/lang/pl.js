/*
 * PLUGIN AUTOTOOLS
 *
 * Polish language file.
 *
 * Author: Dare (piczok@gmail.com)
 */

 var s_PluginFail			= "Wtyczka nie będzie działać.";

 theUILang.autotools			= "Autotools";
 theUILang.autotoolsEnableLabel		= "AutoLabel użyj szablonu:";
 theUILang.autotoolsPathToFinished	= "Ścieżka dla ukończonych pobierań";
 theUILang.autotoolsEnableWatch		= "AutoWatch";
 theUILang.autotoolsPathToWatch		= "Ścieżka do obserwowanego katalogu";
 theUILang.autotoolsWatchStart		= "Uruchom pobieranie automatycznie";
 theUILang.autotoolsNoPathToFinished	= "Wtyczka autotools: Ścieżka dla ukończonych pobierań nie jest skonfigurowana. " + s_PluginFail;
 theUILang.autotoolsNoPathToWatch	= "Wtyczka autotools: Ścieżka do obserwowanego katalogu nie jest skonfigurowana. " + s_PluginFail;
 theUILang.autotoolsFileOpType		= "Rodzaj operacji";
 theUILang.autotoolsFileOpMove		= "Przenieś";
 theUILang.autotoolsFileOpHardLink	= "Hard link";
 theUILang.autotoolsFileOpCopy		= "Kopiuj";
 theUILang.autotoolsFileOpSoftLink	= "Soft link";
 theUILang.autotoolsAddLabel		= "Dodaj etykietę torrenta do ścieżki";
 theUILang.autotoolsAddName		= "Dodaj nazwę torrenta do ścieżki";
 theUILang.autotoolsEnableMove		= "AutoMove jeśli etykieta pasuje do wzorca";
 theUILang.autotoolsSkipMoveForFiles	= "Pomiń jeśli torrent zawiera pliki pasujące do wzorca";

thePlugins.get("autotools").langLoaded();