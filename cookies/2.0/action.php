<?php
require_once( 'cookies.php' );

$cookies = new rCookies();
$cookies->set();

$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$cookies->get().']]></data>';
header("Content-Length: ".strlen($content));
header("Content-Type: text/xml; charset=UTF-8");
echo $content;
?>
