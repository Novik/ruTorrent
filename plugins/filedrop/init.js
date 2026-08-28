if(window.FileReader)
	plugin.loadLang();
else
	plugin.disable();

plugin.isEditableTarget = function(el)
{
	if (!el)
		return false;
	const tag = el.tagName;
	return (tag == 'INPUT') || (tag == 'TEXTAREA') || el.isContentEditable;
}

plugin.addUrls = async function(urls)
{
	// break URL list into chunks based on plugin configuration
	const urlChunks = [];
	if (plugin.queuefiles) {
		for (let i = 0; i < urls.length; i += plugin.queuefiles)
			urlChunks.push(urls.slice(i, i + plugin.queuefiles));
	}
	else {
		if (plugin.maxfiles && urls.length > plugin.maxfiles) {
			noty(theUILang.tooManyFiles + plugin.maxfiles, "error");
			return;
		}
		urlChunks.push(urls);  // all URLs in one chunk
	}

	// send API calls to rTorrent one chunk at a time with
	// delay between chunks
	for (const [i, urls] of urlChunks.entries()) {
		if (i != 0)
			await new Promise(r => setTimeout(r, 200));

		await Promise.all(urls.map(async url => {
			const data = await $.ajax({
				url: plugin.path + '../../php/addtorrent.php',
				method: "POST",
				data: { url, json: 1 },
				dataType: "json",
			}).catch((_xhr, status, error) => {
				console.error(
					"filedrop: rTorrent API call error: " +
					"url = %s | response status = %s | error = %o",
					url, status, error);
				return {};
			});

			const result = (typeof data === "object")
				? (data.result ?? "Failed")
				: "Failed";
			noty(
				`${url} : ${theUILang['addTorrent' + result]}`,
				(result == "Success") ? "success" : "error");
		}));
	}
}

plugin.handlePaste = function(event)
{
	// Handle a magnet link or torrent URL pasted anywhere on the page
	// (outside of a text field), the same way a drop is handled.
	if (plugin.isEditableTarget(event.target))
		return;

	const cd = event.clipboardData || window.clipboardData;
	if (!cd)
		return;

	const text = cd.getData('text/plain') || cd.getData('text');
	if (!text)
		return;

	const urls = text.split(/\r\n|\n|\r|\s+/)
		.map(item => item.trim())
		.filter(item => (/^magnet:|^https?:\/\//i).test(item));
	if (!urls.length)
		return;

	event.preventDefault();
	plugin.addUrls(urls);
}

plugin.onLangLoaded = function()
{
	document.addEventListener("paste", plugin.handlePaste);

	injectScript(plugin.path+"jquery.filedrop.js",function()
	{
		$("#maincont").on("drop", function(event)
		{
			// Handle dropped URLs and dropped text containing URLs.
			// The filedrop jQuery plugin only handles dropped files,
			// not URLs. We have to do it in an event handler that
			// gets called before filedrop's handler.

			const items = [...event.originalEvent.dataTransfer.items];
			// If a file is present, let the filedrop plugin handle it
			const hasFile = items.some(item => item.kind === "file");
			if (hasFile)
				return true;
			// find text/uri-list; if not found, find text/plain
			const uriList = items.find(item =>
				item.kind === "string" &&
				(/^text\/uri-list(?![a-z-])/i).test(item.type)
			) ?? items.find(item =>
				item.kind === "string" &&
				(/^text\/plain(?![a-z-])/i).test(item.type)
			);
			if (uriList == null)
				return true;  // not URL list; let filedrop handle this

			const separator = (/^text\/uri-list/i).test(uriList.type)
				? /\r\n|\n|\r/  // line split text/uri-list
				: /\s+/;        // word split text/plain
			uriList.getAsString(data => {
				// find all magnet URLs and http/https URLs, which are
				// assumed to be links to torrent files
				const urls = data.split(separator)
					.map(item => item.trim())
					.filter(item => (/^magnet:|^https?:\/\//i).test(item));
				plugin.addUrls(urls);
			});

			// don't run filedrop event handler
			event.stopImmediatePropagation();
			return false;

		}).filedrop(
		{
//			fallback_id:	'torrent_file',
			paramname:	'torrent_file',
			maxfiles: 	plugin.maxfiles,
			maxfilesize: 	plugin.maxfilesize,
			queuefiles: 	plugin.queuefiles,
			url: 		plugin.path+'../../php/addtorrent.php',
			data:
			{
				json: 	1
			},

			uploadFinished: function(i, file, response, time)
			{
				noty(file.name+' : '+ theUILang['addTorrent'+response.result], (response.result=='Success') ? "success" : "error");
			},

			beforeEach: function(file)
			{
				return(file.name.match(".torrent")!=null);
			},

			error: function(err, file)
			{
				switch(err)
				{
					case 'BrowserNotSupported':
					{
						plugin.remove();
						noty(theUILang.doesntSupportHTML5,"error");
						break;
					}
					case 'TooManyFiles':
					{
						noty(theUILang.tooManyFiles+plugin.maxfiles,"error");
						break;
					}
					case 'FileTooLarge':
					{
						noty(file.name+' '+theUILang.fileTooLarge+' '+plugin.maxfilesize+theUILang.MB,"error");
						break;
					}
					default:
						break;
				}
			}
		});
	});
}

plugin.onRemove = function()
{
	document.removeEventListener("paste", plugin.handlePaste);
	$("#maincont").off('drop').off('dragenter').off('dragover').off('dragleave');
}
