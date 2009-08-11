<?php
require_once( 'retrackers.php' );

$trks = new rRetrackers();
$trks->set();

$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$trks->get().']]></data>';
header("Content-Length: ".strlen($content));
header("Content-Type: text/xml; charset=UTF-8");
echo $content;
?>
