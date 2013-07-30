plugin.loadLang();

plugin.config = theWebUI.config;
theWebUI.config = function(data)
{
	plugin.config.call(this,data);
	var oldDblClick = this.getTable("fls").ondblclick;
	this.getTable("fls").ondblclick = function(obj) 
	{
		if(plugin.enabled && (theWebUI.dID!="") && (theWebUI.dID.length==40))
		{
		        if(theWebUI.settings["webui.fls.view"])
		        {
				var arr = obj.id.split('_f_');
		        	theWebUI.getData(theWebUI.dID,arr[1]);
		        	return(false);
			}
		        else
		        {
				var lnk = this.getAttr(obj.id, "link");
	                	if(lnk==null)
	                	{
        	        		theWebUI.getData(theWebUI.dID,obj.id.substr(3));
					return(false);
	        		}
			}
		}
		return(oldDblClick.call(this,obj));
	}
}

theWebUI.getData = function( hash, no )
{
	$("#datahash").val(hash);
	$("#datano").val(no);
	$("#getdata").submit();
}

if(plugin.canChangeMenu())
{
	plugin.createFileMenu = theWebUI.createFileMenu;
	theWebUI.createFileMenu = function( e, id )
	{
		if(plugin.createFileMenu.call(this, e, id))
		{
		        if(plugin.enabled)
		        {
				theContextMenu.add([CMENU_SEP]);
				var fno = null;
				var table = this.getTable("fls");
				if(table.selCount == 1)
				{
		        		var fid = table.getFirstSelected();
					if(this.settings["webui.fls.view"])
					{
						var arr = fid.split('_f_');
						fno = arr[1];
					}
					else
						if(!this.dirs[this.dID].isDirectory(fid))
							fno = fid.substr(3);
					if( 
//						((fno!=null) && (this.files[this.dID][fno].size>=2147483647) && !theWebUI.systemInfo.php.canHandleBigFiles) || 
						(theWebUI.dID.length>40))
						fno = null;
				}
				theContextMenu.add( [theUILang.getData,  (fno==null) ? null : "theWebUI.getData('" + theWebUI.dID + "',"+fno+")"] );
			}
			return(true);
		}
		return(false);
	}
}

plugin.onLangLoaded = function()
{
	$(document.body).append($("<iframe name='datafrm'/>").css({visibility: "hidden"}).attr( { name: "datafrm", id: "datafrm" } ).width(0).height(0).load(function()
	{
	        $("#datahash").val('');
	        $("#datano").val('');
		var d = (this.contentDocument || this.contentWindow.document);
		if(d && (d.location.href != "about:blank"))
			try { eval(d.body.textContent ? d.body.textContent : d.body.innerText); } catch(e) {}
	}));
	$(document.body).append(
		$('<form action="plugins/data/action.php" id="getdata" method="get" target="datafrm">'+
			'<input type="hidden" name="hash" id="datahash" value="">'+
			'<input type="hidden" name="no" id="datano" value="">'+
		'</form>').width(0).height(0));
}

plugin.onRemove = function()
{
	$("#datafrm").remove();
	$("#getdata").remove();
}