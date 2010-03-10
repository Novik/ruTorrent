<?php
require_once( 'autotools.php' );

$at = new rAutoTools();
$at->set();

$content = $at->get();
if(!ini_get("zlib.output_compression"))
	header( "Content-Length: ".strlen( $content ) );
header("Content-Type: application/javascript; charset=UTF-8");
echo $content;
?>
