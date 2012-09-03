<?php
require_once( 'theme.php' );

$theme = new rTheme();
$theme->set();
cachedEcho($theme->get(),"application/javascript");
