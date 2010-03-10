<?php
require_once( '../plugins/cookies/cookies.php');
$theSettings->registerPlugin("cookies");
$cookies = rCookies::load();
$jResult.=$cookies->get();
?>
