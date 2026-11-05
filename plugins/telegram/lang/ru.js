/* Строки плагина Telegram на русском языке. */
theUILang.telegram = "Telegram";
theUILang.telegramEnabled = "Включить уведомления Telegram";
theUILang.telegramBotToken = "Токен бота:";
theUILang.telegramChatId = "ID чата:";
theUILang.telegramTokenConfigured = "Токен настроен. Оставьте поле пустым, чтобы сохранить его.";
theUILang.telegramTokenClear = "Удалить сохранённый токен";
theUILang.telegramTemplates = "Уведомления";
theUILang.telegramTemplateHelp = "Доступные подстановки: {STATE}, {TORRENT}, {HASH}, {LINK}. Все шаблоны используют одну структуру. Текст отправляется как есть, без форматирования Telegram (* или _ отображаются как введены); {LINK} добавляет HTTP(S)-ссылку из комментария торрента. Пустой шаблон заменяется шаблоном по умолчанию.";
theUILang.telegramEventAdded = "Добавлен";
theUILang.telegramEventFinished = "Завершён";
theUILang.telegramEventRemoved = "Удалён";
theUILang.telegramTest = "Отправить тестовое сообщение";
theUILang.telegramTestHelp = "Перед тестированием сохраните настройки, нажав OK.";
theUILang.telegramSaved = "Настройки Telegram сохранены.";
theUILang.telegramSaveFailed = "Не удалось сохранить настройки Telegram.";
theUILang.telegramTestSucceeded = "Тестовое сообщение Telegram отправлено.";
theUILang.telegramTestFailed = "Не удалось отправить тестовое сообщение Telegram";
theUILang.telegramUnknownError = "Неизвестная ошибка";

thePlugins.get("telegram").langLoaded();
