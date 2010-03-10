<?php

require_once( '../plugins/autotools/autotools.php');
eval(getPluginConf('autotools'));

$pathToAutoTools = $rootPath.'/plugins/autotools';
$needStart = true;

if( $do_diagnostic )
{
	if( !$pathToPHP || $pathToPHP == "" )
		findRemoteEXE( 'php', "thePlugins.get('autotools').showError('theUILang.autotoolsPHPNotFound');", $remoteRequests );

	@chmod( $pathToAutoTools.'/label.php', 0644 );
	@chmod( $pathToAutoTools.'/move.php',  0644 );
	@chmod( $pathToAutoTools.'/watch.php', 0644 );

	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $pathToAutoTools.'/label.php',0x0004 ) )
	{
		$jResult .= "plugin.disable(); plugin.showError('theUILang.autotoolsLabelPhpNotAvailable');";
		$needStart = false;
	}
	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $pathToAutoTools.'/move.php',0x0004 ) )
	{
		$jResult .= "plugin.disable(); plugin.showError('theUILang.autotoolsMovePhpNotAvailable');";
		$needStart = false;
	}
	if( !isUserHavePermission( $theSettings->uid, $theSettings->gid, $pathToAutoTools.'/watch.php',0x0004 ) )
	{
		$jResult .= "plugin.disable(); plugin.showError('theUILang.autotoolsWatchPhpNotAvailable');";
		$needStart = false;
	}
}

if($needStart)
{
	if( $theSettings->iVersion < 0x804 )
	{
		$cmdInsert = new rXMLRPCCommand('on_insert');
		$cmdFinished = new rXMLRPCCommand('on_finished');
	}
	else
	{
		$cmdInsert = new rXMLRPCCommand('system.method.set_key','event.download.inserted_new');
		$cmdFinished = new rXMLRPCCommand('system.method.set_key','event.download.finished');
	}
	$cmdSchedule = new rXMLRPCCommand('schedule', array( 'autowatch'.getUser(), '10', $autowatch_interval."", 'execute={sh,-c,'.escapeshellarg(getPHP()).' '.escapeshellarg($pathToAutoTools.'/watch.php').' '.escapeshellarg(getUser()).' &}' ));
	$cmdInsert->addParameters( array('autolabel'.getUser(), 'branch=$not=$d.get_custom1=,"execute={'.getPHP().','.$pathToAutoTools.'/label.php,$d.get_hash=,'.getUser().'}"') );
	$cmdFinished->addParameters( array('automove'.getUser(), 'execute={'.getPHP().','.$pathToAutoTools.'/move.php,$d.get_hash=,'.getUser().'}') );
	$req = new rXMLRPCRequest( array( $cmdInsert, $cmdFinished, $cmdSchedule ) );
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
}

?>