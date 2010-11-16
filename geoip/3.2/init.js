plugin.loadLang();

if(plugin.enabled)
{
	plugin.loadMainCSS();

	var thePeersCache = 
	{
		MAX_SIZE: 1024,
		ips: [],
		info: {},

		add: function( data )
		{
			for( var i = 0; i< data.length; i++ )
			{
				this.ips.push(data[i].ip);
				this.info[data[i].ip] = data[i].info;
			}
		},

		strip: function()
		{
			if(this.ips.length>=this.MAX_SIZE)
			{
				for(var i=0; i<this.MAX_SIZE/2; i++)
					delete this.info[this.ips[i]];
				this.ips.splice(0,this.MAX_SIZE/2);
			}
		},

		get: function( ip )
		{
			return( $type(this.info[ip]) ? this.info[ip] : null );
		},

		fill: function(peer)
		{
		        if(!peer.processed)
		        {
		                var info = this.get(peer.ip);
		                if(info)
	        	        {
	                	        peer.processed = true;
	                	        if(plugin.retrieveCountry)
	                	        {
						peer.country = info.country;
						peer.icon = "geoip geoip_flag_"+peer.country.substr(0,2);
					}
					peer.name = info.host;
				}
			}
			return(peer.processed);
		}
	};

	plugin.config = theWebUI.config;
	theWebUI.config = function(data)
	{
		if(plugin.retrieveCountry && plugin.canChangeColumns())
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
							var countryName = theUILang.country[arr[i].substr(0,2)];
							if(countryName)
								arr[i] = countryName+arr[i].substr(2);
							break;
						}
		        		}
				return(plugin.prsFormat(table,arr));
			}
		}
		plugin.config.call(this,data);
		if(plugin.retrieveCountry && plugin.canChangeColumns())
			plugin.done();
	}

	plugin.getpeersResponse = rTorrentStub.prototype.getpeersResponse;
	rTorrentStub.prototype.getpeersResponse = function(xml)
	{
		var peers = plugin.getpeersResponse.call(this,xml);
		if(plugin.enabled)
		{
			var content = "";
			$.each( peers, function(id,peer)
			{
				if(!thePeersCache.fill(peer))
					content += ("&ip="+peer.ip);
			});
			if(content.length)
			{
				var AjaxReq = jQuery.ajax(
				{
					type: "POST",
					contentType: "application/x-www-form-urlencoded",
					processData: false,
					timeout: theWebUI.settings["webui.reqtimeout"],
				        async : false,
					url : "plugins/geoip/lookup.php",
					data : "dummy=1"+content,
					dataType : "json",
					cache: false,
					success : function(data)
					{
						thePeersCache.add(data);
					}
				});
				$.each( peers, function(id,peer)
				{
					thePeersCache.fill(peer);
				});
				thePeersCache.strip();
			}
		}
		return(peers);
	}

	if(plugin.canChangeColumns())
	{
		plugin.done = function()
		{
			if(plugin.allStuffLoaded)
			{
				var table = theWebUI.getTable("prs");
				table.renameColumnById("country",theUILang.countryName);
				table.oldFilesSortAlphaNumeric = table.sortAlphaNumeric;
				table.sortAlphaNumeric = function(x, y) 
				{
					if(this.getIdByCol(this.sIndex)=="country")
					{
					        var newX = { key: x.k, v: x.v, e: x.e };
				        	var newY = { key: y.k, v: y.v, e: y.e };		
						if(theUILang.country[x.v])
							newX.v = theUILang.country[x.v];
						if(theUILang.country[y.v])
							newY.v = theUILang.country[y.v];
						return(this.oldFilesSortAlphaNumeric(newX,newY));
					}
					return(this.oldFilesSortAlphaNumeric(x,y));
				}
			}       	
			else
				setTimeout(arguments.callee,1000);
		}
	}
}

plugin.onRemove = function()
{
        if(plugin.retrieveCountry)
		theWebUI.getTable("prs").removeColumnById("country");
}
