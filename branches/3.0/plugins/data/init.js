plugin.loadLang();

if(plugin.enabled)
{
	plugin.config = theWebUI.config;
	theWebUI.config = function(data)
	{
		plugin.config.call(this,data);
		var oldDblClick = this.getTable("fls").ondblclick;
		this.getTable("fls").ondblclick = function(obj) 
		{
			if(theWebUI.dID!="")
			{
			        if(theWebUI.settings["webui.fls.view"])
			        	theWebUI.getData(theWebUI.dID,obj.id.substr(43));
			        else
			        {
					var lnk = this.getAttr(obj.id, "link");
		                	if(!lnk)
	        	        		theWebUI.getData(theWebUI.dID,obj.id.substr(3));
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
				theContextMenu.add([CMENU_SEP]);
				var fno = null;
				var table = this.getTable("fls");
				if(table.selCount == 1)
				{
			        	var fid = table.getFirstSelected();
					if(this.settings["webui.fls.view"])
						fno = fid.substr(43);
					else
						if(this.dirs[theWebUI.dID].getEntryPriority(fid))
							fno = fid.substr(3);
					if((fno!=null) && (this.files[theWebUI.dID][fno].size>=2147483647))
						fno = null;
				}
				theContextMenu.add( [theUILang.getData,  (fno==null) ? null : "theWebUI.getData('" + theWebUI.dID + "',"+fno+")"] );
				return(true);
			}
			return(false);
		}
	}
}

plugin.onLangLoaded = function()
{
	if(this.enabled)
	{
		$(document.body).append($("<iframe>").attr( { name: "datafrm", id: "datafrm" } ).width(0).height(0).load(function()
		{
		        $("#datahash").val('');
		        $("#datano").val('');
			var d = (this.contentDocument || this.contentWindow.document);
			if(d.location.href != "about:blank")
				try { eval(d.body.innerHTML); } catch(e) { log(d.body.innerHTML); }
		}));
		$(document.body).append(
			$('<form action="plugins/data/action.php" id="getdata" method="get" target="datafrm">'+
				'<input type="hidden" name="hash" id="datahash" value="">'+
				'<input type="hidden" name="no" id="datano" value="">'+
			'</form>').width(0).height(0));
        }
}