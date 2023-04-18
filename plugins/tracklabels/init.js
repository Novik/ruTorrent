
theWebUI.trackersLabels = {};
plugin.injectedStyles = {};
plugin.loadLang();

plugin.config = theWebUI.config;
theWebUI.config = function()
{
	if(plugin.canChangeColumns())
	{
		theWebUI.tables.trt.columns.push({ text: theUILang.Tracker, width: '100px', id: 'tracker', type: TYPE_STRING});
		plugin.config.call(this);
		plugin.reqId = theRequestManager.addRequest("trk", null, function(hash,tracker,value)
		{
			const domain = theWebUI.getTrackerName( tracker.name );
			tracker.icon = domain ? {src: plugin.imageURI('tracker', domain)} : 'Status_Checking';
		});
	}
}

plugin.isTorrentRowShown = theWebUI.isTorrentRowShown;
theWebUI.isTorrentRowShown = function(table, hash)
{
	return plugin.isTorrentRowShown.call(theWebUI, table, hash) && (
		!plugin.enabled || !(theWebUI.actLbls['ptrackers_cont'] ?? []).length || (
		// check if tracker of hash is selected
		hash in this.trackers
		&& this.trackers[hash]
			.filter(t => Number(t.group) === 0)
			.map(t => theWebUI.getTrackerName( t.name ))
			.some(name => name && plugin.isActiveLabel(name))
	));
}

plugin.isActiveLabel = function(lbl)
{
	return (theWebUI.actLbls['ptrackers_cont'] ?? []).includes('i'+lbl);
}

plugin.addTrackers = theWebUI.addTrackers;
theWebUI.addTrackers = function(data)
{
	plugin.addTrackers.call(theWebUI,data);
	if(plugin.enabled)
		theWebUI.rebuildTrackersLabels();
}

if(!$type(theWebUI.getTrackerName))
{
	theWebUI.getTrackerName = function(announce)
	{
	        var domain = '';
		if(announce)
		{
			var parts = announce.match(/^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/);
			if(parts && (parts.length>6))
			{
				domain = parts[6];
				if(!domain.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/))
				{
					parts = domain.split(".");
					if(parts.length>2)
					{
						if($.inArray(parts[parts.length-2]+"", ["co", "com", "net", "org"])>=0 ||
							$.inArray(parts[parts.length-1]+"", ["uk"])>=0)
							parts = parts.slice(parts.length-3);
						else
							parts = parts.slice(parts.length-2);
						domain = parts.join(".");
					}
				}
			}
		}
		return(domain);
	}
}

plugin.contextMenuEntries = theWebUI.contextMenuEntries;
theWebUI.contextMenuEntries = function(labelType, el) {
	const entries = plugin.contextMenuEntries.call(theWebUI, labelType, el);
	if (plugin.canChangeMenu() && ['ptrackers_cont', 'plabel_cont'].includes(labelType)) {
		const lbl = 'ptrackers_cont' === labelType ? el.id.substr(1) : theWebUI.idToLbl(el.id);
		if (lbl)
			return entries.concat([
				[theUILang.EditIcon, `theWebUI.showTracklabelsDialog('${lbl}');`]
			]);
	}
	return entries;
}


theWebUI.showTracklabelsDialog = function(lbl) {
	$(`#${plugin.dialogId} input[type=text]`).val(lbl);
	theDialogManager.show(plugin.dialogId);
}



plugin.updateLabel = theWebUI.updateLabel;
theWebUI.updateLabel = function(label, ...args)
{
	plugin.updateLabel.call(this, label, ...args);
	var icon = $(label).children('.label-icon');
	var id = icon.parent().attr('id');
	if (id && icon.parents('#plabel_cont')[0] && !icon.children('img')[0])
	{
		var lbl = theWebUI.idToLbl(id);
		icon.append($("<img>")
			.attr({ id: 'lbl_'+lbl, src: plugin.imageURI('label', lbl)}))
			.css({ background: 'none' });
	}
}

