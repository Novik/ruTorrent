<?php

require_once( 'rules.php' );

class extratioHooks
{
	static public function OnLabelChanged( $prm )
	{
		$mngr = rRatioRulesList::load();
		$mngr->checkLabels( array($prm["hash"]) );
	}
}
