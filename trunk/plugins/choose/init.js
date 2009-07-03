var plugin = new rPlugin("choose");
plugin.loadMainCSS();
if(browser.isOldIE)
	plugin.loadCSS("ie6");

var addForm = $$("addtorrent");
var btn = document.createElement("input");
btn.type = "button";
btn.value = "..."
btn.id = "browse";
btn.onclick = toggleFrame;
btn.className = 'Button';
var edit = $$("dir_edit");
if(browser.isIE)
	edit.onfocusin = function () { if(isFrameVisible()) hideFrame(); }
else
	edit.onfocus = function () { if(isFrameVisible()) hideFrame(); }
edit.setAttribute('autocomplete','off');
addForm.insertBefore(btn, edit.nextSibling);
var dv = document.createElement("iframe");
dv.id = "dir_frame";
dv.src = "";
dv.style.visibility = "hidden";
addForm.appendChild(dv);

function showFrame()
{
	dv.src = "plugins/choose/getdirs.php?dir="+encodeURIComponent(edit.value) + "&time=" + (new Date()).getTime();
	var x = edit.offsetLeft;
	var y = edit.offsetTop+edit.offsetHeight;
	var parent = edit.offsetParent;
	while(parent && parent.id!='tadd')
	{
		x+=parent.offsetLeft;
		y+=parent.offsetTop;
		parent = parent.offsetParent;
	}
	dv.style.left =  x + "px";
	dv.style.top = y + "px";
	var width = edit.offsetWidth-2;
	btn.value = "X";
	dv.style.width = width + "px";
	dv.style.visibility = "visible";
	dv.style.display = "block";
	dv.style.zIndex = Drag.zindex++; 
	edit.readOnly = true;
}

function hideFrame()
{
	dv.style.visibility = "hidden";
	dv.style.display = "none";
	btn.value = "...";
	edit.readOnly = false;
}

function isFrameVisible()
{
	return(dv.style.visibility != "hidden");
}

function toggleFrame()
{
	if(isFrameVisible())
	 	hideFrame();
	else
		showFrame();	
	return(false);
}
