/* Telegram plugin English strings. */
theUILang.telegram = "Telegram";
theUILang.telegramEnabled = "Enable Telegram notifications";
theUILang.telegramBotToken = "Bot token:";
theUILang.telegramChatId = "Chat ID:";
theUILang.telegramTokenConfigured = "A token is configured. Leave blank to keep it.";
theUILang.telegramTemplates = "Notifications";
theUILang.telegramTemplateHelp = "Available placeholders: {STATE}, {TORRENT}, {HASH}, {LINK}. All defaults use the same structure; Telegram Markdown is enabled and {LINK} uses an HTTP(S) torrent comment.";
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
