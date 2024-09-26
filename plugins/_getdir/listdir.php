<?php
require_once( '../../php/util.php' );
require_once( '../../php/settings.php' );
eval(FileUtil::getPluginConf("_getdir"));

$theSettings = rTorrentSettings::get();

if(isset($_REQUEST['dir']) && strlen($_REQUEST['dir']))
{
	$dir = rawurldecode($_REQUEST['dir']);
	rTorrentSettings::get()->correctDirectory($dir);
	$dir = FileUtil::addslash($dir);

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
	$dir = $topDirectory;
}

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
echo json_encode(array(
	"path" => $dir,
	"directories" => array_values($directories),
	"files" => array_values($files),
));
?>
