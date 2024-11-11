plugin.loadLang();
plugin.loadMainCSS();
if(browser.isKonqueror)
	plugin.loadCSS("konqueror");

plugin.recentTrackers = {};

theWebUI.checkCreate = function()
{
	theDialogManager.hide('tcreate');
		var arr = $('#trackers').val().split("\n");
	var trk = '';
	for( var i in arr )
		trk+=(arr[i].trim()+'\r');
	theWebUI.startConsoleTask("create", plugin.name,
		{
			"piece_size" : $('#piece_size').val(),
			"trackers" : trk,
			"path_edit" : $("#path_edit").val().trim(),
			"comment" : $("#comment").val().trim(),
			"source" : $("#source").val().trim(),
			"private" : $('#private').prop('checked') ? 1 : 0,
			"start_seeding" : $('#start_seeding').prop('checked') ? 1 : 0,
			"hybrid" : $('#hybrid').prop('checked') ? 1 : 0,
		},
		{
			noclose: true,
		},
	);
}

plugin.onTaskFinished = function(task,fromBackground)
{
	if(!fromBackground)
	{
		$("#xtaskno").val(task.no);
		if(!task.status)
			$('#xcsave').show();
	}
	theWebUI.request('?action=rtget',[plugin.getRecentTrackers, plugin]);
}

rTorrentStub.prototype.rtget = function()
{
	this.content = "cmd=rtget";
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/create/action.php";
	this.dataType = "json";
}

rTorrentStub.prototype.rtdelete = function()
{
	this.content = "cmd=rtdelete&trackers="+plugin.deleteFromRecentTrackers;
	this.contentType = "application/x-www-form-urlencoded";
	this.mountPoint = "plugins/create/action.php";
	this.dataType = "json";
}

theWebUI.showCreate = function()
{
	if( $("#trackers").val().trim().length < 1 )
		$("#deleteFromRecentTrackers").prop("disabled", true);
	else
		$("#deleteFromRecentTrackers").prop("disabled", false);
	$('#start_seeding').prop('disabled',!theWebUI.systemInfo.rTorrent.started);
	if(theWebUI.systemInfo.rTorrent.started)
		$('#lbl_start_seeding').removeClass('disabled');
	else
		$('#lbl_start_seeding').addClass('disabled');
	theDialogManager.show('tcreate');
}

plugin.getRecentTrackers = function( data )
{
	plugin.recentTrackers = data;
	$("#recentTrackers").prop(
		"disabled",
		(!plugin.recentTrackers?.recent_trackers || !propsCount(plugin.recentTrackers.recent_trackers)),
	);
}

theWebUI.addTrackerToBox = function(ann)
{
	$("#deleteFromRecentTrackers").prop("disabled", false);
	const val = $('#trackers').val();
	if (val.includes(ann)) return;  // do nothing if selected tracker is already in the box
	$('#trackers').val(
		[...val.split(/\r?\n/), ann]  // split by "\r\n" or "\n"
			.filter(tracker => tracker.trim().length)  // remove empty lines
			.join("\r\n")
	).trigger("focus");
}

theWebUI.showRecentTrackers = function() {
	if (plugin.recentTrackers?.recent_trackers && propsCount(plugin.recentTrackers.recent_trackers)) {
		theContextMenu.clear();
		for( var domain in plugin.recentTrackers.recent_trackers )
			theContextMenu.add([domain,"theWebUI.addTrackerToBox('"+addslashes(plugin.recentTrackers.recent_trackers[domain])+"')"]);
		var offs = $("#recentTrackers").offset();
		theContextMenu.show(offs.left,offs.top-theContextMenu.obj.height()-5);
	}
}

theWebUI.deleteFromRecentTrackers = function()
{
	$("#deleteFromRecentTrackers").prop("disabled", true);
	var trklist = $('#trackers').val();
	if(!trklist)
		return(false);
	var arr = trklist.split("\n");
	$('#trackers').val('');
	var trk = '';
	for( var i in arr )
		trk+=(arr[i].trim()+'\r');
	plugin.deleteFromRecentTrackers = trk;
	theWebUI.request('?action=rtdelete');
	theWebUI.request('?action=rtget',[plugin.getRecentTrackers, plugin]);
}

