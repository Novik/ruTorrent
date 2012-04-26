if(window.FileReader)
	plugin.loadLang();
else
	plugin.disable();

plugin.onLangLoaded = function()
{
	injectScript(plugin.path+"/jquery.filedrop.js",function()
	{
		$("#maincont").filedrop(
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
				log(file.name+' : '+ theUILang['addTorrent'+response.result]);
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
						log(theUILang.doesntSupportHTML5);
						break;
					}
					case 'TooManyFiles':
					{
						log(theUILang.tooManyFiles+plugin.maxfiles);
						break;
					}
					case 'FileTooLarge':
					{
						log(file.name+' '+theUILang.fileTooLarge+' '+plugin.maxfilesize+theUILang.MB);
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
	$("#maincont").unbind('drop').unbind('dragenter').unbind('dragover').unbind('dragleave');
}