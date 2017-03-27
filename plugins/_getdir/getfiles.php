<?php
require_once( '../../php/util.php' );
require_once( '../../php/settings.php' );
eval(getPluginConf("_getdir"));

$theSettings = rTorrentSettings::get();
$dh = false;
$curFile = null;

$btn_id = "'".$_REQUEST['btn']."'";
$edit_id = "'".$_REQUEST['edit']."'";
$frame_id = "'".$_REQUEST['frame']."'";

function compareEntries( $a, $b )
{
	if($a=='/.')
		return( -1 );
	if($b=='/.')
		return( 1 );
	if($a=='/..')
		return( -1 );
	if($b=='/..')
		return( 1 );
	return( function_exists("mb_strtolower") ? 
		strcmp(mb_strtolower($a), mb_strtolower($b)) :
		strcmp(strtolower($a), strtolower($b)) );
}

if(isset($_REQUEST['dir']) && strlen($_REQUEST['dir']))
{
	$dir = rawurldecode($_REQUEST['dir']);
	rTorrentSettings::get()->correctDirectory($dir);
	if(LFS::is_file($dir) && 
		(($theSettings->uid<0) || 
		!$checkUserPermissions ||
		isUserHavePermission($theSettings->uid,$theSettings->gid,$dir,0x0004)))
	{
		$curFile = basename($dir);
		$dir = dirname($dir);
	}
	if(is_dir($dir))
	{
		$dh = @opendir($dir);
		$dir = addslash($dir);
		if( $dh && 
			((strpos($dir,$topDirectory)!==0) ||
			(($theSettings->uid>=0) && 
			$checkUserPermissions &&
			!isUserHavePermission($theSettings->uid,$theSettings->gid,$dir,0x0005))))
		{
			closedir($dh);
			$dh = false;
		}
	}
}
if(!$dh)
{
	$curFile = null;
	$dir = isLocalMode() ? $theSettings->directory : $topDirectory;
	if(strpos(addslash($dir),$topDirectory)!==0)
		$dir = $topDirectory;
	$dh = @opendir($dir);
}
$files = array();
$dirs = array();
if($dh)
{
	$dir = addslash($dir);
	while(false !== ($file = readdir($dh)))
        {
	        $path = fullpath($dir . $file);
		if(($file=="..") && ($dir==$topDirectory))
			continue;
		if(is_dir($path) &&
			(strpos(addslash($path),$topDirectory)===0) &&
			( $theSettings->uid<0 || !$checkUserPermissions || isUserHavePermission($theSettings->uid,$theSettings->gid,$path,0x0005) )
			)
			$dirs['/'.$file] = addslash($path);
		else
		{
			if(LFS::is_file($path)
				&& ( $theSettings->uid<0 || isUserHavePermission($theSettings->uid,$theSettings->gid,$path,0x0004))
				)
				$files[$file." "] = $path;
		}
        }
        closedir($dh);
        uksort($files,"compareEntries");
        uksort($dirs,"compareEntries");
	$files = array_merge($dirs,$files);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<style>
body { background-color: window; color: windowtext; border: 0px; margin: 0px; padding: 0px; -moz-user-select:none; }
td { padding-top: 1px; padding-bottom: 1px; padding-left: 0px; padding-right: 0px; cursor:default; font-size: 11px; font-family: Tahoma, Arial, Helvetica, sans-serif; }
.rmenuobj { border-width: 0; }
.rmenuitem { color: windowtext; }
.rmenuitemselected { color: highlighttext; background-color: highlight; }
</style>
<title></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<script language='JavaScript'>

document.oncontextmenu = function(e) { return false; }
document.ondragstart = function() { return false; };
document.onselectstart = function() { return false; };

function bin2hex(s)
{
	var v,i, f = 0, a = [];
	s += '';
	f = s.length;
	for (i = 0; i<f; i++)
        	a[i] = s.charCodeAt(i).toString(16).replace(/^([\da-f])$/,"0$1");
	return a.join('');
}

var doc = null;

function keyHandler(e)
{
	e = e || window.event;
	var charCode = (e.which == null) ? e.keyCode : ((e.which!=0 && e.charCode!=0) ? e.which : 0);
	if(charCode>=32)
	{
		var elements = document.getElementsByName('i'+e.charCode);
		if( elements.length )
		{
			var el = elements[0];
			menuClick(el);
			!el.scrollIntoView || el.scrollIntoView(false);
			e.preventDefault ? e.preventDefault() : (e.returnValue = false);
		}			
	}
}

function init()
{
	doc = window.frameElement.ownerDocument;
<?php
	if(!$curFile)
		$curFile = "/.";
	echo "var el=document.getElementById('".bin2hex($curFile)."');";
?>
	document.documentElement.scrollTop = el.offsetTop;
	menuClick(el);
	if(/WebKit/i.test(navigator.userAgent))
	{
		var _timer=setInterval(function(){ scrollBy(1,1); clearInterval(_timer); },10);
	}
	window.onkeypress = keyHandler;	
}

selected = null;

function menuClick(obj)
{
	if(selected)
		selected.className = 'rmenuitem';
	obj.className = 'rmenuitemselected';
	selected = obj;
	var code = obj.getAttribute('code');
	if(code && doc)
	{
		var el = doc.getElementById(<?php echo $edit_id;?>);
		el.value = decodeURIComponent(code);
	}
}

function menuDblClick(obj)
{
	menuClick(obj);
	location.search = "?dir="+obj.getAttribute('code')+ 
		"&btn=" + <?php echo $btn_id;?> +
		"&edit=" + <?php echo $edit_id;?> +
		"&frame=" + <?php echo $frame_id;?> +
		"&time=" + (new Date()).getTime();
}

function hideFrame()
{
	window.frameElement.style.visibility = "hidden";
	window.frameElement.style.display = "none";
	var edit = doc.getElementById(<?php echo $edit_id;?>);
	var btn = doc.getElementById(<?php echo $btn_id;?>);
	btn.value = "...";
	edit.readOnly = false;
}

function menuDblClickAndExit(obj)
{
	menuClick(obj);
	hideFrame();
}

</script>
</head>
<body onLoad='init()'>

<table class='rmenuobj' cellpadding=0 cellspacing=0 width=100%>
<?php
function ordutf8($s, $offset) 
{
	if(function_exists("mb_convert_encoding"))
	{
		list(, $ret) = unpack('N', mb_convert_encoding(mb_strtolower(mb_substr($s,$offset,1)), 'UCS-4BE', 'UTF-8'));
	}
	else
	{
		$ret = ord( strtolower($s[$offset]) );
	}
	return($ret);
}

foreach($files as $key=>$data)
{
	$chr = ordutf8($key,0);
	if(($key=='/.') || ($key[0]!='/'))
		echo "<tr><td code='".rawurlencode($data)."' id='".bin2hex($key)."' name='i".$chr."' class='rmenuitem' nowrap onclick='menuClick(this); return false;' ondblclick='menuDblClickAndExit(this); return false;'>";
	else
	{
		$chr = ordutf8($key,1);
		echo "<tr><td code='".rawurlencode($data)."' id='".bin2hex($key)."' name='i".$chr."' class='rmenuitem' nowrap onclick='menuClick(this); return false;' ondblclick='menuDblClick(this); return false;'>";
	}		
	echo "&nbsp;&nbsp;";
	echo $key;
	echo "</td></tr>";
}
?>
</table>
</body>