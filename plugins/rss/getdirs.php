<?php
require_once( '../../config.php' );
require_once( '../../util.php' );
require_once( '../../settings.php' );

$theSettings = rTorrentSettings::load();
$tdir = "../..";
$dh = false;

if(isset($_REQUEST['dir']))
{
	$dir = rawurldecode($_REQUEST['dir']);
	$dh = @opendir($dir);
	if( $dh && 
		($theSettings->uid>=0) && ($theSettings->gid>=0) && 
		!isUserHavePermission($theSettings->uid,$theSettings->gid,$dir,0x0007))
	{
		closedir($dh);
		$dh = false;
	}
}
if(!$dh)
{
	$dir = $tdir;
	$dh = @opendir($dir);
}
$files = array();
if($dh)
{
	$len = strlen($dir);
	if($len && ($dir[$len-1]!="/"))
		$dir.="/";
	while(false !== ($file = readdir($dh)))
        {
		$path = realpath($dir . $file . "/");
		if(($file=="..") && ($dir=="/"))
		{
			continue;
		}
		if(is_dir($path) && is_readable($path)
			 && ( $theSettings->uid<0 || $theSettings->gid<0 || isUserHavePermission($theSettings->uid,$theSettings->gid,$path,0x0007))
			)
		{
			$files[$file] = $path;
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
td { padding-top: 1px; padding-bottom: 1px; padding-left: 0px; padding-right: 0px; cursor:default; font-size: 11px; font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif; }
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
var doc = null;

function init()
{
	doc = window.frameElement.ownerDocument;
	menuClick(document.getElementById('root'));
	if(/WebKit/i.test(navigator.userAgent))
	{
		var _timer=setInterval(function(){ scrollBy(1,1); clearInterval(_timer); },10);
	}
}

<?php
if(isset($_REQUEST['btn']))
	echo "dir_edit = '".$_REQUEST['edt']."';";
else
	echo 'dir_edit = "dir_edit";';
echo "\n";
if(isset($_REQUEST['edt']))
	echo "browse = '".$_REQUEST['btn']."';";
else
	echo 'browse = "browse";';
echo "\n";
?>

selected = null;

function menuClick(obj)
{
	if(selected)
		selected.className = 'rmenuitem';
	obj.className = 'rmenuitemselected';
	selected = obj;
	var code = obj.getAttribute('code');
	if(code)
	{
		var el = doc.getElementById(dir_edit);
		el.value = decodeURIComponent(code);
	}
}

function menuDblClick(obj)
{
	menuClick(obj);
	location.search = "?dir="+obj.getAttribute('code')+"&btn="+browse+"&edt="+dir_edit+"&time=" + (new Date()).getTime();
}

function hideFrame()
{
	window.frameElement.style.visibility = "hidden";
	window.frameElement.style.display = "none";
	var edit = doc.getElementById(dir_edit);
	var btn = doc.getElementById(browse);
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
<body onload='init()'>

<table class='rmenuobj' cellpadding=0 cellspacing=0 width=100%>
<?php
foreach($files as $key=>$data)
{
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