<?php
require_once( 'theme.php' );

$theme = new rTheme();
$theme->set();
CachedEcho::send($theme->get(),"application/javascript");
