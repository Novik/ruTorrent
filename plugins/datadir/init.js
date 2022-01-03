plugin.loadMainCSS();
plugin.loadLang();

theWebUI.EditDataDir = function()
{
	var id = theWebUI.getTable("trt").getFirstSelected();
	if( id && (id.length==40) && this.torrents[id] )
	{
	        var save_path = this.torrents[id].save_path.trim();
		if( !save_path.length ) // torrent is not open
			this.request( "?action=getsavepath&hash=" + id, [this.showDataDirDlg, this] );
		else
			theWebUI.showDataDirDlg( { hash: id, savepath: save_path } );
	}
}

theWebUI.showDataDirDlg = function( d )
{
	var id = theWebUI.getTable("trt").getFirstSelected();
	var is_done = false;
	var is_multy = false;
	if( id && (id.length==40) && this.torrents[id] )
	{
		is_done = String(this.torrents[id].done).trim() === "1000";
		is_multy = String(this.torrents[id].multi_file).trim() !== "0";
	}
	$('#edit_datadir').val( d.savepath.trim() );
	$('#btn_datadir_ok').prop("disabled",false);
	// can't ignore torrent's path if not multy
	$('#move_not_add_path').prop("disabled",!is_multy).prop("checked",false);
	$('#move_datafiles').prop("checked",true);
	// can't "fast resume" torrent if not completed
	$('#move_fastresume').prop("disabled",!is_done).prop("checked",false);
	theDialogManager.show( "dlg_datadir" );
}

rTorrentStub.prototype.getsavepath = function()
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

rTorrentStub.prototype.getsavepathResponse = function( xml )
{
	var datas = xml.getElementsByTagName( 'data' );
	var data = datas[0];
	var values = data.getElementsByTagName( 'value' );
	var torrent = theWebUI.torrents[this.hashes[0]];
	var save_path = '';
	if(torrent)
	{
		torrent.base_path = this.getValue( values, 3 );
		var pos = torrent.base_path.lastIndexOf('/');
		torrent.save_path = (torrent.base_path.substring(pos+1) === torrent.name) ? 
			torrent.base_path.substring(0,pos) : torrent.base_path;
		save_path = torrent.save_path;
	}
	return( { hash: this.hashes[0], savepath: save_path } );
}

if(plugin.canChangeMenu())
{
	plugin.createMenu = theWebUI.createMenu;
	theWebUI.createMenu = function( e, id )
	{
		plugin.createMenu.call(this, e, id);
		if(plugin.enabled && plugin.allStuffLoaded)
		{
			var table = this.getTable("trt");
			
			var el = theContextMenu.get( theUILang.Properties );
			if( el )
				theContextMenu.add( el, [theUILang.DataDir + "...", 
					((table.selCount > 1) || (table.getFirstSelected().length==40)) ? "theWebUI.EditDataDir()" : null] );
		}
	}
}

theWebUI.sendDataDir = function()
{
	$('#btn_datadir_ok').prop("disabled",true);
	var sr = this.getTable("trt").rowSel;
	for( var k in sr )
	{
		if( sr[k] && (k.length==40))
		{
			this.DataDirID = k;
			this.requestWithTimeout( "?action=setdatadir", [this.receiveDataDir, this], function()
			{
				theWebUI.timeout();
				$('#btn_datadir_ok').prop("disabled",false);
			});
		}
	}

}

theWebUI.receiveDataDir = function( d )
{
	$('#btn_datadir_ok').prop("disabled",false);
	if( !d.errors.length )
		theDialogManager.hide( 'dlg_datadir' );
	else
		for( var i = 0; i < d.errors.length; i++ )
		{
			var s = eval(d.errors[i].desc);
			if( d.errors[i].prm )
				s += " (" + d.errors[i].prm + ")";
			noty( s, "error" );
		}
}

rTorrentStub.prototype.setdatadir = function()
{
	var id = theWebUI.DataDirID;
	this.content = "hash=" + id +
		"&datadir=" + encodeURIComponent( $('#edit_datadir').val() ) +
		"&move_addpath=" + ( $$('move_not_add_path').checked  ? '0' : '1' ) +
		"&move_datafiles=" + ( $$('move_datafiles').checked  ? '1' : '0' ) +
		"&move_fastresume=" + ( $$('move_fastresume').checked  ? '1' : '0' );
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/datadir/action.php";
	this.dataType = "json";
}

plugin.onLangLoaded = function()
{
	theDialogManager.make( 'dlg_datadir', theUILang.datadirDlgCaption,
		"<div class='content fxcaret'>" +
			"<fieldset>" +
				"<label id='lbl_datadir' for='edit_datadir'>" + theUILang.DataDir + ": </label>" +
				"<input type='text' id='edit_datadir' class='TextboxLarge' maxlength='200'/>" +
				"<input type='button' id='btn_datadir_browse' class='Button' value='...' />" +
				"<div class='checkbox'>" +
					"<input type='checkbox' checked id='move_not_add_path'/>"+
					"<label for='move_not_add_path'>"+ theUILang.Dont_add_tname +"</label>"+
				"</div>" +
				"<div class='checkbox'>" +
					"<input type='checkbox' checked id='move_datafiles'/>"+
					"<label for='move_datafiles'>"+ theUILang.DataDirMove +"</label>"+
				"</div>" +
				"<div class='checkbox'>" +
					"<input type='checkbox' checked id='move_fastresume'/>"+
					"<label for='move_fastresume'>"+ theUILang.doFastResume +"</label>"+
				"</div>" +
			"</fieldset>" +
		"</div>"+
		"<div class='aright buttons-list'>" +
			"<input type='button' value='" + theUILang.ok + "' class='OK Button' id='btn_datadir_ok'" +
				" onclick='theWebUI.sendDataDir(); return(false);' />" +
			"<input type='button' value='"+ theUILang.Cancel + "' class='Cancel Button'/>" +
		"</div><br/>" +
		"<div />", 
		true);
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
