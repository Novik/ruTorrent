plugin.loadLang();
plugin.loadMainCSS();

plugin.updateDetails = theWebUI.updateDetails;
theWebUI.updateDetails = function() 
{
	plugin.updateDetails.call(this);
	if((this.activeView == 'Chunks') && plugin.enabled && plugin.allStuffLoaded)
	{
		if (this.dID != "") 
		{
			plugin.hash = this.dID;
			this.request( "?action=getchunks", [plugin.drawChunks, plugin]);
		} 
		else
			plugin.clearChunks();
	}
}

plugin.clearDetails = theWebUI.clearDetails;
theWebUI.clearDetails = function() 
{
	plugin.clearDetails.call(theWebUI);
	if(plugin.enabled && plugin.allStuffLoaded)
		plugin.clearChunks();
}

plugin.drawChunks = function( d ) 
{
	if( $('#cCont').get(0).clientWidth && ($type(d.chunks) || $type(d.seen)) )
	{
		var mode = iv($('#chunks_mode').val());
		var cells = mode ? d.seen : d.chunks;
		var cellsCount = mode ? d.seen.length / 2 : d.chunks.length;
		var numCols = Math.floor( $('#cCont').get(0).clientWidth / 22 );
		var numRows = Math.ceil( cellsCount / numCols );
		var table = $('#cTable').get(0);
		var mustInsert = (cellsCount!=plugin.cellsCount) || (numRows!=table.rows.length) || (numCols!=plugin.numCols);
		if(mustInsert)
			plugin.clearChunks();

		$('#ccount').text(d.tsize);
		$('#csize').text(( d.size / 1024 ) + " " + theUILang.KB);
		$('#cinfohdr').text( (mode ? theUILang.cAvail : theUILang.cDownloaded)+':' );
		if(!mode)
			$('#cinfo').text( theWebUI.torrents[plugin.hash].done/10+'%' );
		$('#clegend').text( theUILang.cLegendVal[mode] );

		var k = mode;
		var sumAvail = 0;
		var sumBitAvail = 0;
		for(var i=0; i < numRows; i++) 
		{
			var tRow = mustInsert ? table.insertRow(-1) : table.rows[i];
			for(var j=0; j < numCols && (k<cells.length); j++, k++) 
			{
	    			var tCell = mustInsert ? tRow.insertCell(-1) : tRow.cells[j];
				var chunk = cells.charAt( k );
				if( chunk != '0')
	        			tCell.innerHTML = chunk;
    				else
					tCell.innerHTML = "&nbsp;";
				tCell.className = "cCell Cell" + chunk;
				if(mode) 
				{
					var val = parseInt(chunk,16);
					sumAvail+=val;
					if(val)
						sumBitAvail++;
					k++;
				}
			}
		}
		if(mode)
			$('#cinfo').text( theConverter.round((sumBitAvail==d.tsize) ? sumAvail/d.tsize : sumBitAvail/d.tsize,2) );
		plugin.cellsCount = cellsCount;
		plugin.numCols = numCols;

	}
	else
		plugin.clearChunks();
	d = null;
}

plugin.clearChunks = function() 
{
	$('#cTable').empty();
	$('#ccount').text('');
	$('#csize').text('');
	$('#cinfohdr').text( '' );
	$('#cinfo').text( '' );
	$('#clegend').text( '' );
}

rTorrentStub.prototype.getchunks = function() 
{
	var commands = ["d.get_bitfield", "d.get_chunk_size", "d.get_size_chunks"];
	if(theWebUI.systemInfo.rTorrent.apiVersion>=4)
		commands.push("d.chunks_seen");
	for(var i in commands)
	{
		var cmd = new rXMLRPCCommand( commands[i] );
		cmd.addParameter("string",plugin.hash);
        	this.commands.push( cmd );
	}
}

rTorrentStub.prototype.getchunksResponse = function(xml) 
{
	if(plugin.hash!=theWebUI.dID)
	        return({});
	var datas = xml.getElementsByTagName('data');
	var data = datas[0];
	var values = data.getElementsByTagName('value');
	var ret = { chunks: this.getValue(values,1), size: this.getValue(values,3), tsize: this.getValue(values,5) }
	if(theWebUI.systemInfo.rTorrent.apiVersion>=4)
		ret.seen = this.getValue(values,7);
	return(ret);
}

plugin.resizeBottom = theWebUI.resizeBottom;
theWebUI.resizeBottom = function( w, h )
{
	if(plugin.enabled) 
	{
	        if(theWebUI.configured)
	        {
	        	if(h)
		        	$('#cCont').height(h-50);
		}
		else
			setTimeout( 'theWebUI.resize()', 1000 );
	}
	plugin.resizeBottom.call(this,w,h);
}

plugin.onLangLoaded = function() 
{
	plugin.attachPageToTabs(
		$("<div>").attr("id","Chunks").append(
			$("<div>").attr("id","cHeader").html( 
				"<table width='100%'><tr>"+
				"<td class='sthdr'>"+theUILang.chunksCount+":</td>"+
				"<td class='stval' id='ccount'>&nbsp;</td>"+
				"<td class='sthdr'>"+theUILang.chunkSize+":</td>"+
				"<td class='stval' id='csize'>&nbsp;</td>"+
				"<td class='sthdr' id='cinfohdr'>"+theUILang.cDownloaded+":</td>"+
				"<td class='stval' id='cinfo'>&nbsp;</td>"+
				"<td class='sthdr'>"+theUILang.cLegend+":</td>"+
				"<td class='stval' id='clegend'>&nbsp;</td>"+
				"<td align='right' id='cmode_cont' class='sthdr'>"+
					theUILang.cMode+":&nbsp;"+
					"<select name='chunks_mode' id='chunks_mode' onchange='theWebUI.updateDetails()'>"+
						"<option value='0' selected>"+theUILang.cDownloaded+"</option>"+
						"<option value='1'>"+theUILang.cAvail+"</option>"+
					"</select>"+
				"</td></tr></table>").append(
			$("<div>").attr("id","cCont").append( 
				$("<table>").attr("id","cTable")))).get(0), theUILang.Chunks,"lcont");
	if(theWebUI.systemInfo.rTorrent.apiVersion<4)
		$('#cmode_cont').empty();
}

plugin.onRemove = function()
{
	plugin.removePageFromTabs("Chunks");
}