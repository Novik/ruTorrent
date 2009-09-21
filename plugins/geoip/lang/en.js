
if ( utWebUI.GeoIPMode == "name" ) {
    utWebUI.prsColumns[ 0 ][ "text" ] = "Country";
} else {
    utWebUI.prsColumns[ 0 ][ "text" ] = "CC";
}
utWebUI.prsColumns[ 1 ][ "text" ] = "IP";
