
plugin.loadLang();


if (plugin.enabled) {
	var g_cache_host_addr = new Array();
	var g_cache_host_name = new Array();
	var g_cache_host_time = new Array();

	/* SETTINGS */
	var g_cache_size = 1024; /* Max count of cache items. */


	function resolve_host_name_success(data) {
		var res = data.split("<|>");
		cache_add(res[0], res[1]);
		console.log("Resolved host name for address:", res[0], "is", res[1]);
	}

	function resolve_host_name_failure(XMLHttpRequest, textStatus, errorThrown) {
		console.log("Fail to resolve host name:", textStatus, errorThrown);
	}

	function resolve_host_name(host_addr) {
		var jqAjaxReq;

		jqAjaxReq = jQuery.ajax( {
		  type: "POST",
		  processData: true,
		  contentType: "application/x-www-form-urlencoded",
		  async : true,
		  cache : false,
		  timeout: theWebUI.settings["webui.reqtimeout"],
		  url : "plugins/hostname/lookup.php",
		  data : { ip : host_addr},
		  dataType : "text",
		  success : resolve_host_name_success,
		  error: resolve_host_name_failure } );
	}

	/* Delete oldest item from cache. */
	function cache_delete_oldest() {
		var i, count = g_cache_host_addr.length;
		var old_idx, old_time, ttime;

		if (count <= g_cache_size)
			return;

		old_idx = 0;
		old_time = g_cache_host_time[0];
		for (i = 1; i < count; i ++) {
			ttime = g_cache_host_time[i];
			if (ttime < old_time) {
				old_time = ttime;
				old_idx = i;
			}
		}

		g_cache_host_addr.splice(old_idx, 1);
		g_cache_host_name.splice(old_idx, 1);
		g_cache_host_time.splice(old_idx, 1);

		return (old_idx);
	}

	/* Bin search. */
	function cache_find(val) {
		var m = 0, lft = 0, rt = g_cache_host_addr.length;

		if (rt == 0)
			return [0, 0];
		rt --;
		while (lft <= rt) {
			m = ((lft + rt) >>> 1);
			if (g_cache_host_addr[m].toString() == val.toString())
				return [1, m]; /* Found! */
			if (g_cache_host_addr[m].toString() > val.toString()) {
				rt = (m - 1);
			} else {
				lft = (m + 1);
			}
		}
		return [0, m];
	}

	function cache_add(host_addr, host_name) {
		var found, index, tTime = new Date();

		if (host_name == null) {
			host_name = "-";
		}

		/* Make sure that we have space. */
		cache_delete_oldest();

		[found, index] = cache_find(host_addr);
		if (found == 1) {
			/* Update existing. */
			/* g_cache_host_addr[index] = host_addr; Allready set. */
			g_cache_host_name[index] = host_name;
			g_cache_host_time[index] = tTime.getTime();
			return (index);
		}

		/* Add new item. */
		if (g_cache_host_addr.length > 0 &&
		    g_cache_host_addr.length > index &&
		    g_cache_host_addr[index].toString() < host_addr.toString()) {
			index ++;
		}
		g_cache_host_addr.splice(index, 0, host_addr);
		g_cache_host_name.splice(index, 0, host_name);
		g_cache_host_time.splice(index, 0, tTime.getTime());

		return (index);
	}

	function cache_get_host_name(host_addr) {
		var host_name, found, index, jqAjaxReq = false;
		var tTime = new Date();

		[found, index] = cache_find(host_addr);
		if (found == 0) {
			/* First time add. */
			cache_add(host_addr, "-");
			resolve_host_name(host_addr);
			return ("..."); /* Mean: resolve started. */
		}

		host_name = g_cache_host_name[index];
		if (host_name == null) {
			host_name = "-";
			g_cache_host_name[index] = "-";
		}
		if (host_name == "-") {
			/* Resolve in process. */
			if ((tTime.getTime() - g_cache_host_time[index]) > theWebUI.settings["webui.reqtimeout"]) {
				/* Retry resolve. */
				g_cache_host_time[index] = tTime.getTime();
				resolve_host_name(host_addr);
			}
		} else {
			/* Update cache item time. */
			g_cache_host_time[index] = tTime.getTime();
		}

		return (host_name);
	}

	plugin.config = theWebUI.config;
	theWebUI.config = function(data) {
		if (plugin.canChangeColumns()) {
			this.tables.prs.columns.unshift({
			  text : 'hostname',
			  width : '80px',
			  id: 'hostname',
			  type : TYPE_STRING});
		}
		plugin.config.call(this, data);
		plugin.done();
	}

	plugin.getpeersResponse = rTorrentStub.prototype.getpeersResponse;
	rTorrentStub.prototype.getpeersResponse = function(xml) {
		var peers = plugin.getpeersResponse.call(this, xml);
		if (plugin.enabled) {
			$.each(peers, function(id, peer) {
				peer.hostname = cache_get_host_name(peer.ip);
			});
		}
		return (peers);
	}

	if (plugin.canChangeColumns()) {
		plugin.done = function() {
			if (plugin.allStuffLoaded) {
				var table = theWebUI.getTable("prs");
				table.renameColumnById("hostname",
				  theUILang.HostName);
			} else {
				setTimeout(arguments.callee, 1000);
			}
		}
	}
}

plugin.onRemove = function() {
	if (plugin.retrieveCountry) {
		theWebUI.getTable("prs").removeColumnById("hostname");
	}
}
