var plugin = new rPlugin("create");
plugin.loadMainCSS();
if(browser.isKonqueror)
	plugin.loadCSS("konqueror");
else
if(browser.isOldIE)
	plugin.loadCSS("ie6");

function showPathFrame()
{
	var dv = $$('path_frame');
	var edit = $$('path_edit');
	var btn = $$('browse_path');
	dv.src = "plugins/create/getdirs.php?dir="+encodeURIComponent(edit.value) + "&time=" + (new Date()).getTime();
	var x = edit.offsetLeft;
	var y = edit.offsetTop+edit.offsetHeight;
	var parent = edit.offsetParent;
	while(parent && parent.id!='tcreate')
	{
		x+=parent.offsetLeft;
		y+=parent.offsetTop;
		parent = parent.offsetParent;
	}
	dv.style.left =  x + "px";
	dv.style.top = y + "px";
	var width = edit.offsetWidth-2;
	btn.value = "X";
	dv.style.width = width + "px";
	dv.style.visibility = "visible";
	dv.style.display = "block";
	edit.readOnly = true;
}

function hidePathFrame()
{
	var dv = $$('path_frame');
	var edit = $$('path_edit');
	var btn = $$('browse_path');
	dv.style.visibility = "hidden";
	dv.style.display = "none";
	btn.value = "...";
	edit.readOnly = false;
}

function isPathFrameVisible()
{
	var dv = $$('path_frame');
	return(dv.style.visibility != "hidden");
}

function togglePathFrame()
{
	if(isPathFrameVisible())
	 	hidePathFrame();
	else
		showPathFrame();	
	return(false);
}

utWebUI.allCreateStuffLoaded = false;
utWebUI.initCreate = function()
{
 	var removeBtn = $$("mnu_remove");
	var createBtn = document.createElement("A");
	createBtn.id="mnu_create";
	createBtn.href="javascript:utWebUI.showCreate();"
	createBtn.title=WUILang.mnu_create;
	createBtn.innerHTML='<div id="create"></div>';
	removeBtn.parentNode.insertBefore(createBtn,removeBtn);
	var sep = document.createElement("DIV");
	sep.className = "TB_Separator";
	removeBtn.parentNode.insertBefore(sep,removeBtn);

	var dlg = document.createElement("DIV");
	dlg.className = "dlg-window";
	dlg.id = "tcreate";
	dlg.innerHTML = 
		"<a href=\"javascript:Hide('tcreate');\" class='dlg-close'></a>"+
		"<div id='tcreate-header' class='dlg-header'>"+WUILang.CreateNewTorrent+
		"</div>"+
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
					"<label>"+WUILang.PieceSize+": </label>"+
					"<select id='piece_size' name='piece_size'/>"+
						"<option value=\"32\">32"+WUILang.KB+"</option>"+
						"<option value=\"64\">64"+WUILang.KB+"</option>"+
						"<option value=\"128\">128"+WUILang.KB+"</option>"+
						"<option value=\"256\" selected=\"selected\">256"+WUILang.KB+"</option>"+
						"<option value=\"512\">512"+WUILang.KB+"</option>"+
						"<option value=\"1024\">1024"+WUILang.KB+"</option>"+
						"<option value=\"2048\">2048"+WUILang.KB+"</option>"+
						"<option value=\"4096\">4096"+WUILang.KB+"</option>"+
					"</select>"+
				"</fieldset>"+
				"<fieldset>"+
					"<legend>"+WUILang.Other+"</legend>"+
					"<label id='nomargin'><input type='checkbox' name='start_seeding' id='start_seeding'/>"+WUILang.StartSeeding+"</label>"+
					"<input type='checkbox' name='private' id='private'/>"+WUILang.PrivateTorrent+"<br/>"+
				"</fieldset>"+
				"<div class='aright'><input type='submit' id='createAndSave' value='"+WUILang.CreateAndSaveAs+"' class='Button' /><input type='button' id='Cancel3' value='"+WUILang.Cancel1+"' class='Button' onclick='javascript:Hide(\"tcreate\");return(false);' /></div>"+
			"</div>"+
			"<iframe id='path_frame' src=''></iframe>"+
			"<iframe id='createfrm' name='createfrm' src=''></iframe>"+
		"</form>";
	var b = document.getElementsByTagName("body").item(0);
	b.appendChild(dlg);
	$$('browse_path').onclick=togglePathFrame;
	$$('path_frame').style.visibility = "hidden";
	var edit = $$("path_edit");
	if(browser.isIE)
		edit.onfocusin = function () { if(isPathFrameVisible()) hidePathFrame(); }
	else
		edit.onfocus = function () { if(isPathFrameVisible()) hidePathFrame(); }
	edit.setAttribute('autocomplete','off');
	$$('createtorrent').onsubmit = checkCreate;
	utWebUI.allCreateStuffLoaded = true;
};

function checkCreate()
{
	var edit = $$("path_edit").value;
	edit = edit.replace(/(^\s+)|(\s+$)/g, "");
	if(!edit.length) 
	{
		alert(WUILang.BadTorrentData);
   		return false;
   	}
	return(true);
}

utWebUI.createInited = false;
utWebUI.showCreate  = function() 
{
	if(!utWebUI.createInited)
	{
		Drag.init($$("tcreate-header"), $$("tcreate"), 0, getWindowWidth(), 0, getWindowHeight(), true);
		utWebUI.createInited = true;
	}
	Toggle($$("tcreate"));
}

utWebUI.showCreateError = function(err)
{
	if(utWebUI.allCreateStuffLoaded)
		log(err);
	else
		setTimeout('utWebUI.showCreateError('+err+')',1000);
}

plugin.loadLanguages();
