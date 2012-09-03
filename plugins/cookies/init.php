<?php
require_once( '../plugins/cookies/cookies.php');
$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
$cookies = rCookies::load();
$jResult.=$cookies->get();
