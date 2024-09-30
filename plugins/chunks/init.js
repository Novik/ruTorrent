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

rTorrentStub.prototype.getchunksParseXML = function(xml)
{
	if(plugin.hash!=theWebUI.dID)
		return({});
	const values = this.getXMLValues(xml, 2, 1)[0];
	var ret = { chunks: values[0], size: values[1], tsize: values[2] }
	if(theWebUI.systemInfo.rTorrent.apiVersion>=4)
		ret.seen = values[3];
	return(ret);
}

plugin.onLangLoaded = function() {
	plugin.attachPageToTabs(
		$("<div>").attr("id","Chunks").append(
			$("<div>").addClass("d-flex flex-row justify-content-between align-items-center").append(
				...[
					[theUILang.chunksCount, , "ccount"],
					[theUILang.chunkSize, , "csize"],
					[theUILang.cDownloaded, "cinfohdr", "cinfo"],
					[theUILang.cLegend, , "clegend"],
				].flatMap(([headerName, headerId, valueId]) => [
					$("<div>").addClass("sthdr").append(
						$("<span>").attr({id:headerId}).text(headerName + ":"),
					),
					$("<div>").addClass("stval").append(
						$("<span>").attr({id:valueId}).text(""),
					),
				]),
				$("<div>").addClass("sthdr").append(
					$("<span>").attr({id:"cmode_cont"}).text(theUILang.cMode + ":"),
				),
				$("<div>").addClass("stval").append(
					$("<select>").attr({name:"chunks_mode", id:"chunks_mode", onchange:"theWebUI.updateDetails();"}).append(
						$("<option>").val(0).text(theUILang.cDownloaded),
						$("<option>").val(1).text(theUILang.cAvail),
					),
				),
			),
			$("<div>").attr("id","cCont").append( 
				$("<table>").attr("id","cTable")
			)
		).get(0),
		theUILang.Chunks,
		"lcont",
	);
	if(theWebUI.systemInfo.rTorrent.apiVersion<4)
		$('#cmode_cont').empty();
}

plugin.onRemove = function()
{
	plugin.removePageFromTabs("Chunks");
}