plugin.onLangLoaded = function() {
	var plg = thePlugins.get("_task");
	if (!plg.allStuffLoaded)
		setTimeout(arguments.callee,1000);
	else {
		theWebUI.request('?action=rtget',[plugin.getRecentTrackers, plugin]);
		$('#tsk_btns').prepend(
			$("<button>").attr({type:"button", id:"xcsave"}).text(theUILang.torrentSave).hide(),
		);
		plugin.addButtonToToolbar("create",theUILang.mnu_create,"theWebUI.showCreate()","remove");
		plugin.addSeparatorToToolbar("remove");

		const pieceSizeArray = [32, 64, 128, 256, 512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];
		var pieceSize = $("<div>").addClass("row").append(
			$("<div>").addClass("col-md-2").append(
				$("<label>").attr({for: "piece_size", name: "lbl_piece_size", id: "lbl_piece_size"}).text(theUILang.PieceSize + ": "),
			),
			$("<div>").addClass("col-md-4").append(
				$("<select>").attr({id: "piece_size", name: "piece_size"}).addClass("flex-grow-1").append(
					...pieceSizeArray.map(ele => $("<option>").val(ele).text(
						ele < 1024 ? ele + theUILang.KB : (ele / 1024) + theUILang.MB
					)),
				),
			),
		);
		if(plugin.hidePieceSize)
			pieceSize = "";

		var hybridTorrent = $("<div>").addClass("col-md-4").append(
			$("<input>").attr({type: "checkbox", name: "hybrid", id: "hybrid"}),
			$("<label>").attr({for: "hybrid", id: "lbl_hybrid"}).text(theUILang.HybridTorrent),
		);
		if(plugin.hideHybrid)
			hybridTorrent = "";

		const tcreateContent = $("<div>").addClass("cont").append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.SelectSource),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-12").append(
						$("<input>").attr({type: "text", id: "path_edit", name: "path_edit", autocomplete: "off"}).addClass("flex-grow-1"),
					),
				),
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.TorrentProperties),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-2 align-self-start").append(
						$("<label>").attr({for: "trackers", name: "lbl_trackers", id: "lbl_trackers"}).text(theUILang.Trackers + ": "),
					),
					$("<div>").addClass("col-md-10").append(
						$("<textarea>").attr({id: "trackers", name: "trackers"}).addClass("flex-grow-1"),
					),
				),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-2").append(
						$("<label>").attr({for: "comment", name: "lbl_comment", id: "lbl_comment"}).text(theUILang.Comment + ": "),
					),
					$("<div>").addClass("col-md-10").append(
						$("<input>").attr({type: "text", id: "comment", name: "comment"}).addClass("flex-grow-1"),
					),
				),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-2").append(
						$("<label>").attr({for: "source", name: "lbl_source", id: "lbl_source"}).text(theUILang.source + ": "),
					),
					$("<div>").addClass("col-md-10").append(
						$("<input>").attr({type: "text", id: "source", name: "source"}).addClass("flex-grow-1"),
					),
				),
				pieceSize,
			),
			$("<fieldset>").append(
				$("<legend>").text(theUILang.Other),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-md-4").append(
						$("<input>").attr({type: "checkbox", name: "start_seeding", id: "start_seeding"}),
						$("<label>").attr({for: "start_seeding", id: "lbl_start_seeding"}).text(theUILang.StartSeeding),
					),
					$("<div>").addClass("col-md-4").append(
						$("<input>").attr({type: "checkbox", name: "private", id: "private"}),
						$("<label>").attr({for: "private", id: "lbl_private"}).text(theUILang.PrivateTorrent),
					),
					...hybridTorrent,
				),
			),
		);
		const tcreateButtons = $("<div>").addClass("buttons-list").append(
			$("<button>")
				.attr({type:"button", id:"recentTrackers"})
				.addClass("menuitem")
				.on("click", theWebUI.showRecentTrackers)
				.text(theUILang.recentTrackers + "..."),
			$("<button>")
				.attr({type:"button", id:"deleteFromRecentTrackers"})
				.addClass("me-auto")
				.on("click", theWebUI.deleteFromRecentTrackers)
				.text(theUILang.deleteFromRecentTrackers),
			$("<button>").attr({type:"button", id:"torrentCreate"}).text(theUILang.torrentCreate).on("click", theWebUI.checkCreate).addClass("OK"),
			$("<button>").attr({type:"button"}).addClass("Cancel").text(theUILang.Cancel),
		);
		theDialogManager.make("tcreate",theUILang.CreateNewTorrent,
			[tcreateContent, tcreateButtons],
			true
		);
		// toggle create torrent options before opening dialog window
		theDialogManager.setHandler("tcreate", "beforeShow", () => {
			if (plugin.recentTrackers) {
				const recent = plugin.recentTrackers;
				$("#trackers").val(recent.last_used.join("\r\n"));
				$(`#piece_size option[value=${recent.piece_size || 1024}]`).prop("selected", true);
				$("#start_seeding").prop("checked", iv(recent.start_seeding));
				$("#private").prop("checked", iv(recent.private_torrent));
				$("#hybrid").prop("checked", iv(recent.hybrid_torrent));
			}
		});

		$(document.body).append($("<iframe name='xcreatefrm'/>").css({visibility: "hidden"}).attr( { name: "xcreatefrm", id: "xcreatefrm" } ).width(0).height(0));
		$(document.body).append(
			$('<form action="plugins/create/action.php" id="xgetfile" method="post" target="xcreatefrm">'+
				'<input type="hidden" name="cmd" value="getfile">'+
				'<input type="hidden" name="no" id="xtaskno" value="0">'+
			'</form>').width(0).height(0),
		);
		$("#xcsave").on('click', () => $('#xgetfile').trigger('submit'));
		if (thePlugins.isInstalled("_getdir")) {
			new theWebUI.rDirBrowser("path_edit", true, 375);
		}
		plugin.markLoaded();
	}
};

plugin.onRemove = function()
{
	plugin.removeSeparatorFromToolbar("remove");
	plugin.removeButtonFromToolbar("create");
}

plugin.langLoaded = function()
{
	if(plugin.enabled)
		plugin.onLangLoaded();
}
