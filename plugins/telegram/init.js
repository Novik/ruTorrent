plugin.loadLang();

if (plugin.canChangeOptions()) {
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) {
		if (plugin.enabled && theWebUI.telegram) {
			$('#telegram_enabled').prop('checked', !!theWebUI.telegram.enabled);
			$('#telegram_chat_id').val(theWebUI.telegram.chatId || '');
			$('#telegram_token').val('');
			$('#telegram_token_status').text(theWebUI.telegram.tokenConfigured ? theUILang.telegramTokenConfigured : '');
			Object.keys(theWebUI.telegram.events || {}).forEach((event) => {
				$('#telegram_event_' + event).prop('checked', !!theWebUI.telegram.events[event]);
				$('#telegram_template_' + event).val((theWebUI.telegram.templates || {})[event] || '');
			});
		}
		plugin.addAndShowSettings.call(theWebUI, arg);
	};

	theWebUI.telegramWasChanged = function() {
		const cfg = theWebUI.telegram || {};
		if ($('#telegram_enabled').prop('checked') !== !!cfg.enabled) return true;
		if ($('#telegram_chat_id').val() !== (cfg.chatId || '')) return true;
		if ($('#telegram_token').val() !== '') return true;
		for (const event of ['added', 'finished', 'removed']) {
			if ($('#telegram_event_' + event).prop('checked') !== !!(cfg.events || {})[event]) return true;
			if ($('#telegram_template_' + event).val() !== ((cfg.templates || {})[event] || '')) return true;
		}
		return false;
	};

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() {
		plugin.setSettings.call(this);
		if (plugin.enabled && this.telegramWasChanged())
			this.request('?action=settelegram');
	};

	rTorrentStub.prototype.settelegram = function() {
		let content = 'enabled=' + ($('#telegram_enabled').prop('checked') ? '1' : '0');
		const token = $('#telegram_token').val();
		if (token) content += '&token=' + encodeURIComponent(token);
		content += '&chat_id=' + encodeURIComponent($('#telegram_chat_id').val());
		for (const event of ['added', 'finished', 'removed']) {
			content += '&event_' + event + '=' + ($('#telegram_event_' + event).prop('checked') ? '1' : '0');
			content += '&template_' + event + '=' + encodeURIComponent($('#telegram_template_' + event).val());
		}
		this.content = content;
		this.contentType = 'application/x-www-form-urlencoded';
		this.mountPoint = 'plugins/telegram/action.php?mode=settelegram';
		this.dataType = 'script';
	};

	rTorrentStub.prototype.testtelegram = function() {
		this.content = 'test=1';
		this.contentType = 'application/x-www-form-urlencoded';
		this.mountPoint = 'plugins/telegram/action.php?mode=testtelegram';
		this.dataType = 'json';
	};

	plugin.testTelegram = function() {
		theWebUI.request('?action=testtelegram', [plugin.testTelegramResponse, plugin]);
	};

	plugin.testTelegramResponse = function(result) {
		if (result && result.ok)
			noty(theUILang.telegramTestSucceeded, 'info');
		else
			noty(theUILang.telegramTestFailed + ': ' + ((result && result.error) || theUILang.telegramUnknownError), 'error');
	};
}

plugin.onLangLoaded = function() {
	if (!this.canChangeOptions()) return;
	const events = ['added', 'finished', 'removed'];
	const eventFields = events.map((event) => $('<div>').addClass('row').append(
		$('<div>').addClass('col-12 col-md-3').append(
			$('<input>').attr({type: 'checkbox', id: 'telegram_event_' + event}),
			$('<label>').attr({for: 'telegram_event_' + event}).text(theUILang['telegramEvent' + event[0].toUpperCase() + event.slice(1)])
		),
		$('<div>').addClass('col-12 col-md-9').append(
			$('<textarea>').attr({id: 'telegram_template_' + event, rows: 3, maxlength: 4096})
		)
	));
	const page = $('<div>').attr({id: 'st_telegram'}).append(
		$('<fieldset>').append(
			$('<legend>').text(theUILang.telegram),
			$('<div>').addClass('row').append(
				$('<div>').addClass('col-12 checkbox').append(
					$('<input>').attr({type: 'checkbox', id: 'telegram_enabled'}),
					$('<label>').attr({for: 'telegram_enabled'}).text(theUILang.telegramEnabled)
				)
			),
			$('<div>').addClass('row').append(
				$('<div>').addClass('col-12 col-md-3').append($('<label>').attr({for: 'telegram_token'}).text(theUILang.telegramBotToken)),
				$('<div>').addClass('col-12 col-md-5').append(
					$('<input>').attr({type: 'password', id: 'telegram_token', autocomplete: 'new-password', maxlength: 256})
				)
			),
			$('<div>').addClass('row').append(
				$('<div>').addClass('col-12 col-md-3'),
				$('<div>').addClass('col-12 col-md-5').append(
					$('<small>').attr({id: 'telegram_token_status'}).addClass('form-text').css({color: 'var(--text-color, inherit)'})
				)
			),
			$('<div>').addClass('row').append(
				$('<div>').addClass('col-12 col-md-3').append($('<label>').attr({for: 'telegram_chat_id'}).text(theUILang.telegramChatId)),
				$('<div>').addClass('col-12 col-md-5').append($('<input>').attr({type: 'text', id: 'telegram_chat_id', maxlength: 256}))
			),
			$('<div>').addClass('row').append(
				$('<div>').addClass('col-12').append(
					$('<button>').attr({type: 'button'}).addClass('OK').css({width: 'max-content', minWidth: '180px', whiteSpace: 'nowrap', opacity: 1, filter: 'none', cursor: 'pointer'}).on('click', () => { plugin.testTelegram(); return false; }).text(theUILang.telegramTest),
					$('<small>').addClass('form-text').css({color: 'var(--text-color, inherit)'}).text(theUILang.telegramTestHelp)
				)
			)
		),
		$('<fieldset>').append(
			$('<legend>').text(theUILang.telegramTemplates),
			$('<div>').addClass('row').append(
				$('<div>').addClass('col-12').append($('<small>').text(theUILang.telegramTemplateHelp))
			),
			...eventFields
		)
	);
	this.attachPageToOptions(page[0], theUILang.telegram);
};

plugin.onRemove = function() {
	this.removePageFromOptions('st_telegram');
};
