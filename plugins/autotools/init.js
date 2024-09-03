plugin.loadLang();

if (plugin.canChangeOptions()) {
	plugin.addAndShowSettings = theWebUI.addAndShowSettings;
	theWebUI.addAndShowSettings = function(arg) {
		if (plugin.enabled) {
			$$('enable_label').checked = (theWebUI.autotools.EnableLabel === 1);
			$$('label_template').value = theWebUI.autotools.LabelTemplate;
			linked($$('enable_label'), 0, ['label_template']);
			$$('enable_move').checked  = (theWebUI.autotools.EnableMove === 1);
			$$('path_to_finished').value = theWebUI.autotools.PathToFinished;
			$$('skip_move_for_files').value = theWebUI.autotools.SkipMoveForFiles;
			linked($$('enable_move'), 0, ['automove_filter', 'path_to_finished', 'skip_move_for_files', 'automove_browse_btn', 'fileop_type', 'auto_add_label', 'auto_add_name']);
			$$('fileop_type').value = theWebUI.autotools.FileOpType;
			$$('enable_watch').checked  = (theWebUI.autotools.EnableWatch === 1);
			$$('path_to_watch').value = theWebUI.autotools.PathToWatch;
			linked($$('enable_watch'), 0, ['path_to_watch', 'autowatch_browse_btn', 'watch_start']);
			$$('watch_start').checked  = (theWebUI.autotools.WatchStart === 1);
			if (plugin.DirBrowser1)
				plugin.DirBrowser1.hide();
			if (plugin.DirBrowser2)
				plugin.DirBrowser2.hide();
			$$('automove_filter').value = theWebUI.autotools.MoveFilter;
			$$('auto_add_label').checked = (theWebUI.autotools.AddLabel === 1);
			$$('auto_add_name').checked = (theWebUI.autotools.AddName === 1);			
		}
		plugin.addAndShowSettings.call(theWebUI, arg);
	}

	theWebUI.autotoolsWasChanged = function() {
		if ($$('enable_label').checked !== (theWebUI.autotools.EnableLabel === 1))
			return true;
		if ($$('label_template').value !== theWebUI.autotools.LabelTemplate)
			return true;
		if ($$('enable_move').checked !== (theWebUI.autotools.EnableMove === 1))
			return true;
		if ($$('path_to_finished').value !== theWebUI.autotools.PathToFinished)
			return true;
		if ($$('skip_move_for_files').value !== theWebUI.autotools.SkipMoveForFiles)
			return true;
		if ($$('enable_watch').checked !== ( theWebUI.autotools.EnableWatch === 1))
			return true;
		if ($$('path_to_watch').value !== theWebUI.autotools.PathToWatch)
			return true;
		if ($$('fileop_type').value !== theWebUI.autotools.FileOpType)
			return true;
		if ($$('watch_start').checked !== (theWebUI.autotools.WatchStart === 1))
			return true;
		if ($$('automove_filter').value !== theWebUI.autotools.MoveFilter)
			return true;
		if ($$('auto_add_label').checked !== (theWebUI.autotools.AddLabel === 1))
			return true;
		if ($$('auto_add_name').checked !== (theWebUI.autotools.AddName === 1))
			return true;
		return false;
	}

	plugin.setSettings = theWebUI.setSettings;
	theWebUI.setSettings = function() {
		plugin.setSettings.call(this);
		if (plugin.enabled && this.autotoolsWasChanged())
			this.request("?action=setautotools");
	}

	rTorrentStub.prototype.setautotools = function() {
		this.content = "enable_label=" + ( $$('enable_label').checked ? '1' : '0' ) +
			"&label_template=" + $$('label_template').value +
			"&enable_move=" + ( $$('enable_move').checked  ? '1' : '0' ) +
			"&path_to_finished=" + $$('path_to_finished').value +
			"&skip_move_for_files=" + $$('skip_move_for_files').value +
			"&fileop_type=" + $$('fileop_type').value +
			"&enable_watch=" + ( $$('enable_watch').checked  ? '1' : '0' ) +
			"&add_label=" + ( $$('auto_add_label').checked  ? '1' : '0' ) +
			"&add_name=" + ( $$('auto_add_name').checked  ? '1' : '0' ) +
			"&path_to_watch=" + $$('path_to_watch').value +
			"&automove_filter=" + $$('automove_filter').value +			
			"&watch_start=" + ( $$('watch_start').checked  ? '1' : '0' );
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/autotools/action.php";
		this.dataType = "script";
	}
}

