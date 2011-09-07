<?php

require_once( '../plugins/autotools/autotools.php');

$at = rAutoTools::load();
if($at->setHandlers())
{	
	$jResult .= $at->get();
	if( $do_diagnostic )
	{
		if( $at->enable_move )
		{
			$path_to_finished = trim( $at->path_to_finished );
			if( $path_to_finished == '' )
				$jResult .= "plugin.showError('theUILang.autotoolsNoPathToFinished');";
		}
		if( $at->enable_watch )
		{
			$path_to_watch = trim( $at->path_to_watch );
			if( $path_to_watch == '' )
				$jResult .= "plugin.showError('theUILang.autotoolsNoPathToWatch');";
		}
	}
	$theSettings->registerPlugin("autotools");
}
else
        $jResult .= "plugin.disable(); log('autotools: '+theUILang.pluginCantStart);";

?>