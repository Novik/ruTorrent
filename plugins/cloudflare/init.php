<?php
eval(getPluginConf($plugin["name"]));

findRemoteEXE('python',"thePlugins.get('cloudflare').showError('theUILang.pythonNotFound');",$remoteRequests);
exec('python -c "import cfscrape"',"",&$error_code);
if ($error_code != 0) {
  $jResult .= "plugin.disable(); noty('cloudflare: '+theUILang.pluginCantStart,'error');";
} else {
  $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
}
  
