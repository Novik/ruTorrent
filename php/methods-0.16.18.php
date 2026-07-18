<?php

// rtorrent >= 0.16.18: the network port commands were renamed/removed in
// src/main.cc. network.port_range / network.port_random (and their .set forms)
// became CMD_REDIRECT aliases to network.listen.port.range / .random, and
// network.port_open became a deprecated no-op ("does nothing"). All of these
// live inside the "if (method.use_deprecated)" block, which since 0.16.14 can
// only be enabled at startup via the -D launch flag (not from the rc) -- and on
// 0.16.18 -D itself aborts at startup (a redirect-ordering bug in main.cc). We
// launch without -D, so the old names are absent at runtime and anything
// sending them faults with "Method '...' not defined"; batched in the settings
// dialog, one such fault blanks the whole panel.
//
// Remap the port getters/setters to the canonical, always-present names.
// network.port_open has no replacement (it did nothing on modern rtorrent), so
// route it to the harmless no-op "cat" so the settings batch and the save don't
// fault -- the checkbox stays but is inert, exactly as it already was.
//
// Loaded from settings.php when iVersion >= 0x1012.

$this->aliases = array_merge($this->aliases, array(

	"get_port_range"  => array( "name"=>"network.listen.port.range",      "prm"=>0 ),
	"set_port_range"  => array( "name"=>"network.listen.port.range.set",  "prm"=>1 ),
	"get_port_random" => array( "name"=>"network.listen.port.random",     "prm"=>0 ),
	"set_port_random" => array( "name"=>"network.listen.port.random.set", "prm"=>1 ),

	"get_port_open"   => array( "name"=>"cat", "prm"=>0 ),
	"set_port_open"   => array( "name"=>"cat", "prm"=>1 ),
	"port_open"       => array( "name"=>"cat", "prm"=>0 ),

));
