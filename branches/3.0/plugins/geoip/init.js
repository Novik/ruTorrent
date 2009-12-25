plugin.loadLang();

if(plugin.enabled)
{
	plugin.loadMainCSS();

	if(plugin.canChangeColumns())
	{
		plugin.config = theWebUI.config;
		theWebUI.config = function(data)
		{
			this.tables.prs.columns.unshift({text : 'Country', width : '60px', id: 'country', type : TYPE_STRING});
			plugin.prsFormat = this.tables.prs.format;
			theWebUI.tables.prs.format = function(table,arr)
			{
				if(plugin.allStuffLoaded)
					for(var i in arr)
					{
						if(arr[i]==null)
							arr[i] = '';
						else
	   					if(table.getIdByCol(i)=="country")
	   					{
							var countryName = WUILang.country[arr[i]];
							if(countryName)
								arr[i] = countryName;
							break;
						}
		        		}
				return(plugin.prsFormat(table,arr));
			}
			plugin.config.call(this,data);
			plugin.done();
		}

		plugin.done = function()
		{
			if(plugin.allStuffLoaded)
			{
				var table = theWebUI.getTable("prs");
				table.renameColumnById("country",WUILang.countryName);
				table.oldFilesSortAlphaNumeric = table.sortAlphaNumeric;
				table.sortAlphaNumeric = function(x, y) 
				{
					if(this.getIdByCol(this.sIndex)=="country")
					{
					        var newX = { key: x.k, v: x.v, e: x.e };
				        	var newY = { key: y.k, v: y.v, e: y.e };		
						if(WUILang.country[x.v])
							newX.v = WUILang.country[x.v];
						if(WUILang.country[y.v])
							newY.v = WUILang.country[y.v];
						return(this.oldFilesSortAlphaNumeric(newX,newY));
					}
					return(this.oldFilesSortAlphaNumeric(x,y));
				}
			}       	
			else
				setTimeout(arguments.callee,1000);
		}
	}

	plugin.getpeersResponse = rTorrentStub.prototype.getpeersResponse;
	rTorrentStub.prototype.getpeersResponse = function(xml)
	{
		var ret = plugin.getpeersResponse.call(this,xml);
		$.each( ret, function(id,peer)
		{
	                var AjaxReq = jQuery.ajax(
			{
			        async : false,
				url : "plugins/geoip/lookup.php",
				data : { action : "geoip", ip : peer.ip },
				dataType : "text",
				success : function(data)
				{
					peer.country = data;
					peer.icon = "geoip_flag_"+data;
				}
			});
		});
		return(ret);
	}
}