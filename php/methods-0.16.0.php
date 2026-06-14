<?php

// rtorrent 0.16.x: deprecated command aliases (schedule2, schedule_remove2,
// execute2, network.http.max_open, throttle.ip, dht.throttle.name, ...) are
// gated behind method.use_deprecated, which since 0.16.14 cannot be enabled
// from the rc file — only via the -D launch flag. Stock 0.16.14 therefore
// does NOT register these aliases, so anything sending them faults with
// "Method '<name>' not defined".
//
// This file overrides the offending entries from methods-0.9.4.php with
// the canonical command names that exist on every 0.16.x build. Loaded
// from settings.php when iVersion >= 0x1000.

$this->aliases = array_merge($this->aliases, array(

	// Scheduling
	"schedule"        => array( "name"=>"schedule",        "prm"=>1 ),
	"schedule_remove" => array( "name"=>"schedule.remove", "prm"=>1 ),

	// Command execution
	"execute"         => array( "name"=>"execute",         "prm"=>1 ),

	// HTTP connection cap (was network.http.max_open in 0.9.x)
	"get_max_open_http" => array( "name"=>"network.http.max_total_connections",     "prm"=>0 ),
	"set_max_open_http" => array( "name"=>"network.http.max_total_connections.set", "prm"=>1 ),

	// Per-group ratio commands — bare seeding group remap (0.10.2 map sent
	// group2.seeding.* which is gone in 0.16). Use group.seeding.* now.
	"ratio.min"         => array( "name"=>"group.seeding.ratio.min",         "prm"=>0 ),
	"ratio.max"         => array( "name"=>"group.seeding.ratio.max",         "prm"=>0 ),
	"ratio.upload"      => array( "name"=>"group.seeding.ratio.upload",      "prm"=>0 ),
	"ratio.min.set"     => array( "name"=>"group.seeding.ratio.min.set",     "prm"=>1 ),
	"ratio.max.set"     => array( "name"=>"group.seeding.ratio.max.set",     "prm"=>1 ),
	"ratio.upload.set"  => array( "name"=>"group.seeding.ratio.upload.set",  "prm"=>1 ),

));
