var plugin = new rPlugin( "datadir" );
plugin.loadMainCSS();
plugin.loadLanguages();

utWebUI.datadirInited = false;

utWebUI.EditDataDir = function()
{
	if( !utWebUI.datadirInited )
	{
		Drag.init( $$("dlg_datadir-header"), $$("dlg_datadir"), 0, getWindowWidth(), 0, getWindowHeight(), true );
		utWebUI.datadirInited = true;
	}
	if( this.dID != '' && this.torrents[this.dID] )
	{
		var d = this.torrents[this.dID].slice( 1 );
		var d20 = d[20].replace(/(^\s+)|(\s+$)/g, "");	// trim( d.get_basepath ) ???
		if( true || d20 == '' ) // torrent is not open
		{
			// async request for (d.open,d.get_basedir,d.close)
			this.Request( "?action=getbasepath&hash=" + this.dID, [this.showDataDirDlg, this] );
		}
		else {
			d20 = d20.replace(/\/[^\/]+$/g, "");	// remove torrent name (subdir or file)
			$$('edit_datadir').value = d20;
			$$('btn_datadir_ok').disabled = false;
			ShowModal( "dlg_datadir" );
		}
	}
}

utWebUI.showDataDirDlg = function( data )
{
	if( this.dID != '' && this.torrents[this.dID] )
	{
		var d = eval("(" + data + ")");
		d20 = d.basepath;
		d20 = d20.replace(/(^\s+)|(\s+$)/g, "");	// trim( d.get_basepath ) ???
		//this.torrents[d.hash][20] = d.basepath;

		d20 = d20.replace(/\/[^\/]+$/g, "");		// remove torrent name (subdir or file)
		$$('edit_datadir').value = d20;
		$$('btn_datadir_ok').disabled = false;
		ShowModal( "dlg_datadir" );
	}
}

rTorrentStub.prototype.getbasepath = function()
{
	var cmd = new rXMLRPCCommand( "d.open" );
	cmd.addParameter( "string", this.hashes[0] );
	this.commands.push( cmd );
	cmd = new rXMLRPCCommand( "d.get_base_path" );
	cmd.addParameter( "string", this.hashes[0] );
	this.commands.push( cmd );
	cmd = new rXMLRPCCommand( "d.close" );
	cmd.addParameter( "string", this.hashes[0] );
	this.commands.push( cmd );
}

rTorrentStub.prototype.getbasepathResponse = function( xmlDoc, docText )
{
	var datas = xmlDoc.getElementsByTagName( 'data' );
	var data = datas[0];
	var values = data.getElementsByTagName( 'value' );
	return( '{hash: "' + this.hashes[0] + '",basepath: "' + this.getValue( values, 3 ) + '"}' );
}


utWebUI.datadirCreateMenu = utWebUI.createMenu;
utWebUI.createMenu = function( e, id )
{
	this.datadirCreateMenu( e, id );
	var el = ContextMenu.get( WUILang.Properties );
	if( el )
	{
		if( this.trtTable.selCount > 1 )
			ContextMenu.add( el, [WUILang.DataDir + "..."] );
		else
			ContextMenu.add( el, [WUILang.DataDir + "...", "utWebUI.EditDataDir()"] );
	}
}

function enableDataDirButton()
{
	utWebUI.TimeoutLog();
	$$('btn_datadir_ok').disabled = false;
}

utWebUI.sendDataDir = function()
{
	$$('btn_datadir_ok').disabled = true;
	this.RequestWithTimeout( "?action=setdatadir", [this.receiveDataDir, this], enableDataDirButton );
}

utWebUI.receiveDataDir = function( data )
{
	$$('btn_datadir_ok').disabled = false;
	var d = eval( "(" + data + ")" );
	if( !d.errors.length )
	{
		//this.torrents[d.hash][20] = d.basepath;
		HideModal( 'dlg_datadir' );
	}
	else
		for( var i = 0; i < d.errors.length; i++ )
		{
			var s = d.errors[i].desc;
			if( d.errors[i].prm )
				s += " (" + d.errors[i].prm + ")";
			log( s );
		}
}

utWebUI.datadirCreate = function()
{
	var dlg = document.createElement( "DIV" );
	dlg.className = "dlg-window";
	dlg.id = "dlg_datadir";
	dlg.innerHTML =
		"<a href=\"javascript:HideModal( 'dlg_datadir' );\" class='dlg-close'></a>" +
		"<div id='dlg_datadir-header' class='dlg-header'>" + WUILang.datadirDlgCaption + "</div>" +
		"<div class='cont fxcaret'>" +
			"<fieldset>" +
				"<label id='lbl_datadir' for='edit_datadir'>" + WUILang.DataDir + ": </label>" +
				"<br/>" +
				"<div class='aright'>" +
					"<input type='text' id='edit_datadir' class='TextboxLarge' maxlength='200'/>" +
					"<input type='button' id='btn_datadir_browse' class='Button' value='...' />" +
				"</div>" +
				"<div class='checkbox'>" +
					"<input type='checkbox' id='move_datafiles'/>"+
					"<label for='move_datafiles'>"+ WUILang.DataDirMove +"</label>"+
				"</div>" +
			"</fieldset>" +
			"<div class='aright'>" +
				"<input type='button' value='" + WUILang.ok1 + "' class='Button' id='btn_datadir_ok'" +
					" onclick='javascript:utWebUI.sendDataDir()'/>" +
				"<input type='button' value='"+ WUILang.Cancel1 + "' class='Button'" +
					" onclick=\"javascript:HideModal( 'dlg_datadir' ); return false;\" />" +
			"</div>" +
		"</div>";
	var b = document.getElementsByTagName( "body" ).item( 0 );
	b.appendChild( dlg );
	if( utWebUI.rDirBrowserLoaded )
	{
		plugin.DirBrowser = new rDirBrowser(
			dlg, $$('edit_datadir'), $$('btn_datadir_browse'), 'frame_datadir_browse' );
	}
	else {
		var btn = $$('btn_datadir_browse');
		btn.id = 'btn_datadir_browse_disabled';
		btn.disabled = true;
	}
}

rTorrentStub.prototype.setdatadir = function()
{
	this.content = "hash=" + utWebUI.dID +
		"&datadir=" + encodeURIComponent( $$('edit_datadir').value ) +
		"&move_datafiles=" + ( $$('move_datafiles').checked  ? '1' : '0' );
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/datadir/action.php";
}

rTorrentStub.prototype.setdatadirResponse = function( xmlDoc, docText )
{
	var datas = xmlDoc.getElementsByTagName( 'data' );
	return datas[0].childNodes[0].data;
}


