<?php
require_once( 'xmpp.php' );

$at = new rXmpp();
$at->set();
CachedEcho::send($at->get(),"application/javascript");
