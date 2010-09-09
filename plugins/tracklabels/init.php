<?php

$theSettings->registerPlugin("tracklabels");

if($hnd = opendir('../plugins/tracklabels/trackers'))
{
	$jResult.="var styles = '';";
	while(false !== ($file = readdir($hnd)))
	{
		if($file != "." && $file != ".." && is_file('../plugins/tracklabels/trackers/'.$file))
			$jResult.="styles += '#i".str_replace('.','\\\.',basename($file, ".png"))." {background: url(./plugins/tracklabels/trackers/".$file.") no-repeat 4px 50%} ';";
	}
	closedir($hnd);
	$jResult.="if(styles.length) injectCSSText(styles);\n";
}

?>