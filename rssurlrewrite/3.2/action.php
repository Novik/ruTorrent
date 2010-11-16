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
		if($rslt==false)
			$val = '{ "msg": theUILang.rssPatternError }';
		else
			$val = '{ "msg": '.quoteAndDeslashEachItem($rslt).' }';
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

cachedEcho($val,"application/json",true,false);

?>