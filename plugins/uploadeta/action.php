<?php
require_once('uploadeta.php');

/* Use the set function in uploadeta.php to save a new value */
$uploadeta = new rUploadeta();
$uploadeta->set();
cachedEcho($uploadeta->get(),"application/javascript");
