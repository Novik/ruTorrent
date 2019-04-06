<?php
eval(getPluginConf($plugin["name"]));

$pathToPython = getExternal("python");

if ($do_diagnostic) {
  findRemoteEXE('python',"thePlugins.get('cloudflare').showError('theUILang.pythonNotFound');",$remoteRequests);
  $error_code=shell_exec("$pathToPython -c \"import cfscrape\";echo \$?");
  if ($error_code != 0) {
    $jResult .= "plugin.disable(); noty('cloudflare: '+theUILang.cannotLoadCfscrape,'error');";
  } else {
    $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
  }
} else {
  $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
}
