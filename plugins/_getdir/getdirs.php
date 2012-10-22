<?php
require_once( '../../php/util.php' );
require_once( '../../php/settings.php' );

$dh = false;
$theSettings = rTorrentSettings::get();

$btn_id = "'".$_REQUEST['btn']."'";
$edit_id = "'".$_REQUEST['edit']."'";
$frame_id = "'".$_REQUEST['frame']."'";

if(isset($_REQUEST['dir']) && strlen($_REQUEST['dir']))
{
	$dir = rawurldecode($_REQUEST['dir']);
	rTorrentSettings::get()->correctDirectory($dir);
	$dh = @opendir($dir);
	$dir = addslash($dir);

	if( $dh &&
		((strpos($dir,$topDirectory)!==0) ||
		(($theSettings->uid>=0) &&
		!isUserHavePermission($theSettings->uid,$theSettings->gid,$dir,0x0007))))
	{
		closedir($dh);
		$dh = false;
	}
}
if(!$dh)
{
	$dir = isLocalMode() ? $theSettings->directory : $topDirectory;
	if(strpos(addslash($dir),$topDirectory)!==0)
		$dir = $topDirectory;
	$dh = @opendir($dir);
}
$files = array();
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
			( $theSettings->uid<0 || isUserHavePermission($theSettings->uid,$theSettings->gid,$path,0x0007))
			)
		{
			$files[$file." "] = $path;
		}
        }
        closedir($dh);
	ksort($files,SORT_STRING);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ru" lang="ru">
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

var ownerDocument = window.frameElement.ownerDocument;

function init()
{
	menuClick(document.getElementById('root'));
	if(/WebKit/i.test(navigator.userAgent))
	{
		var _timer=setInterval(function(){ scrollBy(1,1); clearInterval(_timer); },10);
	}
}

selected = null;

function menuClick(obj)
{
	if(selected)
		selected.className = 'rmenuitem';
	obj.className = 'rmenuitemselected';
	selected = obj;
	var code = obj.getAttribute('code');
	if(code && window.frameElement)
	{
		var el = ownerDocument.getElementById(<?php echo $edit_id;?>);
		el.value = decodeURIComponent(code);
	}
}

function menuDblClick(obj)
{
	menuClick(obj);
	location.search = "?dir="+obj.getAttribute('code') +
		"&btn=" + <?php echo $btn_id;?> +
		"&edit=" + <?php echo $edit_id;?> +
		"&frame=" + <?php echo $frame_id;?> +
		"&time=" + (new Date()).getTime();
}

function hideFrame()
{
	window.frameElement.style.display = "none";
	window.frameElement.style.visibility = "hidden";
	var edit = ownerDocument.getElementById(<?php echo $edit_id;?>);
	var btn = ownerDocument.getElementById(<?php echo $btn_id;?>);
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
foreach($files as $key=>$data)
{
	$key = trim($key);
	if($key==='.')
		echo "<tr><td code='".rawurlencode($data)."' id='root' class='rmenuitemselected' nowrap onclick='menuClick(this); return false;' ondblclick='menuDblClickAndExit(this); return false;'>";
	else
		echo "<tr><td code='".rawurlencode($data)."' class='rmenuitem' nowrap onclick='menuClick(this); return false;' ondblclick='menuDblClick(this); return false;'>";
	echo "&nbsp;&nbsp;";
	echo $key;
	echo "</td></tr>";
}
?>
</table>
</body>