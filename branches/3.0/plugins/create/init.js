plugin.loadLang();
plugin.loadMainCSS();
if(browser.isKonqueror)
	plugin.loadCSS("konqueror");

plugin.onLangLoaded = function()
{
	if(this.enabled)
	{
		this.addButtonToToolbar("create",WUILang.mnu_create,"theDialogManager.toggle('tcreate')","remove");
		this.addSeparatorToToolbar("remove");

		var pieceSize = 
			"<label>"+WUILang.PieceSize+": </label>"+
			"<select id='piece_size' name='piece_size'>"+
				"<option value=\"32\">32"+WUILang.KB+"</option>"+
				"<option value=\"64\">64"+WUILang.KB+"</option>"+
				"<option value=\"128\">128"+WUILang.KB+"</option>"+
				"<option value=\"256\" selected=\"selected\">256"+WUILang.KB+"</option>"+
				"<option value=\"512\">512"+WUILang.KB+"</option>"+
				"<option value=\"1024\">1"+WUILang.MB+"</option>"+
				"<option value=\"2048\">2"+WUILang.MB+"</option>"+
				"<option value=\"4096\">4"+WUILang.MB+"</option>"+
				"<option value=\"8192\">8"+WUILang.MB+"</option>"+
				"<option value=\"16384\">16"+WUILang.MB+"</option>"+
				"</select>";
		if(this.hidePieceSize)
			pieceSize = "";	
		theDialogManager.make("tcreate",WUILang.CreateNewTorrent,
			"<form action='plugins/create/createtorrent.php' id='createtorrent' method='post' target='createfrm'>"+
				"<div class='cont fxcaret'>"+
					"<fieldset>"+
						"<legend>"+WUILang.SelectSource+"</legend>"+
						"<input type='text' id='path_edit' name='path_edit' class='TextboxLarge' autocomplete='off'/>"+
						"<input type=button value='...' id='browse_path' class='Button'><br/>"+
					"</fieldset>"+
					"<fieldset>"+
						"<legend>"+WUILang.TorrentProperties+"</legend>"+
                                	               "<label>"+WUILang.Trackers+": </label>"+
						"<textarea id='trackers' name='trackers'></textarea><br/>"+
        	                               	       "<label>"+WUILang.Comment+": </label>"+
	                	               	"<input type='text' id='comment' name='comment' class='TextboxLarge'/><br/>"+
						pieceSize+	
					"</fieldset>"+
					"<fieldset>"+
						"<legend>"+WUILang.Other+"</legend>"+
						"<label id='nomargin'><input type='checkbox' name='start_seeding' id='start_seeding'/>"+WUILang.StartSeeding+"</label>"+
						"<input type='checkbox' name='private' id='private'/>"+WUILang.PrivateTorrent+"<br/>"+
					"</fieldset>"+
					"<div class='aright'><input type='submit' id='createAndSave' value='"+WUILang.CreateAndSaveAs+"' class='Button' /><input type='button' class='Cancel Button' value='"+WUILang.Cancel+"'/></div>"+
				"</div>"+
				"<iframe id='createfrm' name='createfrm' src=''></iframe>"+
			"</form>");
		if(thePlugins.isInstalled("_getdir"))
		{
			var btn = new theWebUI.rDirBrowser( 'tcreate', 'path_edit', 'browse_path', null, true );
			theDialogManager.setHandler('tcreate','afterHide',function()
			{
				btn.hide();
			});
		}
		else
			$('#browse_path').remove();
		$('#createtorrent').submit( function ()
		{
			if(!$.trim($("#path_edit").val()).length) 
			{
				alert(WUILang.BadTorrentData);
   				return false;
		   	}
			return(true);
		});
	}
};
