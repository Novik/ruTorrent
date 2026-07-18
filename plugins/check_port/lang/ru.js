/*
 * PLUGIN CHECK_PORT
 *
 * Russian language file.
 *
 * Author:
 */

 theUILang.checkWebsiteNotFound = "Плагин check_port не будет работать: неверные настройки";
 theUILang.checkPort		= "Проверить статус порта";
 theUILang.checkingPort		= "Checking port status";
 theUILang.portStatus		= [
 				  "Статус порта неизвестен",
 				  "Порт закрыт",
 				  "Порт открыт"
 				  ];
 theUILang.notAvailable = "-";

 theUILang.forcePort		= "Задать конкретный порт...";
 theUILang.forcePortPrompt	= "Укажите порт прослушивания (1-65535):";
 theUILang.forcePortInvalid	= "Недопустимый номер порта.";

thePlugins.get("check_port").langLoaded();
