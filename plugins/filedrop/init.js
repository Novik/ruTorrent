if(window.FileReader)
	plugin.loadLang();
else
	plugin.disable();

plugin.onLangLoaded = function()
{
	injectScript(plugin.path+"jquery.filedrop.js",function()
	{
		$("#maincont").on("drop", function(event)
		{
			// Handle dropped URLs and dropped text containing URLs.
			// The filedrop jQuery plugin only handles dropped files,
			// not URLs. We have to do it in an event handler that
			// gets called before filedrop's handler.

			const items = [...event.originalEvent.dataTransfer.items];
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
			uriList.getAsString(async data => {
				// add all magnet URLs and http/https URLs, which are
				// assumed to be links to torrent files
				for (let url of data.split(separator)) {
					url = url.trim();
					if ((/^magnet:|^https?:\/\//i).test(url)) {
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
					}
				}
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
	$("#maincont").off('drop').off('dragenter').off('dragover').off('dragleave');
}