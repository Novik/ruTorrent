<?php
require_once( 'scheduler.php' );

$sch = rScheduler::load();
$sch->set();

$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$sch->get().']]></data>';
header("Content-Length: ".strlen($content));
header("Content-Type: text/xml; charset=UTF-8");
echo $content;
?>
