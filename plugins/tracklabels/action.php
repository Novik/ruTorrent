<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc" );

eval(FileUtil::getPluginConf('tracklabels'));

function try_send_image($image_name, $mime = 'image/png')
{
	if (is_readable($image_name)) {
		SendFile::send($image_name, $mime);
		exit;
	}
}

function bad($message) {
	http_response_code(400);
	echo $message;
	exit;
}

$basepath = realpath(FileUtil::getSettingsPath());
$knownAliases = [
	'app'       => ['apps', 'applications', 'трограммы'],
	'book'      => ['books', 'livres', 'книги'],
	'film'      => ['films', 'фильм', 'фильмы'],
	'game'      => ['jeux', 'игра'],
	'game2'     => ['games', 'игры'],
	'image'     => ['images', 'изображения', 'картинки', 'фото'],
	'kid'       => ['kids', 'enfants', 'детское', 'детям'],
	'movie'     => ['movies', 'serial', 'serials', 'сериал', 'сериалы'],
	'music'     => ['musique', 'музыка'],
	'other'     => ['others', 'autres', 'другое'],
	'pack'      => ['packs'],
	'porn'      => ['porno', 'pornos', 'порно'],
	'serie'     => ['series', 'tv', 'тв'],
	'software'  => ['softwares', 'logiciels', 'софт'],
	'sport'     => ['sports', 'спорт'],
	'video'     => ['videos', 'видео'],
];

function get_alias($name)
{
	global $knownAliases, $labelAliases;
	foreach (array_merge_recursive($knownAliases, (array) $labelAliases) as $alias => $aliases) {
		if ((is_array($aliases) && in_array($name, $aliases)) || $aliases === $name) {
			$name = $alias;
			break;
		}
	}
	return $name;
}

function image_name($prefix, $req_field)
{
	global $basepath;
	$res = null;
	if (isset($_REQUEST[$req_field])) {
		$dir = $basepath . '/' . $prefix;
		if (!is_dir($dir))
			FileUtil::makeDirectory($dir);
		$name = rawurldecode($_REQUEST[$req_field]);
		$name = get_alias(function_exists('mb_strtolower')
			? mb_strtolower($name, 'utf-8')
			: strtolower($name));
		$res = $dir . '/' . $name . '.png';
		if (!strlen($name)
			|| strlen($name) > 200
			|| strpos($res, '/./') !== false
			|| strpos($res, '/../') !== false
			|| strpos($res, '//') !== false)
			// restrict to sane sub-directory paths
			bad('Invalid ' . $req_field . ': '.$name);
	}
	return $res;
}

$png_name = image_name('labels', 'label');
if ($png_name === null)
	$png_name = image_name('trackers', 'tracker');

if ($png_name !== null) {
	$targetdir = dirname($png_name);
	if (isset($_POST['delete'])) {
		@unlink($png_name);
		// delete empty sub-directories
		while (Utility::str_starts_with(dirname($targetdir), $basepath.'/') && @rmdir($targetdir)) {
			$targetdir = dirname($targetdir);
		}
		exit;
	} else if (isset($_POST['upload'])) {
		$filename = $_FILES['uploadfile']["name"];
		$tempname = $_FILES['uploadfile']["tmp_name"];

		if (!is_dir($targetdir))
			FileUtil::makeDirectory($targetdir);
		if (!is_dir($targetdir))
			bad('Failed to create dir: ' . $targetdir);
		if (mime_content_type($tempname) !== 'image/png')
			bad('Only image/png supported!');
		if (!move_uploaded_file($tempname, $png_name))
			bad('Image upload failed!');
		exit;
	} else {
		try_send_image($png_name);
		try_send_image(dirname(__FILE__).substr($png_name, strlen($basepath)));


		if (!isset($_REQUEST["label"]) && isset($_REQUEST["tracker"])) {
			$tracker = basename($png_name, '.png');
			$ico_name = $targetdir . '/' . $tracker . '.ico';
			try_send_image($ico_name, 'image/x-icon');
			try_send_image(dirname(__FILE__).substr($ico_name, strlen($basepath)), 'image/x-icon');

			ignore_user_abort(true);
			set_time_limit(0);

			$client = new Snoopy();
			$client->read_timeout = 5;
			$client->_fp_timeout = 5;
			foreach (['favicon.ico', 'favicon.png'] as $favicon) {
				$url = $client->linkencode("http://{$tracker}/{$favicon}");
				@$client->fetchComplex($url);
				if ($client->status == 200) {
					file_put_contents($ico_name, $client->results);
					if (strpos(mime_content_type($ico_name), "image/")===false)
						@unlink($ico_name);
					else
						try_send_image($ico_name, 'image/x-icon');
				}
			}
		}
	}
}

SendFile::send('./trackers/unknown.png', 'image/png');
