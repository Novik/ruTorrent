<?php

require_once( 'config.php' );

$filename = $settings."/settings.txt";
$s = '';
if( is_readable($filename) )
{
	$w = @fopen($filename, "rb");
	if($w)
	{
    	    $s = fgets($w);
	    fclose($w);
	}	    
}
$content = '<?xml version="1.0" encoding="UTF-8"?><data>'.$s.'</data>';
header("Content-Length: ".strlen($content));
header("Content-Type: text/xml; charset=UTF-8");
echo $content;
?>
