<?php

if(empty($pathToExternals['python']))	// May be path already defined?
{
	$pathToExternals['python'] = '';// Something like /usr/bin/python. If empty, will be found in PATH.
}
//For using recaptcha solver plugin with cloudscaper. Needed fields in https://github.com/VeNoMouS/cloudscraper
//Need python library: python_anticaptcha
//
//$recaptcha_solving_enabled = true
//$cloudscraper_recaptcha = array(
// "provider" => "",
// "api_key" => "",
// "username" => "",
// "password" => ""
// );
