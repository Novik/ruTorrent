plugin.loadMainCSS();
plugin.loadLang();

function firstSelectedTorrent() {
	const table = theWebUI.getTable("trt");
	const hash = table.selCount > 0 ? table.getFirstSelected() : null;
	return hash && hash.length === 40 ? {...theWebUI.torrents[hash], hash} : undefined;
}

theWebUI.EditDataDir = function()
{
	const torrent = firstSelectedTorrent();
	if( torrent )
	{
	        var save_path = torrent.save_path.trim();
		if( !save_path.length ) // torrent is not open
			this.request( "?action=getsavepath&hash=" + torrent.hash, [this.showDataDirDlg, this] );
		else
			theWebUI.showDataDirDlg( { hash: torrent.hash, savepath: save_path } );
	}
}

theWebUI.showDataDirDlg = function( d )
{
	var is_done = false;
	var is_multy = false;
	const torrent = firstSelectedTorrent();
	if( torrent )
	{
		is_done = String(torrent.done).trim() === "1000";
		is_multy = String(torrent.multi_file).trim() !== "0";
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
		torrent.base_path = this.getXMLValue( values, 3 );
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
			var el = theContextMenu.get( theUILang.Properties );
			if( el )
				theContextMenu.add( el, [theUILang.DataDir + "...", 
					firstSelectedTorrent() ? "theWebUI.EditDataDir()" : null] );
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

plugin.onLangLoaded = function() {
	theDialogManager.make('dlg_datadir', theUILang.datadirDlgCaption,
		$("<div>").addClass("cont fxcaret").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.DataDir),
				$("<div>").addClass("row align-items-center").append(
					$("<div>").addClass("col-md-2 d-none d-md-flex justify-content-end").append(
						$("<label>").attr({id: "lbl_datadir", for: "edit_datadir"}).text(theUILang.DataDir + ": "),
					),
					$("<div>").addClass("col-md-10 d-flex align-items-center").append(
						$("<input>").attr({type: "text", id: "edit_datadir"}).addClass("flex-grow-1"),
					),
					$("<div>").addClass("offset-md-2 col-md-10 d-flex align-items-center").append(
						$("<input>").attr({type: "checkbox", id: "move_not_add_path"}),
						$("<label>").attr({for: "move_not_add_path"}).text(theUILang.Dont_add_tname),
					),
					$("<div>").addClass("offset-md-2 col-md-10 d-flex align-items-center").append(
						$("<input>").attr({type: "checkbox", id: "move_datafiles"}),
						$("<label>").attr({for: "move_datafiles"}).text(theUILang.DataDirMove),
					),
					$("<div>").addClass("offset-md-2 col-md-10 d-flex align-items-center").append(
						$("<input>").attr({type: "checkbox", id: "move_fastresume"}),
						$("<label>").attr({for: "move_fastresume"}).text(theUILang.doFastResume),
					),
				),
			),
		)[0].outerHTML +
		$("<div>").addClass("buttons-list").append(
			$("<button>").addClass("OK").attr(
				{id: "btn_datadir_ok", onclick: "theWebUI.sendDataDir(); return(false);"}
			).text(theUILang.ok),
			$("<button>").addClass("Cancel").text(theUILang.Cancel),
		)[0].outerHTML,
		true,
	);
	if (thePlugins.isInstalled("_getdir")) {
		new theWebUI.rDirBrowser("edit_datadir");
	}
}

plugin.onRemove = function()
{
	theDialogManager.hide("dlg_datadir");
}
