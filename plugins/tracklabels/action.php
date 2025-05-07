<?php

require_once( dirname(__FILE__)."/../../php/util.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc" );

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

function image_name($prefix, $req_field)
{
	global $basepath;
	$res = null;
	if (isset($_REQUEST[$req_field])) {
		$dir = $basepath . '/' . $prefix;
		if (!is_dir($dir))
			FileUtil::makeDirectory($dir);
		$name = function_exists('mb_strtolower')
			? mb_strtolower(rawurldecode($_REQUEST[$req_field]), 'utf-8')
			: strtolower(rawurldecode($_REQUEST[$req_field]));
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

			$url = Snoopy::linkencode("http://".$tracker."/favicon.ico");
			$client = new Snoopy();
			$client->read_timeout = 5;
			$client->_fp_timeout = 5;
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

SendFile::send('./trackers/unknown.png', 'image/png');
