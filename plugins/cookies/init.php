<?php
require_once( dirname(__FILE__)."/cookies.php");
$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
$cookies = rCookies::load();
$jResult.=$cookies->get();
