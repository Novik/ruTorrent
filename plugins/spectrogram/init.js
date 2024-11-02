plugin.loadLang();

if (plugin.canChangeMenu()) {
	plugin.createFileMenu = theWebUI.createFileMenu;
	theWebUI.createFileMenu = function(e, id) {
		if (plugin.createFileMenu.call(this, e, id)) {
			if (plugin.enabled && plugin.allStuffLoaded) {
				var fno = null;
				var table = this.getTable("fls");
				if ((table.selCount == 1)  && (theWebUI.dID.length==40)) {
					var fid = table.getFirstSelected();
					var ext = '';
					var s = table.getRawValue(fid,0);
					var pos = s.lastIndexOf(".");
					if (pos>0) {
						ext = s.substring(pos+1);
						s = s.substring(0,pos);
					}
					$('#soximgfile').val(s);
					if (this.settings["webui.fls.view"]) {
						var arr = fid.split('_f_');
						fno = arr[1];
					} else if (!this.dirs[this.dID].isDirectory(fid))
						fno = fid.substr(3);
					if ($.inArray( ext.toLowerCase(), plugin.extensions )==-1)
						fno = null;
				}
				theContextMenu.add( [theUILang.exsox,  (fno==null) ? null : "theWebUI.filesox('" + theWebUI.dID + "',"+fno+")"] );
			}
			return true;
		}
		return false;
	}

	theWebUI.filesox = function(hash,no) {
		this.startConsoleTask(
			"sox", plugin.name,
			{ "hash" : hash, "no" : no },
			{ noclose: true }
		);
	}

	plugin.onTaskShowLog = function(task,line,id,ndx) {
		if (id=='tskcmdlog') {
			if (line=="-=*=-") {
				plugin.gotImage = true;
				return '';
			}
		}
		return escapeHTML(line)+'<br>';
	}

	plugin.onTaskFinished = function(task,onBackground) {
		if (!onBackground) {
			if (plugin.gotImage) {
				$('#tskcmdlog').addClass('image-cont');
				$('#tskcmdlog').empty();
				$('#tskcmdlog').append(
					$("<div>").attr({id:"soxframe"}).addClass("soxframe").append(
						$("<img>").attr({
							src: `plugins/spectrogram/action.php?cmd=soxgetimage&no=${task.no}&file=${encodeURIComponent($('#soximgfile').val())}`,
							alt: "",
						}),
					),
				);
				$("#soxtaskno").val(task.no);
				$("#soxsave").on('click', () => {
					$("#soximgcmd").val("soxgetimage");
					$('#soxgetimg').trigger('submit');
				});
				$('.soxplay').show();
			} else
				$('.soxplay').hide();
		}
	}

	plugin.onTaskShowInterface = function(task) {
		plugin.gotImage = false;
	}

	plugin.onTaskHideInterface = function(task) {
		$('.soxplay').hide();
		$('#tskcmdlog').removeClass('image-cont');
	}
}

plugin.onLangLoaded = function() {
	if (!thePlugins.get("_task").allStuffLoaded)
		setTimeout(arguments.callee,1000);
	else {
		$(document.body).append($("<iframe name='soxplayfrm'/>").css({visibility: "hidden"}).attr( { name: "soxplayfrm", id: "soxplayfrm" } ).width(0).height(0));
		$(document.body).append(
			$('<form action="plugins/spectrogram/action.php" id="soxgetimg" method="post" target="soxplayfrm">'+
				'<input type="hidden" name="cmd" id="soximgcmd" value="soxgetimage">'+
				'<input type="hidden" name="no" id="soxtaskno" value="0">'+
				'<input type="hidden" name="file" id="soximgfile" value="frame">'+
				'</form>').width(0).height(0));
		plugin.markLoaded();
		$('#tsk_btns').prepend(
			$("<button>").attr({type:"button", id:"soxsave"}).addClass("soxplay").text(theUILang.exSave).hide(),
		);
	}
}

plugin.langLoaded = function() {
	if (plugin.enabled)
		plugin.onLangLoaded();
}
