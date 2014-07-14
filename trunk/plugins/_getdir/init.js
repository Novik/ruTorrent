plugin.loadMainCSS();

theWebUI.rDirBrowser = function( dlg_id, edit_id, btn_id, frame_id, withFiles )
{
	this.edit = $('#'+edit_id);
	this.btn  = $('#'+btn_id);
	this.scriptName = withFiles ? "getfiles" : "getdirs";
	if(!frame_id)
		frame_id = edit_id+"_frame";
	var self = this;
	this.btn.val("...").on( "click", function() { return(self.toggle()); } ).addClass("browseButton");
	this.edit.prop("autocomplete", "off").on( browser.isIE ? "focusin" : "focus", function() { return(self.hide()); } ).addClass("browseEdit");
	this.frame = $("<iframe>").attr( {id: frame_id, src: ""} ).css({position: "absolute", width: 0, visibility: "hidden"}).addClass("browseFrame");
	this.dlg_id = dlg_id;
	$('#'+dlg_id).append( this.frame );
}

theWebUI.rDirBrowser.prototype.show = function()
{
	var x = this.edit[0].offsetLeft;
	var y = this.edit[0].offsetTop + this.edit[0].offsetHeight;
	var parent = this.edit[0].offsetParent;
	while( parent && parent.id != this.dlg_id )
	{
		x += parent.offsetLeft;
		y += parent.offsetTop;
		parent = parent.offsetParent;
	}
        this.frame.prop("src","plugins/_getdir/"+this.scriptName+".php?dir="+ encodeURIComponent(this.edit.val()) +
		"&btn=" + this.btn.attr("id") +
		"&edit=" + this.edit.attr("id") +
		"&frame=" + this.frame.attr("id") +
		"&time=" + (new Date()).getTime()).css(
		{
			visibility: "visible",
			left: x,
			top: y,
			width: this.edit.width()+2
		}).show();
	this.btn.val("X");
	theDialogManager.bringToTop(this.frame.attr("id"));
	this.edit.prop( "read-only", true );
	return(false);
}

theWebUI.rDirBrowser.prototype.hide = function()
{
        if(this.frame.css("visibility")!="hidden")
        {
	        this.btn.val("...");
		this.edit.prop( "read-only", false );
		this.frame.css( { visibility: "hidden" } );
		this.frame.hide().css( {width: 0} );	
	}
	return(false);
}

theWebUI.rDirBrowser.prototype.toggle = function()
{
	return((this.frame.css("visibility")!="hidden") ? this.hide() : this.show());
}

plugin.onRemove = function()
{
	$(".browseButton").remove();
	$(".browseFrame").remove();
	$(".browseEdit").prop("autocomplete", "on").off( browser.isIE ? "focusin" : "focus" );
}
