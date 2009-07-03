
var plugin = new rPlugin( "autotools" );
plugin.loadMainCSS();
plugin.loadLanguages();

utWebUI.autotoolsStuffLoaded = false;

utWebUI.autotoolsAddAndShowSettings = utWebUI.addAndShowSettings;
utWebUI.addAndShowSettings = function( arg )
{
	$$('enable_label').checked = ( utWebUI.autotools.EnableLabel == 1 );
	$$('enable_move').checked  = ( utWebUI.autotools.EnableMove  == 1 );
	$$('path_to_finished').value = utWebUI.autotools.PathToFinished;
	linked( $$('enable_move'), 0, ['path_to_finished', 'automove_browse_btn'] );
//	var s = '';
//	for( var i = 0; i < utWebUI.autotools.sample.length; i++ )
//	{
//		var grp = utWebUI.autotools.sample[i];
//		if( i > 0 )
//			s += '\r\n';
//		for( var j = 0; j < grp.length; j++ )
//		{
//			s += grp[j];
//			s += '\r\n';
//		}
//	}
//	$$('eautotools').value = s;
	utWebUI.autotoolsAddAndShowSettings( arg );
}

utWebUI.autotoolsWasChanged = function()
{
	if( $$('enable_label').checked != ( utWebUI.autotools.EnableLabel == 1 ) )
		return true;
	if( $$('enable_move').checked  != ( utWebUI.autotools.EnableMove  == 1 ) )
		return true;
	if( $$('path_to_finished').value != utWebUI.autotools.PathToFinished )
		return true;
//	var arr = $$('eautotools').value.split( "\n" );
//	var groups = new Array();				// array of curGroups
//	var curGroup = new Array();
//	for( var i = 0; i < arr.length; i++ )
//	{
//		var s = arr[i].replace( /(^\s+)|(\s+$)/g, "" );	// trim
//		if( s.length > 0 )
//		{
//			curGroup.push( s );
//		}
//		else if( curGroup.length > 0 )			// groups are separated by "\n\n"
//		{
//			groups.push( curGroup );
//			curGroup = new Array();
//		}
//	}
//	if( curGroup.length > 0 )
//		groups.push( curGroup );
//
//	if( groups.length != utWebUI.autotools.sample.length )
//		return true;
//	for( var i = 0; i < groups.length; i++ )		// foreach group
//	{
//		if( groups[i].length != utWebUI.autotools.sample[i].length )
//			return true;
//		for( var j = 0; j < groups[i].length; j++ )	// foreach curGroup
//			if( groups[i][j] != utWebUI.autotools.sample[i][j] )
//				return true;
//	}
	return false;
}

utWebUI.autotoolsSetSettings = utWebUI.setSettings;
utWebUI.setSettings = function()
{
	this.autotoolsSetSettings();
	if( this.autotoolsWasChanged() )
		this.Request( "?action=setautotools" );
}

utWebUI.autotoolsCreate = function()
{
	var dlg = document.createElement( "DIV" );
	dlg.className = "stg_con";
	dlg.id = "st_autotools";
	dlg.innerHTML =
		"<fieldset>"+
			"<legend>"+ WUILang.autotools +"</legend>"+
			"<table>"+
			"<tr>"+
				"<td>"+
					"<input type='checkbox' id='enable_label' checked='false'"+
					"<label for='enable_label'>"+ WUILang.autotoolsEnableLabel +"</label>"+
				"</td>"+
			"</tr>"+
			"<tr />"+
			"<tr>"+
				"<td>"+
					"<input type='checkbox' id='enable_move' checked='false' "+
					"onchange='javascript:linked(this, 0, [\"path_to_finished\", \"automove_browse_btn\"]);' />"+
						"<label for='enable_move'>"+ WUILang.autotoolsEnableMove +"</label>"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td>"+
					"<label id='lbl_path_to_finished' for='path_to_finished' class='disabled' disabled='true'>"+
					WUILang.autotoolsPathToFinished +":</label>"+
				"</td>"+
			"</tr>"+
			"<tr>"+
				"<td class='alr'>"+
					"<input type='text' id='path_to_finished' class='TextboxLarge' maxlength='100' />"+
					"<input type='button' id='automove_browse_btn' class='Button' value='...' />"+
				"</td>"+
			"</tr>"+
			"</table>"+
		"</fieldset>";
	plugin.attachPageToOptions( dlg, WUILang.autotools );
	if( utWebUI.rDirBrowserLoaded )
	{
		plugin.DirBrowser = new rDirBrowser( 
			dlg, $$('path_to_finished'), $$('automove_browse_btn'), 'automove_browse_frame' );
	}
	else {
		var btn = $$('automove_browse_btn');
		btn.id = 'automove_browse_btn_disabled';
		btn.disabled = true;
	}

	utWebUI.autotoolsStuffLoaded = true;
}

utWebUI.showAutoToolsError = function( err )
{
	if( utWebUI.autotoolsStuffLoaded )
		log( err );
	else
		setTimeout( 'utWebUI.showAutoToolsError(' + err + ')', 1000 );
}

rTorrentStub.prototype.setautotools = function()
{
	this.content = "enable_label=" + ( $$('enable_label').checked ? '1' : '0' ) +
		"&enable_move=" + ( $$('enable_move').checked  ? '1' : '0' ) +
		"&path_to_finished=" + $$('path_to_finished').value;
//	var arr = $$('eautotools').value.split( "\n" );
//	for( var i = 0; i < arr.length; i++ )
//	{
//		var s = arr[i].replace( /(^\s+)|(\s+$)/g, "" );
//		//if( s.toLowerCase() != 'dht://' )
//		//	this.content += "&tracker=" + encodeURIComponent(s);
//		this.content += "&sample=" + encodeURIComponent(s);
//	}
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/autotools/action.php";
}

rTorrentStub.prototype.setautotoolsResponse = function( xmlDoc, docText )
{
	var datas = xmlDoc.getElementsByTagName( 'data' );
	return datas[0].childNodes[0].data;
}




