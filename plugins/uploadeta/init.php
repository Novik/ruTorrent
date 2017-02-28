<?php
require_once('uploadeta.php');

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
$uploadeta = rUploadeta::load();
/* Use get from uploadeta.php to get the stored value */
$jResult.=$uploadeta->get();
