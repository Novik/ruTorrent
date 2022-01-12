<?php
eval(FileUtil::getPluginConf($plugin["name"]));
require_once( dirname(__FILE__)."/task.php" );

rTaskManager::cleanup();
$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);

$jResult .= "plugin.maxConcurentTasks = ".$maxConcurentTasks.";";
$jResult .= "plugin.showTabAlways = ".$showTabAlways.";";
