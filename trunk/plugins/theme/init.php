<?php

require_once( 'theme.php' );
$theme = rTheme::load();
$jResult.=$theme->get();

$themes = "plugin.themes = [";
if($hth = opendir('../plugins/theme/themes')) 
{
	while(false !== ($file = readdir($hth)))
	{
		if($file != "." && $file != ".." && is_dir('../plugins/theme/themes/'.$file))
		{
			if($themes != "plugin.themes = [")
				$themes.=',';
			$themes.=("'".$file."'");
		}
	}
	closedir($hth);
}
$jResult.=($themes.'];');

if($theme->isValid())
{
	$themeInit = "../plugins/theme/themes/".$theme->current."/init.js";
	if(is_readable($themeInit))
		$jEnd.=file_get_contents($themeInit);
}

?>