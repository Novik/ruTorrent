plugin.loadMainCSS();
plugin.loadLang();

theWebUI.EditDataDir = function()
{
	var id = theWebUI.getTable("trt").getFirstSelected();
	if( id && this.torrents[id] )
	{
	        var base_path = $.trim(this.torrents[id].base_path)
		if( !base_path.length ) // torrent is not open
			this.request( "?action=getbasepath&hash=" + id, [this.showDataDirDlg, this] );
		else
			theWebUI.showDataDirDlg( { hash: id, basepath: base_path } );
	}
}

theWebUI.showDataDirDlg = function( d )
{
	$('#edit_datadir').val( $.trim(d.basepath).replace(/\/[^\/]+$/g, "") );
	$('#btn_datadir_ok').attr("disabled",false);
	theDialogManager.show( "dlg_datadir" );
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

rTorrentStub.prototype.getbasepathResponse = function( xml )
{
	var datas = xml.getElementsByTagName( 'data' );
	var data = datas[0];
	var values = data.getElementsByTagName( 'value' );
	return( { hash: this.hashes[0], basepath: this.getValue( values, 3 ) } );
}

if(plugin.canChangeMenu())
{
	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled && plugin.allStuffLoaded)
		{
			var el = theContextMenu.get( theUILang.Properties );
			if( el )
				theContextMenu.add( el, [theUILang.DataDir + "...", "theWebUI.EditDataDir()"] );
		}
	}
}

theWebUI.sendDataDir = function()
{
	$('#btn_datadir_ok').attr("disabled",true);
	var sr = this.getTable("trt").rowSel;
	for( var k in sr )
	{
		if( sr[k] )
		{
			this.DataDirID = k;
			this.requestWithTimeout( "?action=setdatadir", [this.receiveDataDir, this], function()
			{
				theWebUI.timeout();
				$('#btn_datadir_ok').attr("disabled",false);
			});
		}
	}

}

theWebUI.receiveDataDir = function( d )
{
	$('#btn_datadir_ok').attr("disabled",false);
	if( !d.errors.length )
		theDialogManager.hide( 'dlg_datadir' );
	else
		for( var i = 0; i < d.errors.length; i++ )
		{
			var s = d.errors[i].desc;
			if( d.errors[i].prm )
				s += " (" + d.errors[i].prm + ")";
			log( s );
		}
}

rTorrentStub.prototype.setdatadir = function()
{
	var id = theWebUI.DataDirID;
	this.content = "hash=" + id +
		"&datadir=" + encodeURIComponent( $('#edit_datadir').val() ) +
		"&move_datafiles=" + ( $$('move_datafiles').checked  ? '1' : '0' );
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/datadir/action.php";
	this.dataType = "json";
}

plugin.onLangLoaded = function()
{
	theDialogManager.make( 'dlg_datadir', theUILang.datadirDlgCaption,
		"<div class='cont fxcaret'>" +
			"<fieldset>" +
				"<label id='lbl_datadir' for='edit_datadir'>" + theUILang.DataDir + ": </label>" +
				"<input type='text' id='edit_datadir' class='TextboxLarge' maxlength='200'/>" +
				"<input type='button' id='btn_datadir_browse' class='Button' value='...' />" +
				"<div class='checkbox'>" +
					"<input type='checkbox' id='move_datafiles'/>"+
					"<label for='move_datafiles'>"+ theUILang.DataDirMove +"</label>"+
				"</div>" +
			"</fieldset>" +
		"</div>"+
		"<div class='aright buttons-list'>" +
			"<input type='button' value='" + theUILang.ok + "' class='OK Button' id='btn_datadir_ok'" +
				" onclick='theWebUI.sendDataDir(); return(false);' />" +
			"<input type='button' value='"+ theUILang.Cancel + "' class='Cancel Button'/>" +
		"</div>", true);
	if(thePlugins.isInstalled("_getdir"))
	{
		var btn = new theWebUI.rDirBrowser( 'dlg_datadir', 'edit_datadir', 'btn_datadir_browse', 'frame_datadir_browse' );
		theDialogManager.setHandler('dlg_datadir','afterHide',function()
		{
			btn.hide();
		});
	}
	else
		$('#btn_datadir_browse').remove();
}

plugin.onRemove = function()
{
	theDialogManager.hide("dlg_datadir");
}