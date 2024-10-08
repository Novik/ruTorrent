plugin.loadLang();
plugin.loadMainCSS();

function filterDir(ev) {
	const keyword = ev.currentTarget.value.toUpperCase();
	if (!keyword || keyword.length === 0) {
		$(ev.currentTarget).next().children().css("display", "");
		return;
	}
	$(ev.currentTarget).next().find(".rmenuitem").map((_, dir) => {
		const txtValue = dir.textContent || dir.innerText;
		dir.style.display = txtValue.toUpperCase().indexOf(keyword) > -1 ? "" : "none";
	});
}

theWebUI.rDirBrowser = class {
	constructor(edit_id, withFiles, height) {
		const self = this;
		this.edit = $('#' + edit_id).addClass("browseEdit").prop("autocomplete", "off").on(
			browser.isIE ? "focusin" : "focus", () => self.hide()
		);
		this.btn = $("<button>").attr(
			{type:"button", id:edit_id + "_btn"}
		).addClass("browseButton").text("...").on(
			"focus", (ev) => ev.currentTarget.blur()
		).on("click", () => self.toggle());
		this.edit.after(
			this.btn,
		);
		// add a handler to the containing dialog window / option page
		// to close the directory list along with it
		// 1. `id` of the containing dialog window
		const dlgId = this.btn.parents(".dlg-window").attr("id");
		// 2. add an after-hide handler
		theDialogManager.addHandler(dlgId, "afterHide", () =>self.hide());
		// 3. `id` of the containing option page
		const stgId = this.btn.parents(".stg_con").attr("id");
		// 4. add an after-hide handler if the button is within an option page
		if (!!stgId) {
			theOptionsSwitcher.addHandler(stgId, "afterHide", () => self.hide());
		}

		this.withFiles = withFiles;
		this.height = height;
		this.frame = $("<dialog>").attr({id: edit_id + "_frame"}).addClass("browseFrame").append(
			$("<input>").attr(
				{type: "text", placeholder:theUILang.typeToFilter}
			).addClass("filter-dir").on("input", filterDir),
		).on(
			"keydown", (ev) => {
				// some keyboard shortcuts for dir list operation
				switch (ev.key) {
					case "Escape": {
						ev.stopPropagation();
						if ($(ev.currentTarget).find(".filter-dir").val() === "") {
							this.hide();
						} else {
							$(ev.currentTarget).find(".filter-dir").val("").trigger("input");
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
					case "Home": {
						$(ev.currentTarget).find(".rmenuitem").first().trigger("click");
					} break;
					case "End": {
						$(ev.currentTarget).find(".rmenuitem").last().trigger("click");
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
		}).hide();
		$("#dir-container").append(this.frame);
	}

	requestDir() {
		const path = this.edit.val();
		if (path.length > 0 && !path.endsWith("/")) {
			this.edit.val(path.slice(0, path.lastIndexOf("/") + 1));
		}
		$.ajax(
			`plugins/_getdir/listdir.php?dir=${encodeURIComponent(this.edit.val())}&time=${(new Date()).getTime()}${this.withFiles ? "&withfiles=1" : ""}`,
			{
				success: (res) => {
					this.frame.find(".filter-dir").val("").trigger("focus");
					this.edit.val(res.path).data({cwd:res.path});
					this.frame.find(".rmenuobj").remove();
					this.frame.append(
						$("<div>").addClass("rmenuobj").append(
							...res.directories.map(ele => $("<div>").addClass("rmenuitem").text(ele + "/")),
							...(this.withFiles ? res.files : []).map(ele => $("<div>").addClass("rmenuitem").text(ele)),
						),
					);
					this.frame.find(".rmenuitem").on(
						"click", (ev) => this.selectItem(ev)
					).on(
						"dblclick", (ev) => (ev.currentTarget.innerText.endsWith("/")) ? this.requestDir() : this.hide()
					);
				},
				error: (res) => console.log(res),
			}
		);
	}

	selectItem(ev) {
		this.frame.find(".rmenuitem.active").removeClass("active");
		$(ev.currentTarget).addClass("active");
		this.edit.val(this.edit.data("cwd") + ev.target.innerText);
	}

	show() {
		const { top, left } = this.edit.offset();
		this.frame.css({
			left: left,
			top: top + this.edit.outerHeight(),
			width: this.edit.width(),
			height: this.height || "",
		}).show();
		this.requestDir();
		this.btn.text("X");
		theDialogManager.bringToTop(this.frame.attr("id"));
		this.edit.prop("read-only", true);
		return false;
	}

	hide() {
		if (this.frame.css("display") !== "none") {
			this.btn.text("...");
			this.edit.prop("read-only", false);
			this.frame.find(".filter-dir").val("");
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
