<?php
eval(getPluginConf($plugin["name"]));

$pathToExternals['python'] = '';	

if ($do_diagnostic) {
  findRemoteEXE('python',"thePlugins.get('cloudflare').showError('theUILang.pythonNotFound');",$remoteRequests);
  $error_code=shell_exec('python -c "import cfscrape";echo $?');
  if ($error_code != 0) {
    $jResult .= "plugin.disable(); noty('cloudflare: '+theUILang.pluginCantStart,'error');";
  } else {
    $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
  }
} else {
  $theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);
}
