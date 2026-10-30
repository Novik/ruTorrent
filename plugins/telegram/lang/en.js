/* Telegram plugin English strings. */
theUILang.telegram = "Telegram";
theUILang.telegramEnabled = "Enable Telegram notifications";
theUILang.telegramBotToken = "Bot token:";
theUILang.telegramChatId = "Chat ID:";
theUILang.telegramTokenConfigured = "A token is configured. Leave blank to keep it.";
theUILang.telegramTokenClear = "Remove the saved token";
theUILang.telegramTemplates = "Notifications";
theUILang.telegramTemplateHelp = "Available placeholders: {STATE}, {TORRENT}, {HASH}, {LINK}. All defaults use the same structure. The text is sent as typed, without Telegram formatting (* or _ are shown as is); {LINK} uses an HTTP(S) torrent comment. An empty template uses the default one.";
theUILang.telegramEventAdded = "Added";
theUILang.telegramEventFinished = "Finished";
theUILang.telegramEventRemoved = "Removed";
theUILang.telegramTest = "Send test message";
theUILang.telegramTestHelp = "Save settings by clicking OK before testing.";
theUILang.telegramSaved = "Telegram settings saved.";
theUILang.telegramSaveFailed = "Telegram settings could not be saved.";
theUILang.telegramTestSucceeded = "Telegram test message sent.";
theUILang.telegramTestFailed = "Telegram test message failed";
theUILang.telegramUnknownError = "Unknown error";

thePlugins.get("telegram").langLoaded();
