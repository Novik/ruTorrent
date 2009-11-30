<?php
require_once( 'search.php' );

$sites = new rSearch();
$sites->set();

$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$sites->get().']]></data>';
header("Content-Length: ".strlen($content));
header("Content-Type: text/xml; charset=UTF-8");
echo $content;
?>
