
utWebUI.rDirBrowserLoaded = true;

function rDirBrowser( form, edit, btn, frame_id )
{
	this.form = form;
	this.edit = edit;
	this.btn  = btn;

	//this.btn.className = "Button";
	this.btn.value = "...";
	this.btn.DirBrowser = this;
	this.btn.onclick = function() { this.DirBrowser.toggleFrame(); }

	this.edit.setAttribute( "autocomplete", "off" );
	this.edit.DirBrowser = this;
	this.edit.readOnly = false;
	if( browser.isIE )
		this.edit.onfocusin = function() { if( this.DirBrowser.isFrameVisible() ) this.DirBrowser.hideFrame(); }
	else
		this.edit.onfocus   = function() { if( this.DirBrowser.isFrameVisible() ) this.DirBrowser.hideFrame(); }

	this.frame = document.createElement( "iframe" );
	this.frame.id = frame_id;
	this.frame.src = "";
	this.frame.style.visibility = "hidden";
	this.frame.style.display = "none";

	this.form.appendChild( this.frame );
}

rDirBrowser.prototype.showFrame = function()
{
	this.frame.src = "plugins/_getdir/getdir.php?dir="+ encodeURIComponent(this.edit.value) +
		"&btn=" + this.btn.id +
		"&edit=" + this.edit.id +
		"&frame=" + this.frame.id +
		"&time=" + (new Date()).getTime();
	var x = this.edit.offsetLeft;
	var y = this.edit.offsetTop + this.edit.offsetHeight;
	var parent = this.edit.offsetParent;
	while( parent && parent.id != this.form.id )
	{
		x += parent.offsetLeft;
		y += parent.offsetTop;
		parent = parent.offsetParent;
	}
	this.frame.style.left = x + "px";
	this.frame.style.top  = y + "px";
	var width = this.edit.offsetWidth - 2;
	this.btn.value = "X";
	this.frame.style.width = width + "px";
	this.frame.style.visibility = "visible";
	this.frame.style.display = "block";
	this.frame.style.zIndex = Drag.zindex++;
	this.edit.readOnly = true;
}

rDirBrowser.prototype.hideFrame = function()
{
	this.btn.value = "...";
	this.edit.readOnly = false;
	this.frame.style.visibility = "hidden";
	this.frame.style.display = "none";
}

rDirBrowser.prototype.isFrameVisible = function()
{
	return this.frame.style.visibility != "hidden";
}

rDirBrowser.prototype.toggleFrame = function()
{
	if( this.isFrameVisible() )
		this.hideFrame();
	else
		this.showFrame();
	return false;
}


