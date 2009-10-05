rBrowseButton = function(aDlg,aEdit,aBtn,aPlugName)
{
	this.dlg = aDlg;
	this.edit = aEdit;
	this.plugName = aPlugName;
	this.btn = aBtn;
	this.btn.value = "..."
	var self = this;
	this.btn.onclick = function() { self.toggleFrame(); return(false); };
	this.btn.className = 'Button';
	if(browser.isIE)
		this.edit.onfocusin = function () { if(self.isFrameVisible()) self.hideFrame(); }
	else
		this.edit.onfocus = function () { if(self.isFrameVisible()) self.hideFrame(); }
	this.edit.setAttribute('autocomplete','off');
	this.frm = document.createElement("iframe");
	this.frm.id = aEdit.id+"frame";
	this.frm.src = "";
	this.frm.style.visibility = "hidden";
	this.frm.className = "hiddenFrame";
	this.dlg.appendChild(this.frm);
}

rBrowseButton.prototype.isFrameVisible = function()
{
	return(this.frm.style.visibility != "hidden");
}

rBrowseButton.prototype.showFrame = function()
{
	this.frm.src = "plugins/"+this.plugName+"/getdirs.php?dir="+encodeURIComponent(this.edit.value)+"&btn="+this.btn.id+"&edt="+this.edit.id+"&time=" + (new Date()).getTime();
	var x = this.edit.offsetLeft;
	var y = this.edit.offsetTop+this.edit.offsetHeight;
	var parent = this.edit.offsetParent;
	while(parent && parent.id!=this.dlg.id)
	{
		x+=parent.offsetLeft;
		y+=parent.offsetTop;
		parent = parent.offsetParent;
	}
	this.frm.style.left =  x + "px";
	this.frm.style.top = y + "px";
	var width = this.edit.offsetWidth-2;
	this.btn.value = "X";
	this.frm.style.width = width + "px";
	this.frm.style.visibility = "visible";
	this.frm.style.display = "block";
	this.edit.readOnly = true;
}

rBrowseButton.prototype.hideFrame = function()
{
	this.frm.style.visibility = "hidden";
	this.frm.style.display = "none";
	this.btn.value = "...";
	this.edit.readOnly = false;
}

rBrowseButton.prototype.toggleFrame = function()
{
	if(this.isFrameVisible())
	 	this.hideFrame();
	else
		this.showFrame();
}

utWebUI.MarkRSSLoaded();
