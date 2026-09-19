<?php

require_once( 'theme.php' );
$theme = rTheme::load();
// The stored name arrives as a request parameter, and this is the only place
// that decides whether it names a real theme. Emit it only once it has passed.
if(!$theme->isValid())
	$theme->current = '';
$jResult.=$theme->get();

$themes = "plugin.themes = [";
foreach(glob("../plugins/theme/themes/*",GLOB_ONLYDIR) as $path)
{
	if($themes != "plugin.themes = [")
		$themes.=',';
	$name = basename($path);
	$themes.=("'".$name."'");
}
$jResult.=($themes.'];');

$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);

if($theme->isValid())
{
	$themeInit = "../plugins/theme/themes/".$theme->current."/init.js";
	if(is_readable($themeInit))
		$jEnd.=file_get_contents($themeInit);
}
