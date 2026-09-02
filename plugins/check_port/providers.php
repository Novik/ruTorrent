<?php
require_once(dirname(__FILE__) . "/providers/yougetsignal.php");
require_once(dirname(__FILE__) . "/providers/portchecker.php");
require_once(dirname(__FILE__) . "/providers/globalping.php");

$checkPortProviders = [
	"yougetsignal" => [
		"function" => "check_port_yougetsignal",
		"ipv4" => true,
		"ipv6" => false,
	],
	"portchecker" => [
		"function" => "check_port_portchecker",
		"ipv4" => true,
		"ipv6" => true,
	],
	"globalping" => [
		"function" => "check_port_globalping",
		"ipv4" => true,
		"ipv6" => true,
	],
];