plugin.updateLabels = theWebUI.updateLabels;
theWebUI.updateLabels = function(wasRemoved)
{
	if(plugin.enabled)
	{
		if(wasRemoved)
			theWebUI.rebuildTrackersLabels();
		theWebUI.updateAllFilterLabel('torrl', this.settings["webui.show_labelsize"]);
	}
	plugin.updateLabels.call(theWebUI,wasRemoved);
}

theWebUI.rebuildTrackersLabels = function()
{
	if(!plugin.allStuffLoaded)
	{
		setTimeout('theWebUI.rebuildTrackersLabels()',1000);
		return;
	}

	const table = this.getTable('trt');
	const colId = table.getColById('tracker');
	const setTracker = plugin.canChangeColumns()
		? ((hash, trk) => table.setValue(hash, colId, trk))
		: () => {};
	const countByTracker = {};
	const sizeByTracker = {};
	for(const [hash, torrent] of Object.entries(this.torrents))
	{
		const trackerNames = (this.trackers[hash] ?? [])
			.filter(t => t.group == 0)
			.map(t => theWebUI.getTrackerName(t.name))
			.filter(name => Boolean(name));

		const firstName = trackerNames[0] ?? null;
		torrent.tracker = firstName;
		if (firstName)
		{
			setTracker(hash, firstName);
			const size = parseInt(torrent.size);
			new Set(trackerNames).forEach( name => {
				countByTracker[name] = (countByTracker[name] ?? 0) + 1;
				sizeByTracker[name] = (sizeByTracker[name] ?? 0) + size;
			});
		}
	}
	const ul = $("#torrl");
	const lbls = Object.keys(countByTracker);
	lbls.sort();

	let needTableFilter = false;
	for(const lbl of lbls)
	{
		if(!(lbl in this.trackersLabels))
		{
			const labelEl = theWebUI.createSelectableLabelElement('i'+lbl, lbl, theWebUI.labelContextMenu)
				.addClass('tracker');
			labelEl.children('.label-icon')
				.append($('<img>').attr('src', plugin.imageURI('tracker', lbl)))
				.css({ background: 'none' });
			ul.append(labelEl);
		}
		theWebUI.updateLabel($$('i'+lbl), countByTracker[lbl], sizeByTracker[lbl], theWebUI.settings["webui.show_labelsize"]);
		if(plugin.isActiveLabel(lbl)) {
			const actLabel = $($$('i'+lbl));
			if (!actLabel.hasClass('sel')) {
				needTableFilter = true;
				$('#ptrackers_cont').find('.sel').removeClass('sel');
				$(actLabel).addClass("sel");
			}
		}
	}
	if (needTableFilter)
		theWebUI.filterTorrentTable();
	let needSwitch = false;
	for(const lbl in this.trackersLabels)
		if(!(lbl in countByTracker))
		{
			$($$('i'+lbl)).remove();
			if(plugin.isActiveLabel(lbl))
				needSwitch = true;
		}
	this.trackersLabels = countByTracker;
	if(needSwitch)
		theWebUI.resetLabels();

	setTimeout(plugin.refreshTrackerRows, 0);
}

plugin.refreshTrackerRows = function()
{
	if(plugin.canChangeColumns())
	{
		var table = theWebUI.getTable('trt');
		table.refreshRows();
		if(table.sortId)
			table.Sort();
	}
}

plugin.imageURI = function (target, label) {
	return `plugins/tracklabels/action.php?${target}=${encodeURIComponent(label)}`;
}

