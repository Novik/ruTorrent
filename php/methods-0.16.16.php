<?php

// rtorrent >= 0.16.16: override aliases from methods-0.9.4.php and
// methods-0.16.0.php for commands deprecated/removed in v0.16.16+.
//
// Proxy addresses, open-socket queries, and d.multicall2 were removed.
// network.http.max_total_connections.set and network.max_open_files.set
// now log deprecation warnings — use system.sockets.* equivalents.
//
// Loaded from settings.php when iVersion >= 0x1010.

$this->aliases = array_merge($this->aliases, array(

	// HTTP connection cap → system.sockets.http.max_alloc (ceiling, not floor)
	// (was network.http.max_total_connections — now warns)
	"get_max_open_http" => array( "name"=>"system.sockets.http.max_alloc",     "prm"=>0 ),
	"set_max_open_http" => array( "name"=>"system.sockets.http.max_alloc.set", "prm"=>1 ),

	// Max open files setter → system.sockets.files.max_alloc.set
	// (was network.max_open_files.set — now warns)
	"set_max_open_files" => array( "name"=>"system.sockets.files.max_alloc.set", "prm"=>1 ),

	// d.multicall2 removed; d.multicall is canonical
	"d.multicall2" => array( "name"=>"d.multicall",  "prm"=>1 ),

	// Proxy addresses — removed in 0.16.16
	"get_http_proxy"    => array( "name"=>"network.proxy.http",      "prm"=>0 ),
	"set_http_proxy"    => array( "name"=>"network.proxy.http.set",  "prm"=>1 ),
	"http_proxy"        => array( "name"=>"network.proxy.http",      "prm"=>0 ),
	"get_proxy_address" => array( "name"=>"network.proxy.global",     "prm"=>0 ),
	"set_proxy_address" => array( "name"=>"network.proxy.global.set", "prm"=>1 ),

	// Socket queries — removed in 0.16.16
	"get_max_open_sockets"     => array( "name"=>"system.sockets.max_size", "prm"=>0 ),
	"network.open_sockets"     => array( "name"=>"system.sockets.size",     "prm"=>0 ),
	"network.max_open_sockets" => array( "name"=>"system.sockets.max_size", "prm"=>0 ),

));
