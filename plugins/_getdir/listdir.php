<?php
require_once( '../../php/util.php' );
require_once( '../../php/settings.php' );
eval(FileUtil::getPluginConf("_getdir"));

$theSettings = rTorrentSettings::get();

$requestedDir = $_REQUEST['dir'];

if(isset($requestedDir) && strlen($requestedDir))
{
	$dir = rawurldecode($requestedDir);
	rTorrentSettings::get()->correctDirectory($dir);

	if(
			(strpos($dir,$topDirectory)!==0) ||
			(($theSettings->uid>=0) && $checkUserPermissions && !Permission::doesUserHave($theSettings->uid,$theSettings->gid,$dir,0x0007))
		)
	{
		$dir = $topDirectory;
	}
}
else
{
	$dir = User::isLocalMode() ? $theSettings->directory : $topDirectory;
	if (strpos($dir, $topDirectory) !== 0)
		$dir = $topDirectory;
}

$dir = FileUtil::addslash($dir);
$items = array_diff(scandir($dir), (($dir == $topDirectory) ? ["..", "."] : ["."]));
$directories = array_filter($items, function ($item) {
	global $dir;
	return is_dir($dir.$item);
});
$files = array_filter($items, function ($item) {
	global $dir;
	return is_file($dir.$item);
});

header("Content-Type: application/json");
echo JSON::safeEncode(array(
	"path" => $dir,
	"directories" => array_values($directories),
	"files" => array_values($files),
));
?>
