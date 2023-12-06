<?php

if(empty($pathToExternals['python']))	// May be path already defined?
{
	$pathToExternals['python'] = '';// Something like /usr/bin/python. If empty, will be found in PATH.
}

$recaptcha_solving_enabled = false;

//For using recaptcha solver plugin with cloudscaper. Needed fields in https://github.com/VeNoMouS/cloudscraper
//Need python library: python_anticaptcha

$cloudscraper_recaptcha = array
(
	"provider" => "",
	"api_key" => "",
	"username" => "",
	"password" => ""
);
