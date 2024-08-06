plugin.loadLang();
plugin.loadMainCSS();

function filterDir(ev) {
	const keyword = ev.currentTarget.value.toUpperCase();
	if (!keyword || keyword.length === 0) {
		$(ev.currentTarget).next().children().css("display", "");
		return;
	}
	const dirs = $(ev.currentTarget).next().children(".rmenuitem");
	for (let i = 0; i < dirs.length; i++) {
		if (dirs[i]) {
			const txtValue = dirs[i].textContent || dirs[i].innerText;
			dirs[i].style.display = txtValue.toUpperCase().indexOf(keyword) > -1 ? "" : "none";
		}
	}
}

theWebUI.rDirBrowser = class {
	constructor(dlg_id, edit_id, btn_id, frame_id, withFiles, height) {
		this.edit = $('#' + edit_id);
		this.btn = $('#' + btn_id);
		this.setButtonText("...");
		this.withFiles = withFiles;
		this.height = height;
		const self = this;
		this.btn.on("click", () => self.toggle()).addClass("browseButton");
		this.edit.prop("autocomplete", "off").on(browser.isIE ? "focusin" : "focus", function () { return self.hide(); }).addClass("browseEdit");
		this.frame = $("<div>").attr({id: edit_id + "_frame"}).addClass("browseFrame").append(
			$("<input>").attr({type: "text", placeholder:theUILang.typeToFilter}).addClass("filter-dir").on(
				"input", filterDir
			),
		).on(
			"keydown", (ev) => {
				// some keyboard shortcuts for dir list operation
				switch (ev.key) {
					case "Escape": {
						ev.stopPropagation();
						if ($(ev.currentTarget).children(".filter-dir").val() === "") {
							this.hide();
						} else {
							$(ev.currentTarget).children(".filter-dir").val("").trigger("input");
						}
					} break;
					case "ArrowDown": {
						const selector = "[style!='display: none;']";
						const itemList = $(ev.currentTarget).find(".rmenuitem" + selector);
						if (itemList.filter(".active").length === 0) {
							itemList.first().trigger("click");
						} else {
							itemList.filter(".active").nextAll(selector).first().trigger("click");
						}
					} break;
					case "ArrowUp": {
						const selector = "[style!='display: none;']";
						const itemList = $(ev.currentTarget).find(".rmenuitem" + selector);
						if (itemList.filter(".active").length === 0) {
							itemList.last().trigger("click");
						} else {
							itemList.filter(".active").prevAll(selector).first().trigger("click");
						}
					}	break;
					case "ArrowRight": {
						$(ev.currentTarget).find(".rmenuitem.active").trigger("dblclick");
					} break;
					case "ArrowLeft": {
						$(ev.currentTarget).find(".rmenuitem:contains('..')").trigger("click").trigger("dblclick");
					} break;
				}

				// scroll vertically if selected item is out of list container
				const selected = $(ev.currentTarget).find(".rmenuitem.active");
				if (selected.length === 0)
					return;
				const selPos = {
					top: selected.offset().top,
					bottom: selected.offset().top + selected.outerHeight(),
				};
				const container = $(ev.currentTarget).find(".rmenuobj");
				const contPos = {
					top: container.offset().top,
					bottom: container.offset().top + container[0].clientHeight,
				};
				const bottomDiff = selPos.bottom - contPos.bottom;
				if (bottomDiff > 0) {
					const topScroll = container.scrollTop();
					container.scrollTop(topScroll + bottomDiff);
					return;
				}
				const topDiff = selPos.top - contPos.top;
				if (topDiff < 0) {
					const topScroll = container.scrollTop();
					container.scrollTop(topScroll + topDiff);
					return;
				}
			}
		).hide();
		$("#iframe-container").append(this.frame);
	}

	requestDir() {
		$.ajax(
			`plugins/_getdir/listdir.php?dir=${encodeURIComponent(this.edit.val())}&time=${(new Date()).getTime()}${this.withFiles ? "&withfiles=1" : ""}`,
			{
				success: (res) => {
					this.frame.children(".filter-dir").val("").trigger("focus");
					this.edit.val(res.path).data({cwd:res.path});
					$(".rmenuobj").remove();
					this.frame.append(
						$("<div>").addClass("rmenuobj").append(
							...res.directories.map(ele => $("<div>").addClass("rmenuitem").text(ele + "/")),
							...(this.withFiles ? res.files : []).map(ele => $("<div>").addClass("rmenuitem").text(ele)),
						),
					);
					this.frame.children(".rmenuobj").on(
						"click", (ev) => this.selectItem(ev)
					).on(
						"dblclick", () => this.requestDir()
					);
				},
				error: (res) => console.log(res),
			}
		);
	}

	setButtonText(buttonText) {
		if (this.btn[0].tagName === "INPUT") {
			this.btn.val(buttonText);
		} else if (this.btn[0].tagName === "BUTTON") {
			this.btn.text(buttonText);
		}
	}

	selectItem(ev) {
		$(ev.target).parent().children(".active").removeClass("active");
		$(ev.target).addClass("active");
		this.edit.val(this.edit.data("cwd") + ev.target.innerText);
	}

	show() {
		const { top, left } = this.edit.offset();
		this.frame.css({
			left: left,
			top: top + this.edit.outerHeight(),
			width: this.edit.outerWidth(),
			height: this.height || "",
		}).show();
		this.requestDir();
		this.setButtonText("X");
		theDialogManager.bringToTop(this.frame.attr("id"));
		this.edit.prop("read-only", true);
		return false;
	}

	hide() {
		if (this.frame.css("display") !== "none") {
			this.setButtonText("...");
			this.edit.prop("read-only", false);
			this.frame.children(".filter-dir").val("");
			this.frame.hide();
		}
		return false;
	}

	toggle() {
		return (this.frame.css("display") !== "none") ? this.hide() : this.show();
	}
}

plugin.onLangLoaded = function() {
	$(".filter-dir:not([placeholder])").attr({placeholder:theUILang.typeToFilter});
}

plugin.onRemove = function() {
	$(".browseButton").remove();
	$(".browseFrame").remove();
	$(".browseEdit").prop("autocomplete", "on").off(browser.isIE ? "focusin" : "focus");
}
