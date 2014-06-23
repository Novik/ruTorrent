<?php
eval(getPluginConf($plugin["name"]));

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);

$jResult .= "plugin.maxConcurentTasks = ".$maxConcurentTasks.";";
$jResult .= "plugin.showTabAlways = ".$showTabAlways.";";
