<?php
// Command line only: it moves directories it is told to, so a web request
// must never reach it (see env_check.php). A refusal fails, so that the
// unpack script keeps what was extracted and the archives when
// $pathToExternals['php'] names another PHP, such as php-cgi.
if(PHP_SAPI !== 'cli')
{
	http_response_code(403);
	echo "place.php can only be run from the command line\n";
	exit(1);
}

// Run by the unpack scripts (stage.sh):
// place.php <how> <hash> <user> place <private directory> <output> <links the extractor skipped>
// place.php <how> <hash> <user> refuse <reason> <name>
if((count($argv) < 7) || !in_array($argv[4], array('place', 'refuse'), true) ||
	(($argv[4] === 'place') && (count($argv) < 8)))
{
	fwrite(STDERR, "usage: place.php how hash user place directory output skipped | refuse reason name\n");
	exit(2);
}
if($argv[3] !== '')
	$_SERVER['REMOTE_USER'] = $argv[3];
require_once( dirname(__FILE__)."/unpack.php" );
if($argv[4] !== 'place')
{
	rUnpack::report($argv[1], $argv[2], $argv[5], $argv[6]);
	exit(1);
}
foreach(rUnpack::PLACEMENT_FUNCTIONS as $function)
	if(!function_exists($function))
	{
		rUnpack::report($argv[1], $argv[2], "check-unavailable", $function);
		exit(1);
	}
exit(rUnpack::place($argv[1], $argv[2], $argv[5], $argv[6], (int)$argv[7]));
