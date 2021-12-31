plugin.loadLang();
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

	update: function( ip, comment )
	{
		if(this.get(ip))
			this.info[ip].comment = comment;
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
				if(plugin.retrieveComments)
					peer.comment = info.comment;
				peer.name = info.host;
			}
		}
		return(peer.processed);
	}
};

plugin.config = theWebUI.config;
theWebUI.config = function()
{
	if(plugin.canChangeColumns())
	{
		if(plugin.retrieveCountry)
		{
			this.tables.prs.columns.unshift({text : 'Country', width : '120px', id: 'country', type : TYPE_STRING});
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
		if(plugin.retrieveComments)
			this.tables.prs.columns.push({text : 'Comment', width : '200px', id: 'comment', type : TYPE_STRING});
	}
	plugin.config.call(this);
	if((plugin.retrieveCountry || plugin.retrieveComments) && plugin.canChangeColumns())
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
			table.renameColumnById("comment",theUILang.commentName);
			table.oldFilesSortAlphaNumeric = table.sortAlphaNumeric;
			table.sortAlphaNumeric = function(x, y) 
			{
				if(this.getIdByCol(this.sIndex)=="country")
				{
				        var newX = { key: x.key, v: x.v, e: x.e };
			        	var newY = { key: y.key, v: y.v, e: y.e };

					var countryName = theUILang.country[x.v.substr(0,2)];
					if(countryName)
						newX.v = countryName+x.v.substr(2);
					countryName = theUILang.country[y.v.substr(0,2)];
					if(countryName)
						newY.v = countryName+y.v.substr(2);
					return(this.oldFilesSortAlphaNumeric(newX,newY));
				}
				return(this.oldFilesSortAlphaNumeric(x,y));
			}
		}       	
		else
			setTimeout(arguments.callee,1000);
	}
}

if(plugin.canChangeMenu() && plugin.retrieveComments)
{
	plugin.createPeerMenu = theWebUI.createPeerMenu;
   	theWebUI.createPeerMenu = function(e, id)
	{
		if(plugin.createPeerMenu.call(theWebUI, e, id))
		{
			if(plugin.enabled && plugin.allStuffLoaded)
			{
				var el = theContextMenu.get(theUILang.peerAdd);
				var selCount = theWebUI.getTable("prs").selCount;
				if(el && selCount)
				{
					theContextMenu.add(el, [theUILang.peerComment+'...',
						(this.isTorrentCommandEnabled('commentpeer',theWebUI.dID) && (selCount==1)) ? 
							"theDialogManager.show('cadd')" : null]);
				}
			}
			return(true);
		}
		return(false);
   	}

   	theWebUI.addNewComment = function()
   	{
		this.request("?action=addpeercomment", [plugin.peerCommentAdded, plugin]);
	}

	rTorrentStub.prototype.addpeercomment = function()
	{
		this.content = "ip="+plugin.ip+"&comment="+encodeURIComponent($('#peerComment').val());
		this.contentType = "application/x-www-form-urlencoded";
		this.mountPoint = "plugins/geoip/action.php";
		this.dataType = "json";
   	}

   	plugin.peerCommentAdded = function(data)
   	{
   		thePeersCache.update( data.ip, data.comment );
   		theWebUI.updatePeers();
   	}
}

plugin.onLangLoaded = function()
{
	if(plugin.retrieveComments)
	{
		theDialogManager.make("cadd",theUILang.peerComment,
			'<div class="content fxcaret">'+theUILang.peerCommentLabel+'<br><input type="text" id="peerComment" class="Textbox" maxlength="64"/></div>'+
			'<div class="aright buttons-list"><input type="button" class="OK Button" value="'+theUILang.ok+'" onclick="theWebUI.addNewComment();theDialogManager.hide(\'cadd\');return(false);" />'+
				'<input type="button" class="Cancel Button" value="'+theUILang.Cancel+'"/></div>',
			true);
		theDialogManager.setHandler('cadd','beforeShow',function()
		{
			var peer = theWebUI.peers[theWebUI.getTable("prs").getFirstSelected()];
			plugin.ip = peer.ip;
			$('#peerComment').val(peer.comment);
		});
	}
}

plugin.onRemove = function()
{
        if(plugin.retrieveCountry)
		theWebUI.getTable("prs").removeColumnById("country");
        if(plugin.retrieveComments)
		theWebUI.getTable("prs").removeColumnById("comment");
}
