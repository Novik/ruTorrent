<?php

require_once( '../plugins/autotools/autotools.php');
eval(getPluginConf('autotools'));

$pathToAutoTools = $rootPath.'/plugins/autotools';

$req = new rXMLRPCRequest( array( 
	$theSettings->getOnInsertCommand(array('autolabel'.getUser(), 
		getCmd('branch').'=$'.getCmd('not').'=$'.getCmd("d.get_custom1").'=,"'.getCmd('execute').'={'.getPHP().','.$pathToAutoTools.'/label.php,$'.getCmd("d.get_hash").'=,'.getUser().'}"')),
	$theSettings->getOnFinishedCommand(array('automove'.getUser(), 
		getCmd('d.stop=').' ; '.getCmd('d.set_custom').'=x-dest,"$'.getCmd('execute_capture').
		'={'.getPHP().','.$pathToAutoTools.'/move.php,$'.getCmd('d.get_hash').'=,$'.getCmd('d.get_base_path').'=,$'.
		getCmd('d.get_base_filename').'=,$'.getCmd('d.is_multi_file').'=,'.getUser().'}" ; '.
		getCmd('branch').'=$'.getCmd('not').'=$'.getCmd('d.get_custom').'=x-dest,,'.getCmd('d.set_directory_base').'=$'.getCmd('d.get_custom').'=x-dest ; '.
		getCmd('d.start=')
		)),
	new rXMLRPCCommand('schedule', array( 'autowatch'.getUser(), '10', $autowatch_interval."", 
		getCmd('execute').'={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg($pathToAutoTools.'/watch.php').' '.escapeshellarg(getUser()).' &}' ))
	));
if($req->run() && !$req->fault)
{
	$at = rAutoTools::load();
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