
theWebUI.trackerNames = [];
theWebUI.torrentTrackerIds = new Map();
plugin.injectedStyles = {};
plugin.iconEditSuffix = {};
plugin.imageEditSuffix = {
	tracker: {},
	label: {},
}
plugin.loadLang();

const catlist = theWebUI.categoryList;
const ptrackersPanelArgs = [
	[['ptrackers_all', {text: theUILang.All, icon: 'all'}]],
	[(hash) => theWebUI.torrentTrackerIds.get(hash) ?? []]
];

const plabelEntries = catlist.refreshPanel.plabel.bind(catlist);
catlist.refreshPanel.plabel = (attribs) => plabelEntries(attribs)
	.map(([labelId, aa]) => [
		labelId,
		aa,
		labelId.startsWith('clabel__') ? labelId.substring(8) : 'nlb'
	])
	.map(([labelId, a, label]) => [
		labelId,
		labelId === 'plabel_all'
		? a
		: {
			...a,
			icon: 'url:'
				+ plugin.imageURI('label', label)
				+ (plugin.imageEditSuffix.label[label] ?? '')

	}]);

catlist.refreshPanel.ptrackers = () => [
	catlist.updatedStatisticEntry('ptrackers', "ptrackers_all"),
	...theWebUI.trackerNames
		.map(tracker => ['i' + tracker, tracker])
		.map(([trackerId, tracker]) => catlist.updatedStatisticEntry('ptrackers', trackerId, {
			icon: 'url:'
				+ plugin.imageURI('tracker', tracker)
				+ (plugin.imageEditSuffix.tracker[tracker] ?? ''),
			text: tracker,
		}))
];

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
			var parts = announce.match(/^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/);
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
		if ($.inArray( domain, plugin.hideTrackers, domain ) != -1) domain = '';
		return(domain);
	}
}

plugin.contextMenuEntries = catlist.contextMenuEntries.bind(catlist);
catlist.contextMenuEntries = function(panelId, labelId) {
	const entries = plugin.contextMenuEntries(panelId, labelId);
	if (plugin.canChangeMenu() && ['ptrackers', 'plabel'].includes(panelId)) {
		if (labelId !== `${panelId}_all`) {
			const lbl = panelId === 'plabel'
				? (labelId.startsWith('clabel__') ? labelId.substring(8) : 'nlb')
				: labelId.substring(1);
			return entries.concat([
				[theUILang.EditIcon, `theWebUI.showTracklabelsDialog('${lbl}');`]
			]);
		}
	}
	return entries;
}


theWebUI.showTracklabelsDialog = function(lbl) {
	$(`#${plugin.dialogId} input[type=text]`).val(lbl);
	theDialogManager.show(plugin.dialogId);
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
	this.torrentTrackerIds.clear();
	for(const [hash, torrent] of Object.entries(this.torrents))
	{
		const trackerNames = (this.trackers[hash] ?? [])
					.filter(t => Number(t.group) === 0)
					.map(t => theWebUI.getTrackerName(t.name))
					.filter(name => Boolean(name));
		this.torrentTrackerIds.set(hash, trackerNames.map(name => 'i' + name));

		const firstName = trackerNames[0] ?? null;
		torrent.tracker = firstName;
		if (plugin.canChangeColumns() && firstName)
		{
			table.setValue(hash, colId, firstName);
		}
	}

	theWebUI.trackerNames = [...new Set([...theWebUI.torrentTrackerIds.values()].flat())]
		.sort()
		.map(labelId => labelId.slice(1))
	catlist.rescan('ptrackers');
	catlist.refreshAndSyncPanel('ptrackers', true);
}

plugin.imageURI = function (target, label) {
	return `plugins/tracklabels/action.php?${target}=${encodeURIComponent(label)}`;
}

plugin.onLangLoaded = function() {
	if ('dialogId' in plugin)
		return;
	const eid = 'tracklabels-dialog'
	plugin.dialogId = eid;
	theDialogManager.make(
		plugin.dialogId, theUILang.Tracklabels_dialog,
		$('<div>').addClass('cont').append(
			$('<form>').attr({ enctype: 'multipart/form-data', method: 'post', action: 'javascript:;' }).append(
				$("<div>").addClass("row").append(
					...[
						[theUILang.FileUserIcon, 'uploadfile', 'file', { value: '', accept: '.png' }],
						[theUILang.Label, 'label', 'text', { value: '', list: `${eid}-datalist` }],
					].map(([text, name, type, attribs]) => [
						$('<div>').addClass("col-12 col-md-3").append(
							$('<label>').addClass("ms-md-auto").attr({for:`${eid}-${name}`}).text(text + ": "),
						),
						$("<div>").addClass("col-12 col-md-9").append(
							$('<input>').attr({name, type, id:`${eid}-${name}`, ...attribs}),
						),
					]),
					$("<div>").addClass("col-12").append(
						$('<datalist>').attr('id', `${eid}-datalist`),
					),
				),
				$('<div>').addClass('buttons-list').append(
					...[
						[theUILang.UploadUserIcon, 'submit', 'OK Button', {}],
						[theUILang.DeleteUserIcon, 'button', 'Button', {name: 'delete'}],
						[theUILang.Cancel, 'button', 'Cancel Button', {}],
					].map(([value, type, cls, attribs]) => $('<input>')
						.attr({value, type, ...attribs})
						.addClass(cls),
					),
				),
			),
		)
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
					plugin.imageEditSuffix[target][label] = `&t=${new Date().getTime()})`;
					if (trkTarget) {
						catlist.refreshPanel.ptrackers();
					} else {
						catlist.refreshPanel.plabel([]);
					}
					theWebUI.update();  // update icons immediately
					catlist.syncFn();
				} else {
					noty(theUILang.EditFailed + ` ${request.response}`, 'error');
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
			...[...catlist.torrentLabelTree.torrentLabels.keys()]
				.concat(theWebUI.trackerNames)
				.map(lbl => $('<option>').attr('value', lbl))
		);
		updateButtons();
	});

	plugin.addPaneToCategory(
		'ptrackers',
		theUILang.Trackers,
		...ptrackersPanelArgs
	);
};

plugin.onRemove = function()
{
	if ('dialogId' in plugin) {
		$(`#${plugin.dialogId}`).remove();
		plugin.dialogId = undefined;
	}
	plugin.removePaneFromCategory('ptrackers');
	theWebUI.categoryList.resetSelection();
	if(plugin.canChangeColumns())
	{
		theWebUI.getTable("trt").removeColumnById("tracker");
		if(thePlugins.isInstalled("rss"))
			theWebUI.getTable("rss").removeColumnById("tracker");
	}
}

