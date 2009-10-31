var cplugin = new rPlugin("chunks");
var CurrHash;

cplugin.loadLanguages();
cplugin.loadMainCSS();

utWebUI.ChunksInitDone = utWebUI.initDone;

function ChunkUpdateDetails() {
    utWebUI.ChunksUpdateDetails();
    utWebUI.updateChunks();
} // updateDetails


function ChunkClearDetails(id) {
    utWebUI.ChunksClearDetails(id);
    utWebUI.clearChunks(id)
} // clearDetails


utWebUI.drawChunks = function( chunks ) {
    var d = eval("(" + chunks + ")");
    var Chunks = utWebUI.Chunks;
    var Chunk, ChunkNum;
    var cWidth = parseInt( utWebUI.tEM.clientWidth ) * 2; // for one cell
    var tWidth = parseInt( Chunks.cTable.clientWidth ) * 0.8;
    this.clearChunks();
    if ( cWidth <= 0 || tWidth <= 0 ) {
        return;
    }
    var ChunkLen = d.chunks[0].length, tRow, tCell;
    // Calculate number of table columns
    var NumCols = Math.round( tWidth / cWidth );
    var NumRows = Math.ceil( ChunkLen / NumCols );

    // Make header with important data
    tRow = ELE_TR.cloneNode(true);
    tCell = ELE_TD.cloneNode(true);
    tCell.colSpan = NumCols;
    tCell.innerHTML = NumRows + " " + WUILang.chunksRows + ", " + NumCols + " ";
    tCell.innerHTML += WUILang.chunksColumns + ", " + d.chunks[2] + " (";
    tCell.innerHTML += ( d.chunks[0].length ) + ") ";
    tCell.innerHTML += WUILang.chunksChunks + ", " + WUILang.chunksSize + ": ";
    tCell.innerHTML += ( d.chunks[1] / 1024 ) + " " + WUILang.KB; // Chunk size
    tCell.className = "tHeader";
    tRow.appendChild( tCell );
    Chunks.cTable.appendChild( tRow );
    
    for (var i=0; i < NumRows; i++) {
        tRow = ELE_TR.cloneNode(true);
        for (var j=0; j < NumCols; j++) {
            tCell = ELE_TD.cloneNode(true);
            Chunk = d.chunks[0].substr( i * NumCols + j, 1);
            ChunkNum = parseInt( Chunk, 16 );
            if ( Chunk != '0') {
                tCell.innerHTML = Chunk;
            } else {
                tCell.innerHTML = "&nbsp;";
            }
            tCell.className = "cCell Cell" + Chunk;
            tRow.appendChild( tCell );
        }
        Chunks.cTable.appendChild( tRow );
    }
} // drawChunks


utWebUI.clearChunks = function( id ) {
    var Chunks = utWebUI.Chunks;
    // Delete rows and columns from table
	while (Chunks.cTable.firstChild ) {
        Chunks.cTable.removeChild( Chunks.cTable.firstChild );
    }
} // clearChunks

	
function updateChunks() {
    if (this.activeView == 'Chunks' ) {
        if (this.dID != "") {
            CurrHash = this.dID;
            this.Request( "?action=getchunks", [this.drawChunks, this]);
        } else {
            this.clearChunks();
        }
    }
} // updateChunks


function getChunks() {
    var cmd = new rXMLRPCCommand( "d.multicall" );
    cmd.addParameter("string", "default" );
    cmd.addParameter("string", "d.get_hash=");
    cmd.addParameter("string", "d.get_bitfield=" );
    cmd.addParameter("string", "d.get_chunk_size=");
    cmd.addParameter("string", "d.get_size_chunks=");
    this.commands.push( cmd );
} // getchunks


function getChunksResponse (xmlDoc, docText) {
    var datas = xmlDoc.getElementsByTagName('data');
    var ret = WUILang.chunksNoChunksFound, values, data;
    for (var i=1; i < datas.length; i++) {
        data = datas[i];
        values = data.getElementsByTagName('value');
        if ( this.getValue(values,0) == CurrHash ) { // hash
            ret = '{"":"", "chunks": ["';
            ret += this.getValue(values,1); // chunks
            ret += '",';
            ret += this.getValue(values,2); // chunk size
            ret += ',';
            ret += this.getValue(values,3); // size of torrent in chunks
            ret += ']}'
            break;
        }
    }
    // Return string representing chunks
    return(ret);
} // getChResponse


function resizeChunks() {
} // resizeChunks


utWebUI.initDone = function() {
    // call a native handler
    utWebUI.ChunksInitDone();
    
    // Create "chunks" tab
    utWebUI.Chunks = document.createElement("DIV");
    utWebUI.Chunks.id = "Chunks";
    utWebUI.Chunks.cTable = ELE_TABLE.cloneNode(true);
    utWebUI.Chunks.cTable.className = "tChunks";
    utWebUI.Chunks.cTable.style.verticalAlign = "top";
    utWebUI.Chunks.appendChild( utWebUI.Chunks.cTable );
    cplugin.attachPageToTabs(utWebUI.Chunks, WUILang.Chunks);
    
    if ( utWebUI.ChunksEnabled == true ) {
        // Reference for calculating width of a table cell
        utWebUI.tEM = ELE_DIV.cloneNode(true);
        utWebUI.tEM.id = "tEM";
        utWebUI.tEM.appendChild(document.createTextNode('M'));
        utWebUI.Chunks.appendChild( utWebUI.tEM );
        
        rTorrentStub.prototype.getchunks = getChunks;
        rTorrentStub.prototype.getchunksResponse = getChunksResponse;
        utWebUI.updateChunks = updateChunks;
        utWebUI.ChunksClearDetails = utWebUI.clearDetails;
        utWebUI.ChunksUpdateDetails = utWebUI.updateDetails;
        utWebUI.updateDetails = ChunkUpdateDetails;
        utWebUI.clearDetails = ChunkClearDetails;
    } else {
        var tRow, tCell;
        tRow = ELE_TR.cloneNode(true);
        tCell = ELE_TD.cloneNode(true);
        // Inform user that chunks are not supported
        tCell.innerHTML = WUILang.chunksNotSupported;
        tCell.innerHTML += utWebUI.rTorrentVersion;
        tRow.id = "tRowNotSupported";
        tRow.appendChild( tCell );
        tCell.id = "tCellNotSupported";
        utWebUI.Chunks.cTable.appendChild( tRow );
    }
} // initDone

