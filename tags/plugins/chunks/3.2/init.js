plugin.loadLang();

if(plugin.enabled)
{
	var CurrHash;
	plugin.loadMainCSS();

	plugin.updateDetails = theWebUI.updateDetails;
	theWebUI.updateDetails = function() {
		plugin.updateDetails.call(this);
		if (this.activeView == 'Chunks' ) {
			if (this.dID != "") {
				CurrHash = this.dID;
				this.request( "?action=getchunks", [this.drawChunks, this]);
			} else {
				this.clearChunks();
			}
		}
	} // updateDetails

	plugin.clearDetails = theWebUI.clearDetails;
	theWebUI.clearDetails = function() {
		plugin.clearDetails.call(theWebUI);
		if(plugin.enabled && plugin.allStuffLoaded)
			theWebUI.clearChunks();
	} // clearDetails


	theWebUI.drawChunks = function( d ) {
		var Chunks = theWebUI.Chunks;
		var Chunk, ChunkNum;
		var cWidth = iv( theWebUI.tEM.clientWidth ) * 2; // for one cell
		var tWidth = iv( Chunks.cTable.clientWidth ) * 0.8;
		this.clearChunks();
		if( (cWidth <= 0) || (tWidth <= 0) || !$type(d.chunks) ) {
			return;
		}
		var ChunkLen = d.chunks.length, tRow, tCell;
		// Calculate number of table columns
		var NumCols = Math.round( tWidth / cWidth );
		var NumRows = Math.ceil( ChunkLen / NumCols );

		// Make header with important data

		var tCell = Chunks.cTable.insertRow(-1).insertCell(-1);
		tCell.colSpan = NumCols;
		tCell.innerHTML = NumRows + " " + theUILang.chunksRows + ", " + NumCols + " ";
		tCell.innerHTML += theUILang.chunksColumns + ", " + d.tsize + " (";
		tCell.innerHTML += ( d.chunks.length ) + ") ";
		tCell.innerHTML += theUILang.chunksChunks + ", " + theUILang.chunksSize + ": ";
		tCell.innerHTML += ( d.size / 1024 ) + " " + theUILang.KB; // Chunk size
		tCell.className = "tHeader";
    
		for (var i=0; i < NumRows; i++) {
        		tRow = Chunks.cTable.insertRow(-1);
	        	for (var j=0; j < NumCols; j++) {
        	    		tCell = tRow.insertCell(-1);
            			Chunk = d.chunks.substr( i * NumCols + j, 1);
		        	ChunkNum = parseInt( Chunk, 16 );
	            		if ( Chunk != '0') {
        	        		tCell.innerHTML = Chunk;
            			} else {
					tCell.innerHTML = "&nbsp;";
				}
				tCell.className = "cCell Cell" + Chunk;
			}
		}

		d = null;
	} // drawChunks

	theWebUI.clearChunks = function() {
		var Chunks = theWebUI.Chunks;
		// Delete rows and columns from table
		$(Chunks.cTable).empty();		
	} // clearChunks
	
	rTorrentStub.prototype.getchunks = function() {
		var commands = ["d.get_bitfield", "d.get_chunk_size", "d.get_size_chunks"];
		for(var i in commands)
		{
			var cmd = new rXMLRPCCommand( commands[i] );
			cmd.addParameter("string",CurrHash);
	        	this.commands.push( cmd );
		}
	} // getchunks

	rTorrentStub.prototype.getchunksResponse = function(xml) {
		if(CurrHash!=theWebUI.dID)
		        return({});
		var datas = xml.getElementsByTagName('data');
		var data = datas[0];
		var values = data.getElementsByTagName('value');
		return( { chunks: this.getValue(values,1), size: this.getValue(values,3), tsize: this.getValue(values,5) } );
	}
}

plugin.onLangLoaded = function() {
	// Create "chunks" tab
	if ( this.enabled ) {
		theWebUI.Chunks = $("<div>").attr("id","Chunks").get(0);
		theWebUI.Chunks.cTable = $("<table>").addClass("tChunks").css("vertical-align","top").get(0);
		theWebUI.Chunks.appendChild( theWebUI.Chunks.cTable );
		plugin.attachPageToTabs(theWebUI.Chunks, theUILang.Chunks,"lcont");
		// Reference for calculating width of a table cell
		theWebUI.tEM = $("<div>").attr("id","tEM").get(0);
		theWebUI.tEM.appendChild(document.createTextNode('M'));
		theWebUI.Chunks.appendChild( theWebUI.tEM );
	}
}

plugin.onRemove = function()
{
	plugin.removePageFromTabs("Chunks");
}