plugin.onLangLoaded = function()
{
	if ('dialogId' in plugin)
		return;
	const eid = 'tracklabels-dialog'
	plugin.dialogId = eid;
	theDialogManager.make(plugin.dialogId, theUILang.Tracklabels_dialog,
		$('<div>').addClass('cont').append(
		$('<form>').addClass('optionColumn')
		.attr({ enctype: 'multipart/form-data', method: 'post', action: 'javascript:;' })
		.append(...[
			[theUILang.FileUserIcon, 'uploadfile', 'file', { value: '', accept: '.png' }],
			[theUILang.Label, 'label', 'text', { value: '', list: `${eid}-datalist`, class: 'TextboxLarge' }],
		].map(([text, name, type, attribs]) => $('<div>').append(
			$('<label>').attr('for', `${eid}-${name}`).text(text),
			$('<input>').attr({ name, type, id: `${eid}-${name}`, ...attribs })
		)),
			$('<datalist>').attr('id', `${eid}-datalist`),
			$('<div>').addClass('aright buttons-list').attr('style', 'margin-top: 10px')
			.append(...[
				[theUILang.UploadUserIcon, 'submit', 'OK Button', {}],
				[theUILang.DeleteUserIcon, 'button', 'Button', {name: 'delete'}],
				[theUILang.Cancel, 'button', 'Cancel Button', {}],
			].map(([value, type, cls, attribs]) => $('<input>')
				.attr({value, type, class: cls, ...attribs})
			))))[0].outerHTML
	);
	const submitBtn = $(`#${eid} input[type=submit]`);
	const delBtn = $(`#${eid} input[name=delete]`);
	const labelTxt = $(`#${eid}-label`);
	const formEl = $(`#${eid} form`)[0];

	const validFormData = (del) => {
		const label = labelTxt.val();
		const formData = new FormData(formEl);
		const valid = label && (del || formData.get('uploadfile')?.size);
		const trackerTarget = label.includes('.') && !label.includes('/');
		if (valid) {
			if (trackerTarget) {
				formData.delete('label');
				formData.set('tracker', label);
			}
			if (del) {
				formData.delete('uploadfile');
				formData.set('delete', 'on');
			} else {
				formData.set('upload', 'on');
			}
		}
		return valid ? formData : null;
	};

	const updateButtons = () => {
		submitBtn.prop('disabled', !validFormData(false));
		delBtn.prop('disabled', !validFormData(true));
	};
	labelTxt.keyup(updateButtons).change(updateButtons);
	$(`#${eid} input[name=uploadfile]`).change(updateButtons);

	const sendForm = (del) => {
		const formData = validFormData(del);
		const valid = Boolean(formData);
		if (valid) {
			submitBtn.prop('disabled', true);
			const trkTarget = formData.has('tracker');
			const target = trkTarget ? 'tracker' : 'label';
			const label = formData.get(target);
			const uri = plugin.imageURI(target, label);
			formData.delete(target);
			const request = new XMLHttpRequest();
			request.onloadend = () => {
				if (request.status === 200) {
					// hide dialog if upload successful
					theDialogManager.hide(plugin.dialogId);
					// show uploaded image
					const el = $($$((trkTarget ? 'i' : 'lbl_')+label));
					const img = trkTarget ? el.find('img') : el;
					img.attr('src', `${uri}&t=${new Date().getTime()}`);
				} else {
					noty(`Icon edit failed! ${request.response}`, 'error');
				}
				submitBtn.prop('disabled', false);
			};
			// successful POST invalidates cache for URI: see https://www.rfc-editor.org/rfc/rfc7234#section-4.4
			request.open('POST', uri);
			request.send(formData);
		}
		return valid;
	};
	$(`#${eid} form`).submit(() => sendForm(false));
	delBtn.click(() => sendForm(true))

	theDialogManager.setHandler(plugin.dialogId, 'beforeShow', function()
	{
		$(`#${eid}-datalist`).empty().append(
			...Object.keys(theWebUI.cLabels).concat(Object.keys(theWebUI.trackersLabels))
			.map(lbl => $('<option>').attr('value', lbl))
		);
		updateButtons();
	});

	plugin.addPaneToCategory("ptrackers",theUILang.Trackers, 'flabel_cont')
		.append(
			$('<ul>').attr('id', 'torrl')
			.append(theWebUI.createSelectableLabelElement(undefined, theUILang.All, theWebUI.labelContextMenu).addClass('-_-_-all-_-_- sel'))
		);
};

plugin.onRemove = function()
{
	if ('dialogId' in plugin) {
		$(`#${plugin.dialogId}`).remove();
		plugin.dialogId = undefined;
	}
	plugin.removePaneFromCategory('ptrackers');
	theWebUI.resetLabels();
	if(plugin.canChangeColumns())
	{
		theWebUI.getTable("trt").removeColumnById("tracker");
		if(thePlugins.isInstalled("rss"))
			theWebUI.getTable("rss").removeColumnById("tracker");
	}
}

