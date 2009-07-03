<?php
require_once('rss.php');
ob_start();
ignore_user_abort(true);
set_time_limit(0);
$val = null;
$cmd = "get";
if(isset($_REQUEST['mode']))
	$cmd = $_REQUEST['mode'];
$errorsReported = false;
$mngr = new rRSSManager();
switch($cmd)
{
	case "getintervals":
	{
		$val = $mngr->getIntervals();
		break;
	}
	case "add":
	{
		$lbl = null;
		if(isset($_REQUEST['label']))
			$lbl = $_REQUEST['label'];
		if(isset($_REQUEST['url']))
			$mngr->add($_REQUEST['url'],$lbl);
		break;
	}
	case "edit":
	{
		$lbl = null;
		if(isset($_REQUEST['label']))
			$lbl = $_REQUEST['label'];
		if(isset($_REQUEST['rss']) && isset($_REQUEST['url']))
			$mngr->change($_REQUEST['rss'],$_REQUEST['url'],$lbl);
		break;
	}
	case "clearhistory":
	{
		$mngr->clearHistory();
		break;
	}
	case "refresh":
	{
		if(isset($_REQUEST['rss']))
		{
			$mngr->updateRSS($_REQUEST['rss']);
		}
		else
			$mngr->update(true);
		break;
	}
	case "toggle":
	{
		if(isset($_REQUEST['rss']))
		{
			$mngr->toggleStatus($_REQUEST['rss']);
		}
		break;
	}
	case "remove":
	{
		if(isset($_REQUEST['rss']))
		{
			$mngr->remove($_REQUEST['rss']);
		}
		break;
	}
	case "getfilters":
	{
		$val = $mngr->getFilters();
		break;
	}
	case "checkfilter":
	{
		$hash = null;
		$pattern = '';
		$exclude = '';
		if(isset($_REQUEST['rss']))
			$hash = $_REQUEST['rss'];
		if(isset($_REQUEST['pattern']))		
			$pattern = trim($_REQUEST['pattern']);
		if(isset($_REQUEST['exclude']))		
			$exclude = trim($_REQUEST['exclude']);
		$filter = new rRSSFilter( '', $pattern, $exclude, 1, '', 0, 1, null, '' );
        	$val = $mngr->testFilter($filter,$hash);
        	$errorsReported = true;
		break;
	}
	case "setfilters":
	{
		if(!isset($HTTP_RAW_POST_DATA))
			$HTTP_RAW_POST_DATA = file_get_contents("php://input");
		$flts = new rRSSFilterList();
		$flt = null;
		if(isset($HTTP_RAW_POST_DATA))
		{
			$vars = split('&', $HTTP_RAW_POST_DATA);
			foreach($vars as $var)
			{
				$parts = split("=",$var);
				if($parts[0]=="name")
				{
					if($flt)
						$flts->add($flt);
					$flt = new rRSSFilter(rawurldecode($parts[1]));
				}
				else
				if($parts[0]=="pattern")
				{
					if($flt)
						$flt->pattern = trim(rawurldecode($parts[1]));
				}
				else
				if($parts[0]=="exclude")
				{
					if($flt)
						$flt->exclude = trim(rawurldecode($parts[1]));
				}
				else
				if($parts[0]=="enabled")
				{
					if($flt)
						$flt->enabled = $parts[1];
				}
				else
				if($parts[0]=="hash")
				{
					if($flt)
						$flt->rssHash = $parts[1];
				}
				else
				if($parts[0]=="start")
				{
					if($flt)
						$flt->start = $parts[1];
				}
				if($parts[0]=="addPath")
				{
					if($flt)
						$flt->addPath = $parts[1];
				}
				else
				if($parts[0]=="dir")
				{
					if($flt)
						$flt->directory = rawurldecode($parts[1]);
				}
				else
				if($parts[0]=="label")
				{
					if($flt)
						$flt->label = rawurldecode($parts[1]);
				}
  	                }
			if($flt)
				$flts->add($flt);
			$mngr->setFilters($flts);
		}
		break;
	}
	case "loadtorrents":
	{
		if(!isset($HTTP_RAW_POST_DATA))
			$HTTP_RAW_POST_DATA = file_get_contents("php://input");
		if(isset($HTTP_RAW_POST_DATA))
		{
			set_time_limit(0);
			$vars = split('&', $HTTP_RAW_POST_DATA);
			$lbl = null;
			$dir = null;
			$isStart = true; 
			$isAddPath = true;
			$curRSS = null;
			$rssArray = array();
			foreach($vars as $var)
			{
				$parts = split("=",$var);
				if($parts[0]=="torrents_start_stopped")
					$isStart = false;
				else
				if($parts[0]=="not_add_path")
					$isAddPath = false;
				else
				if($parts[0]=="dir_edit")
					$dir = rawurldecode($parts[1]);
				else
				if($parts[0]=="label")
					$lbl = rawurldecode($parts[1]);
				else
				if($parts[0]=="rss")
					$curRSS = $parts[1];
				else
				if(($parts[0]=="url") && $curRSS)
				{
					if(!array_key_exists($curRSS,$rssArray) || !is_array($rssArray[$curRSS]))
						$rssArray[$curRSS] = array();
					$rssArray[$curRSS][] = rawurldecode($parts[1]);
				}
			}
			foreach($rssArray as $hash=>$urls)
			{
				$rss = new rRSS();
				$rss->hash = $hash;
				if($mngr->cache->get($rss))
				{
					foreach($urls as $url)
						$mngr->getTorrents( $rss, $url, $isStart, $isAddPath, $dir, $lbl, false );
				}
			}
			$mngr->saveHistory();
			$mngr->cache->set($mngr->rssList);
		}
		break;
	}
}
if($val===null)
{
	$val = $mngr->get();
	$errorsReported = true;
}
$content = '<?xml version="1.0" encoding="UTF-8"?><data><![CDATA['.$val.']]></data>';
header("Content-Length: ".strlen($content));
header("Content-Type: text/xml; charset=UTF-8");
echo $content;
ob_flush();
flush();
if(!connection_aborted() && $errorsReported)
{
	$mngr->clearErrors();	
}
?>
