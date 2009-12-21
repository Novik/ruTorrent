// Made by Zoltan Csala, 2009

var gplugin = new rPlugin("geoip");
var item = '';
var Country = new Array();
var CntInd;

gplugin.loadMainCSS();
gplugin.loadLanguages();

// Insert GeoIP information - either country's code or name
function LookupSuccess(data) {
    Country[ CntInd++ ] = data;
} // LookupSuccess


// For some reason, GeoIP data could not be retrieved, put dummy country
function LookupFailure (XMLHttpRequest, textStatus, errorThrown) {
    Country[ CntInd++ ] = "unknown";
} // LookupFailure


// Resolve countries separately
function getCountryFromIP( aIP ) {
    var AjaxReq;
    AjaxReq = jQuery.ajax( {
        async : false,
        url : "plugins/geoip/lookup.php",
        data : { action : "geoip", ip : aIP },
        dataType : "text",
        success : LookupSuccess,
        error: LookupFailure } );
} // getCountryFromIP


function GeoIPResponse( xmlDoc, docText )
{
    var ret = '{"":"","peers": [';
	var datas = xmlDoc.getElementsByTagName('data');
	var j;

	Country[ 0 ] = "dummy";
	CntInd = 1;
	for(j=1; j<datas.length; j++) {
		var data = datas[j];
		var values = data.getElementsByTagName('value');
		getCountryFromIP( this.getValue(values,1) );
	}
	
	for(j=1; j<datas.length; j++) {
		var data = datas[j];
		var values = data.getElementsByTagName('value');
		item = '["';
		item += this.getValue(values,0);	//	p.get_id
		item += '","';

        item += Country[j] + '","';         //  country code
        item += this.getValue(values,1);	//	p.get_address

		item += '","';
		var cv = this.getValue(values, 2);
		var mycv = this.getClientVersion(this.getValue(values,11));
		if((mycv.indexOf("Unknown")>=0) && (cv.indexOf("Unknown")<0))
			mycv = cv;
		item+=mycv;
		item+='","';
		if (this.getValue(values,3) == 1)   //	p.is_incoming
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
} // GeoIPResponse


// Examples of row with country data
//
// ["2D5554313737302DF39F6C123C3A428E7B128189", "RS", "93.XX.XX.105", "uTorrent 1.7.7",
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
    var CountryCode, CountryName;
    
    for (i = 0; i < l; i++) {
        var sId = d.peers[i][0];

        if (typeof (this.peers[sId]) == "undefined") {
            // Show only IP, use country code to show flag
            this.peers[sId] = d.peers[i].slice(1);
            CountryCode = this.peers[sId][0];
            CountryName = WUILang.country[ CountryCode ];
            if ( typeof(CountryName) == "undefined" ) {
                this.peers[sId][0] = CountryCode;
            } else {
                this.peers[sId][0] = CountryName;
            }
	        this.prsTable.addRow(this.peers[sId], sId, "geoip_flag_" + CountryCode );
        } else {
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
    
    if ( utWebUI.GeoIPSupported ) {
        // Modify function for adding rows so that flags can be added
        utWebUI.addPeers = addGeoIPPeers;
        // Modify peer list table formatter
        utWebUI.prsTable.format = FormatPeers;
        // Change original peer response function
        rTorrentStub.prototype.getpeersResponse = GeoIPResponse;
    }
} // initDone


