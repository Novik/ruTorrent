<?php

require_once( 'util.php' );

$fname = getWebUIJsonFile()."/uisettings.json";
@chmod($fname, 0777);
cachedEcho($fname, "text/plain", true);
