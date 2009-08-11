<?php
require_once( 'throttle.php' );

$thr = new rThrottle();
$thr->set();

$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$thr->get().']]></data>';
header("Content-Length: ".strlen($content));
header("Content-Type: text/xml; charset=UTF-8");
echo $content;
?>
