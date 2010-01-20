plugin.loadLang();
plugin.loadMainCSS();
if(browser.isKonqueror)
	plugin.loadCSS("konqueror");

plugin.onLangLoaded = function()
{
	if(this.enabled)
	{
		this.addButtonToToolbar("create",theUILang.mnu_create,"theDialogManager.toggle('tcreate')","remove");
		this.addSeparatorToToolbar("remove");

		var pieceSize = 
			"<label>"+theUILang.PieceSize+": </label>"+
			"<select id='piece_size' name='piece_size'>"+
				"<option value=\"32\">32"+theUILang.KB+"</option>"+
				"<option value=\"64\">64"+theUILang.KB+"</option>"+
				"<option value=\"128\">128"+theUILang.KB+"</option>"+
				"<option value=\"256\" selected=\"selected\">256"+theUILang.KB+"</option>"+
				"<option value=\"512\">512"+theUILang.KB+"</option>"+
				"<option value=\"1024\">1"+theUILang.MB+"</option>"+
				"<option value=\"2048\">2"+theUILang.MB+"</option>"+
				"<option value=\"4096\">4"+theUILang.MB+"</option>"+
				"<option value=\"8192\">8"+theUILang.MB+"</option>"+
				"<option value=\"16384\">16"+theUILang.MB+"</option>"+
				"</select>";
		if(this.hidePieceSize)
			pieceSize = "";	
		theDialogManager.make("tcreate",theUILang.CreateNewTorrent,
			"<form action='plugins/create/createtorrent.php' id='createtorrent' method='post' target='createfrm'>"+
				"<div class='cont fxcaret'>"+
					"<fieldset>"+
						"<legend>"+theUILang.SelectSource+"</legend>"+
						"<input type='text' id='path_edit' name='path_edit' class='TextboxLarge' autocomplete='off'/>"+
						"<input type=button value='...' id='browse_path' class='Button'><br/>"+
					"</fieldset>"+
					"<fieldset>"+
						"<legend>"+theUILang.TorrentProperties+"</legend>"+
                                	               "<label>"+theUILang.Trackers+": </label>"+
						"<textarea id='trackers' name='trackers'></textarea><br/>"+
        	                               	       "<label>"+theUILang.Comment+": </label>"+
	                	               	"<input type='text' id='comment' name='comment' class='TextboxLarge'/><br/>"+
						pieceSize+	
					"</fieldset>"+
					"<fieldset>"+
						"<legend>"+theUILang.Other+"</legend>"+
						"<label id='nomargin'><input type='checkbox' name='start_seeding' id='start_seeding'/>"+theUILang.StartSeeding+"</label>"+
						"<input type='checkbox' name='private' id='private'/>"+theUILang.PrivateTorrent+"<br/>"+
					"</fieldset>"+
					"<div class='aright'><input type='submit' id='createAndSave' value='"+theUILang.CreateAndSaveAs+"' class='Button' /><input type='button' class='Cancel Button' value='"+theUILang.Cancel+"'/></div>"+
				"</div>"+
			"</form>");
		$(document.body).append($("<iframe>").attr( { name: "createfrm", id: "createfrm" } ).width(0).height(0).load(function()
		{
			var d = (this.contentDocument || this.contentWindow.document);
			if(d.location.href != "about:blank")
				try { eval(d.body.innerHTML); } catch(e) { log(d.body.innerHTML); }
		}));

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
				alert(theUILang.BadTorrentData);
   				return false;
		   	}
			return(true);
		});
	}
};
