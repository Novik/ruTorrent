<?php
require_once( 'xmpp.php' );

$at = new rXmpp();
$at->set();
cachedEcho($at->get(),"application/javascript");
