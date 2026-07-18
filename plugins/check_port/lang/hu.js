/*
 * PLUGIN CHECK_PORT
 *
 * Hungarian language file.
 *
 * Author:
 */

 theUILang.checkWebsiteNotFound = "Check_port bővítmény: Bővítmény nem fog működni. Érvénytelen konfiguráció";
 theUILang.checkPort		= "Port állapotának ellenőrzése";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Port állapota ismeretlen",
 				  "Port zárva van",
 				  "Port nyitva van"
 				  ];
 theUILang.notAvailable = "-";

 theUILang.forcePort		= "Adott port kényszerítése...";
 theUILang.forcePortPrompt	= "Figyelő port beállítása (1-65535):";
 theUILang.forcePortInvalid	= "Érvénytelen portszám.";

thePlugins.get("check_port").langLoaded();
