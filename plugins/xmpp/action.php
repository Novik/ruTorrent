<?php
require_once( 'xmpp.php' );

// Loaded rather than built empty: a request that leaves the password alone
// has to find the stored one here for set() to keep it.
$at = rXmpp::load();
$at->set();
CachedEcho::send($at->get(),"application/javascript");
