<?php
	$path = dirname(realpath($argv[0]));
	if(chdir($path))
	{
		if( count( $argv ) > 4 )
			$_SERVER['REMOTE_USER'] = $argv[4];
		require_once( './rules.php' );
		$mngr = rRatioRulesList::load();
		$rule = $mngr->getRule( rawurldecode($argv[2]), $argv[1] );
		if($rule)
		{
			$val = $rule->{$argv[3]};
			if($val!='')
				echo $val;
		}
	}