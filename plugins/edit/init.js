var plugin = new rPlugin("edit");
plugin.loadMainCSS();
plugin.loadLanguages();

function sortByGroup(a,b)
{
	return( a[3] < b[3] ? -1 : a[3] > b[3] ? 1 : 0 );
}

utWebUI.editInited = false;

utWebUI.EditTrackers = function()
{
	if(!utWebUI.editInited)
	{
		Drag.init($$("tedit-header"), $$("tedit"), 0, getWindowWidth(), 0, getWindowHeight(), true);
		utWebUI.editInited = true;
	}
	if((this.dID != "") && this.torrents[this.dID])
	{
		var d = this.torrents[this.dID].slice(1);
		var trk = this.trackers[this.dID];
		trk.sort(sortByGroup);
		var s = "";
		if(trk.length)
		{
			var lastGroup = trk[0][3];
			for(var i=0; i<trk.length; i++)
			{
			        if(trk[i][0]!="dht://")
			        {
					if(lastGroup != trk[i][3])
					{
						s+='\r\n';
						lastGroup = trk[i][3];
					}
					s+=trk[i][0];
					s+='\r\n';
				}
			}
		}
		s = s.replace(/(^\s+)|(\s+$)/g, "");
		if(browser.isKonqueror)
		{
			var tarea = $$('etrackers');
			if(tarea.lastChild)
				tarea.removeChild(tarea.lastChild);
			tarea.appendChild(document.createTextNode(s)); 
		}
		else
			$$('etrackers').value = s;
		$$('ecomment').value = d[24].replace(/(^\s+)|(\s+$)/g, "");
		$$('editok').disabled = false;
		ShowModal("tedit");
	}
}

utWebUI.oldEditCreateMenu = utWebUI.createMenu;

utWebUI.createMenu = function(e, id) 
{
	this.oldEditCreateMenu(e,id);
	var el = ContextMenu.get(WUILang.Properties);
	if(el)
	{
		if(this.trtTable.selCount > 1)
			ContextMenu.add(el,[WUILang.EditTrackers]);
		else
			ContextMenu.add(el,[WUILang.EditTrackers,"utWebUI.EditTrackers()"]);
	}
}

utWebUI.oldEditCreateTrackerMenu = utWebUI.createTrackerMenu;

utWebUI.createTrackerMenu = function(e, id) 
{
	if(utWebUI.oldEditCreateTrackerMenu(e, id))
	{
		ContextMenu.add([CMENU_SEP]);
		ContextMenu.add([WUILang.EditTrackers,"utWebUI.EditTrackers()"]);
		return(true);
	}
	return(false);
}

function enableEditButton()
{
	utWebUI.TimeoutLog(); 
	$$('editok').disabled = false;
}

utWebUI.sendEdit = function() 
{
	$$('editok').disabled = true;
	this.RequestWithTimeout("?action=edittorrent",[this.receiveEdit, this], enableEditButton);
}

utWebUI.receiveEdit = function(data)
{
	$$('editok').disabled = false;
	var d = eval("(" + data + ")");
	if(!d.errors.length)
	{
		utWebUI.getTrackers(utWebUI.dID);	
		HideModal("tedit");
	}
	else
		for( var i=0; i<d.errors.length; i++)
		{
			var s = d.errors[i].desc;
			if(d.errors[i].prm)
				s = s + " ("+d.errors[i].prm+")";
			log(s);
		}
}

utWebUI.editCreate = function() 
{
	var dlg = document.createElement("DIV");
	dlg.className = "dlg-window";
	dlg.id = "tedit";
	dlg.innerHTML = 
		"<a href=\"javascript:HideModal('tedit');\" class='dlg-close'></a>"+
		"<div id='tedit-header' class='dlg-header'>"+WUILang.EditTorrentProperties+
		"</div>"+
		"<div class='cont fxcaret'>"+
			"<fieldset>"+
				"<label>"+WUILang.Trackers+": </label>"+
				"<textarea id='etrackers' name='etrackers'></textarea><br/>"+
				"<label>"+WUILang.Comment+": </label>"+
                               	"<input type='text' id='ecomment' name='ecomment' class='TextboxLarge'/><br/>"+
			"</fieldset>"+
			"<div class='aright'><input type='button' value='"+WUILang.ok1+"' class='Button' id='editok' onclick='javascript:utWebUI.sendEdit()'/><input type='button' value='"+WUILang.Cancel1+"' class='Button' onclick='javascript:HideModal(\"tedit\");return(false);' /></div>"+
		"</div>";
	var b = document.getElementsByTagName("body").item(0);
	b.appendChild(dlg);
}

rTorrentStub.prototype.edittorrent = function()
{
	this.content = "hash="+utWebUI.dID+"&comment="+encodeURIComponent($$('ecomment').value);
	var arr = $$('etrackers').value.split("\n");
	for(var i = 0; i<arr.length; i++)	
	{
		var s = arr[i].replace(/(^\s+)|(\s+$)/g, "");
		if(s.toLowerCase()!='dht://')
			this.content = 	this.content+"&tracker="+encodeURIComponent(s);
	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/edit/action.php";
}

rTorrentStub.prototype.edittorrentResponse = function(xmlDoc,docText)
{
	var datas = xmlDoc.getElementsByTagName('data');
	return(datas[0].childNodes[0].data);
}