plugin.onLangLoaded = function() {
	if (this.canChangeOptions()) {
		const stgAutoTools = $("<div>").attr({id:"st_autotools"}).append(
			$("<fieldset>").append(
				$("<legend>").text(theUILang.autotools),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-1 justify-content-md-end").append(
						$("<input>").attr({type:"checkbox", id:"enable_label", onchange:"linked(this, 0, ['label_template']);"}),
					),
					$("<div>").addClass("col-11").append(
						$("<label>").attr({for:"enable_label"}).text(theUILang.autotoolsEnableLabel),
					),
					$("<div>").addClass("col-11 offset-1").append(
						$("<input>").attr({type:"text", id:"label_template", maxlength:100}),
					),
				),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-1 justify-content-md-end").append(
						$("<input>").attr({type:"checkbox", id:"enable_move", onchange:"linked(this, 0, ['automove_filter', 'skip_move_for_files', 'path_to_finished', 'automove_browse_btn', 'fileop_type', 'auto_add_label', 'auto_add_name']);"}),
					),
					$("<div>").addClass("col-11").append(
						$("<label>").attr({for:"enable_move"}).text(theUILang.autotoolsEnableMove),
					),
					$("<div>").addClass("col-11 offset-1").append(
						$("<input>").attr({type:"text", id:"automove_filter", maxlength:200}),
					),
					$("<div>").addClass("col-11 offset-1").append(
						$("<label>").attr({id:"lbl_skip_move_for_files", for:"skip_move_for_files"}).addClass("disabled").text(theUILang.autotoolsSkipMoveForFiles),
					),
					$("<div>").addClass("col-11 offset-1").append(
						$("<input>").attr({type:"text", id:"skip_move_for_files", maxlength:30}),
					),
					$("<div>").addClass("col-11 offset-1").append(
						$("<label>").attr({id:"lbl_path_to_finished", for:"path_to_finished"}).addClass("disabled").text(theUILang.autotoolsPathToFinished + ": "),
					),
					$("<div>").addClass("col-11 offset-1").append(
						$("<input>").attr({type:"text", id:"path_to_finished", maxlength:100}),
						$("<button>").attr({type:"button", id:"automove_browse_btn"}).addClass("browseButton").text("..."),
					),
					$("<div>").addClass("col-11 col-md-5 offset-1").append(
						$("<label>").attr({id:"lbl_fileop_type", for:"fileop_type"}).addClass("disabled").text(theUILang.autotoolsFileOpType + ": "),
					),
					$("<div>").addClass("col-11 offset-1 col-md-6 offset-md-0").append(
						$("<select>").attr({id:"fileop_type"}).addClass("disabled").append(
							...[
								["Move", theUILang.autotoolsFileOpMove],
								["HardLink", theUILang.autotoolsFileOpHardLink],
								["Copy", theUILang.autotoolsFileOpCopy],
								["SoftLink", theUILang.autotoolsFileOpSoftLink],
							].map(([value, text]) => $("<option>").val(value).text(text)),
						),
					),
					$("<div>").addClass("col-11 offset-1 col-md-5 checkbox").append(
						$("<input>").attr({type:"checkbox", id:"auto_add_label"}),
						$("<label>").attr({id:"lbl_auto_add_label", for:"auto_add_label"}).text(theUILang.autotoolsAddLabel),
					),
					$("<div>").addClass("col-11 offset-1 col-md-6 offset-md-0 checkbox").append(
						$("<input>").attr({type:"checkbox", id:"auto_add_name"}),
						$("<label>").attr({id:"lbl_auto_add_name", for:"auto_add_name"}).text(theUILang.autotoolsAddName),
					),
				),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-1 justify-content-md-end").append(
						$("<input>").attr({type:"checkbox", id:"enable_watch", onchange:"linked(this, 0, ['path_to_watch', 'autowatch_browse_btn', 'watch_start']);"}),
					),
					$("<div>").addClass("col-11 col-md-5").append(
						$("<label>").attr({for:"enable_watch"}).text(theUILang.autotoolsEnableWatch),
					),
				),
				$("<div>").addClass("row").append(
					$("<div>").addClass("col-11 col-md-5 offset-1").append(
						$("<label>").attr({id:"lbl_path_to_watch", for:"path_to_watch"}).addClass("disabled").text(theUILang.autotoolsPathToWatch + ": "),
					),
					$("<div>").addClass("col-11 offset-1 col-md-6 offset-md-0").append(
						$("<input>").attr({type:"text", id:"path_to_watch", maxlength:100}),
						$("<button>").attr({type:"button", id:"autowatch_browse_btn"}).addClass("browseButton").text("..."),
					),
					$("<div>").addClass("col-11 col-md-5 offset-1").append(
						$("<input>").attr({type:"checkbox", id:"watch_start"}),
						$("<label>").attr({id:"lbl_watch_start", for:"watch_start"}).addClass("disabled").text(theUILang.autotoolsWatchStart),
					),
				),
			),
		);
		this.attachPageToOptions(
			stgAutoTools.get(0),
			theUILang.autotools,
		);
		if (thePlugins.isInstalled("_getdir")) {
			this.DirBrowser1 = new theWebUI.rDirBrowser("stg", "path_to_finished", "automove_browse_btn", "automove_browse_frame");
			this.DirBrowser2 = new theWebUI.rDirBrowser("stg", "path_to_watch", "autowatch_browse_btn", "autowatch_browse_frame");
		} else {
			$('#automove_browse_btn').remove();
			$('#autowatch_browse_btn').remove();
		}
	}
}

plugin.onRemove = function() {
	this.removePageFromOptions("st_autotools");
}
