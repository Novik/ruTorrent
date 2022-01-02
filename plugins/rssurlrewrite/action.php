<?php
require_once('rules.php');

if(isset($_REQUEST['mode']))
	$cmd = $_REQUEST['mode'];
$mngr = rURLRewriteRulesList::load();
$val = null;

switch($cmd)
{
	case "checkrule":
	{
		$rule = new rURLRewriteRule( 'test',
			trim($_REQUEST['pattern']), trim($_REQUEST['replacement']) );
		$href = trim($_REQUEST['test']);
		$rslt = $rule->apply($href,$href);
		$val = array( "msg"=>$rslt );
		break;
	}
	case "setrules":
	{
		$mngr->set();
		break;
	}
}

if(is_null($val))
	$val = $mngr->getContents();

CachedEcho::send(JSON::safeEncode($val),"application/json",true);
