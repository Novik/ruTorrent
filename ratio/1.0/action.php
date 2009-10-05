<?php
require_once( 'ratio.php' );

$rat = new rRatio();
$rat->set();

$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$rat->get().']]></data>';
header("Content-Length: ".strlen($content));
header("Content-Type: text/xml; charset=UTF-8");
echo $content;
?>
