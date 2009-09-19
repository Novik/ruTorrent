var plugin = new rPlugin("geoip");
var item = '';
var GeoIPMode;
var ActiveLanguage = GetActiveLanguage();

plugin.loadLanguages();
plugin.loadMainCSS();

// Insert GeoIP information - either country's code or name
function LookupSuccess(data) {
    if ( GeoIPMode == 'code') {
        item += data + ' ';
    } else {
        item += data;
    }
} // LookupSuccess


// For some reason, GeoIP data could not be retrieved, put dummy country
function LookupFailure (XMLHttpRequest, textStatus, errorThrown) {
    item += '","DUMMY';
} // LookupFailure


// Change original response function
rTorrentStub.prototype.getpeersResponse = function( xmlDoc, docText )
{
    var ret = '{"":"","peers": [';
	var datas = xmlDoc.getElementsByTagName('data');
	var AjaxReq;
	
	for(var j=1; j<datas.length; j++) {
		var data = datas[j];
		var values = data.getElementsByTagName('value');
		item = '["';
		item += this.getValue(values,0);	//	p.get_id
		item += '","';
		
		if ( GeoIPMode == 'code' ) {
		    // Pack country code with IP address
		    AjaxReq = jQuery.ajax( {
		    async : false,
		    url : "plugins/geoip/lookup.php",
		    data : { action : "geoip-code", ip : this.getValue(values,1) },
		    dataType : "text",
		    success : LookupSuccess,
		    error: LookupFailure } );
		    
		    item += this.getValue(values,1);	//	p.get_address
		} else {
		    item += this.getValue(values,1);	//	p.get_address
		    item += '","';
		    
		    AjaxReq = jQuery.ajax( {
		    async : false,
		    url : "plugins/geoip/lookup.php",
		    data : { action : "geoip-name", ip : this.getValue(values,1),
		        lang : ActiveLanguage },
		    dataType : "text",
		    success : LookupSuccess,
		    error: LookupFailure } );
		}
		item += '","';
		var cv = this.getValue(values, 2);
		var mycv = this.getClientVersion(this.getValue(values,11));
		if((mycv.indexOf("Unknown")>=0) && (cv.indexOf("Unknown")<0))
			mycv = cv;
		item+=mycv;
		item+='","';
		if (this.getValue(values,3) == 1) //	p.is_incoming
            item += 'In';
		if (this.getValue(values,4) == 1)	//	p.is_encrypted
			item+=' Enc';
		if (this.getValue(values,5) == 1)	//	p.is_snubbed
			item+=' Snub';
		item+='",';
		item+=this.getValue(values,6);	//	get_completed_percent
		item+=',';
		item+=this.getValue(values,7);	//	p.get_down_total
		item+=',';
		item+=this.getValue(values,8);	//	p.get_up_total
		item+=',';
		item+=this.getValue(values,9);	//	p.get_down_rate
		item+=',';
		item+=this.getValue(values,10);	//	p.get_up_rate

		item+=']';
		if (j>1) {
			item = ',' + item;
		}
		ret += item;
	}
	ret+=']}';
	item = '';
	return(ret);
} // rTorrentStub.prototype.getpeersResponse


// Examples of row with country data
//
// ["2D5554313737302DF39F6C123C3A428E7B128189", "93.87.249.105", "Serbia", "uTorrent 1.7.7",
// "I", 36, 0, 43806720, 0, 52428]
// ["2D5554313737302DF39F6C123C3A428E7B128189", "RS 93.87.249.105", "uTorrent 1.7.7",
// "I", 36, 0, 43806720, 0, 52428]

function FormatPeers(aPeerRow, aColIndex) {
    if (aColIndex == null) {
	    aPeerRow[4] = (aPeerRow[4]==null) ? "" : aPeerRow[4] + "%";
	    aPeerRow[5] = (aPeerRow[5]==null) ? "" : ffs(aPeerRow[5]);
	    aPeerRow[6] = (aPeerRow[6]==null) ? "" : ffs(aPeerRow[6]);
	    aPeerRow[7] = (aPeerRow[7]==null) ? "" : ffs(aPeerRow[7]) + "/" + WUILang.s + "";
	    aPeerRow[8] = (aPeerRow[8]==null) ? "" : ffs(aPeerRow[8]) + "/" + WUILang.s + "";
	    for( var i = 9; i < aPeerRow.length; i++) {
		    if (aPeerRow[i] == null) aPeerRow[i] = "";
	    }
    } else {
	    if (aColIndex == null) {
		    aPeerRow = "";
	    } else {
	        switch (aColIndex) {
            case 4 : 
                aPeerRow = aPeerRow + "%";
                break;
            case 5 :
            case 6 :
                aPeerRow = ffs(aPeerRow);
      	        break;
            case 7 : 
            case 8 : 
                aPeerRow = ffs(aPeerRow) + "/" + WUILang.s + "";
                break;
            } // switch
        }
    }
	return aPeerRow;
} // FormatPeers


// Needed for adding flags - this function runs only for showing country flags
function addGeoIPPeers(aPeerRow)
{
    var d = eval("(" + aPeerRow + ")");
    var i, l = d.peers.length;
    var sl = this.prsTable.dBody.scrollLeft;
    var CountryCode;
    
    for (i = 0; i < l; i++) {
        var sId = d.peers[i][0];
        CountryCode = d.peers[i][1].split( " " );
        
        if (typeof (this.peers[sId]) == "undefined") {
            this.peers[sId] = d.peers[i].slice(1);
            // Show only IP, use country code to show flag
            this.peers[sId][0] = CountryCode[1];
	        this.prsTable.addRow(this.peers[sId], sId, "geoip_flag_" + CountryCode[0] );
        } else {
            this.prsTable.setValue(sId, 0, CountryCode[1]);
            this.peers[sId][0] = d.peers[i][1];
            for (var j = 2; j < d.peers[i].length; j++) {           
                this.prsTable.setValue(sId, j-1, d.peers[i][j]);
		        this.peers[sId][j-1] = d.peers[i][j];
		    }
	    }
	    this.peers[sId][24] = true;
    } // for
    
	this.prsTable.dBody.scrollLeft = sl;
	for(var k in this.peers) {
	    if (this.peers[k][24]!=true){
            delete this.peers[k];
	        this.prsTable.removeRow(k);
	    } else
			this.peers[k][24] = false;
	}
	if(this.prsTable.sIndex !=- 1)
		this.prsTable.Sort();
	else
		utWebUI.prsTable.resizeHack();
	delete aPeerRow;
	d = null;
} // addGeoIPPeers


utWebUI.GeoIPInitDone = utWebUI.initDone;

utWebUI.initDone = function() {
    // call a native handler
    this.GeoIPInitDone();
    if ( utWebUI.GeoIPIndex > 0 ) {
        GeoIPMode = 'name';
        // Modify peer list table formatter
        utWebUI.prsTable.format = FormatPeers;
    } else {
        GeoIPMode = 'code';
        // Modify function for adding rows so that flags can be added
        utWebUI.addPeers = addGeoIPPeers;
    }
} // initDone

