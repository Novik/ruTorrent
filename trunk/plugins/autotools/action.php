<?php
require_once( 'autotools.php' );
require_once( '../../util.php' );

$at = new rAutoTools();
$at->set();

$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$at->get().']]></data>';
header( "Content-Length: ".strlen( $content ) );
header( "Content-Type: text/xml; charset=UTF-8" );
echo $content;
?>
